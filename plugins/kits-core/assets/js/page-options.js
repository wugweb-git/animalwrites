/**
 * Page Options panel in the editor's document sidebar (includes/page-options.php). Each field is post meta; empty is
 * Inherit, which shows what the item gets from its kind of page, the site-wide default or the theme. Fields are grouped
 * as on Kits → Theme Options: Header, Content, Footer. Core components only, no build step.
 */
( function ( wp, data ) {
	if ( ! data || ! data.fields || ! data.fields.length ) {
		return;
	}
	const el = wp.element.createElement;
	const { SelectControl, PanelRow } = wp.components;
	const { useSelect } = wp.data;
	const { useEntityProp } = wp.coreData;
	const Panel = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) || ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );

	const PageOptions = () => {
		const postType = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostType(), [] );
		const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );
		if ( ! meta ) {
			return null;
		}
		const field = ( f ) =>
			el( PanelRow, { key: f.key }, el( SelectControl, {
				label: f.label,
				value: meta[ '_kits_' + f.key ] || '',
				options: [ { value: '', label: f.inherit } ].concat( f.choices ),
				onChange: ( v ) => setMeta( { ...meta, [ '_kits_' + f.key ]: v } ),
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true,
			} ) );
		const groups = [ 'Header', 'Content', 'Footer' ];
		return el(
			Panel,
			{ name: 'kits-page-options', title: 'Page Options' },
			groups.map( ( g ) => {
				const items = data.fields.filter( ( f ) => ( f.group || 'Content' ) === g );
				return items.length ? el( 'div', { key: g, role: 'group', 'aria-label': g },
					el( 'h3', { style: { fontSize: '11px', fontWeight: 500, textTransform: 'uppercase', margin: '16px 0 8px' } }, g ),
					items.map( field )
				) : null;
			} )
		);
	};

	wp.plugins.registerPlugin( 'kits-page-options', { render: PageOptions } );
} )( window.wp, window.kitsPageOptions );
