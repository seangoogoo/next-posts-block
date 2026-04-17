# Manual QA Checklist — Sequential Posts Block v1.0

Run through this on `wp.local` after `composer install && npm install && npm run build`.

## Environment sanity

- [ ] Plugin activates without fatal error (check `wp-content/debug.log`)
- [ ] No console errors in the editor referencing `sequential-posts-block-editor`
- [ ] `npm run build` produces `build/index.js` + `build/index.asset.php` without warnings

## Editor

- [ ] Block inserter search "Sequential Posts" → variation appears with list icon
- [ ] Inserting the block creates a Query Loop with default innerBlocks (featured image, title, excerpt, read-more)
- [ ] Save draft, reload editor → block re-hydrates as "Sequential Posts", not plain "Query Loop"
- [ ] Right sidebar shows "Sequential settings" PanelBody with ASC/DESC ToggleGroup
- [ ] Toggle between ASC and DESC updates the `sequentialOrder` attribute in the saved block HTML comment

## Frontend

- [ ] On a single post: renders 3 cards (the 3 following posts chronologically)
- [ ] Wrap-around: on the most-recent post, next 3 are the 3 oldest posts
- [ ] DESC direction: on the oldest post, next 3 are the 3 most recent (reversed)
- [ ] On a page: empty output (pre_render short-circuits)
- [ ] On an archive: empty output
- [ ] On home: empty output
- [ ] Modify the Post Template (wrap in Group, reorder blocks, add styling) → frontend reflects

## Deactivation gracefulness

- [ ] Deactivate the plugin — existing posts with the block degrade to plain Query Loop without fatal error

## Risk items flagged during build

- [ ] `@experimentalToggleGroupControl` renders on the running WP version (needs WP ≥ 6.2 approximately)
- [ ] REST filter registration on `rest_api_init` actually catches `lm_success_story` — check that network tab `/wp-json/wp/v2/lm_success_story` accepts the `sequential_context_post` param

## Lundi Matin integration (Phase 12)

- [ ] Works on `lm_success_story` CPT single page (after template edit)
- [ ] Card design integrates cleanly with existing theme CSS
- [ ] Legacy `[ss-footer-cta]` shortcode on non-migrated pages still functional
