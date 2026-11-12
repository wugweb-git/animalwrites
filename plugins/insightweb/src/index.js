/**
 * InsightWeb's new screens: Dashboard, Settings, and Keywords with the other tabs (from each tab's own output), drawn with the Kits admin shell (@kits/shell is
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
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	AppShell,
	AppHeader,
	BlockParts,
	Button,
	ChipRow,
	ConfirmButton,
	DataList,
	EmptyState,
	ErrorState,
	Field,
	LoadingState,
	MiniChart,
	Notice,
	PageParts,
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
		/>
	);
}

/* ---------- Dashboard ---------- */

const RANGES = [ 7, 28, 90 ];

const figureColumns = ( first ) => [
	{ key: 'title', label: first, primary: true },
	{ key: 'clicks', label: __( 'Clicks', 'insightweb' ) },
	{ key: 'impr', label: __( 'Impressions', 'insightweb' ) },
	{ key: 'pos', label: __( 'Position', 'insightweb' ) },
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
						'insightweb'
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
				label={ __( 'Loading the figures…', 'insightweb' ) }
			/>
		);
	}
	const range = data.ranges?.[ String( days ) ];
	let after = data.atText
		? sprintf(
				/* translators: 1: date of the newest figures, 2: date and time of the count. */
				__( 'Figures up to %1$s, counted %2$s.', 'insightweb' ),
				data.endText || '–',
				data.atText
			)
		: __( 'Counting for the first time…', 'insightweb' );
	if ( range && ! range.compared && ! data.counting ) {
		after +=
			' ' +
			sprintf(
				/* translators: %d: number of days. */
				__(
					'The %d days before are not all stored, so no change is shown.',
					'insightweb'
				),
				days
			);
	}
	if ( waiting && tries >= 15 ) {
		after +=
			' ' +
			__(
				'The count is taking longer than usual; reload this page later.',
				'insightweb'
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
						'insightweb'
					) }
				</Notice>
			) }
			<ChipRow
				label={ __( 'Time range', 'insightweb' ) }
				value={ days }
				onChange={ setDays }
				chips={ RANGES.map( ( n ) => ( {
					value: n,
					/* translators: %d: number of days. */
					label: sprintf( __( 'Last %d days', 'insightweb' ), n ),
				} ) ) }
				after={
					<>
						{ after }{ ' ' }
						<Button
							variant="link"
							busy={ busy }
							onClick={ countNow }
						>
							{ __( 'Count now', 'insightweb' ) }
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
						{ __( 'Needs attention', 'insightweb' ) }
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
				label={ __( 'Search Insights at a glance', 'insightweb' ) }
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
										'insightweb'
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
					title={ __( 'Clicks per day', 'insightweb' ) }
				>
					<MiniChart
						label={ sprintf(
							/* translators: %d: number of days. */
							__(
								'Clicks per day, last %d days',
								'insightweb'
							),
							days
						) }
						points={ range.chart }
						unit={ __( 'clicks', 'insightweb' ) }
					/>
				</SectionCard>
			) }
			{ ! data.counting && (
				<>
					<SectionCard
						id="top-pages"
						title={ __( 'Top pages', 'insightweb' ) }
						description={ __(
							'The pages with the most clicks from Google in this range.',
							'insightweb'
						) }
						actions={
							<a href={ data.links?.pages }>
								{ __( 'All pages', 'insightweb' ) }
							</a>
						}
					>
						<DataList
							label={ __( 'Top pages', 'insightweb' ) }
							columns={ [
								{
									...figureColumns(
										__( 'Page', 'insightweb' )
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
								'insightweb'
							) }
						/>
					</SectionCard>
					<SectionCard
						id="top-searches"
						title={ __( 'Top searches', 'insightweb' ) }
						description={ __(
							'The searches that brought the most clicks in this range.',
							'insightweb'
						) }
						actions={
							<a href={ data.links?.keywords }>
								{ __( 'All keywords', 'insightweb' ) }
							</a>
						}
					>
						<DataList
							label={ __( 'Top searches', 'insightweb' ) }
							columns={ figureColumns(
								__( 'Search', 'insightweb' )
							) }
							rows={ range?.topQueries || [] }
							emptyTitle={ __(
								'No search figures stored for this range yet.',
								'insightweb'
							) }
						/>
					</SectionCard>
				</>
			) }
			<SectionCard
				id="sources"
				title={ __( 'Daily jobs', 'insightweb' ) }
				description={ __(
					'Each source’s last good run. A failed run keeps the figures it had.',
					'insightweb'
				) }
			>
				<DataList
					label={ __( 'Daily jobs', 'insightweb' ) }
					columns={ [
						{
							key: 'title',
							label: __( 'Source', 'insightweb' ),
							primary: true,
						},
						{
							key: 'last',
							label: __( 'Last good run', 'insightweb' ),
						},
						{
							key: 'status',
							label: __( 'State', 'insightweb' ),
							render: ( r ) =>
								r.tone ? (
									<>
										<strong>{ r.status }</strong>
										{ r.why ? ` ${ r.why }` : '' }
									</>
								) : (
									r.status
								),
						},
					] }
					rows={ health.sources }
					emptyTitle={ __( 'No sources yet.', 'insightweb' ) }
				/>
			</SectionCard>
			<OverviewBoxes />
		</>
	);
}

/** The Overview boxes (the same as the classic Overview), loaded one after another. */
function OverviewBoxes() {
	const boxes = app.boxes || [];
	const [ got, setGot ] = useState( {} );
	useEffect( () => {
		let stop = false;
		( async () => {
			for ( const b of boxes ) {
				if ( stop ) {
					return;
				}
				try {
					const r = await apiFetch( {
						path: `${ path( 'dashboard/box' ) }?key=${ encodeURIComponent( b.key ) }`,
					} );
					setGot( ( o ) => ( {
						...o,
						[ b.key ]: { blocks: r.blocks },
					} ) );
				} catch ( e ) {
					setGot( ( o ) => ( {
						...o,
						[ b.key ]: { error: errText( e ) },
					} ) );
				}
			}
		} )();
		return () => {
			stop = true;
		};
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps -- the list comes with the page
	return boxes.map( ( b ) => {
		const g = got[ b.key ];
		let body;
		if ( ! g ) {
			body = <LoadingState label={ __( 'Loading…', 'insightweb' ) } />;
		} else if ( g.error ) {
			body = <Notice tone="error">{ g.error }</Notice>;
		} else if ( ! g.blocks.length ) {
			body = <p>{ __( 'Nothing yet.', 'insightweb' ) }</p>;
		} else {
			body = <BlockParts blocks={ g.blocks } title={ b.title } />;
		}
		return (
			<SectionCard
				key={ b.key }
				id={ `box-${ b.key }` }
				title={ b.title }
			>
				{ body }
			</SectionCard>
		);
	} );
}

// Tiles shown while the first count runs.
const PLACEHOLDER = [
	[ 'clicks', __( 'Clicks', 'insightweb' ) ],
	[ 'impr', __( 'Impressions', 'insightweb' ) ],
	[ 'ctr', __( 'Click rate', 'insightweb' ) ],
	[ 'pos', __( 'Average position', 'insightweb' ) ],
	[ 'pages', __( 'Pages in search', 'insightweb' ) ],
	[ 'queries', __( 'Searches', 'insightweb' ) ],
].map( ( [ key, label ] ) => ( { key, label, value: '', sub: '' } ) );

/* ---------- Settings ---------- */

const SERVICES = 'services';
const MORE = 'more'; // Run now, imports, Delete data: the Settings tab's own rows

/**
 * The open group from the address (#numbers), so a link opens the right group.
 *
 * @param {Array} groups Groups.
 */
function hashGroup( groups ) {
	const h = window.location.hash.replace( '#', '' );
	if (
		SERVICES === h ||
		MORE === h ||
		groups.some( ( g ) => g.slug === h )
	) {
		return h;
	}
	// Back from Run now, an import or a snapshot: open Runs and data, where its result shows.
	const first = ( app.more || [] )[ 0 ];
	if (
		new URLSearchParams( window.location.search ).has( 'ran' ) ||
		( first &&
			'notice' === first.type &&
			[ 'success', 'error' ].includes( first.tone ) )
	) {
		return MORE;
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
	const changedCount = group.blocks.reduce(
		( n, b ) => n + b.fields.filter( ( f ) => f.changed ).length,
		0
	);
	const anyChanged = changedCount > 0;
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
				{ __( 'Save changes', 'insightweb' ) }
			</Button>
			{ anyChanged && (
				<ConfirmButton
					label={ __( 'Restore defaults', 'insightweb' ) }
					question={
						/* translators: %d: number of changed numbers. */ _n(
							'Put %d changed number back to its default?',
							'Put %d changed numbers back to their defaults?',
							changedCount,
							'insightweb'
						)
					}
					count={ changedCount }
					busy={ busy }
					onConfirm={ () => save( { reset: true } ) }
				/>
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
				<SettingsBlock title={ __( 'News (GDELT)', 'insightweb' ) }>
					<Switch
						label={ __(
							'Use GDELT for Rising (Indian news counts, daily)',
							'insightweb'
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
					title={ __( 'Seeds for keyword tools', 'insightweb' ) }
				>
					<Field
						label={ __( 'Searches', 'insightweb' ) }
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
				title={ __( 'No connected services.', 'insightweb' ) }
			/>
		);
	}
	return (
		<SectionCard
			id={ `insightweb-group-${ SERVICES }` }
			title={ __( 'Connected services', 'insightweb' ) }
			description={ __(
				'Where the figures come from. Search Insights only reads them. Keys stay in Kits → Keys and are never shown.',
				'insightweb'
			) }
		>
			<ServiceList label={ __( 'Connected services', 'insightweb' ) }>
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
				label={ __( 'Loading the settings…', 'insightweb' ) }
			/>
		);
	}
	const chips = [
		{
			value: SERVICES,
			label: __( 'Connected services', 'insightweb' ),
			href: `#${ SERVICES }`,
		},
		...data.groups.map( ( g ) => ( {
			value: g.slug,
			label: dirty[ g.slug ] ? `${ g.label } •` : g.label,
			href: `#${ g.slug }`,
		} ) ),
		{
			value: MORE,
			label: __( 'Runs and data', 'insightweb' ),
			href: `#${ MORE }`,
		},
	];
	return (
		<>
			<ChipRow
				label={ __( 'Settings groups', 'insightweb' ) }
				chips={ chips }
				value={ open }
				onChange={ setOpen }
			/>
			{ SERVICES === open && <Services /> }
			{ MORE === open && (
				<SectionCard
					id={ MORE }
					title={ __( 'Runs and data', 'insightweb' ) }
					description={ __(
						'The schedule, keys, Run now, snapshots, imports and Delete data.',
						'insightweb'
					) }
				>
					<PageParts
						parts={ app.more || [] }
						title={ __( 'Runs and data', 'insightweb' ) }
					/>
				</SectionCard>
			) }
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

/** Keywords or another tab: its own output, part by part. */
function TabScreen() {
	const parts = app.parts || [];
	return (
		<SectionCard id={ app.screen } title={ app.title }>
			{ 0 === parts.length && (
				<p>
					{ __(
						'Nothing to show on this tab yet.',
						'insightweb'
					) }
				</p>
			) }
			<PageParts parts={ parts } title={ app.title } />
		</SectionCard>
	);
}

function App() {
	return (
		<AppShell header={ <Header /> }>
			{ 'settings' === app.screen && <Settings /> }
			{ 'dashboard' === app.screen && <Dashboard /> }
			{ 'settings' !== app.screen && 'dashboard' !== app.screen && (
				<TabScreen />
			) }
		</AppShell>
	);
}

const root = document.getElementById( 'insightweb-app' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
