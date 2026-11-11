/**
 * Theme Options → Colours (docs/admin-ui-spec.md D1.5).
 *
 * - Palette: each theme colour, name fixed, colour editable. Saved as the whole list in Global Styles
 *   settings.color.palette.theme; the key is removed when every colour is the theme's again.
 * - Colour sets: background, text, headings and link of each set, picked from the palette. Saved per block type in
 *   styles.blocks.<block>.variations.<set>; a value equal to the theme's is removed.
 * - Site colour set: kits_page_defaults._site.colours (the app's page-default field).
 * - Contrast: the design registry's pairs and each set's text, headings and link on its background, worked out from
 *   the colours on screen before they are saved (the same sums as Design system → Contrast).
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	ColorIndicator,
	ColorPicker,
	Dropdown,
	Flex,
	FlexItem,
	Notice,
	SelectControl,
} from '@wordpress/components';
import { ThemeNote } from '../theme-note';

const data = window.kitsAdminApp?.colours || {};
const palette = data.palette || [];
const sets = data.sets || [];
const PALETTE = [ 'settings', 'color', 'palette', 'theme' ];
const lower = ( v ) => String( v || '' ).toLowerCase();

// The set fields and where each sits inside a variation.
const SET_FIELDS = [
	[
		'background',
		__( 'Background', 'kits-core' ),
		[ 'color', 'background' ],
	],
	[ 'text', __( 'Text', 'kits-core' ), [ 'color', 'text' ] ],
	[
		'heading',
		__( 'Headings', 'kits-core' ),
		[ 'elements', 'heading', 'color', 'text' ],
	],
	[
		'link',
		__( 'Link', 'kits-core' ),
		[ 'elements', 'link', 'color', 'text' ],
	],
];

const setPath = ( block, slug, sub ) => [
	'styles',
	'blocks',
	block,
	'variations',
	slug,
	...sub,
];

// The palette slug a value points at (var(--wp--preset--color--x) or var:preset|color|x), or ''.
export function slugOf( v ) {
	const m =
		/^var\(\s*--wp--preset--color--([a-z0-9-]+)\s*\)$/i.exec( v || '' ) ||
		/^var:preset\|color\|([a-z0-9-]+)$/i.exec( v || '' );
	return m ? m[ 1 ] : '';
}
const presetVar = ( slug ) => `var(--wp--preset--color--${ slug })`;

/*
 * Contrast, as kits_css_var_find(), kits_token_resolve() and kits_contrast() in includes/system-screen.php.
 */

// The first var(--name[, fallback]) in a CSS value, brackets counted.
function varFind( v ) {
	const m = /var\(\s*--([a-z0-9-]+)\s*/i.exec( v );
	if ( ! m ) {
		return null;
	}
	const start = m.index;
	let depth = 0;
	let comma = -1;
	let j;
	for ( j = start + 3; j < v.length; j++ ) {
		const c = v[ j ];
		if ( '(' === c ) {
			depth++;
		} else if ( ')' === c ) {
			depth--;
			if ( 0 === depth ) {
				break;
			}
		} else if ( ',' === c && 1 === depth && comma < 0 ) {
			comma = j;
		}
	}
	if ( j >= v.length ) {
		return null;
	}
	return {
		whole: v.slice( start, j + 1 ),
		name: m[ 1 ],
		fallback: comma < 0 ? null : v.slice( comma + 1, j ).trim(),
	};
}

// A CSS value with var() references followed through vals (name without dashes => value).
export function resolve( value, vals ) {
	let v = String( value || '' ).replace(
		/var:preset\|color\|([a-z0-9-]+)/gi,
		'var(--wp--preset--color--$1)'
	);
	for ( let i = 0; i < 10; i++ ) {
		const ref = varFind( v );
		if ( ! ref ) {
			break;
		}
		v = v.replace( ref.whole, vals[ ref.name ] ?? ref.fallback ?? '' );
	}
	return v.trim().toLowerCase();
}

function luminance( hex ) {
	if ( ! /^#([0-9a-f]{3}|[0-9a-f]{6})$/.test( hex ) ) {
		return null;
	}
	const h =
		4 === hex.length
			? '#' +
				hex[ 1 ] +
				hex[ 1 ] +
				hex[ 2 ] +
				hex[ 2 ] +
				hex[ 3 ] +
				hex[ 3 ]
			: hex;
	const c = [ 1, 3, 5 ]
		.map( ( i ) => parseInt( h.slice( i, i + 2 ), 16 ) / 255 )
		.map( ( x ) =>
			x <= 0.03928 ? x / 12.92 : Math.pow( ( x + 0.055 ) / 1.055, 2.4 )
		);
	return 0.2126 * c[ 0 ] + 0.7152 * c[ 1 ] + 0.0722 * c[ 2 ];
}

// Contrast ratio of two #rrggbb colours, or 0 when either is not a plain hex colour.
export function contrast( a, b ) {
	const la = luminance( a );
	const lb = luminance( b );
	if ( null === la || null === lb ) {
		return 0;
	}
	return ( Math.max( la, lb ) + 0.05 ) / ( Math.min( la, lb ) + 0.05 );
}

function PaletteRow( { colour, value, onChange } ) {
	return (
		<div className="kits-app-field">
			<Dropdown
				popoverProps={ { placement: 'bottom-start' } }
				renderToggle={ ( { isOpen, onToggle } ) => (
					<Button
						variant="tertiary"
						onClick={ onToggle }
						aria-expanded={ isOpen }
						aria-label={ sprintf(
							/* translators: 1: colour name, 2: colour value. */
							__( 'Change %1$s, now %2$s', 'kits-core' ),
							colour.name,
							value
						) }
					>
						<Flex align="center" gap={ 2 }>
							<FlexItem>
								<ColorIndicator colorValue={ value } />
							</FlexItem>
							<FlexItem>
								<strong>{ colour.name }</strong> { value }
							</FlexItem>
						</Flex>
					</Button>
				) }
				renderContent={ () => (
					<ColorPicker
						color={ value }
						enableAlpha={ false }
						onChange={ onChange }
					/>
				) }
			/>
			<ThemeNote
				isTheme={ lower( value ) === lower( colour.color ) }
				themeLabel={ colour.color }
				label={ colour.name }
				onReset={ () => onChange( colour.color ) }
			/>
		</div>
	);
}

function SetField( { label, setLabel, value, theme, hexOf, onChange } ) {
	const slug = slugOf( value );
	const themeSlug = slugOf( theme );
	const nameOf = ( s ) => palette.find( ( c ) => c.slug === s )?.name || s;
	const options = palette.map( ( c ) => ( {
		value: c.slug,
		label: `${ c.name } (${ hexOf( c.slug ) })`,
	} ) );
	// A value that is not a palette colour (set in the Site Editor) stays listed.
	if ( ! slug && value ) {
		options.unshift( { value: '', label: value } );
	}
	const full = sprintf(
		/* translators: 1: colour set, 2: field (Background, Text…). */
		__( '%1$s: %2$s', 'kits-core' ),
		setLabel,
		label
	);
	return (
		<div className="kits-app-field">
			<Flex align="flex-end" gap={ 2 }>
				<FlexItem>
					<ColorIndicator
						colorValue={ slug ? hexOf( slug ) : value }
					/>
				</FlexItem>
				<FlexItem isBlock>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ label }
						value={ slug }
						options={ options }
						onChange={ onChange }
					/>
				</FlexItem>
			</Flex>
			<ThemeNote
				isTheme={ value === theme || ( !! slug && slug === themeSlug ) }
				themeLabel={ themeSlug ? nameOf( themeSlug ) : theme }
				label={ full }
				onReset={ () => onChange( themeSlug || null ) }
			/>
		</div>
	);
}

function ContrastTable( { rows } ) {
	return (
		<table className="widefat striped">
			<thead>
				<tr>
					<th scope="col">{ __( 'Pair', 'kits-core' ) }</th>
					<th scope="col">{ __( 'Text', 'kits-core' ) }</th>
					<th scope="col">{ __( 'On', 'kits-core' ) }</th>
					<th scope="col">{ __( 'Ratio', 'kits-core' ) }</th>
					<th scope="col">
						{ __( 'AA (4.5:1 text)', 'kits-core' ) }
					</th>
				</tr>
			</thead>
			<tbody>
				{ rows.map( ( r ) => {
					const ratio = contrast( r.fg, r.bg );
					let aa = __( 'not a plain colour', 'kits-core' );
					if ( ratio ) {
						aa =
							ratio >= 4.5 ? (
								__( 'Pass', 'kits-core' )
							) : (
								<strong>{ __( 'Fail', 'kits-core' ) }</strong>
							);
					}
					return (
						<tr key={ r.key }>
							<th scope="row">{ r.name }</th>
							<td>
								<ColorIndicator colorValue={ r.fg } />{ ' ' }
								{ r.fgName } { r.fg }
							</td>
							<td>
								<ColorIndicator colorValue={ r.bg } />{ ' ' }
								{ r.bgName } { r.bg }
							</td>
							<td>{ ratio ? ratio.toFixed( 2 ) + ':1' : '—' }</td>
							<td>{ aa }</td>
						</tr>
					);
				} ) }
			</tbody>
		</table>
	);
}

function ColoursBody( { gs, item } ) {
	if ( ! gs.ready ) {
		return (
			<Notice status="warning" isDismissible={ false }>
				{ __(
					'Colours are kept in Styles, which this account cannot change.',
					'kits-core'
				) }
			</Notice>
		);
	}

	// Palette: the saved list (Global Styles) over the theme's.
	const saved = gs.get( PALETTE );
	const list = Array.isArray( saved ) ? saved : [];
	const hexOf = ( slug ) =>
		list.find( ( c ) => c?.slug === slug )?.color ??
		palette.find( ( c ) => c.slug === slug )?.color ??
		'';
	const setColour = ( slug, hex ) => {
		const next = palette.map( ( c ) => ( {
			slug: c.slug,
			name: c.name,
			color: c.slug === slug ? hex : hexOf( c.slug ),
		} ) );
		// Colours the theme no longer has stay as they were saved.
		list.forEach( ( c ) => {
			if ( c?.slug && ! palette.some( ( p ) => p.slug === c.slug ) ) {
				next.push( c );
			}
		} );
		const allTheme =
			next.length === palette.length &&
			next.every(
				( c, i ) => lower( c.color ) === lower( palette[ i ].color )
			);
		gs.set( PALETTE, allTheme ? undefined : next );
	};

	// A colour set's value: the first block type's saved one, else the theme's.
	const setValue = ( s, sub, key ) =>
		gs.get( setPath( s.blockTypes[ 0 ], s.slug, sub ) ) ?? s.theme[ key ];
	const setSetValue = ( s, sub, key, slug ) => {
		// The theme's value, or a reset, removes the key so the theme file applies.
		const v =
			! slug || slug === slugOf( s.theme[ key ] )
				? undefined
				: presetVar( slug );
		s.blockTypes.forEach( ( b ) => gs.set( setPath( b, s.slug, sub ), v ) );
	};

	// Contrast, from the colours on screen.
	const vals = { ...( data.tokens || {} ) };
	palette.forEach( ( c ) => {
		vals[ 'wp--preset--color--' + c.slug ] = hexOf( c.slug );
	} );
	const nameOfValue = ( v ) =>
		palette.find( ( c ) => c.slug === slugOf( v ) )?.name || '';
	const rows = [];
	sets.forEach( ( s ) => {
		const bgv = setValue( s, [ 'color', 'background' ], 'background' );
		SET_FIELDS.slice( 1 ).forEach( ( [ key, label, sub ] ) => {
			const fgv = setValue( s, sub, key );
			rows.push( {
				key: s.slug + key,
				name: sprintf(
					/* translators: 1: colour set, 2: Text, Headings or Link. */
					__( '%1$s: %2$s', 'kits-core' ),
					s.label,
					label
				),
				fg: resolve( fgv, vals ),
				fgName: nameOfValue( fgv ),
				bg: resolve( bgv, vals ),
				bgName: nameOfValue( bgv ),
			} );
		} );
	} );
	( data.pairs || [] ).forEach( ( p, i ) =>
		rows.push( {
			key: 'pair' + i,
			name: p.name,
			fg: resolve( `var(--${ p.text })`, vals ),
			fgName: p.text,
			bg: resolve( `var(--${ p.on })`, vals ),
			bgName: p.on,
		} )
	);

	return (
		<>
			<h3>{ __( 'Palette', 'kits-core' ) }</h3>
			<p className="description">
				{ __(
					'The theme’s colours. Names are fixed; every part of the site that uses a colour follows a change.',
					'kits-core'
				) }
			</p>
			{ palette.map( ( c ) => (
				<PaletteRow
					key={ c.slug }
					colour={ c }
					value={ hexOf( c.slug ) }
					onChange={ ( hex ) => setColour( c.slug, hex ) }
				/>
			) ) }

			<h3>{ __( 'Colour sets', 'kits-core' ) }</h3>
			<p className="description">
				{ __(
					'A page picks a set in Page Options; a section picks one as its Group style.',
					'kits-core'
				) }
			</p>
			{ ! sets.length && (
				<p className="description">
					{ __( 'None yet.', 'kits-core' ) }
				</p>
			) }
			{ sets.map( ( s ) => (
				<fieldset key={ s.slug } className="kits-app-field">
					<legend>
						<strong>{ s.label }</strong>
					</legend>
					<Flex align="flex-start" gap={ 4 } wrap>
						{ SET_FIELDS.map( ( [ key, label, sub ] ) => (
							<FlexItem key={ key }>
								<SetField
									label={ label }
									setLabel={ s.label }
									value={ setValue( s, sub, key ) }
									theme={ s.theme[ key ] }
									hexOf={ hexOf }
									onChange={ ( slug ) =>
										setSetValue( s, sub, key, slug )
									}
								/>
							</FlexItem>
						) ) }
					</Flex>
				</fieldset>
			) ) }

			<h3>{ __( 'Site colour set', 'kits-core' ) }</h3>
			{ item( [ 'pd', 'colours' ] ) }

			<h3>{ __( 'Contrast', 'kits-core' ) }</h3>
			<p className="description">
				{ __(
					'Worked out from the colours above, before they are saved.',
					'kits-core'
				) }
			</p>
			<ContrastTable rows={ rows } />
			{ data.contrastUrl && (
				<p>
					<a href={ data.contrastUrl }>
						{ __( 'Design system → Colour contrast', 'kits-core' ) }
					</a>
				</p>
			) }
		</>
	);
}

export default {
	slug: 'colours',
	label: __( 'Colours', 'kits-core' ),
	Body: ColoursBody,
};
