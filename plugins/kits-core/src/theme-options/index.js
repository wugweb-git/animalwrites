/**
 * Kits → Theme Options, new screen (trial: admin.php?page=kits-options&ui=next).
 *
 * One app header (site, versions, Save), the Kits tabs, a section list and the fields of the chosen section. Values
 * save through /wp/v2/settings (option kits_theme_options); a value equal to the theme's is not stored, so
 * "Reset to theme" sends the theme's value.
 */
import { createRoot, useState, useEffect, useMemo } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
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

/**
 * Groups of fields, in field order.
 *
 * @param {Object} list Fields by key.
 * @return {Array} [ { slug, label, keys } ].
 */
function groupsOf( list ) {
	const out = [];
	Object.keys( list ).forEach( ( key ) => {
		const f = list[ key ];
		const slug = f.group.toLowerCase().replace( /[^a-z0-9]+/g, '-' );
		let g = out.find( ( x ) => x.slug === slug );
		if ( ! g ) {
			g = { slug, label: f.group, keys: [] };
			out.push( g );
		}
		g.keys.push( key );
	} );
	return out;
}

function sectionFromHash( groups ) {
	const h = window.location.hash.replace( '#', '' );
	return groups.find( ( g ) => g.slug === h ) ? h : groups[ 0 ]?.slug;
}

function Field( { name, field, value, inherited, onChange } ) {
	const isTheme = value === inherited;
	const reset = isTheme ? null : (
		<Button
			variant="link"
			onClick={ () => onChange( inherited ) }
			aria-label={ sprintf(
				/* translators: %s: field label. */
				__( 'Reset %s to theme', 'kits-core' ),
				field.label
			) }
		>
			{ __( 'Reset to theme', 'kits-core' ) }
		</Button>
	);
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
		<div className="kits-app-field" data-field={ name }>
			{ control }
			<p className="description">
				{ isTheme
					? __( 'Theme value', 'kits-core' )
					: sprintf(
							/* translators: %s: the theme's value. */
							__( 'Theme value: %s', 'kits-core' ),
							field.choices?.[ inherited ] ??
								( inherited || __( 'empty', 'kits-core' ) )
						) }{ ' ' }
				{ reset }
			</p>
		</div>
	);
}

function App() {
	const editable = useMemo( () => {
		const out = {};
		Object.keys( fields ).forEach( ( k ) => {
			if ( 'share' !== fields[ k ].kind ) {
				out[ k ] = fields[ k ];
			}
		} );
		return out;
	}, [] );
	const groups = useMemo( () => groupsOf( editable ), [ editable ] );
	const [ section, setSection ] = useState( () => sectionFromHash( groups ) );
	const [ saved, setSaved ] = useState( data.saved || {} );
	const [ edits, setEdits ] = useState( {} );
	const [ status, setStatus ] = useState( null );
	const [ busy, setBusy ] = useState( false );

	useEffect( () => {
		const on = () => setSection( sectionFromHash( groups ) );
		window.addEventListener( 'hashchange', on );
		return () => window.removeEventListener( 'hashchange', on );
	}, [ groups ] );

	const inherited = data.inherited || {};
	const valueOf = ( k ) =>
		k in edits ? edits[ k ] : ( saved[ k ] ?? inherited[ k ] ?? '' );
	const dirty = Object.keys( edits ).some(
		( k ) => edits[ k ] !== ( saved[ k ] ?? inherited[ k ] ?? '' )
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
		setBusy( true );
		setStatus( null );
		apiFetch( {
			path: '/wp/v2/settings',
			method: 'POST',
			// Only the edited fields: the clean-up keeps every field that is not sent, so a change made
			// elsewhere since this screen opened is not written back.
			data: { kits_theme_options: edits },
		} )
			.then( ( res ) => {
				// A stored value the schema cannot read comes back as null: keep what this screen knows.
				setSaved(
					res.kits_theme_options &&
						'object' === typeof res.kits_theme_options
						? res.kits_theme_options
						: { ...saved, ...edits }
				);
				setEdits( {} );
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

	const current = groups.find( ( g ) => g.slug === section ) || groups[ 0 ];

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
										data.site,
										data.theme,
										data.core
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
									{ groups.map( ( g ) => (
										<li key={ g.slug }>
											<a
												href={ '#' + g.slug }
												aria-current={
													g.slug === current?.slug
														? 'true'
														: undefined
												}
											>
												{ g.slug === current?.slug ? (
													<strong>{ g.label }</strong>
												) : (
													g.label
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
								{ current.keys.map( ( k ) => (
									<Field
										key={ k }
										name={ k }
										field={ editable[ k ] }
										value={ valueOf( k ) }
										inherited={ inherited[ k ] ?? '' }
										onChange={ ( v ) =>
											setEdits( { ...edits, [ k ]: v } )
										}
									/>
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
