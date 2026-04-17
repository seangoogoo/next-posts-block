<?php
declare(strict_types=1);

namespace NextPostsBlock\Tests\Integration;

use NextPostsBlock\ContextDetector;
use WP_UnitTestCase;

/**
 * Order-sensitive: the REST test defines the REST_REQUEST constant, which
 * PHP cannot un-define. It MUST stay the last declared test in this class
 * so the leaked constant cannot poison the is_singular / is_admin branches.
 */
final class ContextDetectorTest extends WP_UnitTestCase
{
	private ContextDetector $detector;

	protected function setUp(): void
	{
		parent::setUp();
		$this->detector = new ContextDetector();
		unset($_GET['sequential_context_post'], $GLOBALS['post']);
	}

	protected function tearDown(): void
	{
		unset($_GET['sequential_context_post'], $GLOBALS['post']);
		parent::tearDown();
	}

	public function test_singular_view_returns_queried_post_id(): void
	{
		$post_id = self::factory()->post->create(['post_status' => 'publish']);
		$this->go_to(get_permalink($post_id));

		$this->assertTrue(is_singular(), 'Fixture URL should resolve to a singular view.');
		$this->assertSame($post_id, $this->detector->current_post_id());
	}

	public function test_home_returns_null(): void
	{
		$this->go_to(home_url('/'));

		$this->assertFalse(is_singular());
		$this->assertNull($this->detector->current_post_id());
	}

	public function test_search_results_return_null(): void
	{
		$this->go_to(home_url('/?s=anything'));

		$this->assertTrue(is_search(), 'Fixture URL should resolve to a search query.');
		$this->assertNull($this->detector->current_post_id());
	}

	public function test_classic_admin_context_returns_global_post_id(): void
	{
		$post_id = self::factory()->post->create(['post_status' => 'publish']);
		set_current_screen('edit-post');
		$GLOBALS['post'] = get_post($post_id);

		$this->assertTrue(is_admin());
		$this->assertFalse(is_singular(), 'Admin context must not also look singular.');
		$this->assertSame($post_id, $this->detector->current_post_id());
	}

	/**
	 * MUST remain the last test in this class — see class docblock.
	 */
	public function test_rest_request_reads_sequential_context_post_param(): void
	{
		$post_id = self::factory()->post->create(['post_status' => 'publish']);
		$_GET['sequential_context_post'] = (string) $post_id;

		if (!defined('REST_REQUEST')) {
			define('REST_REQUEST', true);
		}

		$this->assertFalse(is_singular(), 'REST branch must not be shadowed by a singular match.');
		$this->assertSame($post_id, $this->detector->current_post_id());
	}
}
