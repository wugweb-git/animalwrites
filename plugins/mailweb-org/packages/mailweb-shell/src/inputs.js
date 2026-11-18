/**
 * Inputs the shell's screens share: a select (WordPress's SelectControl), a secret field (a key or password: never shown back, only
 * whether one is set and its last 4 characters) and a button that asks before a change that touches many items
 * (with the count, and a word to type when it cannot be undone).
 */
import { useEffect, useId, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { SelectControl } from '@wordpress/components';
import { Button } from './index';

/**
 * A select over a fixed list: WordPress's SelectControl. The label and help come from the Field around it, which
 * passes the id.
 *
 * @param {Object}   props
 * @param {string}   props.id       Control id (from Field's render).
 * @param {string}   props.value    Current value.
 * @param {Array}    props.options  [ { value, label, disabled } ].
 * @param {string}   props.none     Label of an empty first choice, or '' for none.
 * @param {Function} props.onChange ( value ) => void.
 */
export function Select( {
	id,
	value,
	options = [],
	none = '',
	onChange,
	...rest
} ) {
	const { 'aria-describedby': describedBy, ...others } = rest;
	// SelectControl sets aria-describedby from its own help prop only, after the props it is given; the Field's help
	// line is outside it, so the reference is put back on the element.
	const ref = useRef( null );
	useEffect( () => {
		if ( ! ref.current ) {
			return;
		}
		if ( describedBy ) {
			ref.current.setAttribute( 'aria-describedby', describedBy );
		} else {
			ref.current.removeAttribute( 'aria-describedby' );
		}
	} );
	return (
		<SelectControl
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			ref={ ref }
			id={ id }
			className="kits-shell-select"
			value={ value }
			options={ [
				...( none ? [ { value: '', label: none } ] : [] ),
				...options.map( ( o ) => ( {
					value: o.value,
					label: o.label,
					disabled: o.disabled,
				} ) ),
			] }
			onChange={ ( v ) => onChange( v ) }
			{ ...others }
		/>
	);
}

/**
 * A secret: the saved one is never sent to the screen. Shows whether one is set (and its last 4 characters when the
 * server gives them); typing a new one replaces it on Save, an empty box keeps the saved one.
 *
 * @param {Object}   props
 * @param {string}   props.label     Visible label.
 * @param {string}   props.help      Help line.
 * @param {boolean}  props.isSet     A secret is saved.
 * @param {string}   props.last4     Its last 4 characters, or ''.
 * @param {string}   props.value     What was typed (not the saved one).
 * @param {Function} props.onChange  ( value ) => void.
 * @param {boolean}  props.disabled  Read only.
 * @param {string}   props.stateText Words in place of the saved state (a secret fixed in wp-config.php).
 */
export function SecretField( {
	label,
	help,
	isSet,
	last4 = '',
	value,
	onChange,
	disabled,
	stateText,
} ) {
	const id = useId();
	const [ shown, setShown ] = useState( false );
	let state = __( 'None saved.', 'kits-core' );
	if ( isSet ) {
		state = last4
			? sprintf(
					/* translators: %s: last 4 characters of the saved secret. */
					__(
						'Saved, ends in …%s. Type a new one to replace it.',
						'kits-core'
					),
					last4
				)
			: __( 'Saved. Type a new one to replace it.', 'kits-core' );
	}
	if ( stateText ) {
		state = stateText;
	}
	return (
		<div className="kits-shell-field">
			<label className="kits-shell-field__label" htmlFor={ id }>
				{ label }
			</label>
			<span className="kits-shell-secret">
				<input
					id={ id }
					type={ shown ? 'text' : 'password' }
					value={ value }
					autoComplete="new-password"
					spellCheck={ false }
					disabled={ disabled }
					aria-describedby={ `${ id }-help` }
					onChange={ ( e ) => onChange( e.target.value ) }
				/>
				<Button
					variant="link"
					onClick={ () => setShown( ( s ) => ! s ) }
					aria-pressed={ shown }
					disabled={ disabled || ! value }
				>
					{ shown
						? __( 'Hide', 'kits-core' )
						: __( 'Show', 'kits-core' ) }
				</Button>
			</span>
			<p className="kits-shell-field__help" id={ `${ id }-help` }>
				{ state } { help }
			</p>
		</div>
	);
}

/**
 * A button that asks first, in place: what will happen to how many items, then Confirm and Cancel. With `word`, the
 * word must be typed before Confirm works (for what cannot be undone).
 *
 * @param {Object}   props
 * @param {string}   props.label     The button.
 * @param {string}   props.question  What will happen; %d is the count.
 * @param {number}   props.count     How many items.
 * @param {string}   props.word      A word to type first, or ''.
 * @param {string}   props.tone      'error' for a removal.
 * @param {boolean}  props.busy      Running.
 * @param {boolean}  props.disabled  Not now.
 * @param {Function} props.onConfirm () => void.
 */
export function ConfirmButton( {
	label,
	question,
	count = 0,
	word = '',
	tone,
	busy,
	disabled,
	onConfirm,
} ) {
	const id = useId();
	const [ asking, setAsking ] = useState( false );
	const [ typed, setTyped ] = useState( '' );
	const close = () => {
		setAsking( false );
		setTyped( '' );
	};
	if ( ! asking ) {
		return (
			<Button
				busy={ busy }
				disabled={ disabled || 0 === count }
				onClick={ () => setAsking( true ) }
			>
				{ label }
			</Button>
		);
	}
	return (
		<div
			className={ `kits-shell-confirm${
				'error' === tone ? ' is-error' : ''
			}` }
			role="group"
			aria-labelledby={ `${ id }-q` }
		>
			<p id={ `${ id }-q` }>
				<strong>{ sprintf( question, count ) }</strong>
			</p>
			{ word && (
				<p>
					<label htmlFor={ id }>
						{ sprintf(
							/* translators: %s: the word to type. */
							__( 'Type %s to confirm.', 'kits-core' ),
							word
						) }
					</label>{ ' ' }
					<input
						id={ id }
						type="text"
						value={ typed }
						autoComplete="off"
						onChange={ ( e ) => setTyped( e.target.value ) }
					/>
				</p>
			) }
			<p className="kits-shell-confirm__foot">
				<Button
					variant="primary"
					busy={ busy }
					disabled={ !! word && typed !== word }
					onClick={ () => {
						close();
						onConfirm();
					} }
				>
					{ __( 'Confirm', 'kits-core' ) }
				</Button>
				<Button onClick={ close }>
					{ __( 'Cancel', 'kits-core' ) }
				</Button>
			</p>
		</div>
	);
}
