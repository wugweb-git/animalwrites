/**
 * RankWeb Breadcrumbs block in the editor (rankweb/breadcrumbs, blocks/breadcrumbs/block.json): rendered on the
 * server through ServerSideRender, no settings of its own. Also the earlier name, animal-writes/breadcrumb, which
 * RankWeb registers as an alias (includes/breadcrumbs-render.php sets window.rankwebBreadcrumbAlias), so pages that
 * hold it still open in the editor. Hidden from the inserter.
 */
import { registerBlockType, getBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { select } from '@wordpress/data';
import ServerSideRender from '@wordpress/server-side-render';

function Preview( { name, attributes } ) {
	const editor = select( 'core/editor' );
	const id = editor ? editor.getCurrentPostId() : 0;
	return (
		<div { ...useBlockProps() }>
			<ServerSideRender
				block={ name }
				attributes={ attributes }
				urlQueryArgs={ id ? { post_id: id } : {} }
				EmptyResponsePlaceholder={ () => (
					<p style={ { opacity: 0.6 } }>Breadcrumbs</p>
				) }
			/>
		</div>
	);
}

registerBlockType( 'rankweb/breadcrumbs', {
	edit: ( { attributes } ) => (
		<Preview name="rankweb/breadcrumbs" attributes={ attributes } />
	),
	save: () => null,
} );

if (
	window.rankwebBreadcrumbAlias &&
	! getBlockType( 'animal-writes/breadcrumb' )
) {
	registerBlockType( 'animal-writes/breadcrumb', {
		apiVersion: 3,
		title: 'Breadcrumb (earlier name)',
		category: 'theme',
		supports: { html: false, inserter: false },
		edit: ( { attributes } ) => (
			<Preview
				name="animal-writes/breadcrumb"
				attributes={ attributes }
			/>
		),
		save: () => null,
	} );
}
