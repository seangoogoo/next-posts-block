/**
 * apiFetch middleware: appends the sequential_block marker plus sort params
 * (and sequential_context_post when available) to REST calls so the editor
 * preview reflects the correct sequential posts. When no context post is
 * available (e.g. editing a template), the server falls back to the first
 * N items of the canonical list — same behavior as the frontend.
 */

import apiFetch from '@wordpress/api-fetch';
import { select } from '@wordpress/data';

const NAMESPACE = 'sequential-posts-block/query';
const POST_TYPE_REST_PATTERN = /\/wp\/v2\/([a-z0-9_-]+)(\?|$)/i;

/**
 * Recursively finds the first Sequential Posts block in the editor and
 * returns its orderBy / order / excludeSticky. Returns null if the variation
 * is absent from the block tree — signals that this REST call is NOT ours
 * to augment.
 */
function findSequentialSettings() {
	const blocks = select( 'core/block-editor' )?.getBlocks() ?? [];

	function findInBlocks( blockList ) {
		for ( const block of blockList ) {
			if (
				block.name === 'core/query' &&
				block.attributes?.namespace === NAMESPACE
			) {
				return {
					orderby: block.attributes.query?.orderBy ?? 'date',
					order: block.attributes.query?.order ?? 'asc',
					excludeSticky: Boolean(
						block.attributes.query?.excludeSticky
					),
				};
			}
			if ( block.innerBlocks?.length ) {
				const found = findInBlocks( block.innerBlocks );
				if ( found ) return found;
			}
		}
		return null;
	}

	return findInBlocks( blocks );
}

apiFetch.use( ( options, next ) => {
	const url = options.url || options.path || '';
	const match = url.match( POST_TYPE_REST_PATTERN );

	if ( ! match ) {
		return next( options );
	}

	const settings = findSequentialSettings();
	if ( ! settings ) {
		return next( options );
	}

	if ( url.includes( 'sequential_block=' ) ) {
		return next( options );
	}

	const currentPostId = select( 'core/editor' )?.getCurrentPostId();
	const params = [
		'sequential_block=1',
		`sequential_orderby=${ settings.orderby }`,
		`sequential_order=${ settings.order }`,
	];
	if ( settings.excludeSticky ) {
		params.push( 'sequential_exclude_sticky=1' );
	}
	if ( currentPostId ) {
		params.push( `sequential_context_post=${ currentPostId }` );
	}

	const separator = url.includes( '?' ) ? '&' : '?';
	const augmented = `${ url }${ separator }${ params.join( '&' ) }`;

	return next( {
		...options,
		url: options.url ? augmented : undefined,
		path: options.path ? augmented : undefined,
	} );
} );
