/**
 * Custom InspectorControls panel for the Sequential Posts variation.
 *
 * Adds a "Sequential settings" panel with a single ToggleControl:
 *   "Exclude sticky posts from the sequence" → writes to query.excludeSticky.
 *
 * Scoped to our variation via a BlockEdit filter. Other core/query blocks
 * are left untouched.
 *
 * Pairs with the server-side CanonicalList::get($post_type, …, $exclude_sticky)
 * which rebuilds the list without sticky IDs when the flag is true.
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const NAMESPACE = 'next-posts-block/query';

const withSequentialSettings = createHigherOrderComponent(
	( BlockEdit ) => ( props ) => {
		const isOurVariation =
			props.name === 'core/query' &&
			props.attributes?.namespace === NAMESPACE;

		if ( ! isOurVariation ) {
			return <BlockEdit { ...props } />;
		}

		const excludeSticky = Boolean(
			props.attributes?.query?.excludeSticky
		);

		const setExcludeSticky = ( value ) => {
			props.setAttributes( {
				query: { ...props.attributes.query, excludeSticky: value },
			} );
		};

		return (
			<Fragment>
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody
						title={ __(
							'Sequential settings',
							'next-posts-block'
						) }
						initialOpen={ true }
					>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __(
								'Exclude sticky posts from the sequence',
								'next-posts-block'
							) }
							help={ __(
								'Sticky posts are removed from the canonical list used to build the sequence.',
								'next-posts-block'
							) }
							checked={ excludeSticky }
							onChange={ setExcludeSticky }
						/>
					</PanelBody>
				</InspectorControls>
			</Fragment>
		);
	},
	'withSequentialSettings'
);

addFilter(
	'editor.BlockEdit',
	'next-posts-block/sequential-settings',
	withSequentialSettings
);
