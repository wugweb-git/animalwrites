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
					emptyTitle={ __( 'No sources yet.', 'aw-seo-extras' ) }
				/>
			</SectionCard>
			<OverviewBoxes />
		</>
	);
}

/**
 * A part of a box's text: a link when it has one (http(s) or a relative address, from the route).
 *
 * @param {Object} props
 * @param {Object} props.cell { text, href, external }.
 */
function Cell( { cell } ) {
	if ( ! cell.href ) {
		return cell.text;
	}
	return cell.external ? (
		<a href={ cell.href } target="_blank" rel="noopener noreferrer">
			{ cell.text }
		</a>
	) : (
		<a href={ cell.href }>{ cell.text }</a>
	);
}

/**
 * One box's parts: paragraphs, notes, headings, lists, tables, notices.
 *
 * @param {Object} props
 * @param {Array}  props.blocks Parts from GET dashboard/box.
 * @param {string} props.title  The box's title (table captions).
 */
function BoxParts( { blocks, title } ) {
	return blocks.map( ( b, i ) => {
		const key = `${ b.type }-${ i }`;
		if ( 'table' === b.type ) {
			return (
				<RefTable
					key={ key }
					caption={ title }
					head={ b.head }
					rows={ b.rows.map( ( r ) =>
						r.map( ( c, j ) => <Cell key={ j } cell={ c } /> )
					) }
				/>
			);
		}
		if ( 'list' === b.type ) {
			return (
				<ul key={ key }>
					{ b.items.map( ( c, j ) => (
						<li key={ j }>
							<Cell cell={ c } />
						</li>
					) ) }
				</ul>
			);
		}
		if ( 'notice' === b.type ) {
			return (
				<Notice key={ key } tone={ b.tone }>
					<Cell cell={ b } />
				</Notice>
			);
		}
		if ( 'heading' === b.type ) {
			return <h3 key={ key }>{ b.text }</h3>;
		}
		return (
			<p
				key={ key }
				className={ 'note' === b.type ? 'kits-shell-field__help' : '' }
			>
				<Cell cell={ b } />
			</p>
		);
	} );
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
			body = <LoadingState label={ __( 'Loading…', 'aw-seo-extras' ) } />;
		} else if ( g.error ) {
			body = <Notice tone="error">{ g.error }</Notice>;
		} else if ( ! g.blocks.length ) {
			body = <p>{ __( 'Nothing yet.', 'aw-seo-extras' ) }</p>;
		} else {
			body = <BoxParts blocks={ g.blocks } title={ b.title } />;
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

/* ---------- Keywords and the other tabs ---------- */

/**
 * Open this tab's address with some query arguments changed (a view, a search, a filter, a page).
 *
 * @param {Object} set  Arguments to set ('' removes one).
 * @param {Array}  drop Arguments to remove.
 */
function go( set, drop = [] ) {
	const url = new URL( window.location.href );
	drop.forEach( ( k ) => url.searchParams.delete( k ) );
	Object.keys( set ).forEach( ( k ) =>
		'' === set[ k ]
			? url.searchParams.delete( k )
			: url.searchParams.set( k, set[ k ] )
	);
	window.location.assign( url.toString() );
}

/**
 * Send what the tab's own form sends (its hidden fields and nonce, plus the choice), so its own handler saves it.
 *
 * @param {Object} props
 * @param {string} props.method 'get' or 'post'.
 * @param {string} props.action The form's address ('' for this one).
 * @param {Array}  props.pairs  [ name, value ] pairs.
 */
function sendForm( { method, action, pairs } ) {
	const form = document.createElement( 'form' );
	form.method = method;
	form.action = action || window.location.href;
	pairs.forEach( ( [ name, value ] ) => {
		const input = document.createElement( 'input' );
		input.type = 'hidden';
		input.name = name;
		input.value = value;
		form.appendChild( input );
	} );
	document.body.appendChild( form );
	form.submit();
}

/**
 * A tab's list (its WP_List_Table): views, search, filters, sort, pages, bulk and row actions. Every change opens the
 * tab's own address or sends its own form, as the current screen does.
 *
 * @param {Object} props
 * @param {Object} props.part The listtable part.
 */
function TabList( { part } ) {
	const current = part.views.find( ( v ) => v.current );
	const sorted = part.columns.find( ( c ) => c.sorted );
	const primary =
		part.columns.find( ( c ) => c.primary ) || part.columns[ 0 ];
	const rows = part.rows.map( ( r ) => ( {
		...r,
		title:
			primary && r.cells[ primary.key ]
				? r.cells[ primary.key ].text
				: r.id,
	} ) );
	return (
		<DataList
			label={ app.title }
			columns={ part.columns.map( ( c ) => ( {
				key: c.key,
				label: c.label,
				sortable: !! c.sortHref,
				primary: c.primary,
				render: ( r ) => (
					<Cell cell={ r.cells[ c.key ] || { text: '' } } />
				),
			} ) ) }
			rows={ rows }
			total={ part.total }
			page={ part.page }
			pages={ part.pages }
			onPage={ ( n ) => go( { paged: String( n ) } ) }
			search={ part.search ? part.search.value : undefined }
			searchLabel={ part.search ? part.search.label : undefined }
			onSearch={
				part.search
					? ( words ) =>
							go( { [ part.search.name ]: words }, [ 'paged' ] )
					: undefined
			}
			filters={ part.filters }
			onFilter={ ( key, value ) => go( { [ key ]: value }, [ 'paged' ] ) }
			views={ part.views }
			view={ current ? current.value : undefined }
			onView={ ( value ) => {
				const v = part.views.find( ( x ) => x.value === value );
				if ( v ) {
					window.location.assign( v.href );
				}
			} }
			sort={
				sorted ? { key: sorted.key, dir: sorted.sorted } : undefined
			}
			onSort={ ( key, dir ) => {
				const c = part.columns.find( ( x ) => x.key === key );
				if ( c && c.sortHref ) {
					const url = new URL( c.sortHref, window.location.href );
					url.searchParams.set( 'order', dir );
					window.location.assign( url.toString() );
				}
			} }
			bulkActions={ part.cb ? part.bulk : [] }
			onBulk={ ( value, ids ) =>
				sendForm( {
					method: part.method,
					action: part.action,
					pairs: [
						...part.hidden,
						[ 'action', value ],
						...rows
							.filter( ( r ) => ids.includes( r.id ) && r.pick )
							.map( ( r ) => [ part.cb, r.pick ] ),
					],
				} )
			}
			rowActions={ ( r ) =>
				r.actions.map( ( a ) => ( { label: a.label, href: a.href } ) )
			}
			emptyTitle={ part.empty || __( 'Nothing here.', 'aw-seo-extras' ) }
		/>
	);
}

/**
 * An id for a tick box or choice of a tab's form.
 *
 * @param {string} name  The field's name.
 * @param {string} value Its value.
 */
const fieldId = ( name, value ) =>
	`iw-${ name }-${ value }`.replace( /[^A-Za-z0-9_-]/g, '-' );

/**
 * One field of a tab's form, as a plain control the form sends as it is.
 *
 * @param {Object} props
 * @param {Object} props.f The field.
 */
function TabField( { f } ) {
	if ( 'checkbox' === f.type ) {
		return (
			<p>
				<input
					id={ fieldId( f.name, f.value ) }
					type="checkbox"
					name={ f.name }
					value={ f.value }
					defaultChecked={ f.checked }
				/>{ ' ' }
				<label htmlFor={ fieldId( f.name, f.value ) }>
					{ f.label }
				</label>
				{ f.help && (
					<span className="kits-shell-field__help">{ f.help }</span>
				) }
			</p>
		);
	}
	if ( 'radio' === f.type ) {
		return (
			<fieldset>
				<legend>{ f.label }</legend>
				{ f.options.map( ( o ) => (
					<p key={ o.value }>
						<input
							id={ fieldId( f.name, o.value ) }
							type="radio"
							name={ f.name }
							value={ o.value }
							defaultChecked={ o.value === f.value }
						/>{ ' ' }
						<label htmlFor={ fieldId( f.name, o.value ) }>
							{ o.label }
						</label>
					</p>
				) ) }
			</fieldset>
		);
	}
	return (
		<Field
			label={ f.label }
			help={ f.help }
			render={ ( id, helpId ) => {
				if ( 'select' === f.type ) {
					return (
						<select
							id={ id }
							name={ f.name }
							multiple={ f.multiple }
							defaultValue={ f.value }
							aria-describedby={ helpId }
						>
							{ f.options.map( ( o ) => (
								<option key={ o.value } value={ o.value }>
									{ o.label }
								</option>
							) ) }
						</select>
					);
				}
				if ( 'textarea' === f.type ) {
					return (
						<textarea
							id={ id }
							name={ f.name }
							rows={ f.rows }
							defaultValue={ f.value }
							aria-describedby={ helpId }
						/>
					);
				}
				return (
					<input
						id={ id }
						type={ f.type }
						name={ f.name }
						defaultValue={ f.value }
						placeholder={ f.placeholder }
						min={ f.min }
						max={ f.max }
						step={ f.step }
						required={ f.required }
						aria-describedby={ helpId }
					/>
				);
			} }
		/>
	);
}

/**
 * Any other form of a tab: its words, fields and buttons, sent to the same address with the same nonce.
 *
 * @param {Object} props
 * @param {Object} props.part The form part.
 */
function TabForm( { part } ) {
	return (
		<form method={ part.method } action={ part.action || undefined }>
			{ part.hidden.map( ( [ name, value ], i ) => (
				<input key={ i } type="hidden" name={ name } value={ value } />
			) ) }
			<BoxParts blocks={ part.blocks } title={ app.title } />
			{ part.fields.map( ( f, i ) => (
				<TabField key={ `${ f.name }-${ i }` } f={ f } />
			) ) }
			{ part.buttons.length > 0 && (
				<p className="kits-shell-actions">
					{ part.buttons.map( ( b, i ) => (
						<button
							key={ i }
							type="submit"
							name={ b.name || undefined }
							value={ b.value }
							className={ `kits-shell-button ${
								b.primary ? 'is-primary' : 'is-secondary'
							}` }
						>
							{ b.label }
						</button>
					) ) }{ ' ' }
				</p>
			) }
		</form>
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
						'aw-seo-extras'
					) }
				</p>
			) }
			{ parts.map( ( p, i ) => {
				if ( 'listtable' === p.type ) {
					return <TabList key={ i } part={ p } />;
				}
				if ( 'form' === p.type ) {
					return <TabForm key={ i } part={ p } />;
				}
				return (
					<BoxParts key={ i } blocks={ [ p ] } title={ app.title } />
				);
			} ) }
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
