/**
 * Theme Options → Typography (docs/admin-ui-spec.md D1.6): body, heading and button font, the font sizes, and a link
 * to the Font Library. Every value is saved in the user Global Styles post (global-styles.js); a value equal to the
 * theme's is not stored, so Reset to theme removes it. Data: window.kitsAdminApp.typography
 * (includes/admin-app-typography.php).
 */
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Flex,
	FlexBlock,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { ThemeNote } from '../theme-note';

const data = window.kitsAdminApp?.typography || {};
const families = data.families || [];
const fluid = data.fluid || { min: 375, max: 1200 };
const SIZES = [ 'settings', 'typography', 'fontSizes', 'theme' ];
const SAMPLE = 'Sphinx of black quartz, judge my vow. 0123456789';

/**
 * The family slug of a stored font value, 'inherit', '' or the value itself when it names no preset.
 *
 * @param {string} v A fontFamily value from Styles.
 * @return {string} Slug.
 */
function slugOf( v ) {
	const s = String( v ?? '' ).trim();
	const m =
		s.match( /^var\(--wp--preset--font-family--([a-z0-9-]+)\)$/i ) ||
		s.match( /^var:preset\|font-family\|([a-z0-9-]+)$/i );
	return m ? m[ 1 ] : s;
}

const familyOf = ( slug ) => families.find( ( f ) => f.slug === slug );
const inheritLabel = __( 'Same as the text around it', 'kits-core' );
const labelOf = ( slug ) => {
	if ( 'inherit' === slug ) {
		return inheritLabel;
	}
	return familyOf( slug )?.name || slug;
};

// A length in rem or px, for example 0.9375rem or 15px.
const LENGTH = /^(\d*\.?\d+)(rem|px)$/;
const toPx = ( len ) => {
	const m = String( len ).trim().match( LENGTH );
	if ( ! m ) {
		return NaN;
	}
	return 'rem' === m[ 2 ] ? parseFloat( m[ 1 ] ) * 16 : parseFloat( m[ 1 ] );
};
const num = ( n ) => String( parseFloat( n.toFixed( 4 ) ) );

/**
 * The smallest and largest size of a clamp( min, …, max ) size, or null.
 *
 * @param {string} size A font size value.
 * @return {Object|null} { min, max }.
 */
function parseClamp( size ) {
	const m = String( size ?? '' )
		.trim()
		.match(
			/^clamp\(\s*(\d*\.?\d+(?:rem|px))\s*,[^,]+,\s*(\d*\.?\d+(?:rem|px))\s*\)$/
		);
	return m ? { min: m[ 1 ], max: m[ 2 ] } : null;
}

/**
 * A fluid size from min to max, growing evenly between the theme's two screen widths (as the theme's own sizes do).
 *
 * @param {string} min Smallest size (rem or px).
 * @param {string} max Largest size (rem or px).
 * @return {string} The CSS value.
 */
function clampOf( min, max ) {
	const a = toPx( min );
	const b = toPx( max );
	if ( a === b ) {
		return min;
	}
	const slope = ( b - a ) / ( fluid.max - fluid.min );
	const base = ( a - slope * fluid.min ) / 16;
	const vw = slope * 100;
	return `clamp(${ min }, ${ num( base ) }rem ${ vw < 0 ? '-' : '+' } ${ num(
		Math.abs( vw )
	) }vw, ${ max })`;
}

// Two sizes are the same when they are equal or are fluid with the same smallest and largest size.
function sameSize( x, y ) {
	if ( x === y ) {
		return true;
	}
	const p = parseClamp( x );
	const q = parseClamp( y );
	return (
		!! p &&
		!! q &&
		toPx( p.min ) === toPx( q.min ) &&
		toPx( p.max ) === toPx( q.max )
	);
}

function FontRole( { name, role, gs } ) {
	const theme = slugOf( role.theme );
	const stored = gs.get( role.path );
	const current = undefined === stored ? theme : slugOf( stored );
	const options = families.map( ( f ) => ( {
		value: f.slug,
		label:
			'custom' === f.origin
				? sprintf(
						/* translators: %s: font name. */
						__( '%s (Font Library)', 'kits-core' ),
						f.name
					)
				: f.name,
	} ) );
	if ( 'body' !== name || 'inherit' === theme ) {
		options.unshift( { value: 'inherit', label: inheritLabel } );
	}
	if ( current && ! options.some( ( o ) => o.value === current ) ) {
		options.push( {
			value: current,
			label: sprintf(
				/* translators: %s: a font value that is not installed. */
				__( '%s (not installed)', 'kits-core' ),
				current
			),
		} );
	}
	const onChange = ( slug ) => {
		if ( slug === theme ) {
			gs.set( role.path, undefined );
		} else {
			gs.set(
				role.path,
				'inherit' === slug
					? 'inherit'
					: 'var:preset|font-family|' + slug
			);
		}
	};
	// 'inherit' follows the body font (buttons and headings sit in body text).
	const body = data.roles?.body;
	const bodySlug = body ? slugOf( gs.get( body.path ) ?? body.theme ) : '';
	const sampleFamily =
		familyOf( 'inherit' === current ? bodySlug : current )?.fontFamily ||
		'inherit';
	return (
		<div className="kits-app-field">
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ role.label }
				help={ role.note }
				value={ current }
				options={ options }
				onChange={ onChange }
			/>
			<p style={ { fontFamily: sampleFamily } } aria-hidden="true">
				{ SAMPLE }
			</p>
			<ThemeNote
				isTheme={ current === theme }
				themeLabel={ labelOf( theme ) }
				label={ role.label }
				onReset={ () => gs.set( role.path, undefined ) }
			/>
		</div>
	);
}

function SizeRow( { size, value, onChange } ) {
	const themeClamp = parseClamp( size.size );
	const now = parseClamp( value );
	const [ draft, setDraft ] = useState( null );
	const isTheme = sameSize( value, size.size );
	const reset = () => {
		setDraft( null );
		onChange( size.size );
	};

	// A size the theme sets from a token (var(…)) or any other form is shown, not edited here.
	if ( ! themeClamp && ! LENGTH.test( String( size.size ).trim() ) ) {
		return (
			<div className="kits-app-field">
				<p>
					<strong>{ size.name }</strong> <code>{ size.slug }</code>
				</p>
				<p className="description">
					{ sprintf(
						/* translators: %s: CSS value. */
						__( 'Set by the theme: %s', 'kits-core' ),
						size.size
					) }
				</p>
				{ ! isTheme && (
					<ThemeNote
						isTheme={ false }
						themeLabel={ size.size }
						label={ size.name }
						onReset={ reset }
					/>
				) }
			</div>
		);
	}

	if ( ! themeClamp ) {
		const shown = draft?.size ?? value;
		const bad = ! LENGTH.test( String( shown ).trim() );
		return (
			<div className="kits-app-field">
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ sprintf(
						/* translators: 1: size name, 2: size slug. */
						__( '%1$s (%2$s)', 'kits-core' ),
						size.name,
						size.slug
					) }
					help={
						bad
							? sprintf(
									/* translators: %s: the size that is saved. */
									__(
										'Write a size in rem or px, for example 1rem. Until then, Save keeps %s.',
										'kits-core'
									),
									value
								)
							: undefined
					}
					value={ shown }
					onChange={ ( v ) => {
						setDraft( { size: v } );
						if ( LENGTH.test( v.trim() ) ) {
							onChange( v.trim() );
						}
					} }
				/>
				<ThemeNote
					isTheme={ isTheme }
					themeLabel={ size.size }
					label={ size.name }
					onReset={ reset }
				/>
			</div>
		);
	}

	const shown = draft || now || { min: value, max: value };
	const update = ( part, v ) => {
		const next = { ...shown, [ part ]: v };
		setDraft( next );
		const a = toPx( next.min );
		const b = toPx( next.max );
		if ( ! isNaN( a ) && ! isNaN( b ) && a <= b ) {
			// Back at the theme's sizes: the theme's own value, so the row counts as the theme's.
			const atTheme =
				a === toPx( themeClamp.min ) && b === toPx( themeClamp.max );
			onChange(
				atTheme
					? size.size
					: clampOf( next.min.trim(), next.max.trim() )
			);
		}
	};
	const a = toPx( shown.min );
	const b = toPx( shown.max );
	let error = '';
	if ( isNaN( a ) || isNaN( b ) ) {
		error = __(
			'Write each size in rem or px, for example 0.9375rem.',
			'kits-core'
		);
	} else if ( a > b ) {
		error = __(
			'The phone size must not be larger than the wide screen size.',
			'kits-core'
		);
	}
	const label = sprintf(
		/* translators: 1: size name, 2: size slug. */
		__( '%1$s (%2$s)', 'kits-core' ),
		size.name,
		size.slug
	);
	return (
		<div className="kits-app-field" role="group" aria-label={ label }>
			<p>
				<strong>{ label }</strong>
			</p>
			<Flex align="flex-start" gap={ 4 } wrap>
				<FlexBlock>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ sprintf(
							/* translators: %d: screen width in pixels. */
							__( 'Phones (%d px)', 'kits-core' ),
							fluid.min
						) }
						value={ shown.min }
						onChange={ ( v ) => update( 'min', v ) }
					/>
				</FlexBlock>
				<FlexBlock>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ sprintf(
							/* translators: %d: screen width in pixels. */
							__( 'Wide screens (%d px and up)', 'kits-core' ),
							fluid.max
						) }
						value={ shown.max }
						onChange={ ( v ) => update( 'max', v ) }
					/>
				</FlexBlock>
			</Flex>
			{ error && (
				<Notice status="warning" isDismissible={ false }>
					{ error }{ ' ' }
					{ sprintf(
						/* translators: %s: the size that is saved. */
						__( 'Until then, Save keeps %s.', 'kits-core' ),
						value
					) }
				</Notice>
			) }
			<ThemeNote
				isTheme={ isTheme }
				themeLabel={ `${ themeClamp.min } – ${ themeClamp.max }` }
				label={ label }
				onReset={ reset }
			/>
		</div>
	);
}

function FontSizes( { gs } ) {
	const sizes = data.sizes || [];
	const stored = gs.get( SIZES );
	const list = Array.isArray( stored ) ? stored : [];
	const valueOf = ( s ) =>
		list.find( ( x ) => x?.slug === s.slug )?.size ?? s.size;
	const onChange = ( slug, size ) => {
		const next = sizes.map( ( s ) => ( {
			...( list.find( ( x ) => x?.slug === s.slug ) || s ),
			size: s.slug === slug ? size : valueOf( s ),
		} ) );
		// Sizes saved in Styles that the theme no longer has are kept as they are.
		const extra = list.filter(
			( x ) => ! sizes.some( ( s ) => s.slug === x?.slug )
		);
		const allTheme =
			! extra.length &&
			next.every( ( x, i ) => sameSize( x.size, sizes[ i ].size ) );
		gs.set( SIZES, allTheme ? undefined : [ ...next, ...extra ] );
	};
	if ( ! sizes.length ) {
		return null;
	}
	return (
		<>
			<h3>{ __( 'Font sizes', 'kits-core' ) }</h3>
			<p className="description">
				{ sprintf(
					/* translators: 1: phone width, 2: wide screen width, in pixels. */
					__(
						'Each size grows evenly from the phone size at %1$d px to the wide screen size at %2$d px. Write sizes in rem (1rem = 16 px) or px.',
						'kits-core'
					),
					fluid.min,
					fluid.max
				) }
			</p>
			{ sizes.map( ( s ) => (
				<SizeRow
					key={ s.slug }
					size={ s }
					value={ valueOf( s ) }
					onChange={ ( v ) => onChange( s.slug, v ) }
				/>
			) ) }
		</>
	);
}

function Typography( { gs } ) {
	if ( ! data.roles ) {
		return null;
	}
	return (
		<>
			{ ! gs.ready && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'The site’s Styles could not be found, so fonts and sizes cannot be saved here.',
						'kits-core'
					) }
				</Notice>
			) }
			<h3>{ __( 'Fonts', 'kits-core' ) }</h3>
			{ Object.keys( data.roles ).map( ( k ) => (
				<FontRole
					key={ k }
					name={ k }
					role={ data.roles[ k ] }
					gs={ gs }
				/>
			) ) }
			{ data.links?.fonts && (
				<p>
					<Button variant="secondary" href={ data.links.fonts }>
						{ __( 'Manage fonts', 'kits-core' ) }
					</Button>{ ' ' }
					<span className="description">
						{ __(
							'Install or remove fonts in the Font Library; installed fonts show in the lists above.',
							'kits-core'
						) }
					</span>
				</p>
			) }
			<FontSizes gs={ gs } />
		</>
	);
}

export default {
	slug: 'typography',
	label: __( 'Typography', 'kits-core' ),
	items: [],
	Body: Typography,
};
