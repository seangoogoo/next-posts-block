<?php
declare(strict_types=1);

namespace NextPostsBlock;

/**
 * Wires the plugin's hooks into WordPress.
 *
 * Instantiated on plugins_loaded by the bootstrap file.
 */
final class Plugin
{
    public function boot(): void
    {
        add_action('init', [$this, 'load_textdomain']);
        add_action('init', [$this, 'register_frontend_hooks']);
        add_action('rest_api_init', [$this, 'register_rest_hooks']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);
    }

    public function load_textdomain(): void
    {
        load_plugin_textdomain(
            'next-posts-block',
            false,
            dirname(plugin_basename(NEXT_POSTS_BLOCK_PATH)) . '/languages'
        );
    }

    /**
     * Frontend / editor-render hooks.
     *
     * pre_render_block is critical: it fires for the parent core/query block
     * and sets a static flag that query_loop_block_query_vars later reads.
     * See QueryFilter class docblock for the full execution order.
     */
    public function register_frontend_hooks(): void
    {
        $filter = new QueryFilter();

        add_filter('pre_render_block', [$filter, 'pre_render'], 10, 2);
        add_filter('query_loop_block_query_vars', [$filter, 'filter_query_vars'], 10, 3);
    }

    /**
     * REST filters registered on rest_api_init so all CPTs are available.
     */
    public function register_rest_hooks(): void
    {
        $filter = new QueryFilter();

        foreach (get_post_types(['public' => true]) as $post_type) {
            add_filter(
                "rest_{$post_type}_query",
                static function (array $args, \WP_REST_Request $request) use ($filter, $post_type): array {
                    return $filter->filter_rest_query($post_type, $args, $request);
                },
                10,
                2
            );
        }
    }

    public function enqueue_editor_assets(): void
    {
        $asset_file = NEXT_POSTS_BLOCK_PATH . 'build/index.asset.php';
        if (!file_exists($asset_file)) {
            return;
        }
        $asset = require $asset_file;

        wp_enqueue_script(
            'next-posts-block-editor',
            NEXT_POSTS_BLOCK_URL . 'build/index.js',
            $asset['dependencies'] ?? [],
            $asset['version'] ?? NEXT_POSTS_BLOCK_VERSION,
            true
        );

        wp_set_script_translations(
            'next-posts-block-editor',
            'next-posts-block',
            NEXT_POSTS_BLOCK_PATH . 'languages'
        );
    }
}
