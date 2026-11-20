/**
 * RankWeb Gutenberg Block Editor Sidebar & Live SEO Score Engine.
 * Benchmark: Rank Math Pro / Yoast SEO.
 */
( function( wp ) {
	const { registerPlugin } = wp.plugins;
	const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
	const { PanelBody, TextControl, SelectControl, ButtonGroup, Button } = wp.components;
	const { useSelect, useDispatch } = wp.data;
	const { useState, useEffect, createElement: el, Fragment } = wp.element;
	const { __ } = wp.i18n;

	function calculateSeoScore( title, content, keyword ) {
		let score = 10;
		if ( ! keyword || keyword.trim() === '' ) {
			return { score: 15, checks: [
				{ text: __( 'Set a Focus Keyword to enable comprehensive analysis', 'rankweb' ), pass: false }
			] };
		}

		const kw = keyword.toLowerCase().trim();
		const lcTitle = ( title || '' ).toLowerCase();
		const lcContent = ( content || '' ).toLowerCase();
		const plainContent = lcContent.replace( /<[^>]*>?/gm, ' ' );
		const wordCount = plainContent.trim().split( /\s+/ ).filter( Boolean ).length;

		const checks = [];

		// 1. Keyword in Title (+20)
		const kwInTitle = lcTitle.includes( kw );
		checks.push({
			text: kwInTitle ? __( 'Focus Keyword found in SEO Title', 'rankweb' ) : __( 'Add Focus Keyword to SEO Title', 'rankweb' ),
			pass: kwInTitle
		});
		if ( kwInTitle ) score += 20;

		// 2. Keyword in First 10% of Content (+15)
		const first100Words = plainContent.split( /\s+/ ).slice( 0, 100 ).join( ' ' );
		const kwInIntro = first100Words.includes( kw );
		checks.push({
			text: kwInIntro ? __( 'Focus Keyword used in the first 100 words', 'rankweb' ) : __( 'Include Focus Keyword near the beginning of your content', 'rankweb' ),
			pass: kwInIntro
		});
		if ( kwInIntro ) score += 15;

		// 3. Content Length (+20)
		const goodLength = wordCount >= 600;
		const okLength = wordCount >= 300;
		checks.push({
			text: goodLength ? sprintf( __( 'Content is %d words long (Good length)', 'rankweb' ), wordCount ) : sprintf( __( 'Content is %d words. Recommended is 600+ words', 'rankweb' ), wordCount ),
			pass: okLength
		});
		if ( goodLength ) score += 20;
		else if ( okLength ) score += 10;

		// 4. Keyword in Subheadings (+15)
		const hasHeadings = /<h[2-4][^>]*>.*?<\/h[2-4]>/i.test( content );
		const kwInHeadings = hasHeadings && /<h[2-4][^>]*>.*?(?=.*?)/i.test( content ) && lcContent.includes( kw );
		checks.push({
			text: kwInHeadings ? __( 'Focus Keyword found in H2/H3 subheadings', 'rankweb' ) : __( 'Use Focus Keyword in at least one subheading', 'rankweb' ),
			pass: kwInHeadings
		});
		if ( kwInHeadings ) score += 15;

		// 5. Internal Links (+10)
		const internalLinkCount = ( content.match( /href=[\"'][^\"']*(?:animalwrites\.in|\/)[^\"']*[\"']/gi ) || [] ).length;
		const hasInternal = internalLinkCount >= 2;
		checks.push({
			text: hasInternal ? sprintf( __( '%d internal links found', 'rankweb' ), internalLinkCount ) : __( 'Add at least 2 internal links to related content', 'rankweb' ),
			pass: hasInternal
		});
		if ( hasInternal ) score += 10;

		// 6. External Links (+10)
		const externalLinkCount = ( content.match( /href=[\"']https?:\/\/(?!animalwrites\.in)[^\"']+[\"']/gi ) || [] ).length;
		const hasExternal = externalLinkCount >= 1;
		checks.push({
			text: hasExternal ? sprintf( __( '%d authoritative external links found', 'rankweb' ), externalLinkCount ) : __( 'Add at least 1 external link to an authority source', 'rankweb' ),
			pass: hasExternal
		});
		if ( hasExternal ) score += 10;

		return {
			score: Math.min( 100, Math.max( 0, score ) ),
			checks: checks
		};
	}

	function RankWebSidebar() {
		const meta = useSelect( ( select ) => select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {} );
		const title = useSelect( ( select ) => select( 'core/editor' ).getEditedPostAttribute( 'title' ) || '' );
		const content = useSelect( ( select ) => select( 'core/editor' ).getEditedPostContent() || '' );
		const excerpt = useSelect( ( select ) => select( 'core/editor' ).getEditedPostAttribute( 'excerpt' ) || '' );
		const slug = useSelect( ( select ) => select( 'core/editor' ).getEditedPostAttribute( 'slug' ) || '' );
		const { editPost } = useDispatch( 'core/editor' );

		const [ previewMode, setPreviewMode ] = useState( 'desktop' );
		const keyword = meta.rank_math_focus_keyword || '';
		const schemaType = meta._rankweb_schema_type || 'Article';

		const { score, checks } = calculateSeoScore( title, content, keyword );

		let scoreClass = 'rankweb-score-pill--bad';
		if ( score >= 80 ) scoreClass = 'rankweb-score-pill--good';
		else if ( score >= 50 ) scoreClass = 'rankweb-score-pill--ok';

		const displayUrl = ( window.rankwebEditorData ? window.rankwebEditorData.siteUrl : 'https://animalwrites.in' ) + '/' + ( slug || 'sample-post' ) + '/';
		const displayDesc = excerpt ? excerpt.substring( 0, 155 ) : ( content ? content.replace( /<[^>]*>?/gm, ' ' ).substring( 0, 155 ) + '...' : __( 'Add an excerpt or introduction to provide an effective meta description snippet.', 'rankweb' ) );

		return el(
			Fragment,
			null,
			el(
				PluginSidebarMoreMenuItem,
				{ target: 'rankweb-seo-sidebar', icon: 'search' },
				'RankWeb SEO'
			),
			el(
				PluginSidebar,
				{ name: 'rankweb-seo-sidebar', title: 'RankWeb SEO', icon: 'search' },
				el(
					'div',
					{ className: 'rankweb-sidebar-content' },
					el(
						'div',
						{ className: 'rankweb-score-card' },
						el( 'span', { className: 'rankweb-score-card__label' }, __( 'SEO Content Score', 'rankweb' ) ),
						el( 'span', { className: 'rankweb-score-pill ' + scoreClass }, score + ' / 100' )
					),
					el(
						TextControl,
						{
							label: __( 'Focus Keyword', 'rankweb' ),
							value: keyword,
							help: __( 'Enter the primary search query this post aims to rank for.', 'rankweb' ),
							onChange: function( nextValue ) {
								editPost( { meta: Object.assign( {}, meta, { rank_math_focus_keyword: nextValue, _rankweb_score: score } ) } );
							}
						}
					),
					el(
						SelectControl,
						{
							label: __( 'Structured Data Schema Type', 'rankweb' ),
							value: schemaType,
							options: [
								{ label: 'Article', value: 'Article' },
								{ label: 'NewsArticle', value: 'NewsArticle' },
								{ label: 'Event', value: 'Event' },
								{ label: 'FAQPage', value: 'FAQPage' },
								{ label: 'Organization', value: 'Organization' }
							],
							onChange: function( nextValue ) {
								editPost( { meta: Object.assign( {}, meta, { _rankweb_schema_type: nextValue } ) } );
							}
						}
					),
					el(
						'div',
						{ style: { marginTop: '20px' } },
						el( 'strong', { style: { display: 'block', marginBottom: '8px' } }, __( 'Google SERP Preview', 'rankweb' ) ),
						el(
							ButtonGroup,
							{ style: { marginBottom: '10px' } },
							el( Button, { isSmall: true, isPrimary: previewMode === 'desktop', onClick: function() { setPreviewMode('desktop'); } }, __( 'Desktop', 'rankweb' ) ),
							el( Button, { isSmall: true, isPrimary: previewMode === 'mobile', onClick: function() { setPreviewMode('mobile'); } }, __( 'Mobile', 'rankweb' ) )
						),
						el(
							'div',
							{ className: 'rankweb-serp-preview' },
							el( 'div', { className: 'rankweb-serp-preview__url' }, displayUrl ),
							el( 'div', { className: 'rankweb-serp-preview__title' }, title || __( 'Post Title Example', 'rankweb' ) ),
							el( 'div', { className: 'rankweb-serp-preview__desc' }, displayDesc )
						)
					),
					el(
						'div',
						{ style: { marginTop: '20px' } },
						el( 'strong', { style: { display: 'block', marginBottom: '10px' } }, __( 'SEO Checklist & Content Analysis', 'rankweb' ) ),
						el(
							'ul',
							{ className: 'rankweb-checklist' },
							checks.map( function( c, idx ) {
								return el(
									'li',
									{ key: idx, className: 'rankweb-check-item' },
									el(
										'span',
										{ className: 'rankweb-check-icon ' + ( c.pass ? 'rankweb-check-icon--pass' : 'rankweb-check-icon--fail' ) },
										c.pass ? '✓' : '✕'
									),
									el( 'span', null, c.text )
								);
							} )
						)
					)
				)
			)
		);
	}

	registerPlugin( 'rankweb-seo', {
		render: RankWebSidebar,
		icon: 'search'
	} );
} )( window.wp );
