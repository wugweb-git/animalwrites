/**
 * Kits → Theme Options, new screen (trial: admin.php?page=kits-options&ui=next).
 *
 * One app header (site, versions, Save), the Kits tabs, a section list and the fields of the chosen section.
 * Everything saves in one request to /wp/v2/settings, and only what changed is sent:
 * - core: site title, tagline, logo, site icon (WordPress's own options);
 * - to:   kits_theme_options fields (a value equal to the theme's is not stored, so Reset sends the theme's value);
 * - pd:   kits_page_defaults._site, the site-wide header, footer, sidebar, title and breadcrumbs ('' = the theme's).
 */
import { createRoot, useState, useEffect, useMemo } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { MediaUpload } from '@wordpress/media-utils';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Flex,
	FlexBlock,
	FlexItem,
	Notice,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';

const data = window.kitsAdminApp || {};
const fields = data.fields || {};
const pdFields = data.pageDefaults?.fields || {};
// Published menus for the menu fields: [ { value: id, label: title } ].
const menus = data.menus || [];
const themeOwn = __( 'The theme’s own', 'kits-core' );
const menuLabel = ( id ) =>
	'' === id ? themeOwn : menus.find( ( m ) => m.value === id )?.label || id;

/**
 * The sections, in order: Identity, Header, Footer, Layout, then the groups of the Theme Options fields not placed in
 * them (today Speed and Older content, which move to Settings in phase 2).
 *
 * @return {Array} [ { slug, label, items, links, info } ].
 */
function sectionsOf() {
	const placed = [
		'announce',
		'sticky_header',
		'header_menu',
		'sticky_bar',
		'sticky_href',
		'sticky_label',
		'footer_menu_1',
		'footer_menu_2',
	];
	const out = [
		{
			slug: 'identity',
			label: __( 'Identity', 'kits-core' ),
			items: [
				[ 'core', 'title' ],
				[ 'core', 'description' ],
				[ 'media', 'site_logo' ],
				[ 'media', 'site_icon' ],
			],
		},
		{
			slug: 'header',
			label: __( 'Header', 'kits-core' ),
			items: [
				[ 'pd', 'header' ],
				[ 'to', 'sticky_header' ],
				[ 'to', 'header_menu' ],
				[ 'to', 'announce' ],
			],
			links: [
				[
					__(
						'Edit what the header holds (logo, menu, buttons)',
						'kits-core'
					),
					data.links?.parts,
				],
				[ __( 'Edit menus', 'kits-core' ), data.links?.menus ],
			],
		},
		{
			slug: 'footer',
			label: __( 'Footer', 'kits-core' ),
			items: [
				[ 'pd', 'footer' ],
				[ 'to', 'footer_menu_1' ],
				[ 'to', 'footer_menu_2' ],
				[ 'to', 'sticky_bar' ],
				[ 'to', 'sticky_href' ],
				[ 'to', 'sticky_label' ],
			],
			links: [
				[
					__( 'Edit what the footer holds', 'kits-core' ),
					data.links?.parts,
				],
				[ __( 'Edit menus', 'kits-core' ), data.links?.menus ],
			],
		},
		{
			slug: 'layout',
			label: __( 'Layout and spacing', 'kits-core' ),
			items: [
				[ 'pd', 'sidebar' ],
				[ 'pd', 'title' ],
				[ 'pd', 'crumbs' ],
			],
			info: data.layoutInfo || [],
			links: [
				[
					__( 'Widths and spacing in Styles', 'kits-core' ),
					data.links?.styles,
				],
			],
		},
	];
	Object.keys( fields ).forEach( ( key ) => {
		const f = fields[ key ];
		if ( 'share' === f.kind || placed.includes( key ) ) {
			return;
		}
		// Font preload is a speed setting (spec: Settings → Performance).
		const group = 'font_preload' === key ? 'Speed' : f.group;
		const slug = group.toLowerCase().replace( /[^a-z0-9]+/g, '-' );
		let g = out.find( ( x ) => x.slug === slug );
		if ( ! g ) {
			g = { slug, label: group, items: [] };
			out.push( g );
		}
		g.items.push( [ 'to', key ] );
	} );
	return out;
}

function sectionFromHash( sections ) {
	const h = window.location.hash.replace( '#', '' );
	return sections.find( ( s ) => s.slug === h ) ? h : sections[ 0 ]?.slug;
}

function ThemeNote( { isTheme, themeLabel, label, onReset } ) {
	return (
		<p className="description">
			{ isTheme
				? __( 'Theme value', 'kits-core' )
				: sprintf(
						/* translators: %s: the theme's value. */
						__( 'Theme value: %s', 'kits-core' ),
						themeLabel || __( 'empty', 'kits-core' )
					) }{ ' ' }
			{ ! isTheme && (
				<Button
					variant="link"
					onClick={ onReset }
					aria-label={ sprintf(
						/* translators: %s: field label. */
						__( 'Reset %s to theme', 'kits-core' ),
						label
					) }
				>
					{ __( 'Reset to theme', 'kits-core' ) }
				</Button>
			) }
		</p>
	);
}

// A kits_theme_options field.
function ThemeOptionField( { field, value, inherited, onChange } ) {
	let control;
	if ( 'switch' === field.kind ) {
		control = (
			<ToggleControl
				__nextHasNoMarginBottom
				label={ field.label }
				help={ field.note }
				checked={ 'on' === value }
				onChange={ ( on ) => onChange( on ? 'on' : 'off' ) }
			/>
		);
	} else if ( 'choice' === field.kind ) {
		control = (
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ field.label }
				help={ field.note }
				value={ value }
				options={ Object.keys( field.choices ).map( ( k ) => ( {
					value: k,
					label: field.choices[ k ],
				} ) ) }
				onChange={ onChange }
			/>
		);
	} else if ( 'menu' === field.kind ) {
		control = (
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ field.label }
				help={ field.note }
				value={ value }
				options={ [
					{ value: '', label: themeOwn },
					...menus,
					...( value && ! menus.some( ( m ) => m.value === value )
						? [
								{
									value,
									label: sprintf(
										/* translators: %s: menu id. */
										__(
											'Menu %s (not published)',
											'kits-core'
										),
										value
									),
								},
							]
						: [] ),
				] }
				onChange={ onChange }
			/>
		);
	} else {
		control = (
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				type={ 'url' === field.kind ? 'url' : 'text' }
				label={ field.label }
				help={ field.note }
				value={ value }
				maxLength={ 'text' === field.kind ? 60 : undefined }
				onChange={ onChange }
			/>
		);
	}
	return (
		<div className="kits-app-field">
			{ control }
			<ThemeNote
				isTheme={ value === inherited }
				themeLabel={
					'menu' === field.kind
						? menuLabel( inherited )
						: ( field.choices?.[ inherited ] ?? inherited )
				}
				label={ field.label }
				onReset={ () => onChange( inherited ) }
			/>
		</div>
	);
}

// A site-wide page default (kits_page_defaults._site): '' is the theme's own.
function PageDefaultField( { name, value, onChange } ) {
	const f = pdFields[ name ];
	if ( ! f ) {
		return null;
	}
	const keys = Object.keys( f.choices || {} );
	if ( ! keys.length ) {
		return (
			<div className="kits-app-field">
				<p>
					<strong>{ f.label }</strong>
				</p>
				<p className="description">{ f.empty }</p>
				{ '' !== value && (
					<ThemeNote
						isTheme={ false }
						themeLabel={ __( 'the theme’s own', 'kits-core' ) }
						label={ f.label }
						onReset={ () => onChange( '' ) }
					/>
				) }
			</div>
		);
	}
	return (
		<div className="kits-app-field">
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ f.label }
				help={ f.note }
				value={ value }
				options={ [
					{ value: '', label: __( 'Theme', 'kits-core' ) },
					...keys.map( ( k ) => ( {
						value: k,
						label: f.choices[ k ],
					} ) ),
				] }
				onChange={ onChange }
			/>
			<ThemeNote
				isTheme={ '' === value }
				themeLabel={ __( 'the theme’s own', 'kits-core' ) }
				label={ f.label }
				onReset={ () => onChange( '' ) }
			/>
		</div>
	);
}

// Site title or tagline.
function CoreTextField( { name, value, onChange } ) {
	const labels = {
		title: [
			__( 'Site title', 'kits-core' ),
			__(
				'Shown in the browser tab and search results, and wherever the theme prints the site name.',
				'kits-core'
			),
		],
		description: [
			__( 'Tagline', 'kits-core' ),
			__( 'A short line about the site.', 'kits-core' ),
		],
	};
	return (
		<div className="kits-app-field">
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ labels[ name ][ 0 ] }
				help={ labels[ name ][ 1 ] }
				value={ value }
				onChange={ onChange }
			/>
		</div>
	);
}

// Logo or site icon: an image from the Media Library (0 = none).
function MediaField( { name, value, onChange } ) {
	const labels = {
		site_logo: [
			__( 'Logo', 'kits-core' ),
			__( 'Shown by the Site Logo block in the header.', 'kits-core' ),
		],
		site_icon: [
			__( 'Site icon', 'kits-core' ),
			__(
				'The small square image in browser tabs and on phone home screens. At least 512 × 512 pixels.',
				'kits-core'
			),
		],
	};
	const [ urls, setUrls ] = useState( data.mediaUrls || {} );
	const [ error, setError ] = useState( '' );
	const url = value ? urls[ value ] : '';
	const id = 'kits-app-' + name;
	return (
		<div className="kits-app-field" role="group" aria-labelledby={ id }>
			<p id={ id }>
				<strong>{ labels[ name ][ 0 ] }</strong>
			</p>
			{ url ? (
				<img
					src={ url }
					alt=""
					style={ { maxWidth: 160, maxHeight: 80, display: 'block' } }
				/>
			) : (
				<p className="description">
					{ __( 'None chosen.', 'kits-core' ) }
				</p>
			) }
			<Flex justify="flex-start" gap={ 2 }>
				<MediaUpload
					allowedTypes={ [ 'image' ] }
					value={ value || undefined }
					onSelect={ ( m ) => {
						if (
							'site_icon' === name &&
							( m.width !== m.height || m.width < 512 )
						) {
							setError(
								__(
									'The site icon must be a square image of at least 512 × 512 pixels.',
									'kits-core'
								)
							);
							return;
						}
						setError( '' );
						setUrls( {
							...urls,
							[ m.id ]: m.sizes?.thumbnail?.url || m.url,
						} );
						onChange( m.id );
					} }
					render={ ( { open } ) => (
						<Button variant="secondary" onClick={ open }>
							{ value
								? __( 'Change', 'kits-core' )
								: __( 'Choose an image', 'kits-core' ) }
						</Button>
					) }
				/>
				{ !! value && (
					<Button
						variant="tertiary"
						isDestructive
						onClick={ () => onChange( 0 ) }
					>
						{ __( 'Remove', 'kits-core' ) }
					</Button>
				) }
			</Flex>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<p className="description">{ labels[ name ][ 1 ] }</p>
		</div>
	);
}

function App() {
	const sections = useMemo( () => sectionsOf(), [] );
	const [ section, setSection ] = useState( () =>
		sectionFromHash( sections )
	);
	const [ saved, setSaved ] = useState( {
		core: data.core || {},
		to: data.saved || {},
		pd: data.pageDefaults?.site || {},
	} );
	const [ edits, setEdits ] = useState( { core: {}, to: {}, pd: {} } );
	const [ status, setStatus ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const inherited = data.inherited || {};

	useEffect( () => {
		const on = () => setSection( sectionFromHash( sections ) );
		window.addEventListener( 'hashchange', on );
		return () => window.removeEventListener( 'hashchange', on );
	}, [ sections ] );

	const base = ( store, k ) => {
		if ( 'to' === store ) {
			return saved.to[ k ] ?? inherited[ k ] ?? '';
		}
		if ( 'pd' === store ) {
			return saved.pd[ k ] ?? '';
		}
		return saved.core[ k ] ?? '';
	};
	const valueOf = ( store, k ) =>
		k in edits[ store ] ? edits[ store ][ k ] : base( store, k );
	const set = ( store, k, v ) =>
		setEdits( { ...edits, [ store ]: { ...edits[ store ], [ k ]: v } } );
	const changed = ( store ) =>
		Object.keys( edits[ store ] ).filter(
			( k ) => edits[ store ][ k ] !== base( store, k )
		);
	const dirty = [ 'core', 'to', 'pd' ].some(
		( s ) => changed( s ).length > 0
	);

	useEffect( () => {
		const warn = ( e ) => {
			if ( dirty ) {
				e.preventDefault();
				e.returnValue = '';
			}
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirty ] );

	const save = () => {
		// Only what changed: every clean-up keeps what is not sent, so a change made elsewhere since this screen
		// opened is not written back.
		const body = {};
		changed( 'core' ).forEach( ( k ) => ( body[ k ] = edits.core[ k ] ) );
		const to = changed( 'to' );
		if ( to.length ) {
			body.kits_theme_options = Object.fromEntries(
				to.map( ( k ) => [ k, edits.to[ k ] ] )
			);
		}
		const pd = changed( 'pd' );
		if ( pd.length ) {
			body.kits_page_defaults = {
				_site: Object.fromEntries(
					pd.map( ( k ) => [ k, edits.pd[ k ] ] )
				),
			};
		}
		setBusy( true );
		setStatus( null );
		apiFetch( { path: '/wp/v2/settings', method: 'POST', data: body } )
			.then( ( res ) => {
				const obj = ( v, fallback ) =>
					v && 'object' === typeof v ? v : fallback;
				const core = { ...saved.core };
				Object.keys( core ).forEach( ( k ) => {
					if ( k in res ) {
						core[ k ] = res[ k ] ?? core[ k ];
					}
				} );
				setSaved( {
					core,
					// A stored value the schema cannot read comes back as null: keep what this screen knows.
					to: obj( res.kits_theme_options, {
						...saved.to,
						...edits.to,
					} ),
					pd:
						res.kits_page_defaults &&
						'object' === typeof res.kits_page_defaults
							? obj( res.kits_page_defaults._site, {} ) // No _site: every choice is the theme's.
							: { ...saved.pd, ...edits.pd },
				} );
				setEdits( { core: {}, to: {}, pd: {} } );
				setStatus( {
					type: 'success',
					text: __( 'Saved.', 'kits-core' ),
				} );
			} )
			.catch( ( err ) =>
				setStatus( {
					type: 'error',
					text:
						err?.message ||
						__( 'Not saved. Try again.', 'kits-core' ),
				} )
			)
			.finally( () => setBusy( false ) );
	};

	const current =
		sections.find( ( s ) => s.slug === section ) || sections[ 0 ];

	const item = ( [ kind, k ] ) => {
		// Logo and site icon are WordPress options like the title: they live in the core store.
		const store = 'media' === kind ? 'core' : kind;
		const onChange = ( v ) => set( store, k, v );
		const value = valueOf( store, k );
		if ( 'to' === store ) {
			return fields[ k ] ? (
				<ThemeOptionField
					key={ k }
					field={ fields[ k ] }
					value={ value }
					inherited={ inherited[ k ] ?? '' }
					onChange={ onChange }
				/>
			) : null;
		}
		if ( 'pd' === store ) {
			return (
				<PageDefaultField
					key={ k }
					name={ k }
					value={ value }
					onChange={ onChange }
				/>
			);
		}
		if ( 'media' === kind ) {
			return (
				<MediaField
					key={ k }
					name={ k }
					value={ value }
					onChange={ onChange }
				/>
			);
		}
		return (
			<CoreTextField
				key={ k }
				name={ k }
				value={ value }
				onChange={ onChange }
			/>
		);
	};

	return (
		<div className="kits-app">
			{ /* The header stays in view while the fields scroll (32px: the admin bar). */ }
			<div style={ { position: 'sticky', top: 32, zIndex: 20 } }>
				<Card>
					<CardHeader>
						<Flex align="center" wrap>
							<FlexBlock>
								<h1 className="kits-app-title">
									{ __( 'Theme Options', 'kits-core' ) }
								</h1>
								<p className="description">
									{ sprintf(
										/* translators: 1: site name, 2: theme name and version, 3: Kits Core version. */
										__(
											'%1$s · %2$s · Kits Core %3$s',
											'kits-core'
										),
										saved.core.title || data.site,
										data.theme,
										data.coreVersion
									) }
								</p>
							</FlexBlock>
							<FlexItem>
								<span
									className="kits-app-status"
									role="status"
									aria-live="polite"
								>
									{ dirty && ! busy
										? __( 'Changes not saved', 'kits-core' )
										: '' }
								</span>{ ' ' }
								<Button
									variant="primary"
									onClick={ save }
									disabled={ ! dirty || busy }
									isBusy={ busy }
									__next40pxDefaultSize
								>
									{ __( 'Save', 'kits-core' ) }
								</Button>
							</FlexItem>
						</Flex>
					</CardHeader>
				</Card>
			</div>
			<nav
				className="nav-tab-wrapper wp-clearfix"
				aria-label={ __( 'Kits screens', 'kits-core' ) }
			>
				{ ( data.tabs || [] ).map( ( t ) => (
					<a
						key={ t.slug }
						className={
							'nav-tab' + ( t.current ? ' nav-tab-active' : '' )
						}
						href={ t.url }
						aria-current={ t.current ? 'page' : undefined }
					>
						{ t.label }
					</a>
				) ) }
			</nav>
			{ status && (
				<Notice
					status={ status.type }
					onRemove={ () => setStatus( null ) }
				>
					{ status.text }
				</Notice>
			) }
			<p>
				{ __(
					'Trial screen. The current screen keeps working:',
					'kits-core'
				) }{ ' ' }
				<a href={ data.oldUrl }>
					{ __( 'open the current Theme Options', 'kits-core' ) }
				</a>
			</p>
			<Flex align="flex-start" gap={ 6 } wrap>
				<FlexItem>
					<nav aria-label={ __( 'Sections', 'kits-core' ) }>
						<Card>
							<CardBody>
								<ul>
									{ sections.map( ( s ) => (
										<li key={ s.slug }>
											<a
												href={ '#' + s.slug }
												aria-current={
													s.slug === current?.slug
														? 'true'
														: undefined
												}
											>
												{ s.slug === current?.slug ? (
													<strong>{ s.label }</strong>
												) : (
													s.label
												) }
											</a>
										</li>
									) ) }
									{ data.shareUrl && (
										<li>
											<a href={ data.shareUrl }>
												{ __(
													'Share bar (current screen)',
													'kits-core'
												) }
											</a>
										</li>
									) }
								</ul>
							</CardBody>
						</Card>
					</nav>
				</FlexItem>
				<FlexBlock>
					{ current && (
						<Card>
							<CardHeader>
								<h2>{ current.label }</h2>
							</CardHeader>
							<CardBody>
								{ current.items.map( item ) }
								{ ( current.info || [] ).map( ( [ k, v ] ) => (
									<p key={ k }>
										<strong>{ k }:</strong> { v }
									</p>
								) ) }
								{ ( current.links || [] )
									.filter( ( [ , href ] ) => href )
									.map( ( [ label, href ] ) => (
										<p key={ label }>
											<a href={ href }>{ label }</a>
										</p>
									) ) }
							</CardBody>
						</Card>
					) }
				</FlexBlock>
			</Flex>
		</div>
	);
}

const root = document.getElementById( 'kits-admin-app' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
