/**
 * apiFetch middleware: attaches the Next Posts block's full native `query`
 * attrs (as a single JSON param) and the sequential marker to REST calls,
 * so the editor preview resolves against the same CanonicalList used on
 * the frontend. Falls back to the first N canonical items when no context
 * post is available (template editing).
 */

import apiFetch from '@wordpress/api-fetch';
import { select } from '@wordpress/data';

const NAMESPACE = 'next-posts-block/query';
const POST_TYPE_REST_PATTERN = /\/wp\/v2\/([a-z0-9_-]+)(\?|$)/i;

/**
 * Recursively finds the first Next Posts block in the editor tree and
 * returns its native `query` attributes bag. Returns null when the
 * variation is not present — signals this REST call is not ours to augment.
 */
function findSequentialAttrs() {
	const blocks = select( 'core/block-editor' )?.getBlocks() ?? [];

	function findInBlocks( blockList ) {
		for ( const block of blockList ) {
			if (
				block.name === 'core/query' &&
				block.attributes?.namespace === NAMESPACE
			) {
				return block.attributes.query ?? {};
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

	const attrs = findSequentialAttrs();
	if ( ! attrs ) {
		return next( options );
	}

	if ( url.includes( 'sequential_block=' ) ) {
		return next( options );
	}

	const currentPostId = select( 'core/editor' )?.getCurrentPostId();
	const params = [
		'sequential_block=1',
		`sequential_query_attrs=${ encodeURIComponent(
			JSON.stringify( attrs )
		) }`,
	];
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
