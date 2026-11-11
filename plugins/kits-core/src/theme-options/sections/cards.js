/**
 * Theme Options → Cards (docs/admin-ui-spec.md D1.8).
 *
 * Card padding, Card gap and the stacked large card's padding and gap, kept in the site's Global Styles (user origin)
 * under settings.custom.kits.card through the app's one Global Styles store (../global-styles.js). WordPress prints
 * them as --wp--custom--kits--card--*, and the theme's kit.css reads them with its own token as the fallback: an unset
 * key is the theme's value. Field data comes from includes/admin-app-cards.php (window.kitsAdminApp.cards).
 */
import { __, sprintf } from '@wordpress/i18n';
import { Notice, SelectControl } from '@wordpress/components';
import { ThemeNote } from '../theme-note';

const data = window.kitsAdminApp?.cards || {};
const fields = data.fields || {};
const keys = Object.keys( fields );
const BASE = [ 'settings', 'custom', 'kits', 'card' ];
const pathOf = ( k ) => [ ...BASE, ...k.split( '.' ) ];

function CardsField( { gs, name } ) {
	const f = fields[ name ];
	const path = pathOf( name );
	const v = gs.get( path );
	const value = 'string' === typeof v ? v : '';
	const known = f.choices.some( ( c ) => c.value === value );
	// '' is the theme's value: the key is removed.
	const onChange = ( next ) => gs.set( path, '' === next ? undefined : next );
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
				onChange={ onChange }
			/>
			<ThemeNote
				isTheme={ '' === value }
				themeLabel={ f.theme }
				label={ f.label }
				onReset={ () => onChange( '' ) }
			/>
		</div>
	);
}

function CardsBody( { gs } ) {
	if ( ! gs.ready || ! keys.length ) {
		return (
			<Notice status="warning" isDismissible={ false }>
				{ __(
					'Card values are kept in Styles, which your account cannot change.',
					'kits-core'
				) }
			</Notice>
		);
	}
	return (
		<>
			<p className="description">
				{ __(
					'Sizes are the theme’s spacing tokens. The site keeps the theme’s values until you pick one.',
					'kits-core'
				) }{ ' ' }
				{ data.links?.tokens && (
					<a href={ data.links.tokens }>
						{ __( 'See the tokens', 'kits-core' ) }
					</a>
				) }
			</p>
			{ keys.map( ( k ) => (
				<CardsField key={ k } gs={ gs } name={ k } />
			) ) }
			{ ( data.rules || [] ).map( ( [ k, v ] ) => (
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
	Body: CardsBody,
};
