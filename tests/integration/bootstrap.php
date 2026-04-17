<?php
/**
 * Integration test bootstrap: boots wp-phpunit, loads this plugin as a
 * must-use plugin, then requires Composer autoload so yoast/phpunit-polyfills
 * can wrap the PHPUnit class aliases.
 */
declare(strict_types=1);

$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
	$_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

if (!file_exists($_tests_dir . '/includes/functions.php')) {
	fwrite(
		STDERR,
		"Could not find {$_tests_dir}/includes/functions.php. Run bin/install-wp-tests.sh first." . PHP_EOL
	);
	exit(1);
}

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter('muplugins_loaded', static function (): void {
	require dirname(__DIR__, 2) . '/sequential-posts-block.php';
});

require $_tests_dir . '/includes/bootstrap.php';

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
