/**
 * Kits Hero (kits/hero): a container whose look is all block supports (blocks/hero/block.json). Every setting is a
 * core control (Styles > Blocks > Hero, or the block's own sidebar). Built by wp-scripts to build/hero/.
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

const TEMPLATE = [
	[
		'core/heading',
		{ level: 1, placeholder: __( 'Page heading', 'kits-core' ) },
	],
	[
		'core/paragraph',
		{
			fontSize: 'large',
			placeholder: __(
				'One line that says what the page is for.',
				'kits-core'
			),
		},
	],
	[ 'core/buttons' ],
];

registerBlockType( 'kits/hero', {
	edit: function Edit() {
		return (
			<section
				{ ...useInnerBlocksProps( useBlockProps(), {
					template: TEMPLATE,
				} ) }
			/>
		);
	},
	save: () => (
		<section { ...useInnerBlocksProps.save( useBlockProps.save() ) } />
	),
} );
