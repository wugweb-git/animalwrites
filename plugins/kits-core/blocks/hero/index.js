/**
 * Kits Hero (kits/hero): a container whose look is all block supports (block.json). No build step: it uses the
 * editor's own globals. Every setting is a core control (Styles > Blocks > Hero, or the block's own sidebar).
 */
( function ( wp ) {
	var el = wp.element.createElement;
	var be = wp.blockEditor;
	var TEMPLATE = [
		[ 'core/heading', { level: 1, placeholder: wp.i18n.__( 'Page heading', 'kits-core' ) } ],
		[ 'core/paragraph', { fontSize: 'large', placeholder: wp.i18n.__( 'One line that says what the page is for.', 'kits-core' ) } ],
		[ 'core/buttons' ],
	];

	wp.blocks.registerBlockType( 'kits/hero', {
		edit: function () {
			return el( 'section', be.useInnerBlocksProps( be.useBlockProps(), { template: TEMPLATE } ) );
		},
		save: function () {
			return el( 'section', be.useInnerBlocksProps.save( be.useBlockProps.save() ) );
		},
	} );
} )( window.wp );
