<?php
declare(strict_types=1);

namespace NextPostsBlock\Tests\Integration;

use ReflectionProperty;
use NextPostsBlock\QueryFilter;
use WP_Block;
use WP_REST_Request;
use WP_UnitTestCase;

final class QueryFilterTest extends WP_UnitTestCase
{
	private QueryFilter $filter;

	/** @var int[] Canonical-ordered (date ASC) post IDs. */
	private array $post_ids = [];

	/** @var int[] */
	private array $sticky_ids = [];

	protected function setUp(): void
	{
		parent::setUp();

		$this->filter = new QueryFilter();
		$this->reset_static_state();

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

		$dates = [
			'2024-01-01 00:00:00',
			'2024-01-02 00:00:00',
			'2024-01-03 00:00:00',
			'2024-01-04 00:00:00',
			'2024-01-05 00:00:00',
			'2024-01-06 00:00:00',
		];
		foreach ($dates as $i => $date) {
			$this->post_ids[] = self::factory()->post->create([
				'post_title' => 'Post ' . ($i + 1),
				'post_date' => $date,
				'post_date_gmt' => $date,
				'post_status' => 'publish',
			]);
		}

		$this->sticky_ids = [$this->post_ids[1], $this->post_ids[4]];
		update_option('sticky_posts', $this->sticky_ids);

		wp_cache_flush();
	}

	protected function tearDown(): void
	{
		delete_option('sticky_posts');
		$this->reset_static_state();
		parent::tearDown();
	}

	// ------------------------------------------------------------------
	// pre_render
	// ------------------------------------------------------------------

	public function test_pre_render_ignores_non_query_blocks(): void
	{
		$this->filter->pre_render(null, ['blockName' => 'core/paragraph', 'attrs' => []]);

		$this->assertFalse($this->get_static('is_sequential'));
	}

	public function test_pre_render_ignores_non_namespaced_core_query(): void
	{
		$parsed = $this->make_parsed_block();
		$parsed['attrs']['namespace'] = 'something-else';

		$this->filter->pre_render(null, $parsed);

		$this->assertFalse($this->get_static('is_sequential'));
	}

	public function test_pre_render_arms_flag_and_captures_attrs(): void
	{
		$parsed = $this->make_parsed_block();
		$parsed['attrs']['query']['orderBy'] = 'title';
		$parsed['attrs']['query']['order'] = 'desc';
		$parsed['attrs']['query']['excludeSticky'] = true;

		$this->filter->pre_render(null, $parsed);

		$this->assertTrue($this->get_static('is_sequential'));
		$this->assertSame('title', $this->get_static('orderby'));
		$this->assertSame('desc', $this->get_static('order'));
		$this->assertTrue($this->get_static('exclude_sticky'));
	}

	public function test_pre_render_arms_flag_on_non_singular_context(): void
	{
		// v1.1.0: pre_render must arm regardless of context so filter_query_vars
		// can apply the canonical fallback on archives / home / search.
		$this->go_to(home_url('/'));
		$this->assertFalse(is_singular());

		$this->filter->pre_render(null, $this->make_parsed_block());

		$this->assertTrue($this->get_static('is_sequential'));
	}

	public function test_pre_render_resets_armed_state_when_followed_by_non_matching_block(): void
	{
		// v1.2.1: static reset at top of pre_render prevents a prior armed
		// state from leaking into an unrelated sibling core/query.
		$this->filter->pre_render(null, $this->make_parsed_block());
		$this->assertTrue($this->get_static('is_sequential'));

		$this->filter->pre_render(null, ['blockName' => 'core/paragraph', 'attrs' => []]);

		$this->assertFalse($this->get_static('is_sequential'));
		$this->assertFalse($this->get_static('exclude_sticky'));
	}

	// ------------------------------------------------------------------
	// filter_query_vars
	// ------------------------------------------------------------------

	public function test_filter_query_vars_passes_through_when_not_armed(): void
	{
		$input = ['post_type' => 'post', 'posts_per_page' => 5];
		$block = $this->make_child_block();

		$result = $this->filter->filter_query_vars($input, $block, 1);

		$this->assertSame($input, $result);
	}

	public function test_filter_query_vars_singular_rewrites_post_in_and_neutralizes_sticky(): void
	{
		$this->go_to(get_permalink($this->post_ids[2]));
		$this->filter->pre_render(null, $this->make_parsed_block());

		$block = $this->make_child_block(['postType' => 'post', 'perPage' => 3]);
		$result = $this->filter->filter_query_vars(['post__not_in' => [999]], $block, 1);

		$this->assertSame(
			[$this->post_ids[3], $this->post_ids[4], $this->post_ids[5]],
			$result['post__in']
		);
		$this->assertSame('post__in', $result['orderby']);
		$this->assertSame('ASC', $result['order']);
		$this->assertSame(3, $result['posts_per_page']);
		$this->assertSame(1, $result['ignore_sticky_posts']);
		$this->assertSame([], $result['post__not_in']);
	}

	public function test_filter_query_vars_wraps_around_when_current_is_last(): void
	{
		$this->go_to(get_permalink($this->post_ids[5]));
		$this->filter->pre_render(null, $this->make_parsed_block());

		$block = $this->make_child_block(['postType' => 'post', 'perPage' => 3]);
		$result = $this->filter->filter_query_vars([], $block, 1);

		$this->assertSame(
			[$this->post_ids[0], $this->post_ids[1], $this->post_ids[2]],
			$result['post__in']
		);
	}

	public function test_filter_query_vars_non_singular_returns_first_n_of_canonical(): void
	{
		// v1.1.0 fallback.
		$this->go_to(home_url('/'));
		$this->assertFalse(is_singular());

		$this->filter->pre_render(null, $this->make_parsed_block());

		$block = $this->make_child_block(['postType' => 'post', 'perPage' => 3]);
		$result = $this->filter->filter_query_vars([], $block, 1);

		$this->assertSame(
			[$this->post_ids[0], $this->post_ids[1], $this->post_ids[2]],
			$result['post__in']
		);
		$this->assertSame(3, $result['posts_per_page']);
	}

	public function test_filter_query_vars_non_singular_empty_canonical_forces_post_in_zero(): void
	{
		// Empty canonical list (non-existent post type) must still neutralize
		// sticky handling per v1.2.0.
		$this->go_to(home_url('/'));

		$this->filter->pre_render(null, $this->make_parsed_block([
			'attrs' => [
				'namespace' => 'next-posts-block/query',
				'query' => [
					'postType' => 'spb_nonexistent',
					'perPage' => 3,
					'orderBy' => 'date',
					'order' => 'asc',
				],
			],
		]));

		$block = $this->make_child_block(['postType' => 'spb_nonexistent', 'perPage' => 3]);
		$result = $this->filter->filter_query_vars(['post__not_in' => [999]], $block, 1);

		$this->assertSame([0], $result['post__in']);
		$this->assertSame(1, $result['posts_per_page']);
		$this->assertSame(1, $result['ignore_sticky_posts']);
		$this->assertSame([], $result['post__not_in']);
	}

	public function test_filter_query_vars_exclude_sticky_filters_sticky_from_result(): void
	{
		// v1.2.0: query.excludeSticky=true must propagate to CanonicalList::get.
		$this->go_to(home_url('/'));

		$parsed = $this->make_parsed_block();
		$parsed['attrs']['query']['excludeSticky'] = true;
		$parsed['attrs']['query']['perPage'] = 10;
		$this->filter->pre_render(null, $parsed);

		$block = $this->make_child_block(['postType' => 'post', 'perPage' => 10]);
		$result = $this->filter->filter_query_vars([], $block, 1);

		foreach ($this->sticky_ids as $sticky_id) {
			$this->assertNotContains($sticky_id, $result['post__in']);
		}
		// 6 total posts − 2 sticky = 4 non-sticky posts.
		$this->assertCount(4, $result['post__in']);
	}

	// ------------------------------------------------------------------
	// filter_rest_query
	// ------------------------------------------------------------------

	public function test_rest_filter_passes_through_without_marker_or_context(): void
	{
		$request = new WP_REST_Request('GET', '/wp/v2/posts');
		$input = ['posts_per_page' => 3];

		$result = $this->filter->filter_rest_query('post', $input, $request);

		$this->assertSame($input, $result);
	}

	public function test_rest_filter_sequential_resolution_with_context_post(): void
	{
		$request = new WP_REST_Request('GET', '/wp/v2/posts');
		$request->set_param('sequential_block', '1');
		$request->set_param('sequential_context_post', (string) $this->post_ids[2]);
		$request->set_param('sequential_orderby', 'date');
		$request->set_param('sequential_order', 'asc');

		$result = $this->filter->filter_rest_query(
			'post',
			['posts_per_page' => 3, 'post__not_in' => [999]],
			$request
		);

		$this->assertSame(
			[$this->post_ids[3], $this->post_ids[4], $this->post_ids[5]],
			$result['post__in']
		);
		$this->assertSame('post__in', $result['orderby']);
		$this->assertSame('ASC', $result['order']);
		$this->assertSame(1, $result['ignore_sticky_posts']);
		$this->assertSame([], $result['post__not_in']);
	}

	public function test_rest_filter_canonical_fallback_with_marker_only(): void
	{
		// Editor-template preview case: marker present, no context post.
		$request = new WP_REST_Request('GET', '/wp/v2/posts');
		$request->set_param('sequential_block', '1');

		$result = $this->filter->filter_rest_query('post', ['posts_per_page' => 3], $request);

		$this->assertSame(
			[$this->post_ids[0], $this->post_ids[1], $this->post_ids[2]],
			$result['post__in']
		);
		$this->assertSame(1, $result['ignore_sticky_posts']);
	}

	public function test_rest_filter_exclude_sticky_propagates(): void
	{
		$request = new WP_REST_Request('GET', '/wp/v2/posts');
		$request->set_param('sequential_block', '1');
		$request->set_param('sequential_exclude_sticky', '1');

		$result = $this->filter->filter_rest_query('post', ['posts_per_page' => 10], $request);

		foreach ($this->sticky_ids as $sticky_id) {
			$this->assertNotContains($sticky_id, $result['post__in']);
		}
		$this->assertCount(4, $result['post__in']);
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	/** @param array<string, mixed> $overrides */
	private function make_parsed_block(array $overrides = []): array
	{
		return array_replace_recursive(
			[
				'blockName' => 'core/query',
				'attrs' => [
					'namespace' => 'next-posts-block/query',
					'query' => [
						'postType' => 'post',
						'perPage' => 3,
						'orderBy' => 'date',
						'order' => 'asc',
					],
				],
				'innerBlocks' => [],
				'innerContent' => [],
			],
			$overrides
		);
	}

	/**
	 * Synthesizes the child core/post-template block that carries the
	 * query context that filter_query_vars reads.
	 *
	 * @param array<string, mixed> $query_context
	 */
	private function make_child_block(array $query_context = []): WP_Block
	{
		$parsed = [
			'blockName' => 'core/post-template',
			'attrs' => [],
			'innerBlocks' => [],
			'innerContent' => [],
		];
		$context = [];
		if (!empty($query_context)) {
			$context['query'] = $query_context;
		}
		return new WP_Block($parsed, $context);
	}

	private function reset_static_state(): void
	{
		$defaults = [
			'is_sequential' => false,
			'orderby' => 'date',
			'order' => 'asc',
			'exclude_sticky' => false,
		];
		foreach ($defaults as $name => $value) {
			$this->static_prop($name)->setValue(null, $value);
		}
	}

	/** @return mixed */
	private function get_static(string $name)
	{
		return $this->static_prop($name)->getValue();
	}

	/**
	 * PHP 7.4 still requires setAccessible() before reflecting private
	 * members. On 8.1+ the call is a no-op (deprecated on 8.5+), so we
	 * gate it behind a version check to keep both CI and local dev quiet.
	 */
	private function static_prop(string $name): ReflectionProperty
	{
		$prop = new ReflectionProperty(QueryFilter::class, $name);
		if (PHP_VERSION_ID < 80100) {
			$prop->setAccessible(true);
		}
		return $prop;
	}
}
