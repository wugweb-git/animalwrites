/**
 * PlanWeb's new screens (trial, &ui=next): Dashboard, Entries and Settings, drawn with the Kits admin shell (@kits/shell
 * is window.kits.shell from Kits Core) and fed by the planweb/v1 routes. Data and the screen come from
 * window.planwebApp (includes/planweb-ui.php).
 */
import {
	createRoot,
	useCallback,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import {
	AppShell,
	AppHeader,
	Button,
	ChipRow,
	DataList,
	EmptyState,
	ErrorState,
	Field,
	LoadingState,
	Notice,
	RefTable,
	SectionCard,
	ServiceList,
	ServiceRow,
	SettingsBlock,
	SettingsGroup,
	Switch,
	Tile,
	TileGrid,
} from '@kits/shell'; // eslint-disable-line import/no-unresolved -- Kits Core's shell script (window.kits.shell), mapped in webpack.config.js

const app = window.planwebApp || {};
const lists = app.lists || {};
const path = ( p ) => `/${ app.rest }/${ p }`;
const errText = ( e ) => ( e && e.message ) || String( e );

/** Header with the plugin's tabs and the way back to the current screen. */
function Header() {
	return (
		<AppHeader
			icon={ app.icon }
			name={ app.name }
			version={ app.version }
			tabs={ app.tabs }
		/>
	);
}

/* ---------- Dashboard ---------- */

function Dashboard() {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ range, setRange ] = useState( 30 );
	const [ note, setNote ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ tries, setTries ] = useState( 0 );

	const load = () => {
		setError( '' );
		apiFetch( { path: path( 'dashboard' ) } )
			.then( setData )
			.catch( ( e ) => setError( errText( e ) ) );
	};
	useEffect( load, [] );
	// While a count runs in the background, look again every 20 seconds (a read; nothing is counted).
	const waiting = !! data && ( data.counting || data.stale );
	useEffect( () => {
		if ( ! waiting || tries >= 15 ) {
			return;
		}
		const t = setTimeout( () => {
			setTries( tries + 1 );
			load();
		}, 20000 );
		return () => clearTimeout( t );
	}, [ waiting, data, tries ] );

	const countNow = () => {
		setBusy( true );
		apiFetch( { path: path( 'dashboard/count' ), method: 'POST' } )
			.then( () => {
				setTries( 0 );
				setNote(
					__(
						'A count is running in the background. The numbers change when it is done.',
						'planweb'
					)
				);
				setTimeout( load, 20000 );
			} )
			.catch( ( e ) => setNote( errText( e ) ) )
			.finally( () => setBusy( false ) );
	};

	if ( error ) {
		return <ErrorState message={ error } onRetry={ load } />;
	}
	if ( ! data ) {
		return (
			<LoadingState label={ __( 'Loading the counts…', 'planweb' ) } />
		);
	}
	const tiles = data.tiles[ range ] || [];
	const byType = data.byType[ range ] || [];
	let after = data.atText
		? sprintf(
				/* translators: %s: date and time. */
				__( 'Counted %s.', 'planweb' ),
				data.atText
			)
		: __( 'Counting for the first time…', 'planweb' );
	if ( data.stale ) {
		after += ' ' + __( 'A new count for today is running.', 'planweb' );
	}
	return (
		<>
			<ChipRow
				label={ __( 'Time range', 'planweb' ) }
				value={ range }
				onChange={ setRange }
				chips={ ( data.ranges || [] ).map( ( n ) => ( {
					value: n,
					/* translators: %d: number of days. */
					label: sprintf( __( 'Next %d days', 'planweb' ), n ),
				} ) ) }
				after={
					<>
						{ after }{ ' ' }
						<Button
							variant="link"
							busy={ busy }
							onClick={ countNow }
						>
							{ __( 'Count now', 'planweb' ) }
						</Button>
					</>
				}
			/>
			{ note && (
				<Notice tone="info" onDismiss={ () => setNote( '' ) }>
					{ note }
				</Notice>
			) }
			<TileGrid label={ __( 'PlanWeb at a glance', 'planweb' ) }>
				{ tiles.map( ( t ) => (
					<Tile
						key={ t.key }
						label={ t.label }
						value={ t.value }
						sub={ t.sub }
						href={ t.href }
						tone={ t.tone }
						loading={ data.counting }
					/>
				) ) }
			</TileGrid>
			{ ! data.counting && (
				<>
					<SectionCard
						id="by-type"
						title={ sprintf(
							/* translators: %d: number of days. */
							__( 'By type, next %d days', 'planweb' ),
							range
						) }
						description={ __(
							'Entries from today on, by type. Each number opens the list.',
							'planweb'
						) }
					>
						<RefTable
							caption={ __( 'By type', 'planweb' ) }
							head={ [
								__( 'Type', 'planweb' ),
								__( 'Entries', 'planweb' ),
							] }
							rows={ byType.map( ( r ) => [
								r.label,
								<a key="a" href={ r.href }>
									{ r.count.toLocaleString() }
								</a>,
							] ) }
						/>
					</SectionCard>
					<SectionCard
						id="by-status"
						title={ __( 'By status', 'planweb' ) }
						description={ __(
							'Every entry, past and future. Approved and Sent are set by an administrator.',
							'planweb'
						) }
					>
						<RefTable
							caption={ __( 'By status', 'planweb' ) }
							head={ [
								__( 'Status', 'planweb' ),
								__( 'Entries', 'planweb' ),
							] }
							rows={ data.byStatus.map( ( r ) => [
								r.label,
								<a key="a" href={ r.href }>
									{ r.count.toLocaleString() }
								</a>,
							] ) }
						/>
					</SectionCard>
				</>
			) }
		</>
	);
}

/* ---------- Entries ---------- */

const FILTER_KEYS = [
	'search',
	'type',
	'channel',
	'owner',
	'status',
	'view',
	'when',
	'sort',
	'dir',
	'page',
];

/* The address keeps the list's page as "paged": "page" there is the admin screen (page=planweb). */
const urlKey = ( k ) => ( 'page' === k ? 'paged' : k );

/** The list's filters from the address (so a Dashboard tile opens the right list). */
function argsFromUrl() {
	const q = new URLSearchParams( window.location.search );
	const a = {};
	FILTER_KEYS.forEach( ( k ) => {
		if ( q.get( urlKey( k ) ) ) {
			a[ k ] = q.get( urlKey( k ) );
		}
	} );
	return a;
}

/**
 * Keep the address in step with the list, so a reload or a shared link shows the same rows.
 *
 * @param {Object} a The list's arguments.
 */
function argsToUrl( a ) {
	const u = new URL( window.location.href );
	FILTER_KEYS.forEach( ( k ) => u.searchParams.delete( urlKey( k ) ) );
	Object.entries( a ).forEach( ( [ k, v ] ) => {
		if ( v !== '' && v !== undefined && v !== null ) {
			u.searchParams.set( urlKey( k ), String( v ) );
		}
	} );
	window.history.replaceState( null, '', u.toString() );
}

const query = ( a ) =>
	Object.entries( a )
		.filter( ( [ , v ] ) => v !== '' && v !== undefined && v !== null )
		.map(
			( [ k, v ] ) =>
				`${ encodeURIComponent( k ) }=${ encodeURIComponent( v ) }`
		)
		.join( '&' );

const BLANK = {
	id: 0,
	date: '',
	time: '',
	title: '',
	note: '',
	type: 'reminder',
	channel: '',
	owner: '',
	status: 'idea',
	link: '',
};

/**
 * One field of the entry form.
 *
 * @param {Object}   props
 * @param {string}   props.label  Label.
 * @param {string}   props.help   Help line.
 * @param {Function} props.render ( id, helpId ) => control.
 */
function FormField( { label, help, render } ) {
	return <Field label={ label } help={ help } render={ render } />;
}

/**
 * A select over one of the fixed lists.
 *
 * @param {Object}   props
 * @param {string}   props.id       Element id.
 * @param {string}   props.helpId   Help id.
 * @param {Array}    props.options  [ { value, label } ].
 * @param {string}   props.value    Value.
 * @param {Function} props.onChange New value.
 * @param {string}   props.none     Label of the empty choice ('' for none).
 * @param {Array}    props.off      Values shown but not selectable.
 */
function Select( {
	id,
	helpId,
	options,
	value,
	onChange,
	none = '',
	off = [],
} ) {
	return (
		<select
			id={ id }
			value={ value }
			aria-describedby={ helpId }
			onChange={ ( e ) => onChange( e.target.value ) }
		>
			{ none && <option value="">{ none }</option> }
			{ options.map( ( o ) => (
				<option
					key={ o.value }
					value={ o.value }
					disabled={ off.includes( o.value ) && o.value !== value }
				>
					{ o.label }
				</option>
			) ) }
		</select>
	);
}

/**
 * Add or change one entry. Saved through the same function as the Calendar's form, with the same checks.
 *
 * @param {Object}   props
 * @param {Object}   props.entry   Entry being changed, or BLANK.
 * @param {Function} props.onSaved Called after a save.
 * @param {Function} props.onClose Close the form.
 */
function EntryForm( { entry, onSaved, onClose } ) {
	const [ f, setF ] = useState( () => ( { ...BLANK, ...entry } ) );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	useEffect( () => setF( { ...BLANK, ...entry } ), [ entry ] );
	const set = ( k ) => ( v ) => setF( ( o ) => ( { ...o, [ k ]: v } ) );
	const text = ( k, type = 'text', extra = {} ) =>
		function Control( id, helpId ) {
			return (
				<input
					id={ id }
					type={ type }
					value={ f[ k ] }
					aria-describedby={ helpId }
					onChange={ ( e ) => set( k )( e.target.value ) }
					{ ...extra }
				/>
			);
		};
	const off = [ 'due', ...( app.canApprove ? [] : app.approveOnly || [] ) ];
	const save = () => {
		setBusy( true );
		setError( '' );
		const { date, time, title, note, type, channel, owner, status, link } =
			f;
		apiFetch( {
			path: path( f.id ? `entries/${ f.id }` : 'entries' ),
			method: 'POST',
			data: {
				date,
				time,
				title,
				note,
				type,
				channel,
				owner,
				status,
				link,
			},
		} )
			.then( ( r ) => onSaved( r ) )
			.catch( ( e ) => setError( errText( e ) ) )
			.finally( () => setBusy( false ) );
	};
	return (
		<SectionCard
			id="planweb-form"
			title={
				f.id
					? __( 'Edit entry', 'planweb' )
					: __( 'Add an entry', 'planweb' )
			}
			description={ __(
				'Nothing on this calendar is posted or sent: it is the team’s plan.',
				'planweb'
			) }
		>
			{ error && (
				<Notice tone="error" onDismiss={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			<form
				onSubmit={ ( e ) => {
					e.preventDefault();
					save();
				} }
			>
				<FormField
					label={ __( 'Date', 'planweb' ) }
					render={ text( 'date', 'date', { required: true } ) }
				/>
				<FormField
					label={ __( 'Time', 'planweb' ) }
					help={ __( 'Optional (IST).', 'planweb' ) }
					render={ text( 'time', 'time' ) }
				/>
				<FormField
					label={ __( 'Title', 'planweb' ) }
					render={ text( 'title', 'text', {
						required: true,
						maxLength: 120,
					} ) }
				/>
				<FormField
					label={ __( 'Note', 'planweb' ) }
					help={ __(
						'No email addresses, names or phone numbers.',
						'planweb'
					) }
					render={ ( id, helpId ) => (
						<textarea
							id={ id }
							rows={ 3 }
							value={ f.note }
							aria-describedby={ helpId }
							onChange={ ( e ) =>
								set( 'note' )( e.target.value )
							}
						/>
					) }
				/>
				<FormField
					label={ __( 'Type', 'planweb' ) }
					render={ ( id, helpId ) => (
						<Select
							id={ id }
							helpId={ helpId }
							options={ lists.types || [] }
							value={ f.type }
							onChange={ set( 'type' ) }
						/>
					) }
				/>
				<FormField
					label={ __( 'Channel', 'planweb' ) }
					render={ ( id, helpId ) => (
						<Select
							id={ id }
							helpId={ helpId }
							options={ lists.channels || [] }
							value={ f.channel }
							onChange={ set( 'channel' ) }
							none="—"
						/>
					) }
				/>
				<FormField
					label={ __( 'Owner', 'planweb' ) }
					render={ ( id, helpId ) => (
						<Select
							id={ id }
							helpId={ helpId }
							options={ lists.owners || [] }
							value={ f.owner }
							onChange={ set( 'owner' ) }
							none="—"
						/>
					) }
				/>
				<FormField
					label={ __( 'Status', 'planweb' ) }
					help={
						app.canApprove
							? __(
									'Due is set on the day by the scheduler.',
									'planweb'
								)
							: __(
									'Approved and Sent are set by an administrator.',
									'planweb'
								)
					}
					render={ ( id, helpId ) => (
						<Select
							id={ id }
							helpId={ helpId }
							options={ lists.statuses || [] }
							value={ f.status }
							onChange={ set( 'status' ) }
							off={ off }
						/>
					) }
				/>
				<FormField
					label={ __( 'Link', 'planweb' ) }
					help={ __(
						'A page on this site, or an X, LinkedIn, Instagram, Facebook, Threads, Reddit or WhatsApp channel address.',
						'planweb'
					) }
					render={ text( 'link', 'url' ) }
				/>
				<p>
					<Button variant="primary" type="submit" busy={ busy }>
						{ f.id
							? __( 'Save entry', 'planweb' )
							: __( 'Add entry', 'planweb' ) }
					</Button>{ ' ' }
					<Button type="button" onClick={ onClose }>
						{ __( 'Cancel', 'planweb' ) }
					</Button>
				</p>
			</form>
		</SectionCard>
	);
}

const WHEN = () => [
	{ value: 'from30', label: __( 'From 30 days ago', 'planweb' ) },
	{ value: 'next7', label: __( 'Next 7 days', 'planweb' ) },
	{ value: 'next30', label: __( 'Next 30 days', 'planweb' ) },
	{ value: 'next90', label: __( 'Next 90 days', 'planweb' ) },
	{ value: 'past', label: __( 'Before today', 'planweb' ) },
	{ value: 'all', label: __( 'All dates', 'planweb' ) },
];

function Entries() {
	const [ args, setArgs ] = useState( argsFromUrl );
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ editing, setEditing ] = useState( null );
	const [ msg, setMsg ] = useState( null );

	const latest = useRef( 0 );
	const load = useCallback( () => {
		const ticket = ++latest.current;
		const current = () => ticket === latest.current;
		setLoading( true );
		setError( '' );
		apiFetch( { path: `${ path( 'entries' ) }?${ query( args ) }` } )
			.then( ( r ) => current() && setData( r ) )
			.catch( ( e ) => current() && setError( errText( e ) ) )
			.finally( () => current() && setLoading( false ) );
	}, [ args ] );
	useEffect( () => {
		argsToUrl( args );
		load();
	}, [ args, load ] );

	// An entry's own address (&edit=ID, as the Calendar's) opens its form; the address follows the open form.
	useEffect( () => {
		const id = parseInt(
			new URLSearchParams( window.location.search ).get( 'edit' ),
			10
		);
		if ( id > 0 ) {
			apiFetch( { path: path( `entries/${ id }` ) } )
				.then( ( r ) => setEditing( r.entry ) )
				.catch( ( e ) =>
					setMsg( { tone: 'error', text: errText( e ) } )
				);
		}
	}, [] );
	useEffect( () => {
		const u = new URL( window.location.href );
		if ( editing && editing.id ) {
			u.searchParams.set( 'edit', String( editing.id ) );
		} else {
			u.searchParams.delete( 'edit' );
		}
		window.history.replaceState( null, '', u.toString() );
	}, [ editing ] );

	const change = ( k, v ) =>
		setArgs( ( a ) => ( { ...a, [ k ]: v, page: 'page' === k ? v : '' } ) );
	const trash = ( row ) => {
		if (
			// eslint-disable-next-line no-alert -- a plain browser confirm before the Trash, as core's lists do
			! window.confirm( __( 'Move this entry to the Trash?', 'planweb' ) )
		) {
			return;
		}
		apiFetch( {
			path: path( `entries/${ row.id }/trash` ),
			method: 'POST',
		} )
			.then( ( r ) => {
				setMsg( { tone: 'success', text: r.message } );
				load();
			} )
			.catch( ( e ) => setMsg( { tone: 'error', text: errText( e ) } ) );
	};
	const a = data ? data.args : {};
	const opts = ( list, all ) => [
		{ value: '', label: all },
		...( list || [] ),
	];
	const columns = [
		{
			key: 'date',
			label: __( 'Date', 'planweb' ),
			sortable: true,
			render: ( r ) => (
				<span className={ r.past ? 'planweb-past' : undefined }>
					{ r.dateText }
					{ r.time ? `, ${ r.time }` : '' }
					<br />
					<span className="kits-shell-field__help">
						{ r.whenText }
					</span>
				</span>
			),
		},
		{
			key: 'title',
			label: __( 'What', 'planweb' ),
			sortable: true,
			primary: true,
			render: ( r ) => (
				<>
					<strong>{ r.title }</strong>
					{ r.overdue && (
						<>
							{ ' · ' }
							<strong>{ __( 'Overdue', 'planweb' ) }</strong>
						</>
					) }
					{ r.note && (
						<>
							<br />
							<span className="kits-shell-field__help">
								{ r.note }
							</span>
						</>
					) }
				</>
			),
		},
		{
			key: 'typeText',
			label: __( 'Type', 'planweb' ),
			// The Calendar's badges (assets/admin.css): each Type has its own mark shape as well as its colour.
			render: ( r ) =>
				r.typeText ? (
					<span
						className={ `planweb-type planweb-type--${ r.type }` }
					>
						<span className="planweb-mark" aria-hidden="true" />
						{ r.typeText }
					</span>
				) : (
					'—'
				),
		},
		{
			key: 'channelText',
			label: __( 'Channel', 'planweb' ),
			render: ( r ) => r.channelText || '—',
		},
		{
			key: 'ownerText',
			label: __( 'Owner', 'planweb' ),
			render: ( r ) => r.ownerText || '—',
		},
		{
			key: 'statusText',
			label: __( 'Status', 'planweb' ),
			render: ( r ) => (
				<span
					className={ `planweb-status planweb-status--${ r.status }` }
				>
					{ r.statusText }
				</span>
			),
		},
		{
			key: 'link',
			label: __( 'Link', 'planweb' ),
			render: ( r ) =>
				r.link ? <a href={ r.link }>{ r.linkHost }</a> : '—',
		},
	];
	return (
		<>
			{ msg && (
				<Notice tone={ msg.tone } onDismiss={ () => setMsg( null ) }>
					{ msg.text }
				</Notice>
			) }
			{ editing ? (
				<EntryForm
					entry={ editing }
					onClose={ () => setEditing( null ) }
					onSaved={ ( r ) => {
						setEditing( null );
						setMsg( { tone: 'success', text: r.message } );
						load();
					} }
				/>
			) : (
				<p>
					<Button
						variant="primary"
						onClick={ () => setEditing( { ...BLANK } ) }
					>
						{ __( 'Add an entry', 'planweb' ) }
					</Button>
				</p>
			) }
			<SectionCard
				id="entries"
				title={ __( 'Entries', 'planweb' ) }
				description={ __(
					'The team’s plan. From 30 days ago by default; pick All dates for older ones.',
					'planweb'
				) }
			>
				<DataList
					label={ __( 'Entries', 'planweb' ) }
					columns={ columns }
					rows={ data ? data.rows : [] }
					total={ data ? data.total : 0 }
					page={ data ? data.page : 1 }
					pages={ data ? data.pages : 1 }
					onPage={ ( p ) => change( 'page', p ) }
					search={ a.search || '' }
					onSearch={ ( w ) => change( 'search', w ) }
					searchLabel={ __( 'Search titles and notes', 'planweb' ) }
					views={ data ? data.views : [] }
					view={ a.view || 'all' }
					onView={ ( v ) => change( 'view', v ) }
					filters={ [
						{
							key: 'type',
							label: __( 'Type', 'planweb' ),
							options: opts(
								lists.types,
								__( 'All types', 'planweb' )
							),
							value: a.type || '',
						},
						{
							key: 'status',
							label: __( 'Status', 'planweb' ),
							options: opts(
								lists.statuses,
								__( 'All statuses', 'planweb' )
							),
							value: a.status || '',
						},
						{
							key: 'when',
							label: __( 'Dates', 'planweb' ),
							options: WHEN(),
							value: a.when || 'from30',
						},
						{
							key: 'channel',
							label: __( 'Channel', 'planweb' ),
							options: opts(
								lists.channels,
								__( 'All channels', 'planweb' )
							),
							value: a.channel || '',
						},
						{
							key: 'owner',
							label: __( 'Owner', 'planweb' ),
							options: opts(
								lists.owners,
								__( 'All owners', 'planweb' )
							),
							value: a.owner || '',
						},
					] }
					onFilter={ change }
					sort={ { key: a.sort || 'date', dir: a.dir || 'asc' } }
					onSort={ ( key, dir ) =>
						setArgs( ( o ) => ( {
							...o,
							sort: key,
							dir,
							page: '',
						} ) )
					}
					rowActions={ ( r ) =>
						[
							r.canEdit && {
								label: __( 'Edit', 'planweb' ),
								onClick: () => {
									setEditing( r );
									window.scrollTo( 0, 0 );
								},
							},
							r.canTrash && {
								label: __( 'Trash', 'planweb' ),
								tone: 'error',
								onClick: () => trash( r ),
							},
						].filter( Boolean )
					}
					loading={ loading }
					error={ error }
					onRetry={ load }
					emptyTitle={ __( 'No entries match.', 'planweb' ) }
				/>
			</SectionCard>
		</>
	);
}

/* ---------- Settings ---------- */

const GROUPS = () => [
	{ value: 'services', label: __( 'Connected services', 'planweb' ) },
	{ value: 'switch', label: __( 'PlanWeb', 'planweb' ) },
	{ value: 'lists', label: __( 'Lists', 'planweb' ) },
	{ value: 'import', label: __( 'Import from Dates', 'planweb' ) },
];

function hashGroup() {
	const h = window.location.hash.replace( '#', '' );
	return GROUPS().some( ( g ) => g.value === h ) ? h : 'services';
}

function Services() {
	const [ list, setList ] = useState( null );
	const [ error, setError ] = useState( '' );
	const load = () => {
		setError( '' );
		apiFetch( { path: path( 'services' ) } )
			.then( ( r ) => setList( r.services ) )
			.catch( ( e ) => setError( errText( e ) ) );
	};
	useEffect( load, [] );
	if ( error ) {
		return <ErrorState message={ error } onRetry={ load } />;
	}
	if ( ! list ) {
		return <LoadingState />;
	}
	return (
		<SectionCard
			id="planweb-group-services"
			title={ __( 'Connected services', 'planweb' ) }
			description={ __(
				'What PlanWeb will read or work with. Nothing is posted or sent from PlanWeb.',
				'planweb'
			) }
		>
			<ServiceList label={ __( 'Connected services', 'planweb' ) }>
				{ list.map( ( s ) => (
					<ServiceRow
						key={ s.slug }
						name={ s.name }
						status={ s.status }
						tone={ s.tone }
						summary={ s.summary }
					/>
				) ) }
			</ServiceList>
		</SectionCard>
	);
}

/**
 * The PlanWeb switch, as Kits → Modules has it. Saved only when it changed.
 *
 * @param {Object}   props
 * @param {Object}   props.group   { on }.
 * @param {Function} props.onSaved New state.
 */
function SwitchGroup( { group, onSaved } ) {
	const [ on, setOn ] = useState( group.on );
	const [ busy, setBusy ] = useState( false );
	const [ msg, setMsg ] = useState( null );
	useEffect( () => setOn( group.on ), [ group.on ] );
	const save = () => {
		if (
			! on &&
			// eslint-disable-next-line no-alert -- a plain browser confirm before switching the plugin off
			! window.confirm(
				__(
					'Switch PlanWeb off? Kits → Dates comes back and these screens go; the entries are kept.',
					'planweb'
				)
			)
		) {
			return;
		}
		setBusy( true );
		setMsg( null );
		apiFetch( {
			path: path( 'settings/switch' ),
			method: 'POST',
			data: { on },
		} )
			.then( ( r ) => {
				onSaved( { on: r.on } );
				setMsg( { tone: 'success', text: r.message } );
			} )
			.catch( ( e ) => setMsg( { tone: 'error', text: errText( e ) } ) )
			.finally( () => setBusy( false ) );
	};
	return (
		<SettingsGroup
			id="planweb-group-switch"
			title={ __( 'PlanWeb', 'planweb' ) }
			description={ __(
				'The same switch as Kits → Modules → PlanWeb.',
				'planweb'
			) }
			footer={
				<>
					<Button
						variant="primary"
						busy={ busy }
						disabled={ on === group.on }
						onClick={ save }
					>
						{ __( 'Save changes', 'planweb' ) }
					</Button>
					<span aria-live="polite">
						{ msg && 'success' === msg.tone ? msg.text : '' }
					</span>
				</>
			}
		>
			{ msg && 'error' === msg.tone && (
				<Notice tone="error" onDismiss={ () => setMsg( null ) }>
					{ msg.text }
				</Notice>
			) }
			<Switch
				label={ __( 'Calendar in PlanWeb', 'planweb' ) }
				checked={ on }
				onChange={ setOn }
				help={ __(
					'On: Kits → Dates moves to PlanWeb, and every date in Dates is copied here once (Dates itself is kept unchanged). Off: Kits → Dates as before; entries are kept.',
					'planweb'
				) }
			/>
		</SettingsGroup>
	);
}

/**
 * The fixed lists, read only, with how many entries use each value.
 *
 * @param {Object} props
 * @param {Array}  props.lists [ { key, label, values: [ { slug, label, count } ] } ].
 */
function ListsGroup( { lists: groups } ) {
	return (
		<SettingsGroup
			id="planweb-group-lists"
			title={ __( 'Lists', 'planweb' ) }
			description={ __(
				'The values entries can take. They are fixed for now; renaming and adding come later.',
				'planweb'
			) }
		>
			{ groups.map( ( l ) => (
				<SettingsBlock key={ l.key } title={ l.label }>
					<RefTable
						caption={ l.label }
						head={ [
							l.label,
							__( 'Entries using it', 'planweb' ),
						] }
						rows={ l.values.map( ( v ) => [
							v.label,
							v.count.toLocaleString(),
						] ) }
					/>
				</SettingsBlock>
			) ) }
		</SettingsGroup>
	);
}

/**
 * Import from Dates: what the import did, the one-for-one check, and Run the import again.
 *
 * @param {Object}   props
 * @param {Object}   props.group   The import payload.
 * @param {Function} props.onSaved New payload.
 */
function ImportGroup( { group, onSaved } ) {
	const [ busy, setBusy ] = useState( false );
	const [ msg, setMsg ] = useState( null );
	const c = group.check;
	const bad = c.differs + c.missing + c.extra;
	const run = () => {
		setBusy( true );
		setMsg( null );
		apiFetch( { path: path( 'import' ), method: 'POST' } )
			.then( ( r ) => {
				onSaved( r.import );
				setMsg( { tone: 'success', text: r.message } );
			} )
			.catch( ( e ) => setMsg( { tone: 'error', text: errText( e ) } ) )
			.finally( () => setBusy( false ) );
	};
	const side = ( x ) =>
		x
			? `${ x.date } · ${ x.title }${ x.note ? ` (${ x.note })` : '' }`
			: '—';
	return (
		<SettingsGroup
			id="planweb-group-import"
			title={ __( 'Import from Dates', 'planweb' ) }
			description={ __(
				'Every date in Dates copied here once. Dates itself is never changed.',
				'planweb'
			) }
			footer={
				<>
					<Button busy={ busy } onClick={ run }>
						{ __( 'Run the import again', 'planweb' ) }
					</Button>
					<span className="kits-shell-field__help">
						{ __( 'Adds only dates not imported yet.', 'planweb' ) }
					</span>
					<span aria-live="polite">
						{ msg && 'success' === msg.tone ? msg.text : '' }
					</span>
				</>
			}
		>
			{ msg && 'error' === msg.tone && (
				<Notice tone="error" onDismiss={ () => setMsg( null ) }>
					{ msg.text }
				</Notice>
			) }
			<p>
				{ group.ran
					? sprintf(
							/* translators: 1: date and time, 2: dates in Dates, 3: entries made, 4: already there. */
							__(
								'Last run %1$s: %2$d dates in Dates, %3$d entries made, %4$d already there.',
								'planweb'
							),
							group.atText,
							group.stored,
							group.created,
							group.skipped
						)
					: __(
							'Not run yet: it runs on the first admin page after PlanWeb is switched on.',
							'planweb'
						) }
			</p>
			{ group.failed.map( ( f ) => (
				<Notice key={ f.index } tone="error">
					{ sprintf(
						/* translators: 1: position in the Dates list, 2: error. */
						__( 'Date %1$d was not imported: %2$s', 'planweb' ),
						f.index + 1,
						f.error
					) }
				</Notice>
			) ) }
			{ group.personal.length > 0 && (
				<Notice tone="warning">
					{ sprintf(
						/* translators: %s: entry IDs. */
						__(
							'These imported entries look as if they hold an email address or a phone number; check them: %s',
							'planweb'
						),
						group.personal.join( ', ' )
					) }
				</Notice>
			) }
			<Notice tone={ bad ? 'warning' : 'success' }>
				{ sprintf(
					/* translators: 1: same, 2: differs, 3: missing, 4: extra. */
					__(
						'Check now: %1$d the same, %2$d different, %3$d missing, %4$d extra.',
						'planweb'
					),
					c.same,
					c.differs,
					c.missing,
					c.extra
				) }
			</Notice>
			{ group.invalid.map( ( x ) => (
				<p key={ x.index }>
					{ sprintf(
						/* translators: 1: position in the Dates list, 2: the stored value. */
						__(
							'Dates element %1$d is not a date and was not imported: %2$s',
							'planweb'
						),
						x.index + 1,
						x.value
					) }
				</p>
			) ) }
			{ group.odd.length > 0 && (
				<RefTable
					caption={ __( 'Dates that do not match', 'planweb' ) }
					head={ [
						__( 'Check', 'planweb' ),
						__( 'In Dates', 'planweb' ),
						__( 'In PlanWeb', 'planweb' ),
					] }
					rows={ group.odd.map( ( l ) => [
						l.state,
						side( l.dates ),
						side( l.entry ),
					] ) }
				/>
			) }
		</SettingsGroup>
	);
}

function Settings() {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ open, setOpen ] = useState( hashGroup );
	const load = () => {
		setError( '' );
		apiFetch( { path: path( 'settings' ) } )
			.then( setData )
			.catch( ( e ) => setError( errText( e ) ) );
	};
	useEffect( load, [] );
	useEffect( () => {
		const onHash = () => setOpen( hashGroup() );
		window.addEventListener( 'hashchange', onHash );
		return () => window.removeEventListener( 'hashchange', onHash );
	}, [] );
	if ( error ) {
		return <ErrorState message={ error } onRetry={ load } />;
	}
	if ( ! data ) {
		return (
			<LoadingState label={ __( 'Loading the settings…', 'planweb' ) } />
		);
	}
	const put = ( k ) => ( v ) => setData( ( d ) => ( { ...d, [ k ]: v } ) );
	return (
		<>
			<ChipRow
				label={ __( 'Settings groups', 'planweb' ) }
				chips={ GROUPS().map( ( g ) => ( {
					...g,
					href: `#${ g.value }`,
				} ) ) }
				value={ open }
				onChange={ setOpen }
			/>
			{ 'services' === open && <Services /> }
			{ 'switch' === open && (
				<SwitchGroup
					group={ data.switch }
					onSaved={ put( 'switch' ) }
				/>
			) }
			{ 'lists' === open && <ListsGroup lists={ data.lists } /> }
			{ 'import' === open && (
				<ImportGroup
					group={ data.import }
					onSaved={ put( 'import' ) }
				/>
			) }
			{ ! GROUPS().some( ( g ) => g.value === open ) && (
				<EmptyState title={ __( 'No such group.', 'planweb' ) } />
			) }
		</>
	);
}

function App() {
	let screen = <Dashboard />;
	if ( 'entries' === app.screen ) {
		screen = <Entries />;
	} else if ( 'settings' === app.screen ) {
		screen = <Settings />;
	}
	return <AppShell header={ <Header /> }>{ screen }</AppShell>;
}

const root = document.getElementById( 'planweb-app' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
