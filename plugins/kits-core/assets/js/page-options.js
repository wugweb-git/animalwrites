/**
 * Page Options panel in the editor's document sidebar (includes/page-options.php). Each field is post meta; empty is
 * Inherit, which shows what the item gets from its post type or the theme. Core components only, no build step.
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
		return el(
			Panel,
			{ name: 'kits-page-options', title: 'Page Options' },
			data.fields.map( ( f ) =>
				el( PanelRow, { key: f.key }, el( SelectControl, {
					label: f.label,
					value: meta[ '_kits_' + f.key ] || '',
					options: [ { value: '', label: f.inherit } ].concat( f.choices ),
					onChange: ( v ) => setMeta( { ...meta, [ '_kits_' + f.key ]: v } ),
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				} ) )
			)
		);
	};

	wp.plugins.registerPlugin( 'kits-page-options', { render: PageOptions } );
} )( window.wp, window.kitsPageOptions );
