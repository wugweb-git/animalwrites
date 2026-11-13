/**
 * The way back from the Site Editor to Kits → Theme Options (includes/admin-app.php): while the header or footer part,
 * or a page template, is open, the document sidebar shows a "Kits Theme Options" panel with a link to the section that
 * holds that part's switches. Core components only, no build step; it changes nothing it shows.
 */
( function ( wp, data ) {
	if ( ! wp || ! wp.plugins || ! data || ! data.links ) {
		return;
	}
	const el = wp.element.createElement;
	const { useSelect } = wp.data;
	const DocPanel = wp.editor && wp.editor.PluginDocumentSettingPanel;
	const TplPanel = wp.editSite && wp.editSite.PluginTemplateSettingPanel; // WordPress before 6.6
	if ( ! DocPanel && ! TplPanel ) {
		return;
	}

	// The Theme Options section for what is open: header and footer parts by slug, any template → Content types.
	const linkFor = ( type, id ) => {
		const slug = String( id || '' ).split( '//' ).pop();
		if ( 'wp_template_part' === type ) {
			return data.links[ slug ] || null;
		}
		return 'wp_template' === type ? data.links.templates || null : null;
	};

	const BackLink = () => {
		const { type, id } = useSelect( ( select ) => {
			const ed = select( 'core/editor' );
			return { type: ed.getCurrentPostType(), id: ed.getCurrentPostId() };
		}, [] );
		const link = linkFor( type, id );
		if ( ! link ) {
			return null;
		}
		const body = el( 'p', null, link.text, ' ', el( 'a', { href: link.url }, link.label ) );
		return DocPanel
			? el( DocPanel, { name: 'kits-theme-options-link', title: data.title }, body )
			: el( TplPanel, null, body );
	};

	wp.plugins.registerPlugin( 'kits-theme-options-link', { render: BackLink } );
} )( window.wp, window.kitsSiteEditorLinks );
