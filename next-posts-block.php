<?php
/**
 * Plugin Name:       Next Posts — Query Loop Block
 * Description:       Query Loop variation that displays posts sequentially relative to the current post, with wrap-around. Supports ordering by date or title.
 * Version:           1.2.2
 * Requires at least: 6.1
 * Requires PHP:      7.4
 * Author:            Jensen SIU
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       next-posts-block
 * Domain Path:       /languages
 *
 * @package NextPostsBlock
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('NEXT_POSTS_BLOCK_VERSION', '1.2.2');
define('NEXT_POSTS_BLOCK_PATH', plugin_dir_path(__FILE__));
define('NEXT_POSTS_BLOCK_URL', plugin_dir_url(__FILE__));

// Composer autoload (when vendor/ exists)
if (file_exists(NEXT_POSTS_BLOCK_PATH . 'vendor/autoload.php')) {
    require_once NEXT_POSTS_BLOCK_PATH . 'vendor/autoload.php';
}

// Fallback manual autoload for PSR-4 NextPostsBlock\ namespace
spl_autoload_register(static function (string $class): void {
    $prefix = 'NextPostsBlock\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = NEXT_POSTS_BLOCK_PATH . 'includes/' . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

add_action('plugins_loaded', static function (): void {
    (new \NextPostsBlock\Plugin())->boot();
});
