<?php
/**
 * Test bootstrap: loads Composer autoload for unit tests.
 *
 * Integration tests are deferred to a later milestone; this bootstrap
 * intentionally does not load wp-phpunit.
 */
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
