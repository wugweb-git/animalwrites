/**
 * Kits admin shell: the breadcrumb trail in the app header (Plugin › Tab › Section, or the item on a detail screen)
 * and the "Change in …" link of a setting that lives on another screen.
 *
 * The section crumb comes from the screen: a ChipRow of links (the settings groups, #slug) names the open one, and a
 * detail screen names its item with useCrumb(). The header listens, so the plugins' own scripts need no change.
 */
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button as WPButton } from '@wordpress/components';

/** The last crumb the screen named ({ label, href }), and who listens for it. */
let current = null;
const listeners = new Set();

/**
 * Name the screen's section or item (null clears it).
 *
 * @param {Object|null} crumb { label, href }.
 */
export function setCrumb( crumb ) {
	const next = crumb && crumb.label ? crumb : null;
	if ( next?.label === current?.label && next?.href === current?.href ) {
		return;
	}
	current = next;
	listeners.forEach( ( fn ) => fn( current ) );
}

/**
 * Name the section or item for as long as the calling part is on the screen.
 *
 * @param {string} label The section's or item's name ('' for none).
 * @param {string} href  Its address (optional; the last crumb is not a link).
 */
export function useCrumb( label, href ) {
	useEffect( () => {
		if ( ! label ) {
			return undefined;
		}
		setCrumb( { label, href } );
		return () => {
			if ( current?.label === label ) {
				setCrumb( null );
			}
		};
	}, [ label, href ] );
}

/** The crumb the screen named, kept up to date. */
function useNamedCrumb() {
	const [ crumb, set ] = useState( current );
	useEffect( () => {
		listeners.add( set );
		set( current );
		return () => listeners.delete( set );
	}, [] );
	return crumb;
}

/**
 * The trail: Plugin › Tab › Section. Every crumb but the last links to its level; the last is the page.
 *
 * @param {Object} props
 * @param {string} props.name   Plugin name (links to its first tab).
 * @param {Array}  props.tabs   The header's tabs ({ label, url, current }).
 * @param {Array}  props.crumbs Crumbs given by the screen ([ { label, href } ]), in place of the tab and section.
 */
export function Breadcrumbs( { name, tabs = [], crumbs } ) {
	const named = useNamedCrumb();
	const tab = tabs.find( ( t ) => t.current );
	let trail = [ { label: name, href: tabs[ 0 ]?.url } ];
	if ( Array.isArray( crumbs ) && crumbs.length ) {
		trail = trail.concat( crumbs );
	} else {
		if ( tab ) {
			trail.push( { label: tab.label, href: tab.url } );
		}
		if ( named ) {
			trail.push( named );
		}
	}
	trail = trail.filter( ( c ) => c && c.label );
	if ( trail.length < 2 ) {
		return null;
	}
	return (
		<nav
			className="kits-shell-crumbs"
			aria-label={ __( 'Breadcrumb', 'kits-core' ) }
		>
			<ol>
				{ trail.map( ( c, i ) => {
					const last = i === trail.length - 1;
					return (
						<li key={ `${ i }-${ c.label }` }>
							{ last || ! c.href ? (
								<span
									aria-current={ last ? 'page' : undefined }
								>
									{ c.label }
								</span>
							) : (
								<WPButton variant="link" href={ c.href }>
									{ c.label }
								</WPButton>
							) }
						</li>
					);
				} ) }
			</ol>
		</nav>
	);
}

/**
 * "Change in <place>": a setting kept on another admin screen. Same tab (an admin-internal link).
 *
 * @param {Object} props
 * @param {Object} props.to { label, url }: the place's name ("Site Editor → Header") and its admin address.
 */
export function ChangeIn( { to } ) {
	if ( ! to || ! to.label || ! to.url ) {
		return null;
	}
	return (
		<p className="kits-shell-changein">
			<WPButton variant="link" href={ to.url }>
				{ sprintf(
					/* translators: %s: where the setting is changed ("Site Editor → Header"). */
					__( 'Change in %s', 'kits-core' ),
					to.label
				) }
			</WPButton>
		</p>
	);
}
