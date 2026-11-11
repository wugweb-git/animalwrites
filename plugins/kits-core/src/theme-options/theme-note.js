/**
 * "Theme value" under a field, or "Theme value: X" with a Reset to theme link when the field differs from the theme.
 */
import { __, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

export function ThemeNote( { isTheme, themeLabel, label, onReset } ) {
	return (
		<p className="description">
			{ isTheme
				? __( 'Theme value', 'kits-core' )
				: sprintf(
						/* translators: %s: the theme's value. */
						__( 'Theme value: %s', 'kits-core' ),
						themeLabel || __( 'empty', 'kits-core' )
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
