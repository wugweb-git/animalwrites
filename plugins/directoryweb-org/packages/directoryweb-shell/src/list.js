/**
 * Kits admin shell: the working list and the field-by-field review (docs/kits-plugin-ui.md).
 *
 * DataList is driven by the screen: it shows the rows it is given and reports what the user asks for (search, a filter,
 * a sort, a page, a view, a bulk or row action). The screen fetches the rows from its own REST route; nothing is
 * filtered or sorted in the browser, so a list of 50,000 rows costs the same as one of 50.
 */
import { useEffect, useId, useState } from '@wordpress/element';
import { DataViews } from '@wordpress/dataviews';
import { ThemeProvider } from '@wordpress/theme';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Button, Modal, SearchControl } from '@wordpress/components';
import {
	check,
	closeSmall,
	external,
	pencil,
	seen,
	trash,
	undo,
} from '@wordpress/icons';
import { EmptyState, ErrorState } from './states';

const cx = ( ...names ) => names.filter( Boolean ).join( ' ' );

/**
 * The icon for each named row action (@wordpress/icons). The tone follows the name: delete and cancel are red, success is
 * green; edit, view and external stay ink.
 */
const ROW_ICONS = {
	edit: { icon: pencil },
	delete: { icon: trash, tone: 'danger' },
	cancel: { icon: closeSmall, tone: 'danger' },
	success: { icon: check, tone: 'success' },
	view: { icon: seen },
	undo: { icon: undo },
	external: { icon: external },
};

/**
 * A row action: a link or a button. `disabled` with `why` shows the action off and says why ("Undo needs SourceWeb").
 * With `icon` (edit, delete, cancel, success, view, undo, external) it is an icon button: the label is its accessible
 * name and its tooltip. `external` opens a new tab and says so to screen readers.
 *
 * @param {Object} props
 * @param {Object} props.action { label, href, onClick, disabled, why, tone, icon, expanded, controls }. expanded and
 *                              controls: a button that opens a part of the page (aria-expanded, aria-controls).
 */
export function RowAction( { action } ) {
	const id = useId();
	const named = ROW_ICONS[ action.icon ];
	if ( named ) {
		const ext = 'external' === action.icon;
		const label = ext
			? sprintf(
					/* translators: %s: action label. */
					__( '%s (opens in a new tab)', 'kits-core' ),
					action.label
				)
			: action.label;
		const button = (
			<Button
				className={ cx(
					'kits-shell-iconaction',
					named.tone && 'is-' + named.tone
				) }
				icon={ named.icon }
				label={ label }
				showTooltip
				size="small"
				href={ action.disabled ? undefined : action.href }
				target={ ext && action.href ? '_blank' : undefined }
				rel={ ext && action.href ? 'noopener noreferrer' : undefined }
				onClick={ action.disabled ? undefined : action.onClick }
				disabled={ action.disabled }
				accessibleWhenDisabled={ action.disabled }
				aria-describedby={
					action.disabled && action.why ? id : undefined
				}
				aria-expanded={
					undefined === action.expanded ? undefined : action.expanded
				}
				aria-controls={ action.controls }
			/>
		);
		if ( action.disabled && action.why ) {
			return (
				<span className="kits-shell-rowaction is-off">
					{ button }
					<span className="kits-shell-rowaction__why" id={ id }>
						{ action.why }
					</span>
				</span>
			);
		}
		return button;
	}
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

/**
 * The working list, on WordPress's DataViews (13 Nov 2026): saved views as pills above it, then DataViews' search,
 * filters, table, tick boxes with the bulk actions, and pagination; the empty, loading and error states. The screen
 * stays in charge: every search, filter, sort and page goes back to it (onSearch, onFilter, onSort, onPage) and it
 * fetches the rows from its own REST route; nothing is filtered or sorted in the browser.
 *
 * @param {Object}   props
 * @param {string}   props.label         The list's name (the table's region label).
 * @param {Array}    props.columns       [ { key, label, sortable, primary, render( row ) } ]. The primary column carries the row actions.
 * @param {Array}    props.rows          The rows of this page.
 * @param {string}   props.rowKey        Key of a row's id (default 'id').
 * @param {number}   props.total         Rows in all pages.
 * @param {number}   props.page          This page (from 1).
 * @param {number}   props.pages         Number of pages.
 * @param {Function} props.onPage        Called with a page number.
 * @param {string}   props.search        The search words.
 * @param {Function} props.onSearch      Called with the words as they are typed (DataViews waits for a pause).
 * @param {boolean}  props.searchOnEnter Send the words only on Enter: for a screen whose search loads a new page.
 * @param {string}   props.searchLabel   The search box's label.
 * @param {Array}    props.filters       [ { key, label, options: [ { value, label } ], value } ]; an option with value '' is "all".
 * @param {Function} props.onFilter      Called with ( key, value ); '' when the filter is cleared.
 * @param {Array}    props.views         [ { value, label, count } ]: saved views above the list ("Waiting 3").
 * @param {string}   props.view          The open view.
 * @param {Function} props.onView        Called with a view's value.
 * @param {Object}   props.sort          { key, dir: 'asc' | 'desc' }.
 * @param {Function} props.onSort        Called with ( key, dir ).
 * @param {Array}    props.bulkActions   [ { value, label } ]: DataViews actions on the ticked rows (and in each row's menu).
 * @param {Function} props.onBulk        Called with ( value, ids ).
 * @param {Function} props.rowActions    row => [ { label, href, onClick, disabled, why, tone, icon } ] (RowAction).
 * @param {Function} props.rowView       row => what the row shows when opened (a DetailPanel …), or null for none.
 *                                       A View action opens it in a dialog.
 * @param {boolean}  props.loading       Rows are being fetched.
 * @param {string}   props.error         What failed, or ''.
 * @param {Function} props.onRetry       Try again after an error.
 * @param {string}   props.emptyTitle    Words when there are no rows.
 * @param {Element}  props.empty         More words or an action when there are no rows.
 */
export function DataList( {
	label,
	columns = [],
	rows = [],
	rowKey = 'id',
	total = 0,
	page = 1,
	pages = 1,
	onPage,
	search = '',
	onSearch,
	searchOnEnter,
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
	const [ picked, setPicked ] = useState( [] );
	const [ open, setOpen ] = useState( null );
	const [ words, setWords ] = useState( search );
	useEffect( () => setWords( search ), [ search ] );
	const idList = rows.map( ( r ) => String( r[ rowKey ] ) ).join( '\n' );
	// A new page of rows starts with nothing ticked.
	useEffect( () => setPicked( ( p ) => ( p.length ? [] : p ) ), [ idList ] );
	const openRow =
		null === open
			? null
			: rows.find( ( r ) => String( r[ rowKey ] ) === open );
	const openView = openRow && rowView ? rowView( openRow ) : null;

	const primary = columns.find( ( c ) => c.primary ) || columns[ 0 ];
	const shown = new Set( columns.map( ( c ) => c.key ) );
	const fields = [
		...columns.map( ( c ) => {
			const f = filters.find( ( x ) => x.key === c.key );
			return {
				id: c.key,
				label: c.label,
				enableSorting: !! ( c.sortable && onSort ),
				enableHiding: false,
				enableGlobalSearch: false,
				getValue: ( { item } ) => item[ c.key ],
				render: ( { item } ) => {
					const cell = c.render ? c.render( item ) : item[ c.key ];
					if ( c !== primary || ! ( rowActions || rowView ) ) {
						return cell;
					}
					const acts = [
						...( ( rowActions && rowActions( item ) ) || [] ),
					];
					if ( rowView && rowView( item ) ) {
						acts.push( {
							label: __( 'View', 'kits-core' ),
							icon: 'view',
							onClick: () => setOpen( String( item[ rowKey ] ) ),
						} );
					}
					return (
						<>
							{ cell }
							{ acts.length > 0 && (
								<span className="kits-shell-rowactions">
									{ acts.map( ( a ) => (
										<RowAction
											key={ a.label }
											action={ a }
										/>
									) ) }
								</span>
							) }
						</>
					);
				},
				...( f ? filterOf( f ) : {} ),
			};
		} ),
		// A filter with no column of its own: a field that is never a column.
		...filters
			.filter( ( f ) => ! shown.has( f.key ) )
			.map( ( f ) => ( {
				id: f.key,
				label: f.label,
				enableSorting: false,
				enableHiding: false,
				enableGlobalSearch: false,
				getValue: ( { item } ) => item[ f.key ],
				...filterOf( f ),
			} ) ),
	];

	const dvView = {
		type: 'table',
		search,
		page,
		perPage: Math.max( rows.length, 1 ),
		sort: sort.key
			? {
					field: sort.key,
					direction: 'asc' === sort.dir ? 'asc' : 'desc',
				}
			: undefined,
		filters: filters
			.filter( ( f ) => '' !== String( f.value ?? '' ) )
			.map( ( f ) => ( {
				field: f.key,
				operator: 'is',
				value: f.value,
			} ) ),
		titleField: primary?.key,
		fields: columns.filter( ( c ) => c !== primary ).map( ( c ) => c.key ),
		layout: { density: 'balanced' },
	};

	// DataViews hands back the whole view; each change goes to the screen as the call it already answers. One view can
	// carry several (Reset clears the search and every filter); a new search, filter or sort starts again at page 1,
	// so the page goes only when nothing else changed.
	const onChangeView = ( next ) => {
		let other = false;
		const typed = ( next.search ?? '' ).trim();
		if ( typed !== search.trim() ) {
			other = true;
			onSearch?.( typed );
		}
		const now = new Map(
			( next.filters || [] )
				.filter( ( x ) => undefined !== x.value && '' !== x.value )
				.map( ( x ) => [ x.field, x.value ] )
		);
		for ( const f of filters ) {
			const b = String( now.get( f.key ) ?? '' );
			if ( String( f.value ?? '' ) !== b ) {
				other = true;
				onFilter?.( f.key, b );
			}
		}
		const s = next.sort;
		if ( s && ( s.field !== sort.key || s.direction !== sort.dir ) ) {
			other = true;
			onSort?.( s.field, s.direction );
		}
		if ( ! other && next.page && next.page !== page ) {
			onPage?.( next.page );
		}
	};

	const actions =
		bulkActions.length > 0 && onBulk
			? bulkActions.map( ( b ) => ( {
					id: String( b.value ),
					label: b.label,
					supportsBulk: true,
					callback: ( items ) =>
						onBulk(
							b.value,
							items.map( ( i ) => i[ rowKey ] )
						),
				} ) )
			: [];

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
			{ error ? (
				<ErrorState message={ error } onRetry={ onRetry } />
			) : (
				// The Kits look: DataViews' brand colour is ink (#141414, --ks-ink), not admin blue.
				<ThemeProvider color={ { primary: '#141414' } }>
					<DataViews
						data={ rows }
						fields={ fields }
						view={ dvView }
						onChangeView={ onChangeView }
						actions={ actions }
						paginationInfo={ {
							totalItems: total,
							totalPages: pages,
						} }
						getItemId={ ( r ) => String( r[ rowKey ] ) }
						isItemClickable={ () => false }
						selection={ picked }
						onChangeSelection={ setPicked }
						isLoading={ !! loading }
						search={ !! onSearch && ! searchOnEnter }
						searchLabel={
							searchLabel || __( 'Search', 'kits-core' )
						}
						defaultLayouts={ { table: {} } }
						config={ { perPageSizes: [] } }
						empty={
							<EmptyState
								title={
									emptyTitle ||
									__( 'Nothing here.', 'kits-core' )
								}
							>
								{ empty }
							</EmptyState>
						}
					>
						{ ( onSearch || filters.length > 0 ) && (
							<div className="kits-shell-listbar">
								{ onSearch && searchOnEnter && (
									<form
										className="kits-shell-search"
										role="search"
										onSubmit={ ( e ) => {
											e.preventDefault();
											onSearch( words.trim() );
										} }
									>
										<SearchControl
											__nextHasNoMarginBottom
											label={
												searchLabel ||
												__( 'Search', 'kits-core' )
											}
											placeholder={
												searchLabel ||
												__( 'Search', 'kits-core' )
											}
											value={ words }
											onChange={ setWords }
										/>
									</form>
								) }
								{ onSearch && ! searchOnEnter && (
									<DataViews.Search
										label={
											searchLabel ||
											__( 'Search', 'kits-core' )
										}
									/>
								) }
								{ filters.length > 0 && (
									<DataViews.FiltersToggle />
								) }
							</div>
						) }
						{ filters.length > 0 && <DataViews.FiltersToggled /> }
						<div
							className="kits-shell-dvlayout"
							aria-busy={ loading || undefined }
							role="region"
							aria-label={ label }
						>
							<DataViews.Layout />
						</div>
						{ total > 0 && (
							<div className="kits-shell-pager">
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
								{ pages > 1 && <DataViews.Pagination /> }
							</div>
						) }
					</DataViews>
				</ThemeProvider>
			) }
			{ openView && (
				<Modal
					title={
						'object' === typeof openRow[ primary?.key ]
							? label
							: String( openRow[ primary?.key ] ?? label )
					}
					onRequestClose={ () => setOpen( null ) }
					size="medium"
				>
					{ openView }
				</Modal>
			) }
		</div>
	);
}

/**
 * A DataList filter as a DataViews field's filter: its options (less the '' "all" one) as elements, one "is" choice,
 * always shown.
 *
 * @param {Object} f { label, options: [ { value, label } ] }.
 * @return {Object} elements and filterBy for the field.
 */
function filterOf( f ) {
	return {
		elements: ( f.options || [] )
			.filter( ( o ) => '' !== String( o.value ) )
			.map( ( o ) => ( { value: o.value, label: o.label } ) ),
		filterBy: { operators: [ 'is' ], isPrimary: true },
	};
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
