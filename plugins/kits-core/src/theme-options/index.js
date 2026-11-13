/**
 * Kits → Theme Options (admin.php?page=kits-options; the older screen is at &ui=classic until it is retired).
 *
 * On the Kits plugins' look (docs/kits-plugin-ui.md, window.kits.shell): the app header (Kits icon, name, version, the
 * five Kits tabs, Save), the sections as chips and the chosen section as a card with its fields.
 * Sections not built here yet show today's fields, printed by PHP (kits_admin_app_legacy()) and moved into the
 * section; they save with their own button, as before.
 * Everything saves in one request to /wp/v2/settings, and only what changed is sent:
 * - to:   kits_theme_options fields (a value equal to the theme's is not stored, so Reset sends the theme's value);
 * - pd:   kits_page_defaults._site, the site-wide sidebar, title and breadcrumbs ('' = the theme's).
 * Site title, logo, icon, menus and header/footer parts are WordPress's own screens (Settings, Site Editor).
 * Sections that edit Styles save the site's Global Styles in the same Save (global-styles.js); they are modules
 * { slug, label, Body } listed in extraSections.
 */
import {
	createRoot,
	useState,
	useEffect,
	useMemo,
	useRef,
} from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import {
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useGlobalStyles } from './global-styles';
import { ThemeNote } from './theme-note';
import cards from './sections/cards';

// Sections in their own files, after Layout, in this order.
const extraSections = [ cards ]; // Colours, type and buttons are Styles' (owner, 11 Nov 2026).

const { AppShell, AppHeader, ChipRow, SectionCard, Notice, Button } =
	window.kits.shell;
const data = window.kitsAdminApp || {};
const fields = data.fields || {};
const pdFields = data.pageDefaults?.fields || {};

/**
 * The sections, in order: Header, Footer, Layout, Cards, then the sections
 * that still show today's fields (Content types, Socials, Share bar; a built section replaces its fallback), then the
 * groups of any Theme Options fields not placed in them. Fields shown on Kits → Settings (speed, older content, font preload) are left out.
 *
 * @return {Array} [ { slug, label, items, links, info, legacy } ].
 */
function sectionsOf() {
	const placed = [
		'announce',
		'sticky_header',
		'sticky_bar',
		'sticky_href',
		'sticky_label',
	];
	const out = [
		{
			slug: 'header',
			label: __( 'Header', 'kits-core' ),
			items: [
				[ 'to', 'sticky_header' ],
				[ 'to', 'announce' ],
			],
			links: [
				[
					__(
						'Edit the header in the Site Editor (logo, menu, buttons, announcement text)',
						'kits-core'
					),
					data.links?.header || data.links?.parts,
				],
				[ __( 'Edit menus', 'kits-core' ), data.links?.menus ],
			],
		},
		{
			slug: 'footer',
			label: __( 'Footer', 'kits-core' ),
			items: [
				[ 'to', 'sticky_bar' ],
				[ 'to', 'sticky_href' ],
				[ 'to', 'sticky_label' ],
			],
			links: [
				[
					__(
						'Edit the footer in the Site Editor (links, profile icons)',
						'kits-core'
					),
					data.links?.footer || data.links?.parts,
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
				[
					__( 'Each kind of page’s layout in Templates', 'kits-core' ),
					data.links?.templates,
				],
			],
		},
		...extraSections,
	];
	const into = data.legacyInto || {};
	out.forEach( ( s ) => {
		if ( into[ s.slug ] ) {
			s.legacy = into[ s.slug ];
		}
	} );
	// Today's fields for a section not built yet; once it is built, the built one wins.
	( data.legacy || [] ).forEach( ( l ) => {
		if ( ! out.some( ( x ) => x.slug === l.slug ) ) {
			out.push( {
				slug: l.slug,
				label: l.label,
				items: [],
				legacy: l.slug,
			} );
		}
	} );
	const elsewhere = data.elsewhere || [];
	Object.keys( fields ).forEach( ( key ) => {
		const f = fields[ key ];
		if (
			'share' === f.kind ||
			placed.includes( key ) ||
			elsewhere.includes( key )
		) {
			return;
		}
		const group = f.group;
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
	if ( sections.find( ( s ) => s.slug === h ) ) {
		return h;
	}
	// After a section saved through options.php (&kits_section=…), or an older address (&section=…).
	const asked = data.initial || '';
	return sections.find( ( s ) => s.slug === asked )
		? asked
		: sections[ 0 ]?.slug;
}

/**
 * Today's fields of a section not built here yet: the PHP-printed block is moved in while the section is open and
 * back to its hidden holder when another opens, so its forms keep what was typed.
 *
 * @param {Object} props
 * @param {string} props.slug The block's data-kits-legacy value.
 */
function Legacy( { slug } ) {
	const ref = useRef( null );
	useEffect( () => {
		const node = document.querySelector(
			'[data-kits-legacy="' + slug + '"]'
		);
		const box = ref.current;
		if ( ! node || ! box ) {
			return undefined;
		}
		const holder = document.getElementById( 'kits-legacy-holder' );
		box.appendChild( node );
		return () => holder && holder.appendChild( node );
	}, [ slug ] );
	return (
		<>
			<p className="description">
				{ __(
					'These fields save with their own button below.',
					'kits-core'
				) }
			</p>
			<div ref={ ref } />
		</>
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
				themeLabel={ field.choices?.[ inherited ] ?? inherited }
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
		return null;
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

function App() {
	const sections = useMemo( () => sectionsOf(), [] );
	const [ section, setSection ] = useState( () =>
		sectionFromHash( sections )
	);
	const [ saved, setSaved ] = useState( {
		to: data.saved || {},
		pd: data.pageDefaults?.site || {},
	} );
	const [ edits, setEdits ] = useState( { to: {}, pd: {} } );
	const [ status, setStatus ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const inherited = data.inherited || {};
	const gs = useGlobalStyles();

	useEffect( () => {
		const on = () => setSection( sectionFromHash( sections ) );
		window.addEventListener( 'hashchange', on );
		return () => window.removeEventListener( 'hashchange', on );
	}, [ sections ] );

	const base = ( store, k ) => {
		if ( 'to' === store ) {
			return saved.to[ k ] ?? inherited[ k ] ?? '';
		}
		return saved.pd[ k ] ?? '';
	};
	const valueOf = ( store, k ) =>
		k in edits[ store ] ? edits[ store ][ k ] : base( store, k );
	const set = ( store, k, v ) =>
		setEdits( { ...edits, [ store ]: { ...edits[ store ], [ k ]: v } } );
	const changed = ( store ) =>
		Object.keys( edits[ store ] ).filter(
			( k ) => edits[ store ][ k ] !== base( store, k )
		);
	const dirty =
		gs.dirty || [ 'to', 'pd' ].some( ( s ) => changed( s ).length > 0 );

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
		const settings = Object.keys( body ).length
			? apiFetch( {
					path: '/wp/v2/settings',
					method: 'POST',
					data: body,
				} )
			: null;
		const jobs = [
			settings &&
				settings.then( ( res ) => {
					const obj = ( v, fallback ) =>
						v && 'object' === typeof v ? v : fallback;
					setSaved( {
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
					setEdits( { to: {}, pd: {} } );
				} ),
			gs.dirty ? gs.save() : null,
		].filter( Boolean );
		Promise.allSettled( jobs )
			.then( ( results ) => {
				const failed = results.find( ( r ) => 'rejected' === r.status );
				if ( ! failed ) {
					setStatus( {
						type: 'success',
						text: __( 'Saved.', 'kits-core' ),
					} );
					return;
				}
				const msg =
					failed.reason?.message ||
					__( 'Not saved. Try again.', 'kits-core' );
				setStatus( {
					type: 'error',
					// The other request went through: say so, and what is left stays marked not saved.
					text:
						results.length > 1 &&
						results.some( ( r ) => 'fulfilled' === r.status )
							? sprintf(
									/* translators: %s: error message. */
									__(
										'Partly saved. The rest was not saved: %s',
										'kits-core'
									),
									msg
								)
							: msg,
				} );
			} )
			.finally( () => setBusy( false ) );
	};

	const current =
		sections.find( ( s ) => s.slug === section ) || sections[ 0 ];

	const item = ( [ kind, k ] ) => {
		const store = kind;
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
		return (
			<PageDefaultField
				key={ k }
				name={ k }
				value={ value }
				onChange={ onChange }
			/>
		);
	};

	return (
		<AppShell
			header={
				<AppHeader
					icon={ data.icon }
					name={ __( 'Kits', 'kits-core' ) }
					version={ data.version }
					tabs={ data.tabs || [] }
					aside={
						<>
							<span
								className="kits-app-status"
								role="status"
								aria-live="polite"
							>
								{ dirty && ! busy
									? __( 'Changes not saved', 'kits-core' )
									: '' }
							</span>
							<Button
								variant="primary"
								onClick={ save }
								disabled={ ! dirty || busy }
								busy={ busy }
							>
								{ __( 'Save', 'kits-core' ) }
							</Button>
						</>
					}
				/>
			}
		>
			<p className="kits-shell-frame__intro">
				{ sprintf(
					/* translators: 1: site name, 2: theme name and version, 3: Kits Core version. */
					__( '%1$s · %2$s · Kits Core %3$s', 'kits-core' ),
					data.site,
					data.theme,
					data.coreVersion
				) }
			</p>
			<ChipRow
				label={ __( 'Sections', 'kits-core' ) }
				chips={ sections.map( ( s ) => ( {
					value: s.slug,
					label: s.label,
					href: '#' + s.slug,
				} ) ) }
				value={ current?.slug }
			/>
			{ status && (
				<Notice
					tone={ status.type }
					onDismiss={ () => setStatus( null ) }
				>
					{ status.text }
				</Notice>
			) }
			{ current && (
				<SectionCard
					id={ 'section-' + current.slug }
					title={ current.label }
				>
					<div className="kits-shell-prose">
						{ current.Body ? (
							<current.Body gs={ gs } item={ item } />
						) : (
							current.items.map( item )
						) }
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
						{ current.legacy && (
							<Legacy
								key={ current.legacy }
								slug={ current.legacy }
							/>
						) }
					</div>
				</SectionCard>
			) }
		</AppShell>
	);
}

const root = document.getElementById( 'kits-admin-app' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
