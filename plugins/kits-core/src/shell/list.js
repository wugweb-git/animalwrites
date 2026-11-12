/**
 * Kits admin shell: the working list and the field-by-field review (docs/kits-plugin-ui.md).
 *
 * DataList is driven by the screen: it shows the rows it is given and reports what the user asks for (search, a filter,
 * a sort, a page, a view, a bulk or row action). The screen fetches the rows from its own REST route; nothing is
 * filtered or sorted in the browser, so a list of 50,000 rows costs the same as one of 50.
 */
import { Fragment, useEffect, useId, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { EmptyState, LoadingState, ErrorState } from './states';

const cx = ( ...names ) => names.filter( Boolean ).join( ' ' );

/**
 * A row action: a link or a button. `disabled` with `why` shows the action off and says why ("Undo needs SourceWeb").
 *
 * @param {Object} props
 * @param {Object} props.action { label, href, onClick, disabled, why, tone, expanded, controls }. expanded and
 *                              controls: a button that opens a part of the page (aria-expanded, aria-controls).
 */
export function RowAction( { action } ) {
	const id = useId();
	const cls = cx( 'kits-shell-link', 'error' === action.tone && 'is-danger' );
	if ( action.disabled ) {
		return (
			<span className="kits-shell-rowaction is-off">
				<button
					type="button"
					className="kits-shell-link"
					disabled
					aria-describedby={ action.why ? id : undefined }
				>
					{ action.label }
				</button>
				{ action.why && (
					<span className="kits-shell-rowaction__why" id={ id }>
						{ action.why }
					</span>
				) }
			</span>
		);
	}
	if ( action.href ) {
		return (
			<a className={ cls } href={ action.href }>
				{ action.label }
			</a>
		);
	}
	return (
		<button
			type="button"
			className={ cls }
			onClick={ action.onClick }
			aria-expanded={
				undefined === action.expanded ? undefined : action.expanded
			}
			aria-controls={ action.controls }
		>
			{ action.label }
		</button>
	);
}

function FilterSelect( { filter, onFilter } ) {
	const id = useId();
	return (
		<div className="kits-shell-filter">
			<label htmlFor={ id }>{ filter.label }</label>
			<select
				id={ id }
				value={ filter.value ?? '' }
				onChange={ ( e ) => onFilter?.( filter.key, e.target.value ) }
			>
				{ ( filter.options || [] ).map( ( o ) => (
					<option key={ o.value } value={ o.value }>
						{ o.label }
					</option>
				) ) }
			</select>
		</div>
	);
}

/**
 * The working list: saved views, search, filters, sortable columns, bulk and row actions, pagination, and the empty,
 * loading and error states. On phones each row stacks into a card, every cell with its column's name.
 *
 * @param {Object}   props
 * @param {string}   props.label       The list's name (table caption).
 * @param {Array}    props.columns     [ { key, label, sortable, primary, render( row ) } ]. The primary column carries the row actions.
 * @param {Array}    props.rows        The rows of this page.
 * @param {string}   props.rowKey      Key of a row's id (default 'id').
 * @param {string}   props.rowTitle    Key of a row's name, for the tick box's label (default 'title').
 * @param {number}   props.total       Rows in all pages.
 * @param {number}   props.page        This page (from 1).
 * @param {number}   props.pages       Number of pages.
 * @param {Function} props.onPage      Called with a page number.
 * @param {string}   props.search      The search words.
 * @param {Function} props.onSearch    Called with the words when the search is sent (Enter or the button).
 * @param {string}   props.searchLabel The search box's label.
 * @param {Array}    props.filters     [ { key, label, options: [ { value, label } ], value } ].
 * @param {Function} props.onFilter    Called with ( key, value ).
 * @param {Array}    props.views       [ { value, label, count } ]: saved views above the list ("Waiting 3").
 * @param {string}   props.view        The open view.
 * @param {Function} props.onView      Called with a view's value.
 * @param {Object}   props.sort        { key, dir: 'asc' | 'desc' }.
 * @param {Function} props.onSort      Called with ( key, dir ).
 * @param {Array}    props.bulkActions [ { value, label } ].
 * @param {Function} props.onBulk      Called with ( value, ids ).
 * @param {Function} props.rowActions  row => [ { label, href, onClick, disabled, why, tone } ].
 * @param {Function} props.rowView     row => what the row shows when opened (a DetailPanel …), or null for none.
 *                                     Opened rows show under their row, across every column; View and Hide toggle it.
 * @param {boolean}  props.loading     Rows are being fetched.
 * @param {string}   props.error       What failed, or ''.
 * @param {Function} props.onRetry     Try again after an error.
 * @param {string}   props.emptyTitle  Words when there are no rows.
 * @param {Element}  props.empty       More words or an action when there are no rows.
 */
export function DataList( {
	label,
	columns = [],
	rows = [],
	rowKey = 'id',
	rowTitle = 'title',
	total = 0,
	page = 1,
	pages = 1,
	onPage,
	search = '',
	onSearch,
	searchLabel,
	filters = [],
	onFilter,
	views = [],
	view,
	onView,
	sort = {},
	onSort,
	bulkActions = [],
	onBulk,
	rowActions,
	rowView,
	loading,
	error,
	onRetry,
	emptyTitle,
	empty,
} ) {
	const [ words, setWords ] = useState( search );
	const [ picked, setPicked ] = useState( [] );
	const [ bulk, setBulk ] = useState( '' );
	const [ open, setOpen ] = useState( [] );
	const viewBase = useId();
	const searchId = useId();
	const bulkId = useId();
	useEffect( () => setWords( search ), [ search ] );
	// A new page of rows starts with nothing ticked.
	const ids = rows.map( ( r ) => r[ rowKey ] );
	const idList = ids.join( '\n' );
	useEffect( () => setPicked( ( p ) => ( p.length ? [] : p ) ), [ idList ] );
	useEffect( () => setOpen( ( o ) => ( o.length ? [] : o ) ), [ idList ] );

	const all = ids.length > 0 && ids.every( ( id ) => picked.includes( id ) );
	const tick = ( id, on ) =>
		setPicked( ( p ) =>
			on ? [ ...p, id ] : p.filter( ( x ) => x !== id )
		);
	const hasBulk = bulkActions.length > 0 && !! onBulk;

	const ariaSort = ( c ) => {
		if ( ! c.sortable || sort.key !== c.key ) {
			return undefined;
		}
		return 'asc' === sort.dir ? 'ascending' : 'descending';
	};
	const head = ( c ) => {
		if ( ! c.sortable || ! onSort ) {
			return c.label;
		}
		const on = sort.key === c.key;
		const next = on && 'asc' === sort.dir ? 'desc' : 'asc';
		let mark = '';
		if ( on ) {
			mark = 'asc' === sort.dir ? '↑' : '↓';
		}
		return (
			<button
				type="button"
				className="kits-shell-sort"
				onClick={ () => onSort( c.key, next ) }
			>
				{ c.label }
				<span className="kits-shell-sort__mark" aria-hidden="true">
					{ mark }
				</span>
			</button>
		);
	};

	let body;
	if ( error ) {
		body = <ErrorState message={ error } onRetry={ onRetry } />;
	} else if ( ! loading && 0 === rows.length ) {
		body = (
			<EmptyState
				title={ emptyTitle || __( 'Nothing here.', 'kits-core' ) }
			>
				{ empty }
			</EmptyState>
		);
	} else if ( loading && 0 === rows.length ) {
		body = <LoadingState />;
	} else {
		body = (
			<table
				className={ cx( 'kits-shell-list', loading && 'is-loading' ) }
				aria-busy={ loading || undefined }
			>
				<caption className="screen-reader-text">{ label }</caption>
				<thead>
					<tr>
						{ hasBulk && (
							<td className="kits-shell-list__check">
								<input
									type="checkbox"
									checked={ all }
									aria-label={ __(
										'Select all',
										'kits-core'
									) }
									onChange={ ( e ) =>
										setPicked( e.target.checked ? ids : [] )
									}
								/>
							</td>
						) }
						{ columns.map( ( c ) => (
							<th
								key={ c.key }
								scope="col"
								aria-sort={ ariaSort( c ) }
							>
								{ head( c ) }
							</th>
						) ) }
					</tr>
				</thead>
				<tbody>
					{ rows.map( ( r ) => {
						const id = r[ rowKey ];
						const acts = rowActions ? [ ...rowActions( r ) ] : [];
						const detail = rowView ? rowView( r ) : null;
						const isOpen = !! detail && open.includes( id );
						const viewId = `${ viewBase }-${ String( id ).replace(
							/[^\w-]/g,
							'_'
						) }`;
						if ( detail ) {
							acts.push( {
								label: isOpen
									? __( 'Hide', 'kits-core' )
									: __( 'View', 'kits-core' ),
								onClick: () =>
									setOpen( ( o ) =>
										o.includes( id )
											? o.filter( ( x ) => x !== id )
											: [ ...o, id ]
									),
								expanded: isOpen,
								controls: viewId,
							} );
						}
						return (
							<Fragment key={ id }>
								<tr
									className={ isOpen ? 'is-open' : undefined }
								>
									{ hasBulk && (
										<td className="kits-shell-list__check">
											<input
												type="checkbox"
												checked={ picked.includes(
													id
												) }
												aria-label={ sprintf(
													/* translators: %s: the row's name. */
													__(
														'Select %s',
														'kits-core'
													),
													String(
														r[ rowTitle ] || id
													)
												) }
												onChange={ ( e ) =>
													tick( id, e.target.checked )
												}
											/>
										</td>
									) }
									{ columns.map( ( c ) => {
										const cell = c.render
											? c.render( r )
											: r[ c.key ];
										const Tag = c.primary ? 'th' : 'td';
										return (
											<Tag
												key={ c.key }
												scope={
													c.primary
														? 'row'
														: undefined
												}
												data-label={ c.label }
												className={
													c.primary
														? 'is-primary'
														: undefined
												}
											>
												{ cell }
												{ c.primary &&
													acts.length > 0 && (
														<span className="kits-shell-rowactions">
															{ acts.map(
																( a ) => (
																	<RowAction
																		key={
																			a.label
																		}
																		action={
																			a
																		}
																	/>
																)
															) }
														</span>
													) }
											</Tag>
										);
									} ) }
								</tr>
								{ isOpen && (
									<tr
										className="kits-shell-list__view"
										id={ viewId }
									>
										<td
											colSpan={
												columns.length +
												( hasBulk ? 1 : 0 )
											}
										>
											{ detail }
										</td>
									</tr>
								) }
							</Fragment>
						);
					} ) }
				</tbody>
			</table>
		);
	}

	return (
		<div className="kits-shell-datalist">
			{ views.length > 0 && (
				<ul
					className="kits-shell-views"
					aria-label={ __( 'Views', 'kits-core' ) }
				>
					{ views.map( ( v ) => (
						<li key={ v.value }>
							<button
								type="button"
								className={ cx(
									'kits-shell-view',
									v.value === view && 'is-on'
								) }
								aria-pressed={ v.value === view }
								onClick={ () => onView?.( v.value ) }
							>
								{ v.label }
								{ undefined !== v.count && (
									<span className="kits-shell-view__count">
										{ v.count }
									</span>
								) }
							</button>
						</li>
					) ) }
				</ul>
			) }
			{ ( onSearch || filters.length > 0 || hasBulk ) && (
				<div className="kits-shell-listbar">
					{ onSearch && (
						<form
							className="kits-shell-search"
							role="search"
							onSubmit={ ( e ) => {
								e.preventDefault();
								onSearch( words.trim() );
							} }
						>
							<label
								className="screen-reader-text"
								htmlFor={ searchId }
							>
								{ searchLabel || __( 'Search', 'kits-core' ) }
							</label>
							<input
								id={ searchId }
								type="search"
								value={ words }
								placeholder={
									searchLabel || __( 'Search', 'kits-core' )
								}
								onChange={ ( e ) => setWords( e.target.value ) }
							/>
							<button
								type="submit"
								className="kits-shell-button is-secondary"
							>
								{ __( 'Search', 'kits-core' ) }
							</button>
						</form>
					) }
					{ filters.map( ( f ) => (
						<FilterSelect
							key={ f.key }
							filter={ f }
							onFilter={ onFilter }
						/>
					) ) }
					{ hasBulk && (
						<div className="kits-shell-bulk">
							<label
								className="screen-reader-text"
								htmlFor={ bulkId }
							>
								{ __( 'Bulk action', 'kits-core' ) }
							</label>
							<select
								id={ bulkId }
								value={ bulk }
								onChange={ ( e ) => setBulk( e.target.value ) }
							>
								<option value="">
									{ __( 'Bulk action', 'kits-core' ) }
								</option>
								{ bulkActions.map( ( b ) => (
									<option key={ b.value } value={ b.value }>
										{ b.label }
									</option>
								) ) }
							</select>
							<button
								type="button"
								className="kits-shell-button is-secondary"
								disabled={ ! bulk || 0 === picked.length }
								onClick={ () => onBulk( bulk, picked ) }
							>
								{ __( 'Apply', 'kits-core' ) }
							</button>
							{ picked.length > 0 && (
								<span className="kits-shell-bulk__count">
									{ sprintf(
										/* translators: %d: number of rows ticked. */
										_n(
											'%d selected',
											'%d selected',
											picked.length,
											'kits-core'
										),
										picked.length
									) }
								</span>
							) }
						</div>
					) }
				</div>
			) }
			{ body }
			{ ! error && total > 0 && (
				<nav
					className="kits-shell-pager"
					aria-label={ __( 'Pages', 'kits-core' ) }
				>
					<span className="kits-shell-pager__count">
						{ pages > 1
							? sprintf(
									/* translators: 1: page, 2: pages, 3: rows. */
									__(
										'Page %1$d of %2$d · %3$d rows',
										'kits-core'
									),
									page,
									pages,
									total
								)
							: sprintf(
									/* translators: %d: rows. */
									_n(
										'%d row',
										'%d rows',
										total,
										'kits-core'
									),
									total
								) }
					</span>
					{ pages > 1 && (
						<>
							<button
								type="button"
								className="kits-shell-button is-secondary"
								disabled={ page <= 1 || loading }
								onClick={ () => onPage?.( page - 1 ) }
							>
								{ __( 'Previous', 'kits-core' ) }
							</button>
							<button
								type="button"
								className="kits-shell-button is-secondary"
								disabled={ page >= pages || loading }
								onClick={ () => onPage?.( page + 1 ) }
							>
								{ __( 'Next', 'kits-core' ) }
							</button>
						</>
					) }
				</nav>
			) }
		</div>
	);
}

/**
 * Field-by-field review of one proposed change (a claim, an enrichment proposal): the current value beside the
 * proposed one, a tick per field, Apply for the ticked fields, and Undo on a field already applied.
 *
 * @param {Object}   props
 * @param {string}   props.title      Heading (H3).
 * @param {Array}    props.fields     [ { key, label, current, proposed, applied, undoOff } ]. undoOff: why Undo is off, or ''.
 * @param {Function} props.onApply    Called with the ticked keys.
 * @param {Function} props.onReject   Called with the ticked keys ("Reject ticked"); leave out where nothing is rejected.
 * @param {Function} props.onUndo     Called with one key.
 * @param {boolean}  props.busy       Saving.
 * @param {string}   props.applyLabel The Apply button's words.
 * @param {Element}  props.after      Extra controls under the table ("Show my name").
 */
export function FieldReview( {
	title,
	fields = [],
	onApply,
	onReject,
	onUndo,
	busy,
	applyLabel,
	after,
} ) {
	const open = fields.filter( ( f ) => ! f.applied ).map( ( f ) => f.key );
	// Kept as the fields the user unticked, so fields that become applied (or undone) need no reset.
	const [ off, setOff ] = useState( [] );
	const picked = open.filter( ( k ) => ! off.includes( k ) );
	const tick = ( k, on ) =>
		setOff( ( o ) => ( on ? o.filter( ( x ) => x !== k ) : [ ...o, k ] ) );
	const blank = (
		<span className="kits-shell-review__empty">
			{ __( '(empty)', 'kits-core' ) }
		</span>
	);
	return (
		<div className="kits-shell-review">
			{ title && <h3 className="kits-shell-block__title">{ title }</h3> }
			<table className="kits-shell-list kits-shell-review__table">
				<caption className="screen-reader-text">
					{ title || __( 'Proposed changes', 'kits-core' ) }
				</caption>
				<thead>
					<tr>
						<td className="kits-shell-list__check" />
						<th scope="col">{ __( 'Field', 'kits-core' ) }</th>
						<th scope="col">{ __( 'Now', 'kits-core' ) }</th>
						<th scope="col">{ __( 'Proposed', 'kits-core' ) }</th>
						<th scope="col">{ __( 'State', 'kits-core' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ fields.map( ( f ) => (
						<tr
							key={ f.key }
							className={ f.applied ? 'is-applied' : undefined }
						>
							<td className="kits-shell-list__check">
								{ ! f.applied && (
									<input
										type="checkbox"
										checked={ picked.includes( f.key ) }
										aria-label={ sprintf(
											/* translators: %s: field name. */
											__( 'Apply %s', 'kits-core' ),
											f.label
										) }
										onChange={ ( e ) =>
											tick( f.key, e.target.checked )
										}
									/>
								) }
							</td>
							<th
								scope="row"
								className="is-primary"
								data-label={ __( 'Field', 'kits-core' ) }
							>
								{ f.label }
							</th>
							<td data-label={ __( 'Now', 'kits-core' ) }>
								{ f.current || blank }
							</td>
							<td data-label={ __( 'Proposed', 'kits-core' ) }>
								<ins>{ f.proposed || blank }</ins>
							</td>
							<td data-label={ __( 'State', 'kits-core' ) }>
								{ f.applied ? (
									<>
										<span className="kits-shell-status is-ok">
											{ __( 'Applied', 'kits-core' ) }
										</span>{ ' ' }
										{ onUndo && (
											<RowAction
												action={ {
													label: __(
														'Undo',
														'kits-core'
													),
													onClick: () =>
														onUndo( f.key ),
													disabled: !! f.undoOff,
													why: f.undoOff,
												} }
											/>
										) }
									</>
								) : (
									__( 'Waiting', 'kits-core' )
								) }
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
			{ after }
			{ onApply && open.length > 0 && (
				<div className="kits-shell-review__foot">
					{ onReject && (
						<button
							type="button"
							className="kits-shell-button"
							aria-busy={ busy || undefined }
							disabled={ busy || 0 === picked.length }
							onClick={ () => onReject( picked ) }
						>
							{ sprintf(
								/* translators: %d: number of fields ticked. */
								_n(
									'Reject %d ticked',
									'Reject %d ticked',
									picked.length,
									'kits-core'
								),
								picked.length
							) }
						</button>
					) }
					<button
						type="button"
						className="kits-shell-button is-primary"
						aria-busy={ busy || undefined }
						disabled={ busy || 0 === picked.length }
						onClick={ () => onApply( picked ) }
					>
						{ applyLabel ||
							sprintf(
								/* translators: %d: number of fields ticked. */
								_n(
									'Apply %d field',
									'Apply %d fields',
									picked.length,
									'kits-core'
								),
								picked.length
							) }
					</button>
				</div>
			) }
		</div>
	);
}
