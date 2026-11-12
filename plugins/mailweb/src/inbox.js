/**
 * MailWeb's Inbox on the new screens, laid out like an email app: folders on the left, the message list in the middle
 * and the open message on the right (docs/mailweb-plan.md §3.3). Below 1200px one pane shows at a time, with Back.
 *
 * Reading uses GET mailweb/v1/inbox/message (opening marks a message read, as webmail does); moving, marking and
 * trashing use POST mailweb/v1/inbox/bulk. Reply, Reply all, Forward and New message open today's compose screen.
 * Nothing here sends an email.
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Button,
	EmptyState,
	ErrorState,
	LoadingState,
	Notice,
} from '@kits/shell'; // eslint-disable-line import/no-unresolved -- Kits Core's shell script (window.kits.shell), mapped in webpack.config.js
import './inbox.css';

const app = window.mailwebApp || {};
const path = ( p ) => `/${ app.rest }/${ p }`;
const errText = ( e ) => ( e && e.message ) || String( e );

/** The Inbox's place from the address, so a reload or a link opens the same view and message. */
function startState() {
	const q = new URLSearchParams( window.location.search );
	return {
		box: q.get( 'box' ) || 'all',
		folder: q.get( 'folder' ) || 'inbox',
		search: q.get( 's' ) || '',
		page: Math.max( 1, parseInt( q.get( 'paged' ) || '1', 10 ) || 1 ),
	};
}

/**
 * The message route's arguments for a row id (`box|uid`, or `form|id` in the forms folder).
 *
 * @param {string} id     Row id.
 * @param {string} folder Folder.
 */
function messageArgs( id, folder ) {
	const [ box, n ] = String( id ).split( '|' );
	return 'form' === box ? { folder, item: n } : { folder, box, uid: n };
}

function Paperclip() {
	return (
		<svg
			className="mailweb-mail__clip"
			viewBox="0 0 24 24"
			width="16"
			height="16"
			aria-hidden="true"
			focusable="false"
		>
			<path
				d="M8 12.5V7a4 4 0 0 1 8 0v9a2.5 2.5 0 0 1-5 0V8"
				fill="none"
				stroke="currentColor"
				strokeWidth="1.8"
				strokeLinecap="round"
			/>
		</svg>
	);
}

/**
 * Folders and mailboxes: the left pane.
 *
 * @param {Object}   props
 * @param {Object}   props.data The list payload (views, boxes).
 * @param {Object}   props.st   The list's place.
 * @param {Function} props.go   Change place.
 */
function Folders( { data, st, go } ) {
	return (
		<nav
			className="mailweb-mail__folders"
			aria-label={ __( 'Folders', 'mailweb' ) }
		>
			<a
				className="kits-shell-button is-primary mailweb-mail__new"
				href={ app.composeUrl }
			>
				{ __( 'New message', 'mailweb' ) }
			</a>
			{ ( data?.boxes || [] ).length > 1 && (
				<div className="mailweb-mail__box">
					<label className="screen-reader-text" htmlFor="mailweb-box">
						{ __( 'Mailbox', 'mailweb' ) }
					</label>
					<select
						id="mailweb-box"
						value={ st.box }
						onChange={ ( e ) => go( { box: e.target.value } ) }
					>
						{ data.boxes.map( ( b ) => (
							<option key={ b.value } value={ b.value }>
								{ b.label }
							</option>
						) ) }
					</select>
				</div>
			) }
			<ul className="mailweb-mail__views">
				{ ( data?.views || [] ).map( ( v ) => (
					<li key={ v.value }>
						<button
							type="button"
							className="mailweb-mail__view"
							aria-current={
								v.value === st.folder ? 'page' : undefined
							}
							onClick={ () =>
								go( { folder: v.value, search: '' } )
							}
						>
							<span>{ v.label }</span>
							{ v.count > 0 && (
								<span className="mailweb-mail__count">
									{ v.count }
									<span className="screen-reader-text">
										{ ' ' }
										{ __( 'unread', 'mailweb' ) }
									</span>
								</span>
							) }
						</button>
					</li>
				) ) }
			</ul>
			<p className="mailweb-mail__webmail">
				{ __(
					'In step with Hostinger webmail: what you read, move or send here shows there too.',
					'mailweb'
				) }
			</p>
		</nav>
	);
}

/**
 * The open message: the right pane.
 *
 * @param {Object}   props
 * @param {Object}   props.msg     The message payload, or null while loading.
 * @param {boolean}  props.loading Loading.
 * @param {string}   props.error   Load error.
 * @param {Function} props.onRetry Load again.
 * @param {Function} props.onBack  Back to the list (phones and tablets).
 * @param {Function} props.onAct   Run an action on this message.
 * @param {Function} props.onMove  Move this message to a folder (its path).
 * @param {Function} props.onShow  Load again with pictures from the internet.
 * @param {string}   props.busy    The action running.
 */
function Reader( {
	msg,
	loading,
	error,
	onRetry,
	onBack,
	onAct,
	onMove,
	onShow,
	busy,
} ) {
	const head = useRef( null );
	useEffect( () => {
		// Below 1200px the message replaces the list, so focus moves to it; beside the list it stays put.
		if (
			msg &&
			head.current &&
			! window.matchMedia( '(min-width: 1200px)' ).matches
		) {
			head.current.focus();
		}
	}, [ msg ] );
	const back = (
		<button
			type="button"
			className="kits-shell-link mailweb-mail__back"
			onClick={ onBack }
		>
			{ __( '← Back to the list', 'mailweb' ) }
		</button>
	);
	if ( loading ) {
		return (
			<>
				{ back }
				<LoadingState
					label={ __( 'Opening the message…', 'mailweb' ) }
				/>
			</>
		);
	}
	if ( error ) {
		return (
			<>
				{ back }
				<ErrorState message={ error } onRetry={ onRetry } />
			</>
		);
	}
	if ( ! msg ) {
		return (
			<EmptyState
				title={ __( 'Choose a message to read it.', 'mailweb' ) }
			/>
		);
	}
	const compose = msg.compose || {};
	return (
		<article className="mailweb-mail__message">
			{ back }
			<h2 className="mailweb-mail__subject" tabIndex="-1" ref={ head }>
				{ msg.subject }
			</h2>
			<div
				className="mailweb-mail__toolbar"
				role="toolbar"
				aria-label={ __( 'Message actions', 'mailweb' ) }
			>
				{ compose.reply && (
					<a
						className="kits-shell-button is-primary"
						href={ compose.reply }
					>
						{ __( 'Reply', 'mailweb' ) }
					</a>
				) }
				{ compose.all && (
					<a
						className="kits-shell-button is-secondary"
						href={ compose.all }
					>
						{ __( 'Reply all', 'mailweb' ) }
					</a>
				) }
				{ compose.forward && (
					<a
						className="kits-shell-button is-secondary"
						href={ compose.forward }
					>
						{ __( 'Forward', 'mailweb' ) }
					</a>
				) }
				{ ( msg.actions || [] ).map( ( a ) => (
					<Button
						key={ a.value }
						variant="secondary"
						busy={ busy === a.value }
						disabled={ !! busy }
						onClick={ () => onAct( a.value ) }
					>
						{ a.label }
					</Button>
				) ) }
				{ ( msg.moveTo || [] ).length > 0 && (
					<span className="mailweb-mail__move">
						<label
							htmlFor="mailweb-move-to"
							className="screen-reader-text"
						>
							{ __( 'Move to folder', 'mailweb' ) }
						</label>
						<select
							id="mailweb-move-to"
							value=""
							disabled={ !! busy }
							onChange={ ( e ) =>
								e.target.value && onMove( e.target.value )
							}
						>
							<option value="">
								{ __( 'Move to folder…', 'mailweb' ) }
							</option>
							{ msg.moveTo.map( ( f ) => (
								<option key={ f.value } value={ f.value }>
									{ f.label }
								</option>
							) ) }
						</select>
					</span>
				) }
			</div>
			{ 'form' === msg.kind ? (
				<>
					<dl className="mailweb-mail__meta">
						{ msg.fields.map( ( f ) => (
							<div key={ f.label }>
								<dt>{ f.label }</dt>
								<dd>{ f.value }</dd>
							</div>
						) ) }
						<div>
							<dt>{ __( 'Date', 'mailweb' ) }</dt>
							<dd>{ msg.date }</dd>
						</div>
					</dl>
					<div className="mailweb-mail__text">{ msg.text }</div>
				</>
			) : (
				<>
					<dl className="mailweb-mail__meta">
						<div>
							<dt>{ __( 'From', 'mailweb' ) }</dt>
							<dd>
								<strong>{ msg.from }</strong>
							</dd>
						</div>
						<div>
							<dt>{ __( 'To', 'mailweb' ) }</dt>
							<dd>{ msg.to }</dd>
						</div>
						{ msg.cc && (
							<div>
								<dt>{ __( 'Cc', 'mailweb' ) }</dt>
								<dd>{ msg.cc }</dd>
							</div>
						) }
						<div>
							<dt>{ __( 'Date', 'mailweb' ) }</dt>
							<dd>{ msg.date }</dd>
						</div>
						<div>
							<dt>{ __( 'Mailbox', 'mailweb' ) }</dt>
							<dd>{ msg.mailbox }</dd>
						</div>
					</dl>
					{ msg.remote && (
						<Notice tone="info">
							{ __(
								'Pictures from the internet are hidden: they can tell the sender you opened the message.',
								'mailweb'
							) }{ ' ' }
							<button
								type="button"
								className="kits-shell-link"
								onClick={ onShow }
							>
								{ __( 'Show pictures', 'mailweb' ) }
							</button>
						</Notice>
					) }
					<iframe
						className="mailweb-mail__frame"
						title={ __( 'Message', 'mailweb' ) }
						sandbox="allow-popups allow-popups-to-escape-sandbox"
						referrerPolicy="no-referrer"
						srcDoc={ msg.doc }
					/>
					{ msg.files?.length > 0 && (
						<section className="mailweb-mail__files">
							<h3>
								{ sprintf(
									/* translators: %d: number of attachments. */
									_n(
										'%d attachment',
										'%d attachments',
										msg.files.length,
										'mailweb'
									),
									msg.files.length
								) }
							</h3>
							<ul>
								{ msg.files.map( ( f ) => (
									<li key={ f.url }>
										<Paperclip />
										<a href={ f.url }>{ f.name }</a>{ ' ' }
										<span className="mailweb-mail__muted">
											{ f.size }
										</span>
									</li>
								) ) }
							</ul>
						</section>
					) }
				</>
			) }
		</article>
	);
}

export default function Inbox() {
	const [ st, setSt ] = useState( startState );
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ note, setNote ] = useState( null );
	const [ picked, setPicked ] = useState( [] );
	const [ open, setOpen ] = useState(
		() => new URLSearchParams( window.location.search ).get( 'msg' ) || ''
	);
	const [ msg, setMsg ] = useState( null );
	const [ msgLoading, setMsgLoading ] = useState( false );
	const [ msgError, setMsgError ] = useState( '' );
	const [ images, setImages ] = useState( false );
	const [ busy, setBusy ] = useState( '' );

	const load = useCallback( () => {
		setLoading( true );
		setError( '' );
		// Rows from another folder are cleared while the new list loads, so none can be opened in the wrong folder.
		setData( ( d ) => ( d && d.folder !== st.folder ? null : d ) );
		apiFetch( {
			path: `${ path( 'inbox' ) }?${ new URLSearchParams( st ).toString() }`,
		} )
			.then( ( r ) => {
				setData( { ...r, folder: st.folder } );
				setPicked( [] );
			} )
			.catch( ( e ) => setError( errText( e ) ) )
			.finally( () => setLoading( false ) );
	}, [ st ] );
	useEffect( load, [ load ] );

	const want = useRef( '' ); // the message, pictures choice and folder last asked for: an older answer is dropped.
	const loadMsg = useCallback( () => {
		const key = `${ open }|${ images }|${ st.folder }`;
		want.current = key;
		if ( ! open ) {
			setMsg( null );
			return;
		}
		setMsgLoading( true );
		setMsgError( '' );
		const args = { ...messageArgs( open, st.folder ) };
		if ( images ) {
			args.images = '1';
		}
		apiFetch( {
			path: `${ path( 'inbox/message' ) }?${ new URLSearchParams(
				args
			).toString() }`,
		} )
			.then( ( r ) => want.current === key && setMsg( r ) )
			.catch(
				( e ) => want.current === key && setMsgError( errText( e ) )
			)
			.finally( () => want.current === key && setMsgLoading( false ) );
	}, [ open, images, st.folder ] );
	useEffect( loadMsg, [ loadMsg ] );

	// Keep the address in step (same page, &ui=next kept).
	useEffect( () => {
		const url = new URL( window.location.href );
		[
			[ 'box', 'all' === st.box ? '' : st.box ],
			[ 'folder', 'inbox' === st.folder ? '' : st.folder ],
			[ 's', st.search ],
			[ 'paged', st.page > 1 ? String( st.page ) : '' ],
			[ 'msg', open ],
		].forEach( ( [ k, v ] ) =>
			v ? url.searchParams.set( k, v ) : url.searchParams.delete( k )
		);
		window.history.replaceState( null, '', url.toString() );
	}, [ st, open ] );

	const go = ( patch ) => {
		setOpen( '' );
		setSt( ( s ) => ( { ...s, page: 1, ...patch } ) );
	};

	const openRow = ( r ) => {
		if ( ! data || data.folder !== st.folder ) {
			return; // a row from the folder being left
		}
		setImages( false );
		setOpen( r.id );
		if ( r.unread ) {
			// Opening marks it read on the server; show that at once.
			setData( ( d ) => ( {
				...d,
				rows: d.rows.map( ( x ) =>
					x.id === r.id ? { ...x, unread: false } : x
				),
				views: d.views.map( ( v ) =>
					v.value === st.folder && v.count > 0
						? { ...v, count: v.count - 1 }
						: v
				),
			} ) );
		}
	};

	const run = ( action, ids ) => {
		setBusy( action );
		return apiFetch( {
			path: path( 'inbox/bulk' ),
			method: 'POST',
			data: { action, ids, folder: st.folder },
		} )
			.then( ( r ) => {
				setNote( {
					tone: r.ok ? 'success' : 'error',
					text: r.message,
				} );
				if ( r.ok ) {
					// Moved, or marked unread: back to the list, unless another message was opened meanwhile.
					setOpen( ( o ) => ( ids.includes( o ) ? '' : o ) );
				}
				load();
			} )
			.catch( ( e ) => setNote( { tone: 'error', text: errText( e ) } ) )
			.finally( () => setBusy( '' ) );
	};

	// Move to folder: the same move as Archive, to a folder of the message's own mailbox.
	const move = ( ids, to ) => {
		setBusy( 'move' );
		return apiFetch( {
			path: path( 'inbox/move' ),
			method: 'POST',
			data: { ids, to, folder: st.folder },
		} )
			.then( ( r ) => {
				setNote( {
					tone: r.ok ? 'success' : 'error',
					text: r.message,
				} );
				if ( r.ok ) {
					setOpen( ( o ) => ( ids.includes( o ) ? '' : o ) );
				}
				load();
			} )
			.catch( ( e ) => setNote( { tone: 'error', text: errText( e ) } ) )
			.finally( () => setBusy( '' ) );
	};

	const rows = data?.rows || [];
	const cols = data?.columns || {};
	const allPicked = rows.length > 0 && picked.length === rows.length;
	const toggle = ( id ) =>
		setPicked( ( p ) =>
			p.includes( id ) ? p.filter( ( x ) => x !== id ) : [ ...p, id ]
		);
	const [ words, setWords ] = useState( st.search );
	useEffect( () => setWords( st.search ), [ st.search ] );

	let emptyTitle = __( 'No messages.', 'mailweb' );
	if ( data?.failed ) {
		emptyTitle = __(
			'The mailbox could not be read just now. Try again in a minute.',
			'mailweb'
		);
	} else if ( st.search ) {
		emptyTitle = __( 'Nothing matches your search.', 'mailweb' );
	}

	let list;
	if ( loading && ! data ) {
		list = <LoadingState label={ __( 'Loading messages…', 'mailweb' ) } />;
	} else if ( error ) {
		list = <ErrorState message={ error } onRetry={ load } />;
	} else if ( ! rows.length ) {
		list = <EmptyState title={ emptyTitle } />;
	} else {
		list = (
			<ul
				className="mailweb-mail__rows"
				aria-label={ __( 'Messages', 'mailweb' ) }
				aria-busy={ loading || undefined }
			>
				{ rows.map( ( r ) => (
					<li
						key={ r.id }
						className={
							'mailweb-mail__row' +
							( r.unread ? ' is-unread' : '' ) +
							( r.id === open ? ' is-open' : '' )
						}
					>
						<input
							type="checkbox"
							className="mailweb-mail__pick"
							checked={ picked.includes( r.id ) }
							onChange={ () => toggle( r.id ) }
							aria-label={ sprintf(
								/* translators: %s: message subject. */
								__( 'Select “%s”', 'mailweb' ),
								r.subject
							) }
						/>
						<button
							type="button"
							className="mailweb-mail__open"
							aria-current={ r.id === open ? 'true' : undefined }
							onClick={ () => openRow( r ) }
						>
							<span className="mailweb-mail__line">
								<span className="mailweb-mail__from">
									{ r.from }
								</span>
								<span className="mailweb-mail__date">
									{ r.date }
								</span>
							</span>
							<span className="mailweb-mail__line">
								<span className="mailweb-mail__title">
									{ r.subject }
								</span>
								{ r.files && (
									<>
										<Paperclip />
										<span className="screen-reader-text">
											{ __(
												'Has attachments',
												'mailweb'
											) }
										</span>
									</>
								) }
							</span>
							{ ( cols.mailbox && r.mailbox ) || r.email ? (
								<span className="mailweb-mail__muted">
									{ [ r.email, cols.mailbox ? r.mailbox : '' ]
										.filter( Boolean )
										.join( ' · ' ) }
								</span>
							) : null }
							{ r.unread && (
								<span className="screen-reader-text">
									{ __( '(unread)', 'mailweb' ) }
								</span>
							) }
						</button>
					</li>
				) ) }
			</ul>
		);
	}

	return (
		<div className="mailweb-mail" data-pane={ open ? 'read' : 'list' }>
			<Folders data={ data } st={ st } go={ go } />
			<section
				className="mailweb-mail__list"
				aria-label={ __( 'Message list', 'mailweb' ) }
			>
				{ false !== cols.search && (
					<form
						className="mailweb-mail__search"
						role="search"
						onSubmit={ ( e ) => {
							e.preventDefault();
							go( { search: words.trim() } );
						} }
					>
						<label
							className="screen-reader-text"
							htmlFor="mailweb-search"
						>
							{ __( 'Search mail', 'mailweb' ) }
						</label>
						<input
							id="mailweb-search"
							type="search"
							value={ words }
							placeholder={ __( 'Search mail', 'mailweb' ) }
							onChange={ ( e ) => setWords( e.target.value ) }
						/>
					</form>
				) }
				<div className="mailweb-mail__bar">
					<input
						type="checkbox"
						className="mailweb-mail__pick"
						checked={ allPicked }
						disabled={ ! rows.length }
						onChange={ () =>
							setPicked(
								allPicked ? [] : rows.map( ( r ) => r.id )
							)
						}
						aria-label={ __(
							'Select all on this page',
							'mailweb'
						) }
					/>
					{ picked.length > 0 ? (
						( data?.bulk || [] ).map( ( b ) => (
							<Button
								key={ b.value }
								variant="link"
								busy={ busy === b.value }
								disabled={ !! busy }
								onClick={ () => run( b.value, picked ) }
							>
								{ b.label }
							</Button>
						) )
					) : (
						<span className="mailweb-mail__muted">
							{ sprintf(
								/* translators: %d: number of messages. */
								_n(
									'%d message',
									'%d messages',
									data?.total || 0,
									'mailweb'
								),
								data?.total || 0
							) }
						</span>
					) }
					<Button
						variant="link"
						onClick={ load }
						disabled={ loading }
					>
						{ __( 'Refresh', 'mailweb' ) }
					</Button>
				</div>
				{ note && (
					<Notice
						tone={ note.tone }
						onDismiss={ () => setNote( null ) }
					>
						{ note.text }
					</Notice>
				) }
				{ list }
				{ ( data?.pages || 1 ) > 1 && (
					<nav
						className="mailweb-mail__pager"
						aria-label={ __( 'Pages', 'mailweb' ) }
					>
						<Button
							variant="secondary"
							disabled={ st.page <= 1 || loading }
							onClick={ () =>
								setSt( ( s ) => ( { ...s, page: s.page - 1 } ) )
							}
						>
							{ __( 'Newer', 'mailweb' ) }
						</Button>
						<span>
							{ sprintf(
								/* translators: 1: page number, 2: number of pages. */
								__( 'Page %1$d of %2$d', 'mailweb' ),
								st.page,
								data.pages
							) }
						</span>
						<Button
							variant="secondary"
							disabled={ st.page >= data.pages || loading }
							onClick={ () =>
								setSt( ( s ) => ( { ...s, page: s.page + 1 } ) )
							}
						>
							{ __( 'Older', 'mailweb' ) }
						</Button>
					</nav>
				) }
			</section>
			<section
				className="mailweb-mail__read"
				aria-label={ __( 'Open message', 'mailweb' ) }
			>
				<Reader
					msg={ open ? msg : null }
					loading={ !! open && msgLoading }
					error={ open ? msgError : '' }
					onRetry={ loadMsg }
					onBack={ () => setOpen( '' ) }
					onAct={ ( a ) => run( a, [ open ] ) }
					onMove={ ( to ) => move( [ open ], to ) }
					onShow={ () => setImages( true ) }
					busy={ busy }
				/>
			</section>
		</div>
	);
}
