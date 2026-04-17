/**
 * Locks native core/query controls that conflict with Sequential Posts semantics.
 *
 * 1. Query Type toggle (v1.1.0): locked to "Custom" (inherit: false) so the
 *    plugin's server-side filter cannot be bypassed.
 *      - Data integrity: effect coerces inherit=true back to false.
 *      - UI: CSS :has() rule hides the lone ToggleGroupControl ToolsPanelItem.
 *
 * 2. Sticky posts SelectControl (v1.2.0): hidden and neutralized. Users
 *    configure sticky exclusion via our own ToggleControl in
 *    inspector-controls.js (query.excludeSticky). Server always forces
 *    ignore_sticky_posts=1 and clears post__not_in so the native 4-option
 *    control has no effect even if it leaks through.
 *      - UI: MutationObserver + label-based match. Robust across WP versions
 *        and locales (matches "sticky posts" / "publications épinglées" /
 *        explicit label fallback). On deselect, hidden items are restored.
 *
 * Both hides are best-effort on the UI layer; the data layer enforcement
 * (inherit=false + ignore_sticky_posts=1) remains authoritative regardless.
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useEffect } from '@wordpress/element';

const NAMESPACE = 'sequential-posts-block/query';

/* ------------------------------------------------------------------ */
/* (1) Query Type toggle lock                                         */
/* ------------------------------------------------------------------ */

const QUERY_TYPE_STYLE_ID = 'sequential-posts-block-hide-query-type';
const QUERY_TYPE_HIDE_RULE = `.components-tools-panel-item:has(.components-toggle-group-control){display:none!important}`;

function ensureQueryTypeStyleInjected() {
	if ( document.getElementById( QUERY_TYPE_STYLE_ID ) ) return;
	const style = document.createElement( 'style' );
	style.id = QUERY_TYPE_STYLE_ID;
	style.textContent = QUERY_TYPE_HIDE_RULE;
	document.head.appendChild( style );
}

function removeQueryTypeStyle() {
	const el = document.getElementById( QUERY_TYPE_STYLE_ID );
	if ( el ) el.remove();
}

/* ------------------------------------------------------------------ */
/* (2) Sticky posts SelectControl hide                                */
/* ------------------------------------------------------------------ */

// Label patterns matched across WP locales. Extend when adding new locales.
const STICKY_LABEL_PATTERNS = [
	/sticky\s+posts?/i,               // en_US / en_GB
	/publications?\s+épinglées?/i,    // fr_FR
	/publicaciones?\s+fijadas?/i,     // es_ES
	/sticky/i,                        // safety net
];
const STICKY_HIDDEN_ATTR = 'data-sequential-hidden';

function isStickyItem( item ) {
	// Match against the visible label text of the ToolsPanelItem.
	const label = item.querySelector( 'label, legend, .components-base-control__label' );
	const text = ( label?.textContent || item.textContent || '' ).trim();
	return STICKY_LABEL_PATTERNS.some( ( re ) => re.test( text ) );
}

function hideStickyItems() {
	const items = document.querySelectorAll(
		'.editor-block-inspector .components-tools-panel-item, .block-editor-block-inspector .components-tools-panel-item'
	);
	for ( const item of items ) {
		if ( item.hasAttribute( STICKY_HIDDEN_ATTR ) ) continue;
		if ( isStickyItem( item ) ) {
			item.setAttribute( STICKY_HIDDEN_ATTR, '1' );
			item.style.display = 'none';
		}
	}
}

function restoreStickyItems() {
	const items = document.querySelectorAll( `[${ STICKY_HIDDEN_ATTR }]` );
	for ( const item of items ) {
		item.style.display = '';
		item.removeAttribute( STICKY_HIDDEN_ATTR );
	}
}

function watchSidebarForSticky() {
	hideStickyItems();
	const observer = new MutationObserver( () => hideStickyItems() );
	observer.observe( document.body, { childList: true, subtree: true } );
	return () => {
		observer.disconnect();
		restoreStickyItems();
	};
}

/* ------------------------------------------------------------------ */
/* HOC                                                                 */
/* ------------------------------------------------------------------ */

const withLockedCustomQuery = createHigherOrderComponent(
	( BlockEdit ) => ( props ) => {
		const isOurVariation =
			props.name === 'core/query' &&
			props.attributes?.namespace === NAMESPACE;
		const isInheriting = props.attributes?.query?.inherit === true;

		// Data integrity: force inherit=false whenever our variation is mounted.
		useEffect( () => {
			if ( ! isOurVariation || ! isInheriting ) return;
			props.setAttributes( {
				query: { ...props.attributes.query, inherit: false },
			} );
		}, [ isOurVariation, isInheriting ] );

		// UI hides: only while our variation is the selected block.
		useEffect( () => {
			if ( ! isOurVariation || ! props.isSelected ) return undefined;
			ensureQueryTypeStyleInjected();
			const stopStickyWatch = watchSidebarForSticky();
			return () => {
				removeQueryTypeStyle();
				stopStickyWatch();
			};
		}, [ isOurVariation, props.isSelected ] );

		return <BlockEdit { ...props } />;
	},
	'withLockedCustomQuery'
);

addFilter(
	'editor.BlockEdit',
	'sequential-posts-block/lock-custom-query',
	withLockedCustomQuery
);
