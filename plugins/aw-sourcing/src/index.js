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
							onReject={ ( ids ) =>
								decide( { do: 'reject', ids } )
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
	[
		'edit',
		'runs',
		'view',
		'status',
		'kw',
		'part',
		'tagrule',
		'field',
		'overview',
	].forEach( ( k ) => u.searchParams.delete( k ) );
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

/* ---------- Sources ---------- */

const SOURCE_ACTIONS = {
	allow: __( 'Change to Allowed', 'aw-sourcing' ),
	neutral: __( 'Change to Neutral', 'aw-sourcing' ),
	block: __( 'Change to Blocked', 'aw-sourcing' ),
	press_add: __( 'Search here for coverage', 'aw-sourcing' ),
	press_remove: __( 'Stop searching here', 'aw-sourcing' ),
	relay_on: __( 'Relay on (fetch through this site)', 'aw-sourcing' ),
	relay_off: __( 'Relay off', 'aw-sourcing' ),
};

function Sources() {
	const [ sub, go ] = useSub( [ 'edit', 'view' ] );
	const [ msg, setMsg ] = useState( null );
	const back = ( m ) => {
		setMsg( m || null );
		go( {} );
	};
	if ( sub.edit ) {
		return (
			<SourceForm
				host={ 'new' === sub.edit ? '' : sub.edit }
				onDone={ back }
				go={ go }
			/>
		);
	}
	if ( 'feeds' === sub.view ) {
		return (
			<SectionCard
				id="feeds"
				title={ __( 'Feeds', 'aw-sourcing' ) }
				description={ __(
					'Every feed and source read by code (News, Library and Opportunity), with the site each belongs to.',
					'aw-sourcing'
				) }
				actions={
					<Button onClick={ () => back() }>
						{ __( 'Back to Sources', 'aw-sourcing' ) }
					</Button>
				}
			>
				<Feeds host="" go={ go } />
			</SectionCard>
		);
	}
	return <SourceList go={ go } msg={ msg } setMsg={ setMsg } />;
}

function SourceList( { go, msg, setMsg } ) {
	const first = new URLSearchParams( window.location.search ).get( 'status' );
	const [ q, setQ ] = useState( {
		view: first || '',
		search: '',
		orderby: '',
		order: 'desc',
		page: 1,
	} );
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ shorts, setShorts ] = useState( {} );
	const [ busy, setBusy ] = useState( false );
	const load = useCallback( () => {
		setLoading( true );
		setError( '' );
		apiFetch( { path: path( 'sources', q ) } )
			.then( ( r ) => {
				setData( r );
				setShorts( {} );
			} )
			.catch( ( e ) => setError( errText( e ) ) )
			.finally( () => setLoading( false ) );
	}, [ q ] );
	useEffect( load, [ load ] );
	const set = ( part ) => setQ( ( o ) => ( { ...o, page: 1, ...part } ) );
	const send = ( route, data2 ) => {
		setMsg( null );
		setBusy( true );
		return apiFetch( { path: path( route ), method: 'POST', data: data2 } )
			.then( ( r ) =>
				setMsg( {
					tone: r.changed ? 'success' : 'info',
					text: r.message,
				} )
			)
			.catch( ( e ) => setMsg( { tone: 'error', text: errText( e ) } ) )
			.finally( () => {
				setBusy( false );
				load();
			} );
	};
	const decide = ( action, hosts ) =>
		send( 'sources/decide', { action, hosts } );
	const remove = ( r ) => {
		if (
			// eslint-disable-next-line no-alert -- a plain browser confirm, as today's screen
			window.confirm(
				sprintf(
					/* translators: %s: host. */
					__(
						'Delete the source %s? This cannot be undone.',
						'aw-sourcing'
					),
					r.id
				)
			)
		) {
			decide( 'delete', [ r.id ] );
		}
	};
	const phase = data?.phase ?? 0;
	const bulk = Object.keys( SOURCE_ACTIONS )
		.filter(
			( k ) =>
				! ( k.startsWith( 'press_' ) && phase >= 2 ) &&
				! ( k.startsWith( 'relay_' ) && phase < 1 )
		)
		.map( ( k ) => ( { value: k, label: SOURCE_ACTIONS[ k ] } ) );
	const edited = Object.keys( shorts ).length > 0;
	const views = [
		...( data?.views || [] ),
		...( data
			? [
					{
						value: '__feeds',
						label: __( 'Feeds', 'aw-sourcing' ),
						count: data.feedCount,
					},
				]
			: [] ),
	];
	return (
		<SectionCard
			id="sources"
			title={ __( 'Sources', 'aw-sourcing' ) }
			description={ __(
				'Every host the workers have sent, and every site a feed or a source read by code comes from. Blocked publishers are refused; Neutral is the default for new hosts; Allowed publishers skip the keyword check. Short name: what cards show on the source chip.',
				'aw-sourcing'
			) }
			actions={
				<>
					<Button
						busy={ busy }
						disabled={ ! edited }
						onClick={ () =>
							send( 'sources/short', { short: shorts } )
						}
					>
						{ __( 'Save short names', 'aw-sourcing' ) }
					</Button>
					<a
						className="kits-shell-button is-primary"
						href={ subHref( { edit: 'new' } ) }
						onClick={ inPlace( go, { edit: 'new' } ) }
					>
						{ __( 'Add New Source', 'aw-sourcing' ) }
					</a>
				</>
			}
		>
			{ msg && (
				<Notice tone={ msg.tone } onDismiss={ () => setMsg( null ) }>
					{ msg.text }
				</Notice>
			) }
			{ data?.stale && (
				<Notice tone="info">
					{ __(
						'Search value: Search Console has not run within 28 days, so there are no figures for this window.',
						'aw-sourcing'
					) }
				</Notice>
			) }
			<DataList
				label={ __( 'Sources', 'aw-sourcing' ) }
				columns={ [
					{
						key: 'title',
						label: __( 'Site', 'aw-sourcing' ),
						sortable: true,
						primary: true,
						render: ( r ) => (
							<>
								<a
									href={ subHref( { edit: r.id } ) }
									onClick={ inPlace( go, { edit: r.id } ) }
								>
									<strong>{ r.id }</strong>
								</a>
								{ r.name && (
									<span className="kits-shell-field__help">
										{ ' ' }
										{ r.name }
									</span>
								) }
								{ 'block' === r.status && (
									<span className="kits-shell-field__help">
										{ ' ' }
										<Status
											tone="error"
											label={ __(
												'Blocked: new items from it are refused. Items already on the site are unchanged.',
												'aw-sourcing'
											) }
										/>
									</span>
								) }
							</>
						),
					},
					{
						key: 'short',
						label: __( 'Short name', 'aw-sourcing' ),
						render: ( r ) =>
							r.pub ? (
								<input
									type="text"
									maxLength="24"
									size="12"
									aria-label={ sprintf(
										/* translators: %s: host. */
										__(
											'Short name for %s',
											'aw-sourcing'
										),
										r.id
									) }
									value={ shorts[ r.id ] ?? r.short }
									onChange={ ( e ) =>
										setShorts( {
											...shorts,
											[ r.id ]: e.target.value,
										} )
									}
								/>
							) : (
								'—'
							),
					},
					{
						key: 'status',
						label: __( 'Status', 'aw-sourcing' ),
						sortable: true,
						render: ( r ) => (
							<>
								{ r.pub ? (
									<Status
										tone={
											{
												allow: 'success',
												block: 'error',
											}[ r.status ] || 'info'
										}
										label={ r.statusText }
									/>
								) : (
									<span className="kits-shell-field__help">
										{ r.statusText }
									</span>
								) }
								{ r.press && (
									<span className="kits-shell-field__help">
										{ ' ' }
										{ __(
											'Searched for coverage',
											'aw-sourcing'
										) }
									</span>
								) }
							</>
						),
					},
					{
						key: 'rating',
						label: __( 'Rating', 'aw-sourcing' ),
						render: ( r ) => {
							if ( ! r.rating ) {
								return '–';
							}
							return r.ratingUrl ? (
								<a href={ r.ratingUrl }>{ r.rating }</a>
							) : (
								r.rating
							);
						},
					},
					{
						key: 'dr',
						label: __( 'Domain Rating', 'aw-sourcing' ),
						sortable: true,
						render: ( r ) =>
							null === r.dr ? (
								'—'
							) : (
								<>
									{ r.dr }
									{ r.drAt && (
										<span className="kits-shell-field__help">
											{ ' ' }
											{ r.drAt }
										</span>
									) }
								</>
							),
					},
					{
						key: 'feeds',
						label: __( 'Feeds', 'aw-sourcing' ),
						sortable: true,
						render: ( r ) =>
							r.feeds ? (
								<a
									href={ subHref( { edit: r.id } ) }
									onClick={ inPlace( go, { edit: r.id } ) }
								>
									{ r.feedsText }
								</a>
							) : (
								'—'
							),
					},
					{
						key: 'relay',
						label: __( 'Relay', 'aw-sourcing' ),
						render: ( r ) => {
							if ( ! r.relay ) {
								return '—';
							}
							return (
								<>
									{ __( 'On', 'aw-sourcing' ) }
									<span className="kits-shell-field__help">
										{ ' ' }
										{ r.relayLast
											? sprintf(
													/* translators: %s: last result. */
													__(
														'Last: %s',
														'aw-sourcing'
													),
													r.relayLast
												)
											: __(
													'Not used yet',
													'aw-sourcing'
												) }
									</span>
								</>
							);
						},
					},
					{
						key: 'items',
						label: __(
							'Items (30 days / published)',
							'aw-sourcing'
						),
						sortable: true,
					},
					{
						key: 'search',
						label: __( 'Search value', 'aw-sourcing' ),
						sortable: true,
						render: ( r ) => r.search || '—',
					},
					{
						key: 'seo',
						label: __( 'SEO', 'aw-sourcing' ),
						render: ( r ) =>
							r.seoAt ? (
								<>
									{ r.seoTitle }
									{ r.seoDesc && (
										<span className="kits-shell-field__help">
											{ ' ' }
											{ r.seoDesc }
										</span>
									) }
									<span className="kits-shell-field__help">
										{ ' ' }
										{ r.seoAt }
									</span>
								</>
							) : (
								__( 'Not checked yet', 'aw-sourcing' )
							),
					},
					{
						key: 'last',
						label: __( 'Last seen', 'aw-sourcing' ),
						sortable: true,
						render: ( r ) => r.last || '—',
					},
				] }
				rows={ data?.rows || [] }
				total={ data?.total || 0 }
				page={ data?.page || 1 }
				pages={ data?.pages || 1 }
				onPage={ ( page ) => setQ( ( o ) => ( { ...o, page } ) ) }
				search={ q.search }
				onSearch={ ( search ) => set( { search } ) }
				searchLabel={ __( 'Search sources', 'aw-sourcing' ) }
				views={ views }
				view={ q.view }
				onView={ ( view ) =>
					'__feeds' === view
						? go( { view: 'feeds' } )
						: set( { view } )
				}
				sort={ { key: q.orderby, dir: q.order } }
				onSort={ ( orderby, order ) => set( { orderby, order } ) }
				bulkActions={ bulk }
				onBulk={ decide }
				rowActions={ ( r ) => [
					{
						label: __( 'Edit', 'aw-sourcing' ),
						onClick: () => go( { edit: r.id } ),
					},
					...( r.pub
						? [ 'allow', 'neutral', 'block' ]
								.filter( ( st ) => st !== r.status )
								.map( ( st ) => ( {
									label: {
										allow: __( 'Allow', 'aw-sourcing' ),
										neutral: __( 'Neutral', 'aw-sourcing' ),
										block: __( 'Block', 'aw-sourcing' ),
									}[ st ],
									tone: 'block' === st ? 'error' : '',
									onClick: () => decide( st, [ r.id ] ),
								} ) )
						: [] ),
					...( phase < 2
						? [
								{
									label: r.press
										? SOURCE_ACTIONS.press_remove
										: SOURCE_ACTIONS.press_add,
									onClick: () =>
										decide(
											r.press
												? 'press_remove'
												: 'press_add',
											[ r.id ]
										),
								},
							]
						: [] ),
					...( phase >= 1
						? [
								{
									label: r.relayOwn
										? __( 'Relay off', 'aw-sourcing' )
										: __( 'Relay on', 'aw-sourcing' ),
									onClick: () =>
										decide(
											r.relayOwn
												? 'relay_off'
												: 'relay_on',
											[ r.id ]
										),
								},
							]
						: [] ),
					...( r.canDelete
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
				emptyTitle={ __( 'No sources.', 'aw-sourcing' ) }
			/>
		</SectionCard>
	);
}

/**
 * Add a source or edit one, then its feeds.
 *
 * @param {Object}   props
 * @param {string}   props.host   Host, or '' to add.
 * @param {Function} props.onDone Back to the list, with a notice.
 * @param {Function} props.go     From useSub().
 */
function SourceForm( { host, onDone, go } ) {
	const [ s, setS ] = useState( null );
	const [ v, setV ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ msg, setMsg ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const load = () => {
		setError( '' );
		apiFetch( { path: path( 'sources/one', { host } ) } )
			.then( ( r ) => {
				setS( r );
				setV( {
					host: r.host,
					name: r.name,
					short: r.short,
					status: r.status,
					press: r.press,
					relay: r.relay,
				} );
			} )
			.catch( ( e ) => setError( errText( e ) ) );
	};
	useEffect( load, [ host ] );
	if ( error ) {
		return <ErrorState message={ error } onRetry={ load } />;
	}
	if ( ! s || ! v ) {
		return (
			<LoadingState
				label={ __( 'Loading the source…', 'aw-sourcing' ) }
			/>
		);
	}
	const put = ( k ) => ( val ) => setV( { ...v, [ k ]: val } );
	const save = () => {
		setBusy( true );
		setMsg( null );
		apiFetch( { path: path( 'sources/save' ), method: 'POST', data: v } )
			.then( ( r ) =>
				onDone( {
					tone: r.changed ? 'success' : 'info',
					text: r.message,
				} )
			)
			.catch( ( e ) => {
				setMsg( { tone: 'error', text: errText( e ) } );
				setBusy( false );
			} );
	};
	return (
		<>
			<SettingsGroup
				id="source-form"
				title={
					s.new
						? __( 'Add New Source', 'aw-sourcing' )
						: sprintf(
								/* translators: %s: host. */
								__( 'Edit Source: %s', 'aw-sourcing' ),
								s.host
							)
				}
				footer={
					<>
						<Button
							variant="primary"
							busy={ busy }
							onClick={ save }
						>
							{ s.new
								? __( 'Add Source', 'aw-sourcing' )
								: __( 'Update', 'aw-sourcing' ) }
						</Button>{ ' ' }
						<Button onClick={ () => onDone() }>
							{ __( 'Cancel', 'aw-sourcing' ) }
						</Button>
					</>
				}
			>
				{ msg && (
					<Notice
						tone={ msg.tone }
						onDismiss={ () => setMsg( null ) }
					>
						{ msg.text }
					</Notice>
				) }
				{ s.new ? (
					<TextField
						label={ __( 'Host', 'aw-sourcing' ) }
						help={ __(
							"The site's host or any address on it (https://www.example.com/news/ becomes example.com).",
							'aw-sourcing'
						) }
						value={ v.host }
						onChange={ put( 'host' ) }
						input={ { placeholder: 'example.com' } }
					/>
				) : (
					<p>
						<strong>{ s.host }</strong>{ ' ' }
						<a href={ `https://${ s.host }/` }>
							{ __( 'Visit site', 'aw-sourcing' ) }
						</a>
					</p>
				) }
				<TextField
					label={ __( 'Name', 'aw-sourcing' ) }
					help={ __(
						"The publisher's name, shown on item pages. Kept on publisher records only.",
						'aw-sourcing'
					) }
					value={ v.name }
					onChange={ put( 'name' ) }
				/>
				<TextField
					label={ __( 'Short name', 'aw-sourcing' ) }
					help={ __(
						'What cards show on the source chip (e.g. TOI, PIB, Sentinel).',
						'aw-sourcing'
					) }
					value={ v.short }
					onChange={ put( 'short' ) }
					input={ { maxLength: 24 } }
				/>
				<SelectField
					label={ __( 'Status', 'aw-sourcing' ) }
					help={ __(
						'Blocked publishers are refused when the workers send their items. Neutral is the default for new hosts the workers find. Allowed publishers skip the keyword check.',
						'aw-sourcing'
					) }
					value={ v.status }
					options={ s.options }
					onChange={ put( 'status' ) }
				/>
				{ s.pressShown && (
					<Switch
						label={ __(
							'Search here for coverage',
							'aw-sourcing'
						) }
						help={ __(
							'The press queries also run on this site.',
							'aw-sourcing'
						) }
						checked={ v.press }
						onChange={ put( 'press' ) }
					/>
				) }
				{ s.relayShown && (
					<Switch
						label={ __(
							'Fetch through this site (relay)',
							'aw-sourcing'
						) }
						help={
							__(
								"When this site refuses the workers (GitHub's servers) with 403, they ask this site to fetch the feed or page. Its subdomains are included.",
								'aw-sourcing'
							) +
							( s.relayLast
								? ' ' +
									sprintf(
										/* translators: %s: last result. */
										__(
											'Last relay result: %s.',
											'aw-sourcing'
										),
										s.relayLast
									)
								: '' )
						}
						checked={ v.relay }
						onChange={ put( 'relay' ) }
					/>
				) }
			</SettingsGroup>
			{ ! s.new && (
				<SectionCard
					id="source-feeds"
					title={ __( 'Feeds', 'aw-sourcing' ) }
					description={ __(
						"This site's feeds and sources read by code.",
						'aw-sourcing'
					) }
				>
					<Feeds host={ s.host } go={ go } />
				</SectionCard>
			) }
		</>
	);
}

/**
 * The feeds list: every feed, or one site's. On, off, keyword check and delete here; the feed form opens today's screen.
 *
 * @param {Object}   props
 * @param {string}   props.host One site, or ''.
 * @param {Function} props.go   From useSub().
 */
function Feeds( { host, go } ) {
	const [ q, setQ ] = useState( { view: '', search: '', page: 1 } );
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ msg, setMsg ] = useState( null );
	const load = useCallback( () => {
		setLoading( true );
		setError( '' );
		const view = q.view;
		apiFetch( {
			path: path( 'sources/feeds', {
				host,
				type: [ 'on', 'off' ].includes( view ) ? '' : view,
				state: [ 'on', 'off' ].includes( view ) ? view : '',
				search: q.search,
				page: q.page,
			} ),
		} )
			.then( setData )
			.catch( ( e ) => setError( errText( e ) ) )
			.finally( () => setLoading( false ) );
	}, [ q, host ] );
	useEffect( load, [ load ] );
	const set = ( part ) => setQ( ( o ) => ( { ...o, page: 1, ...part } ) );
	const op = ( o, ids ) => {
		setMsg( null );
		return apiFetch( {
			path: path( 'sources/feeds/decide' ),
			method: 'POST',
			data: { op: o, ids },
		} )
			.then( ( r ) =>
				setMsg( {
					tone: r.changed ? 'success' : 'info',
					text: r.message,
				} )
			)
			.catch( ( e ) => setMsg( { tone: 'error', text: errText( e ) } ) )
			.finally( load );
	};
	const remove = ( r ) => {
		if (
			// eslint-disable-next-line no-alert -- a plain browser confirm, as today's screen
			window.confirm(
				sprintf(
					/* translators: %s: feed name. */
					__(
						'Delete the feed "%s"? This cannot be undone.',
						'aw-sourcing'
					),
					r.title
				)
			)
		) {
			op( 'delete', [ r.id ] );
		}
	};
	return (
		<>
			{ msg && (
				<Notice tone={ msg.tone } onDismiss={ () => setMsg( null ) }>
					{ msg.text }
				</Notice>
			) }
			{ data && (
				<p>
					<a href={ data.addUrl }>
						{ __( 'Add New Feed', 'aw-sourcing' ) }
					</a>
				</p>
			) }
			<DataList
				label={ __( 'Feeds', 'aw-sourcing' ) }
				columns={ [
					{
						key: 'title',
						label: __( 'Name', 'aw-sourcing' ),
						primary: true,
						render: ( r ) => (
							<a href={ r.editUrl }>
								<strong>{ r.title }</strong>
							</a>
						),
					},
					{
						key: 'typeText',
						label: __( 'Type', 'aw-sourcing' ),
					},
					{
						key: 'address',
						label: __( 'Address', 'aw-sourcing' ),
						render: ( r ) => (
							<>
								<code>{ r.address }</code>
								{ r.note && (
									<span className="kits-shell-field__help">
										{ ' ' }
										{ r.note }
									</span>
								) }
							</>
						),
					},
					...( host
						? []
						: [
								{
									key: 'host',
									label: __( 'Publisher', 'aw-sourcing' ),
									render: ( r ) =>
										r.host ? (
											<a
												href={ subHref( {
													edit: r.host,
												} ) }
												onClick={ inPlace( go, {
													edit: r.host,
												} ) }
											>
												{ r.host }
											</a>
										) : (
											'—'
										),
								},
							] ),
					{
						key: 'on',
						label: __( 'On', 'aw-sourcing' ),
						render: ( r ) =>
							r.on ? (
								<Status
									tone="success"
									label={ __( 'On', 'aw-sourcing' ) }
								/>
							) : (
								<Status
									tone="info"
									label={ __( 'Off', 'aw-sourcing' ) }
								/>
							),
					},
					{
						key: 'filter',
						label: __( 'Keyword check', 'aw-sourcing' ),
					},
				] }
				rows={ data?.rows || [] }
				total={ data?.total || 0 }
				page={ data?.page || 1 }
				pages={ data?.pages || 1 }
				onPage={ ( page ) => setQ( ( o ) => ( { ...o, page } ) ) }
				search={ q.search }
				onSearch={ host ? undefined : ( search ) => set( { search } ) }
				searchLabel={ __( 'Search feeds', 'aw-sourcing' ) }
				views={ host ? [] : data?.views || [] }
				view={ q.view }
				onView={ ( view ) => set( { view } ) }
				bulkActions={ [
					{ value: 'on', label: __( 'Turn on', 'aw-sourcing' ) },
					{ value: 'off', label: __( 'Turn off', 'aw-sourcing' ) },
					{
						value: 'filter_on',
						label: __( 'Keyword check on', 'aw-sourcing' ),
					},
					{
						value: 'filter_off',
						label: __( 'Keyword check off', 'aw-sourcing' ),
					},
				] }
				onBulk={ op }
				rowActions={ ( r ) => [
					{ label: __( 'Edit', 'aw-sourcing' ), href: r.editUrl },
					{
						label: r.on
							? __( 'Turn off', 'aw-sourcing' )
							: __( 'Turn on', 'aw-sourcing' ),
						onClick: () => op( r.on ? 'off' : 'on', [ r.id ] ),
					},
					...( r.rss
						? [
								{
									label: r.filterOn
										? __(
												'Keyword check off',
												'aw-sourcing'
											)
										: __(
												'Keyword check on',
												'aw-sourcing'
											),
									onClick: () =>
										op(
											r.filterOn
												? 'filter_off'
												: 'filter_on',
											[ r.id ]
										),
								},
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
				emptyTitle={ __( 'No feeds.', 'aw-sourcing' ) }
			/>
		</>
	);
}

/* ---------- Keywords ---------- */

/**
 * Checkboxes for a list of values.
 *
 * @param {Object}   props
 * @param {string}   props.id       Id prefix.
 * @param {string}   props.legend   Legend (for screen readers).
 * @param {Array}    props.options  [ { value, label, help } ].
 * @param {Array}    props.value    Ticked values.
 * @param {Function} props.onChange New ticked values.
 */
function Checks( { id, legend, options, value, onChange } ) {
	return (
		<fieldset className="kits-shell-checks">
			<legend className="screen-reader-text">{ legend }</legend>
			{ options.map( ( o ) => (
				<label key={ o.value } htmlFor={ `${ id }-${ o.value }` }>
					<input
						id={ `${ id }-${ o.value }` }
						type="checkbox"
						checked={ value.includes( o.value ) }
						onChange={ ( e ) =>
							onChange(
								e.target.checked
									? [ ...value, o.value ]
									: value.filter( ( x ) => x !== o.value )
							)
						}
					/>{ ' ' }
					{ o.help ? <strong>{ o.label }</strong> : o.label }
					{ o.help && (
						<span className="kits-shell-field__help">
							{ ' — ' }
							{ o.help }
						</span>
					) }
				</label>
			) ) }
		</fieldset>
	);
}

/** The view's role, ticked first on Add New Keyword. */
const VIEW_ROLE = {
	include: 'Include',
	animal: 'Animal',
	context: 'Context',
	exclude: 'Exclude',
	search: 'Search',
	press: 'Press',
	category: 'Category word',
};

function Keywords() {
	const [ sub, go ] = useSub( [ 'edit', 'kw' ] );
	const [ msg, setMsg ] = useState( null );
	const back = ( m ) => {
		setMsg( m || null );
		go( {} );
	};
	if ( sub.edit ) {
		return (
			<KeywordForm
				id={ 'new' === sub.edit ? 0 : parseInt( sub.edit, 10 ) || 0 }
				onDone={ back }
				go={ go }
			/>
		);
	}
	if ( 'suggestions' === sub.kw ) {
		return <Suggestions onBack={ () => back() } />;
	}
	return <KeywordList go={ go } msg={ msg } setMsg={ setMsg } />;
}

/**
 * The relevance filter and aggregator switches, and the Press settings in the Press view.
 *
 * @param {Object}   props
 * @param {Object}   props.settings As GET keywords gives them.
 * @param {boolean}  props.press    Show the Press settings.
 * @param {Function} props.onSaved  New settings.
 */
function KeywordSettings( { settings, press, onSaved } ) {
	const [ v, setV ] = useState( settings );
	const [ msg, setMsg ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	useEffect( () => setV( settings ), [ settings ] );
	const dirty = JSON.stringify( v ) !== JSON.stringify( settings );
	const save = () => {
		setBusy( true );
		setMsg( null );
		apiFetch( {
			path: path( 'keywords/settings' ),
			method: 'POST',
			data: { ...v, press: press ? v.press : {} },
		} )
			.then( ( r ) => {
				setMsg( {
					tone: r.saved ? 'success' : 'info',
					text: r.message,
				} );
				onSaved( r.settings );
			} )
			.catch( ( e ) => setMsg( { tone: 'error', text: errText( e ) } ) )
			.finally( () => setBusy( false ) );
	};
	return (
		<SettingsGroup
			id="keyword-switches"
			title={ __( 'Keyword check', 'aw-sourcing' ) }
			footer={
				<Button
					variant="primary"
					busy={ busy }
					disabled={ ! dirty }
					onClick={ save }
				>
					{ __( 'Save changes', 'aw-sourcing' ) }
				</Button>
			}
		>
			{ msg && (
				<Notice tone={ msg.tone } onDismiss={ () => setMsg( null ) }>
					{ msg.text }
				</Notice>
			) }
			<Switch
				label={ __( 'Relevance filter', 'aw-sourcing' ) }
				help={ __(
					'Off: every item from a non-blocked publisher is kept.',
					'aw-sourcing'
				) }
				checked={ v.enabled }
				onChange={ ( on ) => setV( { ...v, enabled: on } ) }
			/>
			<Switch
				label={ __(
					'Aggregator feeds: only allowed publishers',
					'aw-sourcing'
				) }
				help={ __(
					'Google News, Apple News and similar: items from Neutral publishers are refused. Keep off unless the allow list is complete.',
					'aw-sourcing'
				) }
				checked={ v.gnews_allow_only }
				onChange={ ( on ) => setV( { ...v, gnews_allow_only: on } ) }
			/>
			{ press && (
				<SettingsBlock
					title={ __( 'Press discovery', 'aw-sourcing' ) }
					description={ __(
						'The queries are the Press keywords below: Google News and Google Search for coverage of Animal Writes.',
						'aw-sourcing'
					) }
				>
					<Switch
						label={ __( 'Press discovery', 'aw-sourcing' ) }
						checked={ v.press.enabled }
						onChange={ ( on ) =>
							setV( { ...v, press: { ...v.press, enabled: on } } )
						}
					/>
					<TextField
						label={ __( 'Every (days)', 'aw-sourcing' ) }
						value={ String( v.press.every_days ) }
						onChange={ ( d ) =>
							setV( {
								...v,
								press: { ...v.press, every_days: d },
							} )
						}
						input={ {
							type: 'number',
							min: '1',
							max: '60',
							step: '1',
						} }
					/>
				</SettingsBlock>
			) }
		</SettingsGroup>
	);
}

/** Test a headline: kept or dropped, and why. */
function HeadlineTest() {
	const [ text, setText ] = useState( '' );
	const [ res, setRes ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const test = () => {
		setBusy( true );
		apiFetch( { path: path( 'keywords/test', { text } ) } )
			.then( ( r ) => setRes( r ) )
			.catch( ( e ) => setRes( { error: errText( e ) } ) )
			.finally( () => setBusy( false ) );
	};
	return (
		<SettingsGroup
			id="keyword-test"
			title={ __( 'Test a headline', 'aw-sourcing' ) }
			footer={
				<Button
					busy={ busy }
					disabled={ '' === text.trim() }
					onClick={ test }
				>
					{ __( 'Test', 'aw-sourcing' ) }
				</Button>
			}
		>
			<TextField
				label={ __( 'Headline', 'aw-sourcing' ) }
				value={ text }
				onChange={ setText }
				input={ {
					placeholder: __( 'Paste a headline', 'aw-sourcing' ),
				} }
			/>
			{ res &&
				( res.error ? (
					<Notice tone="error">{ res.error }</Notice>
				) : (
					<p>
						<Status
							tone={ res.kept ? 'success' : 'error' }
							label={
								res.kept
									? __( 'Kept', 'aw-sourcing' )
									: __( 'Dropped', 'aw-sourcing' )
							}
						/>{ ' ' }
						({ res.why })
					</p>
				) ) }
		</SettingsGroup>
	);
}

function KeywordList( { go, msg, setMsg } ) {
	const first = new URLSearchParams( window.location.search );
	const [ q, setQ ] = useState( {
		view: first.get( 'view' ) || '',
		cat: first.get( 'cat' ) || '',
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
		apiFetch( { path: path( 'keywords', q ) } )
			.then( setData )
			.catch( ( e ) => setError( errText( e ) ) )
			.finally( () => setLoading( false ) );
	}, [ q ] );
	useEffect( load, [ load ] );
	const set = ( part ) => setQ( ( o ) => ( { ...o, page: 1, ...part } ) );
	const decide = ( op, ids ) => {
		setMsg( null );
		return apiFetch( {
			path: path( 'keywords/decide' ),
			method: 'POST',
			data: { op, ids },
		} )
			.then( ( r ) =>
				setMsg( {
					tone:
						( r.errors.length && 'error' ) ||
						( r.changed && 'success' ) ||
						'info',
					text: [ r.message, ...r.errors ].join( ' ' ),
				} )
			)
			.catch( ( e ) => setMsg( { tone: 'error', text: errText( e ) } ) )
			.finally( load );
	};
	const remove = ( ids, label ) => {
		if (
			// eslint-disable-next-line no-alert -- a plain browser confirm, as today's screen
			window.confirm(
				label
					? sprintf(
							/* translators: %s: keyword. */
							__(
								'Remove "%s" from sourcing? Its Sourcing and Press roles go; Search Insights keeps the record.',
								'aw-sourcing'
							),
							label
						)
					: sprintf(
							/* translators: %d: number of keywords. */
							_n(
								'Remove %d keyword from sourcing? Its Sourcing and Press roles go; Search Insights keeps the record.',
								'Remove %d keywords from sourcing? Their Sourcing and Press roles go; Search Insights keeps the records.',
								ids.length,
								'aw-sourcing'
							),
							ids.length
						)
			)
		) {
			decide( 'delete', ids );
		}
	};
	const roles = data?.roles || [];
	const bulk = [
		...roles.map( ( r ) => ( {
			value: `add_${ r.value }`,
			label: sprintf(
				/* translators: %s: role. */
				__( 'Add role: %s', 'aw-sourcing' ),
				r.label
			),
		} ) ),
		...roles.map( ( r ) => ( {
			value: `remove_${ r.value }`,
			label: sprintf(
				/* translators: %s: role. */
				__( 'Remove role: %s', 'aw-sourcing' ),
				r.label
			),
		} ) ),
		...( data?.categories || [] ).map( ( c ) => ( {
			value: `cat_${ c.value }`,
			label: sprintf(
				/* translators: %s: category. */
				__( 'Map to category: %s', 'aw-sourcing' ),
				c.label
			),
		} ) ),
		{ value: 'delete', label: __( 'Delete from sourcing', 'aw-sourcing' ) },
	];
	const views = [
		...( data?.views || [] ),
		...( data
			? [
					{
						value: '__suggestions',
						label: __(
							'Suggestions from Search Insights',
							'aw-sourcing'
						),
					},
				]
			: [] ),
	];
	const catName = ( data?.categories || [] ).find(
		( c ) => c.value === q.cat
	)?.label;
	const addNew = { edit: 'new', ...( q.view ? { view: q.view } : {} ) };
	return (
		<>
			{ data && (
				<KeywordSettings
					settings={ data.settings }
					press={ 'press' === q.view }
					onSaved={ ( s ) => setData( { ...data, settings: s } ) }
				/>
			) }
			<SectionCard
				id="keywords"
				title={ __( 'Keywords', 'aw-sourcing' ) }
				description={ __(
					'Every keyword with a Sourcing or Press role. English only for Sourcing and Press. Plurals and dots do not matter: "dogs" adds to "dog". Include and Exclude cannot go together.',
					'aw-sourcing'
				) }
				actions={
					<a
						className="kits-shell-button is-primary"
						href={ subHref( addNew ) }
						onClick={ inPlace( go, addNew ) }
					>
						{ __( 'Add New Keyword', 'aw-sourcing' ) }
					</a>
				}
			>
				{ msg && (
					<Notice
						tone={ msg.tone }
						onDismiss={ () => setMsg( null ) }
					>
						{ msg.text }
					</Notice>
				) }
				{ q.cat && (
					<Notice tone="info">
						{ sprintf(
							/* translators: %s: category. */
							__( 'Category: %s.', 'aw-sourcing' ),
							catName || q.cat
						) }{ ' ' }
						<Button onClick={ () => set( { cat: '' } ) }>
							{ __( 'Show every category', 'aw-sourcing' ) }
						</Button>
					</Notice>
				) }
				<DataList
					label={ __( 'Keywords', 'aw-sourcing' ) }
					columns={ [
						{
							key: 'title',
							label: __( 'Keyword', 'aw-sourcing' ),
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
									</a>
									{ r.langLabel && (
										<span className="kits-shell-field__help">
											{ ' — ' }
											{ r.langLabel }
										</span>
									) }
								</>
							),
						},
						{
							key: 'spellings',
							label: __( 'Other spellings', 'aw-sourcing' ),
						},
						{
							key: 'rolesText',
							label: __( 'Roles', 'aw-sourcing' ),
							render: ( r ) => r.rolesText || '—',
						},
						{
							key: 'categories',
							label: __( 'Categories', 'aw-sourcing' ),
							render: ( r ) =>
								r.categories.length
									? r.categories.map( ( c, i ) => (
											<span key={ c.slug }>
												{ i > 0 && ', ' }
												<Button
													variant="link"
													onClick={ () =>
														set( {
															view: 'category',
															cat: c.slug,
														} )
													}
												>
													{ c.name }
												</Button>
											</span>
										) )
									: '—',
						},
						{
							key: 'search',
							label: __( 'Google News search', 'aw-sourcing' ),
						},
						{
							key: 'items',
							label: __( 'Items (30 days)', 'aw-sourcing' ),
							sortable: true,
						},
						{
							key: 'impr',
							label: __( 'Impressions (28 days)', 'aw-sourcing' ),
							sortable: true,
						},
					] }
					rows={ data?.rows || [] }
					total={ data?.total || 0 }
					page={ data?.page || 1 }
					pages={ data?.pages || 1 }
					onPage={ ( page ) => setQ( ( o ) => ( { ...o, page } ) ) }
					search={ q.search }
					onSearch={ ( search ) => set( { search } ) }
					searchLabel={ __( 'Search keywords', 'aw-sourcing' ) }
					views={ views }
					view={ q.view }
					onView={ ( view ) =>
						'__suggestions' === view
							? go( { kw: 'suggestions' } )
							: set( { view } )
					}
					sort={ { key: q.orderby, dir: q.order } }
					onSort={ ( orderby, order ) => set( { orderby, order } ) }
					bulkActions={ bulk }
					onBulk={ ( op, ids ) =>
						'delete' === op ? remove( ids ) : decide( op, ids )
					}
					rowActions={ ( r ) => [
						{
							label: __( 'Edit', 'aw-sourcing' ),
							onClick: () => go( { edit: r.id } ),
						},
						...( 'en' === r.lang
							? [
									...roles
										.filter( ( x ) =>
											[
												data.include,
												data.exclude,
											].includes( x.value )
										)
										.map( ( x ) => {
											const has = r.roles & x.value; // eslint-disable-line no-bitwise -- role bits
											return {
												label: has
													? sprintf(
															/* translators: %s: role. */
															__(
																'Remove %s',
																'aw-sourcing'
															),
															x.label
														)
													: sprintf(
															/* translators: %s: role. */
															__(
																'Add %s',
																'aw-sourcing'
															),
															x.label
														),
												onClick: () =>
													decide(
														`${
															has
																? 'remove'
																: 'add'
														}_${ x.value }`,
														[ r.id ]
													),
											};
										} ),
									{
										label: __( 'Delete', 'aw-sourcing' ),
										tone: 'error',
										onClick: () =>
											remove( [ r.id ], r.title ),
									},
								]
							: [] ),
					] }
					loading={ loading }
					error={ error }
					onRetry={ load }
					emptyTitle={ __( 'No keywords.', 'aw-sourcing' ) }
				/>
			</SectionCard>
			<HeadlineTest />
		</>
	);
}

/**
 * Add New Keyword or edit one: other spellings, roles, categories, sections, language, search and its query.
 *
 * @param {Object}   props
 * @param {number}   props.id     Record id, 0 to add.
 * @param {Function} props.onDone Back to the list, with a notice.
 * @param {Function} props.go     From useSub().
 */
function KeywordForm( { id, onDone, go } ) {
	const [ k, setK ] = useState( null );
	const [ v, setV ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ msg, setMsg ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const load = () => {
		setError( '' );
		apiFetch( { path: path( 'keywords/one', { id } ) } )
			.then( ( r ) => {
				const l = r.choices;
				const pre = new URLSearchParams( window.location.search ).get(
					'view'
				);
				const preRole = ( l.roles || [] ).find(
					( x ) => x.label === VIEW_ROLE[ pre ]
				);
				setK( r );
				setV( {
					label: r.label,
					variants: r.variants,
					lang: r.lang,
					roles: id || ! preRole ? r.roles : [ preRole.value ],
					categories: r.cats,
					sections: r.sections,
					search_on: !! r.search_on,
					query: r.query || '',
				} );
			} )
			.catch( ( e ) => setError( errText( e ) ) );
	};
	useEffect( load, [ id ] );
	if ( error ) {
		return <ErrorState message={ error } onRetry={ load } />;
	}
	if ( ! k || ! v ) {
		return (
			<LoadingState
				label={ __( 'Loading the keyword…', 'aw-sourcing' ) }
			/>
		);
	}
	const put = ( key ) => ( val ) => setV( { ...v, [ key ]: val } );
	const send = ( extra = {} ) => {
		setBusy( true );
		setMsg( null );
		apiFetch( {
			path: path( 'keywords/save' ),
			method: 'POST',
			data: { ...v, id, ...extra },
		} )
			.then( ( r ) =>
				onDone( {
					tone: 'none' === r.done ? 'info' : 'success',
					text: r.message,
				} )
			)
			.catch( ( e ) => {
				setBusy( false );
				setMsg( { tone: 'error', text: errText( e ) } );
				if ( ! id && e?.data?.id ) {
					go( { edit: e.data.id } ); // refused on a record that exists: open it, where it can be corrected
				}
			} );
	};
	const del = () => {
		if (
			// eslint-disable-next-line no-alert -- a plain browser confirm, as today's screen
			window.confirm(
				__(
					'Remove this keyword from sourcing? Its Sourcing and Press roles go; Search Insights keeps the record.',
					'aw-sourcing'
				)
			)
		) {
			send( { delete: true } );
		}
	};
	const langs = [
		{ value: 'en', label: __( 'English', 'aw-sourcing' ) },
		{ value: 'hinglish', label: __( 'Hinglish', 'aw-sourcing' ) },
		{ value: 'hi', label: __( 'Hindi', 'aw-sourcing' ) },
	];
	return (
		<SettingsGroup
			id="keyword-form"
			title={
				id
					? sprintf(
							/* translators: %s: keyword. */
							__( 'Edit keyword: %s', 'aw-sourcing' ),
							k.label
						)
					: __( 'Add New Keyword', 'aw-sourcing' )
			}
			footer={
				<>
					<Button
						variant="primary"
						busy={ busy }
						onClick={ () => send() }
					>
						{ id
							? __( 'Update', 'aw-sourcing' )
							: __( 'Add New Keyword', 'aw-sourcing' ) }
					</Button>{ ' ' }
					{ id > 0 && (
						<>
							<Button disabled={ busy } onClick={ del }>
								{ __( 'Delete', 'aw-sourcing' ) }
							</Button>{ ' ' }
						</>
					) }
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
			{ id ? (
				<RefTable
					caption={ __( 'The keyword', 'aw-sourcing' ) }
					head={ [
						__( 'Item', 'aw-sourcing' ),
						__( 'Value', 'aw-sourcing' ),
					] }
					rows={ [
						[
							__( 'Keyword', 'aw-sourcing' ),
							<>
								<strong>{ k.label }</strong>{ ' ' }
								<code>{ k.norm }</code>{ ' ' }
								<span className="kits-shell-field__help">
									{ __(
										'Fixed once saved: it is the record Search Insights keeps its figures on. Add other spellings below.',
										'aw-sourcing'
									) }
								</span>
							</>,
						],
						[
							__( 'Search Insights roles', 'aw-sourcing' ),
							sprintf(
								/* translators: %s: roles. */
								__( '%s (not changed here)', 'aw-sourcing' ),
								k.otherRoles
							),
						],
					] }
				/>
			) : (
				<TextField
					label={ __( 'Keyword', 'aw-sourcing' ) }
					help={ __(
						'English only for Sourcing and Press. Plurals and dots do not matter: "dogs" adds to "dog".',
						'aw-sourcing'
					) }
					value={ v.label }
					onChange={ put( 'label' ) }
					input={ { required: true } }
				/>
			) }
			<TextField
				label={ __( 'Other spellings', 'aw-sourcing' ) }
				help={ __(
					'One per line. Every spelling counts for every role. A trailing * matches the start of a word (steriliz* matches sterilized and sterilization).',
					'aw-sourcing'
				) }
				value={ v.variants }
				onChange={ put( 'variants' ) }
				input={ { rows: 4 } }
			/>
			<SelectField
				label={ __( 'Language', 'aw-sourcing' ) }
				help={ __(
					'Detected automatically; correct it when an English phrase was taken for Hinglish. Sourcing and Press roles are for English keywords only.',
					'aw-sourcing'
				) }
				value={ v.lang }
				options={ langs }
				onChange={ put( 'lang' ) }
			/>
			<SettingsBlock
				title={ __( 'Roles', 'aw-sourcing' ) }
				description={ __(
					'Include and Exclude cannot go together.',
					'aw-sourcing'
				) }
			>
				<Checks
					id="sw-kw-role"
					legend={ __( 'Roles', 'aw-sourcing' ) }
					options={ k.choices.roles }
					value={ v.roles }
					onChange={ put( 'roles' ) }
				/>
			</SettingsBlock>
			<SettingsBlock
				title={ __( 'Categories', 'aw-sourcing' ) }
				description={ __(
					"With the Category word role, an item that has this keyword gets these categories (the section's rule still limits them). An item a search of this keyword found gets them too.",
					'aw-sourcing'
				) }
			>
				<Checks
					id="sw-kw-cat"
					legend={ __( 'Categories', 'aw-sourcing' ) }
					options={ k.choices.categories }
					value={ v.categories }
					onChange={ put( 'categories' ) }
				/>
			</SettingsBlock>
			<SettingsBlock
				title={ __( 'Sections', 'aw-sourcing' ) }
				description={ __(
					'Where items its search finds go (News for a keyword search; Media & PR files them as press).',
					'aw-sourcing'
				) }
			>
				<Checks
					id="sw-kw-sec"
					legend={ __( 'Sections', 'aw-sourcing' ) }
					options={ k.choices.sections }
					value={ v.sections }
					onChange={ put( 'sections' ) }
				/>
			</SettingsBlock>
			{ id > 0 && (
				<>
					<Switch
						label={ __( 'Google News search', 'aw-sourcing' ) }
						help={ sprintf(
							/* translators: %s: feed ids. */
							__(
								'An English Include or Search keyword is searched every run. Searches: %s.',
								'aw-sourcing'
							),
							k.searches
						) }
						checked={ v.search_on }
						onChange={ put( 'search_on' ) }
					/>
					<TextField
						label={ __( 'Search query', 'aw-sourcing' ) }
						help={ sprintf(
							/* translators: %s: feed id. */
							__(
								'For the Search role: the words searched (the keyword itself when empty), or a whole Google News search address. Feed id: %s (fixed, so the items it found stay counted).',
								'aw-sourcing'
							),
							k.feedId
						) }
						value={ v.query }
						onChange={ put( 'query' ) }
						input={ { className: 'code' } }
					/>
				</>
			) }
		</SettingsGroup>
	);
}

/**
 * Suggestions from Search Insights: searches people made (20 impressions in 28 days) that no keyword covers.
 *
 * @param {Object}   props
 * @param {Function} props.onBack Back to the list.
 */
function Suggestions( { onBack } ) {
	const [ q, setQ ] = useState( {
		view: '',
		search: '',
		orderby: '',
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
		apiFetch( { path: path( 'keywords/suggestions', q ) } )
			.then( setData )
			.catch( ( e ) => setError( errText( e ) ) )
			.finally( () => setLoading( false ) );
	}, [ q ] );
	useEffect( load, [ load ] );
	const set = ( part ) => setQ( ( o ) => ( { ...o, page: 1, ...part } ) );
	const decide = ( op, qs ) => {
		setMsg( null );
		return apiFetch( {
			path: path( 'keywords/suggestions/decide' ),
			method: 'POST',
			data: { op, qs },
		} )
			.then( ( r ) =>
				setMsg( {
					tone: r.changed ? 'success' : 'info',
					text: r.message,
				} )
			)
			.catch( ( e ) => setMsg( { tone: 'error', text: errText( e ) } ) )
			.finally( load );
	};
	const ops = {
		include: __( 'Add as Include', 'aw-sourcing' ),
		exclude: __( 'Add as Exclude', 'aw-sourcing' ),
		search: __( 'Add as Search', 'aw-sourcing' ),
		dismiss: __( 'Dismiss', 'aw-sourcing' ),
		restore: __( 'Show again', 'aw-sourcing' ),
	};
	const dismissed = 'dismissed' === q.view;
	const bulk = Object.keys( ops )
		.filter( ( k ) => ( dismissed ? 'dismiss' !== k : 'restore' !== k ) )
		.map( ( k ) => ( { value: k, label: ops[ k ] } ) );
	return (
		<SectionCard
			id="suggestions"
			title={ __( 'Suggestions from Search Insights', 'aw-sourcing' ) }
			description={ __(
				'Searches people made (20 impressions or more in 28 days) that no keyword covers yet. Add one as a keyword, or dismiss it.',
				'aw-sourcing'
			) }
			actions={
				<Button onClick={ onBack }>
					{ __( 'Back to Keywords', 'aw-sourcing' ) }
				</Button>
			}
		>
			{ msg && (
				<Notice tone={ msg.tone } onDismiss={ () => setMsg( null ) }>
					{ msg.text }
				</Notice>
			) }
			{ data?.none && (
				<Notice tone="info">
					{ __(
						'Search Insights has no search data yet.',
						'aw-sourcing'
					) }
				</Notice>
			) }
			<DataList
				label={ __( 'Suggestions', 'aw-sourcing' ) }
				columns={ [
					{
						key: 'title',
						label: __( 'Search', 'aw-sourcing' ),
						sortable: true,
						primary: true,
						render: ( r ) => (
							<>
								<strong>{ r.title }</strong>
								{ r.rising && (
									<span className="kits-shell-field__help">
										{ ' ' }
										{ __( 'Rising', 'aw-sourcing' ) }
									</span>
								) }
							</>
						),
					},
					{
						key: 'impr',
						label: __( 'Impressions (28 days)', 'aw-sourcing' ),
						sortable: true,
					},
					{
						key: 'clicks',
						label: __( 'Clicks', 'aw-sourcing' ),
						sortable: true,
					},
					{
						key: 'pos',
						label: __( 'Position', 'aw-sourcing' ),
						sortable: true,
					},
				] }
				rows={ data?.rows || [] }
				total={ data?.total || 0 }
				page={ data?.page || 1 }
				pages={ data?.pages || 1 }
				onPage={ ( page ) => setQ( ( o ) => ( { ...o, page } ) ) }
				search={ q.search }
				onSearch={ ( search ) => set( { search } ) }
				searchLabel={ __( 'Search suggestions', 'aw-sourcing' ) }
				views={ data?.views || [] }
				view={ q.view }
				onView={ ( view ) => set( { view } ) }
				sort={ { key: q.orderby, dir: q.order } }
				onSort={ ( orderby, order ) => set( { orderby, order } ) }
				bulkActions={ bulk }
				onBulk={ decide }
				rowActions={ ( r ) =>
					bulk.map( ( b ) => ( {
						label: b.label,
						onClick: () => decide( b.value, [ r.id ] ),
					} ) )
				}
				loading={ loading }
				error={ error }
				onRetry={ load }
				emptyTitle={ __( 'No suggestions.', 'aw-sourcing' ) }
			/>
		</SectionCard>
	);
}

/* ---------- Rules ---------- */

const RULE_PARTS = [
	{ value: 'sections', label: __( 'Section rules', 'aw-sourcing' ) },
	{ value: 'summary', label: __( 'Summary rules', 'aw-sourcing' ) },
	{ value: 'enrich', label: __( 'Directory enrichment', 'aw-sourcing' ) },
];

/**
 * A save's answer as a notice: success, info (nothing changed) or the refusal.
 *
 * @param {Object} r Answer.
 */
const answerMsg = ( r ) => ( {
	tone: r.tone || ( r.saved || 'saved' === r.done ? 'success' : 'info' ),
	text: r.message,
} );

function Rules() {
	const [ sub, go ] = useSub( [ 'part', 'edit', 'tagrule', 'field' ] );
	const [ msg, setMsg ] = useState( null );
	const part = RULE_PARTS.some( ( p ) => p.value === sub.part )
		? sub.part
		: 'sections';
	const back = ( m ) => {
		setMsg( m || null );
		go( 'sections' === part ? {} : { part } );
	};
	if ( sub.edit ) {
		return <SectionForm type={ sub.edit } onDone={ back } />;
	}
	if ( sub.tagrule ) {
		return (
			<TagRuleForm
				tag={ 'new' === sub.tagrule ? '' : sub.tagrule }
				onDone={ back }
			/>
		);
	}
	if ( sub.field ) {
		return <FieldRuleForm field={ sub.field } onDone={ back } />;
	}
	const parts = (
		<ChipRow
			label={ __( 'Rules', 'aw-sourcing' ) }
			chips={ RULE_PARTS }
			value={ part }
			onChange={ ( p ) => {
				setMsg( null );
				go( 'sections' === p ? {} : { part: p } );
			} }
		/>
	);
	const notice = msg && (
		<Notice tone={ msg.tone } onDismiss={ () => setMsg( null ) }>
			{ msg.text }
		</Notice>
	);
	return (
		<>
			{ parts }
			{ notice }
			{ 'sections' === part && <SectionRules go={ go } /> }
			{ 'summary' === part && <SummaryRules /> }
			{ 'enrich' === part && <EnrichRules go={ go } /> }
		</>
	);
}

/**
 * A loader for a GET route: data, error, reload.
 *
 * @param {string} route Route.
 * @param {Object} args  Query arguments.
 */
function useRoute( route, args = {} ) {
	const key = JSON.stringify( args );
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ loading, setLoading ] = useState( true );
	const load = useCallback( () => {
		setLoading( true );
		setError( '' );
		apiFetch( { path: path( route, JSON.parse( key ) ) } )
			.then( setData )
			.catch( ( e ) => setError( errText( e ) ) )
			.finally( () => setLoading( false ) );
	}, [ route, key ] );
	useEffect( load, [ load ] );
	return { data, error, loading, load };
}

function SectionRules( { go } ) {
	const { data, error, loading, load } = useRoute( 'rules/sections' );
	const [ msg, setMsg ] = useState( null );
	const del = ( r ) => {
		if (
			// eslint-disable-next-line no-alert -- a plain browser confirm, as today's screen
			! window.confirm(
				sprintf(
					/* translators: %s: tag. */
					__(
						'Delete the tag rule for %s? The tag itself stays; items keep the tags they have.',
						'aw-sourcing'
					),
					r.title
				)
			)
		) {
			return;
		}
		apiFetch( {
			path: path( 'rules/tag/delete' ),
			method: 'POST',
			data: { tag: r.id },
		} )
			.then( ( res ) => setMsg( answerMsg( res ) ) )
			.catch( ( e ) => setMsg( { tone: 'error', text: errText( e ) } ) )
			.finally( load );
	};
	const empty = () => {};
	return (
		<>
			<SectionCard
				id="sections"
				title={ __( 'Section rules', 'aw-sourcing' ) }
				description={ __(
					"Which categories and tags each section uses. The workers pick only from these, and each section's filters show only these. A section with no category ticked uses every category.",
					'aw-sourcing'
				) }
			>
				<DataList
					label={ __( 'Sections', 'aw-sourcing' ) }
					columns={ [
						{
							key: 'title',
							label: __( 'Section', 'aw-sourcing' ),
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
									{ ! r.stored && (
										<span className="kits-shell-field__help">
											{ ' ' }
											{ __(
												'No rule saved yet: every category, no tags.',
												'aw-sourcing'
											) }
										</span>
									) }
								</>
							),
						},
						{
							key: 'categories',
							label: __( 'Allowed categories', 'aw-sourcing' ),
						},
						{
							key: 'tags',
							label: __( 'Allowed tags', 'aw-sourcing' ),
						},
						{
							key: 'worker',
							label: __( 'Workers file', 'aw-sourcing' ),
						},
						{
							key: 'summary',
							label: __( 'Summary rule', 'aw-sourcing' ),
						},
					] }
					rows={ data?.sections || [] }
					total={ data?.sections?.length || 0 }
					page={ 1 }
					pages={ 1 }
					onPage={ empty }
					rowActions={ ( r ) => [
						{
							label: __( 'Edit', 'aw-sourcing' ),
							onClick: () => go( { edit: r.id } ),
						},
					] }
					loading={ loading }
					error={ error }
					onRetry={ load }
					emptyTitle={ __( 'No sections.', 'aw-sourcing' ) }
				/>
			</SectionCard>
			<SectionCard
				id="tag-rules"
				title={ __( 'Tag rules', 'aw-sourcing' ) }
				description={ __(
					'An item gets the tag when its title or text has one of the phrases, in a section that allows the tag. An item that also matches a "Hold when" pattern, and has none of the "Unless" words, waits for a person with the hold reason.',
					'aw-sourcing'
				) }
				actions={
					<a
						className="kits-shell-button is-primary"
						href={ subHref( { tagrule: 'new' } ) }
						onClick={ inPlace( go, { tagrule: 'new' } ) }
					>
						{ __( 'Add New Rule', 'aw-sourcing' ) }
					</a>
				}
			>
				{ msg && (
					<Notice
						tone={ msg.tone }
						onDismiss={ () => setMsg( null ) }
					>
						{ msg.text }
					</Notice>
				) }
				<DataList
					label={ __( 'Tag rules', 'aw-sourcing' ) }
					columns={ [
						{
							key: 'title',
							label: __( 'Tag', 'aw-sourcing' ),
							primary: true,
							render: ( r ) => (
								<>
									<a
										href={ subHref( { tagrule: r.id } ) }
										onClick={ inPlace( go, {
											tagrule: r.id,
										} ) }
									>
										<strong>{ r.title }</strong>
									</a>{ ' ' }
									<code>{ r.slug }</code>
								</>
							),
						},
						{ key: 'any', label: __( 'Phrases', 'aw-sourcing' ) },
						{
							key: 'hold',
							label: __( 'Hold when', 'aw-sourcing' ),
							render: ( r ) =>
								r.hold.length
									? r.hold.map( ( h, i ) => (
											<span key={ i }>
												{ i > 0 && ', ' }
												<code>{ h }</code>
											</span>
										) )
									: '—',
						},
						{ key: 'unless', label: __( 'Unless', 'aw-sourcing' ) },
						{
							key: 'reason',
							label: __( 'Hold reason', 'aw-sourcing' ),
						},
						{
							key: 'sections',
							label: __(
								'Sections that may give it',
								'aw-sourcing'
							),
						},
					] }
					rows={ data?.tagRules || [] }
					total={ data?.tagRules?.length || 0 }
					page={ 1 }
					pages={ 1 }
					onPage={ empty }
					rowActions={ ( r ) => [
						{
							label: __( 'Edit', 'aw-sourcing' ),
							onClick: () => go( { tagrule: r.id } ),
						},
						{
							label: __( 'Delete', 'aw-sourcing' ),
							tone: 'error',
							onClick: () => del( r ),
						},
					] }
					loading={ loading }
					error={ error }
					onRetry={ load }
					emptyTitle={ __( 'No tag rules yet.', 'aw-sourcing' ) }
				/>
			</SectionCard>
		</>
	);
}

/**
 * The buttons of a form: Save, Cancel.
 *
 * @param {Object}   props
 * @param {string}   props.label  Save button.
 * @param {boolean}  props.busy   Saving.
 * @param {Function} props.onSave Save.
 * @param {Function} props.onDone Cancel.
 */
function FormButtons( { label, busy, onSave, onDone } ) {
	return (
		<>
			<Button variant="primary" busy={ busy } onClick={ onSave }>
				{ label }
			</Button>{ ' ' }
			<Button onClick={ () => onDone() }>
				{ __( 'Cancel', 'aw-sourcing' ) }
			</Button>
		</>
	);
}

/**
 * One form: load, edit a copy, save; back to the list with the notice on success, the refusal shown in place.
 *
 * @param {string}   route  GET route.
 * @param {Object}   args   Its arguments.
 * @param {Function} toForm Data → form values.
 */
function useForm( route, args, toForm ) {
	const { data, error, load } = useRoute( route, args );
	const [ v, setV ] = useState( null );
	const [ msg, setMsg ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	useEffect( () => {
		if ( data ) {
			setV( toForm( data ) );
		}
	}, [ data ] ); // eslint-disable-line react-hooks/exhaustive-deps -- toForm is the same every time
	const send = ( to, body, onDone ) => {
		setBusy( true );
		setMsg( null );
		apiFetch( { path: path( to ), method: 'POST', data: body } )
			.then( ( r ) => onDone( answerMsg( r ) ) )
			.catch( ( e ) => {
				setBusy( false );
				setMsg( { tone: 'error', text: errText( e ) } );
			} );
	};
	return { data, error, load, v, setV, msg, setMsg, busy, send };
}

/**
 * One section's allowed categories and tags.
 *
 * @param {Object}   props
 * @param {string}   props.type   Post type.
 * @param {Function} props.onDone Back with a notice.
 */
function SectionForm( { type, onDone } ) {
	const f = useForm( 'rules/section', { type }, ( d ) => ( {
		categories: d.categories,
		tags: d.tags,
		gone: d.gone,
	} ) );
	if ( f.error ) {
		return <ErrorState message={ f.error } onRetry={ f.load } />;
	}
	if ( ! f.data || ! f.v ) {
		return (
			<LoadingState
				label={ __( 'Loading the section…', 'aw-sourcing' ) }
			/>
		);
	}
	const d = f.data;
	const gone = ( k ) =>
		d.gone[ k ].map( ( slug ) => ( {
			value: `__gone:${ slug }`,
			label:
				'categories' === k
					? sprintf(
							/* translators: %s: slug. */
							__( '%s (no such category)', 'aw-sourcing' ),
							decodeURIComponent( slug )
						)
					: sprintf(
							/* translators: %s: slug. */
							__( '%s (no such tag)', 'aw-sourcing' ),
							decodeURIComponent( slug )
						),
		} ) );
	const ticked = ( k ) => [
		...f.v[ k ],
		...f.v.gone[ k ].map( ( s ) => `__gone:${ s }` ),
	];
	const tick = ( k ) => ( list ) =>
		f.setV( {
			...f.v,
			[ k ]: list.filter( ( x ) => ! x.startsWith( '__gone:' ) ),
			gone: {
				...f.v.gone,
				[ k ]: list
					.filter( ( x ) => x.startsWith( '__gone:' ) )
					.map( ( x ) => x.slice( 7 ) ),
			},
		} );
	return (
		<SettingsGroup
			id="section-form"
			title={ sprintf(
				/* translators: %s: section. */
				__( 'Edit section: %s', 'aw-sourcing' ),
				d.label
			) }
			footer={
				<FormButtons
					label={ __( 'Save section', 'aw-sourcing' ) }
					busy={ f.busy }
					onSave={ () =>
						f.send( 'rules/section/save', { type, ...f.v }, onDone )
					}
					onDone={ onDone }
				/>
			}
		>
			{ f.msg && <Notice tone={ f.msg.tone }>{ f.msg.text }</Notice> }
			<SettingsBlock
				title={ __( 'Allowed categories', 'aw-sourcing' ) }
				description={ __(
					'None ticked: every category.',
					'aw-sourcing'
				) }
			>
				<Checks
					id="sw-sec-cat"
					legend={ __( 'Allowed categories', 'aw-sourcing' ) }
					options={ [
						...d.choices.categories,
						...gone( 'categories' ),
					] }
					value={ ticked( 'categories' ) }
					onChange={ tick( 'categories' ) }
				/>
			</SettingsBlock>
			<SettingsBlock
				title={ __( 'Allowed tags', 'aw-sourcing' ) }
				description={ __(
					"The tags the workers may give this section's items, and its filters show.",
					'aw-sourcing'
				) }
			>
				<Checks
					id="sw-sec-tag"
					legend={ __( 'Allowed tags', 'aw-sourcing' ) }
					options={ [ ...d.choices.tags, ...gone( 'tags' ) ] }
					value={ ticked( 'tags' ) }
					onChange={ tick( 'tags' ) }
				/>
			</SettingsBlock>
		</SettingsGroup>
	);
}

/**
 * Add a tag rule or edit one.
 *
 * @param {Object}   props
 * @param {string}   props.tag    Tag slug, '' to add.
 * @param {Function} props.onDone Back with a notice.
 */
function TagRuleForm( { tag, onDone } ) {
	const f = useForm( 'rules/tag', { tag }, ( d ) => ( {
		tag: d.tag,
		any: d.any,
		hold: d.hold,
		unless: d.unless,
		hold_reason: d.hold_reason,
	} ) );
	if ( f.error ) {
		return <ErrorState message={ f.error } onRetry={ f.load } />;
	}
	if ( ! f.data || ! f.v ) {
		return (
			<LoadingState label={ __( 'Loading the rule…', 'aw-sourcing' ) } />
		);
	}
	const put = ( k ) => ( val ) => f.setV( { ...f.v, [ k ]: val } );
	return (
		<SettingsGroup
			id="tag-rule-form"
			title={
				tag
					? sprintf(
							/* translators: %s: tag. */
							__( 'Edit tag rule: %s', 'aw-sourcing' ),
							decodeURIComponent( tag )
						)
					: __( 'Add New Rule', 'aw-sourcing' )
			}
			footer={
				<FormButtons
					label={
						tag
							? __( 'Update', 'aw-sourcing' )
							: __( 'Add New Rule', 'aw-sourcing' )
					}
					busy={ f.busy }
					onSave={ () =>
						f.send(
							'rules/tag/save',
							{ ...f.v, original: tag },
							onDone
						)
					}
					onDone={ onDone }
				/>
			}
		>
			{ f.msg && <Notice tone={ f.msg.tone }>{ f.msg.text }</Notice> }
			<SelectField
				label={ __( 'Tag', 'aw-sourcing' ) }
				help={ __(
					'The tag an item gets when its title or text has one of the phrases (only in a section that allows the tag).',
					'aw-sourcing'
				) }
				value={ f.v.tag }
				options={ [
					...( tag
						? []
						: [
								{
									value: '',
									label: __( 'Choose a tag', 'aw-sourcing' ),
								},
							] ),
					...f.data.choices,
				] }
				onChange={ put( 'tag' ) }
			/>
			<TextField
				label={ __( 'Phrases', 'aw-sourcing' ) }
				help={ __(
					'One per line. An item with any of them gets the tag.',
					'aw-sourcing'
				) }
				value={ f.v.any }
				onChange={ put( 'any' ) }
				input={ { rows: 5 } }
			/>
			<TextField
				label={ __( 'Hold when', 'aw-sourcing' ) }
				help={ __(
					'One regular expression per line (case does not matter). An item that also matches one waits for a person.',
					'aw-sourcing'
				) }
				value={ f.v.hold }
				onChange={ put( 'hold' ) }
				input={ { rows: 2 } }
			/>
			<TextField
				label={ __( 'Unless', 'aw-sourcing' ) }
				help={ __(
					'One per line. An item with any of these words is not held.',
					'aw-sourcing'
				) }
				value={ f.v.unless }
				onChange={ put( 'unless' ) }
				input={ { rows: 3 } }
			/>
			<TextField
				label={ __( 'Hold reason', 'aw-sourcing' ) }
				help={ __(
					'What the person checking a held item should look at.',
					'aw-sourcing'
				) }
				value={ f.v.hold_reason }
				onChange={ put( 'hold_reason' ) }
				input={ { rows: 2 } }
			/>
		</SettingsGroup>
	);
}

/** The Summary rules form: lengths per section, quotes and the lead, each section's wording, every summary. */
function SummaryRules() {
	const f = useForm( 'rules/summary', {}, ( d ) => ( {
		...d.values,
		sections: Object.fromEntries(
			d.sections.map( ( s ) => [
				s.type,
				{
					min_words: String( s.min_words ),
					max_words: String( s.max_words ),
					structure: s.structure,
					attribution: s.attribution,
					inherit: s.inherit,
				},
			] )
		),
		_reset: false,
	} ) );
	const [ done, setDone ] = useState( null );
	if ( f.error ) {
		return <ErrorState message={ f.error } onRetry={ f.load } />;
	}
	if ( ! f.data || ! f.v ) {
		return (
			<LoadingState
				label={ __( 'Loading the summary rules…', 'aw-sourcing' ) }
			/>
		);
	}
	const d = f.data;
	const put = ( k ) => ( val ) => f.setV( { ...f.v, [ k ]: val } );
	const putSec = ( pt, k ) => ( val ) =>
		f.setV( {
			...f.v,
			sections: {
				...f.v.sections,
				[ pt ]: { ...f.v.sections[ pt ], [ k ]: val },
			},
		} );
	const save = () => {
		if (
			f.v._reset &&
			// eslint-disable-next-line no-alert -- a plain browser confirm
			! window.confirm(
				__(
					'Go back to the default summary rules? Every rule on this form is replaced.',
					'aw-sourcing'
				)
			)
		) {
			return;
		}
		f.send( 'rules/summary/save', { values: f.v }, ( m ) => {
			setDone( m );
			f.load();
		} );
	};
	const num = ( val, onChange, label ) => (
		<input
			type="number"
			min="1"
			max="400"
			size="4"
			aria-label={ label }
			value={ val }
			onChange={ ( e ) => onChange( e.target.value ) }
		/>
	);
	return (
		<SettingsGroup
			id="summary-rules"
			title={ __( 'Summary rules', 'aw-sourcing' ) }
			description={ __(
				'One rule set for every summary on the site: the workers write and check new summaries and same-story rewrites from these rules, and the site holds a News, Library or research item whose summary is over the maximum or not plain text in To review. A short summary is never held. A summary is never shorter than the search description, which is its lead sentence.',
				'aw-sourcing'
			) }
			footer={
				<Button variant="primary" busy={ f.busy } onClick={ save }>
					{ __( 'Save summary rules', 'aw-sourcing' ) }
				</Button>
			}
		>
			{ ( done || f.msg ) && (
				<Notice
					tone={ ( f.msg || done ).tone }
					onDismiss={ () => {
						setDone( null );
						f.setMsg( null );
					} }
				>
					{ ( f.msg || done ).text }
				</Notice>
			) }
			<SettingsBlock title={ __( 'Length per section', 'aw-sourcing' ) }>
				<RefTable
					caption={ __( 'Length per section', 'aw-sourcing' ) }
					head={ [
						__( 'Section', 'aw-sourcing' ),
						__( 'Minimum words', 'aw-sourcing' ),
						__( 'Maximum words', 'aw-sourcing' ),
						__( 'Rule', 'aw-sourcing' ),
						__(
							'Published summaries below the minimum',
							'aw-sourcing'
						),
					] }
					rows={ d.sections.map( ( s ) => {
						const sv = f.v.sections[ s.type ];
						return [
							<>
								{ s.label } <code>{ s.type }</code>
							</>,
							num(
								sv.min_words,
								putSec( s.type, 'min_words' ),
								`${ s.label } ${ __(
									'minimum words',
									'aw-sourcing'
								) }`
							),
							num(
								sv.max_words,
								putSec( s.type, 'max_words' ),
								`${ s.label } ${ __(
									'maximum words',
									'aw-sourcing'
								) }`
							),
							s.own ? (
								__( 'Its own', 'aw-sourcing' )
							) : (
								<label htmlFor={ `sw-sr-inh-${ s.type }` }>
									<input
										id={ `sw-sr-inh-${ s.type }` }
										type="checkbox"
										checked={ !! sv.inherit }
										onChange={ ( e ) =>
											putSec(
												s.type,
												'inherit'
											)( e.target.checked )
										}
									/>{ ' ' }
									{ __( 'Inherits News', 'aw-sourcing' ) }
								</label>
							),
							s.below,
						];
					} ) }
				/>
			</SettingsBlock>
			<SettingsBlock title={ __( 'Quotes and the lead', 'aw-sourcing' ) }>
				{ Object.keys( d.fields.numbers ).map( ( k ) => (
					<TextField
						key={ k }
						label={ d.fields.numbers[ k ] }
						value={ String( f.v[ k ] ) }
						onChange={ put( k ) }
						input={ {
							type: 'number',
							min: '1',
							max: '400',
							step: '1',
						} }
					/>
				) ) }
			</SettingsBlock>
			{ d.sections.map( ( s ) => (
				<SettingsBlock
					key={ s.type }
					title={
						f.v.sections[ s.type ].inherit
							? sprintf(
									/* translators: %s: section. */
									__(
										'%s (inherits News: these fields apply once it has its own rule)',
										'aw-sourcing'
									),
									s.label
								)
							: s.label
					}
				>
					<TextField
						label={ __( 'Structure', 'aw-sourcing' ) }
						value={ f.v.sections[ s.type ].structure }
						onChange={ putSec( s.type, 'structure' ) }
						input={ { rows: 4 } }
					/>
					<TextField
						label={ __( 'Attribution', 'aw-sourcing' ) }
						value={ f.v.sections[ s.type ].attribution }
						onChange={ putSec( s.type, 'attribution' ) }
						input={ { rows: 3 } }
					/>
				</SettingsBlock>
			) ) }
			<SettingsBlock title={ __( 'Every summary', 'aw-sourcing' ) }>
				{ Object.keys( d.fields.lists ).map( ( k ) => (
					<TextField
						key={ k }
						label={ d.fields.lists[ k ][ 0 ] }
						help={ d.fields.lists[ k ][ 1 ] }
						value={ f.v[ k ] }
						onChange={ put( k ) }
						input={ { rows: 'general' === k ? 12 : 5 } }
					/>
				) ) }
				{ Object.keys( d.fields.texts ).map( ( k ) => (
					<TextField
						key={ k }
						label={ d.fields.texts[ k ] }
						value={ f.v[ k ] }
						onChange={ put( k ) }
						input={ { rows: 3 } }
					/>
				) ) }
			</SettingsBlock>
			<Switch
				label={ __( 'Go back to the default rules', 'aw-sourcing' ) }
				help={ __(
					'On Save, every rule on this form goes back to its default.',
					'aw-sourcing'
				) }
				checked={ !! f.v._reset }
				onChange={ put( '_reset' ) }
			/>
		</SettingsGroup>
	);
}

function EnrichRules( { go } ) {
	const { data, error, loading, load } = useRoute( 'rules/enrich' );
	const [ pos, setPos ] = useState( {} );
	const [ msg, setMsg ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	useEffect( () => {
		if ( data ) {
			setPos(
				Object.fromEntries(
					data.priority.map( ( p ) => [ p.key, p.pos ] )
				)
			);
		}
	}, [ data ] );
	const settings = ( app.tabs || [] ).find( ( t ) => 'settings' === t.slug );
	const saveOrder = () => {
		setBusy( true );
		setMsg( null );
		apiFetch( {
			path: path( 'rules/priority' ),
			method: 'POST',
			data: { pos },
		} )
			.then( ( r ) => setMsg( answerMsg( r ) ) )
			.catch( ( e ) => setMsg( { tone: 'error', text: errText( e ) } ) )
			.finally( () => {
				setBusy( false );
				load();
			} );
	};
	if ( error ) {
		return <ErrorState message={ error } onRetry={ load } />;
	}
	return (
		<>
			{ settings && (
				<Notice tone="info">
					{ __(
						'The sources, the Google key and the monthly cap are on Settings, under Connected services.',
						'aw-sourcing'
					) }{ ' ' }
					<a href={ settings.url }>
						{ __( 'Open Settings', 'aw-sourcing' ) }
					</a>
				</Notice>
			) }
			<SettingsGroup
				id="trust-order"
				title={ __( 'Trust order', 'aw-sourcing' ) }
				description={ __(
					"When sources give different values for a field, the organisation's own claim wins first, then the value most sources agree on, then the source higher in this order. 1 is trusted most. Leave a source empty to rank it after every numbered one.",
					'aw-sourcing'
				) }
				footer={
					<Button busy={ busy } onClick={ saveOrder }>
						{ __( 'Save trust order', 'aw-sourcing' ) }
					</Button>
				}
			>
				{ msg && (
					<Notice
						tone={ msg.tone }
						onDismiss={ () => setMsg( null ) }
					>
						{ msg.text }
					</Notice>
				) }
				{ ! data && (
					<LoadingState
						label={ __( 'Loading the rules…', 'aw-sourcing' ) }
					/>
				) }
				{ ( data?.priority || [] ).map( ( p ) => (
					<TextField
						key={ p.key }
						label={ p.label }
						help={ p.key }
						value={ pos[ p.key ] ?? '' }
						onChange={ ( val ) =>
							setPos( { ...pos, [ p.key ]: val } )
						}
						input={ { type: 'number', min: '1', max: '99' } }
					/>
				) ) }
			</SettingsGroup>
			<SectionCard
				id="field-rules"
				title={ __( 'Field rules', 'aw-sourcing' ) }
				description={ __(
					'Every listing field: the enrichment fields and the fields of the listing field group. A claimed or verified listing is never changed, and a low-confidence value always waits for review; otherwise each field follows its Apply rule.',
					'aw-sourcing'
				) }
			>
				<DataList
					label={ __( 'Field rules', 'aw-sourcing' ) }
					columns={ [
						{
							key: 'title',
							label: __( 'Field', 'aw-sourcing' ),
							primary: true,
							render: ( r ) => (
								<>
									<a
										href={ subHref( {
											part: 'enrich',
											field: r.id,
										} ) }
										onClick={ inPlace( go, {
											part: 'enrich',
											field: r.id,
										} ) }
									>
										<strong>{ r.title }</strong>
									</a>{ ' ' }
									<code>{ r.id }</code>
									{ r.note && (
										<span className="kits-shell-field__help">
											{ ' ' }
											{ r.note }
										</span>
									) }
								</>
							),
						},
						{ key: 'group', label: __( 'Group', 'aw-sourcing' ) },
						{
							key: 'store',
							label: __( 'Stored in', 'aw-sourcing' ),
						},
						{ key: 'on', label: __( 'On', 'aw-sourcing' ) },
						{
							key: 'sources',
							label: __( 'May be filled by', 'aw-sourcing' ),
						},
						{ key: 'multi', label: __( 'Values', 'aw-sourcing' ) },
						{
							key: 'official',
							label: __( 'Official source', 'aw-sourcing' ),
						},
						{
							key: 'ai',
							label: __( 'Read by the AI', 'aw-sourcing' ),
						},
						{
							key: 'invite',
							label: __( 'Invite to claim', 'aw-sourcing' ),
						},
						{ key: 'apply', label: __( 'Apply', 'aw-sourcing' ) },
					] }
					rows={ data?.fields || [] }
					total={ data?.fields?.length || 0 }
					page={ 1 }
					pages={ 1 }
					onPage={ () => {} }
					rowActions={ ( r ) => [
						{
							label: __( 'Edit', 'aw-sourcing' ),
							onClick: () =>
								go( { part: 'enrich', field: r.id } ),
						},
					] }
					loading={ loading }
					error={ error }
					onRetry={ load }
					emptyTitle={ __( 'No fields.', 'aw-sourcing' ) }
				/>
			</SectionCard>
		</>
	);
}

/**
 * One field's rule.
 *
 * @param {Object}   props
 * @param {string}   props.field  Field key.
 * @param {Function} props.onDone Back with a notice.
 */
function FieldRuleForm( { field, onDone } ) {
	const f = useForm( 'rules/field', { field }, ( d ) => ( {
		...d.rule,
		multi: String( d.rule.multi ),
	} ) );
	if ( f.error ) {
		return <ErrorState message={ f.error } onRetry={ f.load } />;
	}
	if ( ! f.data || ! f.v ) {
		return (
			<LoadingState label={ __( 'Loading the field…', 'aw-sourcing' ) } />
		);
	}
	const d = f.data;
	const put = ( k ) => ( val ) => f.setV( { ...f.v, [ k ]: val } );
	return (
		<SettingsGroup
			id="field-rule-form"
			title={ sprintf(
				/* translators: %s: field. */
				__( 'Edit field rule: %s', 'aw-sourcing' ),
				d.label
			) }
			description={ `${ d.field } · ${ d.group } · ${ d.store }` }
			footer={
				<FormButtons
					label={ __( 'Update', 'aw-sourcing' ) }
					busy={ f.busy }
					onSave={ () =>
						f.send(
							'rules/field/save',
							{ field, rule: f.v },
							onDone
						)
					}
					onDone={ onDone }
				/>
			}
		>
			{ f.msg && <Notice tone={ f.msg.tone }>{ f.msg.text }</Notice> }
			<Switch
				label={ __(
					'The worker may propose values for this field',
					'aw-sourcing'
				) }
				help={ d.why }
				checked={ !! f.v.on && ! d.why }
				disabled={ !! d.why }
				onChange={ put( 'on' ) }
			/>
			<SettingsBlock
				title={ __( 'May be filled by', 'aw-sourcing' ) }
				description={ __( 'None ticked: any source.', 'aw-sourcing' ) }
			>
				<Checks
					id="sw-fr-src"
					legend={ __( 'May be filled by', 'aw-sourcing' ) }
					options={ d.choices.sources }
					value={ f.v.sources }
					onChange={ put( 'sources' ) }
				/>
			</SettingsBlock>
			<TextField
				label={ __( 'Values', 'aw-sourcing' ) }
				help={ __(
					'0: one value, and sources that disagree make it low confidence. A number: up to that many different values, never low for disagreeing (phone numbers).',
					'aw-sourcing'
				) }
				value={ f.v.multi }
				onChange={ put( 'multi' ) }
				input={ { type: 'number', min: '0', max: '5' } }
			/>
			<SelectField
				label={ __( 'Official source', 'aw-sourcing' ) }
				help={ __(
					'Its value alone is high confidence (the AWBI list for the address and PIN code).',
					'aw-sourcing'
				) }
				value={ f.v.official }
				options={ [
					{ value: '', label: __( 'None', 'aw-sourcing' ) },
					...d.choices.sources,
				] }
				onChange={ put( 'official' ) }
			/>
			<Switch
				label={ __( 'Read by the AI', 'aw-sourcing' ) }
				help={ __(
					'Values the AI reads from pages: at most medium confidence unless the organisation said so.',
					'aw-sourcing'
				) }
				checked={ !! f.v.ai }
				onChange={ put( 'ai' ) }
			/>
			<Switch
				label={ __( 'Invite to claim', 'aw-sourcing' ) }
				help={ __(
					'When only Google has it, the listing is flagged "Invite to claim".',
					'aw-sourcing'
				) }
				checked={ !! f.v.invite }
				onChange={ put( 'invite' ) }
			/>
			<Switch
				label={ __( 'Profile link', 'aw-sourcing' ) }
				help={ __(
					'A social profile: the website linking to it and the page preview count as one source.',
					'aw-sourcing'
				) }
				checked={ !! f.v.social }
				onChange={ put( 'social' ) }
			/>
			<SelectField
				label={ __( 'Apply', 'aw-sourcing' ) }
				help={ __(
					'Claimed and verified listings, and low-confidence values, always wait for review.',
					'aw-sourcing'
				) }
				value={ f.v.apply }
				options={ d.choices.apply }
				onChange={ put( 'apply' ) }
			/>
		</SettingsGroup>
	);
}

/* ---------- AI models ---------- */

const KEY_TONE = { ok: 'success', wait: 'warn', bad: 'error' };

/** AI models: the keys list, Add New Key, Edit key, Keys overview. */
function AiModels() {
	const [ sub, go ] = useSub( [ 'edit', 'overview' ] );
	const [ msg, setMsg ] = useState( null );
	const back = ( m ) => {
		setMsg( m || null );
		go( {} );
	};
	if ( sub.edit ) {
		return <AiKeyForm id={ sub.edit } onDone={ back } />;
	}
	if ( sub.overview ) {
		return <AiOverview onBack={ () => back() } />;
	}
	return <AiKeyList go={ go } msg={ msg } setMsg={ setMsg } />;
}

/** At a glance, the workers and their keys, the daily caps, keys by group. */
function AiGlance() {
	const { data, error, loading, load } = useRoute( 'ai' );
	if ( error ) {
		return <ErrorState message={ error } onRetry={ load } />;
	}
	if ( loading && ! data ) {
		return <LoadingState label={ __( 'Loading…', 'aw-sourcing' ) } />;
	}
	return (
		<>
			<SectionCard
				id="ai-glance"
				title={ __( 'At a glance', 'aw-sourcing' ) }
			>
				<RefTable
					caption={ __( 'At a glance', 'aw-sourcing' ) }
					head={ [
						__( 'What', 'aw-sourcing' ),
						__( 'Now', 'aw-sourcing' ),
					] }
					rows={ data.glance }
				/>
				<details>
					<summary>
						{ sprintf(
							/* translators: %d: number of workers. */
							__(
								'The %d workers and the keys each can use',
								'aw-sourcing'
							),
							data.workers.length
						) }
					</summary>
					<RefTable
						caption={ __( 'Workers', 'aw-sourcing' ) }
						head={ [
							__( 'Worker', 'aw-sourcing' ),
							__( 'Keys it can use', 'aw-sourcing' ),
							__( 'Used AI in the last 24 h', 'aw-sourcing' ),
						] }
						rows={ data.workers }
					/>
				</details>
			</SectionCard>
			<SectionCard
				id="ai-caps"
				title={ __( 'Daily caps on free models', 'aw-sourcing' ) }
				description={ __(
					'Free models have a daily limit per account, not per key: OpenRouter allows 50 free requests a day per account (1,000 once the account has 10 credits), however many keys it has. The news workers share each account’s requests out over the day and stop calling an account once it reaches the limit. The limit resets at 05:30 IST (00:00 UTC).',
					'aw-sourcing'
				) }
			>
				{ data.caps.length ? (
					<RefTable
						caption={ __( 'Daily caps', 'aw-sourcing' ) }
						head={ [
							__( 'Account', 'aw-sourcing' ),
							__( 'Daily limit', 'aw-sourcing' ),
							__( 'Left today', 'aw-sourcing' ),
							__( 'Last run could use', 'aw-sourcing' ),
							__( 'Last run used', 'aw-sourcing' ),
							__( 'Keys', 'aw-sourcing' ),
							__( 'Last run', 'aw-sourcing' ),
						] }
						rows={ data.caps }
					/>
				) : (
					<p>
						{ __(
							'No worker run has reported yet.',
							'aw-sourcing'
						) }
					</p>
				) }
			</SectionCard>
			<SectionCard
				id="ai-groups"
				title={ __( 'Keys by group', 'aw-sourcing' ) }
			>
				{ data.groups.length ? (
					<RefTable
						caption={ __( 'Keys by group', 'aw-sourcing' ) }
						head={ [
							__( 'Group', 'aw-sourcing' ),
							__( 'What it means', 'aw-sourcing' ),
							__( 'Providers (working / saved)', 'aw-sourcing' ),
						] }
						rows={ data.groups }
					/>
				) : (
					<p>{ __( 'No keys yet.', 'aw-sourcing' ) }</p>
				) }
			</SectionCard>
		</>
	);
}

/**
 * The keys list: saved keys in the workers' order, then the GitHub secrets.
 *
 * @param {Object}   props
 * @param {Function} props.go     From useSub().
 * @param {Object}   props.msg    Notice.
 * @param {Function} props.setMsg Set the notice.
 */
function AiKeyList( { go, msg, setMsg } ) {
	const [ q, setQ ] = useState( {
		view: '',
		search: '',
		orderby: '',
		order: 'asc',
		page: 1,
	} );
	const [ worker, setWorker ] = useState( '' );
	const { data, error, loading, load } = useRoute( 'ai/keys', q );
	const set = ( part ) => setQ( ( o ) => ( { ...o, page: 1, ...part } ) );
	const decide = ( op, ids ) => {
		setMsg( null );
		return apiFetch( {
			path: path( 'ai/keys/decide' ),
			method: 'POST',
			data: { op, ids, worker },
		} )
			.then( ( r ) => setMsg( answerMsg( r ) ) )
			.catch( ( e ) => setMsg( { tone: 'error', text: errText( e ) } ) )
			.finally( load );
	};
	const remove = ( ids, last4 ) => {
		if (
			// eslint-disable-next-line no-alert -- a plain browser confirm, as today's screen
			window.confirm(
				last4
					? sprintf(
							/* translators: %s: last 4 characters of a key. */
							__(
								'Remove key …%s? This takes effect straight away.',
								'aw-sourcing'
							),
							last4
						)
					: sprintf(
							/* translators: %d: number of keys. */
							_n(
								'Remove %d key? This takes effect straight away.',
								'Remove %d keys? This takes effect straight away.',
								ids.length,
								'aw-sourcing'
							),
							ids.length
						)
			)
		) {
			decide( 'remove', ids );
		}
	};
	const onBulk = ( op, ids ) => {
		const saved = ids.filter( ( id ) =>
			( data?.rows || [] ).some( ( r ) => r.id === id && ! r.secret )
		);
		if ( 'remove' === op ) {
			remove( saved );
		} else {
			decide( op, saved );
		}
	};
	const newKey = { edit: 'new' };
	return (
		<>
			<AiGlance />
			<SectionCard
				id="ai-keys"
				title={ __( 'Keys', 'aw-sourcing' ) }
				description={ __(
					'All keys in one list: the ones saved here, in the order the workers try them (top first), then the ones in the workers’ GitHub secrets (changed in GitHub; they cannot be attached to workers). Each key is for every worker unless it is attached to some. Every key is tested again twice a day.',
					'aw-sourcing'
				) }
				actions={
					<>
						<a
							className="kits-shell-button is-primary"
							href={ subHref( newKey ) }
							onClick={ inPlace( go, newKey ) }
						>
							{ __( 'Add New Key', 'aw-sourcing' ) }
						</a>{ ' ' }
						<a
							className="kits-shell-button is-secondary"
							href={ subHref( { overview: 1 } ) }
							onClick={ inPlace( go, { overview: 1 } ) }
						>
							{ __( 'Keys overview', 'aw-sourcing' ) }
						</a>
					</>
				}
			>
				{ msg && (
					<Notice
						tone={ msg.tone }
						onDismiss={ () => setMsg( null ) }
					>
						{ msg.text }
					</Notice>
				) }
				<SelectField
					label={ __(
						'Worker for Attach and Detach',
						'aw-sourcing'
					) }
					help={ __(
						'Choose the worker first, then tick keys and use the bulk action Attach to worker or Detach from worker.',
						'aw-sourcing'
					) }
					value={ worker }
					options={ [
						{
							value: '',
							label: __( '— Choose a worker —', 'aw-sourcing' ),
						},
						...( data?.workers || [] ).map( ( w ) => ( {
							value: w.value,
							label: `${ w.help }: ${ w.label }`,
						} ) ),
					] }
					onChange={ setWorker }
				/>
				<DataList
					label={ __( 'Keys', 'aw-sourcing' ) }
					columns={ [
						{
							key: 'title',
							label: __( 'Key', 'aw-sourcing' ),
							primary: true,
							render: ( r ) => (
								<>
									{ r.secret ? (
										<code>{ r.title }</code>
									) : (
										<a
											href={ subHref( { edit: r.id } ) }
											onClick={ inPlace( go, {
												edit: r.id,
											} ) }
										>
											<code>{ r.title }</code>
										</a>
									) }
									{ r.secret && (
										<span className="kits-shell-field__help">
											{ ' — ' }
											{ __(
												'GitHub secret: change it in GitHub',
												'aw-sourcing'
											) }
										</span>
									) }
									{ r.problem && (
										<Notice tone="error">
											{ r.problem }
										</Notice>
									) }
								</>
							),
						},
						{
							key: 'provider',
							label: __( 'Provider', 'aw-sourcing' ),
							sortable: true,
							render: ( r ) => r.providerLabel,
						},
						{
							key: 'model',
							label: __( 'Model', 'aw-sourcing' ),
							render: ( r ) => <code>{ r.model }</code>,
						},
						{
							key: 'usedBy',
							label: __( 'Used by', 'aw-sourcing' ),
						},
						{
							key: 'label',
							label: __( 'Label', 'aw-sourcing' ),
							sortable: true,
							render: ( r ) => r.label || '—',
						},
						{ key: 'group', label: __( 'Group', 'aw-sourcing' ) },
						{
							key: 'caps',
							label: __( 'Good for', 'aw-sourcing' ),
							render: ( r ) => (
								<>
									{ r.caps }
									{ r.capsNote && (
										<span className="kits-shell-field__help">
											{ ' — ' }
											{ r.capsNote }
										</span>
									) }
								</>
							),
						},
						{
							key: 'status',
							label: __( 'Status', 'aw-sourcing' ),
							sortable: true,
							render: ( r ) => (
								<>
									<Status
										tone={ KEY_TONE[ r.tone ] }
										label={ r.statusLabel }
									/>
									{ r.recheck && (
										<span className="kits-shell-field__help">
											{ ' — ' }
											{ __(
												'Being checked again',
												'aw-sourcing'
											) }
										</span>
									) }
								</>
							),
						},
						{
							key: 'last_ok',
							label: __( 'Last worked', 'aw-sourcing' ),
							sortable: true,
							render: ( r ) => r.lastOk,
						},
						{
							key: 'limits',
							label: __( 'Limit / cooldown', 'aw-sourcing' ),
						},
						{
							key: 'usage',
							label: __( 'Work in the last run', 'aw-sourcing' ),
						},
						{
							key: 'checked',
							label: __( 'Checked', 'aw-sourcing' ),
							sortable: true,
						},
					] }
					rows={ data?.rows || [] }
					total={ data?.total || 0 }
					page={ data?.page || 1 }
					pages={ data?.pages || 1 }
					onPage={ ( page ) => setQ( ( o ) => ( { ...o, page } ) ) }
					search={ q.search }
					onSearch={ ( search ) => set( { search } ) }
					searchLabel={ __( 'Search keys', 'aw-sourcing' ) }
					views={ data?.views || [] }
					view={ q.view }
					onView={ ( view ) => set( { view } ) }
					sort={ { key: q.orderby, dir: q.order } }
					onSort={ ( orderby, order ) => set( { orderby, order } ) }
					bulkActions={ [
						{
							value: 'attach',
							label: __( 'Attach to worker', 'aw-sourcing' ),
						},
						{
							value: 'detach',
							label: __( 'Detach from worker', 'aw-sourcing' ),
						},
						{
							value: 'recheck',
							label: __( 'Check again', 'aw-sourcing' ),
						},
						{
							value: 'remove',
							label: __( 'Remove', 'aw-sourcing' ),
						},
					] }
					onBulk={ onBulk }
					rowActions={ ( r ) =>
						r.secret
							? []
							: [
									{
										label: __( 'Edit', 'aw-sourcing' ),
										onClick: () => go( { edit: r.id } ),
									},
									...( data?.plain
										? [
												{
													label: __(
														'Move up',
														'aw-sourcing'
													),
													onClick: () =>
														decide( 'up', [
															r.id,
														] ),
												},
												{
													label: __(
														'Move down',
														'aw-sourcing'
													),
													onClick: () =>
														decide( 'down', [
															r.id,
														] ),
												},
											]
										: [] ),
									{
										label: __(
											'Check again',
											'aw-sourcing'
										),
										onClick: () =>
											decide( 'recheck', [ r.id ] ),
									},
									{
										label: __( 'Remove', 'aw-sourcing' ),
										tone: 'error',
										onClick: () =>
											remove( [ r.id ], r.last4 ),
									},
								]
					}
					loading={ loading }
					error={ error }
					onRetry={ load }
					emptyTitle={ __( 'No keys yet.', 'aw-sourcing' ) }
				/>
			</SectionCard>
		</>
	);
}

/**
 * Add New Key, or edit a saved key's label and workers (provider, model and status read-only). The key itself is never
 * shown again: only its last 4 characters.
 *
 * @param {Object}   props
 * @param {string}   props.id     Key id or 'new'.
 * @param {Function} props.onDone Back to the list, with a notice.
 */
function AiKeyForm( { id, onDone } ) {
	const { data, error, load, v, setV, msg, busy, send } = useForm(
		'ai/key',
		{ id },
		( d ) => ( { keys: '', many: '', label: d.label, workers: d.workers } )
	);
	if ( error ) {
		return <ErrorState message={ error } onRetry={ load } />;
	}
	if ( ! data || ! v ) {
		return (
			<LoadingState label={ __( 'Loading the key…', 'aw-sourcing' ) } />
		);
	}
	const put = ( k ) => ( val ) => setV( { ...v, [ k ]: val } );
	const save = () =>
		data.new
			? send(
					'ai/keys/add',
					{
						keys: `${ v.keys }\n${ v.many }`,
						label: v.label,
						workers: v.workers,
					},
					onDone
				)
			: send(
					'ai/key/save',
					{ id, label: v.label, workers: v.workers },
					onDone
				);
	return (
		<SettingsGroup
			id="ai-key-form"
			title={
				data.new
					? __( 'Add New Key', 'aw-sourcing' )
					: sprintf(
							/* translators: %s: last 4 characters of a key. */
							__( 'Edit key …%s', 'aw-sourcing' ),
							data.last4
						)
			}
			footer={
				<FormButtons
					label={
						data.new
							? __( 'Add key', 'aw-sourcing' )
							: __( 'Save changes', 'aw-sourcing' )
					}
					busy={ busy }
					onSave={ save }
					onDone={ onDone }
				/>
			}
		>
			{ msg && <Notice tone={ msg.tone }>{ msg.text }</Notice> }
			{ data.new ? (
				<>
					<TextField
						label={ __( 'Key', 'aw-sourcing' ) }
						help={ __(
							'The provider and a model that answers are worked out in the background after you save. The key is never shown again: only its last 4 characters.',
							'aw-sourcing'
						) }
						value={ v.keys }
						onChange={ put( 'keys' ) }
						input={ {
							type: 'password',
							autoComplete: 'new-password',
							spellCheck: false,
						} }
					/>
					<TextField
						label={ __( 'Or several keys', 'aw-sourcing' ) }
						help={ __(
							'One per line. They get the same label and workers.',
							'aw-sourcing'
						) }
						value={ v.many }
						onChange={ put( 'many' ) }
						input={ { rows: 3 } }
					/>
				</>
			) : (
				<RefTable
					caption={ __( 'The key', 'aw-sourcing' ) }
					head={ [
						__( 'Field', 'aw-sourcing' ),
						__( 'Value', 'aw-sourcing' ),
					] }
					rows={ [
						[
							__( 'Key', 'aw-sourcing' ),
							<span key="k">
								<code>…{ data.last4 }</code>{ ' ' }
								{ __(
									'To change the key itself, remove it and add the new one.',
									'aw-sourcing'
								) }
							</span>,
						],
						[
							__( 'Provider', 'aw-sourcing' ),
							`${ data.providerLabel } (${ data.group })`,
						],
						[
							__( 'Model', 'aw-sourcing' ),
							<code key="m">{ data.model }</code>,
						],
						[
							__( 'Status', 'aw-sourcing' ),
							<Status
								key="s"
								tone={ KEY_TONE[ data.tone ] }
								label={ data.statusLabel }
							/>,
						],
						[ __( 'Last check', 'aw-sourcing' ), data.checked ],
					] }
				/>
			) }
			<TextField
				label={ __( 'Label', 'aw-sourcing' ) }
				help={ __(
					'Optional: a note for you, e.g. which account the key is from.',
					'aw-sourcing'
				) }
				value={ v.label }
				onChange={ put( 'label' ) }
				input={ { maxLength: 80 } }
			/>
			<SettingsBlock
				title={ __( 'Used by', 'aw-sourcing' ) }
				description={ __(
					'None ticked: every worker, also workers added later.',
					'aw-sourcing'
				) }
			>
				<Checks
					id="ai-key-workers"
					legend={ __( 'Used by', 'aw-sourcing' ) }
					options={ data.choices }
					value={ v.workers }
					onChange={ put( 'workers' ) }
				/>
			</SettingsBlock>
		</SettingsGroup>
	);
}

/**
 * Keys overview, read-only: every saved key and what each worker gets.
 *
 * @param {Object}   props
 * @param {Function} props.onBack Back to the list.
 */
function AiOverview( { onBack } ) {
	const { data, error, loading, load } = useRoute( 'ai/overview' );
	if ( error ) {
		return <ErrorState message={ error } onRetry={ load } />;
	}
	if ( loading && ! data ) {
		return <LoadingState label={ __( 'Loading…', 'aw-sourcing' ) } />;
	}
	return (
		<SectionCard
			id="ai-overview"
			title={ __( 'Keys overview', 'aw-sourcing' ) }
			description={ __(
				'Read-only. Keys show only their last 4 characters. All workers means the key is attached to none, so every worker may use it, also workers added later. Usable keys: AI keys that work or are rate-limited for now, from free or free-tier providers (paid ones are never served), plus tool keys.',
				'aw-sourcing'
			) }
			actions={
				<Button onClick={ onBack }>
					{ __( 'Back to the keys', 'aw-sourcing' ) }
				</Button>
			}
		>
			{ data.keys.length ? (
				<RefTable
					caption={ __( 'Keys', 'aw-sourcing' ) }
					head={ [
						__( 'Key', 'aw-sourcing' ),
						__( 'Status', 'aw-sourcing' ),
						__( 'Attached to', 'aw-sourcing' ),
					] }
					rows={ data.keys }
				/>
			) : (
				<p>{ __( 'No keys saved yet.', 'aw-sourcing' ) }</p>
			) }
			<RefTable
				caption={ __( 'Usable keys per worker', 'aw-sourcing' ) }
				head={ [
					__( 'Worker', 'aw-sourcing' ),
					__( 'Usable AI keys', 'aw-sourcing' ),
					__( 'Tool keys', 'aw-sourcing' ),
				] }
				rows={ data.workers }
			/>
		</SectionCard>
	);
}

/* ---------- Reports: Rejected, Relay log, Change log ---------- */

/**
 * A read-only report: a server-driven list with views, search, sort and pages.
 *
 * @param {Object} props
 * @param {string} props.route       Route.
 * @param {string} props.title       Title.
 * @param {string} props.description What it lists.
 * @param {Array}  props.columns     DataList columns.
 * @param {string} props.searchLabel Search label.
 * @param {string} props.emptyTitle  Empty list.
 */
function Report( {
	route,
	title,
	description,
	columns,
	searchLabel,
	emptyTitle,
} ) {
	const [ q, setQ ] = useState( {
		view: '',
		search: '',
		orderby: '',
		order: 'asc',
		page: 1,
	} );
	const { data, error, loading, load } = useRoute( route, q );
	const set = ( part ) => setQ( ( o ) => ( { ...o, page: 1, ...part } ) );
	return (
		<SectionCard id={ route } title={ title } description={ description }>
			<DataList
				label={ title }
				columns={ columns }
				rows={ data?.rows || [] }
				total={ data?.total || 0 }
				page={ data?.page || 1 }
				pages={ data?.pages || 1 }
				onPage={ ( page ) => setQ( ( o ) => ( { ...o, page } ) ) }
				search={ q.search }
				onSearch={ ( search ) => set( { search } ) }
				searchLabel={ searchLabel }
				views={ data?.views || [] }
				view={ q.view }
				onView={ ( view ) => set( { view } ) }
				sort={ { key: q.orderby, dir: q.order } }
				onSort={ ( orderby, order ) => set( { orderby, order } ) }
				loading={ loading }
				error={ error }
				onRetry={ load }
				emptyTitle={ emptyTitle }
			/>
		</SectionCard>
	);
}

function Rejected() {
	return (
		<Report
			route="rejected"
			title={ __( 'Rejected', 'aw-sourcing' ) }
			description={ __(
				'Items the relevance check refused, with the reason. They were never created. An item from an allowed publisher is rarely refused: worth a look.',
				'aw-sourcing'
			) }
			searchLabel={ __( 'Search rejected items', 'aw-sourcing' ) }
			emptyTitle={ __( 'Nothing rejected.', 'aw-sourcing' ) }
			columns={ [
				{
					key: 'title',
					label: __( 'Item', 'aw-sourcing' ),
					primary: true,
					render: ( r ) => (
						<>
							<a
								href={ r.url }
								target="_blank"
								rel="noopener noreferrer"
							>
								{ r.title }
							</a>
							<span className="kits-shell-field__help">
								{ ' — ' }
								{ r.host }
							</span>
							{ r.problem && (
								<Notice tone="warn">{ r.problem }</Notice>
							) }
						</>
					),
				},
				{
					key: 'type',
					label: __( 'Type', 'aw-sourcing' ),
					sortable: true,
				},
				{
					key: 'found',
					label: __( 'Found by', 'aw-sourcing' ),
					sortable: true,
				},
				{ key: 'reason', label: __( 'Reason', 'aw-sourcing' ) },
				{
					key: 'at',
					label: __( 'Date', 'aw-sourcing' ),
					sortable: true,
				},
			] }
		/>
	);
}

function RelayLog() {
	return (
		<Report
			route="relay-log"
			title={ __( 'Relay log', 'aw-sourcing' ) }
			description={ __(
				'Every request the workers made through this site for a publisher that refuses GitHub (the last 200). Each source has its relay switch on Sources.',
				'aw-sourcing'
			) }
			searchLabel={ __( 'Search requests', 'aw-sourcing' ) }
			emptyTitle={ __( 'None yet.', 'aw-sourcing' ) }
			columns={ [
				{
					key: 'at',
					label: __( 'Time', 'aw-sourcing' ),
					sortable: true,
					primary: true,
					render: ( r ) => r.when,
				},
				{
					key: 'host',
					label: __( 'Host', 'aw-sourcing' ),
					sortable: true,
				},
				{ key: 'path', label: __( 'Path', 'aw-sourcing' ) },
				{
					key: 'status',
					label: __( 'Result', 'aw-sourcing' ),
					sortable: true,
					render: ( r ) => (
						<Status
							tone={ r.ok ? 'success' : 'warn' }
							label={ r.status }
						/>
					),
				},
				{
					key: 'bytes',
					label: __( 'Size', 'aw-sourcing' ),
					sortable: true,
					render: ( r ) => r.size,
				},
				{
					key: 'ms',
					label: __( 'Time taken', 'aw-sourcing' ),
					sortable: true,
					render: ( r ) => `${ r.ms } ms`,
				},
				{ key: 'note', label: __( 'Note', 'aw-sourcing' ) },
			] }
		/>
	);
}

function ChangeLog() {
	return (
		<Report
			route="changes"
			title={ __( 'Change log', 'aw-sourcing' ) }
			description={ __(
				'Who changed what on these screens and when, with the value before and after (newest first, the last 1,000).',
				'aw-sourcing'
			) }
			searchLabel={ __( 'Search changes', 'aw-sourcing' ) }
			emptyTitle={ __( 'No changes yet.', 'aw-sourcing' ) }
			columns={ [
				{
					key: 'at',
					label: __( 'When', 'aw-sourcing' ),
					sortable: true,
					primary: true,
				},
				{
					key: 'user',
					label: __( 'Who', 'aw-sourcing' ),
					sortable: true,
				},
				{
					key: 'tab',
					label: __( 'Tab', 'aw-sourcing' ),
					sortable: true,
				},
				{ key: 'what', label: __( 'What changed', 'aw-sourcing' ) },
				{
					key: 'change',
					label: __( 'Old → new', 'aw-sourcing' ),
					render: ( r ) => r.change || '—',
				},
			] }
		/>
	);
}

const SCREENS = {
	dashboard: Dashboard,
	review: Review,
	enrichment: Enrichment,
	settings: Settings,
	workers: Workers,
	sources: Sources,
	keywords: Keywords,
	rules: Rules,
	ai: AiModels,
	rejected: Rejected,
	'relay-log': RelayLog,
	log: ChangeLog,
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
