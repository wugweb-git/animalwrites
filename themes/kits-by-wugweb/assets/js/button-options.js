/**
 * Button block: Size, Content and Icon (docs/buttons.md). The variant is the block style (Primary, Secondary, Ghost,
 * Brand); these three set the extra classes kit-btn--sm, kit-btn--label / kit-btn--icon and kit-btn--ico-<name>, so
 * they combine with any style. No build step: plain wp.* globals.
 */
( function ( wp ) {
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls } = wp.blockEditor;
	const { PanelBody, SelectControl } = wp.components;
	const { __ } = wp.i18n;

	const ICONS = [ 'phone', 'mail', 'whatsapp', 'search', 'send', 'download', 'check', 'alert', 'person', 'calendar', 'file', 'link', 'map-pin', 'megaphone', 'arrow-right', 'next', 'prev' ];
	const GROUPS = {
		size: [ 'kit-btn--sm' ],
		content: [ 'kit-btn--label', 'kit-btn--icon' ],
		icon: ICONS.map( ( n ) => 'kit-btn--ico-' + n ),
	};

	const split = ( c ) => ( c || '' ).split( /\s+/ ).filter( Boolean );
	const pick = ( classes, group ) => classes.find( ( c ) => GROUPS[ group ].includes( c ) ) || '';
	const swap = ( className, group, value ) => {
		const rest = split( className ).filter( ( c ) => ! GROUPS[ group ].includes( c ) );
		if ( value ) {
			rest.push( value );
		}
		return rest.join( ' ' ) || undefined;
	};

	const withButtonOptions = wp.compose.createHigherOrderComponent( ( BlockEdit ) => ( props ) => {
		if ( props.name !== 'core/button' ) {
			return el( BlockEdit, props );
		}
		const { className } = props.attributes;
		const classes = split( className );
		const set = ( group ) => ( value ) => props.setAttributes( { className: swap( className, group, value ) } );
		const content = pick( classes, 'content' );
		return el(
			Fragment,
			null,
			el( BlockEdit, props ),
			el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Size and content', 'kits-by-wugweb' ), initialOpen: true },
					el( SelectControl, {
						label: __( 'Size', 'kits-by-wugweb' ),
						value: pick( classes, 'size' ),
						options: [ { label: __( 'Default', 'kits-by-wugweb' ), value: '' }, { label: __( 'Small', 'kits-by-wugweb' ), value: 'kit-btn--sm' } ],
						onChange: set( 'size' ),
						__nextHasNoMarginBottom: true,
					} ),
					el( SelectControl, {
						label: __( 'Content', 'kits-by-wugweb' ),
						value: content,
						options: [
							{ label: __( 'Icon and label', 'kits-by-wugweb' ), value: '' },
							{ label: __( 'Label only (buttons that are not links)', 'kits-by-wugweb' ), value: 'kit-btn--label' },
							{ label: __( 'Icon only (the text stays as its name)', 'kits-by-wugweb' ), value: 'kit-btn--icon' },
						],
						help: 'kit-btn--icon' === content ? __( 'The button text is read out by screen readers and hidden on screen. Choose an icon below.', 'kits-by-wugweb' ) : __( 'A link always keeps an icon: → inside the site, ↗ when it leaves it, or the icon chosen below.', 'kits-by-wugweb' ),
						onChange: set( 'content' ),
						__nextHasNoMarginBottom: true,
					} ),
					el( SelectControl, {
						label: __( 'Icon', 'kits-by-wugweb' ),
						value: pick( classes, 'icon' ),
						options: [ { label: __( 'Automatic', 'kits-by-wugweb' ), value: '' } ].concat( ICONS.map( ( n ) => ( { label: n.replace( '-', ' ' ), value: 'kit-btn--ico-' + n } ) ) ),
						onChange: set( 'icon' ),
						__nextHasNoMarginBottom: true,
					} )
				)
			)
		);
	}, 'withKitsButtonOptions' );

	wp.hooks.addFilter( 'editor.BlockEdit', 'kits-by-wugweb/button-options', withButtonOptions );
} )( window.wp );
