/**
 * Kits admin shell: smaller parts (docs/kits-plugin-ui.md): a bar chart, a write-only list, a setting that lives in
 * another plugin, and the tile picker ("Select metrics").
 */
import { useId, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * A small bar chart (one series over time) with the same numbers as a table for screen readers. Bars are greys; the
 * last bar is the darkest (today). No colour carries meaning.
 *
 * @param {Object} props
 * @param {string} props.label  What the chart shows (its caption).
 * @param {Array}  props.points [ { label, value } ], oldest first.
 * @param {string} props.unit   Words after a number in the table ("rows").
 */
export function MiniChart( { label, points = [], unit = '' } ) {
	const max = Math.max( 1, ...points.map( ( p ) => Number( p.value ) || 0 ) );
	const w = 100 / Math.max( 1, points.length );
	return (
		<figure className="kits-shell-chart">
			<svg
				viewBox="0 0 100 40"
				preserveAspectRatio="none"
				aria-hidden="true"
				focusable="false"
			>
				{ points.map( ( p, i ) => {
					const h = Math.max(
						0.5,
						( ( Number( p.value ) || 0 ) / max ) * 38
					);
					return (
						<rect
							key={ i }
							x={ i * w + w * 0.15 }
							y={ 40 - h }
							width={ w * 0.7 }
							height={ h }
							className={
								i === points.length - 1 ? 'is-last' : undefined
							}
						/>
					);
				} ) }
			</svg>
			<figcaption className="kits-shell-chart__caption">
				{ label }
				{ points.length > 0 && (
					<span className="kits-shell-chart__range">
						{ ' ' }
						{ sprintf(
							/* translators: 1: first label, 2: last label. */
							__( '(%1$s to %2$s)', 'kits-core' ),
							points[ 0 ].label,
							points[ points.length - 1 ].label
						) }
					</span>
				) }
			</figcaption>
			<table className="screen-reader-text">
				<caption>{ label }</caption>
				<tbody>
					{ points.map( ( p, i ) => (
						<tr key={ i }>
							<th scope="row">{ p.label }</th>
							<td>
								{ p.value } { unit }
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
		</figure>
	);
}

/**
 * A write-only list: values are sent once, kept only as a fingerprint, and never shown again (a list of phone numbers
 * that must never be published). Rows show what the server says about each (when it was added), with Remove.
 *
 * @param {Object}   props
 * @param {string}   props.label    Visible label of the add box.
 * @param {string}   props.help     Line under it.
 * @param {Array}    props.items    [ { id, note } ] (note: "Added 3 Nov 2026").
 * @param {Function} props.onAdd    Called with the typed value; returns a promise.
 * @param {Function} props.onRemove Called with an id.
 * @param {boolean}  props.busy     Saving.
 */
export function HashList( { label, help, items = [], onAdd, onRemove, busy } ) {
	const [ value, setValue ] = useState( '' );
	const id = useId();
	return (
		<div className="kits-shell-hashlist">
			<label className="kits-shell-field__label" htmlFor={ id }>
				{ label }
			</label>
			<div className="kits-shell-hashlist__add">
				<input
					id={ id }
					type="text"
					autoComplete="off"
					value={ value }
					aria-describedby={ `${ id }-h` }
					onChange={ ( e ) => setValue( e.target.value ) }
				/>
				<button
					type="button"
					className="kits-shell-button is-secondary"
					disabled={ busy || '' === value.trim() }
					onClick={ () =>
						Promise.resolve( onAdd?.( value.trim() ) ).then( () =>
							setValue( '' )
						)
					}
				>
					{ __( 'Add', 'kits-core' ) }
				</button>
			</div>
			<p className="kits-shell-field__help" id={ `${ id }-h` }>
				{ help ||
					__(
						'Kept only as a fingerprint: once added, it is never shown again.',
						'kits-core'
					) }
			</p>
			<p className="kits-shell-hashlist__count">
				{ sprintf(
					/* translators: %d: number of entries. */
					__( '%d kept', 'kits-core' ),
					items.length
				) }
			</p>
			{ items.length > 0 && (
				<ul className="kits-shell-hashlist__items">
					{ items.map( ( it, i ) => (
						<li key={ it.id }>
							<span>
								{ sprintf(
									/* translators: %d: entry number. */
									__( 'Entry %d', 'kits-core' ),
									i + 1
								) }
								{ it.note && (
									<span className="kits-shell-hashlist__note">
										{ ' · ' }
										{ it.note }
									</span>
								) }
							</span>
							{ onRemove && (
								<button
									type="button"
									className="kits-shell-link is-danger"
									disabled={ busy }
									onClick={ () => onRemove( it.id ) }
								>
									{ __( 'Remove', 'kits-core' ) }
								</button>
							) }
						</li>
					) ) }
				</ul>
			) }
		</div>
	);
}

/**
 * A setting kept by another Kits plugin, shown read-only with a link to where it is changed.
 *
 * @param {Object} props
 * @param {string} props.label  The setting's name.
 * @param {string} props.value  Its value, in words.
 * @param {string} props.plugin The plugin that keeps it ("RankWeb").
 * @param {string} props.href   Its settings screen; '' when the plugin is not active.
 */
export function LinkedSetting( { label, value, plugin, href } ) {
	return (
		<div className="kits-shell-linked">
			<p className="kits-shell-field__label">{ label }</p>
			<p className="kits-shell-linked__value">{ value }</p>
			<p className="kits-shell-field__help">
				{ href ? (
					<a href={ href }>
						{ sprintf(
							/* translators: %s: plugin name. */
							__( 'Kept in %s: change it there', 'kits-core' ),
							plugin
						) }
					</a>
				) : (
					sprintf(
						/* translators: %s: plugin name. */
						__( 'Kept in %s, which is not active.', 'kits-core' ),
						plugin
					)
				) }
			</p>
		</div>
	);
}

/**
 * "Select metrics": which tiles the Dashboard shows, as a fold-out list of tick boxes. The screen saves the choice
 * (per user) and passes it back as `value`.
 *
 * @param {Object}   props
 * @param {Array}    props.options  [ { value, label } ].
 * @param {Array}    props.value    The ticked values.
 * @param {Function} props.onChange Called with the new list when Save is pressed.
 * @param {boolean}  props.busy     Saving.
 */
export function MetricPicker( { options = [], value = [], onChange, busy } ) {
	const [ open, setOpen ] = useState( false );
	const [ picked, setPicked ] = useState( value );
	const id = useId();
	return (
		<div className="kits-shell-picker">
			<button
				type="button"
				className="kits-shell-button is-secondary"
				aria-expanded={ open }
				aria-controls={ id }
				onClick={ () => {
					setPicked( value );
					setOpen( ! open );
				} }
			>
				{ __( 'Select metrics', 'kits-core' ) }
			</button>
			<div
				className="kits-shell-picker__panel"
				id={ id }
				hidden={ ! open }
			>
				<fieldset>
					<legend className="screen-reader-text">
						{ __( 'Tiles to show', 'kits-core' ) }
					</legend>
					{ options.map( ( o ) => (
						<label
							key={ o.value }
							className="kits-shell-picker__opt"
							htmlFor={ `${ id }-${ o.value }` }
						>
							<input
								id={ `${ id }-${ o.value }` }
								type="checkbox"
								checked={ picked.includes( o.value ) }
								onChange={ ( e ) =>
									setPicked( ( p ) =>
										e.target.checked
											? [ ...p, o.value ]
											: p.filter( ( x ) => x !== o.value )
									)
								}
							/>
							{ o.label }
						</label>
					) ) }
				</fieldset>
				<div className="kits-shell-picker__foot">
					<button
						type="button"
						className="kits-shell-button is-primary"
						aria-busy={ busy || undefined }
						disabled={ busy || 0 === picked.length }
						onClick={ () => {
							onChange?.( picked );
							setOpen( false );
						} }
					>
						{ __( 'Save', 'kits-core' ) }
					</button>
					<button
						type="button"
						className="kits-shell-link"
						onClick={ () => setOpen( false ) }
					>
						{ __( 'Cancel', 'kits-core' ) }
					</button>
				</div>
			</div>
		</div>
	);
}
