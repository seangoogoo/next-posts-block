=== Next Posts — Query Loop Block ===
Contributors: jensensiu
Tags: query-loop, block, next-post, related-posts, post-navigation
Requires at least: 6.1
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A Query Loop variation that displays the next N posts after the current one, with wrap-around — configurable order, any post type.

== Description ==

Next Posts is a variation of the native Query Loop block. It appears in the Block Editor inserter as "Next Posts". On any single-post page, it renders the N posts that come after the current one in the chosen sort order, with silent wrap-around at list boundaries — so readers always see a full set of suggestions, even when viewing the last post in a sequence. When the block is used outside a singular context (archives, home, search, template previews), it falls back to the first N items of the canonical list in the chosen sort order instead of rendering empty.

Because Next Posts reuses the native Query Loop block, authors configure the card template visually with the standard Post blocks (Post Title, Post Featured Image, Post Excerpt, and so on). The block is well suited for post navigation, related posts rails, and any layout that needs a sequential list of entries tied to the current post.

**Key features:**

* Works with any public post type
* Visually editable card template using native Post blocks (Post Title, Post Featured Image, Post Excerpt, etc.)
* Uses the native Query Loop ordering controls (date / title, ASC / DESC)
* Configurable number of items (1–10)
* Silent wrap-around at end/start of the list
* Non-singular fallback renders the first N items of the canonical list
* Editor preview reflects order changes in real time
* Zero third-party runtime dependencies

== Installation ==

1. Install through the admin: Plugins → Add New → search for "Next Posts — Query Loop Block" → Install → Activate.
2. Or upload the plugin folder to `/wp-content/plugins/` and activate it through the Plugins menu.
3. In the Block Editor, insert the "Next Posts" block on a single-post template, or directly in a post. The block variation appears in the inserter under "Next Posts".
4. Configure the number of posts (1–10), the sort order, and the card template through the block's sidebar settings.

== Frequently Asked Questions ==

= How do I insert the block? =

In the Block Editor, open the inserter, search for "Next Posts", and add the block to your single-post template or post content.

= How do I change the number of posts shown? =

Select the block and use the "Items per page" control in the sidebar. Valid range is 1 to 10.

= What happens when the current post is the last in the list? =

The block wraps around silently and continues from the start of the list, so readers always see a full set of suggestions.

= What happens on archive, home, or search pages? =

When no single-post context is available, the block renders the first N items of the canonical list in the chosen sort order, instead of returning empty.

= Does it work with WPML or Polylang? =

Not natively. The block builds its list in a way that bypasses the language filters used by multilingual plugins, so a multilingual site may show posts from other languages in the sequence. Native multilingual support may be added in a future release.

= Can I use it with custom post types? =

Yes. The block works with any post type registered as `public`.

= Can I exclude sticky posts from the sequence? =

When enabled, sticky posts are removed from the sequence. Sticky posts never inflate the item count beyond the number you configured, regardless of this setting.

== Screenshots ==

1. The "Next Posts" block shown in the Gutenberg inserter.
2. The block rendering the next 3 posts after the current one on a single-post page.
3. The block sidebar settings panel.

== Changelog ==

= 1.2.1 =
* Security: REST middleware now URI-encodes the `sequential_orderby` / `sequential_order` values before injecting them into request URLs.
* Fix: the MutationObserver that hides the native Sticky SelectControl no longer matches against the full ToolsPanelItem text, only its label — avoiding accidental hiding of our own "Exclude sticky posts from the sequence" toggle if WordPress ever wraps it in a ToolsPanelItem. The broad `/sticky/i` safety pattern has been removed.
* Performance: the observer is now scoped to the sidebar skeleton and filters out pure text-node mutations.
* Fix: `QueryFilter::pre_render` resets the `$is_sequential` and `$exclude_sticky` statics unconditionally before the match checks, preventing state leakage if a previous arm was never consumed by `filter_query_vars` (e.g. another filter short-circuited the render).
* i18n: French translations added for "Sequential settings", "Exclude sticky posts from the sequence", and the help text. JSON regenerated with both source mappings (`src/variation.js` + `src/inspector-controls.js` → `build/index.js`).
* Manual checklist refreshed: removed stale references to a draft v1.0 UI that was never shipped, added scenarios for REST preview tracking, sidebar-scope isolation across multiple query blocks, and pre-v1.2.0 block migration.

= 1.2.0 =
* New: dedicated "Sequential settings" panel with an "Exclude sticky posts from the sequence" toggle (writes to `query.excludeSticky`). When enabled, sticky posts are removed from the canonical list that drives the sequential navigation.
* Fix: `ignore_sticky_posts` is now forced to 1 on every render — the native "Include" sticky mode was silently prepending stickies to the query result, producing more items than `perPage`. `post__not_in` is also cleared so the native "Exclude" option cannot interfere.
* UI: the native "Sticky posts" SelectControl is hidden from the Sequential Posts sidebar. Use the new toggle instead.
* UI: the native "Query type" toggle was hidden in v1.1.1; v1.2.0 consolidates the locking logic in a single module (`src/lock-custom-query.js`).

= 1.1.0 =
* Non-singular fallback: the block now renders the first N items of the canonical list (in the chosen sort order) when used on archives, home, search, or any page without a singular post context. Previously rendered empty.
* Editor: REST preview stays consistent with the frontend, even when no context post is available (e.g. editing a template in the Site Editor).

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.2.1 =
Security fix: REST preview parameters are now URI-encoded before being injected into request URLs.

= 1.2.0 =
New "Sequential settings" panel with a toggle to exclude sticky posts from the sequence. Fixes the native "Include" sticky mode silently inflating the result set beyond perPage.

= 1.1.0 =
Non-singular fallback: the block now renders the first N items when used on archives, home, or search pages.
