/**
 * Shell parts for reading and editing one item: a detail panel (label and value rows, read only), media fields (a
 * picture from the media library, and a row of thumbnails) and a pair-list editor (rows of two boxes, added and
 * removed in place).
 */
import { useEffect, useId, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button } from './index';

/**
 * A reading panel: a heading, rows of label and value, then anything else the screen adds. Rows with an empty value
 * show "—" so a missing value reads as missing, not as a layout gap.
 *
 * @param {Object}   props
 * @param {string}   props.title    Heading (H3).
 * @param {string}   props.subtitle A line under the heading.
 * @param {Array}    props.items    [ { label, value } ]: value is text or nodes.
 * @param {Element}  props.actions  Buttons or links at the foot.
 * @param {Function} props.onClose  Shows a Close button when given.
 * @param {Element}  props.children More content under the rows.
 */
export function DetailPanel( {
	title,
	subtitle,
	items = [],
	actions,
	onClose,
	children,
} ) {
	const id = useId();
	const empty = ( v ) =>
		null === v || undefined === v || '' === v || false === v;
	return (
		<section className="kits-shell-detail" aria-labelledby={ `${ id }-t` }>
			<header className="kits-shell-detail__head">
				<h3 id={ `${ id }-t` }>{ title }</h3>
				{ onClose && (
					<Button variant="link" onClick={ onClose }>
						{ __( 'Close', 'kits-core' ) }
					</Button>
				) }
			</header>
			{ subtitle && (
				<p className="kits-shell-detail__sub">{ subtitle }</p>
			) }
			{ items.length > 0 && (
				<dl className="kits-shell-detail__rows">
					{ items.map( ( it ) => (
						<div
							key={ it.label }
							className="kits-shell-detail__row"
						>
							<dt>{ it.label }</dt>
							<dd>{ empty( it.value ) ? '—' : it.value }</dd>
						</div>
					) ) }
				</dl>
			) }
			{ children }
			{ actions && (
				<p className="kits-shell-detail__foot">{ actions }</p>
			) }
		</section>
	);
}

/**
 * A row of thumbnails (read only), each opening the full picture in a new tab. Pictures from other sites are loaded
 * without a referrer.
 *
 * @param {Object} props
 * @param {Array}  props.urls  Picture URLs.
 * @param {number} props.max   How many to show (the rest are counted).
 * @param {number} props.size  Width and height in px (from the 48px thumbnail the classic screens use).
 * @param {string} props.label What the pictures are, for screen readers ("Logo now").
 */
export function MediaThumbs( { urls = [], max = 6, size = 48, label } ) {
	const list = urls.filter( ( u ) => 'string' === typeof u && u );
	if ( ! list.length ) {
		return null;
	}
	const more = list.length - max;
	return (
		<span className="kits-shell-thumbs" role="group" aria-label={ label }>
			{ list.slice( 0, max ).map( ( u, i ) => (
				<a
					key={ u }
					href={ u }
					target="_blank"
					rel="noopener noreferrer"
				>
					<img
						src={ u }
						width={ size }
						height={ size }
						loading="lazy"
						decoding="async"
						referrerPolicy="no-referrer"
						alt={ sprintf(
							/* translators: 1: what the pictures are, 2: its number. */
							__( '%1$s, picture %2$d', 'kits-core' ),
							label || __( 'Picture', 'kits-core' ),
							i + 1
						) }
					/>
				</a>
			) ) }
			{ more > 0 && (
				<span className="kits-shell-thumbs__more">
					{ sprintf(
						/* translators: %d: pictures not shown. */
						__( '+%d more', 'kits-core' ),
						more
					) }
				</span>
			) }
		</span>
	);
}

/**
 * A picture from the media library: the picture now, Choose (the WordPress media picker, when the screen loaded it
 * with wp_enqueue_media()) and Remove. Without the media picker the item's ID can be typed instead.
 *
 * @param {Object}   props
 * @param {string}   props.label    Visible label.
 * @param {string}   props.help     Help line.
 * @param {number}   props.value    The media library item's ID (0 = none).
 * @param {string}   props.url      Its thumbnail URL, or ''.
 * @param {Function} props.onChange ( id, url ) => void.
 * @param {string}   props.type     Library filter: 'image' (default).
 * @param {boolean}  props.disabled Read only.
 */
export function MediaField( {
	label,
	help,
	value = 0,
	url = '',
	onChange,
	type = 'image',
	disabled,
} ) {
	const id = useId();
	const frame = useRef( null );
	const picker = 'undefined' !== typeof window && window.wp?.media;
	useEffect(
		() => () => {
			if ( frame.current ) {
				frame.current.off( 'select' );
			}
		},
		[]
	);
	const choose = () => {
		if ( ! frame.current ) {
			frame.current = window.wp.media( {
				title: label,
				library: { type },
				multiple: false,
			} );
		}
		frame.current.off( 'select' );
		frame.current.on( 'select', () => {
			const a = frame.current.state().get( 'selection' ).first();
			if ( a ) {
				const j = a.toJSON();
				onChange(
					j.id,
					j.sizes?.thumbnail?.url || j.sizes?.full?.url || j.url || ''
				);
			}
		} );
		frame.current.open();
	};
	return (
		<div className="kits-shell-field kits-shell-media">
			<span className="kits-shell-field__label" id={ `${ id }-l` }>
				{ label }
			</span>
			<span className="kits-shell-media__row">
				<span className="kits-shell-media__preview">
					{ url ? (
						<img src={ url } alt="" width="48" height="48" />
					) : (
						<span className="kits-shell-media__none">
							{ value
								? sprintf(
										/* translators: %d: media library item ID. */
										__( 'Item %d', 'kits-core' ),
										value
									)
								: __( 'None', 'kits-core' ) }
						</span>
					) }
				</span>
				{ picker ? (
					<Button
						onClick={ choose }
						disabled={ disabled }
						aria-describedby={ `${ id }-l` }
					>
						{ value
							? __( 'Change', 'kits-core' )
							: __( 'Choose from Media', 'kits-core' ) }
					</Button>
				) : (
					<input
						type="number"
						min="0"
						value={ value }
						disabled={ disabled }
						aria-labelledby={ `${ id }-l` }
						onChange={ ( e ) =>
							onChange( parseInt( e.target.value, 10 ) || 0, '' )
						}
					/>
				) }
				{ value > 0 && (
					<Button
						variant="link"
						disabled={ disabled }
						onClick={ () => onChange( 0, '' ) }
					>
						{ __( 'Remove', 'kits-core' ) }
					</Button>
				) }
			</span>
			{ help && <p className="kits-shell-field__help">{ help }</p> }
		</div>
	);
}

/**
 * A list of pairs (a word and what it maps to, a host and its name): two boxes a row, Remove on each row, Add row
 * under the list. Rows left with both boxes empty are dropped on change.
 *
 * @param {Object}   props
 * @param {string}   props.label      Visible label (the list's name).
 * @param {string}   props.help       Help line.
 * @param {Array}    props.pairs      [ [ a, b ] ].
 * @param {Function} props.onChange   ( pairs ) => void.
 * @param {string}   props.keyLabel   First column's name.
 * @param {string}   props.valueLabel Second column's name.
 * @param {string}   props.addLabel   The add button's words.
 * @param {boolean}  props.disabled   Read only.
 */
export function PairList( {
	label,
	help,
	pairs = [],
	onChange,
	keyLabel,
	valueLabel,
	addLabel,
	disabled,
} ) {
	const id = useId();
	const set = ( i, j, v ) =>
		onChange(
			pairs.map( ( p, k ) => {
				if ( k !== i ) {
					return p;
				}
				const n = [ ...p ];
				n[ j ] = v;
				return n;
			} )
		);
	const drop = ( i ) => onChange( pairs.filter( ( _, k ) => k !== i ) );
	return (
		<fieldset className="kits-shell-field kits-shell-pairs">
			<legend className="kits-shell-field__label">{ label }</legend>
			{ help && (
				<p className="kits-shell-field__help" id={ `${ id }-h` }>
					{ help }
				</p>
			) }
			{ pairs.length > 0 && (
				<table className="kits-shell-pairs__table">
					<thead>
						<tr>
							<th scope="col">{ keyLabel }</th>
							<th scope="col">{ valueLabel }</th>
							<td />
						</tr>
					</thead>
					<tbody>
						{ pairs.map( ( p, i ) => (
							// Rows have no id of their own; the place in the list is the key.
							<tr key={ i }>
								<td>
									<input
										type="text"
										value={ p[ 0 ] ?? '' }
										disabled={ disabled }
										aria-label={ sprintf(
											/* translators: 1: column name, 2: row number. */
											__( '%1$s, row %2$d', 'kits-core' ),
											keyLabel,
											i + 1
										) }
										onChange={ ( e ) =>
											set( i, 0, e.target.value )
										}
									/>
								</td>
								<td>
									<input
										type="text"
										value={ p[ 1 ] ?? '' }
										disabled={ disabled }
										aria-label={ sprintf(
											/* translators: 1: column name, 2: row number. */
											__( '%1$s, row %2$d', 'kits-core' ),
											valueLabel,
											i + 1
										) }
										onChange={ ( e ) =>
											set( i, 1, e.target.value )
										}
									/>
								</td>
								<td>
									<Button
										variant="link"
										disabled={ disabled }
										onClick={ () => drop( i ) }
										aria-label={ sprintf(
											/* translators: %d: row number. */
											__( 'Remove row %d', 'kits-core' ),
											i + 1
										) }
									>
										{ __( 'Remove', 'kits-core' ) }
									</Button>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
			<Button
				disabled={ disabled }
				onClick={ () => onChange( [ ...pairs, [ '', '' ] ] ) }
			>
				{ addLabel || __( 'Add row', 'kits-core' ) }
			</Button>
		</fieldset>
	);
}
