<?php
declare(strict_types=1);

namespace NextPostsBlock\Tests\Integration;

use NextPostsBlock\CanonicalList;
use WP_UnitTestCase;

final class CanonicalListTest extends WP_UnitTestCase
{
	/** @var int[] */
	private array $post_ids = [];

	/** @var int[] */
	private array $sticky_ids = [];

	protected function setUp(): void
	{
		parent::setUp();

		foreach (
			get_posts([
				'post_type' => 'post',
				'post_status' => 'any',
				'posts_per_page' => -1,
				'fields' => 'ids',
			]) as $id
		) {
			wp_delete_post($id, true);
		}

		$fixtures = [
			['Alpha',   '2024-01-01 00:00:00', 'publish'],
			['Bravo',   '2024-01-02 00:00:00', 'publish'],
			['Charlie', '2024-01-03 00:00:00', 'publish'],
			['Delta',   '2024-01-04 00:00:00', 'publish'],
			['Echo',    '2024-01-05 00:00:00', 'draft'],
			['Foxtrot', '2024-01-06 00:00:00', 'publish'],
			['Golf',    '2024-01-07 00:00:00', 'publish'],
			['Hotel',   '2024-01-08 00:00:00', 'publish'],
		];
		foreach ($fixtures as [$title, $date, $status]) {
			$this->post_ids[] = self::factory()->post->create([
				'post_title' => $title,
				'post_date' => $date,
				'post_date_gmt' => $date,
				'post_status' => $status,
			]);
		}

		$this->sticky_ids = [$this->post_ids[1], $this->post_ids[6]];
		update_option('sticky_posts', $this->sticky_ids);

		wp_cache_flush();
	}

	protected function tearDown(): void
	{
		delete_option('sticky_posts');
		parent::tearDown();
	}

	public function test_returns_published_ids_ordered_by_date_asc_by_default(): void
	{
		$expected = [
			$this->post_ids[0], // Alpha   2024-01-01
			$this->post_ids[1], // Bravo   2024-01-02
			$this->post_ids[2], // Charlie 2024-01-03
			$this->post_ids[3], // Delta   2024-01-04
			$this->post_ids[5], // Foxtrot 2024-01-06 (Echo @ 2024-01-05 is draft)
			$this->post_ids[6], // Golf    2024-01-07
			$this->post_ids[7], // Hotel   2024-01-08
		];
		$this->assertSame($expected, CanonicalList::get('post'));
	}

	public function test_build_with_empty_attrs_returns_all_published_post_ids_in_natural_order(): void
	{
		$result = CanonicalList::build(['postType' => 'post', 'sticky' => 'ignore']);
		$expected = [
			$this->post_ids[0], // Alpha
			$this->post_ids[1], // Bravo (sticky)
			$this->post_ids[2], // Charlie
			$this->post_ids[3], // Delta
			$this->post_ids[5], // Foxtrot (Echo @ draft excluded)
			$this->post_ids[6], // Golf (sticky)
			$this->post_ids[7], // Hotel
		];
		$this->assertSame($expected, $result);
	}

	public function test_build_with_sticky_include_prepends_stickies_in_orderby(): void
	{
		// orderBy date ASC → base order Alpha, Bravo*, Charlie, Delta, Foxtrot, Golf*, Hotel
		// Include mode → stickies first (Bravo, Golf), then non-stickies in natural order.
		$result = CanonicalList::build(['postType' => 'post', 'sticky' => '']);
		$expected = [
			$this->post_ids[1], // Bravo   (sticky)
			$this->post_ids[6], // Golf    (sticky)
			$this->post_ids[0], // Alpha
			$this->post_ids[2], // Charlie
			$this->post_ids[3], // Delta
			$this->post_ids[5], // Foxtrot
			$this->post_ids[7], // Hotel
		];
		$this->assertSame($expected, $result);
	}

	public function test_build_with_sticky_include_and_no_stickies_is_identical_to_ignore(): void
	{
		delete_option('sticky_posts');
		wp_cache_flush();

		$include = CanonicalList::build(['postType' => 'post', 'sticky' => '']);
		$ignore  = CanonicalList::build(['postType' => 'post', 'sticky' => 'ignore']);
		$this->assertSame($ignore, $include);
	}

	public function test_build_with_sticky_exclude_removes_sticky_ids(): void
	{
		$result = CanonicalList::build(['postType' => 'post', 'sticky' => 'exclude']);
		$expected = [
			$this->post_ids[0], // Alpha
			$this->post_ids[2], // Charlie
			$this->post_ids[3], // Delta
			$this->post_ids[5], // Foxtrot
			$this->post_ids[7], // Hotel
		];
		$this->assertSame($expected, $result);
	}

	public function test_build_with_sticky_only_returns_sticky_ids_in_orderby(): void
	{
		$result = CanonicalList::build(['postType' => 'post', 'sticky' => 'only']);
		$expected = [
			$this->post_ids[1], // Bravo (sticky, 2024-01-02)
			$this->post_ids[6], // Golf  (sticky, 2024-01-07)
		];
		$this->assertSame($expected, $result);
	}

	public function test_build_with_sticky_only_and_no_stickies_returns_empty(): void
	{
		delete_option('sticky_posts');
		wp_cache_flush();

		$this->assertSame([], CanonicalList::build(['postType' => 'post', 'sticky' => 'only']));
	}

	public function test_excludes_draft_posts(): void
	{
		$this->assertNotContains($this->post_ids[4], CanonicalList::get('post'));
	}

	public function test_returns_empty_for_nonexistent_post_type(): void
	{
		$this->assertSame([], CanonicalList::get('no_such_type'));
	}

	public function test_second_call_uses_cache_no_extra_query(): void
	{
		global $wpdb;

		CanonicalList::get('post');
		$queries_before = $wpdb->num_queries;

		CanonicalList::get('post');
		$this->assertSame($queries_before, $wpdb->num_queries);
	}

	public function test_cache_invalidates_when_post_is_created(): void
	{
		$before = CanonicalList::get('post');

		$new_id = self::factory()->post->create([
			'post_title' => 'Zulu',
			'post_date' => '2024-01-09 00:00:00',
			'post_date_gmt' => '2024-01-09 00:00:00',
			'post_status' => 'publish',
		]);

		$after = CanonicalList::get('post');

		$this->assertNotSame($before, $after);
		$this->assertContains($new_id, $after);
		$this->assertSame($new_id, end($after), 'New latest post should be last in date-ASC order.');
	}

	public function test_respects_orderby_title_and_order_desc(): void
	{
		// Published titles: Alpha, Bravo, Charlie, Delta, Foxtrot, Golf, Hotel
		$expected = [
			$this->post_ids[7], // Hotel
			$this->post_ids[6], // Golf
			$this->post_ids[5], // Foxtrot
			$this->post_ids[3], // Delta
			$this->post_ids[2], // Charlie
			$this->post_ids[1], // Bravo
			$this->post_ids[0], // Alpha
		];
		$this->assertSame($expected, CanonicalList::get('post', 'title', 'DESC'));
	}

	public function test_exclude_sticky_removes_sticky_ids_from_list(): void
	{
		$result = CanonicalList::get('post', 'date', 'ASC', true);

		foreach ($this->sticky_ids as $sticky_id) {
			$this->assertNotContains($sticky_id, $result);
		}
		$this->assertContains($this->post_ids[0], $result, 'Non-sticky posts should remain.');
	}

	public function test_cache_keys_distinct_between_with_and_without_sticky(): void
	{
		global $wpdb;

		$with_sticky = CanonicalList::get('post', 'date', 'ASC', false);
		$queries_between = $wpdb->num_queries;

		$without_sticky = CanonicalList::get('post', 'date', 'ASC', true);
		$this->assertGreaterThan(
			$queries_between,
			$wpdb->num_queries,
			'exclude_sticky=true must bypass the exclude_sticky=false cache entry.'
		);

		$this->assertNotSame($with_sticky, $without_sticky);
	}
}
