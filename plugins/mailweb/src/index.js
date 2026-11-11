/**
 * MailWeb's new screens (trial, &ui=next): Dashboard, Inbox and Settings, drawn with the Kits admin shell
 * (@kits/shell is window.kits.shell from Kits Core) and fed by the mailweb/v1 routes. Data and the screen come from
 * window.mailwebApp (includes/mailweb-ui.php).
 *
 * Nothing here sends an email. The Inbox (src/inbox.js) reads messages in place; replying and writing a new one
 * stay on the current compose screen.
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
import Inbox from './inbox';

const app = window.mailwebApp || {};
const path = ( p ) => `/${ app.rest }/${ p }`;
const errText = ( e ) => ( e && e.message ) || String( e );

/**
 * A plain browser confirm before an action that changes sending.
 *
 * @param {string} text The question.
 */
const sure = ( text ) => window.confirm( text ); // eslint-disable-line no-alert -- a plain confirm, as core's list tables use

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

const RANGES = [ 7, 30, 90 ];

/**
 * The site date `days` before `today`.
 *
 * @param {string} today Site date (Y-m-d).
 * @param {number} days  Days back.
 */
function dayBefore( today, days ) {
	const at = new Date( `${ today }T12:00:00Z` ); // UTC: the browser's timezone must not move the day
	at.setUTCDate( at.getUTCDate() - days );
	return at.toISOString().slice( 0, 10 );
}

/**
 * The log row to compare with: the newest day on or before `days` ago.
 *
 * @param {Object} log   { 'Y-m-d': { metric: n } }.
 * @param {string} today Site date today.
 * @param {number} days  Range.
 * @return {Object|null} The row.
 */
function rowBefore( log, today, days ) {
	const limit = dayBefore( today, days );
	const dates = Object.keys( log || {} )
		.filter( ( d ) => d <= limit )
		.sort();
	const d = dates[ dates.length - 1 ];
	return d ? log[ d ] : null;
}

/**
 * One series of the last `days` days, a bar a day (0 where nothing was sent).
 *
 * @param {Object} map   { 'Y-m-d': n }.
 * @param {string} today Site date today.
 * @param {number} days  Range.
 */
function series( map, today, days ) {
	const out = [];
	for ( let i = days - 1; i >= 0; i-- ) {
		const d = dayBefore( today, i );
		out.push( { label: d, value: ( map || {} )[ d ] || 0 } );
	}
	return out;
}

function Dashboard() {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ days, setDays ] = useState( 30 );
	const [ note, setNote ] = useState( null );
	const [ busy, setBusy ] = useState( '' );
	const [ tries, setTries ] = useState( 0 );

	const load = () => {
		setError( '' );
		apiFetch( { path: path( 'dashboard' ) } )
			.then( setData )
			.catch( ( e ) => setError( errText( e ) ) );
	};
	useEffect( load, [] );
	// While the first count runs in the background, look again every 20 seconds (a read; nothing is counted).
	useEffect( () => {
		if ( ! data || ! data.counting || tries >= 15 ) {
			return;
		}
		const t = setTimeout( () => {
			setTries( tries + 1 );
			load();
		}, 20000 );
		return () => clearTimeout( t );
	}, [ data, tries ] );

	const act = ( route, question ) => {
		if ( question && ! sure( question ) ) {
			return;
		}
		setBusy( route );
		apiFetch( { path: path( route ), method: 'POST' } )
			.then( ( r ) => {
				setNote( {
					tone: 'success',
					text:
						r.message ||
						__(
							'A count is running in the background. The numbers change when it is done (about a minute).',
							'mailweb'
						),
				} );
				setTries( 0 );
				setTimeout( load, 'dashboard/count' === route ? 20000 : 0 );
			} )
			.catch( ( e ) => setNote( { tone: 'error', text: errText( e ) } ) )
			.finally( () => setBusy( '' ) );
	};

	if ( error ) {
		return <ErrorState message={ error } onRetry={ load } />;
	}
	if ( ! data ) {
		return (
			<LoadingState label={ __( 'Loading the counts…', 'mailweb' ) } />
		);
	}
	const before = rowBefore( data.log, data.today, days );
	const attention = data.tiles.filter( ( t ) => t.tone && ! t.hide );
	const delta = ( t ) => {
		if ( ! before || ! t.metric || undefined === t.raw ) {
			return '';
		}
		const old = before[ t.metric ];
		if ( undefined === old ) {
			return '';
		}
		const d = t.raw - old;
		return sprintf(
			/* translators: 1: change (+3 or -2), 2: number of days. */
			__( '%1$s in %2$d days', 'mailweb' ),
			( d > 0 ? '+' : '' ) + d.toLocaleString(),
			days
		);
	};
	let after = data.atText
		? sprintf(
				/* translators: %s: date and time. */
				__( 'Counted %s.', 'mailweb' ),
				data.atText
			)
		: __( 'Counting for the first time…', 'mailweb' );
	if ( data.atText && ! before ) {
		after +=
			' ' +
			sprintf(
				/* translators: %d: number of days. */
				__(
					'No count from %d days ago yet, so no change is shown.',
					'mailweb'
				),
				days
			);
	}
	const status = data.status;

	return (
		<>
			<ChipRow
				label={ __( 'Compare with', 'mailweb' ) }
				value={ days }
				onChange={ setDays }
				chips={ RANGES.map( ( n ) => ( {
					value: n,
					/* translators: %d: number of days. */
					label: sprintf( __( 'Last %d days', 'mailweb' ), n ),
				} ) ) }
				after={
					<>
						{ after }{ ' ' }
						<Button
							variant="link"
							busy={ 'dashboard/count' === busy }
							onClick={ () => act( 'dashboard/count' ) }
						>
							{ __( 'Count now', 'mailweb' ) }
						</Button>
					</>
				}
			/>
			{ note && (
				<Notice tone={ note.tone } onDismiss={ () => setNote( null ) }>
					{ note.text }
				</Notice>
			) }
			{ status.paused && (
				<Notice tone="error">
					<strong>
						{ __( 'Email sending is paused.', 'mailweb' ) }
					</strong>{ ' ' }
					{ sprintf(
						/* translators: 1: date and time, 2: the mail server's refusal. */
						__(
							'The mail server refused an email on %1$s (%2$s). Emails wait in the queue, not lost.',
							'mailweb'
						),
						status.paused.at,
						status.paused.error
					) }
				</Notice>
			) }
			{ attention.length > 0 && ! data.counting && (
				<Notice tone="warning">
					<strong>{ __( 'Needs attention', 'mailweb' ) }</strong>
					<ul>
						{ attention.map( ( t ) => (
							<li key={ t.key }>
								{ t.label }: { t.value }. { t.sub }
							</li>
						) ) }
					</ul>
				</Notice>
			) }
			<TileGrid label={ __( 'MailWeb at a glance', 'mailweb' ) }>
				{ data.tiles.map( ( t ) => (
					<Tile
						key={ t.key }
						label={ t.label }
						value={ t.value }
						delta={ delta( t ) }
						sub={ t.sub }
						href={ t.href }
						tone={ t.tone }
						loading={ data.counting && !! t.metric }
						missing={ t.hide ? t.label : undefined }
						hide={ t.hide }
					/>
				) ) }
			</TileGrid>
			{ ! data.counting && (
				<>
					<Mailboxes data={ data } />
					<Queue data={ data } />
					<SentFailed
						data={ data }
						days={ Math.min( days, 90 ) }
						busy={ busy }
						act={ act }
					/>
				</>
			) }
			<SendingStatus data={ data } busy={ busy } act={ act } />
			<SectionCard
				id="help"
				title={ __( 'Help', 'mailweb' ) }
				description={ __(
					'What MailWeb reads and where it is set up.',
					'mailweb'
				) }
			>
				<RefTable
					caption={ __( 'Help', 'mailweb' ) }
					head={ [
						__( 'What', 'mailweb' ),
						__( 'Where', 'mailweb' ),
					] }
					rows={ [
						[
							__( 'Webhook address', 'mailweb' ),
							<code key="w">{ data.help.webhook }</code>,
						],
						[
							__(
								'Note all Inbox and Archive mail in FluentCRM (WP-CLI on the server)',
								'mailweb'
							),
							<code key="c">{ data.help.cli }</code>,
						],
						[
							__( 'Read in step with', 'mailweb' ),
							<a
								key="m"
								href={ app.webmail }
								target="_blank"
								rel="noopener noreferrer"
							>
								{ __( 'Hostinger webmail', 'mailweb' ) }
							</a>,
						],
					] }
				/>
			</SectionCard>
		</>
	);
}

function Mailboxes( { data } ) {
	return (
		<SectionCard
			id="mailboxes"
			title={ __( 'Mailboxes', 'mailweb' ) }
			description={
				data.lastIn
					? sprintf(
							/* translators: %s: date, time and mailbox of the last webhook delivery. */
							__( 'Last mail in: %s.', 'mailweb' ),
							data.lastIn
						)
					: __(
							'No mail has come in through the webhook yet.',
							'mailweb'
						)
			}
		>
			{ 0 === data.boxes.length ? (
				<EmptyState
					title={ __(
						'No mailboxes yet. An administrator adds the Mail API token in Settings.',
						'mailweb'
					) }
				/>
			) : (
				data.boxes.map( ( b ) => (
					<SettingsBlock
						key={ b.key }
						title={ b.address }
						description={ [
							b.aliases.length
								? sprintf(
										/* translators: %s: alias addresses. */
										__( 'Also %s.', 'mailweb' ),
										b.aliases.join( ', ' )
									)
								: '',
							null === b.unread
								? ''
								: sprintf(
										/* translators: %s: number of unread emails. */
										__( '%s unread.', 'mailweb' ),
										b.unread.toLocaleString()
									),
							sprintf(
								/* translators: %s: OK, Empty, Not reachable or "Shown from 4:12 pm". */
								__( 'Status: %s.', 'mailweb' ),
								b.status
							),
						]
							.filter( Boolean )
							.join( ' ' ) }
					>
						{ b.newest.length > 0 && (
							<RefTable
								caption={ b.address }
								head={ [
									__( 'From', 'mailweb' ),
									__( 'Subject', 'mailweb' ),
									__( 'Date', 'mailweb' ),
								] }
								rows={ b.newest.map( ( m ) => [
									m.unseen ? (
										<strong key="f">
											{ m.from }{ ' ' }
											<span className="screen-reader-text">
												{ __( '(unread)', 'mailweb' ) }
											</span>
										</strong>
									) : (
										m.from
									),
									m.subject,
									m.date,
								] ) }
							/>
						) }
						<p>
							<a href={ b.url }>
								{ __( 'Open in Inbox', 'mailweb' ) }
							</a>
							{ ' · ' }
							<a
								href={ app.webmail }
								target="_blank"
								rel="noopener noreferrer"
							>
								{ __( 'Open webmail', 'mailweb' ) }
							</a>
						</p>
					</SettingsBlock>
				) )
			) }
		</SectionCard>
	);
}

function Queue( { data } ) {
	const q = data.queue;
	return (
		<SectionCard
			id="queue"
			title={ __( 'Queue', 'mailweb' ) }
			description={ __(
				'Emails over the daily limit wait here and go out as the limit frees, every 5 minutes.',
				'mailweb'
			) }
		>
			<RefTable
				caption={ __( 'Waiting by kind', 'mailweb' ) }
				head={ [
					__( 'Waiting', 'mailweb' ),
					__( 'Emails', 'mailweb' ),
				] }
				rows={ [
					...q.rows,
					[
						__( 'Oldest waiting since', 'mailweb' ),
						q.oldest || '–',
					],
					[
						__( 'Room left today for notices', 'mailweb' ),
						q.room1.toLocaleString(),
					],
					[
						__( 'Room left today for campaigns', 'mailweb' ),
						q.room3.toLocaleString(),
					],
				] }
			/>
			{ app.admin && app.oldSending && (
				<p className="kits-shell-field__help">
					{ __(
						'To send the queue now, use the current Site sending screen.',
						'mailweb'
					) }{ ' ' }
					<a href={ app.oldSending }>
						{ __( 'Open Site sending', 'mailweb' ) }
					</a>
				</p>
			) }
		</SectionCard>
	);
}

function SentFailed( { data, days, busy, act } ) {
	const s = data.sent;
	return (
		<SectionCard
			id="sent"
			title={ __( 'Sent and failed', 'mailweb' ) }
			description={ s.allTime }
			actions={
				data.admin && s.heldN > 0 ? (
					<Button
						busy={ 'queue/retry' === busy }
						onClick={ () =>
							act(
								'queue/retry',
								__(
									'Put the held emails back in line? They are sent on the next runs, within the daily limit.',
									'mailweb'
								)
							)
						}
					>
						{ __( 'Retry held emails', 'mailweb' ) }
					</Button>
				) : null
			}
		>
			<MiniChart
				label={ sprintf(
					/* translators: %d: number of days. */
					__( 'Emails sent per day, last %d days', 'mailweb' ),
					days
				) }
				points={ series( data.days, data.today, days ) }
				unit={ __( 'emails', 'mailweb' ) }
			/>
			<RefTable
				caption={ __( 'Sent and failed', 'mailweb' ) }
				head={ [ __( 'What', 'mailweb' ), __( 'Emails', 'mailweb' ) ] }
				rows={ [
					[ __( 'Sent today', 'mailweb' ), s.today.toLocaleString() ],
					[
						__( 'Failed, will be tried again', 'mailweb' ),
						s.failed.toLocaleString() +
							( s.nextTry
								? ' · ' +
									sprintf(
										/* translators: %s: date and time. */
										__( 'next try %s', 'mailweb' ),
										s.nextTry
									)
								: '' ),
					],
					[
						__( 'Held after 4 failed tries', 'mailweb' ),
						s.heldN.toLocaleString(),
					],
				] }
			/>
			{ s.held.length > 0 && (
				<RefTable
					caption={ __( 'Held emails', 'mailweb' ) }
					head={ [
						__( 'Queued', 'mailweb' ),
						__( 'Kind', 'mailweb' ),
						__( 'Tries', 'mailweb' ),
						__( 'Error', 'mailweb' ),
					] }
					rows={ s.held }
				/>
			) }
		</SectionCard>
	);
}

function SendingStatus( { data, busy, act } ) {
	const st = data.status;
	const passText = {
		set: __( 'set', 'mailweb' ),
		unset: __( 'not set', 'mailweb' ),
		config: __( 'set in wp-config.php', 'mailweb' ),
	};
	return (
		<SectionCard
			id="status"
			title={ __( 'Sending status', 'mailweb' ) }
			actions={
				data.admin && st.paused ? (
					<Button
						variant="primary"
						busy={ 'sending/resume' === busy }
						onClick={ () =>
							act(
								'sending/resume',
								__(
									'Resume sending? Only do this once the mailbox works again: waiting emails then go out within the daily limit.',
									'mailweb'
								)
							)
						}
					>
						{ __( 'Resume sending', 'mailweb' ) }
					</Button>
				) : null
			}
		>
			<RefTable
				caption={ __( 'Sending status', 'mailweb' ) }
				head={ [ __( 'What', 'mailweb' ), __( 'Now', 'mailweb' ) ] }
				rows={ [
					[
						__( 'Sending', 'mailweb' ),
						st.paused
							? sprintf(
									/* translators: 1: date and time, 2: the mail server's refusal. */
									__( 'Paused since %1$s: %2$s', 'mailweb' ),
									st.paused.at,
									st.paused.error
								)
							: __( 'Running', 'mailweb' ),
					],
					[ __( 'Site mailbox', 'mailweb' ), st.smtp.user ],
					[
						__( 'Server', 'mailweb' ),
						`${ st.smtp.host }:${ st.smtp.port }`,
					],
					[
						__( 'Password', 'mailweb' ),
						passText[ st.smtp.pass ] || st.smtp.pass,
					],
					[ __( 'Order', 'mailweb' ), st.rule ],
					[ __( 'FluentCRM', 'mailweb' ), st.pacing ],
				] }
			/>
		</SectionCard>
	);
}

/* ---------- Settings ---------- */

const SERVICES = 'services';
const STATE = () => ( {
	set: __( 'Set. Leave empty to keep it.', 'mailweb' ),
	unset: __( 'Not set.', 'mailweb' ),
	config: __( 'Set in wp-config.php: change it there.', 'mailweb' ),
} );

/**
 * The fields whose value differs from what the route sent.
 *
 * @param {Object} now   Values on the screen.
 * @param {Object} saved Values from the route.
 */
function changed( now, saved ) {
	return Object.fromEntries(
		Object.entries( now ).filter(
			( [ k, v ] ) => JSON.stringify( v ) !== JSON.stringify( saved[ k ] )
		)
	);
}

/**
 * A group's Save button, message line and the call.
 *
 * @param {string}   route   Route.
 * @param {Function} onSaved Called with the route's answer.
 */
function useSave( route, onSaved ) {
	const [ busy, setBusy ] = useState( false );
	const [ msg, setMsg ] = useState( null );
	const save = ( body ) => {
		setBusy( true );
		setMsg( null );
		return apiFetch( { path: path( route ), method: 'POST', data: body } )
			.then( ( r ) => {
				onSaved( r );
				setMsg( { tone: 'success', text: r.message } );
			} )
			.catch( ( e ) => setMsg( { tone: 'error', text: errText( e ) } ) )
			.finally( () => setBusy( false ) );
	};
	return { busy, msg, setMsg, save };
}

function Foot( { busy, dirty, onSave, msg } ) {
	return (
		<>
			<Button
				variant="primary"
				busy={ busy }
				disabled={ ! dirty }
				onClick={ onSave }
			>
				{ __( 'Save changes', 'mailweb' ) }
			</Button>
			<span aria-live="polite">
				{ msg && 'success' === msg.tone ? msg.text : '' }
			</span>
		</>
	);
}

function ErrorLine( { msg, onDismiss } ) {
	return msg && 'error' === msg.tone ? (
		<Notice tone="error" onDismiss={ onDismiss }>
			{ msg.text }
		</Notice>
	) : null;
}

function SendingGroup( { group, onSaved, onDirty } ) {
	const [ v, setV ] = useState( group.values );
	useEffect( () => setV( group.values ), [ group.values ] );
	const diff = changed( v, group.values );
	const dirty = Object.keys( diff ).length > 0;
	useEffect(
		() => onDirty( group.slug, dirty ),
		[ group.slug, dirty, onDirty ]
	);
	const s = useSave( `settings/${ group.slug }`, ( r ) =>
		onSaved( r.group )
	);
	return (
		<SettingsGroup
			id={ `mailweb-group-${ group.slug }` }
			title={ group.label }
			description={ group.note }
			footer={
				<Foot
					busy={ s.busy }
					dirty={ dirty }
					msg={ s.msg }
					onSave={ () => s.save( diff ) }
				/>
			}
		>
			<ErrorLine msg={ s.msg } onDismiss={ () => s.setMsg( null ) } />
			{ group.fields.map( ( f ) => (
				<Field
					key={ f.key }
					label={ f.label }
					help={ f.help }
					changed={ v[ f.key ] !== f.default }
					onReset={ () => setV( { ...v, [ f.key ]: f.default } ) }
					render={ ( id, helpId ) => (
						<input
							id={ id }
							type="number"
							min={ f.min }
							max={ f.max }
							value={ v[ f.key ] }
							aria-describedby={ helpId }
							onChange={ ( e ) =>
								setV( {
									...v,
									[ f.key ]:
										parseInt( e.target.value, 10 ) || 0,
								} )
							}
						/>
					) }
				/>
			) ) }
		</SettingsGroup>
	);
}

function mailboxValues( group ) {
	return {
		send: Object.fromEntries(
			group.boxes.map( ( b ) => [ b.key, b.send ] )
		),
		default_from: group.defaultFrom,
		aliases: group.aliases,
	};
}

function MailboxesGroup( { group, onSaved, onDirty } ) {
	const start = mailboxValues( group );
	const [ v, setV ] = useState( start );
	useEffect( () => setV( mailboxValues( group ) ), [ group ] );
	const diff = changed( v, start );
	const dirty = Object.keys( diff ).length > 0;
	useEffect(
		() => onDirty( group.slug, dirty ),
		[ group.slug, dirty, onDirty ]
	);
	const s = useSave( `settings/${ group.slug }`, ( r ) =>
		onSaved( r.group )
	);
	const replyFrom = group.boxes.find( ( b ) => b.key === v.default_from );
	return (
		<SettingsGroup
			id={ `mailweb-group-${ group.slug }` }
			title={ group.label }
			description={ __(
				'The mailboxes come from the Mail API token. Replies and new messages go only from mailboxes ticked “Send from it”.',
				'mailweb'
			) }
			footer={
				<Foot
					busy={ s.busy }
					dirty={ dirty }
					msg={ s.msg }
					onSave={ () => s.save( diff ) }
				/>
			}
		>
			<ErrorLine msg={ s.msg } onDismiss={ () => s.setMsg( null ) } />
			{ 0 === group.boxes.length && (
				<EmptyState
					title={ __(
						'No mailboxes yet: add the Mail API token under Connected services.',
						'mailweb'
					) }
				/>
			) }
			{ group.boxes.map( ( b ) => (
				<SettingsBlock
					key={ b.key }
					title={ b.address }
					description={
						b.aliases.length
							? sprintf(
									/* translators: %s: alias addresses. */
									__( 'Aliases: %s', 'mailweb' ),
									b.aliases.join( ', ' )
								)
							: ''
					}
				>
					<Switch
						label={ __( 'Send from it', 'mailweb' ) }
						checked={ !! v.send[ b.key ] }
						onChange={ ( on ) =>
							setV( { ...v, send: { ...v.send, [ b.key ]: on } } )
						}
					/>
					<label htmlFor={ `mailweb-from-${ b.key }` }>
						<input
							id={ `mailweb-from-${ b.key }` }
							type="radio"
							name="mailweb-default-from"
							checked={ v.default_from === b.key }
							onChange={ () =>
								setV( { ...v, default_from: b.key } )
							}
						/>{ ' ' }
						{ __( 'Default From', 'mailweb' ) }
					</label>
				</SettingsBlock>
			) ) }
			{ replyFrom && (
				<p className="kits-shell-field__help">
					{ sprintf(
						/* translators: %s: mailbox address. */
						__( 'Replies go from %s.', 'mailweb' ),
						replyFrom.address
					) }
				</p>
			) }
			<Field
				label={ __( 'Aliases', 'mailweb' ) }
				help={ __(
					'One a line: alias = mailbox (as in hPanel → Emails → Email Alias). The Mail API does not list aliases, so keep this in step with hPanel.',
					'mailweb'
				) }
				render={ ( id, helpId ) => (
					<textarea
						id={ id }
						rows={ 4 }
						spellCheck="false"
						value={ v.aliases }
						aria-describedby={ helpId }
						onChange={ ( e ) =>
							setV( { ...v, aliases: e.target.value } )
						}
					/>
				) }
			/>
		</SettingsGroup>
	);
}

function profileValues( group ) {
	return Object.fromEntries(
		group.profiles.map( ( p ) => [
			p.key,
			{ name: p.name, signature: p.signature, picture: p.picture },
		] )
	);
}

function ProfilesGroup( { group, onSaved, onDirty } ) {
	const start = profileValues( group );
	const [ v, setV ] = useState( start );
	useEffect( () => setV( profileValues( group ) ), [ group ] );
	const diff = changed( v, start );
	const dirty = Object.keys( diff ).length > 0;
	useEffect(
		() => onDirty( group.slug, dirty ),
		[ group.slug, dirty, onDirty ]
	);
	const s = useSave( `settings/${ group.slug }`, ( r ) =>
		onSaved( r.group )
	);
	const set = ( key, field, value ) =>
		setV( { ...v, [ key ]: { ...v[ key ], [ field ]: value } } );
	return (
		<SettingsGroup
			id={ `mailweb-group-${ group.slug }` }
			title={ group.label }
			description={ __(
				'Each address’s display name, signature and picture, as set in Hostinger webmail. Site emails sent from that address use the name and end with the signature (FluentCRM emails excepted).',
				'mailweb'
			) }
			footer={
				<Foot
					busy={ s.busy }
					dirty={ dirty }
					msg={ s.msg }
					onSave={ () => s.save( { profiles: diff } ) }
				/>
			}
		>
			<ErrorLine msg={ s.msg } onDismiss={ () => s.setMsg( null ) } />
			{ 0 === group.profiles.length && (
				<EmptyState
					title={ __(
						'No addresses yet: add the Mail API token under Connected services.',
						'mailweb'
					) }
				/>
			) }
			{ group.profiles.map( ( p ) => (
				<SettingsBlock key={ p.key } title={ p.address }>
					<Field
						label={ __( 'Display name', 'mailweb' ) }
						render={ ( id ) => (
							<input
								id={ id }
								type="text"
								value={ v[ p.key ]?.name ?? '' }
								onChange={ ( e ) =>
									set( p.key, 'name', e.target.value )
								}
							/>
						) }
					/>
					<Field
						label={ __( 'Signature (HTML)', 'mailweb' ) }
						help={ __(
							'Links, bold and line breaks are kept; scripts are taken out when it is saved.',
							'mailweb'
						) }
						render={ ( id, helpId ) => (
							<textarea
								id={ id }
								rows={ 5 }
								spellCheck="false"
								value={ v[ p.key ]?.signature ?? '' }
								aria-describedby={ helpId }
								onChange={ ( e ) =>
									set( p.key, 'signature', e.target.value )
								}
							/>
						) }
					/>
					<Field
						label={ __( 'Picture', 'mailweb' ) }
						help={ __(
							'The media library item’s ID (0 = none). The current screen has a media picker.',
							'mailweb'
						) }
						render={ ( id, helpId ) => (
							<input
								id={ id }
								type="number"
								min="0"
								value={ v[ p.key ]?.picture ?? 0 }
								aria-describedby={ helpId }
								onChange={ ( e ) =>
									set(
										p.key,
										'picture',
										parseInt( e.target.value, 10 ) || 0
									)
								}
							/>
						) }
					/>
					{ p.pictureUrl && (
						<img
							src={ p.pictureUrl }
							alt=""
							width="48"
							height="48"
						/>
					) }
				</SettingsBlock>
			) ) }
		</SettingsGroup>
	);
}

/**
 * A key or secret: write-only. The screen shows only whether one is set.
 *
 * @param {Object}   props
 * @param {Object}   props.s       Service from the route.
 * @param {Function} props.onSaved New service list.
 */
function SecretForm( { s, onSaved } ) {
	const [ value, setValue ] = useState( '' );
	const save = useSave( s.route, ( r ) => {
		setValue( '' );
		onSaved( r.services );
	} );
	const fixed = 'config' === s.field.state;
	return (
		<>
			<ErrorLine
				msg={ save.msg }
				onDismiss={ () => save.setMsg( null ) }
			/>
			<Field
				label={ s.field.label }
				help={ `${ STATE()[ s.field.state ] } ${ s.field.help }` }
				render={ ( id, helpId ) => (
					<input
						id={ id }
						type="password"
						autoComplete="new-password"
						value={ value }
						disabled={ fixed }
						aria-describedby={ helpId }
						onChange={ ( e ) => setValue( e.target.value ) }
					/>
				) }
			/>
			{ s.rows && s.rows.length > 0 && (
				<RefTable
					caption={ s.name }
					head={ [
						__( 'What', 'mailweb' ),
						__( 'Value', 'mailweb' ),
					] }
					rows={ s.rows }
				/>
			) }
			{ ! fixed && (
				<Foot
					busy={ save.busy }
					dirty={ '' !== value.trim() }
					msg={ save.msg }
					onSave={ () =>
						save.save( { [ s.field.key ]: value.trim() } )
					}
				/>
			) }
		</>
	);
}

function SmtpForm( { s, onSaved } ) {
	const [ v, setV ] = useState( s.values );
	const [ pass, setPass ] = useState( '' );
	useEffect( () => setV( s.values ), [ s.values ] );
	const diff = changed( v, s.values );
	const save = useSave( s.route, ( r ) => {
		setPass( '' );
		onSaved( r.services );
	} );
	const fields = [
		[ 'user', __( 'Mailbox (email address)', 'mailweb' ), 'email' ],
		[ 'name', __( 'Sender name', 'mailweb' ), 'text' ],
		[ 'host', __( 'SMTP server', 'mailweb' ), 'text' ],
		[ 'port', __( 'Port', 'mailweb' ), 'number' ],
	];
	return (
		<>
			<ErrorLine
				msg={ save.msg }
				onDismiss={ () => save.setMsg( null ) }
			/>
			{ fields.map( ( [ k, label, type ] ) => (
				<Field
					key={ k }
					label={ label }
					render={ ( id ) => (
						<input
							id={ id }
							type={ type }
							value={ v[ k ] }
							autoComplete="off"
							onChange={ ( e ) =>
								setV( { ...v, [ k ]: e.target.value } )
							}
						/>
					) }
				/>
			) ) }
			<Field
				label={ __( 'Mailbox password', 'mailweb' ) }
				help={ STATE()[ s.pass ] }
				render={ ( id, helpId ) => (
					<input
						id={ id }
						type="password"
						autoComplete="new-password"
						value={ pass }
						disabled={ 'config' === s.pass }
						aria-describedby={ helpId }
						onChange={ ( e ) => setPass( e.target.value ) }
					/>
				) }
			/>
			<Foot
				busy={ save.busy }
				dirty={ Object.keys( diff ).length > 0 || '' !== pass }
				msg={ save.msg }
				onSave={ () =>
					save.save( { ...diff, ...( pass ? { pass } : {} ) } )
				}
			/>
			{ app.oldSending && (
				<p className="kits-shell-field__help">
					{ __(
						'A test email is sent from the current Site sending screen.',
						'mailweb'
					) }{ ' ' }
					<a href={ app.oldSending }>
						{ __( 'Open Site sending', 'mailweb' ) }
					</a>
				</p>
			) }
		</>
	);
}

function CrmForm( { s, onSaved } ) {
	const [ v, setV ] = useState( s.values );
	useEffect( () => setV( s.values ), [ s.values ] );
	const diff = changed( v, s.values );
	const save = useSave( s.route, ( r ) => onSaved( r.services ) );
	const tick = ( key, on ) =>
		setV( {
			...v,
			boxes: on
				? [ ...v.boxes, key ]
				: v.boxes.filter( ( k ) => k !== key ),
		} );
	return (
		<>
			<ErrorLine
				msg={ save.msg }
				onDismiss={ () => save.setMsg( null ) }
			/>
			<Switch
				label={ __( 'Note emails on FluentCRM contacts', 'mailweb' ) }
				help={ __(
					'New contacts are made as “transactional”: never added to a list or sent marketing.',
					'mailweb'
				) }
				checked={ v.on }
				onChange={ ( on ) => setV( { ...v, on } ) }
			/>
			<Field
				label={ __( 'Tag', 'mailweb' ) }
				help={ __(
					'Added to people who email you (empty = no tag).',
					'mailweb'
				) }
				render={ ( id, helpId ) => (
					<input
						id={ id }
						type="text"
						value={ v.tag }
						aria-describedby={ helpId }
						onChange={ ( e ) =>
							setV( { ...v, tag: e.target.value } )
						}
					/>
				) }
			/>
			<fieldset className="kits-shell-checks">
				<legend className="kits-shell-field__label">
					{ __( 'Mailboxes', 'mailweb' ) }
				</legend>
				{ s.boxes.map( ( b ) => (
					<label key={ b.key } htmlFor={ `mailweb-crm-${ b.key }` }>
						<input
							id={ `mailweb-crm-${ b.key }` }
							type="checkbox"
							checked={ v.boxes.includes( b.key ) }
							onChange={ ( e ) =>
								tick( b.key, e.target.checked )
							}
						/>{ ' ' }
						{ b.address }
					</label>
				) ) }
			</fieldset>
			<Foot
				busy={ save.busy }
				dirty={ Object.keys( diff ).length > 0 }
				msg={ save.msg }
				onSave={ () => save.save( diff ) }
			/>
		</>
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
	const form = ( s ) => {
		if ( 'secret' === s.kind ) {
			return <SecretForm s={ s } onSaved={ setList } />;
		}
		if ( 'smtp' === s.kind ) {
			return <SmtpForm s={ s } onSaved={ setList } />;
		}
		if ( 'fluentcrm' === s.kind && s.active ) {
			return <CrmForm s={ s } onSaved={ setList } />;
		}
		return null;
	};
	return (
		<SectionCard
			id={ `mailweb-group-${ SERVICES }` }
			title={ __( 'Connected services', 'mailweb' ) }
			description={ __(
				'What MailWeb works with. Keys and passwords are never shown again once saved.',
				'mailweb'
			) }
		>
			<ServiceList label={ __( 'Connected services', 'mailweb' ) }>
				{ list.map( ( s ) => (
					<ServiceRow
						key={ s.slug }
						name={ s.name }
						status={ s.status }
						tone={ s.tone }
						summary={ s.summary }
					>
						{ form( s ) }
					</ServiceRow>
				) ) }
			</ServiceList>
		</SectionCard>
	);
}

const GROUPS = {
	sending: SendingGroup,
	mailboxes: MailboxesGroup,
	profiles: ProfilesGroup,
};

function hashGroup( groups ) {
	const h = window.location.hash.replace( '#', '' );
	return groups.some( ( g ) => g.slug === h ) ? h : SERVICES;
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
			.catch( ( e ) => setError( errText( e ) ) );
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
			<LoadingState label={ __( 'Loading the settings…', 'mailweb' ) } />
		);
	}
	const chips = [
		{
			value: SERVICES,
			label: __( 'Connected services', 'mailweb' ),
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
				label={ __( 'Settings groups', 'mailweb' ) }
				chips={ chips }
				value={ open }
				onChange={ setOpen }
			/>
			{ SERVICES === open && <Services /> }
			{ groups.map( ( g ) => {
				const Group = GROUPS[ g.slug ];
				return Group ? (
					<div key={ g.slug } hidden={ g.slug !== open }>
						<Group
							group={ g }
							onSaved={ replaceGroup }
							onDirty={ markDirty }
						/>
					</div>
				) : null;
			} ) }
		</>
	);
}

function App() {
	let screen = <Dashboard />;
	if ( 'inbox' === app.screen ) {
		screen = <Inbox />;
	} else if ( 'settings' === app.screen ) {
		screen = <Settings />;
	}
	return <AppShell header={ <Header /> }>{ screen }</AppShell>;
}

const root = document.getElementById( 'mailweb-app' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
