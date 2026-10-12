/* Ad slot block (kits/ad-slot): pick a slot; the editor shows the "Ad" badge and the reserved space. The code itself
   comes from Settings > Ads and is printed only on the site. */
( function ( wp ) {
	var el = wp.element.createElement;
	var slots = window.kitsAdSlots || [];
	function find( v ) {
		for ( var i = 0; i < slots.length; i++ ) {
			if ( slots[ i ].value === v ) {
				return slots[ i ];
			}
		}
		return null;
	}
	wp.blocks.registerBlockType( 'kits/ad-slot', {
		apiVersion: 3,
		title: 'Ad slot',
		category: 'kits-ads',
		icon: 'megaphone',
		description: 'A labelled ad position. The code comes from Settings > Ads.',
		keywords: [ 'ad', 'advert', 'adsense' ],
		attributes: { slot: { type: 'string', 'default': 'home-mid' } },
		supports: { html: false, reusable: false },
		edit: function ( props ) {
			var s = find( props.attributes.slot );
			var picker = el( wp.components.SelectControl, {
				label: 'Slot',
				value: props.attributes.slot,
				options: slots.map( function ( o ) { return { value: o.value, label: o.label }; } ),
				onChange: function ( v ) { props.setAttributes( { slot: v } ); },
				help: s ? s.where : ''
			} );
			return el(
				'div',
				wp.blockEditor.useBlockProps( { className: 'kit-ad kit-ad--editor' } ),
				el( wp.blockEditor.InspectorControls, null, el( wp.components.PanelBody, { title: 'Ad slot' }, picker ) ),
				el( 'p', { className: 'kit-ad-label' }, el( 'span', { className: 'kit-badge kit-badge--ad' }, 'Ad' ) ),
				el( 'div', { className: 'kit-ad-unit', 'data-slot-label': s ? s.label : props.attributes.slot } )
			);
		},
		save: function () { return null; }
	} );
} )( window.wp );
