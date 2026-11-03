/**
 * Older Group blocks saved with kit-section or kit-section-band and no style get the Section or Head band style when
 * they open in the editor, the same as on the front end (functions.php, render_block_data). The editor then shows
 * the Styles values on them, and the next save keeps the style in the content. No build step: plain wp.* globals.
 */
( function ( wp ) {
	const { createElement: el, useEffect } = wp.element;
	const { dispatch } = wp.data;

	const styleFor = ( className ) => {
		const classes = ( className || '' ).split( /\s+/ ).filter( Boolean );
		if ( classes.some( ( c ) => c.indexOf( 'is-style-' ) === 0 ) ) {
			return '';
		}
		if ( classes.includes( 'kit-section-band' ) ) {
			return 'is-style-kits-head-band';
		}
		return classes.includes( 'kit-section' ) ? 'is-style-kits-section' : '';
	};

	const withLegacyStyle = wp.compose.createHigherOrderComponent( ( BlockEdit ) => ( props ) => {
		const style = props.name === 'core/group' ? styleFor( props.attributes.className ) : '';
		useEffect( () => {
			if ( style ) {
				// No undo step of its own: it is a migration, not an edit.
				dispatch( 'core/block-editor' ).__unstableMarkNextChangeAsNotPersistent();
				props.setAttributes( { className: style + ' ' + props.attributes.className } );
			}
		}, [ style ] );
		return el( BlockEdit, props );
	}, 'withLegacyStyle' );

	wp.hooks.addFilter( 'editor.BlockEdit', 'kits/legacy-styles', withLegacyStyle );
} )( window.wp );
