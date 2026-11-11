/**
 * Theme Options → Cards (docs/admin-ui-spec.md D1.8).
 *
 * Card padding, Card gap and the stacked large card's padding and gap, saved in the site's Global Styles (user origin)
 * under settings.custom.kits.card. WordPress prints them as --wp--custom--kits--card--*, and the theme's kit.css reads
 * them with its own token as the fallback: an unset key is the theme's value. The data comes from
 * includes/admin-app-cards.php (window.kitsAdminApp.cards).
 *
 * A section the app's one Save also saves: { slug, label, available, Component, subscribe, isDirty, save }.
 */
import { useSyncExternalStore } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { SelectControl } from '@wordpress/components';

const info = window.kitsAdminApp?.cards || null;
const fields = info?.fields || {};
const keys = Object.keys( fields );
const isObject = ( v ) => !! v && 'object' === typeof v && ! Array.isArray( v );

// The section's state: what is saved ({ 'stacked.gap': 'var(--kit-space-4)' }) and this screen's edits ('' = theme).
let saved = { ...( isObject( info?.saved ) ? info.saved : {} ) };
let edits = {};
let version = 0;
const listeners = new Set();
const emit = () => {
	version++;
	listeners.forEach( ( l ) => l() );
};
const subscribe = ( l ) => {
	listeners.add( l );
	return () => listeners.delete( l );
};
const base = ( k ) => saved[ k ] ?? '';
const valueOf = ( k ) => ( k in edits ? edits[ k ] : base( k ) );
const changed = () =>
	keys.filter( ( k ) => k in edits && edits[ k ] !== base( k ) );
const setValue = ( k, v ) => {
	edits = { ...edits, [ k ]: v };
	emit();
};

/**
 * Global Styles save: read the user Global Styles post, change only the given paths, write settings and styles back,
 * so everything else in it (fonts, colours, other sections' values) is kept. Saves from several sections run one after
 * another (window.kitsAdminGlobalStylesQueue), so one cannot write back a copy read before another's save.
 *
 * @param {number} id      The user Global Styles post id.
 * @param {Object} changes { 'custom.kits.card.gap': value } under settings; null or '' removes the key.
 * @return {Promise<Object>} The saved settings.
 */
function saveGlobalStylesSettings( id, changes ) {
	const run = async () => {
		const gs = await apiFetch( {
			path: `/wp/v2/global-styles/${ id }?context=edit`,
		} );
		// An empty "styles" is stored as a JSON list ([]): send it back as an object.
		const settings = isObject( gs?.settings )
			? structuredClone( gs.settings )
			: {};
		const styles = isObject( gs?.styles ) ? gs.styles : {};
		Object.keys( changes ).forEach( ( path ) => {
			const parts = path.split( '.' );
			const last = parts.pop();
			const chain = [ settings ];
			parts.forEach( ( p ) => {
				const at = chain[ chain.length - 1 ];
				if ( ! isObject( at[ p ] ) ) {
					at[ p ] = {};
				}
				chain.push( at[ p ] );
			} );
			const v = changes[ path ];
			if ( null === v || '' === v ) {
				delete chain[ chain.length - 1 ][ last ];
				// Drop the objects this left empty, from the inside out.
				for ( let i = parts.length - 1; i >= 0; i-- ) {
					if ( Object.keys( chain[ i + 1 ] ).length ) {
						break;
					}
					delete chain[ i ][ parts[ i ] ];
				}
			} else {
				chain[ chain.length - 1 ][ last ] = v;
			}
		} );
		const res = await apiFetch( {
			path: `/wp/v2/global-styles/${ id }`,
			method: 'POST',
			data: { settings, styles },
		} );
		return isObject( res?.settings ) ? res.settings : {};
	};
	const queue = ( window.kitsAdminGlobalStylesQueue || Promise.resolve() )
		.catch( () => {} )
		.then( run );
	window.kitsAdminGlobalStylesQueue = queue;
	return queue;
}

const at = ( obj, path ) =>
	path
		.split( '.' )
		.reduce( ( o, p ) => ( isObject( o ) ? o[ p ] : undefined ), obj );

async function save() {
	const todo = changed();
	if ( ! todo.length ) {
		return;
	}
	const sent = Object.fromEntries( todo.map( ( k ) => [ k, edits[ k ] ] ) );
	let res;
	try {
		res = await saveGlobalStylesSettings(
			info.postId,
			Object.fromEntries(
				todo.map( ( k ) => [ 'custom.kits.card.' + k, sent[ k ] ] )
			)
		);
	} catch ( err ) {
		throw new Error(
			sprintf(
				/* translators: %s: the error WordPress gave. */
				__( 'Cards not saved: %s', 'kits-core' ),
				err?.message || __( 'try again.', 'kits-core' )
			)
		);
	}
	const card = at( res, 'custom.kits.card' );
	const next = {};
	keys.forEach( ( k ) => {
		const v = at( card, k );
		if ( 'string' === typeof v && '' !== v ) {
			next[ k ] = v;
		}
	} );
	saved = next;
	edits = {};
	emit();
	// WordPress drops settings.custom for an account that may not add unfiltered HTML.
	const lost = todo.filter( ( k ) => ( next[ k ] ?? '' ) !== sent[ k ] );
	if ( lost.length ) {
		throw new Error(
			sprintf(
				/* translators: %s: field names. */
				__(
					'Cards: WordPress did not keep %s. Your account may not be allowed to change Styles values.',
					'kits-core'
				),
				lost.map( ( k ) => fields[ k ].label ).join( ', ' )
			)
		);
	}
}

function CardsField( { name, ThemeNote } ) {
	useSyncExternalStore( subscribe, () => version );
	const f = fields[ name ];
	const value = valueOf( name );
	const known = f.choices.some( ( c ) => c.value === value );
	return (
		<div className="kits-app-field">
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ f.label }
				help={ f.help }
				value={ value }
				options={ [
					{
						value: '',
						label: sprintf(
							/* translators: %s: the theme's value. */
							__( 'Theme (%s)', 'kits-core' ),
							f.theme
						),
					},
					...f.choices,
					// A value set elsewhere (Site Editor, an import) stays shown until it is changed.
					...( '' !== value && ! known
						? [ { value, label: value } ]
						: [] ),
				] }
				onChange={ ( v ) => setValue( name, v ) }
			/>
			<ThemeNote
				isTheme={ '' === value }
				themeLabel={ f.theme }
				label={ f.label }
				onReset={ () => setValue( name, '' ) }
			/>
		</div>
	);
}

function CardsSection( { ThemeNote } ) {
	return (
		<>
			<p className="description">
				{ __(
					'Sizes are the theme’s spacing tokens. The site keeps the theme’s values until you pick one.',
					'kits-core'
				) }{ ' ' }
				{ info?.links?.tokens && (
					<a href={ info.links.tokens }>
						{ __( 'See the tokens', 'kits-core' ) }
					</a>
				) }
			</p>
			{ keys.map( ( k ) => (
				<CardsField key={ k } name={ k } ThemeNote={ ThemeNote } />
			) ) }
			{ ( info?.rules || [] ).map( ( [ k, v ] ) => (
				<p key={ k }>
					<strong>{ k }:</strong> { v }
				</p>
			) ) }
		</>
	);
}

export default {
	slug: 'cards',
	label: __( 'Cards', 'kits-core' ),
	available: !! info?.postId && keys.length > 0,
	Component: CardsSection,
	subscribe,
	isDirty: () => changed().length > 0,
	save,
};
