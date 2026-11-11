/**
 * Theme Options → Buttons (docs/admin-ui-spec.md D1.7).
 *
 * One group per button style: the default style (Primary, core "fill") and each Button block style the theme registers.
 * Values are Global Styles (user origin), the same ones Styles → Blocks → Button edits:
 * - Primary:    styles.elements.button (and its :hover);
 * - the others: styles.blocks['core/button'].variations[ slug ] (hover in its elements.button[':hover']).
 * Colours are palette colours (var(--wp--preset--color--<slug>)); corner radius and padding are the theme's radius and
 * spacing sizes. Corner radius and padding are one setting for every button (blocks.php: --kit-btn-radius, --kit-btn-pad),
 * so they sit under Primary. Data: includes/admin-app-buttons.php (window.kitsAdminApp.buttons).
 *
 * The app's one Save calls save() below, which writes through saveGlobalStyles().
 */
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	ColorIndicator,
	Flex,
	FlexBlock,
	FlexItem,
	SelectControl,
} from '@wordpress/components';

const data = window.kitsAdminApp?.buttons || null;
const vars = data?.vars || {};
const palette = data?.palette || [];

const isPlain = ( v ) => !! v && 'object' === typeof v && ! Array.isArray( v );

/**
 * A copy of target with patch merged in: a key set to undefined in patch is removed, and an object left empty by that
 * is removed too. Values that are not plain objects (Global Styles keeps an empty list as []) count as {}.
 *
 * @param {Object} target Current data.
 * @param {Object} patch  Changes.
 * @return {Object} Merged copy.
 */
export function mergeGlobalStyles( target, patch ) {
	const out = isPlain( target ) ? { ...target } : {};
	Object.keys( patch ).forEach( ( k ) => {
		const v = patch[ k ];
		if ( undefined === v ) {
			delete out[ k ];
		} else if ( isPlain( v ) ) {
			const merged = mergeGlobalStyles( out[ k ], v );
			if ( Object.keys( merged ).length ) {
				out[ k ] = merged;
			} else {
				delete out[ k ];
			}
		} else {
			out[ k ] = v;
		}
	} );
	return out;
}

/**
 * Saves changes to the site's Global Styles (user origin) and keeps everything else in the post: reads the post fresh,
 * merges only the changed keys, then writes settings and styles back.
 *
 * @param {number} postId Global Styles post id.
 * @param {Object} patch  { styles, settings }: nested changes; undefined removes a key (back to the theme's value).
 * @return {Promise<Object>} The saved post.
 */
export function saveGlobalStyles( postId, patch ) {
	const path = '/wp/v2/global-styles/' + postId;
	return apiFetch( { path: path + '?context=edit' } ).then( ( post ) =>
		apiFetch( {
			path,
			method: 'POST',
			data: {
				settings: mergeGlobalStyles(
					post?.settings,
					patch.settings || {}
				),
				styles: mergeGlobalStyles( post?.styles, patch.styles || {} ),
			},
		} )
	);
}

// Where a field sits inside a style's node (as in admin-app-buttons.php). Padding is kept on both sides.
const PATHS = {
	background: [ 'color', 'background' ],
	text: [ 'color', 'text' ],
	border: [ 'border', 'color' ],
	radius: [ 'border', 'radius' ],
	padding: [ 'spacing', 'padding', 'left' ],
	hover_background: [ ':hover', 'color', 'background' ],
	hover_text: [ ':hover', 'color', 'text' ],
};

const LABELS = {
	background: __( 'Background', 'kits-core' ),
	text: __( 'Text', 'kits-core' ),
	border: __( 'Border', 'kits-core' ),
	radius: __( 'Corner radius (all buttons)', 'kits-core' ),
	padding: __( 'Side padding (all buttons)', 'kits-core' ),
	hover_background: __( 'Hover background', 'kits-core' ),
	hover_text: __( 'Hover text', 'kits-core' ),
};

// The full Global Styles path of a style's field.
function stylesPath( key, field ) {
	const p = PATHS[ field ];
	if ( 'fill' === key ) {
		return [ 'elements', 'button', ...p ];
	}
	const base = [ 'blocks', 'core/button', 'variations', key ];
	return ':hover' === p[ 0 ]
		? [ ...base, 'elements', 'button', ...p ]
		: [ ...base, ...p ];
}

// Writes value at path in obj (in place); undefined is kept, so mergeGlobalStyles() removes that key.
function setIn( obj, path, value ) {
	let o = obj;
	path.slice( 0, -1 ).forEach( ( k ) => {
		o[ k ] = isPlain( o[ k ] ) ? o[ k ] : {};
		o = o[ k ];
	} );
	o[ path[ path.length - 1 ] ] = value;
}

function readPath( obj, path ) {
	return path.reduce(
		( o, k ) => ( isPlain( o ) ? o[ k ] : undefined ),
		obj
	);
}

// The site's own values after a save, read back from the saved post.
let saved = Object.fromEntries(
	( data?.styles || [] ).map( ( s ) => [ s.key, { ...( s.user || {} ) } ] )
);

function readSaved( styles ) {
	return Object.fromEntries(
		( data?.styles || [] ).map( ( s ) => [
			s.key,
			Object.fromEntries(
				s.fields
					.map( ( f ) => [
						f,
						readPath( styles, stylesPath( s.key, f ) ),
					] )
					.filter( ( [ , v ] ) => 'string' === typeof v && '' !== v )
			),
		] )
	);
}

/**
 * A CSS value with every var() it names replaced from the theme's tokens and presets, so the preview can draw it
 * without the site's stylesheets. Preset references (var:preset|color|x) become var(--wp--preset--color--x) first.
 *
 * @param {string} value CSS value.
 * @return {string} Resolved value ('' when unknown).
 */
function resolve( value ) {
	let v = String( value || '' ).replace(
		/var:preset\|([a-z0-9-]+)\|([a-z0-9-]+)/gi,
		'var(--wp--preset--$1--$2)'
	);
	for ( let round = 0; round < 12 && v.includes( 'var(' ); round++ ) {
		const start = v.indexOf( 'var(' );
		let depth = 0;
		let end = -1;
		let comma = -1;
		for ( let i = start + 3; i < v.length; i++ ) {
			if ( '(' === v[ i ] ) {
				depth++;
			} else if ( ')' === v[ i ] ) {
				depth--;
				if ( 0 === depth ) {
					end = i;
					break;
				}
			} else if ( ',' === v[ i ] && 1 === depth && -1 === comma ) {
				comma = i;
			}
		}
		if ( -1 === end ) {
			return '';
		}
		const name = v.slice( start + 4, -1 === comma ? end : comma ).trim();
		const fallback = -1 === comma ? '' : v.slice( comma + 1, end ).trim();
		v =
			v.slice( 0, start ) +
			( vars[ name ] ?? fallback ) +
			v.slice( end + 1 );
	}
	return v.includes( 'var(' ) ? '' : v.trim();
}

// A readable name for a value: the palette colour or size it names, else the CSS it resolves to (a theme colour that is
// not a palette colour, such as a tint, is "the theme's own shade").
function describe( field, value, isThemeValue = false ) {
	if ( ! value ) {
		return __( 'none', 'kits-core' );
	}
	const m = String( value ).match(
		/^var\(--wp--preset--(color|spacing|border-radius)--([a-z0-9-]+)\)$|^var:preset\|(color|spacing|border-radius)\|([a-z0-9-]+)$/i
	);
	if ( m ) {
		const kind = m[ 1 ] || m[ 3 ];
		const slug = m[ 2 ] || m[ 4 ];
		const list = {
			color: palette,
			spacing: data.spacing,
			'border-radius': data.radius,
		}[ kind ];
		const p = ( list || [] ).find( ( x ) => x[ 0 ] === slug );
		if ( p ) {
			return 'color' === kind ? p[ 1 ] : `${ p[ 1 ] } (${ p[ 2 ] })`;
		}
	}
	const css = resolve( value );
	if ( 'radius' === field || 'padding' === field ) {
		return css || String( value );
	}
	const p = palette.find(
		( x ) => x[ 2 ].toLowerCase() === css.toLowerCase()
	);
	if ( p ) {
		return p[ 1 ];
	}
	return isThemeValue
		? __( 'the theme’s own shade', 'kits-core' )
		: css || String( value );
}

// The value a field has now: an edit (null = back to the theme), else the site's saved value.
function current( edits, key, field ) {
	const e = edits?.[ key ];
	if ( e && field in e ) {
		return e[ field ] ?? undefined;
	}
	return saved[ key ]?.[ field ];
}

function ThemeNote( { isTheme, themeLabel, label, onReset } ) {
	return (
		<p className="description">
			{ isTheme
				? __( 'Theme value', 'kits-core' )
				: sprintf(
						/* translators: %s: the theme's value. */
						__( 'Theme value: %s', 'kits-core' ),
						themeLabel
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

function Field( { style, field, value, onChange } ) {
	const theme = style.theme?.[ field ];
	const token = style.tokens?.[ field ];
	const themeValue = theme || ( token ? `var(${ token })` : '' );
	const isColor = ! [ 'radius', 'padding' ].includes( field );
	let list = palette;
	if ( 'radius' === field ) {
		list = data.radius || [];
	} else if ( 'padding' === field ) {
		list = data.spacing || [];
	}
	const prefix =
		{
			radius: '--wp--preset--border-radius--',
			padding: '--wp--preset--spacing--',
		}[ field ] || '--wp--preset--color--';
	const options = [
		{
			value: '',
			label: sprintf(
				/* translators: %s: the theme's value. */
				__( 'Theme (%s)', 'kits-core' ),
				describe( field, themeValue, true )
			),
		},
		...list.map( ( [ slug, name, size ] ) => ( {
			value: `var(${ prefix }${ slug })`,
			label: isColor ? name : `${ name } (${ size })`,
		} ) ),
	];
	// A value set elsewhere (Styles) that is not one of these: keep it choosable so the screen does not hide it.
	if ( value && ! options.some( ( o ) => o.value === value ) ) {
		options.push( {
			value,
			label: sprintf(
				/* translators: %s: a CSS value. */
				__( 'Set in Styles: %s', 'kits-core' ),
				describe( field, value )
			),
		} );
	}
	const label = LABELS[ field ];
	return (
		<div className="kits-app-field">
			<Flex align="flex-end" gap={ 2 }>
				<FlexBlock>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ label }
						value={ value || '' }
						options={ options }
						onChange={ ( v ) => onChange( '' === v ? null : v ) }
					/>
				</FlexBlock>
				{ isColor && (
					<FlexItem>
						<ColorIndicator
							colorValue={ resolve( value || themeValue ) }
						/>
					</FlexItem>
				) }
			</Flex>
			<ThemeNote
				isTheme={ ! value }
				themeLabel={ describe( field, themeValue, true ) }
				label={ label }
				onReset={ () => onChange( null ) }
			/>
		</div>
	);
}

/**
 * The CSS a style's button gets, from the values being edited (the site's tokens otherwise). Mirrors
 * kit-primitives.css section 10: the border is the background (Primary, Brand) or the Border colour, and on hover the
 * border follows the hover background.
 *
 * @param {Object} edits This section's edits.
 * @param {Object} style A style from data.styles.
 * @return {Object} { normal, hover } inline styles.
 */
function previewOf( edits, style ) {
	const fill = data.styles.find( ( s ) => 'fill' === s.key ) || style;
	const val = ( s, field ) => {
		const v = current( edits, s.key, field );
		if ( v ) {
			return v;
		}
		if ( s.theme?.[ field ] ) {
			return s.theme[ field ];
		}
		return s.tokens?.[ field ] ? `var(${ s.tokens[ field ] })` : '';
	};
	const bg =
		'kits-ghost' === style.key
			? 'transparent'
			: resolve( val( style, 'background' ) );
	const fg = resolve( val( style, 'text' ) );
	const line = style.fields.includes( 'border' )
		? resolve( val( style, 'border' ) )
		: bg;
	let hbg = style.fields.includes( 'hover_background' )
		? resolve( val( style, 'hover_background' ) )
		: '';
	let hfg = style.fields.includes( 'hover_text' )
		? resolve( val( style, 'hover_text' ) )
		: '';
	const mix = resolve( 'var(--kit-btn-hover-mix)' );
	const ink = resolve( 'var(--kit-color-ink)' );
	// No hover colour of its own: Primary with a chosen background, and Brand, darken their background (blocks.php).
	const ownBg =
		'fill' === style.key && current( edits, 'fill', 'background' );
	if (
		bg &&
		mix &&
		ink &&
		( 'kits-brand' === style.key ||
			( ownBg && ! current( edits, 'fill', 'hover_background' ) ) )
	) {
		hbg = `color-mix(in srgb, ${ bg } ${ mix }, ${ ink })`;
	}
	if (
		'kits-brand' === style.key ||
		( 'fill' === style.key &&
			current( edits, 'fill', 'text' ) &&
			! current( edits, 'fill', 'hover_text' ) )
	) {
		hfg = fg;
	}
	// Sizes come from the theme's tokens only; one that cannot be read is left to the browser.
	const pad = resolve( val( fill, 'padding' ) );
	const base = {
		display: 'inline-flex',
		alignItems: 'center',
		justifyContent: 'center',
		boxSizing: 'border-box',
		minHeight: resolve( 'var(--kit-btn-height)' ) || undefined,
		padding: pad ? `0 ${ pad }` : undefined,
		borderRadius: resolve( val( fill, 'radius' ) ) || undefined,
		borderWidth: resolve( 'var(--kit-border-control)' ) || undefined,
		borderStyle: 'solid',
		fontWeight: resolve( 'var(--kit-weight-medium)' ) || undefined,
		lineHeight: 1.2,
	};
	return {
		normal: { ...base, background: bg, color: fg, borderColor: line },
		hover: {
			...base,
			background: hbg || bg,
			color: hfg || fg,
			borderColor: hbg || line,
		},
	};
}

function Preview( { edits, style } ) {
	const { normal, hover } = previewOf( edits, style );
	return (
		<Flex
			gap={ 4 }
			justify="flex-start"
			wrap
			role="group"
			aria-label={ sprintf(
				/* translators: %s: button style name. */
				__( 'Preview of %s', 'kits-core' ),
				style.label
			) }
		>
			<FlexItem>
				<p className="description">{ __( 'Normal', 'kits-core' ) }</p>
				<span style={ normal }>{ __( 'Button', 'kits-core' ) }</span>
			</FlexItem>
			<FlexItem>
				<p className="description">{ __( 'Hover', 'kits-core' ) }</p>
				<span style={ hover }>{ __( 'Button', 'kits-core' ) }</span>
			</FlexItem>
		</Flex>
	);
}

function Panel( { edits, setEdits } ) {
	const styles = data?.styles || [];
	const set = ( key, field, v ) =>
		setEdits( {
			...edits,
			[ key ]: { ...( edits?.[ key ] || {} ), [ field ]: v },
		} );
	return (
		<>
			<p>
				{ __(
					'Every button on the site (in the content, the header, forms and cards) follows these styles. They are the same settings as Styles → Blocks → Button.',
					'kits-core'
				) }{ ' ' }
				{ data?.link && (
					<a href={ data.link }>
						{ __( 'Open Styles', 'kits-core' ) }
					</a>
				) }
			</p>
			{ styles.map( ( style ) => (
				<section
					key={ style.key }
					aria-labelledby={ `kits-btn-${ style.key }` }
				>
					<h3 id={ `kits-btn-${ style.key }` }>{ style.label }</h3>
					{ style.note && (
						<p className="description">{ style.note }</p>
					) }
					<Preview edits={ edits } style={ style } />
					{ style.fields.map( ( field ) => (
						<Field
							key={ field }
							style={ style }
							field={ field }
							value={ current( edits, style.key, field ) }
							onChange={ ( v ) => set( style.key, field, v ) }
						/>
					) ) }
				</section>
			) ) }
		</>
	);
}

// The changed fields: [ [ styleKey, field, value ] ], value undefined = back to the theme.
function changes( edits ) {
	const out = [];
	Object.keys( edits || {} ).forEach( ( key ) =>
		Object.keys( edits[ key ] ).forEach( ( field ) => {
			const v = edits[ key ][ field ] ?? undefined;
			if ( v !== saved[ key ]?.[ field ] ) {
				out.push( [ key, field, v ] );
			}
		} )
	);
	return out;
}

export default {
	slug: 'buttons',
	label: __( 'Buttons', 'kits-core' ),
	enabled: !! data,
	initial: {},
	Panel,
	isDirty: ( edits ) => changes( edits ).length > 0,
	save( edits ) {
		const styles = {};
		changes( edits ).forEach( ( [ key, field, v ] ) => {
			const path = stylesPath( key, field );
			setIn( styles, path, v );
			if ( 'padding' === field ) {
				setIn( styles, [ ...path.slice( 0, -1 ), 'right' ], v ); // Both sides.
			}
		} );
		return saveGlobalStyles( data.postId, {
			styles,
		} ).then( ( post ) => {
			saved = readSaved( isPlain( post?.styles ) ? post.styles : {} );
			return post;
		} );
	},
};
