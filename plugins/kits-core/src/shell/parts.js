/**
 * Kits admin shell: page parts (12 Nov 2026). A classic screen's output, handed over by kits_shell_html_parts()
 * (includes/admin-shell-parts.php) as plain parts, drawn with the shell's parts: text, notices, tables, its list table
 * as a DataList and its forms with their fields and buttons. Every change is the same link or form request the
 * classic screen sends (its own address, method and nonce), so the classic handlers keep doing the saving.
 */
import { __ } from '@wordpress/i18n';
import { DataList } from './list';
import { Field, Notice, RefTable } from './index';

/**
 * A text with its link, if any.
 *
 * @param {Object} props
 * @param {Object} props.cell { text, href, external }.
 */
export function Cell( { cell } ) {
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
 * Plain parts: paragraphs, notes, headings, lists, tables, notices.
 *
 * @param {Object} props
 * @param {Array}  props.blocks Parts (kits_shell_html_blocks()).
 * @param {string} props.title  Table captions.
 */
export function BlockParts( { blocks, title } ) {
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

/**
 * Open this page's address with some query arguments changed (a view, a search, a filter, a page).
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
 * Send what the page's own form sends (its hidden fields and nonce, plus the choice), so its own handler gets it.
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
 * A page's list (its WP_List_Table): views, search, filters, sort, pages, bulk and row actions. Every change opens the
 * page's own address or sends its own form, as the classic screen does.
 *
 * @param {Object} props
 * @param {Object} props.part  The listtable part.
 * @param {string} props.title The list's name.
 */
function PageList( { part, title } ) {
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
			label={ title }
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
			emptyTitle={ part.empty || __( 'Nothing here.', 'kits-core' ) }
		/>
	);
}

/**
 * An id for a tick box or choice of a page's form.
 *
 * @param {string} name  The field's name.
 * @param {string} value Its value.
 */
const fieldId = ( name, value ) =>
	`iw-${ name }-${ value }`.replace( /[^A-Za-z0-9_-]/g, '-' );

/**
 * One field of a page's form, as a plain control the form sends as it is.
 *
 * @param {Object} props
 * @param {Object} props.f The field.
 */
function PageField( { f } ) {
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
 * Any other form of a page: its words, fields and buttons, sent to the same address with the same nonce.
 *
 * @param {Object} props
 * @param {Object} props.part  The form part.
 * @param {string} props.title Table captions.
 */
function PageForm( { part, title } ) {
	return (
		<form method={ part.method } action={ part.action || undefined }>
			{ part.hidden.map( ( [ name, value ], i ) => (
				<input key={ i } type="hidden" name={ name } value={ value } />
			) ) }
			<BlockParts blocks={ part.blocks } title={ title } />
			{ part.fields.map( ( f, i ) => (
				<PageField key={ `${ f.name }-${ i }` } f={ f } />
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

/**
 * A classic page's parts, in order.
 *
 * @param {Object} props
 * @param {Array}  props.parts Parts (kits_shell_html_parts()).
 * @param {string} props.title The page's name (list and table captions).
 */
export function PageParts( { parts = [], title } ) {
	return parts.map( ( p, i ) => {
		if ( 'listtable' === p.type ) {
			return <PageList key={ i } part={ p } title={ title } />;
		}
		if ( 'form' === p.type ) {
			return <PageForm key={ i } part={ p } title={ title } />;
		}
		return <BlockParts key={ i } blocks={ [ p ] } title={ title } />;
	} );
}
