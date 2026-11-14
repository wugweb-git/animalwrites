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
	CalendarGrid,
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
	Select,
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
	const [ asked, setAsked ] = useState( false );

	const load = () => {
		setError( '' );
		apiFetch( { path: path( 'dashboard' ) } )
			.then( setData )
			.catch( ( e ) => setError( errText( e ) ) );
	};
	useEffect( load, [] );
	// Opening the Dashboard writes nothing. After "Count now", look again every 20 seconds while the counts are old (a
	// read; nothing is counted here).
	const waiting = asked && !! data && ( data.counting || data.stale );
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
				setAsked( true );
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
		: __(
				'Not counted yet: the hourly count makes them, or press Count now.',
				'planweb'
			);
	if ( data.stale ) {
		after +=
			' ' +
			__(
				'These are from an earlier day: the hourly count brings them up to date, or press Count now.',
				'planweb'
			);
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
			<Month />
			<SiteSchedule items={ data.site || [] } />
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

/* ---------- The site's own schedule ---------- */

/**
 * One site item (a post or a newsletter) as a line: its kind, time, title (opening it in its own editor) and status.
 * Dates are changed there, never here: PlanWeb runs no second scheduler.
 *
 * @param {Object} props
 * @param {Object} props.i planweb_site_items() row.
 */
function SiteItem( { i } ) {
	return (
		<>
			<span className="planweb-type planweb-type--site">
				<span className="planweb-mark" aria-hidden="true" />
				{ i.typeText }
			</span>{ ' ' }
			{ i.editUrl ? (
				<a href={ i.editUrl }>
					{ i.time ? `${ i.time } ` : '' }
					{ i.title }
				</a>
			) : (
				<span>
					{ i.time ? `${ i.time } ` : '' }
					{ i.title }
				</span>
			) }{ ' ' }
			<span className={ `planweb-status planweb-status--${ i.status }` }>
				{ i.statusText }
			</span>
		</>
	);
}

/**
 * The site's own schedule on the Dashboard: posts that missed their publish time, and what is scheduled in the next 30
 * days (posts and newsletters). Read live from WordPress and FluentCRM.
 *
 * @param {Object} props
 * @param {Array}  props.items Site items.
 */
function SiteSchedule( { items } ) {
	return (
		<SectionCard
			id="site-schedule"
			title={ __( 'Site schedule', 'planweb' ) }
			description={ __(
				'Scheduled posts and newsletters in the next 30 days, and posts that missed their publish time, straight from WordPress and FluentCRM. Change a date in the post or the newsletter itself.',
				'planweb'
			) }
		>
			{ items.length ? (
				<RefTable
					caption={ __( 'Site schedule', 'planweb' ) }
					head={ [
						__( 'Date', 'planweb' ),
						__( 'Item', 'planweb' ),
					] }
					rows={ items.map( ( i ) => [
						i.date,
						<SiteItem key={ i.uid } i={ i } />,
					] ) }
				/>
			) : (
				<p>
					{ __(
						'Nothing scheduled on the site in the next 30 days, and nothing missed.',
						'planweb'
					) }
				</p>
			) }
		</SectionCard>
	);
}

/* ---------- Month ---------- */

/**
 * The Calendar's month grid: each entry with its Type badge, time, title (opening its form on Entries) and Status.
 * The month shown is kept in the address as "month" ("m" is the Calendar's own address).
 */
function Month() {
	const [ month, setMonth ] = useState(
		() => new URLSearchParams( window.location.search ).get( 'month' ) || ''
	);
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ loading, setLoading ] = useState( true );
	const load = useCallback( () => {
		setLoading( true );
		setError( '' );
		apiFetch( {
			path: `${ path( 'month' ) }${
				month ? `?m=${ encodeURIComponent( month ) }` : ''
			}`,
		} )
			.then( setData )
			.catch( ( e ) => setError( errText( e ) ) )
			.finally( () => setLoading( false ) );
	}, [ month ] );
	useEffect( load, [ load ] );
	const go = ( m ) => {
		const u = new URL( window.location.href );
		if ( data && m === data.today.slice( 0, 7 ) ) {
			u.searchParams.delete( 'month' );
		} else {
			u.searchParams.set( 'month', m );
		}
		window.history.replaceState( null, '', u.toString() );
		setMonth( m );
	};
	let body;
	if ( error ) {
		body = <ErrorState message={ error } onRetry={ load } />;
	} else if ( ! data ) {
		body = <LoadingState label={ __( 'Loading the month…', 'planweb' ) } />;
	} else {
		const days = {};
		data.entries.forEach( ( e ) => {
			( days[ e.date ] = days[ e.date ] || [] ).push(
				<p key={ e.id } className="planweb-item">
					<span
						className={ `planweb-type planweb-type--${ e.type }` }
					>
						<span className="planweb-mark" aria-hidden="true" />
						{ e.typeText }
					</span>{ ' ' }
					<a href={ e.editUrl }>
						{ e.time ? `${ e.time } ` : '' }
						{ e.title }
					</a>{ ' ' }
					<span
						className={ `planweb-status planweb-status--${ e.status }` }
					>
						{ e.statusText }
					</span>
				</p>
			);
		} );
		// The site's own schedule in the same days: posts (published, scheduled, missed) and newsletters.
		( data.site || [] ).forEach( ( i ) => {
			( days[ i.date ] = days[ i.date ] || [] ).push(
				<p key={ i.uid } className="planweb-item planweb-item--site">
					<SiteItem i={ i } />
				</p>
			);
		} );
		body = (
			<CalendarGrid
				month={ data.month }
				today={ data.today }
				days={ days }
				onMonth={ go }
				loading={ loading }
			/>
		);
	}
	return (
		<SectionCard
			id="calendar"
			title={ __( 'Calendar', 'planweb' ) }
			description={ __(
				'The month’s entries by day, with the site’s own posts and newsletters (outlined mark). An entry opens its form on Entries; a post or newsletter opens in its own editor.',
				'planweb'
			) }
		>
			{ body }
		</SectionCard>
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
	channels: [],
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
		const { date, time, title, note, type, channels, owner, status, link } =
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
				channels,
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
							aria-describedby={ helpId }
							options={ lists.types || [] }
							value={ f.type }
							onChange={ set( 'type' ) }
						/>
					) }
				/>
				<fieldset className="kits-shell-field planweb-channels">
					<legend>{ __( 'Channels', 'planweb' ) }</legend>
					{ ( lists.channels || [] ).map( ( o ) => (
						<label
							key={ o.value }
							className="planweb-channel-box"
							htmlFor={ `planweb-channel-${ o.value }` }
						>
							<input
								id={ `planweb-channel-${ o.value }` }
								type="checkbox"
								checked={ ( f.channels || [] ).includes(
									o.value
								) }
								onChange={ ( e ) =>
									set( 'channels' )(
										e.target.checked
											? [
													...( f.channels || [] ),
													o.value,
												]
											: ( f.channels || [] ).filter(
													( c ) => c !== o.value
												)
									)
								}
							/>{ ' ' }
							{ o.label }
						</label>
					) ) }
					<p className="kits-shell-field__help">
						{ __(
							'Tick every channel the entry goes out on.',
							'planweb'
						) }
					</p>
				</fieldset>
				<FormField
					label={ __( 'Owner', 'planweb' ) }
					render={ ( id, helpId ) => (
						<Select
							id={ id }
							aria-describedby={ helpId }
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
							aria-describedby={ helpId }
							options={ ( lists.statuses || [] ).map( ( o ) => ( {
								...o,
								disabled:
									off.includes( o.value ) &&
									o.value !== f.status,
							} ) ) }
							value={ f.status }
							onChange={ set( 'status' ) }
						/>
					) }
				/>
				<FormField
					label={ __( 'Link', 'planweb' ) }
					help={ __(
						'A page on this site, or an X, LinkedIn, Instagram, Facebook, Threads, Bluesky, Reddit or WhatsApp channel address.',
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
			label: __( 'Channels', 'planweb' ),
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
								icon: 'edit',
								label: __( 'Edit', 'planweb' ),
								onClick: () => {
									setEditing( r );
									window.scrollTo( 0, 0 );
								},
							},
							r.canTrash && {
								icon: 'delete',
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
	{ value: 'feed', label: __( 'Calendar feed', 'planweb' ) },
	{ value: 'site', label: __( 'Site schedule', 'planweb' ) },
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
				'What PlanWeb will read or work with, and the tools used by hand outside it. Nothing is posted or sent from PlanWeb.',
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
 * The PlanWeb group, as Kits → Modules → PlanWeb has it: the switch and "Delete this plugin's data when it is
 * deleted". Each saved only when it changed; "Reset this section" restores the group's defaults.
 *
 * @param {Object}   props
 * @param {Object}   props.group   { on, deleteData }.
 * @param {Function} props.onSaved New state.
 */
function SwitchGroup( { group, onSaved } ) {
	const [ on, setOn ] = useState( group.on );
	const [ deleteData, setDeleteData ] = useState( !! group.deleteData );
	const [ busy, setBusy ] = useState( false );
	const [ msg, setMsg ] = useState( null );
	useEffect( () => setOn( group.on ), [ group.on ] );
	useEffect(
		() => setDeleteData( !! group.deleteData ),
		[ group.deleteData ]
	);
	const dirty = on !== group.on || deleteData !== !! group.deleteData;
	const send = ( data ) => {
		setBusy( true );
		setMsg( null );
		apiFetch( {
			path: path( 'settings/switch' ),
			method: 'POST',
			data,
		} )
			.then( ( r ) => {
				onSaved( { on: r.on, deleteData: r.deleteData } );
				setMsg( { tone: 'success', text: r.message } );
			} )
			.catch( ( e ) => setMsg( { tone: 'error', text: errText( e ) } ) )
			.finally( () => setBusy( false ) );
	};
	const save = () => {
		if (
			! on &&
			group.on &&
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
		send( { on, delete_data: deleteData } );
	};
	const restore = () => {
		if (
			// eslint-disable-next-line no-alert -- a plain browser confirm before a reset, as core's list tables do
			window.confirm(
				__( 'Reset this section to its defaults?', 'planweb' )
			)
		) {
			send( { reset: true } );
		}
	};
	return (
		<SettingsGroup
			id="planweb-group-switch"
			title={ __( 'PlanWeb', 'planweb' ) }
			description={ __(
				'The same settings as Kits → Modules → PlanWeb.',
				'planweb'
			) }
			footer={
				<>
					<Button
						variant="primary"
						busy={ busy }
						disabled={ ! dirty }
						onClick={ save }
					>
						{ __( 'Save changes', 'planweb' ) }
					</Button>
					<Button busy={ busy } onClick={ restore }>
						{ __( 'Reset this section', 'planweb' ) }
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
			<div className="kits-shell-field">
				<label htmlFor="planweb-delete-data">
					<input
						id="planweb-delete-data"
						type="checkbox"
						checked={ deleteData }
						aria-describedby="planweb-delete-data-help"
						onChange={ ( e ) => setDeleteData( e.target.checked ) }
					/>{ ' ' }
					{ __(
						"Delete this plugin's data when it is deleted",
						'planweb'
					) }
				</label>
				<p
					id="planweb-delete-data-help"
					className="kits-shell-field__help"
				>
					{ __(
						'Off: everything stays in the database if the plugin is deleted.',
						'planweb'
					) }
				</p>
			</div>
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

/**
 * The calendar feed: its addresses (each with the key), "Make a new key" and "Turn the feed off".
 *
 * @param {Object}   props
 * @param {Object}   props.group   { on, urls: [ { label, url } ] }.
 * @param {Function} props.onSaved New payload.
 */
function FeedGroup( { group, onSaved } ) {
	const [ busy, setBusy ] = useState( false );
	const [ msg, setMsg ] = useState( null );
	const send = ( action, ask ) => {
		// eslint-disable-next-line no-alert -- a plain browser confirm before the old address stops working
		if ( ask && ! window.confirm( ask ) ) {
			return;
		}
		setBusy( true );
		setMsg( null );
		apiFetch( {
			path: path( 'settings/feed' ),
			method: 'POST',
			data: { action },
		} )
			.then( ( r ) => {
				onSaved( r.feed );
				setMsg( { tone: 'success', text: r.message } );
			} )
			.catch( ( e ) => setMsg( { tone: 'error', text: errText( e ) } ) )
			.finally( () => setBusy( false ) );
	};
	const again = __(
		'Make a new key? The current address stops working, and calendars subscribed to it stop updating until they subscribe again.',
		'planweb'
	);
	return (
		<SettingsGroup
			id="planweb-group-feed"
			title={ __( 'Calendar feed', 'planweb' ) }
			description={ __(
				'A read-only feed for Google Calendar, Apple Calendar or Outlook: the whole schedule (entries, and the site’s posts and newsletters chosen under Site schedule) from 90 days ago on, with date, time, title, type, channels, status and link. Never the note. Nothing comes back from the calendar app, and nothing is posted.',
				'planweb'
			) }
			footer={
				<>
					<Button
						variant={ group.on ? 'secondary' : 'primary' }
						busy={ busy }
						onClick={ () => send( 'new', group.on ? again : '' ) }
					>
						{ group.on
							? __( 'Make a new key', 'planweb' )
							: __(
									'Make a key and turn the feed on',
									'planweb'
								) }
					</Button>
					{ group.on && (
						<Button
							busy={ busy }
							onClick={ () =>
								send(
									'off',
									__(
										'Turn the feed off? Calendars subscribed to it stop updating.',
										'planweb'
									)
								)
							}
						>
							{ __( 'Turn the feed off', 'planweb' ) }
						</Button>
					) }
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
			{ ! group.on && (
				<p>
					{ __(
						'The feed is off: there is no key, so there is no address.',
						'planweb'
					) }
				</p>
			) }
			{ group.on && (
				<>
					<Notice tone="warning">
						{ __(
							'Each address holds the secret key: anyone with it can read these entries. Share it only with the calendar you subscribe.',
							'planweb'
						) }
					</Notice>
					{ group.urls.map( ( u, i ) => (
						<div className="kits-shell-field" key={ u.url }>
							<label htmlFor={ `planweb-feed-${ i }` }>
								{ u.label }
							</label>
							<input
								id={ `planweb-feed-${ i }` }
								type="text"
								className="large-text code"
								readOnly
								value={ u.url }
								onFocus={ ( e ) => e.target.select() }
							/>
						</div>
					) ) }
					<p className="kits-shell-field__help">
						{ __(
							'Google Calendar: Other calendars → + → From URL. Apple Calendar: File → New Calendar Subscription. Outlook: Add calendar → Subscribe from web. Calendar apps fetch the feed on their own schedule (Google: every few hours).',
							'planweb'
						) }
					</p>
				</>
			) }
		</SettingsGroup>
	);
}

/**
 * Which parts of the site's own schedule show beside the entries (post types and FluentCRM newsletters).
 *
 * @param {Object}   props
 * @param {Object}   props.group   { sources: [ { slug, label, on, bulk } ] }.
 * @param {Function} props.onSaved New payload.
 */
function SiteGroup( { group, onSaved } ) {
	const [ live, setLive ] = useState( !! group.on );
	const [ on, setOn ] = useState( () =>
		group.sources.filter( ( s ) => s.on ).map( ( s ) => s.slug )
	);
	const [ busy, setBusy ] = useState( false );
	const [ msg, setMsg ] = useState( null );
	const save = () => {
		setBusy( true );
		setMsg( null );
		apiFetch( {
			path: path( 'settings/site' ),
			method: 'POST',
			data: { on: live, types: on },
		} )
			.then( ( r ) => {
				onSaved( r.site );
				setMsg( { tone: 'success', text: r.message } );
			} )
			.catch( ( e ) => setMsg( { tone: 'error', text: errText( e ) } ) )
			.finally( () => setBusy( false ) );
	};
	return (
		<SettingsGroup
			id="planweb-group-site"
			title={ __( 'Site schedule', 'planweb' ) }
			description={ __(
				'What WordPress and FluentCRM already schedule, shown beside the entries on the Dashboard, in the calendar, in the WordPress Dashboard box and in the calendar feed. Read only: dates are set in the post or the newsletter.',
				'planweb'
			) }
			footer={
				<>
					<Button variant="primary" busy={ busy } onClick={ save }>
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
				label={ __( 'Site schedule in PlanWeb', 'planweb' ) }
				help={
					live
						? __(
								"On: the site's posts and newsletters show beside the entries.",
								'planweb'
							)
						: __(
								'Off: PlanWeb shows only its own entries. Also in Kits → Modules → PlanWeb.',
								'planweb'
							)
				}
				checked={ live }
				onChange={ setLive }
			/>
			<fieldset
				className="kits-shell-field planweb-channels"
				disabled={ ! live }
			>
				<legend>{ __( 'Show', 'planweb' ) }</legend>
				{ group.sources.map( ( s ) => (
					<label
						key={ s.slug }
						className="planweb-channel-box"
						htmlFor={ `planweb-site-${ s.slug }` }
					>
						<input
							id={ `planweb-site-${ s.slug }` }
							type="checkbox"
							checked={ on.includes( s.slug ) }
							onChange={ ( e ) =>
								setOn(
									e.target.checked
										? [ ...on, s.slug ]
										: on.filter( ( x ) => x !== s.slug )
								)
							}
						/>{ ' ' }
						{ s.label }
						{ s.bulk
							? ` (${ __( 'imported in bulk', 'planweb' ) })`
							: '' }
					</label>
				) ) }
				<p className="kits-shell-field__help">
					{ __(
						'News and Directory are imported in bulk (hundreds a month), so they are off unless ticked.',
						'planweb'
					) }
				</p>
			</fieldset>
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
			{ 'site' === open && (
				<SiteGroup group={ data.site } onSaved={ put( 'site' ) } />
			) }
			{ 'feed' === open && (
				<FeedGroup group={ data.feed } onSaved={ put( 'feed' ) } />
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
