/**
 * RankWeb content panel (block editor sidebar). Read only: data from GET /aw/v1/content-advice (includes/content-advice.php).
 * Plain script on WordPress's own packages, so no build step.
 */
( function ( wp ) {
	if ( ! wp || ! wp.plugins || ! wp.element || ! wp.components ) {
		return;
	}
	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;
	var C = wp.components;
	var Sidebar = ( wp.editor && wp.editor.PluginSidebar ) || ( wp.editPost && wp.editPost.PluginSidebar );
	var MoreItem = ( wp.editor && wp.editor.PluginSidebarMoreMenuItem ) || ( wp.editPost && wp.editPost.PluginSidebarMoreMenuItem );
	if ( ! Sidebar ) {
		return;
	}

	var icon = el(
		'svg',
		{ xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24, 'aria-hidden': true },
		el( 'path', { d: 'M4 19h16v2H4v-2zm2-3h2V9H6v7zm5 0h2V4h-2v12zm5 0h2v-5h-2v5z' } )
	);

	function Mark( props ) {
		// Green check when found, grey dash when not: the state is also in the text for screen readers.
		return el(
			'span',
			{
				className: 'rankweb-mark',
				style: { color: props.ok ? '#008a20' : '#757575', fontWeight: 600, marginRight: 6 },
			},
			props.ok ? '✓' : '–',
			el( C.VisuallyHidden, null, props.ok ? __( 'in the text', 'aw-seo-extras' ) : __( 'not in the text', 'aw-seo-extras' ) )
		);
	}

	function Row( props ) {
		return el( 'li', { style: { margin: '0 0 8px', lineHeight: 1.4 } }, props.children );
	}

	function Panel() {
		var postId = wp.data.useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostId();
		}, [] );
		var saved = wp.data.useSelect( function ( select ) {
			var e = select( 'core/editor' );
			return e.isSavingPost() || e.isAutosavingPost();
		}, [] );
		var s = useState( { loading: true } );
		var state = s[ 0 ];
		var setState = s[ 1 ];

		useEffect(
			function () {
				if ( ! postId || saved ) {
					return;
				}
				setState( { loading: true } );
				wp.apiFetch( { path: wp.url.addQueryArgs( '/aw/v1/content-advice', { id: postId } ) } )
					.then( function ( data ) {
						setState( { data: data } );
					} )
					.catch( function ( err ) {
						setState( { error: ( err && err.message ) || __( 'Could not load the advice.', 'aw-seo-extras' ) } );
					} );
			},
			[ postId, saved ]
		);

		if ( state.loading ) {
			return el( C.PanelBody, null, el( C.Spinner ) );
		}
		if ( state.error ) {
			return el( C.PanelBody, null, el( C.Notice, { status: 'error', isDismissible: false }, state.error ) );
		}
		var d = state.data;

		var focus = el(
			C.PanelBody,
			{ title: __( 'Focus keyphrases', 'aw-seo-extras' ), initialOpen: true },
			d.focus.length
				? el(
						'ul',
						null,
						d.focus.map( function ( f, i ) {
							return el(
								Row,
								{ key: i },
								el( Mark, { ok: f.in_text } ),
								el( 'strong', null, f.phrase ),
								f.primary ? ' ' + __( '(primary)', 'aw-seo-extras' ) : '',
								f.owners.length
									? el(
											C.Notice,
											{ status: 'warning', isDismissible: false },
											__( 'Already the focus of:', 'aw-seo-extras' ) + ' ',
											f.owners.map( function ( o, j ) {
												return el( 'a', { key: j, href: o.edit }, o.title + ( j < f.owners.length - 1 ? ', ' : '' ) );
											} )
									  )
									: null
							);
						} )
				  )
				: el( 'p', null, __( 'No focus keyphrase yet. Set one in Rank Math.', 'aw-seo-extras' ) )
		);

		var queries = el(
			C.PanelBody,
			{ title: __( 'Searches it shows for (28 days)', 'aw-seo-extras' ), initialOpen: true },
			! d.insights
				? el( 'p', null, __( 'Turn on InsightWeb (Search Insights) to see the searches this page appears for.', 'aw-seo-extras' ) )
				: d.queries.length
				? el(
						'ul',
						null,
						d.queries.map( function ( q, i ) {
							return el(
								Row,
								{ key: i },
								el( Mark, { ok: q.in_text } ),
								q.query,
								el(
									'div',
									{ style: { color: '#757575', fontSize: 12 } },
									sprintf(
										/* translators: 1: clicks, 2: impressions, 3: average position. */
										__( '%1$d clicks · %2$d impressions · position %3$s', 'aw-seo-extras' ),
										q.clicks,
										q.impr,
										null === q.pos ? '–' : String( q.pos )
									)
								)
							);
						} )
				  )
				: el( 'p', null, __( 'No searches recorded for this page in the last 28 days.', 'aw-seo-extras' ) )
		);

		var links = el(
			C.PanelBody,
			{ title: __( 'Internal links to add', 'aw-seo-extras' ), initialOpen: false },
			d.links.length
				? el(
						'ul',
						null,
						d.links.map( function ( l, i ) {
							return el(
								Row,
								{ key: i },
								'“' + l.phrase + '” → ',
								el( C.ExternalLink, { href: l.url }, l.title )
							);
						} )
				  )
				: el( 'p', null, __( 'No missing links found: every page whose keyphrase is in this text is linked.', 'aw-seo-extras' ) )
		);

		var shape = el(
			C.PanelBody,
			{ title: __( 'Length and headings', 'aw-seo-extras' ), initialOpen: false },
			el(
				'p',
				null,
				sprintf(
					/* translators: 1: words, 2: H2 count, 3: H3 count. */
					__( 'This item: %1$d words, %2$d H2, %3$d H3.', 'aw-seo-extras' ),
					d.shape.words,
					d.shape.h2,
					d.shape.h3
				)
			),
			el(
				'p',
				{ style: { color: '#757575' } },
				sprintf(
					/* translators: 1: number of items compared, 2: words, 3: H2 count, 4: H3 count. */
					__( 'Typical of %1$d published items of this type: %2$d words, %3$d H2, %4$d H3.', 'aw-seo-extras' ),
					d.typical.n,
					d.typical.words,
					d.typical.h2,
					d.typical.h3
				)
			)
		);

		var questions = el(
			C.PanelBody,
			{ title: __( 'FAQ questions', 'aw-seo-extras' ), initialOpen: false },
			d.questions.length
				? el(
						'ul',
						null,
						d.questions.map( function ( q, i ) {
							return el( Row, { key: i }, el( Mark, { ok: ! q.hidden } ), q.q, q.hidden ? el( 'div', { style: { color: '#757575', fontSize: 12 } }, q.hidden ) : null );
						} )
				  )
				: el( 'p', null, __( 'No automatic questions for this item.', 'aw-seo-extras' ) )
		);

		return el( wp.element.Fragment, null, focus, queries, links, shape, questions );
	}

	wp.plugins.registerPlugin( 'rankweb-content', {
		icon: icon,
		render: function () {
			return el(
				wp.element.Fragment,
				null,
				MoreItem ? el( MoreItem, { target: 'rankweb-content', icon: icon }, __( 'RankWeb content', 'aw-seo-extras' ) ) : null,
				el( Sidebar, { name: 'rankweb-content', title: __( 'RankWeb content', 'aw-seo-extras' ), icon: icon }, el( Panel ) )
			);
		},
	} );
} )( window.wp );
