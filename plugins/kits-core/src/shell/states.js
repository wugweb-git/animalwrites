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
 * Shimmering skeleton line.
 *
 * @param {Object} props
 * @param {string} [props.width='100%']
 * @param {string} [props.height='14px']
 * @param {string} [props.className='']
 */
export function SkeletonLine( { width = '100%', height = '14px', className = '' } ) {
	return (
		<div
			className={ `kits-skeleton kits-skeleton-line ${ className }`.trim() }
			style={ { width, height } }
			aria-hidden="true"
		/>
	);
}

/**
 * Shimmering skeleton row for tables and lists.
 *
 * @param {Object} props
 * @param {number} [props.columns=4]
 */
export function SkeletonRow( { columns = 4 } ) {
	const widths = [ '40%', '20%', '15%', '15%', '10%' ];
	return (
		<tr className="kits-skeleton-row" aria-hidden="true">
			{ Array.from( { length: columns } ).map( ( _, i ) => (
				<td key={ i } className="kits-skeleton-cell">
					<SkeletonLine width={ widths[ i % widths.length ] } height="16px" />
				</td>
			) ) }
		</tr>
	);
}

/**
 * Shimmering skeleton table.
 *
 * @param {Object} props
 * @param {number} [props.columns=4]
 * @param {number} [props.rows=5]
 * @param {string} [props.label]
 */
export function SkeletonTable( { columns = 4, rows = 5, label } ) {
	return (
		<div className="kits-skeleton-table-wrapper" role="status" aria-busy="true">
			<span className="screen-reader-text">
				{ label || __( 'Loading data…', 'kits-core' ) }
			</span>
			<table className="kits-skeleton-table" aria-hidden="true">
				<thead>
					<tr>
						{ Array.from( { length: columns } ).map( ( _, i ) => (
							<th key={ i }>
								<SkeletonLine width="60%" height="12px" />
							</th>
						) ) }
					</tr>
				</thead>
				<tbody>
					{ Array.from( { length: rows } ).map( ( _, i ) => (
						<SkeletonRow key={ i } columns={ columns } />
					) ) }
				</tbody>
			</table>
		</div>
	);
}

/**
 * Shimmering skeleton card.
 *
 * @param {Object} props
 * @param {number} [props.lines=3]
 */
export function SkeletonCard( { lines = 3 } ) {
	return (
		<div className="kits-skeleton-card" role="status" aria-busy="true">
			<SkeletonLine width="50%" height="20px" className="kits-skeleton-card__title" />
			<div className="kits-skeleton-card__body">
				{ Array.from( { length: lines } ).map( ( _, i ) => (
					<SkeletonLine
						key={ i }
						width={ i === lines - 1 ? '70%' : '100%' }
						height="12px"
					/>
				) ) }
			</div>
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
