<?php
declare(strict_types=1);

namespace SequentialPostsBlock;

/**
 * Determines the "current post" across three contexts:
 *  - Frontend singular view (is_singular())
 *  - REST request (editor preview) via sequential_context_post query param
 *  - Classic admin editor via global $post
 *
 * Returns null on archives, home, search, or any context without a concrete
 * post — callers should short-circuit rendering when null.
 */
final class ContextDetector
{
    public function current_post_id(): ?int
    {
        if (is_singular()) {
            $id = get_queried_object_id();
            return $id ? (int) $id : null;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            $from_query = filter_input(INPUT_GET, 'sequential_context_post', FILTER_VALIDATE_INT);
            return $from_query ?: null;
        }

        if (is_admin() && !empty($GLOBALS['post']) && $GLOBALS['post'] instanceof \WP_Post) {
            return (int) $GLOBALS['post']->ID;
        }

        return null;
    }
}
