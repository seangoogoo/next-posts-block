/**
 * Sequential Posts — registers a variation of core/query.
 *
 * Uses native Query Loop orderBy/order controls for sort direction.
 * No custom attributes needed — the server reads query.orderBy and
 * query.order to build the canonical list in the right order.
 */

import { registerBlockVariation } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

const NAMESPACE = 'sequential-posts-block/query';

registerBlockVariation( 'core/query', {
	name: 'sequential-posts',
	title: __( 'Sequential Posts', 'sequential-posts-block' ),
	description: __(
		'Displays the N posts that sequentially follow (or precede) the current post, with wrap-around.',
		'sequential-posts-block'
	),
	icon: 'list-view',
	category: 'theme',
	keywords: [
		__( 'next', 'sequential-posts-block' ),
		__( 'previous', 'sequential-posts-block' ),
		__( 'sequence', 'sequential-posts-block' ),
		__( 'related', 'sequential-posts-block' ),
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
