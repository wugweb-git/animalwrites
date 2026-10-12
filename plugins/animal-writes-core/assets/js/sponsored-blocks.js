/* Sponsored blocks (inserter group "Sponsored"): settings in the sidebar, the block shows as it will on the site. */
( function ( wp ) {
	var el = wp.element.createElement;
	var C = wp.components;
	var BE = wp.blockEditor;
	function preview( name, props, empty ) {
		return el( wp.serverSideRender, {
			block: name,
			attributes: props.attributes,
			EmptyResponsePlaceholder: function () { return el( 'p', { style: { opacity: 0.6 } }, empty ); }
		} );
	}
	wp.blocks.registerBlockType( 'animal-writes/sponsored-item', {
		apiVersion: 3,
		title: 'Sponsored Resource',
		category: 'kits-sponsored',
		icon: 'megaphone',
		description: 'A Resource with Sponsored ticked, shown as a card labelled "Sponsored".',
		attributes: { postId: { type: 'integer', 'default': 0 } },
		supports: { html: false },
		edit: function ( props ) {
			var opts = [ { value: 0, label: '— Choose a sponsored Resource —' } ].concat( window.awSponsoredResources || [] );
			return el(
				'div',
				BE.useBlockProps(),
				el( BE.InspectorControls, null, el( C.PanelBody, { title: 'Sponsored Resource' },
					el( C.SelectControl, {
						label: 'Resource',
						value: props.attributes.postId,
						options: opts,
						onChange: function ( v ) { props.setAttributes( { postId: parseInt( v, 10 ) || 0 } ); },
						help: 'Only Resources with Sponsored ticked are listed.'
					} )
				) ),
				preview( 'animal-writes/sponsored-item', props, 'Sponsored Resource: choose one in the block settings.' )
			);
		},
		save: function () { return null; }
	} );
	wp.blocks.registerBlockType( 'animal-writes/sponsored-banner', {
		apiVersion: 3,
		title: 'Sponsored banner',
		category: 'kits-sponsored',
		icon: 'megaphone',
		description: 'Picture, heading, text and link, labelled "Sponsored".',
		attributes: {
			mediaId: { type: 'integer', 'default': 0 },
			mediaAlt: { type: 'string', 'default': '' },
			heading: { type: 'string', 'default': '' },
			text: { type: 'string', 'default': '' },
			url: { type: 'string', 'default': '' },
			linkText: { type: 'string', 'default': '' },
			sponsor: { type: 'string', 'default': '' }
		},
		supports: { html: false },
		edit: function ( props ) {
			var a = props.attributes;
			var set = function ( k ) { return function ( v ) { var o = {}; o[ k ] = v; props.setAttributes( o ); }; };
			return el(
				'div',
				BE.useBlockProps(),
				el( BE.InspectorControls, null, el( C.PanelBody, { title: 'Sponsored banner' },
					el( BE.MediaUploadCheck, null, el( BE.MediaUpload, {
						allowedTypes: [ 'image' ],
						value: a.mediaId,
						onSelect: function ( m ) { props.setAttributes( { mediaId: m.id, mediaAlt: m.alt || '' } ); },
						render: function ( o ) { return el( C.Button, { variant: 'secondary', onClick: o.open }, a.mediaId ? 'Replace picture' : 'Choose picture' ); }
					} ) ),
					a.mediaId ? el( C.Button, { variant: 'link', isDestructive: true, onClick: function () { props.setAttributes( { mediaId: 0, mediaAlt: '' } ); } }, 'Remove picture' ) : null,
					el( C.TextControl, { label: 'Picture description (alt)', value: a.mediaAlt, onChange: set( 'mediaAlt' ) } ),
					el( C.TextControl, { label: 'Sponsor name', value: a.sponsor, onChange: set( 'sponsor' ) } ),
					el( C.TextControl, { label: 'Heading', value: a.heading, onChange: set( 'heading' ) } ),
					el( C.TextareaControl, { label: 'Text', value: a.text, onChange: set( 'text' ) } ),
					el( C.TextControl, { label: 'Link (URL)', type: 'url', value: a.url, onChange: set( 'url' ) } ),
					el( C.TextControl, { label: 'Button text', value: a.linkText, onChange: set( 'linkText' ), help: 'Empty: the heading is the link.' } )
				) ),
				preview( 'animal-writes/sponsored-banner', props, 'Sponsored banner: add a picture, heading or text in the block settings.' )
			);
		},
		save: function () { return null; }
	} );
} )( window.wp );
