/* Animal Writes blocks in the editor: each is rendered on the server (ServerSideRender) and has no settings of its own. */
( function ( wp ) {
	var el = wp.element.createElement;
	( window.awHubBlocks || [] ).forEach( function ( b ) {
		wp.blocks.registerBlockType( b.name, {
			title: b.title,
			category: 'widgets',
			icon: 'pets',
			edit: function ( props ) {
				return el( 'div', wp.blockEditor.useBlockProps(), el( wp.serverSideRender, { block: b.name, attributes: props.attributes, urlQueryArgs: { post_id: wp.data.select( 'core/editor' ).getCurrentPostId() }, EmptyResponsePlaceholder: function () { return el( 'p', { style: { opacity: 0.6 } }, b.title ); } } ) );
			},
			save: function () { return null; }
		} );
	} );
} )( window.wp );
