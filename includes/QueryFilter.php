<?php
declare(strict_types=1);

namespace NextPostsBlock;

use WP_Block;
use WP_REST_Request;

/**
 * Server-side hooks that rewrite a core/query block's SQL args when it
 * carries our variation namespace.
 *
 * Uses the native Query Loop orderBy/order settings to build the canonical
 * list. The resolver always traverses forward — the list sort order itself
 * determines what "next" means (chronological, reverse-chrono, A-Z, Z-A).
 *
 * Architecture: pre_render_block sets a static flag for the parent
 * core/query block. query_loop_block_query_vars reads and consumes it.
 * See previous commit messages for the full rationale.
 *
 * Non-singular fallback (v1.1.0): when no current post can be detected
 * (archive / home / search), the block renders the first N items of the
 * canonical list in the chosen sort order instead of returning empty.
 *
 * Sticky neutralization (v1.2.0): always forces `ignore_sticky_posts = 1`
 * and clears `post__not_in` (except when our own excludeSticky flag
 * expands the canonical list filter). This prevents WP_Query from
 * prepending sticky posts to our result set (which would inflate the
 * count beyond perPage) and prevents the native "Sticky posts" control
 * from silently filtering our resolved IDs.
 */
final class QueryFilter
{
    private const NAMESPACE = 'next-posts-block/query';
    private const MIN_COUNT = 1;
    private const MAX_COUNT = 10;

    /** @var bool Whether the currently-rendering core/query is our variation. */
    private static bool $is_sequential = false;

    /** @var string Native orderBy from the block ('date' or 'title'). */
    private static string $orderby = 'date';

    /** @var string Native order from the block ('asc' or 'desc'). */
    private static string $order = 'asc';

    /** @var bool Custom excludeSticky attribute from the block. */
    private static bool $exclude_sticky = false;

    /**
     * Hook: pre_render_block (priority 10, 2 args)
     *
     * Fires for the parent core/query block BEFORE its children render.
     *
     * @param mixed                $pre_render
     * @param array<string, mixed> $parsed_block
     * @return mixed
     */
    public function pre_render($pre_render, array $parsed_block)
    {
        if (($parsed_block['blockName'] ?? '') !== 'core/query') {
            return $pre_render;
        }

        // Reset after the blockName check: we still want a non-matching core/query
        // (unrelated sibling) to clear any state leaked from a previous armed-but-
        // not-consumed core/query, but inner blocks (core/post-template, etc.) must
        // not wipe the state we just armed, as they fire pre_render_block between
        // our core/query's pre_render and query_loop_block_query_vars consumption.
        self::$is_sequential = false;
        self::$exclude_sticky = false;

        if (($parsed_block['attrs']['namespace'] ?? '') !== self::NAMESPACE) {
            return $pre_render;
        }

        self::$is_sequential = true;
        self::$orderby = (string) ($parsed_block['attrs']['query']['orderBy'] ?? 'date');
        self::$order = (string) ($parsed_block['attrs']['query']['order'] ?? 'asc');
        self::$exclude_sticky = (bool) ($parsed_block['attrs']['query']['excludeSticky'] ?? false);

        return $pre_render;
    }

    /**
     * Hook: query_loop_block_query_vars (priority 10, 3 args)
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function filter_query_vars(array $query, WP_Block $block, int $page): array
    {
        if (!self::$is_sequential) {
            return $query;
        }

        self::$is_sequential = false;
        $exclude_sticky = self::$exclude_sticky;
        self::$exclude_sticky = false;

        $post_type = (string) ($block->context['query']['postType'] ?? 'post');
        $raw_count = (int) ($block->context['query']['perPage'] ?? 3);
        $count = max(self::MIN_COUNT, min(self::MAX_COUNT, $raw_count));

        // Build canonical list with the user's chosen sort order.
        // Resolver always goes forward — the list order defines "next".
        $all_ids = CanonicalList::get($post_type, self::$orderby, self::$order, $exclude_sticky);

        $current_id = (new ContextDetector())->current_post_id();
        $resolved = $current_id === null
            ? array_slice($all_ids, 0, $count)
            : (new SequentialResolver())->resolve($all_ids, $current_id, 'asc', $count);

        if (empty($resolved)) {
            return array_merge($query, [
                'post__in' => [0],
                'post__not_in' => [],
                'posts_per_page' => 1,
                'ignore_sticky_posts' => 1,
            ]);
        }

        return array_merge($query, [
            'post__in' => $resolved,
            'post__not_in' => [],
            'orderby' => 'post__in',
            'order' => 'ASC',
            'posts_per_page' => count($resolved),
            'ignore_sticky_posts' => 1,
        ]);
    }

    /**
     * Hook: rest_{$post_type}_query
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function filter_rest_query(string $post_type, array $args, WP_REST_Request $request): array
    {
        $is_sequential_block = (bool) $request->get_param('sequential_block');
        $context_post = (int) $request->get_param('sequential_context_post');

        // Marker absent AND no context post → not our request.
        if (!$is_sequential_block && !$context_post) {
            return $args;
        }

        $orderby = (string) ($request->get_param('sequential_orderby') ?? 'date');
        $order = (string) ($request->get_param('sequential_order') ?? 'asc');
        $exclude_sticky = (bool) $request->get_param('sequential_exclude_sticky');
        $raw_count = (int) ($args['posts_per_page'] ?? 3);
        $count = max(self::MIN_COUNT, min(self::MAX_COUNT, $raw_count));

        $all_ids = CanonicalList::get($post_type, $orderby, $order, $exclude_sticky);
        $resolved = $context_post
            ? (new SequentialResolver())->resolve($all_ids, $context_post, 'asc', $count)
            : array_slice($all_ids, 0, $count);

        return array_merge($args, [
            'post__in' => $resolved ?: [0],
            'post__not_in' => [],
            'orderby' => 'post__in',
            'order' => 'ASC',
            'ignore_sticky_posts' => 1,
        ]);
    }
}
