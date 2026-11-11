/**
 * InsightWeb's new screens (trial, &ui=next): Dashboard and Settings, drawn with the Kits admin shell (@kits/shell is
 * window.kits.shell from Kits Core) and fed by the insightweb/v1 routes. Data and the screen come from
 * window.insightwebApp (includes/insightweb-ui.php).
 */
import {
	createRoot,
	useCallback,
	useEffect,
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
	MiniChart,
	Notice,
	SectionCard,
	ServiceList,
	ServiceRow,
	SettingsBlock,
	SettingsGroup,
	Switch,
	Tile,
	TileGrid,
} from '@kits/shell'; // eslint-disable-line import/no-unresolved -- Kits Core's shell script (window.kits.shell), mapped in webpack.config.js

const app = window.insightwebApp || {};
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
			aside={
				<a href={ app.oldUrl }>
					{ __( 'Open the current screen', 'aw-seo-extras' ) }
				</a>
			}
		/>
	);
}

/* ---------- Dashboard ---------- */

const RANGES = [ 7, 28, 90 ];

const figureColumns = ( first ) => [
	{ key: 'title', label: first, primary: true },
	{ key: 'clicks', label: __( 'Clicks', 'aw-seo-extras' ) },
	{ key: 'impr', label: __( 'Impressions', 'aw-seo-extras' ) },
	{ key: 'pos', label: __( 'Position', 'aw-seo-extras' ) },
];

function Dashboard() {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ days, setDays ] = useState( 28 );
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
						'A count is running in the background. The numbers change when it is done.',
						'aw-seo-extras'
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
				label={ __( 'Loading the figures…', 'aw-seo-extras' ) }
			/>
		);
	}
	const range = data.ranges?.[ String( days ) ];
	let after = data.atText
		? sprintf(
				/* translators: 1: date of the newest figures, 2: date and time of the count. */
				__( 'Figures up to %1$s, counted %2$s.', 'aw-seo-extras' ),
				data.endText || '–',
				data.atText
			)
		: __( 'Counting for the first time…', 'aw-seo-extras' );
	if ( range && ! range.compared && ! data.counting ) {
		after +=
			' ' +
			sprintf(
				/* translators: %d: number of days. */
				__(
					'The %d days before are not all stored, so no change is shown.',
					'aw-seo-extras'
				),
				days
			);
	}
	if ( waiting && tries >= 15 ) {
		after +=
			' ' +
			__(
				'The count is taking longer than usual; reload this page later.',
				'aw-seo-extras'
			);
	}
	const health = data.health || { tiles: [], sources: [] };
	const attention = health.tiles.filter( ( t ) => t.tone );
	const noRows = ! data.counting && ! range;

	return (
		<>
			{ data.off && (
				<Notice tone="warning">
					{ __(
						'Search Insights is off: its data was deleted and nothing runs on schedule. Run the daily update on the current Settings to start it again.',
						'aw-seo-extras'
					) }
				</Notice>
			) }
			<ChipRow
				label={ __( 'Time range', 'aw-seo-extras' ) }
				value={ days }
				onChange={ setDays }
				chips={ RANGES.map( ( n ) => ( {
					value: n,
					/* translators: %d: number of days. */
					label: sprintf( __( 'Last %d days', 'aw-seo-extras' ), n ),
				} ) ) }
				after={
					<>
						{ after }{ ' ' }
						<Button
							variant="link"
							busy={ busy }
							onClick={ countNow }
						>
							{ __( 'Count now', 'aw-seo-extras' ) }
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
					<strong>
						{ __( 'Needs attention', 'aw-seo-extras' ) }
					</strong>
					<ul>
						{ attention.map( ( t ) => (
							<li key={ t.key }>
								{ t.label }: { t.value }. { t.sub }
							</li>
						) ) }
					</ul>
				</Notice>
			) }
			<TileGrid
				label={ __( 'Search Insights at a glance', 'aw-seo-extras' ) }
			>
				{ ( range?.tiles || PLACEHOLDER ).map( ( t ) => (
					<Tile
						key={ t.key }
						label={ t.label }
						value={ noRows ? '–' : t.value }
						delta={ t.delta }
						sub={
							noRows
								? __(
										'No Search Console rows stored yet',
										'aw-seo-extras'
									)
								: t.sub
						}
						href={ t.href }
						loading={ data.counting }
					/>
				) ) }
				{ health.tiles.map( ( t ) => (
					<Tile
						key={ t.key }
						label={ t.label }
						value={ t.value }
						sub={ t.sub }
						href={ t.href }
						tone={ t.tone }
					/>
				) ) }
			</TileGrid>
			{ range && range.chart.length > 1 && (
				<SectionCard
					id="clicks-per-day"
					title={ __( 'Clicks per day', 'aw-seo-extras' ) }
				>
					<MiniChart
						label={ sprintf(
							/* translators: %d: number of days. */
							__(
								'Clicks per day, last %d days',
								'aw-seo-extras'
							),
							days
						) }
						points={ range.chart }
						unit={ __( 'clicks', 'aw-seo-extras' ) }
					/>
				</SectionCard>
			) }
			{ ! data.counting && (
				<>
					<SectionCard
						id="top-pages"
						title={ __( 'Top pages', 'aw-seo-extras' ) }
						description={ __(
							'The pages with the most clicks from Google in this range.',
							'aw-seo-extras'
						) }
						actions={
							<a href={ data.links?.pages }>
								{ __( 'All pages', 'aw-seo-extras' ) }
							</a>
						}
					>
						<DataList
							label={ __( 'Top pages', 'aw-seo-extras' ) }
							columns={ [
								{
									...figureColumns(
										__( 'Page', 'aw-seo-extras' )
									)[ 0 ],
									render: ( r ) =>
										r.url ? (
											<a href={ r.url }>{ r.title }</a>
										) : (
											r.title
										),
								},
								...figureColumns( '' ).slice( 1 ),
							] }
							rows={ range?.topPages || [] }
							emptyTitle={ __(
								'No page figures stored for this range yet.',
								'aw-seo-extras'
							) }
						/>
					</SectionCard>
					<SectionCard
						id="top-searches"
						title={ __( 'Top searches', 'aw-seo-extras' ) }
						description={ __(
							'The searches that brought the most clicks in this range.',
							'aw-seo-extras'
						) }
						actions={
							<a href={ data.links?.keywords }>
								{ __( 'All keywords', 'aw-seo-extras' ) }
							</a>
						}
					>
						<DataList
							label={ __( 'Top searches', 'aw-seo-extras' ) }
							columns={ figureColumns(
								__( 'Search', 'aw-seo-extras' )
							) }
							rows={ range?.topQueries || [] }
							emptyTitle={ __(
								'No search figures stored for this range yet.',
								'aw-seo-extras'
							) }
						/>
					</SectionCard>
				</>
			) }
			<SectionCard
				id="sources"
				title={ __( 'Daily jobs', 'aw-seo-extras' ) }
				description={ __(
					'Each source’s last good run. A failed run keeps the figures it had.',
					'aw-seo-extras'
				) }
			>
				<DataList
					label={ __( 'Daily jobs', 'aw-seo-extras' ) }
					columns={ [
						{
							key: 'title',
							label: __( 'Source', 'aw-seo-extras' ),
							primary: true,
						},
						{
							key: 'last',
							label: __( 'Last good run', 'aw-seo-extras' ),
						},
						{
							key: 'status',
							label: __( 'State', 'aw-seo-extras' ),
							render: ( r ) =>
								r.tone ? (
									<strong>{ r.status }</strong>
								) : (
									r.status
								),
						},
					] }
					rows={ health.sources }
					emptyTitle={ __( 'No sources yet.', 'aw-seo-extras' ) }
				/>
			</SectionCard>
		</>
	);
}

// Tiles shown while the first count runs.
const PLACEHOLDER = [
	[ 'clicks', __( 'Clicks', 'aw-seo-extras' ) ],
	[ 'impr', __( 'Impressions', 'aw-seo-extras' ) ],
	[ 'ctr', __( 'Click rate', 'aw-seo-extras' ) ],
	[ 'pos', __( 'Average position', 'aw-seo-extras' ) ],
	[ 'pages', __( 'Pages in search', 'aw-seo-extras' ) ],
	[ 'queries', __( 'Searches', 'aw-seo-extras' ) ],
].map( ( [ key, label ] ) => ( { key, label, value: '', sub: '' } ) );

/* ---------- Settings ---------- */

const SERVICES = 'services';

/**
 * The open group from the address (#numbers), so a link opens the right group.
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

/**
 * A group's state as saved.
 *
 * @param {Object} group Group from the route.
 * @return {Object} { t: { CONST: text }, gdelt, seeds }.
 */
function startOf( group ) {
	const t = {};
	group.blocks.forEach( ( b ) =>
		b.fields.forEach( ( f ) => ( t[ f.const ] = f.text ) )
	);
	return {
		t,
		gdelt: group.gdelt ? !! group.gdelt.on : null,
		seeds: group.seeds ? group.seeds.text : null,
	};
}

/**
 * A group with its own Save. Only the changed values are sent.
 *
 * @param {Object}   props
 * @param {Object}   props.group   Group from the route.
 * @param {Function} props.onSaved New group from the route after a save.
 * @param {Function} props.onDirty ( slug, unsaved ) when that changes.
 */
function Group( { group, onSaved, onDirty } ) {
	const [ state, setState ] = useState( () => startOf( group ) );
	const [ msg, setMsg ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	useEffect( () => setState( startOf( group ) ), [ group ] );

	const changedFields = {};
	group.blocks.forEach( ( b ) =>
		b.fields.forEach( ( f ) => {
			if ( state.t[ f.const ] !== f.text ) {
				changedFields[ f.const ] = state.t[ f.const ];
			}
		} )
	);
	const body = {};
	if ( Object.keys( changedFields ).length ) {
		body.fields = changedFields;
	}
	if ( group.gdelt && state.gdelt !== group.gdelt.on ) {
		body.gdelt = state.gdelt;
	}
	if ( group.seeds && state.seeds !== group.seeds.text ) {
		body.seeds = state.seeds;
	}
	const dirty = Object.keys( body ).length > 0;
	const anyChanged = group.blocks.some( ( b ) =>
		b.fields.some( ( f ) => f.changed )
	);
	useEffect(
		() => onDirty( group.slug, dirty ),
		[ group.slug, dirty, onDirty ]
	);

	const save = ( data ) => {
		setBusy( true );
		setMsg( null );
		return apiFetch( {
			path: path( `settings/${ group.slug }` ),
			method: 'POST',
			data,
		} )
			.then( ( r ) => {
				onSaved( r.group );
				setMsg( { tone: 'success', text: r.message } );
			} )
			.catch( ( e ) => setMsg( { tone: 'error', text: errText( e ) } ) )
			.finally( () => setBusy( false ) );
	};
	const restore = () => {
		if (
			// eslint-disable-next-line no-alert -- a plain browser confirm before a reset, as core's list tables do
			window.confirm(
				__( 'Put every number back to its default?', 'aw-seo-extras' )
			)
		) {
			save( { reset: true } );
		}
	};
	const setText = ( k, v ) =>
		setState( ( s ) => ( { ...s, t: { ...s.t, [ k ]: v } } ) );

	const footer = (
		<>
			<Button
				variant="primary"
				busy={ busy }
				disabled={ ! dirty }
				onClick={ () => save( body ) }
			>
				{ __( 'Save changes', 'aw-seo-extras' ) }
			</Button>
			{ anyChanged && (
				<Button busy={ busy } onClick={ restore }>
					{ __( 'Restore defaults', 'aw-seo-extras' ) }
				</Button>
			) }
			<span aria-live="polite">
				{ msg && 'success' === msg.tone ? msg.text : '' }
			</span>
		</>
	);

	return (
		<SettingsGroup
			id={ `insightweb-group-${ group.slug }` }
			title={ group.label }
			footer={ footer }
		>
			{ msg && 'error' === msg.tone && (
				<Notice tone="error" onDismiss={ () => setMsg( null ) }>
					{ msg.text }
				</Notice>
			) }
			{ group.blocks.map( ( b ) => (
				<SettingsBlock
					key={ b.key }
					title={ b.label }
					description={ b.desc }
				>
					{ b.fields.map( ( f ) => (
						<Field
							key={ f.const }
							label={ f.label }
							help={ f.help }
							changed={ state.t[ f.const ] !== f.defaultText }
							onReset={ () => setText( f.const, f.defaultText ) }
							render={ ( id, helpId ) => (
								<input
									id={ id }
									type="number"
									min="0"
									value={ state.t[ f.const ] ?? '' }
									aria-describedby={ helpId }
									onChange={ ( e ) =>
										setText( f.const, e.target.value )
									}
								/>
							) }
						/>
					) ) }
				</SettingsBlock>
			) ) }
			{ group.gdelt && (
				<SettingsBlock title={ __( 'News (GDELT)', 'aw-seo-extras' ) }>
					<Switch
						label={ __(
							'Use GDELT for Rising (Indian news counts, daily)',
							'aw-seo-extras'
						) }
						checked={ !! state.gdelt }
						help={ group.gdelt.help }
						onChange={ ( v ) =>
							setState( ( s ) => ( { ...s, gdelt: v } ) )
						}
					/>
				</SettingsBlock>
			) }
			{ group.seeds && (
				<SettingsBlock
					title={ __( 'Seeds for keyword tools', 'aw-seo-extras' ) }
				>
					<Field
						label={ __( 'Searches', 'aw-seo-extras' ) }
						help={ group.seeds.help }
						render={ ( id, helpId ) => (
							<textarea
								id={ id }
								rows={ 8 }
								value={ state.seeds ?? '' }
								spellCheck="false"
								aria-describedby={ helpId }
								onChange={ ( e ) => {
									const v = e.target.value;
									setState( ( s ) => ( { ...s, seeds: v } ) );
								} }
							/>
						) }
					/>
				</SettingsBlock>
			) }
		</SettingsGroup>
	);
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
	if ( ! list.length ) {
		return (
			<EmptyState
				title={ __( 'No connected services.', 'aw-seo-extras' ) }
			/>
		);
	}
	return (
		<SectionCard
			id={ `insightweb-group-${ SERVICES }` }
			title={ __( 'Connected services', 'aw-seo-extras' ) }
			description={ __(
				'Where the figures come from. Search Insights only reads them. Keys stay in Kits → Keys and are never shown.',
				'aw-seo-extras'
			) }
		>
			<ServiceList label={ __( 'Connected services', 'aw-seo-extras' ) }>
				{ list.map( ( s ) => (
					<ServiceRow
						key={ s.slug }
						name={ s.name }
						status={ s.status }
						tone={ s.tone }
						summary={ s.summary }
					>
						<ul className="kits-shell-list">
							{ s.lines.map( ( l ) => (
								<li key={ l }>{ l }</li>
							) ) }
						</ul>
						{ s.links.length > 0 && (
							<p>
								{ s.links.map( ( l, i ) => (
									<span key={ l.url + l.label }>
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
	const replaceGroup = useCallback(
		( ng ) =>
			setData( ( d ) => ( {
				...d,
				groups: d.groups.map( ( x ) =>
					x.slug === ng.slug ? ng : x
				),
			} ) ),
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
				label={ __( 'Loading the settings…', 'aw-seo-extras' ) }
			/>
		);
	}
	const chips = [
		{
			value: SERVICES,
			label: __( 'Connected services', 'aw-seo-extras' ),
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
				label={ __( 'Settings groups', 'aw-seo-extras' ) }
				chips={ chips }
				value={ open }
				onChange={ setOpen }
				after={
					<a href={ data.moreUrl }>
						{ __(
							'Run now, imports and Delete data: current Settings',
							'aw-seo-extras'
						) }
					</a>
				}
			/>
			{ SERVICES === open && <Services /> }
			{ data.groups.map( ( g ) => (
				<div key={ g.slug } hidden={ g.slug !== open }>
					<Group
						group={ g }
						onSaved={ replaceGroup }
						onDirty={ markDirty }
					/>
				</div>
			) ) }
		</>
	);
}

function App() {
	return (
		<AppShell header={ <Header /> }>
			{ 'settings' === app.screen ? <Settings /> : <Dashboard /> }
		</AppShell>
	);
}

const root = document.getElementById( 'insightweb-app' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
