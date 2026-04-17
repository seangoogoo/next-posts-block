/**
 * apiFetch middleware: appends sequential_context_post, sequential_order,
 * and sequential_orderby to REST calls so the editor preview reflects
 * the correct sequential posts with the user's chosen sort order.
 */

import apiFetch from '@wordpress/api-fetch';
import { select } from '@wordpress/data';

const NAMESPACE = 'sequential-posts-block/query';
const POST_TYPE_REST_PATTERN = /\/wp\/v2\/([a-z0-9_-]+)(\?|$)/i;

/**
 * Recursively finds the first Sequential Posts block in the editor
 * and reads its orderBy/order. Falls back to date/asc.
 */
function getSequentialSort() {
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
				};
			}
			if ( block.innerBlocks?.length ) {
				const found = findInBlocks( block.innerBlocks );
				if ( found ) return found;
			}
		}
		return null;
	}

	return findInBlocks( blocks ) ?? { orderby: 'date', order: 'asc' };
}

apiFetch.use( ( options, next ) => {
	const url = options.url || options.path || '';
	const match = url.match( POST_TYPE_REST_PATTERN );

	if ( ! match ) {
		return next( options );
	}

	const editor = select( 'core/editor' );
	const currentPostId = editor?.getCurrentPostId();
	if ( ! currentPostId ) {
		return next( options );
	}

	if ( url.includes( 'sequential_context_post' ) ) {
		return next( options );
	}

	const { orderby, order } = getSequentialSort();
	const separator = url.includes( '?' ) ? '&' : '?';
	const params = `sequential_context_post=${ currentPostId }&sequential_order=${ order }&sequential_orderby=${ orderby }`;
	const augmented = `${ url }${ separator }${ params }`;

	return next( {
		...options,
		url: options.url ? augmented : undefined,
		path: options.path ? augmented : undefined,
	} );
} );
