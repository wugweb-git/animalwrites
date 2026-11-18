/* RankWeb Breadcrumbs block in the editor: rendered on the server (ServerSideRender), no settings of its own. */
( function ( wp ) {
	var el = wp.element.createElement;
	wp.blocks.registerBlockType( 'rankweb/breadcrumbs', {
		edit: function ( props ) {
			var id = wp.data.select( 'core/editor' ) ? wp.data.select( 'core/editor' ).getCurrentPostId() : 0;
			return el(
				'div',
				wp.blockEditor.useBlockProps(),
				el( wp.serverSideRender, {
					block: 'rankweb/breadcrumbs',
					attributes: props.attributes,
					urlQueryArgs: id ? { post_id: id } : {},
					EmptyResponsePlaceholder: function () {
						return el( 'p', { style: { opacity: 0.6 } }, 'Breadcrumbs' );
					}
				} )
			);
		},
		save: function () {
			return null;
		}
	} );
} )( window.wp );
