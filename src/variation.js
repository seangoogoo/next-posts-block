/**
 * Next Posts — registers a variation of core/query.
 *
 * Uses native Query Loop orderBy/order controls for sort direction.
 * No custom attributes needed — the server reads query.orderBy and
 * query.order to build the canonical list in the right order.
 */

import { registerBlockVariation } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

const NAMESPACE = 'next-posts-block/query';

registerBlockVariation( 'core/query', {
	name: 'next-posts',
	title: __( 'Next Posts', 'next-posts-block' ),
	description: __(
		'Displays the N posts that sequentially follow (or precede) the current post, with wrap-around.',
		'next-posts-block'
	),
	icon: 'list-view',
	category: 'theme',
	keywords: [
		__( 'next', 'next-posts-block' ),
		__( 'previous', 'next-posts-block' ),
		__( 'sequence', 'next-posts-block' ),
		__( 'related', 'next-posts-block' ),
	],
	scope: [ 'inserter' ],
	attributes: {
		namespace: NAMESPACE,
		query: {
			postType: 'post',
			perPage: 3,
			inherit: false,
			offset: 0,
			orderBy: 'date',
			order: 'asc',
			excludeSticky: false,
		},
	},
	innerBlocks: [
		[
			'core/post-template',
			{},
			[
				[ 'core/post-featured-image' ],
				[ 'core/post-title', { level: 3, isLink: true } ],
				[ 'core/post-excerpt' ],
				[ 'core/read-more' ],
			],
		],
	],
	isActive: ( { namespace } ) => namespace === NAMESPACE,
} );
