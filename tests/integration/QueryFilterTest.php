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
		$parsed['attrs']['query']['sticky'] = 'exclude';

		$this->filter->pre_render(null, $parsed);

		$this->assertTrue($this->get_static('is_sequential'));
		$attrs = $this->get_static('query_attrs');
		$this->assertSame('title', $attrs['orderBy']);
		$this->assertSame('desc', $attrs['order']);
		$this->assertSame('exclude', $attrs['sticky']);
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

	public function test_pre_render_resets_armed_state_when_followed_by_non_matching_sibling_core_query(): void
	{
		// v1.2.2: a non-matching sibling core/query (different namespace) still
		// clears any leaked armed state from a prior arm that was short-circuited
		// before filter_query_vars could consume it. Sibling-leak prevention is
		// preserved despite the reset having moved below the blockName check.
		$this->filter->pre_render(null, $this->make_parsed_block());
		$this->assertTrue($this->get_static('is_sequential'));

		$foreign = $this->make_parsed_block();
		$foreign['attrs']['namespace'] = 'third-party/other';
		$this->filter->pre_render(null, $foreign);

		$this->assertFalse($this->get_static('is_sequential'));
		$this->assertSame([], $this->get_static('query_attrs'));
	}

	public function test_pre_render_preserves_armed_state_across_inner_non_query_blocks(): void
	{
		// v1.2.2: the reset only fires when blockName === 'core/query'. Inner
		// blocks (core/post-template, core/post-title, etc.) that WP renders
		// between the parent core/query's pre_render (arm) and its
		// query_loop_block_query_vars (consume) must NOT wipe the armed state.
		// Regression guard against the v1.2.1 bug fixed by v1.2.2.
		$parsed = $this->make_parsed_block();
		$parsed['attrs']['query']['sticky'] = 'exclude';
		$this->filter->pre_render(null, $parsed);
		$this->assertTrue($this->get_static('is_sequential'));
		$this->assertSame('exclude', $this->get_static('query_attrs')['sticky']);

		foreach (['core/post-template', 'core/post-title', 'core/paragraph'] as $inner) {
			$this->filter->pre_render(null, ['blockName' => $inner, 'attrs' => []]);
		}

		$this->assertTrue($this->get_static('is_sequential'));
		$this->assertSame('exclude', $this->get_static('query_attrs')['sticky']);
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
		// v1.3.0: query.sticky='exclude' must propagate to CanonicalList::build.
		$this->go_to(home_url('/'));

		$parsed = $this->make_parsed_block();
		$parsed['attrs']['query']['sticky'] = 'exclude';
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
	// Full render pipeline (regression guard)
	// ------------------------------------------------------------------

	public function test_full_render_pipeline_preserves_armed_state_across_inner_blocks(): void
	{
		// Regression guard: pre_render_block fires for every block, including
		// children of core/query (e.g. core/post-template). If pre_render
		// resets its static flags before the blockName/namespace guards, the
		// armed state set by the parent core/query is wiped by the inner
		// core/post-template's own pre_render_block fire — and
		// query_loop_block_query_vars then sees is_sequential = false, so
		// sticky neutralization and sequential resolution never apply.
		//
		// Exercising the full do_blocks() pipeline (rather than invoking
		// filter_query_vars manually) is what surfaces the bug.
		//
		// Register the filter against the real WP hooks for this test —
		// setUp() only instantiates the filter; Plugin::boot() wires it in
		// production. We un-hook in a finally block to keep test isolation.
		add_filter('pre_render_block', [$this->filter, 'pre_render'], 10, 2);
		add_filter('query_loop_block_query_vars', [$this->filter, 'filter_query_vars'], 10, 3);

		try {
			$markup = '<!-- wp:query {"namespace":"next-posts-block/query","query":{"postType":"post","perPage":3,"inherit":false,"orderBy":"date","order":"asc","sticky":"ignore"}} -->'
				. '<!-- wp:post-template -->'
				. '<!-- wp:post-title /-->'
				. '<!-- /wp:post-template -->'
				. '<!-- /wp:query -->';

			$rendered = $this->render_block_content($markup);

			// Extract per-iteration post IDs from `<li>` wrappers emitted by
			// `core/post-template`. Using a stricter pattern than
			// `/post-(\d+)/` avoids false matches from nested wrappers that
			// may share the `post-{id}` class.
			preg_match_all('/<li[^>]*\bpost-(\d+)\b[^>]*>/', $rendered, $matches);
			$rendered_ids = array_map('intval', $matches[1]);

			// Pre-fix behavior: inner core/post-template's pre_render_block
			// fire resets is_sequential between the parent's arm and the
			// filter's consumption. filter_query_vars returns the unmodified
			// $query, so WP_Query runs with its defaults — which includes
			// prepending sticky posts ($this->post_ids[1] and [4]) and
			// returning MORE than perPage=3 rows.
			//
			// Post-fix behavior: is_sequential survives across inner blocks,
			// SequentialResolver returns the 3 posts following post_ids[0]
			// in date-ASC order, and post__in + ignore_sticky_posts=1 pin
			// the output to exactly those 3 IDs.
			$this->assertCount(
				3,
				$rendered_ids,
				'Rendered output should contain exactly 3 posts. More than 3 means '
				. 'sticky posts were prepended because filter_query_vars never fired '
				. 'with is_sequential=true.'
			);
			$this->assertSame(
				[$this->post_ids[1], $this->post_ids[2], $this->post_ids[3]],
				$rendered_ids,
				'Rendered IDs must be the sequentially-resolved next 3 posts after '
				. 'post_ids[0] in date-ASC order.'
			);

			// Static state should not leak past the render. filter_query_vars
			// consumes the flag; if it never fired (pre-fix), pre_render's
			// own top-of-function reset still leaves it false.
			$this->assertFalse(
				$this->get_static('is_sequential'),
				'is_sequential must not leak past the render.'
			);
		} finally {
			remove_filter('pre_render_block', [$this->filter, 'pre_render'], 10);
			remove_filter('query_loop_block_query_vars', [$this->filter, 'filter_query_vars'], 10);
		}
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
						'sticky' => 'ignore',
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

	/**
	 * Sets up a singular context on $this->post_ids[0] and runs the given
	 * serialized block markup through the full WP render pipeline
	 * (do_blocks → render_block → pre_render_block → query_loop_block_query_vars).
	 *
	 * The singular flags are forced explicitly because go_to() alone does
	 * not reliably set is_singular() in unit-test bootstrap contexts.
	 */
	private function render_block_content(string $markup): string
	{
		global $wp_query, $post;

		$post = get_post($this->post_ids[0]);
		setup_postdata($post);

		$wp_query->queried_object = $post;
		$wp_query->queried_object_id = $this->post_ids[0];
		$wp_query->is_single = true;
		$wp_query->is_singular = true;
		$wp_query->is_home = false;
		$wp_query->is_archive = false;

		return do_blocks($markup);
	}

	private function reset_static_state(): void
	{
		$defaults = [
			'is_sequential' => false,
			'query_attrs' => [],
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
