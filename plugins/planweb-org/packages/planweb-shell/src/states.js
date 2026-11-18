/**
 * Kits admin shell: the empty, loading and error states (docs/kits-plugin-ui.md), shared by every part that loads.
 */
import { __ } from '@wordpress/i18n';
import { Spinner } from '@wordpress/components';

/**
 * Nothing to show yet.
 *
 * @param {Object}  props
 * @param {string}  props.title    One line.
 * @param {Element} props.children More words or an action.
 */
export function EmptyState( { title, children } ) {
	return (
		<div className="kits-shell-state is-empty">
			<p className="kits-shell-state__title">{ title }</p>
			{ children }
		</div>
	);
}

/**
 * Loading.
 *
 * @param {Object} props
 * @param {string} props.label Words beside the spinner.
 */
export function LoadingState( { label } ) {
	return (
		<div className="kits-shell-state is-loading" role="status">
			<Spinner className="kits-shell-spinner" />
			<p className="kits-shell-state__title">
				{ label || __( 'Loading…', 'kits-core' ) }
			</p>
		</div>
	);
}

/**
 * Something failed: the message and a Try again button.
 *
 * @param {Object}   props
 * @param {string}   props.message What went wrong.
 * @param {Function} props.onRetry Try again.
 */
export function ErrorState( { message, onRetry } ) {
	return (
		<div className="kits-shell-state is-error" role="alert">
			<p className="kits-shell-state__title">
				{ __( 'This could not be loaded.', 'kits-core' ) }
			</p>
			{ message && <p>{ message }</p> }
			{ onRetry && (
				<button
					type="button"
					className="kits-shell-button is-secondary"
					onClick={ onRetry }
				>
					{ __( 'Try again', 'kits-core' ) }
				</button>
			) }
		</div>
	);
}
