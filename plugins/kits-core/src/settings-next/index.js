/**
 * Kits → Settings in the options panel (trial, &ui=next): includes/admin-settings-next.php.
 *
 * Dark tab bar with the six Kits tabs, the sections on the left, each section's fields in groups that fold, On/Off
 * toggles for switches and a select for choices. Save sends only the changed fields to kits/v1/settings-panel.
 * The shell parts come from window.kits.shell (script kits-shell, a dependency kits_shell_app() adds).
 */
import { createRoot, useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';

const {
	Panel,
	PanelGroup,
	OnOff,
	Field,
	Notice,
	LoadingState,
	ErrorState,
	Button,
	EmptyState,
} = window.kits.shell;
const data = window.kitsSettingsNext || {};

function Choice( { field, value, onChange } ) {
	return (
		<Field
			label={ field.label }
			help={ field.note }
			changed={ value !== field.default }
			onReset={ () => onChange( field.default ) }
			render={ ( id, describedBy ) => (
				<select
					id={ id }
					value={ value }
					aria-describedby={ describedBy }
					onChange={ ( e ) => onChange( e.target.value ) }
				>
					{ field.choices.map( ( c ) => (
						<option key={ c.value } value={ c.value }>
							{ c.label }
						</option>
					) ) }
				</select>
			) }
		/>
	);
}

function App() {
	const [ sections, setSections ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ section, setSection ] = useState(
		() => window.location.hash.replace( '#', '' ) || 'performance'
	);
	const [ edits, setEdits ] = useState( {} );
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	const load = () => {
		setError( '' );
		apiFetch( { path: data.route } )
			.then( ( r ) => setSections( r.sections || [] ) )
			.catch( ( e ) => setError( e.message || String( e ) ) );
	};
	useEffect( load, [] );
	useEffect( () => {
		if ( section ) {
			window.history.replaceState( null, '', '#' + section );
		}
	}, [ section ] );

	const fieldsOf = ( list ) =>
		( list || [] ).flatMap( ( s ) =>
			s.groups.flatMap( ( g ) => g.fields )
		);
	const all = fieldsOf( sections );
	const valueOf = ( f ) => ( f.key in edits ? edits[ f.key ] : f.value );
	const dirty = all.filter(
		( f ) => f.key in edits && edits[ f.key ] !== f.value
	);
	const set = ( key, v ) => setEdits( ( e ) => ( { ...e, [ key ]: v } ) );

	const save = () => {
		const values = {};
		dirty.forEach( ( f ) => ( values[ f.key ] = edits[ f.key ] ) );
		setBusy( true );
		setNotice( null );
		apiFetch( { path: data.route, method: 'POST', data: { values } } )
			.then( ( r ) => {
				setSections( r.sections || [] );
				setEdits( {} );
				setNotice( {
					tone: 'success',
					text: r.saved
						? __( 'Settings saved.', 'kits-core' )
						: __( 'Nothing changed.', 'kits-core' ),
				} );
			} )
			.catch( ( e ) =>
				setNotice( { tone: 'error', text: e.message || String( e ) } )
			)
			.finally( () => setBusy( false ) );
	};

	// Leaving with unsaved changes asks first.
	useEffect( () => {
		const warn = ( e ) => {
			if ( dirty.length ) {
				e.preventDefault();
				e.returnValue = '';
			}
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	} );

	const list = ( sections || [] ).map( ( s ) => ( {
		slug: s.slug,
		label: s.label,
		note: s.note,
	} ) );
	list.push( {
		slug: 'preview',
		label: __( 'Preview access', 'kits-core' ),
		note: __( 'On the current screen', 'kits-core' ),
	} );
	const open = ( sections || [] ).find( ( s ) => s.slug === section );

	const aside = (
		<>
			<a href={ data.current }>
				{ __( 'Open the current screen', 'kits-core' ) }
			</a>
			<Button
				variant="primary"
				busy={ busy }
				disabled={ ! dirty.length }
				onClick={ save }
			>
				{ dirty.length
					? sprintf(
							/* translators: %d: number of changed settings. */
							_n(
								'Save %d change',
								'Save %d changes',
								dirty.length,
								'kits-core'
							),
							dirty.length
						)
					: __( 'Save', 'kits-core' ) }
			</Button>
		</>
	);

	let body;
	if ( error ) {
		body = <ErrorState message={ error } onRetry={ load } />;
	} else if ( null === sections ) {
		body = <LoadingState />;
	} else if ( 'preview' === section ) {
		body = (
			<EmptyState
				title={ __(
					'Preview access stays on the current screen for now.',
					'kits-core'
				) }
			>
				<p>
					<a href={ data.preview }>
						{ __( 'Open Preview access', 'kits-core' ) }
					</a>
				</p>
			</EmptyState>
		);
	} else if ( ! open ) {
		body = <EmptyState title={ __( 'No settings here.', 'kits-core' ) } />;
	} else {
		body = (
			<>
				<h2 className="screen-reader-text">{ open.label }</h2>
				{ open.groups.map( ( g ) => {
					const on = g.fields.filter(
						( f ) => 'switch' === f.kind && 'on' === valueOf( f )
					).length;
					const switches = g.fields.filter(
						( f ) => 'switch' === f.kind
					).length;
					return (
						<PanelGroup
							key={ g.title }
							title={ g.title }
							summary={
								switches
									? sprintf(
											/* translators: 1: switches on, 2: switches. */
											__(
												'%1$d of %2$d on',
												'kits-core'
											),
											on,
											switches
										)
									: ''
							}
						>
							{ g.fields.map( ( f ) =>
								'switch' === f.kind ? (
									<OnOff
										key={ f.key }
										label={ f.label }
										help={ f.note }
										checked={ 'on' === valueOf( f ) }
										changed={ valueOf( f ) !== f.default }
										onReset={ () =>
											set( f.key, f.default )
										}
										onChange={ ( v ) =>
											set( f.key, v ? 'on' : 'off' )
										}
									/>
								) : (
									<Choice
										key={ f.key }
										field={ f }
										value={ valueOf( f ) }
										onChange={ ( v ) => set( f.key, v ) }
									/>
								)
							) }
						</PanelGroup>
					);
				} ) }
			</>
		);
	}

	return (
		<Panel
			title={ __( 'Kits', 'kits-core' ) }
			icon={ data.icon }
			tabs={ data.tabs || [] }
			sections={ list }
			section={ section }
			onSection={ setSection }
			aside={ aside }
		>
			{ notice && (
				<Notice
					tone={ notice.tone }
					onDismiss={ () => setNotice( null ) }
				>
					{ notice.text }
				</Notice>
			) }
			{ body }
		</Panel>
	);
}

const root = document.getElementById( 'kits-settings-next' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
