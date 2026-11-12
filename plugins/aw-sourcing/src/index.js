/**
 * SourceWeb's new screens (trial, &ui=next): Dashboard, To review, Enrichment review and Settings, drawn with the Kits
 * admin shell (@kits/shell is window.kits.shell from Kits Core) and fed by the sourceweb/v1 routes. Data and the
 * screen come from window.sourcewebApp (includes/sourceweb-ui.php).
 */
import {
	createRoot,
	useCallback,
	useEffect,
	useState,
} from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	AppShell,
	AppHeader,
	Button,
	ChipRow,
	DataList,
	EmptyState,
	ErrorState,
	Field,
	FieldReview,
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

const app = window.sourcewebApp || {};
/**
 * A route's path, with the non-empty arguments as its query.
 *
 * @param {string} p    Route under the plugin's namespace.
 * @param {Object} args Query arguments.
 */
const path = ( p, args = {} ) => {
	const q = Object.keys( args )
		.filter( ( k ) => '' !== args[ k ] && undefined !== args[ k ] )
		.map( ( k ) => `${ k }=${ encodeURIComponent( args[ k ] ) }` )
		.join( '&' );
	return `/${ app.rest }/${ p }${ q ? '?' + q : '' }`;
};
const errText = ( e ) => e?.message || String( e );

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

const RANGES = [ 7, 28, 90 ];

/**
 * The log row to compare with: the newest day on or before `days` ago.
 *
 * @param {Object} log   { 'Y-m-d': { metric: n } }.
 * @param {string} today Site date today.
 * @param {number} days  Range.
 * @return {Array|null} [ date, row ].
 */
function rowBefore( log, today, days ) {
	const at = new Date( `${ today }T12:00:00Z` ); // UTC: the browser's timezone must not move the day
	at.setUTCDate( at.getUTCDate() - days );
	const limit = at.toISOString().slice( 0, 10 );
	const dates = Object.keys( log || {} )
		.filter( ( d ) => d <= limit )
		.sort();
	const d = dates[ dates.length - 1 ];
	return d ? [ d, log[ d ] ] : null;
}

function Dashboard() {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ days, setDays ] = useState( 7 );
	const [ note, setNote ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ tries, setTries ] = useState( 0 );
	const [ waitFor, setWaitFor ] = useState( null );

	const load = () => {
		setError( '' );
		apiFetch( { path: path( 'dashboard' ) } )
			.then( setData )
			.catch( ( e ) => setError( errText( e ) ) );
	};
	useEffect( load, [] );
	// While a count runs in the background, look again every 20 seconds (a read; nothing is counted).
	const waiting =
		!! data &&
		( data.counting || ( null !== waitFor && data.at === waitFor ) );
	useEffect( () => {
		if ( data && null !== waitFor && data.at !== waitFor ) {
			setWaitFor( null );
			setNote( '' );
		}
	}, [ data, waitFor ] );
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
				setWaitFor( data?.at ?? 0 );
				setNote(
					__(
						'A count is running in the background. The numbers change when it is done (about a minute).',
						'aw-sourcing'
					)
				);
			} )
			.catch( ( e ) => setNote( errText( e ) ) )
			.finally( () => setBusy( false ) );
	};

	if ( error ) {
		return <ErrorState message={ error } onRetry={ load } />;
	}
	if ( ! data ) {
		return (
			<LoadingState
				label={ __( 'Loading the counts…', 'aw-sourcing' ) }
			/>
		);
	}
	const before = rowBefore( data.log, data.today, days );
	const attention = data.tiles.filter( ( t ) => t.tone );
	const delta = ( t ) => {
		if ( ! before || ! t.metric || undefined === t.raw ) {
			return '';
		}
		const old = before[ 1 ]?.[ t.metric ];
		if ( undefined === old ) {
			return '';
		}
		const d = t.raw - old;
		return sprintf(
			/* translators: 1: change (+3 or -2), 2: number of days. */
			__( '%1$s in %2$d days', 'aw-sourcing' ),
			( d > 0 ? '+' : '' ) + d.toLocaleString(),
			days
		);
	};
	let after = data.atText
		? sprintf(
				/* translators: %s: date and time. */
				__( 'Counted %s.', 'aw-sourcing' ),
				data.atText
			)
		: __( 'Counting for the first time…', 'aw-sourcing' );
	if ( data.atText && ! before ) {
		after +=
			' ' +
			sprintf(
				/* translators: %d: number of days. */
				__(
					'No count from %d days ago yet, so no change is shown.',
					'aw-sourcing'
				),
				days
			);
	}
	if ( waiting && tries >= 15 ) {
		after +=
			' ' +
			__(
				'The count is taking longer than usual; reload this page later.',
				'aw-sourcing'
			);
	}

	return (
		<>
			<ChipRow
				label={ __( 'Compare with', 'aw-sourcing' ) }
				value={ days }
				onChange={ setDays }
				chips={ RANGES.map( ( n ) => ( {
					value: n,
					/* translators: %d: number of days. */
					label: sprintf( __( 'Last %d days', 'aw-sourcing' ), n ),
				} ) ) }
				after={
					<>
						{ after }{ ' ' }
						<Button
							variant="link"
							busy={ busy }
							onClick={ countNow }
						>
							{ __( 'Count now', 'aw-sourcing' ) }
						</Button>
					</>
				}
			/>
			{ note && (
				<Notice tone="info" onDismiss={ () => setNote( '' ) }>
					{ note }
				</Notice>
			) }
			{ attention.length > 0 && (
				<Notice tone="warning">
					<strong>{ __( 'Needs attention', 'aw-sourcing' ) }</strong>
					<ul>
						{ attention.map( ( t ) => (
							<li key={ t.key }>
								{ t.label }: { t.value }. { t.sub }
							</li>
						) ) }
					</ul>
				</Notice>
			) }
			<TileGrid label={ __( 'SourceWeb at a glance', 'aw-sourcing' ) }>
				{ data.tiles.map( ( t ) => (
					<Tile
						key={ t.key }
						label={ t.label }
						value={ t.value }
						delta={ delta( t ) }
						sub={ t.sub }
						href={ t.href }
						tone={ t.tone }
						loading={ data.counting }
					/>
				) ) }
			</TileGrid>
			{ data.runs.length > 0 && (
				<SectionCard
					id="runs"
					title={ __( 'Worker runs', 'aw-sourcing' ) }
					description={ __(
						'Each workflow’s last report and when it runs next.',
						'aw-sourcing'
					) }
				>
					<DataList
						label={ __( 'Worker runs', 'aw-sourcing' ) }
						columns={ [
							{
								key: 'title',
								label: __( 'Worker', 'aw-sourcing' ),
								primary: true,
							},
							{
								key: 'last',
								label: __( 'Last report', 'aw-sourcing' ),
							},
							{
								key: 'result',
								label: __( 'Result', 'aw-sourcing' ),
							},
							{
								key: 'next',
								label: __( 'Next due', 'aw-sourcing' ),
							},
						] }
						rows={ data.runs }
						total={ data.runs.length }
					/>
				</SectionCard>
			) }
			{ data.reports.length > 0 && (
				<SectionCard
					id="reports"
					title={ __( 'Reports', 'aw-sourcing' ) }
					description={ __(
						'Read-only logs, on the current screens for now.',
						'aw-sourcing'
					) }
				>
					<RefTable
						caption={ __( 'Reports', 'aw-sourcing' ) }
						head={ [
							__( 'Report', 'aw-sourcing' ),
							__( 'What it lists', 'aw-sourcing' ),
						] }
						rows={ data.reports.map( ( r ) => [
							<a key="a" href={ r.url }>
								{ r.label }
							</a>,
							r.what,
						] ) }
					/>
				</SectionCard>
			) }
			<SectionCard
				id="commands"
				title={ __( 'Commands', 'aw-sourcing' ) }
				description={ __(
					'WP-CLI on the server. Each lists its options with --help.',
					'aw-sourcing'
				) }
			>
				<RefTable
					caption={ __( 'Commands', 'aw-sourcing' ) }
					head={ [
						__( 'Command', 'aw-sourcing' ),
						__( 'What it does', 'aw-sourcing' ),
					] }
					rows={ data.commands.map( ( c ) => [
						<code key="c">{ c[ 0 ] }</code>,
						c[ 1 ],
					] ) }
				/>
			</SectionCard>
		</>
	);
}

/* ---------- To review ---------- */

function Review() {
	const [ q, setQ ] = useState( {
		type: '',
		search: '',
		source: '',
		why: '',
		orderby: 'date',
		order: 'desc',
		page: 1,
	} );
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ msg, setMsg ] = useState( null );

	const load = useCallback( () => {
		setLoading( true );
		setError( '' );
		apiFetch( { path: path( 'review', q ) } )
			.then( setData )
			.catch( ( e ) => setError( errText( e ) ) )
			.finally( () => setLoading( false ) );
	}, [ q ] );
	useEffect( load, [ load ] );
	const set = ( part ) => setQ( ( o ) => ( { ...o, page: 1, ...part } ) );

	const decide = ( action, ids ) => {
		setMsg( null );
		return apiFetch( {
			path: path( 'review/decide' ),
			method: 'POST',
			data: { action, ids },
		} )
			.then( ( r ) => setMsg( { tone: 'success', text: r.message } ) )
			.catch( ( e ) => setMsg( { tone: 'error', text: errText( e ) } ) )
			.finally( load );
	};
	const publishAll = () => {
		const n = data.count;
		if (
			// eslint-disable-next-line no-alert -- a plain browser confirm that names the count, as today's screen
			! window.confirm(
				sprintf(
					/* translators: %d: number of items. */
					_n(
						'Publish %d item in this view?',
						'Publish all %d items in this view?',
						n,
						'aw-sourcing'
					),
					n
				)
			)
		) {
			return;
		}
		apiFetch( {
			path: path( 'review/publish-all' ),
			method: 'POST',
			data: { type: q.type, count: n },
		} )
			.then( ( r ) => setMsg( { tone: 'success', text: r.message } ) )
			.catch( ( e ) => setMsg( { tone: 'error', text: errText( e ) } ) )
			.finally( load );
	};

	const rows = data?.rows || [];
	return (
		<SectionCard
			id="review"
			title={ __( 'To review', 'aw-sourcing' ) }
			description={ __(
				'Items that came in automatically and need a person, with why each was held. Publish puts it live; Reject moves it to Drafts (nothing is deleted).',
				'aw-sourcing'
			) }
			actions={
				data && data.count > 0 ? (
					<Button onClick={ publishAll }>
						{ sprintf(
							/* translators: %d: number of items. */
							__( 'Publish all %d', 'aw-sourcing' ),
							data.count
						) }
					</Button>
				) : null
			}
		>
			{ msg && (
				<Notice tone={ msg.tone } onDismiss={ () => setMsg( null ) }>
					{ msg.text }
				</Notice>
			) }
			<DataList
				label={ __( 'Items waiting for review', 'aw-sourcing' ) }
				columns={ [
					{
						key: 'date',
						label: __( 'Received', 'aw-sourcing' ),
						sortable: true,
						render: ( r ) => r.dateText,
					},
					{
						key: 'title',
						label: __( 'Item', 'aw-sourcing' ),
						sortable: true,
						primary: true,
						render: ( r ) => (
							<>
								<a href={ r.editUrl }>
									<strong>{ r.title }</strong>
								</a>
								{ r.excerpt && (
									<span className="kits-shell-field__help">
										{ ' ' }
										{ r.excerpt }
									</span>
								) }
							</>
						),
					},
					{
						key: 'type',
						label: __( 'Type', 'aw-sourcing' ),
						sortable: true,
						render: ( r ) => r.typeLabel,
					},
					{
						key: 'host',
						label: __( 'Source', 'aw-sourcing' ),
						render: ( r ) => r.host || '—',
					},
					{
						key: 'why',
						label: __( 'Why it is waiting', 'aw-sourcing' ),
					},
				] }
				rows={ rows }
				total={ data?.total || 0 }
				page={ data?.page || 1 }
				pages={ data?.pages || 1 }
				onPage={ ( page ) => setQ( ( o ) => ( { ...o, page } ) ) }
				search={ q.search }
				onSearch={ ( search ) => set( { search } ) }
				searchLabel={ __( 'Search items', 'aw-sourcing' ) }
				views={ data?.views || [] }
				view={ q.type }
				onView={ ( type ) => set( { type, source: '', why: '' } ) }
				filters={ [
					{
						key: 'source',
						label: __( 'Source', 'aw-sourcing' ),
						options: data?.filters?.source || [],
						value: q.source,
					},
					{
						key: 'why',
						label: __( 'Why', 'aw-sourcing' ),
						options: data?.filters?.why || [],
						value: q.why,
					},
				] }
				onFilter={ ( key, value ) => set( { [ key ]: value } ) }
				sort={ { key: q.orderby, dir: q.order } }
				onSort={ ( orderby, order ) => set( { orderby, order } ) }
				bulkActions={ [
					{ value: 'publish', label: __( 'Publish', 'aw-sourcing' ) },
					{
						value: 'reject',
						label: __( 'Reject (move to Drafts)', 'aw-sourcing' ),
					},
				] }
				onBulk={ decide }
				rowActions={ ( r ) => [
					{ label: __( 'Edit', 'aw-sourcing' ), href: r.editUrl },
					...( r.url
						? [
								{
									label: __( 'Source', 'aw-sourcing' ),
									href: r.url,
								},
							]
						: [] ),
					{
						label: __( 'Publish', 'aw-sourcing' ),
						onClick: () => decide( 'publish', [ r.id ] ),
						disabled: r.noPublish,
						why: r.noPublish
							? __( 'Needs its summary first', 'aw-sourcing' )
							: '',
					},
					{
						label: __( 'Reject', 'aw-sourcing' ),
						tone: 'error',
						onClick: () => decide( 'reject', [ r.id ] ),
					},
				] }
				loading={ loading }
				error={ error }
				onRetry={ load }
				emptyTitle={ __( 'Nothing is waiting.', 'aw-sourcing' ) }
				empty={
					<p>
						{ __(
							'New items that pass the checks go live on their own.',
							'aw-sourcing'
						) }
					</p>
				}
			/>
		</SectionCard>
	);
}

/* ---------- Enrichment review ---------- */

const FILTER_KEYS = [
	'search',
	'source',
	'confidence',
	'field',
	'flag',
	'state',
];

function Enrichment() {
	const [ q, setQ ] = useState( {
		status: 'pending',
		search: '',
		source: '',
		confidence: '',
		field: '',
		flag: '',
		state: '',
		page: 1,
	} );
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ msg, setMsg ] = useState( null );
	const [ open, setOpen ] = useState( 0 );
	const [ busy, setBusy ] = useState( false );

	const load = useCallback( () => {
		setLoading( true );
		setError( '' );
		apiFetch( { path: path( 'enrichment', q ) } )
			.then( setData )
			.catch( ( e ) => setError( errText( e ) ) )
			.finally( () => setLoading( false ) );
	}, [ q ] );
	useEffect( load, [ load ] );
	const set = ( part ) => {
		setOpen( 0 );
		setQ( ( o ) => ( { ...o, page: 1, ...part } ) );
	};

	const decide = ( body ) => {
		setBusy( true );
		setMsg( null );
		const filters = {};
		FILTER_KEYS.forEach( ( k ) => ( filters[ k ] = q[ k ] ) );
		return apiFetch( {
			path: path( 'enrichment/decide' ),
			method: 'POST',
			data: { ...filters, ...body },
		} )
			.then( ( r ) => setMsg( { tone: 'success', text: r.message } ) )
			.catch( ( e ) => setMsg( { tone: 'error', text: errText( e ) } ) )
			.finally( () => {
				setBusy( false );
				load();
			} );
	};
	const approveHigh = () => {
		if (
			// eslint-disable-next-line no-alert -- a plain browser confirm before a bulk approval
			window.confirm(
				__(
					'Approve every waiting high-confidence value in this filter? Merges are left for one by one.',
					'aw-sourcing'
				)
			)
		) {
			decide( { do: 'approve_high' } );
		}
	};

	const rows = data?.rows || [];
	const listing = rows.find( ( r ) => r.id === open );
	const progress = data?.progress;
	return (
		<>
			{ msg && (
				<Notice tone={ msg.tone } onDismiss={ () => setMsg( null ) }>
					{ msg.text }
				</Notice>
			) }
			{ listing && (
				<SectionCard
					id="listing"
					title={ listing.title }
					description={ [
						listing.flags.join( ' · ' ),
						listing.sources &&
							sprintf(
								/* translators: %s: source names. */
								__( 'From %s', 'aw-sourcing' ),
								listing.sources
							),
					]
						.filter( Boolean )
						.join( '. ' ) }
					actions={
						<Button variant="link" onClick={ () => setOpen( 0 ) }>
							{ __( 'Close', 'aw-sourcing' ) }
						</Button>
					}
				>
					{ listing.fields.length > 0 ? (
						<FieldReview
							key={ listing.fields
								.map( ( f ) => f.key + f.status )
								.join() }
							title={ __( 'Proposed details', 'aw-sourcing' ) }
							fields={ listing.fields.map( ( f ) => ( {
								...f,
								label: `${ f.label } (${ f.confidence || '–' }${
									f.source ? ', ' + f.source : ''
								})`,
							} ) ) }
							busy={ busy }
							onApply={ ( ids ) =>
								decide( { do: 'approve', ids } )
							}
							onUndo={ ( id ) =>
								decide( { do: 'undo', ids: [ id ] } )
							}
							after={
								listing.waiting > 0 && (
									<p>
										<Button
											busy={ busy }
											onClick={ () =>
												decide( {
													do: 'reject',
													listings: [ listing.id ],
												} )
											}
										>
											{ __(
												'Reject every waiting value',
												'aw-sourcing'
											) }
										</Button>
									</p>
								)
							}
						/>
					) : (
						<EmptyState
							title={ __(
								'Nothing waiting or applied in this view.',
								'aw-sourcing'
							) }
						/>
					) }
					{ listing.others.length > 0 && (
						<RefTable
							caption={ __( 'Other decisions', 'aw-sourcing' ) }
							head={ [
								__( 'Field', 'aw-sourcing' ),
								__( 'What happened', 'aw-sourcing' ),
							] }
							rows={ listing.others.map( ( f ) => [
								f.label,
								[ f.status, f.created, f.proposed, f.note ]
									.filter( Boolean )
									.join( ' · ' ),
							] ) }
						/>
					) }
				</SectionCard>
			) }
			<SectionCard
				id="enrichment"
				title={ __( 'Listing details', 'aw-sourcing' ) }
				description={
					progress
						? sprintf(
								/* translators: 1: listings checked, 2: listings, 3: proposals waiting. */
								__(
									'%1$d of %2$d listings checked · %3$d proposals waiting. High-confidence values for empty fields of unclaimed listings are applied on their own and listed under Applied, with Undo.',
									'aw-sourcing'
								),
								progress.checked,
								progress.total,
								progress.pending
							)
						: ''
				}
				actions={
					'pending' === q.status && rows.length > 0 ? (
						<Button busy={ busy } onClick={ approveHigh }>
							{ __(
								'Approve all high (this filter)',
								'aw-sourcing'
							) }
						</Button>
					) : null
				}
			>
				<DataList
					label={ __( 'Listings with proposals', 'aw-sourcing' ) }
					columns={ [
						{
							key: 'title',
							label: __( 'Listing', 'aw-sourcing' ),
							primary: true,
							render: ( r ) => <strong>{ r.title }</strong>,
						},
						{
							key: 'waiting',
							label: __( 'Waiting', 'aw-sourcing' ),
						},
						{
							key: 'applied',
							label: __( 'Applied', 'aw-sourcing' ),
						},
						{
							key: 'confidence',
							label: __( 'Confidence', 'aw-sourcing' ),
							render: ( r ) => r.confidence.join( ', ' ) || '–',
						},
						{
							key: 'sources',
							label: __( 'Source', 'aw-sourcing' ),
							render: ( r ) => r.sources || '–',
						},
						{
							key: 'flags',
							label: __( 'Flags', 'aw-sourcing' ),
							render: ( r ) => r.flags.join( ' · ' ) || '–',
						},
					] }
					rows={ rows }
					total={ data?.total || 0 }
					page={ data?.page || 1 }
					pages={ data?.pages || 1 }
					onPage={ ( page ) => setQ( ( o ) => ( { ...o, page } ) ) }
					search={ q.search }
					onSearch={ ( search ) => set( { search } ) }
					searchLabel={ __( 'Search listings', 'aw-sourcing' ) }
					views={ data?.views || [] }
					view={ q.status }
					onView={ ( status ) => set( { status } ) }
					filters={ data?.filters || [] }
					onFilter={ ( key, value ) => set( { [ key ]: value } ) }
					bulkActions={
						'pending' === q.status
							? [
									{
										value: 'approve',
										label: __(
											'Approve every waiting value',
											'aw-sourcing'
										),
									},
									{
										value: 'reject',
										label: __(
											'Reject every waiting value',
											'aw-sourcing'
										),
									},
								]
							: []
					}
					onBulk={ ( v, ids ) => decide( { do: v, listings: ids } ) }
					rowActions={ ( r ) => [
						{
							label:
								open === r.id
									? __( 'Close', 'aw-sourcing' )
									: __( 'Review', 'aw-sourcing' ),
							onClick: () => {
								setOpen( open === r.id ? 0 : r.id );
								window.scrollTo( 0, 0 );
							},
						},
						{ label: __( 'Edit', 'aw-sourcing' ), href: r.editUrl },
						{ label: __( 'View', 'aw-sourcing' ), href: r.viewUrl },
					] }
					loading={ loading }
					error={ error }
					onRetry={ load }
					emptyTitle={ __( 'Nothing here.', 'aw-sourcing' ) }
				/>
			</SectionCard>
		</>
	);
}

/* ---------- Settings ---------- */

const SERVICES = 'services';

/**
 * The open group from the address (#workers), so a link opens the right group.
 *
 * @param {Array} groups Groups.
 */
function hashGroup( groups ) {
	const h = window.location.hash.replace( '#', '' );
	if ( SERVICES === h || groups.some( ( g ) => g.slug === h ) ) {
		return h;
	}
	return SERVICES;
}

const valuesOf = ( fields ) =>
	fields.reduce( ( o, f ) => ( { ...o, [ f.key ]: f.value } ), {} );

/**
 * A group with its own Save: switches and numbers.
 *
 * @param {Object}   props
 * @param {Object}   props.group   Group from the route.
 * @param {Function} props.onSaved New settings from the route after a save.
 * @param {Function} props.onDirty ( slug, unsaved ) when that changes.
 */
function Group( { group, onSaved, onDirty } ) {
	const [ v, setV ] = useState( () => valuesOf( group.fields ) );
	const [ msg, setMsg ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	// Reset only when this group's stored values change, so saving another group keeps unsaved edits here.
	const stored = JSON.stringify( valuesOf( group.fields ) );
	useEffect( () => setV( JSON.parse( stored ) ), [ stored ] );
	const dirty = group.fields.some( ( f ) => v[ f.key ] !== f.value );
	useEffect(
		() => onDirty( group.slug, dirty ),
		[ group.slug, dirty, onDirty ]
	);
	const save = () => {
		setBusy( true );
		setMsg( null );
		apiFetch( {
			path: path( `settings/${ group.slug }` ),
			method: 'POST',
			data: { values: v },
		} )
			.then( ( r ) => {
				onSaved( r.settings );
				setMsg( { tone: 'success', text: r.message } );
			} )
			.catch( ( e ) => setMsg( { tone: 'error', text: errText( e ) } ) )
			.finally( () => setBusy( false ) );
	};
	return (
		<SettingsGroup
			id={ `sourceweb-group-${ group.slug }` }
			title={ group.label }
			description={ group.description }
			footer={
				<>
					<Button
						variant="primary"
						busy={ busy }
						disabled={ ! dirty }
						onClick={ save }
					>
						{ __( 'Save changes', 'aw-sourcing' ) }
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
			{ group.fields.map( ( f ) =>
				'switch' === f.type ? (
					<Switch
						key={ f.key }
						label={ f.label }
						help={ f.help }
						checked={ !! v[ f.key ] }
						onChange={ ( on ) => setV( { ...v, [ f.key ]: on } ) }
					/>
				) : (
					<Field
						key={ f.key }
						label={ f.label }
						help={ f.help }
						changed={
							undefined !== f.default && v[ f.key ] !== f.default
						}
						onReset={ () => setV( { ...v, [ f.key ]: f.default } ) }
						render={ ( id, helpId ) => (
							<input
								id={ id }
								type="number"
								min="0"
								step="1"
								value={ v[ f.key ] }
								aria-describedby={ helpId }
								onChange={ ( e ) =>
									setV( {
										...v,
										[ f.key ]: parseInt(
											e.target.value || '0',
											10
										),
									} )
								}
							/>
						) }
					/>
				)
			) }
		</SettingsGroup>
	);
}

/**
 * One enrichment source's row: its switch, and for Google Maps the key (write-only) and the cap's place.
 *
 * @param {Object}   props
 * @param {Object}   props.s       Service from the route.
 * @param {Function} props.onSaved New settings after a save.
 */
function SourceEdit( { s, onSaved } ) {
	const [ on, setOn ] = useState( s.on );
	const [ key, setKey ] = useState( '' );
	const [ msg, setMsg ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	useEffect( () => setOn( s.on ), [ s.on ] );
	const send = ( route, data ) => {
		setBusy( true );
		setMsg( null );
		return apiFetch( { path: path( route ), method: 'POST', data } )
			.then( ( r ) => {
				onSaved( r.settings );
				setMsg( { tone: 'success', text: r.message } );
				setKey( '' );
			} )
			.catch( ( e ) => setMsg( { tone: 'error', text: errText( e ) } ) )
			.finally( () => setBusy( false ) );
	};
	return (
		<>
			<Switch
				label={ __( 'On', 'aw-sourcing' ) }
				help={ s.help }
				checked={ on }
				onChange={ setOn }
			/>
			{ s.key && (
				<Field
					label={ __( 'Google Places key', 'aw-sourcing' ) }
					help={
						s.key.set
							? __(
									'A key is set. Type a new one to replace it; leave it empty to keep it. The key is never shown.',
									'aw-sourcing'
								)
							: __(
									'Not set: the live Google block is hidden. Restrict the key to the Places API.',
									'aw-sourcing'
								)
					}
					render={ ( id, helpId ) => (
						<input
							id={ id }
							type="password"
							autoComplete="off"
							value={ key }
							aria-describedby={ helpId }
							onChange={ ( e ) => setKey( e.target.value ) }
						/>
					) }
				/>
			) }
			<p>
				<Button
					busy={ busy }
					disabled={ on === s.on }
					onClick={ () =>
						send( 'settings/enrichment', {
							values: { [ s.source ]: on },
						} )
					}
				>
					{ __( 'Save', 'aw-sourcing' ) }
				</Button>{ ' ' }
				{ s.key && (
					<Button
						busy={ busy }
						disabled={ '' === key.trim() }
						onClick={ () =>
							send( 'settings/google-key', { key: key.trim() } )
						}
					>
						{ __( 'Save key', 'aw-sourcing' ) }
					</Button>
				) }{ ' ' }
				<span aria-live="polite">
					{ msg && 'success' === msg.tone ? msg.text : '' }
				</span>
			</p>
			{ msg && 'error' === msg.tone && (
				<Notice tone="error" onDismiss={ () => setMsg( null ) }>
					{ msg.text }
				</Notice>
			) }
		</>
	);
}

/**
 * Connected services.
 *
 * @param {Object}   props
 * @param {Array}    props.list    Services.
 * @param {Function} props.onSaved New settings after a save.
 */
function Services( { list, onSaved } ) {
	if ( ! list.length ) {
		return (
			<EmptyState
				title={ __( 'No connected services.', 'aw-sourcing' ) }
			/>
		);
	}
	return (
		<SectionCard
			id={ `sourceweb-group-${ SERVICES }` }
			title={ __( 'Connected services', 'aw-sourcing' ) }
			description={ __(
				'Each source and service SourceWeb works with. Keys are never sent to this page: a row says only whether one is set.',
				'aw-sourcing'
			) }
		>
			<ServiceList label={ __( 'Connected services', 'aw-sourcing' ) }>
				{ list.map( ( s ) => (
					<ServiceRow
						key={ s.slug }
						name={ s.name }
						status={ s.status }
						tone={ s.tone }
						summary={ s.summary }
						editLabel={
							s.source
								? undefined
								: __( 'Details', 'aw-sourcing' )
						}
					>
						{ s.source ? (
							<SourceEdit s={ s } onSaved={ onSaved } />
						) : (
							<p>
								{ ( s.links || [] ).map( ( l, i ) => (
									<span key={ l.url }>
										{ i > 0 && ' · ' }
										<a href={ l.url }>{ l.label }</a>
									</span>
								) ) }
							</p>
						) }
					</ServiceRow>
				) ) }
			</ServiceList>
		</SectionCard>
	);
}

function Settings() {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ open, setOpen ] = useState( SERVICES );
	const [ dirty, setDirty ] = useState( {} );
	const markDirty = useCallback(
		( slug, d ) =>
			setDirty( ( o ) =>
				o[ slug ] === d ? o : { ...o, [ slug ]: d }
			),
		[]
	);
	const load = () => {
		setError( '' );
		apiFetch( { path: path( 'settings' ) } )
			.then( ( r ) => {
				setData( r );
				setOpen( hashGroup( r.groups ) );
			} )
			.catch( ( e ) => setError( errText( e ) ) );
	};
	useEffect( load, [] );
	useEffect( () => {
		const onHash = () => data && setOpen( hashGroup( data.groups ) );
		window.addEventListener( 'hashchange', onHash );
		return () => window.removeEventListener( 'hashchange', onHash );
	}, [ data ] );
	const anyDirty = Object.values( dirty ).some( Boolean );
	useEffect( () => {
		const warn = ( e ) => {
			if ( anyDirty ) {
				e.preventDefault();
				e.returnValue = '';
			}
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ anyDirty ] );

	if ( error ) {
		return <ErrorState message={ error } onRetry={ load } />;
	}
	if ( ! data ) {
		return (
			<LoadingState
				label={ __( 'Loading the settings…', 'aw-sourcing' ) }
			/>
		);
	}
	const chips = [
		{
			value: SERVICES,
			label: __( 'Connected services', 'aw-sourcing' ),
			href: `#${ SERVICES }`,
		},
		...data.groups.map( ( g ) => ( {
			value: g.slug,
			label: dirty[ g.slug ] ? `${ g.label } •` : g.label,
			href: `#${ g.slug }`,
		} ) ),
	];
	return (
		<>
			<ChipRow
				label={ __( 'Settings groups', 'aw-sourcing' ) }
				chips={ chips }
				value={ open }
				onChange={ setOpen }
			/>
			{ SERVICES === open && (
				<Services list={ data.services } onSaved={ setData } />
			) }
			{ data.groups.map( ( g ) => (
				<div key={ g.slug } hidden={ g.slug !== open }>
					<Group
						group={ g }
						onSaved={ setData }
						onDirty={ markDirty }
					/>
				</div>
			) ) }
			<SectionCard
				id="elsewhere"
				title={ __( 'On the current screens', 'aw-sourcing' ) }
				description={ __(
					'These lists keep their own screens for now.',
					'aw-sourcing'
				) }
			>
				<p>
					{ data.elsewhere.map( ( l, i ) => (
						<span key={ l.url }>
							{ i > 0 && ' · ' }
							<a href={ l.url }>{ l.label }</a>
						</span>
					) ) }
				</p>
			</SectionCard>
		</>
	);
}

/* ---------- Pages with sub-screens (Workers …): the sub-screen is in the address ---------- */

/**
 * The sub-screen from the address (?edit=<id>, ?runs=<id>) and a way to open another one; Back and Forward work.
 *
 * @param {Array} keys Query keys that open a sub-screen.
 * @return {Array} [ { key: value }, go( { key: value } | {} ) ].
 */
function useSub( keys ) {
	const read = () => {
		const q = new URLSearchParams( window.location.search );
		return keys.reduce(
			( o, k ) => ( q.get( k ) ? { ...o, [ k ]: q.get( k ) } : o ),
			{}
		);
	};
	const [ sub, setSub ] = useState( read );
	useEffect( () => {
		const on = () => setSub( read() );
		window.addEventListener( 'popstate', on );
		return () => window.removeEventListener( 'popstate', on );
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps -- read() only uses the keys given once
	const go = ( next ) => {
		const u = new URL( window.location.href );
		keys.forEach( ( k ) => u.searchParams.delete( k ) );
		Object.keys( next ).forEach( ( k ) =>
			u.searchParams.set( k, next[ k ] )
		);
		window.history.pushState( null, '', u.toString() );
		setSub( next );
		window.scrollTo( 0, 0 );
	};
	return [ sub, go ];
}

/**
 * The address of a sub-screen, for real links (open in a new tab works).
 *
 * @param {Object} next { edit: id } or { runs: id }.
 */
const subHref = ( next ) => {
	const u = new URL( window.location.href );
	[ 'edit', 'runs' ].forEach( ( k ) => u.searchParams.delete( k ) );
	Object.keys( next ).forEach( ( k ) => u.searchParams.set( k, next[ k ] ) );
	return u.toString();
};

/**
 * A click on a sub-screen link opens it in place; a modified click (new tab) is left to the browser.
 *
 * @param {Function} go   From useSub().
 * @param {Object}   next The sub-screen.
 */
const inPlace = ( go, next ) => ( e ) => {
	if ( e.metaKey || e.ctrlKey || e.shiftKey || 0 !== e.button ) {
		return;
	}
	e.preventDefault();
	go( next );
};

const TONE = { success: 'ok', error: 'error', warn: 'warn', info: '' };

/**
 * A result in words with the shell's status mark.
 *
 * @param {Object} props
 * @param {string} props.tone  success | error | warn | info.
 * @param {string} props.label Words.
 */
function Status( { tone, label } ) {
	return (
		<span
			className={ `kits-shell-status${
				TONE[ tone ] ? ' is-' + TONE[ tone ] : ''
			}` }
		>
			{ label }
		</span>
	);
}

/* ---------- Workers ---------- */

function Workers() {
	const [ sub, go ] = useSub( [ 'edit', 'runs' ] );
	const [ msg, setMsg ] = useState( null );
	const back = ( m ) => {
		setMsg( m || null );
		go( {} );
	};
	if ( sub.edit ) {
		return <WorkerForm id={ sub.edit } onDone={ back } />;
	}
	if ( sub.runs ) {
		return <WorkerRuns id={ sub.runs } onBack={ () => back() } />;
	}
	return <WorkerList go={ go } msg={ msg } setMsg={ setMsg } />;
}

function WorkerList( { go, msg, setMsg } ) {
	const [ q, setQ ] = useState( {
		view: '',
		search: '',
		orderby: '',
		order: 'asc',
		page: 1,
	} );
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const load = useCallback( () => {
		setLoading( true );
		setError( '' );
		apiFetch( { path: path( 'workers', q ) } )
			.then( setData )
			.catch( ( e ) => setError( errText( e ) ) )
			.finally( () => setLoading( false ) );
	}, [ q ] );
	useEffect( load, [ load ] );
	const set = ( part ) => setQ( ( o ) => ( { ...o, page: 1, ...part } ) );
	const send = ( route, id ) => {
		setMsg( null );
		return apiFetch( { path: path( route ), method: 'POST', data: { id } } )
			.then( ( r ) => setMsg( { tone: 'success', text: r.message } ) )
			.catch( ( e ) => setMsg( { tone: 'error', text: errText( e ) } ) )
			.finally( load );
	};
	const remove = ( r ) => {
		if (
			// eslint-disable-next-line no-alert -- a plain browser confirm, as today's screen
			window.confirm(
				sprintf(
					/* translators: %s: worker name. */
					__( 'Delete the planned worker "%s"?', 'aw-sourcing' ),
					r.title
				)
			)
		) {
			send( 'workers/delete', r.id );
		}
	};
	return (
		<>
			<SectionCard
				id="workers"
				title={ __( 'Workers', 'aw-sourcing' ) }
				description={ __(
					'One worker per workflow of the workers repository, which reports its start and end here. Schedules are changed in the workflows; names, groups and late limits here. A problem is emailed to the owner once. Planned workers never raise an alert.',
					'aw-sourcing'
				) }
				actions={
					<a
						className="kits-shell-button is-primary"
						href={ subHref( { edit: 'new' } ) }
						onClick={ inPlace( go, { edit: 'new' } ) }
					>
						{ __( 'Add New Worker', 'aw-sourcing' ) }
					</a>
				}
			>
				{ data?.runOff && (
					<Notice tone="warning">
						{ __(
							'Run now is off: AW_GITHUB_DISPATCH_TOKEN is not set in wp-config.php (a fine-grained GitHub token for the workers repository only, Actions read and write).',
							'aw-sourcing'
						) }
					</Notice>
				) }
				{ msg && (
					<Notice
						tone={ msg.tone }
						onDismiss={ () => setMsg( null ) }
					>
						{ msg.text }
					</Notice>
				) }
				<DataList
					label={ __( 'Workers', 'aw-sourcing' ) }
					columns={ [
						{
							key: 'title',
							label: __( 'Worker', 'aw-sourcing' ),
							sortable: true,
							primary: true,
							render: ( r ) => (
								<>
									<a
										href={ subHref( { edit: r.id } ) }
										onClick={ inPlace( go, {
											edit: r.id,
										} ) }
									>
										<strong>{ r.title }</strong>
									</a>{ ' ' }
									<code>{ r.id }</code>
									{ r.what && (
										<span className="kits-shell-field__help">
											{ ' ' }
											{ r.what }
										</span>
									) }
									{ r.problem && (
										<span className="kits-shell-field__help">
											{ ' ' }
											<Status
												tone="error"
												label={ r.problem }
											/>
										</span>
									) }
								</>
							),
						},
						{
							key: 'group',
							label: __( 'Group', 'aw-sourcing' ),
							sortable: true,
							render: ( r ) => r.groupLabel,
						},
						{
							key: 'file',
							label: __( 'Workflow file', 'aw-sourcing' ),
							render: ( r ) =>
								r.fileUrl ? (
									<a href={ r.fileUrl }>
										<code>{ r.file }</code>
									</a>
								) : (
									<>
										<code>{ r.file }</code>{ ' ' }
										<span className="kits-shell-field__help">
											{ __(
												'Not built yet',
												'aw-sourcing'
											) }
										</span>
									</>
								),
						},
						{
							key: 'schedule',
							label: __( 'Schedule (IST)', 'aw-sourcing' ),
							render: ( r ) => (
								<>
									{ r.schedule || '—' }
									{ r.late && (
										<span className="kits-shell-field__help">
											{ ' ' }
											{ r.late }
										</span>
									) }
								</>
							),
						},
						{
							key: 'last_run',
							label: __( 'Last run', 'aw-sourcing' ),
							sortable: true,
							render: ( r ) => {
								if ( ! r.last ) {
									return r.planned
										? '—'
										: __( 'No report yet', 'aw-sourcing' );
								}
								return (
									<>
										<Status
											tone={ r.last.tone }
											label={ r.last.label }
										/>{ ' ' }
										{ r.last.when }
										{ r.last.took
											? ` · ${ r.last.took }`
											: '' }
										{ r.last.counts && (
											<span className="kits-shell-field__help">
												{ ' ' }
												{ r.last.counts }
											</span>
										) }
									</>
								);
							},
						},
						{
							key: 'status',
							label: __( 'Status', 'aw-sourcing' ),
							sortable: true,
							render: ( r ) => (
								<>
									<Status
										tone={
											{
												late: 'warn',
												failing: 'error',
												active: 'success',
											}[ r.status ] || 'info'
										}
										label={ r.statusLabel }
									/>
									{ r.statusNote && (
										<span className="kits-shell-field__help">
											{ ' ' }
											{ r.statusNote }
										</span>
									) }
								</>
							),
						},
						{
							key: 'keys',
							label: __( 'Keys', 'aw-sourcing' ),
						},
					] }
					rows={ data?.rows || [] }
					total={ data?.total || 0 }
					page={ data?.page || 1 }
					pages={ data?.pages || 1 }
					onPage={ ( page ) => setQ( ( o ) => ( { ...o, page } ) ) }
					search={ q.search }
					onSearch={ ( search ) => set( { search } ) }
					searchLabel={ __( 'Search workers', 'aw-sourcing' ) }
					views={ data?.views || [] }
					view={ q.view }
					onView={ ( view ) => set( { view } ) }
					sort={ { key: q.orderby, dir: q.order } }
					onSort={ ( orderby, order ) => set( { orderby, order } ) }
					rowActions={ ( r ) => [
						{
							label: __( 'Edit', 'aw-sourcing' ),
							onClick: () => go( { edit: r.id } ),
						},
						...( r.planned
							? []
							: [
									{
										label: __( 'Run now', 'aw-sourcing' ),
										onClick: () =>
											send( 'workers/run', r.id ),
										disabled: ! r.canRun,
										why: r.canRun
											? ''
											: __(
													'Needs input or the dispatch token',
													'aw-sourcing'
												),
									},
								] ),
						{
							label: __( 'View runs', 'aw-sourcing' ),
							onClick: () => go( { runs: r.id } ),
						},
						...( r.planned
							? [
									{
										label: __( 'Delete', 'aw-sourcing' ),
										tone: 'error',
										onClick: () => remove( r ),
									},
								]
							: [] ),
					] }
					loading={ loading }
					error={ error }
					onRetry={ load }
					emptyTitle={ __( 'No workers found.', 'aw-sourcing' ) }
				/>
			</SectionCard>
			<SectionCard
				id="run-now-log"
				title={ __( 'Run now log', 'aw-sourcing' ) }
				description={ __(
					'The last 50 times Run now was used.',
					'aw-sourcing'
				) }
			>
				{ data && data.log.length ? (
					<RefTable
						caption={ __( 'Run now log', 'aw-sourcing' ) }
						head={ [
							__( 'When', 'aw-sourcing' ),
							__( 'Who', 'aw-sourcing' ),
							__( 'Worker', 'aw-sourcing' ),
							__( 'Result', 'aw-sourcing' ),
						] }
						rows={ data.log }
					/>
				) : (
					<p>{ __( 'None yet.', 'aw-sourcing' ) }</p>
				) }
			</SectionCard>
		</>
	);
}

/**
 * A text input inside a Field.
 *
 * @param {Object}   props
 * @param {string}   props.label    Label.
 * @param {string}   props.help     Help line.
 * @param {string}   props.value    Value.
 * @param {Function} props.onChange New value.
 * @param {Object}   props.input    More input attributes (type, min, placeholder, rows for a textarea).
 */
function TextField( { label, help, value, onChange, input = {} } ) {
	const { rows, ...attrs } = input;
	return (
		<Field
			label={ label }
			help={ help }
			render={ ( id, helpId ) =>
				rows ? (
					<textarea
						id={ id }
						rows={ rows }
						value={ value }
						aria-describedby={ helpId }
						onChange={ ( e ) => onChange( e.target.value ) }
					/>
				) : (
					<input
						id={ id }
						type="text"
						value={ value }
						aria-describedby={ helpId }
						onChange={ ( e ) => onChange( e.target.value ) }
						{ ...attrs }
					/>
				)
			}
		/>
	);
}

/**
 * A select inside a Field (the shell's styled select is not built yet: the browser's own).
 *
 * @param {Object}   props
 * @param {string}   props.label    Label.
 * @param {string}   props.help     Help line.
 * @param {string}   props.value    Value.
 * @param {Array}    props.options  [ { value, label } ].
 * @param {Function} props.onChange New value.
 */
function SelectField( { label, help, value, options, onChange } ) {
	return (
		<Field
			label={ label }
			help={ help }
			render={ ( id, helpId ) => (
				<select
					id={ id }
					value={ value }
					aria-describedby={ helpId }
					onChange={ ( e ) => onChange( e.target.value ) }
				>
					{ options.map( ( o ) => (
						<option key={ o.value } value={ o.value }>
							{ o.label }
						</option>
					) ) }
				</select>
			) }
		/>
	);
}

/**
 * Add New Worker or edit one: name, what it does, group (or a new one), late limit, schedule wanted (Planned) and the
 * saved keys it may use.
 *
 * @param {Object}   props
 * @param {string}   props.id     Worker ID or 'new'.
 * @param {Function} props.onDone Back to the list, with a notice.
 */
function WorkerForm( { id, onDone } ) {
	const [ w, setW ] = useState( null );
	const [ v, setV ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ msg, setMsg ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const load = () => {
		setError( '' );
		apiFetch( { path: path( `workers/${ encodeURIComponent( id ) }` ) } )
			.then( ( r ) => {
				setW( r );
				setV( {
					id: '',
					name: r.name,
					what: r.what,
					group: r.group,
					group_new: '',
					late: r.late,
					schedule: r.schedule,
					keys: r.keys.filter( ( k ) => k.on ).map( ( k ) => k.id ),
				} );
			} )
			.catch( ( e ) => setError( errText( e ) ) );
	};
	useEffect( load, [ id ] );
	if ( error ) {
		return <ErrorState message={ error } onRetry={ load } />;
	}
	if ( ! w || ! v ) {
		return (
			<LoadingState
				label={ __( 'Loading the worker…', 'aw-sourcing' ) }
			/>
		);
	}
	const put = ( k ) => ( val ) => setV( { ...v, [ k ]: val } );
	const save = () => {
		setBusy( true );
		setMsg( null );
		apiFetch( {
			path: path( 'workers/save' ),
			method: 'POST',
			data: {
				...v,
				edit: w.new ? 'new' : w.id,
				keysShown: w.keys.length > 0,
			},
		} )
			.then( ( r ) =>
				onDone( {
					tone: 'same' === r.done ? 'info' : 'success',
					text: r.message,
				} )
			)
			.catch( ( e ) => {
				setMsg( { tone: 'error', text: errText( e ) } );
				setBusy( false );
			} );
	};
	const tick = ( kid, on ) =>
		setV( {
			...v,
			keys: on ? [ ...v.keys, kid ] : v.keys.filter( ( x ) => x !== kid ),
		} );
	const reg = w.registry;
	return (
		<SettingsGroup
			id="worker-form"
			title={
				w.new
					? __( 'Add New Worker', 'aw-sourcing' )
					: sprintf(
							/* translators: %s: worker name. */
							__( 'Edit %s', 'aw-sourcing' ),
							w.name
						)
			}
			description={
				w.new
					? __(
							'A planned worker: the ID is the file name its workflow will have, without .yml. It shows "Not built yet" until a workflow with that ID reports a run, and never raises an alert.',
							'aw-sourcing'
						)
					: ''
			}
			footer={
				<>
					<Button variant="primary" busy={ busy } onClick={ save }>
						{ w.new
							? __( 'Add worker', 'aw-sourcing' )
							: __( 'Save changes', 'aw-sourcing' ) }
					</Button>{ ' ' }
					<Button onClick={ () => onDone() }>
						{ __( 'Cancel', 'aw-sourcing' ) }
					</Button>
				</>
			}
		>
			{ msg && (
				<Notice tone={ msg.tone } onDismiss={ () => setMsg( null ) }>
					{ msg.text }
				</Notice>
			) }
			{ w.new ? (
				<TextField
					label={ __( 'ID', 'aw-sourcing' ) }
					help={ __(
						'e.g. press-news for press-news.yml. Lowercase letters, digits and hyphens. It never changes.',
						'aw-sourcing'
					) }
					value={ v.id }
					onChange={ put( 'id' ) }
					input={ { pattern: '[a-z0-9_-]+', className: 'code' } }
				/>
			) : (
				<RefTable
					caption={ __( 'The worker', 'aw-sourcing' ) }
					head={ [
						__( 'Item', 'aw-sourcing' ),
						__( 'Value', 'aw-sourcing' ),
					] }
					rows={ [
						[
							__( 'ID', 'aw-sourcing' ),
							<>
								<code>{ w.id }</code>{ ' ' }
								{ __( '(IDs never change)', 'aw-sourcing' ) }
							</>,
						],
						...( reg
							? [
									[
										__( 'Workflow', 'aw-sourcing' ),
										<a key="f" href={ reg.fileUrl }>
											<code>{ reg.file }</code>
										</a>,
									],
									[
										__( 'Schedule (IST)', 'aw-sourcing' ),
										`${ reg.schedule } ${ __(
											'(changed in the workflow)',
											'aw-sourcing'
										) }`,
									],
								]
							: [] ),
					] }
				/>
			) }
			<TextField
				label={ __( 'Name', 'aw-sourcing' ) }
				help={
					reg
						? sprintf(
								/* translators: %s: name in the workflow. */
								__(
									'In the workflow: %s. Leave empty for that name.',
									'aw-sourcing'
								),
								reg.name
							)
						: ''
				}
				value={ v.name }
				onChange={ put( 'name' ) }
			/>
			<TextField
				label={ __( 'What it does', 'aw-sourcing' ) }
				help={
					reg
						? __(
								"Leave empty for the registry's description.",
								'aw-sourcing'
							)
						: ''
				}
				value={ v.what }
				onChange={ put( 'what' ) }
				input={ { rows: 3 } }
			/>
			<SelectField
				label={ __( 'Group', 'aw-sourcing' ) }
				value={ v.group }
				options={ w.groups }
				onChange={ put( 'group' ) }
			/>
			<TextField
				label={ __( 'Or a new group', 'aw-sourcing' ) }
				help={ __(
					'A new group is added to the list (with a generic icon).',
					'aw-sourcing'
				) }
				value={ v.group_new }
				onChange={ put( 'group_new' ) }
			/>
			<TextField
				label={ __( 'Late after (hours)', 'aw-sourcing' ) }
				help={
					w.planned
						? __(
								'Blank: never late. Planned workers never raise an alert; this applies once the workflow is in the registry.',
								'aw-sourcing'
							)
						: sprintf(
								/* translators: %s: late limit in the registry. */
								__(
									'Blank: never late (no alert). In the registry: %s.',
									'aw-sourcing'
								),
								reg?.late || ''
							)
				}
				value={ v.late }
				onChange={ put( 'late' ) }
				input={ { type: 'number', min: '1', step: '1' } }
			/>
			{ w.planned && (
				<TextField
					label={ __( 'Schedule wanted', 'aw-sourcing' ) }
					help={ __(
						'In words, for whoever builds the workflow (e.g. daily at 06:00 IST).',
						'aw-sourcing'
					) }
					value={ v.schedule }
					onChange={ put( 'schedule' ) }
				/>
			) }
			<SettingsBlock
				title={ __( 'Keys', 'aw-sourcing' ) }
				description={ __(
					'Ticked: this worker may use the key. Unticking a key that is for all workers makes it for every other worker. Keys show only their last 4 characters.',
					'aw-sourcing'
				) }
			>
				{ w.keys.length ? (
					<fieldset className="kits-shell-checks">
						<legend className="screen-reader-text">
							{ __( 'Keys', 'aw-sourcing' ) }
						</legend>
						{ w.keys.map( ( k ) => (
							<label key={ k.id } htmlFor={ `sw-key-${ k.id }` }>
								<input
									id={ `sw-key-${ k.id }` }
									type="checkbox"
									checked={ v.keys.includes( k.id ) }
									onChange={ ( e ) =>
										tick( k.id, e.target.checked )
									}
								/>{ ' ' }
								{ k.short }{ ' ' }
								<span className="kits-shell-field__help">
									({ k.scope })
								</span>
							</label>
						) ) }
					</fieldset>
				) : (
					<p>
						{ __( 'No keys saved.', 'aw-sourcing' ) }{ ' ' }
						<a href={ w.addKey }>
							{ __( 'Add New Key', 'aw-sourcing' ) }
						</a>
					</p>
				) }
			</SettingsBlock>
		</SettingsGroup>
	);
}

/**
 * A worker's reported runs, newest first, read-only.
 *
 * @param {Object}   props
 * @param {string}   props.id     Worker ID.
 * @param {Function} props.onBack Back to the list.
 */
function WorkerRuns( { id, onBack } ) {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( '' );
	const load = () => {
		setError( '' );
		apiFetch( {
			path: path( `workers/${ encodeURIComponent( id ) }/runs` ),
		} )
			.then( setData )
			.catch( ( e ) => setError( errText( e ) ) );
	};
	useEffect( load, [ id ] );
	if ( error ) {
		return <ErrorState message={ error } onRetry={ load } />;
	}
	if ( ! data ) {
		return (
			<LoadingState label={ __( 'Loading the runs…', 'aw-sourcing' ) } />
		);
	}
	return (
		<SectionCard
			id="worker-runs"
			title={ sprintf(
				/* translators: %s: worker name. */
				__( 'Runs: %s', 'aw-sourcing' ),
				data.name
			) }
			description={ sprintf(
				/* translators: %d: number of runs kept. */
				__(
					'The last %d runs the workflow reported (start and end), newest first.',
					'aw-sourcing'
				),
				data.keep
			) }
			actions={
				<>
					<Button onClick={ onBack }>
						{ __( 'Back to the workers', 'aw-sourcing' ) }
					</Button>
					{ data.githubUrl && (
						<a className="kits-shell-link" href={ data.githubUrl }>
							{ __( 'Runs on GitHub', 'aw-sourcing' ) }
						</a>
					) }
				</>
			}
		>
			<DataList
				label={ __( 'Runs', 'aw-sourcing' ) }
				rowTitle="id"
				columns={ [
					{
						key: 'id',
						label: __( 'Run', 'aw-sourcing' ),
						primary: true,
						render: ( r ) => (
							<>
								{ r.link ? (
									<a href={ r.link }>{ r.id }</a>
								) : (
									r.id
								) }
								{ r.attempt > 1
									? ` ${ sprintf(
											/* translators: %d: attempt number. */
											__( '(attempt %d)', 'aw-sourcing' ),
											r.attempt
										) }`
									: '' }
							</>
						),
					},
					{
						key: 'when',
						label: __( 'Started', 'aw-sourcing' ),
						render: ( r ) => r.when || '—',
					},
					{
						key: 'took',
						label: __( 'Took', 'aw-sourcing' ),
						render: ( r ) => r.took || '—',
					},
					{
						key: 'label',
						label: __( 'Result', 'aw-sourcing' ),
						render: ( r ) => (
							<Status tone={ r.tone } label={ r.label } />
						),
					},
					{
						key: 'event',
						label: __( 'Started by', 'aw-sourcing' ),
					},
					{
						key: 'counts',
						label: __( 'Counts', 'aw-sourcing' ),
						render: ( r ) => r.counts || '—',
					},
					{
						key: 'commit',
						label: __( 'Commit', 'aw-sourcing' ),
						render: ( r ) =>
							r.commit ? (
								<a href={ r.commitUrl }>
									<code>{ r.commit }</code>
								</a>
							) : (
								'—'
							),
					},
				] }
				rows={ data.rows }
				total={ data.rows.length }
				emptyTitle={
					data.planned
						? __( 'Not built yet: no runs.', 'aw-sourcing' )
						: __( 'No runs reported yet.', 'aw-sourcing' )
				}
			/>
		</SectionCard>
	);
}

const SCREENS = {
	dashboard: Dashboard,
	review: Review,
	enrichment: Enrichment,
	settings: Settings,
	workers: Workers,
};

function App() {
	const Screen = SCREENS[ app.screen ] || Dashboard;
	return (
		<AppShell header={ <Header /> }>
			<Screen />
		</AppShell>
	);
}

const root = document.getElementById( 'sourceweb-app' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
