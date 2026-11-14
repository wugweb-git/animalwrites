/**
 * The Dashboard's module cards: each module with its on/off switch (Settings users), where it is set up and its next
 * job run. Data from GET rankweb/v1/modules (includes/rankweb-modules.php); a switch saves through POST
 * settings/modules, which keeps every other switch as it is.
 */
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import {
	ErrorState,
	LoadingState,
	Notice,
	SectionCard,
	SettingsBlock,
	Switch,
} from '@kits/shell'; // eslint-disable-line import/no-unresolved -- Kits Core's shell script (window.kits.shell), mapped in webpack.config.js

const app = window.rankwebApp || {};
const path = ( p ) => `/${ app.rest }/${ p }`;
const errorText = ( e ) => ( e && e.message ) || String( e );

export function ModuleCards() {
	const [ cards, setCards ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( '' );
	const [ note, setNote ] = useState( { tone: 'info', text: '' } );
	const admin = !! app.canSwitch;

	const load = () =>
		apiFetch( { path: path( 'modules' ) } )
			.then( ( r ) => setCards( r.modules ) )
			.catch( ( e ) => setError( errorText( e ) ) );
	useEffect( () => {
		load();
	}, [] );

	const flip = ( key, on ) => {
		setBusy( key );
		setNote( { tone: 'info', text: '' } );
		apiFetch( {
			path: path( 'settings/modules' ),
			method: 'POST',
			data: { on: { [ key ]: on } },
		} )
			.then( ( r ) => {
				setNote( { tone: 'success', text: r.message } );
				return load();
			} )
			.catch( ( e ) =>
				setNote( { tone: 'error', text: errorText( e ) } )
			)
			.finally( () => setBusy( '' ) );
	};

	if ( error ) {
		return <ErrorState message={ error } />;
	}
	return (
		<SectionCard
			id="module-switches"
			title={ __( 'Modules on and off', 'aw-seo-extras' ) }
			description={ __(
				'A module switched off is not loaded and its scheduled job stops. Modules without a switch are always on.',
				'aw-seo-extras'
			) }
		>
			{ note.text && (
				<Notice
					tone={ note.tone }
					onDismiss={ () => setNote( { tone: 'info', text: '' } ) }
				>
					<span role="status">{ note.text }</span>
				</Notice>
			) }
			{ null === cards ? (
				<LoadingState
					label={ __( 'Loading the modules…', 'aw-seo-extras' ) }
				/>
			) : (
				cards.map( ( c ) => (
					<SettingsBlock
						key={ c.key }
						title={ c.label }
						description={ c.desc }
						note={
							c.on && c.next
								? sprintf(
										/* translators: %s: date and time. */
										__( 'Next run: %s', 'aw-seo-extras' ),
										new Date(
											c.next * 1000
										).toLocaleString()
									)
								: ''
						}
					>
						{ c.switch ? (
							<Switch
								label={ sprintf(
									/* translators: %s: module name. */
									__( '%s on', 'aw-seo-extras' ),
									c.label
								) }
								checked={ c.on }
								disabled={ ! admin || !! busy }
								help={
									admin
										? ''
										: __(
												'Only administrators can switch modules.',
												'aw-seo-extras'
											)
								}
								onChange={ ( v ) => flip( c.key, v ) }
							/>
						) : (
							<p>{ __( 'Always on.', 'aw-seo-extras' ) }</p>
						) }
						{ c.settingsUrl && (
							<p>
								<a href={ c.settingsUrl }>
									{ sprintf(
										/* translators: %s: module name. */
										__( '%s settings', 'aw-seo-extras' ),
										c.label
									) }
								</a>
							</p>
						) }
					</SettingsBlock>
				) )
			) }
		</SectionCard>
	);
}
