/**
 * The site's Global Styles (user origin) for the Theme Options app: one store for every section that edits Styles.
 *
 * A section reads with get( path ) and writes with set( path, value ); a path is a list of keys, for example
 * [ 'settings', 'color', 'palette', 'theme' ] or [ 'styles', 'blocks', 'core/group', 'variations', 'x', 'color', 'text' ].
 * set( path, undefined ) removes the key (and parents left empty), which gives the theme's value back.
 *
 * save() reads the post again, applies only the changed paths and posts settings and styles back, so keys this
 * screen does not edit (fonts, a change made in the Site Editor since the screen opened) are kept.
 */
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

const data = window.kitsAdminApp?.globalStyles || {};

// A plain object, or {} (an empty "styles": [] comes back as a list).
const obj = ( v ) =>
	v && 'object' === typeof v && ! Array.isArray( v ) ? v : {};

const docOf = ( src ) => ( {
	settings: obj( src?.settings ),
	styles: obj( src?.styles ),
} );

export function getPath( root, path ) {
	let v = root;
	for ( const k of path ) {
		v = obj( v )[ k ];
		if ( undefined === v ) {
			return undefined;
		}
	}
	return v;
}

// A copy of node with path set to value; undefined removes it and any parent left empty.
function withPath( node, path, value ) {
	const out = { ...obj( node ) };
	const [ k, ...rest ] = path;
	const v = rest.length ? withPath( out[ k ], rest, value ) : value;
	if ( undefined === v || ( rest.length && ! Object.keys( v ).length ) ) {
		delete out[ k ];
	} else {
		out[ k ] = v;
	}
	return out;
}

// JSON with object keys sorted, so { slug, name, color } and { color, name, slug } compare the same.
const stable = ( v ) =>
	JSON.stringify( v, ( k, x ) =>
		x && 'object' === typeof x && ! Array.isArray( x )
			? Object.fromEntries(
					Object.keys( x )
						.sort()
						.map( ( n ) => [ n, x[ n ] ] )
				)
			: x
	);
const same = ( a, b ) => stable( a ) === stable( b );

export function useGlobalStyles() {
	const id = Number( data.id ) || 0;
	const [ base, setBase ] = useState( () => docOf( data ) );
	// JSON of a path => the new value (undefined: removed).
	const [ edits, setEdits ] = useState( {} );

	// The saved document with every edit applied, so a parent path also shows its children's edits.
	const current = Object.keys( edits ).reduce(
		( doc, key ) => withPath( doc, JSON.parse( key ), edits[ key ] ),
		base
	);
	const get = ( path ) => getPath( current, path );
	const set = ( path, value ) =>
		setEdits( ( e ) => ( { ...e, [ JSON.stringify( path ) ]: value } ) );
	const changed = Object.keys( edits ).filter(
		( key ) => ! same( edits[ key ], getPath( base, JSON.parse( key ) ) )
	);

	const save = () => {
		if ( ! changed.length ) {
			return Promise.resolve();
		}
		const sent = { ...edits };
		let doc;
		return apiFetch( {
			path: `/wp/v2/global-styles/${ id }?context=edit`,
		} )
			.then( ( fresh ) => {
				doc = docOf( fresh );
				changed.forEach( ( key ) => {
					doc = withPath( doc, JSON.parse( key ), sent[ key ] );
				} );
				return apiFetch( {
					path: `/wp/v2/global-styles/${ id }`,
					method: 'POST',
					data: doc,
				} );
			} )
			.then( ( res ) => {
				// What was sent, if the answer leaves settings or styles out.
				setBase( res?.settings && res?.styles ? docOf( res ) : doc );
				// Edits made while saving stay.
				setEdits( ( e ) =>
					Object.fromEntries(
						Object.entries( e ).filter(
							( [ key, v ] ) =>
								! ( key in sent ) || v !== sent[ key ]
						)
					)
				);
			} );
	};

	return { ready: id > 0, get, set, dirty: changed.length > 0, save };
}
