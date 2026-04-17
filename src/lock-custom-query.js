/**
 * Locks the core/query "Query Type" toggle to "Custom" (inherit: false) for
 * the Sequential Posts variation.
 *
 * Why: the plugin's semantics require an explicit post type + perPage + order
 * (no archive-inherited main query makes sense for a sequential list). The
 * "Par défaut" / Default mode hides postType, orderBy, perPage controls AND
 * bypasses our server-side filter — so the block renders as a plain
 * core/query without any sequential behavior.
 *
 * Strategy:
 *  1. Data integrity (bulletproof): whenever the block is rendered with
 *     inherit=true, immediately coerce it back to false via setAttributes.
 *     Migrates pre-existing content saved with inherit=true on first edit.
 *  2. UI hide (best-effort): while our variation is the selected block,
 *     inject a <style> into <head> that hides the single ToolsPanelItem
 *     containing a ToggleGroupControl (the Query Type row). Scoped via the
 *     CSS :has() pseudo-class. Cleaned up on deselect or unmount.
 *
 * If the UI hide fails in a future WP version (CSS selector break, :has()
 * unsupported), the data lock in step 1 still prevents misconfiguration —
 * clicking "Par défaut" simply snaps back to "Personnalisée".
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useEffect } from '@wordpress/element';

const NAMESPACE = 'sequential-posts-block/query';
const STYLE_ID = 'sequential-posts-block-hide-query-type';
const HIDE_RULE = `.components-tools-panel-item:has(.components-toggle-group-control){display:none!important}`;

function ensureStyleInjected() {
	if ( document.getElementById( STYLE_ID ) ) return;
	const style = document.createElement( 'style' );
	style.id = STYLE_ID;
	style.textContent = HIDE_RULE;
	document.head.appendChild( style );
}

function removeInjectedStyle() {
	const el = document.getElementById( STYLE_ID );
	if ( el ) el.remove();
}

const withLockedCustomQuery = createHigherOrderComponent( ( BlockEdit ) => ( props ) => {
	const isOurVariation =
		props.name === 'core/query' &&
		props.attributes?.namespace === NAMESPACE;
	const isInheriting = props.attributes?.query?.inherit === true;

	// (1) Data integrity: force inherit=false whenever our variation is mounted.
	useEffect( () => {
		if ( ! isOurVariation || ! isInheriting ) return;
		props.setAttributes( {
			query: { ...props.attributes.query, inherit: false },
		} );
		// setAttributes identity is stable; depend on the flags only.
	}, [ isOurVariation, isInheriting ] );

	// (2) UI: hide the Query Type toggle while our variation is selected.
	useEffect( () => {
		if ( ! isOurVariation || ! props.isSelected ) return undefined;
		ensureStyleInjected();
		return removeInjectedStyle;
	}, [ isOurVariation, props.isSelected ] );

	return <BlockEdit { ...props } />;
}, 'withLockedCustomQuery' );

addFilter(
	'editor.BlockEdit',
	'sequential-posts-block/lock-custom-query',
	withLockedCustomQuery
);
