=== Sequential Posts Block ===
Contributors: jensensiu
Tags: block, gutenberg, query-loop, next-post, related-posts
Requires at least: 6.1
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Query Loop variation that displays posts sequentially relative to the current post, with wrap-around.

== Description ==

Variation of the native Query Loop block. Appears in the inserter as "Sequential Posts". On any single-post page, it renders the N posts that come after (or before) the current one in the chosen sort order, with silent wrap-around at list boundaries.

**Key features:**

* Works with any public post type
* Visually editable card template (Post Title, Featured Image, Post Excerpt, etc.)
* Uses the native Query Loop ordering controls (date / title, ASC / DESC)
* Configurable number of items (1–10)
* Silent wrap-around at end/start of the list
* Editor preview reflects order changes in real-time
* Zero third-party runtime dependencies

**Known limitations:**

* No WPML / Polylang language filtering in v1.0

== Installation ==

1. Upload the `sequential-posts-block` folder to `wp-content/plugins/`
2. Run the install commands (see Development section below)
3. Activate the plugin through the Plugins menu
4. Insert "Sequential Posts" block on any single-post template or in post content

== Development ==

All commands must be run from the plugin directory:

`cd wp-content/plugins/sequential-posts-block`

**First-time setup:**

* `composer install` — installs PHP dev dependencies (PHPUnit, WPCS)
* `npm install` — installs JS build dependencies (@wordpress/scripts)
* `npm run build` — compiles JS from `src/` to `build/`

**When modifying PHP files (`includes/`):**

No rebuild needed. Changes are picked up on the next page load.

**When modifying JS files (`src/`):**

* `npm run build` — one-shot compile (run after each change)
* `npm run start` — watch mode that recompiles automatically on save (recommended during development)

**When modifying `package.json`:**

* `npm install` — then `npm run build`

**Running the unit test suite:**

* `composer test:unit` — runs the SequentialResolver unit tests (15 tests)

**Regenerating the i18n POT:**

* `xgettext --language=JavaScript --keyword=__ --from-code=UTF-8 --default-domain=sequential-posts-block --output=languages/sequential-posts-block.pot src/variation.js src/inspector-controls.js`
* Update `.po` files as needed, then `msgfmt -o languages/sequential-posts-block-{locale}.mo languages/sequential-posts-block-{locale}.po`

== Changelog ==

= 1.0.0 =
* Initial release.
