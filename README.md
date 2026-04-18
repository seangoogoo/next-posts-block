# Next Posts — Query Loop Block

> Gutenberg Query Loop variation that displays posts sequentially relative to
> the current post, with silent wrap-around at list boundaries.

A lightweight variation of the native `core/query` block. Appears in the
inserter as **Next Posts** and renders the N posts that come after
(or before) the current post in the chosen sort order. Outside a singular
context (archives, home, Site Editor templates), it falls back to the first
N items of the canonical list.

## Features

- Works with any public post type (posts, pages, CPTs)
- Native Query Loop UI — no custom settings to learn
- Sort by date or title, ascending or descending
- Silent wrap-around when reaching the start/end of the list
- 1 to 10 items, fully configurable
- Non-singular fallback: renders the first N canonical items on archives/home
- Zero runtime dependencies beyond WordPress core

## Requirements

- WordPress 6.1 or newer
- PHP 7.4 or newer

## Installation

Download the latest `next-posts-block.zip` from the [Releases page](https://github.com/seangoogoo/next-posts-block/releases) and install it via the WordPress admin: **Plugins → Add New Plugin → Upload Plugin**.

## Usage

1. Open any single-post template (or edit a post's content) in the block
   editor
2. **Insert block** → search for **Next Posts**
3. Configure post type, number of items, and sort order in the right
   sidebar (Query Loop native controls)
4. On the frontend:
   - **Single post view** → the block displays N posts sequentially after
     the current one, wrapping around at the end of the list
   - **Archive / home / Site Editor template** → falls back to the first
     N items of the canonical list in the chosen order

## How it works

The block is a **variation** of `core/query`, not a new block. A
`pre_render_block` filter detects the variation via its `namespace`
attribute (`next-posts-block/query`) and arms a static flag; the
`query_loop_block_query_vars` filter then rewrites the query's `post__in`
to the sequentially-resolved IDs.

On the editor side, an `apiFetch` middleware recursively searches the
block tree for our variation and injects a `sequential_block=1` marker
plus `sequential_context_post` (when available) into REST requests to
`/wp/v2/{post_type}`. The server-side `rest_{$post_type}_query` filter
reads these parameters and rewrites the response so the editor preview
matches the frontend.

See [`tests/manual-checklist.md`](./tests/manual-checklist.md) for the
manual QA scenarios and [`includes/QueryFilter.php`](./includes/QueryFilter.php)
for the full hook execution order.

## Development

```bash
composer install          # PHP dev dependencies (PHPUnit, WPCS)
npm install               # JS build dependencies (@wordpress/scripts)
npm run start             # watch mode (rebuilds on save)
npm run build             # one-shot production build
composer test:unit        # run pure-PHP unit tests (15 tests)
composer test:integration # run wp-phpunit integration tests (28 tests)
composer lint             # run WPCS with the repo's phpcs.xml.dist ruleset
```

The integration suite requires a one-time bootstrap of the WordPress test
suite (local MySQL DB + `wp-phpunit`) — see
[`docs/testing.md`](./docs/testing.md) for the setup and conventions.

### Regenerating translations

After adding or updating strings in `src/variation.js`:

```bash
# Extract strings to POT
xgettext --language=JavaScript --keyword=__ --from-code=UTF-8 \
  --default-domain=next-posts-block \
  --output=languages/next-posts-block.pot \
  src/variation.js src/inspector-controls.js

# Update the locale PO, then compile MO
msgfmt -o languages/next-posts-block-{locale}.mo \
  languages/next-posts-block-{locale}.po

# Regenerate the JS JSON file (required for block name/description in the editor)
wp i18n make-json languages/next-posts-block-{locale}.po \
  --no-purge \
  --use-map='{"src/variation.js":"build/index.js"}'
```

The `--use-map` flag is important: without it, WP-CLI hashes the source
file path, but WordPress expects the hash of the enqueued build file.

## Project structure

```
includes/
  CanonicalList.php       # cached list of published post IDs per type/orderby
  ContextDetector.php     # resolves the "current post" across frontend/REST/admin
  SequentialResolver.php  # pure-PHP: computes N next/previous in a list
  QueryFilter.php         # WP hooks: pre_render + query_vars + REST rewrite
  Plugin.php              # boot + hook registration
src/
  index.js                # JS entry — imports variation + middleware
  variation.js            # registerBlockVariation + translatable strings
  inspector-controls.js   # (reserved for future custom controls)
  rest-middleware.js      # apiFetch middleware for editor preview
tests/
  unit/                   # pure-PHP unit tests (no WP required)
  integration/            # wp-phpunit tests against a real WP test DB
  manual-checklist.md     # frontend + editor manual QA
docs/
  testing.md              # test suite bootstrap + conventions
  wordpress-org/          # wp.org submission audit + naming notes
languages/                # POT + PO/MO + JSON for JS strings
build/                    # compiled by wp-scripts; committed for runtime use
```

## Contributing

Issues and pull requests are welcome. Before opening a PR:

- Keep runtime dependencies at zero (WordPress core only)
- Run `composer test:unit`, `composer test:integration`, and `composer lint`
- If you touch `src/`, commit the rebuilt `build/` files

## License

GPL-2.0-or-later. See [LICENSE](./LICENSE).
