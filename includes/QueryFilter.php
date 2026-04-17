<?php
declare(strict_types=1);

namespace SequentialPostsBlock;

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
 */
final class QueryFilter
{
    private const NAMESPACE = 'sequential-posts-block/query';
    private const MIN_COUNT = 1;
    private const MAX_COUNT = 10;

    /** @var bool Whether the currently-rendering core/query is our variation. */
    private static bool $is_sequential = false;

    /** @var string Native orderBy from the block ('date' or 'title'). */
    private static string $orderby = 'date';

    /** @var string Native order from the block ('asc' or 'desc'). */
    private static string $order = 'asc';

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
        if (($parsed_block['attrs']['namespace'] ?? '') !== self::NAMESPACE) {
            return $pre_render;
        }

        if ((new ContextDetector())->current_post_id() === null) {
            return '';
        }

        self::$is_sequential = true;
        self::$orderby = (string) ($parsed_block['attrs']['query']['orderBy'] ?? 'date');
        self::$order = (string) ($parsed_block['attrs']['query']['order'] ?? 'asc');

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

        $current_id = (new ContextDetector())->current_post_id();
        if ($current_id === null) {
            return array_merge($query, ['post__in' => [0], 'posts_per_page' => 1]);
        }

        $post_type = (string) ($block->context['query']['postType'] ?? 'post');
        $raw_count = (int) ($block->context['query']['perPage'] ?? 3);
        $count = max(self::MIN_COUNT, min(self::MAX_COUNT, $raw_count));

        // Build canonical list with the user's chosen sort order.
        // Resolver always goes forward — the list order defines "next".
        $all_ids = CanonicalList::get($post_type, self::$orderby, self::$order);
        $resolved = (new SequentialResolver())->resolve($all_ids, $current_id, 'asc', $count);

        if (empty($resolved)) {
            return array_merge($query, ['post__in' => [0], 'posts_per_page' => 1]);
        }

        return array_merge($query, [
            'post__in' => $resolved,
            'orderby' => 'post__in',
            'order' => 'ASC',
            'posts_per_page' => count($resolved),
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
        $context_post = (int) $request->get_param('sequential_context_post');
        if (!$context_post) {
            return $args;
        }

        $orderby = (string) ($request->get_param('sequential_orderby') ?? 'date');
        $order = (string) ($request->get_param('sequential_order') ?? 'asc');
        $raw_count = (int) ($args['posts_per_page'] ?? 3);
        $count = max(self::MIN_COUNT, min(self::MAX_COUNT, $raw_count));

        $all_ids = CanonicalList::get($post_type, $orderby, $order);
        $resolved = (new SequentialResolver())->resolve($all_ids, $context_post, 'asc', $count);

        return array_merge($args, [
            'post__in' => $resolved ?: [0],
            'orderby' => 'post__in',
            'order' => 'ASC',
        ]);
    }
}
