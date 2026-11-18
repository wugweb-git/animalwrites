/**
 * Kits admin shell: the setup flow (docs/kits-plugin-ui.md). A plugin's first run, or a service's connection, as
 * numbered steps: the list of steps with what is done, one step's card, and Back / Continue under it.
 */
import { __, sprintf } from '@wordpress/i18n';

const cx = ( ...names ) => names.filter( Boolean ).join( ' ' );

/**
 * @param {Object}   props
 * @param {string}   props.title       Heading (H2) ("Set up SourceWeb").
 * @param {string}   props.description One line under it.
 * @param {Array}    props.steps       [ { slug, label, done } ].
 * @param {string}   props.current     The open step's slug.
 * @param {Function} props.onStep      Called with a step's slug (from the list, Back or Continue).
 * @param {boolean}  props.canContinue The open step is ready (default true).
 * @param {boolean}  props.busy        Saving the step.
 * @param {Function} props.onFinish    Called on the last step's button instead of onStep.
 * @param {string}   props.finishLabel The last button's words (default Finish).
 * @param {Element}  props.aside       A line beside the buttons ("You can change this later in Settings.").
 * @param {Element}  props.children    The open step.
 */
export function SetupFlow( {
	title,
	description,
	steps = [],
	current,
	onStep,
	canContinue = true,
	busy,
	onFinish,
	finishLabel,
	aside,
	children,
} ) {
	const at = Math.max(
		0,
		steps.findIndex( ( s ) => s.slug === current )
	);
	const last = at === steps.length - 1;
	const done = steps.filter( ( s ) => s.done ).length;
	return (
		<section className="kits-shell-card kits-shell-setup">
			<div className="kits-shell-card__head">
				<div>
					<h2 className="kits-shell-card__title">{ title }</h2>
					{ description && (
						<p className="kits-shell-card__desc">{ description }</p>
					) }
				</div>
				<p className="kits-shell-setup__progress">
					{ sprintf(
						/* translators: 1: steps done, 2: all steps. */
						__( '%1$d of %2$d done', 'kits-core' ),
						done,
						steps.length
					) }
				</p>
			</div>
			<ol className="kits-shell-steps">
				{ steps.map( ( s, i ) => {
					const on = i === at;
					// A step can be opened once the one before it is done (or it is done itself).
					const open =
						on || s.done || ( i > 0 && steps[ i - 1 ].done );
					return (
						<li
							key={ s.slug }
							className={ cx(
								'kits-shell-step',
								on && 'is-on',
								s.done && 'is-done'
							) }
						>
							<button
								type="button"
								aria-current={ on ? 'step' : undefined }
								disabled={ ! open || busy }
								onClick={ () => onStep?.( s.slug ) }
							>
								<span
									className="kits-shell-step__mark"
									aria-hidden="true"
								>
									{ s.done ? '✓' : i + 1 }
								</span>
								<span className="kits-shell-step__label">
									{ s.label }
									{ s.done && (
										<span className="screen-reader-text">
											{ ' ' }
											{ __( '(done)', 'kits-core' ) }
										</span>
									) }
								</span>
							</button>
						</li>
					);
				} ) }
			</ol>
			<div className="kits-shell-card__body kits-shell-setup__body">
				{ children }
			</div>
			<div className="kits-shell-group__foot kits-shell-setup__foot">
				{ at > 0 && (
					<button
						type="button"
						className="kits-shell-button is-secondary"
						disabled={ busy }
						onClick={ () => onStep?.( steps[ at - 1 ].slug ) }
					>
						{ __( 'Back', 'kits-core' ) }
					</button>
				) }
				<button
					type="button"
					className="kits-shell-button is-primary"
					aria-busy={ busy || undefined }
					disabled={ busy || ! canContinue }
					onClick={ () =>
						last ? onFinish?.() : onStep?.( steps[ at + 1 ].slug )
					}
				>
					{ last
						? finishLabel || __( 'Finish', 'kits-core' )
						: __( 'Continue', 'kits-core' ) }
				</button>
				{ aside && (
					<p className="kits-shell-setup__aside">{ aside }</p>
				) }
			</div>
		</section>
	);
}
