/**
 * RankWeb's new screens (trial, &ui=next): Dashboard and Settings, drawn with the Kits admin shell (@kits/shell is
 * window.kits.shell from Kits Core) and fed by the rankweb/v1 routes. Data and the screen come from window.rankwebApp
 * (includes/rankweb-ui.php).
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
	EmptyState,
	ErrorState,
	Field,
	LoadingState,
	MediaField,
	MiniChart,
	Notice,
	PageParts,
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
import { ModuleCards } from './modules';

const app = window.rankwebApp || {};
const path = ( p ) => `/${ app.rest }/${ p }`;

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

/**
 * The daily log as MiniChart points for one metric: the days within the range, oldest first, one bar each.
 *
 * @param {Object} log    { 'Y-m-d': { metric: n } }.
 * @param {string} today  Site date today (Y-m-d).
 * @param {string} metric Key to read from each day.
 * @param {number} days   How far back to include.
 * @return {Array} [ { label: 'MM-DD', value } ].
 */
function logPoints( log, today, metric, days ) {
	const at = new Date( `${ today }T12:00:00Z` );
	at.setUTCDate( at.getUTCDate() - days );
	const from = at.toISOString().slice( 0, 10 );
	return Object.keys( log || {} )
		.filter( ( d ) => d >= from && undefined !== log[ d ]?.[ metric ] )
		.sort()
		.map( ( d ) => ( {
			label: d.slice( 5 ), // MM-DD
			value: Number( log[ d ][ metric ] ) || 0,
		} ) );
}

// The headline tiles, grouped into themed cards so each group is a real <h2> with its own sub-grid (greyscale, shell
// tokens; no colour carries meaning). Keys not listed here fall into "System".
const TILE_GROUPS = [
	{
		id: 'content',
		keys: [ 'titles', 'descs', 'focus', 'schema' ],
		title: __( 'Content & metadata', 'aw-seo-extras' ),
		description: __(
			'Search titles, descriptions, focus keywords and Rank Math schema across the item types RankWeb manages.',
			'aw-seo-extras'
		),
	},
	{
		id: 'faq-index',
		keys: [ 'faq', 'noindex' ],
		title: __( 'FAQ & indexing', 'aw-seo-extras' ),
		description: __(
			'Pages carrying an FAQ, and the pages RankWeb’s own rules keep out of search.',
			'aw-seo-extras'
		),
	},
	{
		id: 'system',
		keys: [ 'endpoints', 'rankmath' ],
		title: __( 'System', 'aw-seo-extras' ),
		description: __(
			'The hourly sitemap and llms.txt check, and Rank Math itself.',
			'aw-seo-extras'
		),
	},
];

// Metrics with a daily history worth a trend chart (unit shown in the screen-reader table).
const TREND_METRICS = [
	{
		metric: 'titles',
		label: __( 'Search titles missing', 'aw-seo-extras' ),
		unit: __( 'items', 'aw-seo-extras' ),
	},
	{
		metric: 'descs',
		label: __( 'Descriptions missing', 'aw-seo-extras' ),
		unit: __( 'items', 'aw-seo-extras' ),
	},
	{
		metric: 'focus',
		label: __( 'Focus keywords missing', 'aw-seo-extras' ),
		unit: __( 'items', 'aw-seo-extras' ),
	},
	{
		metric: 'schema',
		label: __( 'Schema entries to write', 'aw-seo-extras' ),
		unit: __( 'items', 'aw-seo-extras' ),
	},
	{
		metric: 'faq',
		label: __( 'Pages with an FAQ', 'aw-seo-extras' ),
		unit: __( 'pages', 'aw-seo-extras' ),
	},
	{
		metric: 'noindex',
		label: __( 'Kept out of search', 'aw-seo-extras' ),
		unit: __( 'pages', 'aw-seo-extras' ),
	},
];

/**
 * The Dashboard's three views of one set of counts: 'dashboard' (tiles and the tools), 'insights' (trends and gaps) and
 * 'modules' (switches, the modules table, where things are). Each is its own page and tab, so none is long.
 *
 * @param {Object} props
 * @param {string} props.view 'dashboard', 'insights' or 'modules'.
 */
function Dashboard( { view } ) {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ days, setDays ] = useState( 28 );
	const [ note, setNote ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ tries, setTries ] = useState( 0 );
	const [ waitFor, setWaitFor ] = useState( null ); // the count time before Count now

	const load = () => {
		setError( '' );
		apiFetch( { path: path( 'dashboard' ) } )
			.then( setData )
			.catch( ( e ) => setError( e.message || String( e ) ) );
	};
	useEffect( load, [] );
	// While the first count runs in the background, look again every 20 seconds (a read; nothing is counted).
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
						'aw-seo-extras'
					)
				);
			} )
			.catch( ( e ) => setNote( e.message || String( e ) ) )
			.finally( () => setBusy( false ) );
	};

	if ( error ) {
		return <ErrorState message={ error } onRetry={ load } />;
	}
	if ( ! data ) {
		return (
			<LoadingState
				label={ __( 'Loading the counts…', 'aw-seo-extras' ) }
			/>
		);
	}
	const before = rowBefore( data.log, data.today, days );
	const attention = data.tiles.filter( ( t ) => t.tone );
	const delta = ( t ) => {
		if ( ! before || ! t.metric || null === t.raw ) {
			return '';
		}
		const old = before[ 1 ]?.[ t.metric ];
		if ( undefined === old ) {
			return '';
		}
		const d = t.raw - old;
		return sprintf(
			/* translators: 1: change (+3 or -2), 2: number of days. */
			__( '%1$s in %2$d days', 'aw-seo-extras' ),
			( d > 0 ? '+' : '' ) + d.toLocaleString(),
			days
		);
	};
	let after = data.atText
		? sprintf(
				/* translators: %s: date and time. */
				__( 'Counted %s.', 'aw-seo-extras' ),
				data.atText
			)
		: __( 'Counting for the first time…', 'aw-seo-extras' );
	if ( data.atText && ! before ) {
		after +=
			' ' +
			sprintf(
				/* translators: %d: number of days. */
				__(
					'No count from %d days ago yet, so no change is shown.',
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
	const noindex = data.tiles.find( ( t ) => 'noindex' === t.key );

	const byKey = Object.fromEntries( data.tiles.map( ( t ) => [ t.key, t ] ) );
	const known = new Set( TILE_GROUPS.flatMap( ( g ) => g.keys ) );
	const extra = data.tiles.filter( ( t ) => ! known.has( t.key ) );
	const renderTile = ( t ) => (
		<Tile
			key={ t.key }
			label={ t.label }
			value={ t.value }
			delta={ delta( t ) }
			sub={ t.sub }
			href={ t.href }
			tone={ t.tone }
			loading={ ( data.counting && t.metric ) || t.counting }
		/>
	);
	const trendCharts = TREND_METRICS.map( ( m ) => ( {
		...m,
		points: logPoints( data.log, data.today, m.metric, days ),
	} ) ).filter( ( m ) => m.points.length >= 2 );
	const dists = [ 'titles', 'descs', 'focus' ]
		.map( ( k ) => byKey[ k ] )
		.filter( ( t ) => t && Array.isArray( t.dist ) && t.dist.length > 0 );

	return (
		<>
			{ 'modules' !== view && (
				<ChipRow
					label={ __( 'Compare with', 'aw-seo-extras' ) }
					value={ days }
					onChange={ setDays }
					chips={ RANGES.map( ( n ) => ( {
						value: n,
						label: sprintf(
							/* translators: %d: number of days. */
							__( 'Last %d days', 'aw-seo-extras' ),
							n
						),
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
			) }
			{ note && (
				<Notice tone="info" onDismiss={ () => setNote( '' ) }>
					{ note }
				</Notice>
			) }
			{ 'dashboard' === view && attention.length > 0 && (
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
			{ 'dashboard' === view && ( app.tools || [] ).length > 0 && (
				<SectionCard
					id="rankweb-tools"
					title={ __( 'Tools', 'aw-seo-extras' ) }
					description={ __(
						'RankWeb’s working screens, also under RankWeb in the menu.',
						'aw-seo-extras'
					) }
				>
					<RefTable
						caption={ __( 'Tools', 'aw-seo-extras' ) }
						head={ [
							__( 'Tool', 'aw-seo-extras' ),
							__( 'What it does', 'aw-seo-extras' ),
						] }
						rows={ app.tools.map( ( t ) => [
							<a key="a" href={ t.url }>
								{ t.label }
							</a>,
							t.desc,
						] ) }
					/>
				</SectionCard>
			) }
			{ 'dashboard' === view &&
				TILE_GROUPS.map( ( g, gi ) => {
					const tiles = g.keys
						.map( ( k ) => byKey[ k ] )
						.filter( Boolean )
						// Any tile the groups don't name joins the last group, so nothing is dropped.
						.concat( gi === TILE_GROUPS.length - 1 ? extra : [] );
					if ( ! tiles.length ) {
						return null;
					}
					return (
						<SectionCard
							key={ g.id }
							id={ `rankweb-${ g.id }` }
							title={ g.title }
							description={ g.description }
						>
							<TileGrid label={ g.title }>
								{ tiles.map( renderTile ) }
							</TileGrid>
						</SectionCard>
					);
				} ) }
			{ 'insights' === view && trendCharts.length > 0 && (
				<SectionCard
					id="rankweb-trends"
					title={ __( 'Over time', 'aw-seo-extras' ) }
					description={ __(
						'One bar per recorded day within the compare range; the latest day is the darkest. The full numbers are in each chart’s table.',
						'aw-seo-extras'
					) }
				>
					<div className="rankweb-dash-grid">
						{ trendCharts.map( ( m ) => (
							<MiniChart
								key={ m.metric }
								label={ m.label }
								points={ m.points }
								unit={ m.unit }
							/>
						) ) }
					</div>
				</SectionCard>
			) }
			{ 'insights' === view && ! data.counting && dists.length > 0 && (
				<SectionCard
					id="rankweb-gaps"
					title={ __( 'Where the gaps are', 'aw-seo-extras' ) }
					description={ __(
						'The item types with the most missing fields right now (up to six types per chart). The full numbers are in each chart’s table.',
						'aw-seo-extras'
					) }
				>
					<div className="rankweb-dash-grid">
						{ dists.map( ( t ) => (
							<MiniChart
								key={ t.key }
								label={ t.label }
								points={ t.dist }
								unit={ __( 'items', 'aw-seo-extras' ) }
							/>
						) ) }
					</div>
				</SectionCard>
			) }
			{ 'insights' === view && noindex && ! data.counting && (
				<SectionCard
					id="kept-out"
					title={ __( 'Kept out of search', 'aw-seo-extras' ) }
					description={ __(
						'Pages RankWeb’s own rules mark noindex. They come back by themselves when they qualify (a contact added, more listings).',
						'aw-seo-extras'
					) }
				>
					<ul className="kits-shell-list">
						{ ( noindex.detail || [] ).map( ( line ) => (
							<li key={ line }>{ line }</li>
						) ) }
					</ul>
				</SectionCard>
			) }
			{ 'modules' === view && <ModuleCards /> }
			{ 'modules' === view && (
				<SectionCard
					id="where"
					title={ __( 'Where things are', 'aw-seo-extras' ) }
					description={ __(
						'Everything Rank Math doesn’t do or keeps for Premium, for this site. Rank Math holds everything else; where a setting lives in Rank Math, the link goes there.',
						'aw-seo-extras'
					) }
				>
					<RefTable
						caption={ __( 'On edit screens', 'aw-seo-extras' ) }
						head={ [
							__( 'On edit screens', 'aw-seo-extras' ),
							__( 'What you edit there', 'aw-seo-extras' ),
						] }
						rows={ data.where.boxes }
					/>
					<RefTable
						caption={ __( 'Commands', 'aw-seo-extras' ) }
						head={ [
							__(
								'Commands (WP-CLI on the server)',
								'aw-seo-extras'
							),
							__( 'What it does', 'aw-seo-extras' ),
						] }
						rows={ data.where.commands.map( ( c ) => [
							<code key="c">{ c[ 0 ] }</code>,
							c[ 1 ],
						] ) }
					/>
					<RefTable
						caption={ __( 'In Rank Math', 'aw-seo-extras' ) }
						head={ [
							__( 'In Rank Math', 'aw-seo-extras' ),
							__( 'What is set there', 'aw-seo-extras' ),
						] }
						rows={ data.where.rankMath.map( ( r ) => [
							<a key="a" href={ r.url }>
								{ r.label }
							</a>,
							r.what,
						] ) }
					/>
				</SectionCard>
			) }
		</>
	);
}

/* ---------- Settings ---------- */

const SERVICES = 'services';

/**
 * The open group from the address (#titles), so a link opens the right group.
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

function rowsFor( text ) {
	const n = ( text.match( /\n/g ) || [] ).length + 1;
	return Math.min( 16, Math.max( 3, n + 1 ) );
}

/**
 * One field: a number or a text box (one item per line).
 *
 * @param {Object}   props
 * @param {Object}   props.f        Field from the route.
 * @param {string}   props.value    Text now.
 * @param {Function} props.onChange New text.
 */
function SettingField( { f, value, onChange } ) {
	const changed = value !== f.defaultText;
	return (
		<Field
			label={ f.label }
			help={ f.help }
			changed={ changed }
			onReset={ () => onChange( f.defaultText ) }
			render={ ( id, helpId ) =>
				'int' === f.type ? (
					<input
						id={ id }
						type="number"
						min="0"
						value={ value }
						aria-describedby={ helpId }
						onChange={ ( e ) => onChange( e.target.value ) }
					/>
				) : (
					<textarea
						id={ id }
						rows={ rowsFor( value ) }
						value={ value }
						spellCheck="false"
						aria-describedby={ helpId }
						onChange={ ( e ) => onChange( e.target.value ) }
					/>
				)
			}
		/>
	);
}

/**
 * A group's texts and switches as saved.
 *
 * @param {Object} group Group from the route.
 * @return {Object} { t: { CONST: text }, on: { module: bool }, deleteData }.
 */
function startOf( group ) {
	const t = {};
	const on = {};
	group.blocks.forEach( ( b ) => {
		b.fields.forEach( ( f ) => ( t[ f.const ] = f.text ) );
		on[ b.key ] = b.on;
	} );
	return { t, on, deleteData: !! group.deleteData };
}

/**
 * "FAQ box on" as saved.
 *
 * @param {Object} group Group from the route.
 * @return {Object} { slug: bool }.
 */
function faqOf( group ) {
	return ( group.faqTypes || [] ).reduce(
		( o, x ) => ( { ...o, [ x.slug ]: x.on } ),
		{}
	);
}

/**
 * "Listings kept indexed" as saved: one ID per line.
 *
 * @param {Object} group Group from the route.
 * @return {string} The IDs.
 */
function keepOf( group ) {
	return ( group.keepListings?.ids || [] ).join( '\n' );
}

/**
 * The IDs in a text: whole numbers, in order, once each.
 *
 * @param {string} text Typed text.
 * @return {number[]} IDs.
 */
function idsOf( text ) {
	return [
		...new Set(
			text
				.split( /[\s,]+/ )
				.map( ( x ) => parseInt( x, 10 ) )
				.filter( ( n ) => n > 0 )
		),
	];
}

/**
 * The default share image as saved.
 *
 * @param {Object} group Group from the route.
 * @return {Object} { id, url }.
 */
function shareOf( group ) {
	return group.shareImage || { id: 0, url: '' };
}

/**
 * A group with its own Save: the fields of its modules (or the module switches), plus its extras.
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
	const [ faq, setFaq ] = useState( () => faqOf( group ) );
	const [ keep, setKeep ] = useState( () => keepOf( group ) );
	const [ share, setShare ] = useState( () => shareOf( group ) );
	// Reset each part only when the route sent it anew, so saving the FAQ box keeps unsaved fields and the other way round.
	useEffect( () => setState( startOf( group ) ), [ group.blocks ] ); // eslint-disable-line react-hooks/exhaustive-deps
	useEffect( () => setFaq( faqOf( group ) ), [ group.faqTypes ] ); // eslint-disable-line react-hooks/exhaustive-deps
	useEffect( () => setKeep( keepOf( group ) ), [ group.keepListings ] ); // eslint-disable-line react-hooks/exhaustive-deps
	useEffect( () => setShare( shareOf( group ) ), [ group.shareImage ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const isModules = 'modules' === group.slug;
	const fieldsDirty = group.blocks.some( ( b ) =>
		b.fields.some( ( f ) => state.t[ f.const ] !== f.text )
	);
	const onDirty_ = isModules
		? group.blocks.some(
				( b ) => b.switch && state.on[ b.key ] !== b.on
			) || state.deleteData !== !! group.deleteData
		: fieldsDirty;
	const faqDirty = ( group.faqTypes || [] ).some(
		( x ) => faq[ x.slug ] !== x.on
	);
	const keepDirty =
		!! group.keepListings &&
		idsOf( keep ).join( ',' ) !== group.keepListings.ids.join( ',' );
	const shareDirty = !! group.shareImage && share.id !== group.shareImage.id;
	useEffect(
		() =>
			onDirty(
				group.slug,
				onDirty_ || faqDirty || keepDirty || shareDirty
			),
		[ group.slug, onDirty_, faqDirty, keepDirty, shareDirty, onDirty ]
	);

	const save = ( body, route = `settings/${ group.slug }` ) => {
		// A group's extras save on their own routes: only that part of the group is taken from the answer.
		const extra = {
			'settings/faq-types': 'faqTypes',
			'settings/keep-listings': 'keepListings',
			'settings/share-image': 'shareImage',
		}[ route ];
		setBusy( true );
		setMsg( null );
		return apiFetch( { path: path( route ), method: 'POST', data: body } )
			.then( ( r ) => {
				onSaved(
					extra
						? { ...group, [ extra ]: r.group[ extra ] }
						: {
								...r.group,
								faqTypes: group.faqTypes,
								keepListings: group.keepListings,
								shareImage: group.shareImage,
							}
				);
				setMsg( { tone: 'success', text: r.message } );
			} )
			.catch( ( e ) =>
				setMsg( { tone: 'error', text: e.message || String( e ) } )
			)
			.finally( () => setBusy( false ) );
	};
	const saveFields = () =>
		save(
			isModules
				? {
						on: Object.fromEntries(
							group.blocks
								.filter( ( b ) => b.switch )
								.map( ( b ) => [ b.key, !! state.on[ b.key ] ] )
						),
						delete_data: state.deleteData,
					}
				: { fields: state.t }
		);
	const restore = () => {
		if (
			// eslint-disable-next-line no-alert -- a plain browser confirm before a reset, as core's list tables do
			window.confirm(
				__( 'Reset this section to its defaults?', 'aw-seo-extras' )
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
				disabled={ ! onDirty_ }
				onClick={ saveFields }
			>
				{ __( 'Save changes', 'aw-seo-extras' ) }
			</Button>
			<Button busy={ busy } onClick={ restore }>
				{ __( 'Reset this section', 'aw-seo-extras' ) }
			</Button>
			<span aria-live="polite">
				{ msg && 'success' === msg.tone ? msg.text : '' }
			</span>
		</>
	);

	return (
		<SettingsGroup
			// Not the bare slug: the chips' #slug links switch groups like tabs, without a jump to an anchor.
			id={ `rankweb-group-${ group.slug }` }
			title={ group.label }
			description={
				isModules
					? __(
							'A module switched off is not loaded: its screens, checks and output are gone until it is switched on again.',
							'aw-seo-extras'
						)
					: ''
			}
			footer={ footer }
		>
			{ msg && 'error' === msg.tone && (
				<Notice tone="error" onDismiss={ () => setMsg( null ) }>
					{ msg.text }
				</Notice>
			) }
			{ isModules &&
				group.blocks.map( ( b ) => (
					<Switch
						key={ b.key }
						label={ b.label }
						checked={ b.switch ? state.on[ b.key ] : true }
						disabled={ ! b.switch }
						help={
							b.switch
								? b.desc
								: `${ b.desc } ${ __(
										'Always on: other modules use it.',
										'aw-seo-extras'
									) }`
						}
						onChange={ ( v ) =>
							setState( ( s ) => ( {
								...s,
								on: { ...s.on, [ b.key ]: v },
							} ) )
						}
					/>
				) ) }
			{ isModules && (
				<label htmlFor="rankweb-delete-data">
					<input
						id="rankweb-delete-data"
						type="checkbox"
						checked={ state.deleteData }
						onChange={ ( e ) => {
							const v = e.target.checked;
							setState( ( s ) => ( { ...s, deleteData: v } ) );
						} }
					/>{ ' ' }
					{ __(
						"Delete this plugin's data when it is deleted",
						'aw-seo-extras'
					) }
				</label>
			) }
			{ ! isModules &&
				group.blocks.map( ( b ) => (
					<SettingsBlock
						key={ b.key }
						title={ b.label }
						description={ b.desc }
						changeIn={ b.changeIn }
						note={
							b.on
								? ''
								: __(
										'Off: switch it on under Modules.',
										'aw-seo-extras'
									)
						}
					>
						{ b.fields.map( ( f ) => (
							<SettingField
								key={ f.const }
								f={ f }
								value={ state.t[ f.const ] ?? '' }
								onChange={ ( v ) => setText( f.const, v ) }
							/>
						) ) }
					</SettingsBlock>
				) ) }
			{ group.faqTypes && (
				<SettingsBlock
					title={ __( 'FAQ box on', 'aw-seo-extras' ) }
					description={ __(
						'Ticked types have the FAQ box on their edit screens. A type registered later has it too.',
						'aw-seo-extras'
					) }
				>
					{ [
						[ 'post', __( 'Post types', 'aw-seo-extras' ) ],
						[ 'term', __( 'Taxonomies', 'aw-seo-extras' ) ],
					].map( ( [ store, label ] ) => (
						<fieldset key={ store } className="kits-shell-checks">
							<legend className="kits-shell-field__label">
								{ label }
							</legend>
							{ group.faqTypes
								.filter( ( x ) => x.store === store )
								.map( ( x ) => (
									<label
										key={ x.slug }
										htmlFor={ `rankweb-faq-${ x.slug }` }
									>
										<input
											id={ `rankweb-faq-${ x.slug }` }
											type="checkbox"
											checked={ !! faq[ x.slug ] }
											onChange={ ( e ) =>
												setFaq( {
													...faq,
													[ x.slug ]:
														e.target.checked,
												} )
											}
										/>{ ' ' }
										{ x.label } <code>{ x.slug }</code>
									</label>
								) ) }
						</fieldset>
					) ) }
					<div>
						<Button
							busy={ busy }
							disabled={ ! faqDirty }
							onClick={ () =>
								save(
									{
										on: Object.keys( faq ).filter(
											( k ) => faq[ k ]
										),
									},
									'settings/faq-types'
								)
							}
						>
							{ __( 'Save FAQ box types', 'aw-seo-extras' ) }
						</Button>
					</div>
				</SettingsBlock>
			) }
			{ group.keepListings && (
				<SettingsBlock
					title={ __( 'Listings kept indexed', 'aw-seo-extras' ) }
					description={ sprintf(
						/* translators: %s: number of listings. */
						_n(
							'%s listing stays indexed though it has no contact (it had Search Console impressions).',
							'%s listings stay indexed though they have no contact (they had Search Console impressions).',
							group.keepListings.count,
							'aw-seo-extras'
						),
						group.keepListings.count.toLocaleString()
					) }
				>
					{ group.keepListings.first.length > 0 && (
						<ul className="kits-shell-list">
							{ group.keepListings.first.map( ( l ) => (
								<li key={ l.url + l.name }>
									{ l.url ? (
										<a href={ l.url }>{ l.name }</a>
									) : (
										l.name
									) }
								</li>
							) ) }
							{ group.keepListings.count >
								group.keepListings.first.length && (
								<li>
									{ sprintf(
										/* translators: %s: number of listings. */
										__( 'and %s more', 'aw-seo-extras' ),
										(
											group.keepListings.count -
											group.keepListings.first.length
										).toLocaleString()
									) }
								</li>
							) }
						</ul>
					) }
					<Field
						label={ __( 'Listing IDs', 'aw-seo-extras' ) }
						help={ __(
							'One ID per line (or comma separated). Only Directory listings are kept; other IDs are left out.',
							'aw-seo-extras'
						) }
						render={ ( id, helpId ) => (
							<textarea
								id={ id }
								className="large-text code"
								rows={ 6 }
								spellCheck="false"
								value={ keep }
								aria-describedby={ helpId }
								onChange={ ( e ) => setKeep( e.target.value ) }
							/>
						) }
					/>
					<div>
						<Button
							busy={ busy }
							disabled={ ! keepDirty }
							onClick={ () =>
								save(
									{ ids: idsOf( keep ) },
									'settings/keep-listings'
								)
							}
						>
							{ __(
								'Save listings kept indexed',
								'aw-seo-extras'
							) }
						</Button>
					</div>
				</SettingsBlock>
			) }
			{ group.shareImage && (
				<SettingsBlock
					title={ __( 'Default share image', 'aw-seo-extras' ) }
					description={ __(
						'Shown when a page shared on social media has no picture of its own. Best at 1200 × 630.',
						'aw-seo-extras'
					) }
				>
					<MediaField
						label={ __( 'Image', 'aw-seo-extras' ) }
						help={ __(
							'An image from the media library. None: pages without a picture share no image.',
							'aw-seo-extras'
						) }
						value={ share.id }
						url={ share.url }
						onChange={ ( id, url ) => setShare( { id, url } ) }
					/>
					<div>
						<Button
							busy={ busy }
							disabled={ ! shareDirty }
							onClick={ () =>
								save( { id: share.id }, 'settings/share-image' )
							}
						>
							{ __( 'Save share image', 'aw-seo-extras' ) }
						</Button>
					</div>
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
			.catch( ( e ) => setError( e.message || String( e ) ) );
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
			id={ `rankweb-group-${ SERVICES }` }
			title={ __( 'Connected services', 'aw-seo-extras' ) }
			description={ __(
				'What RankWeb works with. Keys stay in Kits → Keys.',
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
							{ s.features.map( ( f ) => (
								<li key={ f.label }>
									{ f.label }:{ ' ' }
									<strong>
										{ f.on
											? __( 'On', 'aw-seo-extras' )
											: __( 'Off', 'aw-seo-extras' ) }
									</strong>
								</li>
							) ) }
						</ul>
						<p>
							{ s.links.map( ( l, i ) => (
								<span key={ l.url }>
									{ i > 0 && ' · ' }
									<a href={ l.url }>{ l.label }</a>
								</span>
							) ) }
						</p>
						<p className="kits-shell-field__help">{ s.note }</p>
					</ServiceRow>
				) ) }
			</ServiceList>
		</SectionCard>
	);
}

function Settings() {
	const [ groups, setGroups ] = useState( null );
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
			setGroups( ( gs ) =>
				gs.map( ( x ) => ( x.slug === ng.slug ? ng : x ) )
			),
		[]
	);

	const load = () => {
		setError( '' );
		apiFetch( { path: path( 'settings' ) } )
			.then( ( r ) => {
				setGroups( r.groups );
				setOpen( hashGroup( r.groups ) );
			} )
			.catch( ( e ) => setError( e.message || String( e ) ) );
	};
	useEffect( load, [] );
	useEffect( () => {
		const onHash = () => groups && setOpen( hashGroup( groups ) );
		window.addEventListener( 'hashchange', onHash );
		return () => window.removeEventListener( 'hashchange', onHash );
	}, [ groups ] );
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
	if ( ! groups ) {
		return (
			<LoadingState
				label={ __( 'Loading the settings…', 'aw-seo-extras' ) }
			/>
		);
	}
	const current = groups.find( ( g ) => g.slug === open );
	const chips = [
		{
			value: SERVICES,
			label: __( 'Connected services', 'aw-seo-extras' ),
			href: `#${ SERVICES }`,
		},
		...groups.map( ( g ) => ( {
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
			/>
			{ SERVICES === open && <Services /> }
			{ groups.map( ( g ) => (
				<div key={ g.slug } hidden={ g.slug !== open }>
					<Group
						group={ g }
						onSaved={ replaceGroup }
						onDirty={ markDirty }
					/>
				</div>
			) ) }
			{ ! current && SERVICES !== open && (
				<EmptyState title={ __( 'No such group.', 'aw-seo-extras' ) } />
			) }
		</>
	);
}

/** FAQ: the FAQ screen's own output (FAQ box on, the pages with an FAQ, the counts), drawn with Kits' page parts. */
function Faq() {
	return (
		<SectionCard id="faq" title={ __( 'FAQ', 'aw-seo-extras' ) }>
			<PageParts
				parts={ app.parts || [] }
				title={ __( 'FAQ', 'aw-seo-extras' ) }
			/>
		</SectionCard>
	);
}

function App() {
	return (
		<AppShell header={ <Header /> }>
			{ 'settings' === app.screen && <Settings /> }
			{ 'faq' === app.screen && <Faq /> }
			{ [ 'dashboard', 'insights', 'modules' ].includes( app.screen ) && (
				<Dashboard view={ app.screen } />
			) }
		</AppShell>
	);
}

const root = document.getElementById( 'rankweb-app' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
