/**
 * Directory: List / Map switch and Near me (directory-map.php). The list is the default and stays the text
 * alternative; MapLibre loads only when the map is opened; the browser asks for the visitor's location only when
 * "Use my location" is pressed.
 */
( function () {
	'use strict';
	var cfg = window.awDirMap;
	var tools = document.querySelector( '.aw-dir-tools' );
	if ( ! cfg || ! tools ) {
		return;
	}
	var filters = JSON.parse( tools.getAttribute( 'data-filters' ) || '{}' );
	var mapBox = document.querySelector( '.aw-dir-map' );
	var mapEl = mapBox.querySelector( '.aw-dir-map-canvas' );
	var note = mapBox.querySelector( '.aw-dir-map-note' );
	var near = document.getElementById( 'aw-near' );
	var list = [ document.querySelector( '.aw-orgs' ), document.querySelector( '.wp-block-query-pagination' ) ].filter( Boolean );
	var map = null;
	var data = null;
	var nearMarker = null;

	function qs( extra, noPlace ) {
		var p = new URLSearchParams();
		Object.keys( filters ).forEach( function ( k ) {
			if ( ! ( noPlace && 'place' === k ) ) {
				p.set( k, filters[ k ] ); // Near me: distance replaces the page's place
			}
		} );
		Object.keys( extra || {} ).forEach( function ( k ) { p.set( k, extra[ k ] ); } );
		return p.toString();
	}

	function esc( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function load( tag, attrs ) {
		return new Promise( function ( ok, fail ) {
			var el = document.createElement( tag );
			Object.keys( attrs ).forEach( function ( k ) { el[ k ] = attrs[ k ]; } );
			el.onload = ok;
			el.onerror = fail;
			document.head.appendChild( el );
		} );
	}

	/* ---------- List / Map ---------- */

	function setView( view ) {
		tools.querySelectorAll( '[data-view]' ).forEach( function ( b ) {
			b.setAttribute( 'aria-pressed', b.getAttribute( 'data-view' ) === view ? 'true' : 'false' );
		} );
		mapBox.hidden = 'map' !== view;
		list.forEach( function ( el ) { el.style.display = 'map' === view ? 'none' : ''; } );
		if ( 'map' === view ) {
			openMap();
		}
	}

	tools.querySelectorAll( '[data-view]' ).forEach( function ( b ) {
		b.addEventListener( 'click', function () { setView( b.getAttribute( 'data-view' ) ); } );
	} );

	/** A type icon (the theme's CSS mask SVG) drawn in a white disc, as a map image. */
	function iconImage( slug ) {
		var span = document.querySelector( '.aw-dir-icons [data-type="' + slug + '"]' );
		var css = span ? getComputedStyle( span ).getPropertyValue( '--t' ).trim() : '';
		var m = css.match( /^url\(\s*"(.*)"\s*\)$/ ) || css.match( /^url\(\s*'(.*)'\s*\)$/ ) || css.match( /^url\((.*)\)$/ );
		return new Promise( function ( ok ) {
			var size = 64;
			var c = document.createElement( 'canvas' );
			c.width = c.height = size;
			var g = c.getContext( '2d' );
			g.beginPath();
			g.arc( size / 2, size / 2, size / 2 - 3, 0, Math.PI * 2 );
			g.fillStyle = '#ffffff';
			g.fill();
			g.lineWidth = 4;
			g.strokeStyle = '#191919';
			g.stroke();
			if ( ! m ) {
				ok( g.getImageData( 0, 0, size, size ) );
				return;
			}
			var img = new Image();
			img.onload = function () {
				g.drawImage( img, 16, 16, 32, 32 );
				ok( g.getImageData( 0, 0, size, size ) );
			};
			img.onerror = function () { ok( g.getImageData( 0, 0, size, size ) ); };
			img.src = m[ 1 ];
		} );
	}

	function popupHtml( p ) {
		var type = ( data && data.types[ p.t ] ) || '';
		return '<p class="aw-map-pop-name"><a href="' + esc( p.u ) + '">' + esc( p.n ) + '</a></p>' +
			'<p class="aw-map-pop-meta">' + esc( [ type, p.d ].filter( Boolean ).join( ' · ' ) ) + '</p>' +
			( Number( p.a ) ? '<p class="aw-map-pop-approx">Approximate location (area, PIN or district centre)</p>' : '' );
	}

	function openMap() {
		if ( map ) {
			map.resize();
			return;
		}
		note.textContent = 'Loading the map…';
		Promise.all( [
			window.maplibregl ? Promise.resolve() : load( 'script', { src: cfg.lib + 'maplibre-gl.js' } ),
			load( 'link', { rel: 'stylesheet', href: cfg.lib + 'maplibre-gl.css' } ),
			fetch( cfg.points + '?' + qs() ).then( function ( r ) { return r.json(); } ),
		] ).then( function ( res ) {
			data = res[ 2 ];
			var pts = data.points || [];
			var shown = pts.length;
			note.textContent = shown + ' of ' + data.total + ' organisations have a location on the map' +
				( data.approx ? ' (' + data.approx + ' approximate)' : '' ) + '. The list has all of them, with the same details.';
			map = new window.maplibregl.Map( {
				container: mapEl,
				style: cfg.style,
				center: [ 79.0, 22.5 ],
				zoom: 3.6,
				attributionControl: { compact: true },
				cooperativeGestures: true,
			} );
			map.addControl( new window.maplibregl.NavigationControl( { showCompass: false } ), 'top-right' );
			map.on( 'load', function () {
				var slugs = Object.keys( data.types );
				Promise.all( slugs.map( iconImage ) ).then( function ( imgs ) {
					imgs.forEach( function ( im, i ) { map.addImage( 't-' + slugs[ i ], im, { pixelRatio: 2 } ); } );
					return iconImage( '' );
				} ).then( function ( blank ) {
					map.addImage( 't-', blank, { pixelRatio: 2 } );
					map.addSource( 'orgs', {
						type: 'geojson',
						cluster: true,
						clusterRadius: 45,
						clusterMaxZoom: 13,
						data: {
							type: 'FeatureCollection',
							features: pts.map( function ( p ) {
								return { type: 'Feature', geometry: { type: 'Point', coordinates: [ p.x, p.y ] }, properties: p };
							} ),
						},
					} );
					map.addLayer( { id: 'clusters', type: 'circle', source: 'orgs', filter: [ 'has', 'point_count' ], paint: {
						'circle-color': '#FFBE1A', 'circle-stroke-color': '#191919', 'circle-stroke-width': 2,
						'circle-radius': [ 'step', [ 'get', 'point_count' ], 16, 25, 20, 100, 26 ],
					} } );
					map.addLayer( { id: 'cluster-count', type: 'symbol', source: 'orgs', filter: [ 'has', 'point_count' ], layout: {
						'text-field': [ 'get', 'point_count_abbreviated' ], 'text-size': 13, 'text-font': [ 'Noto Sans Bold' ],
					}, paint: { 'text-color': '#191919' } } );
					map.addLayer( { id: 'org', type: 'symbol', source: 'orgs', filter: [ '!', [ 'has', 'point_count' ] ], layout: {
						'icon-image': [ 'concat', 't-', [ 'get', 't' ] ], 'icon-size': 1, 'icon-allow-overlap': true,
					}, paint: { 'icon-opacity': [ 'case', [ '==', [ 'get', 'a' ], 1 ], 0.7, 1 ] } } );
					map.on( 'click', 'clusters', function ( e ) {
						var f = map.queryRenderedFeatures( e.point, { layers: [ 'clusters' ] } )[ 0 ];
						map.getSource( 'orgs' ).getClusterExpansionZoom( f.properties.cluster_id ).then( function ( z ) {
							map.easeTo( { center: f.geometry.coordinates, zoom: z } );
						} );
					} );
					map.on( 'click', 'org', function ( e ) {
						var f = e.features[ 0 ];
						new window.maplibregl.Popup( { maxWidth: '260px' } ).setLngLat( f.geometry.coordinates ).setHTML( popupHtml( f.properties ) ).addTo( map );
					} );
					[ 'clusters', 'org' ].forEach( function ( l ) {
						map.on( 'mouseenter', l, function () { map.getCanvas().style.cursor = 'pointer'; } );
						map.on( 'mouseleave', l, function () { map.getCanvas().style.cursor = ''; } );
					} );
					if ( pts.length ) {
						var b = new window.maplibregl.LngLatBounds();
						pts.forEach( function ( p ) { b.extend( [ p.x, p.y ] ); } );
						map.fitBounds( b, { padding: 40, maxZoom: 12, duration: 0 } );
					}
				} );
			} );
		} ).catch( function () {
			note.textContent = 'The map could not be loaded just now. The list has every organisation.';
		} );
	}

	/* ---------- Near me ---------- */

	var openBtn = tools.querySelector( '.aw-near-open' );
	var form = near.querySelector( '.aw-near-form' );
	var out = near.querySelector( '.aw-near-results' );
	var pin = near.querySelector( '.aw-near-pin' );
	var km = near.querySelector( '.aw-near-km' );
	var last = null;

	openBtn.addEventListener( 'click', function () {
		near.hidden = ! near.hidden;
		openBtn.setAttribute( 'aria-expanded', near.hidden ? 'false' : 'true' );
		if ( ! near.hidden ) {
			pin.focus();
		}
	} );

	function show( res ) {
		var types = res.types || {};
		var html = '';
		if ( res.count ) {
			html += '<p class="aw-near-head">' + res.count + ' within ' + res.km + ' km' + ( res.origin ? ' of ' + esc( res.origin ) : '' ) + ', nearest first' + ( res.count > 50 ? ' (first 50)' : '' ) + '.</p><ol class="aw-near-list">';
			res.results.forEach( function ( p ) {
				html += '<li><a href="' + esc( p.u ) + '">' + esc( p.n ) + '</a> <span class="aw-near-km-val">' + p.km + ' km' + ( Number( p.a ) ? ' (approx.)' : '' ) + '</span><br><span class="aw-near-meta">' + esc( [ types[ p.t ], p.d ].filter( Boolean ).join( ' · ' ) ) + '</span></li>';
			} );
			html += '</ol>';
		} else {
			html += '<p class="aw-near-head">None within ' + res.km + ' km' + ( res.origin ? ' of ' + esc( res.origin ) : '' ) + '.</p>';
		}
		if ( res.district && res.district.url ) {
			html += '<p class="aw-near-fallback"><a href="' + esc( res.district.url ) + '">All organisations in ' + esc( res.district.name ) + ' district' + ( res.district.count ? ' (' + res.district.count + ')' : '' ) + '</a></p>';
		}
		out.innerHTML = html;
		if ( map && window.maplibregl ) {
			if ( nearMarker ) {
				nearMarker.remove();
			}
			nearMarker = new window.maplibregl.Marker( { color: '#2368D7' } ).setLngLat( [ res.lng, res.lat ] ).addTo( map );
			map.easeTo( { center: [ res.lng, res.lat ], zoom: res.km <= 5 ? 12 : res.km <= 10 ? 11 : res.km <= 25 ? 10 : 9 } );
		}
	}

	function ask( extra ) {
		last = extra;
		out.textContent = 'Finding the nearest…';
		fetch( cfg.near + '?' + qs( Object.assign( { km: km.value }, extra ), true ) ).then( function ( r ) {
			return r.json().then( function ( j ) { return { ok: r.ok, j: j }; } );
		} ).then( function ( x ) {
			if ( ! x.ok ) {
				out.textContent = ( x.j && x.j.message ) || 'That did not work. Try a PIN code.';
				return;
			}
			show( x.j );
		} ).catch( function () { out.textContent = 'That did not work. Try again in a moment.'; } );
	}

	near.querySelector( '.aw-near-locate' ).addEventListener( 'click', function () {
		if ( ! navigator.geolocation ) {
			out.textContent = 'Your browser cannot share your location. Enter a PIN code instead.';
			return;
		}
		out.textContent = 'Asking your browser for your location…';
		navigator.geolocation.getCurrentPosition( function ( pos ) {
			ask( { lat: pos.coords.latitude.toFixed( 4 ), lng: pos.coords.longitude.toFixed( 4 ) } );
		}, function () {
			out.textContent = 'Location not shared. Enter a PIN code instead.';
			pin.focus();
		}, { enableHighAccuracy: false, timeout: 10000, maximumAge: 600000 } );
	} );

	form.addEventListener( 'submit', function ( e ) {
		e.preventDefault();
		var v = pin.value.replace( /\D/g, '' );
		if ( /^[1-9]\d{5}$/.test( v ) ) {
			ask( { pin: v } );
		} else if ( last ) {
			ask( last );
		} else {
			out.textContent = 'Enter a 6-digit PIN code, or use your location.';
			pin.focus();
		}
	} );
	km.addEventListener( 'change', function () {
		if ( last ) {
			ask( last );
		}
	} );
}() );
