/**
 * DirectoryWeb's new screens (trial, &ui=next): Dashboard, Claims and Settings, drawn with the Kits admin shell
 * (@kits/shell is window.kits.shell from Kits Core) and fed by the directoryweb/v1 routes. Data and the screen come
 * from window.directorywebApp (includes/directoryweb-ui.php).
 */
import { createRoot, useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import {
	AppShell,
	AppHeader,
	Button,
	ChipRow,
	DataList,
	DetailPanel,
	EmptyState,
	ErrorState,
	Field,
	LinkedSetting,
	LoadingState,
	MediaThumbs,
	Notice,
	RefTable,
	SectionCard,
	ServiceList,
	ServiceRow,
	SettingsBlock,
	SettingsGroup,
	Tile,
	TileGrid,
} from '@kits/shell'; // eslint-disable-line import/no-unresolved -- Kits Core's shell script (window.kits.shell), mapped in webpack.config.js

const app = window.directorywebApp || {};
const path = ( p ) => `/${ app.rest }/${ p }`;
const errText = ( e ) => e?.message || String( e );

/** Header with the tabs, and the way back to the current screen (Claims) or to the listings. */
function Header() {
	return (
		<AppHeader
			icon={ app.icon }
			name={ app.name }
			version={ app.version }
			tabs={ app.tabs }
			aside={
				<a href={ app.listings }>
					{ __( 'Directory listings', 'directoryweb' ) }
				</a>
			}
		/>
	);
}

/* ---------- Dashboard ---------- */

function Dashboard() {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ section, setSection ] = useState( 'all' );
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
						'directoryweb'
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
				label={ __( 'Loading the counts…', 'directoryweb' ) }
			/>
		);
	}
	const tiles = data.tiles.filter(
		( t ) => 'all' === section || t.section === section
	);
	const attention = data.tiles.filter( ( t ) => t.tone && ! t.missing );
	const after = data.atText
		? sprintf(
				/* translators: %s: date and time. */
				__( 'Counted %s.', 'directoryweb' ),
				data.atText
			)
		: __( 'Counting for the first time…', 'directoryweb' );
	const published = data.tiles.find( ( t ) => 'published' === t.key );

	return (
		<>
			<ChipRow
				label={ __( 'Show', 'directoryweb' ) }
				value={ section }
				onChange={ setSection }
				chips={ data.sections }
				after={
					<>
						{ after }
						{ waiting &&
							tries >= 15 &&
							' ' +
								__(
									'The count is taking longer than usual; reload this page later.',
									'directoryweb'
								) }{ ' ' }
						<Button
							variant="link"
							busy={ busy }
							onClick={ countNow }
						>
							{ __( 'Count now', 'directoryweb' ) }
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
					<strong>{ __( 'Needs attention', 'directoryweb' ) }</strong>
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
				label={ __( 'The Directory at a glance', 'directoryweb' ) }
			>
				{ tiles.map( ( t ) => (
					<Tile
						key={ t.key }
						label={ t.label }
						value={ t.value }
						sub={ t.sub }
						href={ t.href }
						tone={ t.tone }
						missing={ t.missing }
						hide={ t.hide }
						loading={ data.counting && ! t.live && ! t.missing }
					/>
				) ) }
			</TileGrid>
			{ published &&
				! data.counting &&
				( published.detail || [] ).length > 0 &&
				( 'all' === section || 'listings' === section ) && (
					<SectionCard
						id="directoryweb-types"
						title={ __(
							'Published listings by type',
							'directoryweb'
						) }
					>
						<ul className="kits-shell-list">
							{ published.detail.map( ( line ) => (
								<li key={ line }>{ line }</li>
							) ) }
						</ul>
					</SectionCard>
				) }
		</>
	);
}

/* ---------- Claims ---------- */

const START = {
	status: 'pending',
	search: '',
	state: '',
	type: '',
	has: '',
	days: '0',
	sort: 'sent',
	dir: 'desc',
	page: 1,
};

/**
 * The open claim from the address (#claim-12).
 *
 * @return {number} Entry ID or 0.
 */
function hashClaim() {
	const m = window.location.hash.match( /^#claim-(\d+)$/ );
	return m ? Number( m[ 1 ] ) : 0;
}

/**
 * One claim, read only: who sent it and the changes it proposes. Claims are reviewed on the Claims screen.
 *
 * @param {Object}   props
 * @param {number}   props.id      Entry ID.
 * @param {Function} props.onClose Back to the list.
 */
function Claim( { id, onClose } ) {
	const [ c, setC ] = useState( null );
	const [ error, setError ] = useState( '' );
	const load = () => {
		setError( '' );
		apiFetch( { path: path( `claims/${ id }` ) } )
			.then( setC )
			.catch( ( e ) => setError( errText( e ) ) );
	};
	useEffect( load, [ id ] );

	let body;
	if ( error ) {
		body = <ErrorState message={ error } onRetry={ load } />;
	} else if ( ! c ) {
		body = (
			<LoadingState
				label={ __( 'Loading the claim…', 'directoryweb' ) }
			/>
		);
	} else {
		const pictures = (
			<>
				{ ( c.notes || [] ).map( ( n ) => (
					<p key={ n } className="kits-shell-field__help">
						{ n }
					</p>
				) ) }
				{ ( c.logoUrl || c.photoUrls.length > 0 ) && (
					<p>
						{ c.logoNowUrl && (
							<img
								src={ c.logoNowUrl }
								alt={ __(
									'The listing’s current logo',
									'directoryweb'
								) }
								width="80"
								height="80"
							/>
						) }{ ' ' }
						{ c.logoUrl && (
							<img
								src={ c.logoUrl }
								alt={ __(
									'Logo sent with the claim',
									'directoryweb'
								) }
								width="80"
								height="80"
							/>
						) }{ ' ' }
						{ c.photoUrls.map( ( u, i ) => (
							<img
								key={ u }
								src={ u }
								alt={ sprintf(
									/* translators: %d: photo number. */
									__(
										'Photo %d sent with the claim',
										'directoryweb'
									),
									i + 1
								) }
								width="80"
								height="80"
							/>
						) ) }
					</p>
				) }
				{ c.undone.length > 0 && (
					<p>
						{ sprintf(
							/* translators: %s: field names. */
							__( 'Undone: %s.', 'directoryweb' ),
							c.undone.join( ', ' )
						) }
					</p>
				) }
			</>
		);
		let head = [
			__( 'Field', 'directoryweb' ),
			__( 'Listing now', 'directoryweb' ),
			__( 'Proposed', 'directoryweb' ),
		];
		let caption = __( 'Proposed changes', 'directoryweb' );
		if ( 'rejected' === c.status ) {
			head = [
				__( 'Field', 'directoryweb' ),
				__( 'Proposed (rejected)', 'directoryweb' ),
			];
			caption = __( 'What the claim proposed', 'directoryweb' );
		} else if ( 'approved' === c.status ) {
			caption = __( 'Applied fields', 'directoryweb' );
		}
		body = (
			<>
				<RefTable
					caption={ __( 'Who sent it', 'directoryweb' ) }
					head={ [
						__( 'Claim', 'directoryweb' ),
						__( 'Details', 'directoryweb' ),
					] }
					rows={ [
						...c.who.map( ( w ) => [ w[ 0 ], w[ 1 ] || '—' ] ),
						[
							__( 'Proof', 'directoryweb' ),
							c.proofUrl ? (
								<a
									key="p"
									href={ c.proofUrl }
									target="_blank"
									rel="noreferrer"
								>
									{ __(
										'Open the document',
										'directoryweb'
									) }
								</a>
							) : (
								'—'
							),
						],
					] }
				/>
				{ c.fields.length > 0 ? (
					<RefTable
						caption={ caption }
						head={ head }
						rows={ c.fields.map( ( f ) =>
							'rejected' === c.status
								? [ f.label, f.proposed ]
								: [ f.label, f.current || '—', f.proposed ]
						) }
					/>
				) : (
					<p>
						{ __(
							'Nothing differs from the listing.',
							'directoryweb'
						) }
					</p>
				) }
				{ pictures }
				<p>
					{ 'pending' === c.status
						? __(
								'This screen only shows the claim. Approve or reject it on the Claims screen.',
								'directoryweb'
							)
						: __(
								'This screen only shows the claim. Changes to it are made on the Claims screen.',
								'directoryweb'
							) }{ ' ' }
					<a href={ c.oldScreen }>
						{ __(
							'Open this claim on the Claims screen',
							'directoryweb'
						) }
					</a>
				</p>
				<p>
					{ c.listingUrl && (
						<a href={ c.listingUrl }>
							{ __( 'Edit listing', 'directoryweb' ) }
						</a>
					) }
					{ c.viewUrl && (
						<>
							{ ' · ' }
							<a href={ c.viewUrl }>
								{ __( 'View listing', 'directoryweb' ) }
							</a>
						</>
					) }
				</p>
			</>
		);
	}
	return (
		<SectionCard
			id="directoryweb-claim"
			title={
				c
					? sprintf(
							/* translators: 1: listing name, 2: claim reference. */
							__( 'Claim: %1$s %2$s', 'directoryweb' ),
							c.title,
							c.ref
						)
					: __( 'Claim', 'directoryweb' )
			}
			description={ c ? c.statusText : '' }
			actions={
				<Button variant="link" onClick={ onClose }>
					{ __( 'Back to all claims', 'directoryweb' ) }
				</Button>
			}
		>
			{ body }
		</SectionCard>
	);
}

function Claims() {
	const [ q, setQ ] = useState( START );
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ open, setOpen ] = useState( hashClaim );
	const [ tick, setTick ] = useState( 0 );

	useEffect( () => {
		setLoading( true );
		setError( '' );
		const args = new URLSearchParams(
			Object.entries( q ).filter( ( [ , v ] ) => '' !== v && null !== v )
		).toString();
		apiFetch( { path: `${ path( 'claims' ) }?${ args }` } )
			.then( setData )
			.catch( ( e ) => setError( errText( e ) ) )
			.finally( () => setLoading( false ) );
	}, [ q, tick ] );
	useEffect( () => {
		const onHash = () => setOpen( hashClaim() );
		window.addEventListener( 'hashchange', onHash );
		return () => window.removeEventListener( 'hashchange', onHash );
	}, [] );

	const show = ( id ) => {
		window.location.hash = id ? `claim-${ id }` : '';
		setOpen( id );
	};
	const set = ( k, v ) =>
		setQ( ( o ) => ( { ...o, [ k ]: v, page: 'page' === k ? v : 1 } ) );

	if ( data && ! data.form && 0 === ( data.counts?.all ?? 0 ) ) {
		return (
			<EmptyState
				title={ __( 'The claim form is not set up.', 'directoryweb' ) }
			>
				<p>
					{ __(
						'Choose the claim form under Settings → Fluent Forms. Claims sent with the old form still show here.',
						'directoryweb'
					) }
				</p>
			</EmptyState>
		);
	}
	const counts = data?.counts || {};
	const columns = [
		{
			key: 'title',
			label: __( 'Listing', 'directoryweb' ),
			primary: true,
			sortable: true,
			render: ( r ) => (
				<>
					<strong>{ r.title }</strong>
					{ r.old && (
						<span className="kits-shell-field__help">
							{ ' ' }
							{ __( 'old form', 'directoryweb' ) }
						</span>
					) }
				</>
			),
		},
		{
			key: 'sent',
			label: __( 'Sent', 'directoryweb' ),
			sortable: true,
			render: ( r ) => r.sentText,
		},
		{
			key: 'ref',
			label: __( 'Reference', 'directoryweb' ),
			render: ( r ) => r.ref || '—',
		},
		{
			key: 'name',
			label: __( 'From', 'directoryweb' ),
			render: ( r ) => ( r.role ? `${ r.name }, ${ r.role }` : r.name ),
		},
		{
			key: 'contact',
			label: __( 'Contact', 'directoryweb' ),
			render: ( r ) =>
				[ r.email, r.phone ].filter( Boolean ).join( ' · ' ),
		},
		{
			key: 'changes',
			label: __( 'Fields changed', 'directoryweb' ),
			render: ( r ) => ( null === r.changes ? '—' : r.changes ),
		},
		{
			key: 'status',
			label: __( 'Status', 'directoryweb' ),
			render: ( r ) => r.statusText,
		},
		{
			key: 'decided',
			label: __( 'Decided', 'directoryweb' ),
			render: ( r ) =>
				[ r.decidedBy, r.decidedAt ].filter( Boolean ).join( ', ' ) ||
				'—',
		},
	];
	const sortKey = 'listing' === q.sort ? 'title' : 'sent';
	return (
		<>
			{ open > 0 && <Claim id={ open } onClose={ () => show( 0 ) } /> }
			<SectionCard
				id="directoryweb-claims"
				title={ __( 'Claims', 'directoryweb' ) }
				description={ __(
					'Claims sent with the claim form, to read. Nothing here changes a claim or a listing: claims are approved or rejected on the Claims screen.',
					'directoryweb'
				) }
			>
				<DataList
					label={ __( 'Claims', 'directoryweb' ) }
					columns={ columns }
					rows={ data?.rows || [] }
					total={ data?.total || 0 }
					page={ data?.page || 1 }
					pages={ data?.pages || 1 }
					onPage={ ( p ) => set( 'page', p ) }
					search={ q.search }
					onSearch={ ( v ) => set( 'search', v ) }
					searchLabel={ __(
						'Search listing, claimant or reference',
						'directoryweb'
					) }
					views={ [
						[ 'pending', __( 'Waiting', 'directoryweb' ) ],
						[ 'approved', __( 'Approved', 'directoryweb' ) ],
						[ 'rejected', __( 'Rejected', 'directoryweb' ) ],
						[ 'all', __( 'All', 'directoryweb' ) ],
					].map( ( [ value, label ] ) => ( {
						value,
						label,
						count: counts[ value ],
					} ) ) }
					view={ q.status }
					onView={ ( v ) => set( 'status', v ) }
					filters={ [
						{
							key: 'state',
							label: __( 'State', 'directoryweb' ),
							value: q.state,
							options: [
								{
									value: '',
									label: __( 'All states', 'directoryweb' ),
								},
								...( data?.states || [] ),
							],
						},
						{
							key: 'type',
							label: __( 'Listing type', 'directoryweb' ),
							value: q.type,
							options: [
								{
									value: '',
									label: __( 'All types', 'directoryweb' ),
								},
								...( data?.types || [] ),
							],
						},
						{
							key: 'has',
							label: __( 'Sent with', 'directoryweb' ),
							value: q.has,
							options: [
								{
									value: '',
									label: __( 'Anything', 'directoryweb' ),
								},
								{
									value: 'logo',
									label: __( 'A logo', 'directoryweb' ),
								},
								{
									value: 'photos',
									label: __( 'Photos', 'directoryweb' ),
								},
								{
									value: 'proof',
									label: __( 'Proof', 'directoryweb' ),
								},
							],
						},
						{
							key: 'days',
							label: __( 'Sent', 'directoryweb' ),
							value: q.days,
							options: [
								{
									value: '0',
									label: __( 'Any time', 'directoryweb' ),
								},
								{
									value: '7',
									label: __( 'Last 7 days', 'directoryweb' ),
								},
								{
									value: '30',
									label: __( 'Last 30 days', 'directoryweb' ),
								},
								{
									value: '90',
									label: __( 'Last 90 days', 'directoryweb' ),
								},
							],
						},
					] }
					onFilter={ set }
					sort={ { key: sortKey, dir: q.dir } }
					onSort={ ( key, dir ) =>
						setQ( ( o ) => ( {
							...o,
							sort: 'title' === key ? 'listing' : 'sent',
							dir,
							page: 1,
						} ) )
					}
					rowActions={ ( r ) =>
						r.old
							? [
									{
										label: __(
											'Review on its own screen',
											'directoryweb'
										),
										href: r.oldUrl,
									},
								]
							: [
									{
										label: __( 'View', 'directoryweb' ),
										onClick: () => show( r.entry ),
									},
									...( r.listingUrl
										? [
												{
													label: __(
														'Edit listing',
														'directoryweb'
													),
													href: r.listingUrl,
												},
											]
										: [] ),
								]
					}
					loading={ loading }
					error={ error }
					onRetry={ () => setTick( ( t ) => t + 1 ) }
					emptyTitle={
						'pending' === q.status
							? __( 'No claims waiting.', 'directoryweb' )
							: __( 'No claims match.', 'directoryweb' )
					}
				/>
			</SectionCard>
		</>
	);
}

/* ---------- Settings ---------- */

/**
 * Read-only values with where each is set.
 *
 * @param {Object} props
 * @param {Array}  props.facts   [ { label, value, where } ].
 * @param {string} props.caption Table caption.
 */
function Facts( { facts, caption } ) {
	if ( ! facts || ! facts.length ) {
		return null;
	}
	return (
		<RefTable
			caption={ caption }
			head={ [
				__( 'Setting', 'directoryweb' ),
				__( 'Now', 'directoryweb' ),
			] }
			rows={ facts.map( ( f ) => [
				f.label,
				<>
					{ f.value || '—' }
					<span className="kits-shell-field__help">
						{ ' ' }
						· { f.where }
					</span>
				</>,
			] ) }
		/>
	);
}

/**
 * The claim form: the one setting saved here (only when it changed).
 *
 * @param {Object}   props
 * @param {Object}   props.form    { value, options }.
 * @param {Function} props.onSaved New settings from the route.
 */
function ClaimForm( { form, onSaved } ) {
	const [ value, setValue ] = useState( String( form.value || '' ) );
	const [ busy, setBusy ] = useState( false );
	const [ msg, setMsg ] = useState( null );
	useEffect( () => setValue( String( form.value || '' ) ), [ form.value ] );
	const dirty = value !== String( form.value || '' );
	const save = () => {
		setBusy( true );
		setMsg( null );
		apiFetch( {
			path: path( 'settings/claim-form' ),
			method: 'POST',
			data: { form: Number( value ) || 0 },
		} )
			.then( ( r ) => {
				onSaved( r.settings );
				setMsg( { tone: 'success', text: r.message } );
			} )
			.catch( ( e ) => setMsg( { tone: 'error', text: errText( e ) } ) )
			.finally( () => setBusy( false ) );
	};
	if ( ! form.options.length ) {
		return (
			<p>
				{ __(
					'Fluent Forms has no forms, or is not active.',
					'directoryweb'
				) }
			</p>
		);
	}
	return (
		<>
			{ msg && (
				<Notice tone={ msg.tone } onDismiss={ () => setMsg( null ) }>
					{ msg.text }
				</Notice>
			) }
			<Field
				label={ __( 'Claim form', 'directoryweb' ) }
				help={ __(
					'The Fluent Forms form shown as “Claim this listing”.',
					'directoryweb'
				) }
				render={ ( id, helpId ) => (
					<select
						id={ id }
						value={ value }
						aria-describedby={ helpId }
						onChange={ ( e ) => setValue( e.target.value ) }
					>
						{ ! form.value && (
							<option value="">
								{ __( 'Choose a form', 'directoryweb' ) }
							</option>
						) }
						{ form.options.map( ( o ) => (
							<option key={ o.value } value={ String( o.value ) }>
								{ o.label }
							</option>
						) ) }
					</select>
				) }
			/>
			<Button
				variant="primary"
				busy={ busy }
				disabled={ ! dirty }
				onClick={ save }
			>
				{ __( 'Save changes', 'directoryweb' ) }
			</Button>
		</>
	);
}

function Settings() {
	const [ s, setS ] = useState( null );
	const [ error, setError ] = useState( '' );
	const load = () => {
		setError( '' );
		apiFetch( { path: path( 'settings' ) } )
			.then( setS )
			.catch( ( e ) => setError( errText( e ) ) );
	};
	useEffect( load, [] );
	if ( error ) {
		return <ErrorState message={ error } onRetry={ load } />;
	}
	if ( ! s ) {
		return (
			<LoadingState
				label={ __( 'Loading the settings…', 'directoryweb' ) }
			/>
		);
	}
	return (
		<>
			<SectionCard
				id="directoryweb-services"
				title={ __( 'Connected services', 'directoryweb' ) }
				description={ __(
					'What the Directory works with. Keys stay in Kits → Keys and are never shown here.',
					'directoryweb'
				) }
			>
				<ServiceList
					label={ __( 'Connected services', 'directoryweb' ) }
				>
					{ s.services.map( ( v ) =>
						v.file ? (
							<ServiceRow
								key={ v.slug }
								name={ v.name }
								status={ v.status }
								tone={ v.tone }
								summary={ v.summary }
							/>
						) : (
							<ServiceRow
								key={ v.slug }
								name={ v.name }
								status={ v.status }
								tone={ v.tone }
								summary={ v.summary }
								editLabel={
									v.claimForm
										? undefined
										: __( 'Details', 'directoryweb' )
								}
							>
								{ v.claimForm && (
									<ClaimForm
										form={ v.claimForm }
										onSaved={ setS }
									/>
								) }
								<Facts facts={ v.facts } caption={ v.name } />
								{ v.link && (
									<p>
										<a href={ v.link.url }>
											{ v.link.label }
										</a>
									</p>
								) }
								{ ! v.claimForm &&
									! v.link &&
									! ( v.facts || [] ).length && (
										<p>{ v.summary }</p>
									) }
							</ServiceRow>
						)
					) }
				</ServiceList>
			</SectionCard>
			{ s.groups.map( ( g ) => (
				<SettingsGroup
					key={ g.slug }
					id={ `directoryweb-group-${ g.slug }` }
					title={ g.label }
					description={ g.description }
				>
					<SettingsBlock
						note={ __(
							'Read only: these are set in code or with WP-CLI today.',
							'directoryweb'
						) }
					>
						{ g.linked && <LinkedSetting { ...g.linked } /> }
						<Facts facts={ g.facts } caption={ g.label } />
					</SettingsBlock>
				</SettingsGroup>
			) ) }
		</>
	);
}

/* ---------- Listings (read only) ---------- */

const LISTING_VIEWS = [
	'all',
	'publish',
	'draft',
	'claimed',
	'verified',
	'recheck',
];

/** The list's first view, from the address (?view=claimed from the Dashboard's tiles). */
function firstListingView() {
	const v = new URLSearchParams( window.location.search ).get( 'view' );
	return LISTING_VIEWS.includes( v ) ? v : 'all';
}

/**
 * One listing's reading panel, opened in its row.
 *
 * @param {Object} props
 * @param {Object} props.r The row.
 */
function ListingDetail( { r } ) {
	const yes = __( 'Yes', 'directoryweb' );
	return (
		<DetailPanel
			title={ r.title }
			subtitle={ [ r.status, r.types ].filter( Boolean ).join( ' · ' ) }
			items={ [
				{
					label: __( 'Picture', 'directoryweb' ),
					value: r.thumb && (
						<MediaThumbs
							urls={ [ r.thumb ] }
							label={ __( 'Logo', 'directoryweb' ) }
						/>
					),
				},
				{ label: __( 'Place', 'directoryweb' ), value: r.place },
				{ label: __( 'Area', 'directoryweb' ), value: r.area },
				{ label: __( 'Services', 'directoryweb' ), value: r.services },
				{
					label: __( 'Website', 'directoryweb' ),
					value: r.website && (
						<a
							href={ r.website }
							target="_blank"
							rel="noopener noreferrer nofollow"
						>
							{ r.website }
						</a>
					),
				},
				{
					label: __( 'Claimed', 'directoryweb' ),
					value: r.claimed ? yes : '',
				},
				{
					label: __( 'Verified (government record)', 'directoryweb' ),
					value: r.verified,
				},
				{
					label: __( 'Needs a re-check', 'directoryweb' ),
					value: r.recheck ? yes : '',
				},
				{ label: __( 'Added', 'directoryweb' ), value: r.created },
				{
					label: __( 'Last changed', 'directoryweb' ),
					value: r.modified,
				},
			] }
			actions={
				<>
					{ r.editUrl && (
						<a
							className="kits-shell-button is-primary"
							href={ r.editUrl }
						>
							{ __( 'Edit listing', 'directoryweb' ) }
						</a>
					) }
					{ r.viewUrl && (
						<a
							className="kits-shell-button is-secondary"
							href={ r.viewUrl }
						>
							{ __( 'View on the site', 'directoryweb' ) }
						</a>
					) }
				</>
			}
		/>
	);
}

function Listings() {
	const [ q, setQ ] = useState( () => ( {
		view: firstListingView(),
		search: '',
		state: '0',
		type: '',
		sort: 'modified',
		dir: 'desc',
		page: 1,
	} ) );
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ tick, setTick ] = useState( 0 );
	useEffect( () => {
		setLoading( true );
		setError( '' );
		const args = new URLSearchParams(
			Object.entries( q ).filter( ( [ , v ] ) => '' !== v && null !== v )
		).toString();
		apiFetch( { path: `${ path( 'listings' ) }?${ args }` } )
			.then( setData )
			.catch( ( e ) => setError( errText( e ) ) )
			.finally( () => setLoading( false ) );
	}, [ q, tick ] );
	const set = ( k, v ) =>
		setQ( ( o ) => ( { ...o, [ k ]: v, page: 'page' === k ? v : 1 } ) );
	const columns = [
		{
			key: 'title',
			label: __( 'Listing', 'directoryweb' ),
			primary: true,
			sortable: true,
			render: ( r ) => <strong>{ r.title }</strong>,
		},
		{
			key: 'modified',
			label: __( 'Last changed', 'directoryweb' ),
			sortable: true,
			render: ( r ) => r.modified,
		},
		{
			key: 'types',
			label: __( 'Type', 'directoryweb' ),
			render: ( r ) => r.types || '—',
		},
		{
			key: 'place',
			label: __( 'Place', 'directoryweb' ),
			render: ( r ) => r.place || '—',
		},
		{
			key: 'status',
			label: __( 'Status', 'directoryweb' ),
			render: ( r ) =>
				[
					r.status,
					r.claimed && __( 'Claimed', 'directoryweb' ),
					r.verified && __( 'Verified', 'directoryweb' ),
					r.recheck && __( 'Needs re-check', 'directoryweb' ),
				]
					.filter( Boolean )
					.join( ' · ' ),
		},
	];
	return (
		<SectionCard
			id="directoryweb-listings"
			title={ __( 'Listings', 'directoryweb' ) }
			description={ __(
				'The Directory’s listings, to read. View opens a listing’s details; Edit listing opens its own edit screen, where every change is made. Claims are reviewed on Directory → Claims.',
				'directoryweb'
			) }
			actions={
				data?.newUrl && (
					<a
						className="kits-shell-button is-secondary"
						href={ data.newUrl }
					>
						{ __( 'Add New Listing', 'directoryweb' ) }
					</a>
				)
			}
		>
			<DataList
				label={ __( 'Listings', 'directoryweb' ) }
				columns={ columns }
				rows={ data?.rows || [] }
				total={ data?.total || 0 }
				page={ data?.page || 1 }
				pages={ data?.pages || 1 }
				onPage={ ( p ) => set( 'page', p ) }
				search={ q.search }
				onSearch={ ( v ) => set( 'search', v ) }
				searchLabel={ __( 'Search listings', 'directoryweb' ) }
				views={ data?.views || [] }
				view={ q.view }
				onView={ ( v ) => set( 'view', v ) }
				filters={ [
					{
						// The route's "every state" is 0; the list's "none chosen" is ''.
						key: 'state',
						label: __( 'State', 'directoryweb' ),
						value: '0' === String( q.state ) ? '' : q.state,
						options: ( data?.filters?.state || [] ).map( ( o ) =>
							'0' === String( o.value ) ? { ...o, value: '' } : o
						),
					},
					{
						key: 'type',
						label: __( 'Listing type', 'directoryweb' ),
						value: q.type,
						options: data?.filters?.type || [],
					},
				] }
				onFilter={ ( k, v ) =>
					set( k, 'state' === k && '' === v ? '0' : v )
				}
				sort={ { key: q.sort, dir: q.dir } }
				onSort={ ( key, dir ) =>
					setQ( ( o ) => ( { ...o, sort: key, dir, page: 1 } ) )
				}
				rowActions={ ( r ) =>
					r.editUrl
						? [
								{
									label: __( 'Edit', 'directoryweb' ),
									href: r.editUrl,
								},
							]
						: []
				}
				rowView={ ( r ) => <ListingDetail r={ r } /> }
				loading={ loading }
				error={ error }
				onRetry={ () => setTick( ( t ) => t + 1 ) }
				emptyTitle={ __( 'No listing matches.', 'directoryweb' ) }
			/>
		</SectionCard>
	);
}

function App() {
	let screen = <Dashboard />;
	if ( 'claims' === app.screen ) {
		screen = <Claims />;
	} else if ( 'listings' === app.screen ) {
		screen = <Listings />;
	} else if ( 'settings' === app.screen ) {
		screen = <Settings />;
	}
	return <AppShell header={ <Header /> }>{ screen }</AppShell>;
}

const root = document.getElementById( 'directoryweb-app' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
