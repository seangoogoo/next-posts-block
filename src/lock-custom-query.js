/**
 * Locks the native core/query Query Type toggle so our variation stays on
 * inherit=false. Native sticky and filter controls are intentionally left
 * visible — the server now honors them verbatim (see CanonicalList::build).
 *
 * - Data integrity: effect coerces inherit=true back to false.
 * - UI: CSS :has() rule hides the lone ToggleGroupControl ToolsPanelItem.
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useEffect } from '@wordpress/element';

const NAMESPACE = 'next-posts-block/query';

const QUERY_TYPE_STYLE_ID = 'next-posts-block-hide-query-type';
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

const withLockedCustomQuery = createHigherOrderComponent(
	( BlockEdit ) => ( props ) => {
		const isOurVariation =
			props.name === 'core/query' &&
			props.attributes?.namespace === NAMESPACE;
		const isInheriting = props.attributes?.query?.inherit === true;

		useEffect( () => {
			if ( ! isOurVariation || ! isInheriting ) return;
			props.setAttributes( {
				query: { ...props.attributes.query, inherit: false },
			} );
		}, [ isOurVariation, isInheriting ] );

		useEffect( () => {
			if ( ! isOurVariation || ! props.isSelected ) return undefined;
			ensureQueryTypeStyleInjected();
			return () => {
				removeQueryTypeStyle();
			};
		}, [ isOurVariation, props.isSelected ] );

		return <BlockEdit { ...props } />;
	},
	'withLockedCustomQuery'
);

addFilter(
	'editor.BlockEdit',
	'next-posts-block/lock-custom-query',
	withLockedCustomQuery
);
