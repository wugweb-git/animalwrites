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
	// The earlier name (animal-writes/breadcrumb), registered by RankWeb only when Animal Writes Core is not active
	// (breadcrumbs-render.php), so pages that hold it still open in the editor. Hidden from the inserter.
	if ( window.rankwebBreadcrumbAlias && ! wp.blocks.getBlockType( 'animal-writes/breadcrumb' ) ) {
		wp.blocks.registerBlockType( 'animal-writes/breadcrumb', {
			title: 'Breadcrumb (earlier name)',
			category: 'theme',
			supports: { html: false, inserter: false },
			edit: function () {
				return el( 'div', wp.blockEditor.useBlockProps(), el( wp.serverSideRender, { block: 'animal-writes/breadcrumb' } ) );
			},
			save: function () {
				return null;
			}
		} );
	}
} )( window.wp );
