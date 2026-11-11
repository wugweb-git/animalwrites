/**
 * Kits admin shell: the options panel (docs/kits-plugin-ui.md), for Kits Core's own settings screens.
 *
 * The pattern of a theme options panel: a dark bar of tabs across the top, a list of the open tab's sections on the
 * left, the section's settings in groups that fold open and shut, and On/Off toggles. The look is Kits: the bar is the
 * darkest grey, the open tab carries the one yellow, everything else is white and greys.
 */
import { useId, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const cx = ( ...names ) => names.filter( Boolean ).join( ' ' );

/**
 * The panel: dark tab bar, section list, content.
 *
 * @param {Object}   props
 * @param {string}   props.title     The panel's name, at the left of the bar.
 * @param {string}   props.icon      Icon URL.
 * @param {Array}    props.tabs      [ { slug, label, url, current } ]: links (other screens).
 * @param {Array}    props.sections  [ { slug, label, note } ]: the open tab's sections.
 * @param {string}   props.section   The open section.
 * @param {Function} props.onSection Called with a section's slug.
 * @param {Element}  props.aside     Right of the bar (Save, a link to the current screen).
 * @param {Element}  props.children  The open section.
 */
export function Panel( {
	title,
	icon,
	tabs = [],
	sections = [],
	section,
	onSection,
	aside,
	children,
} ) {
	return (
		<div className="kits-shell kits-shell-panel">
			<header className="kits-shell-panel__bar">
				<div className="kits-shell-panel__brand">
					{ icon && (
						<img src={ icon } alt="" width="24" height="24" />
					) }
					<span>{ title }</span>
				</div>
				{ tabs.length > 0 && (
					<nav
						className="kits-shell-panel__tabs"
						aria-label={ __( 'Screens', 'kits-core' ) }
					>
						<ul>
							{ tabs.map( ( t ) => (
								<li key={ t.slug }>
									<a
										href={ t.url }
										className={
											t.current ? 'is-current' : undefined
										}
										aria-current={
											t.current ? 'page' : undefined
										}
									>
										{ t.label }
									</a>
								</li>
							) ) }
						</ul>
					</nav>
				) }
				{ aside && (
					<div className="kits-shell-panel__aside">{ aside }</div>
				) }
			</header>
			<div className="kits-shell-panel__main">
				{ sections.length > 0 && (
					<nav
						className="kits-shell-panel__nav"
						aria-label={ __( 'Sections', 'kits-core' ) }
					>
						<ul>
							{ sections.map( ( s ) => (
								<li key={ s.slug }>
									<button
										type="button"
										className={ cx(
											s.slug === section && 'is-on'
										) }
										aria-current={
											s.slug === section
												? 'true'
												: undefined
										}
										onClick={ () => onSection?.( s.slug ) }
									>
										{ s.label }
										{ s.note && (
											<span className="kits-shell-panel__note">
												{ s.note }
											</span>
										) }
									</button>
								</li>
							) ) }
						</ul>
					</nav>
				) }
				<div className="kits-shell-panel__content">{ children }</div>
			</div>
		</div>
	);
}

/**
 * A group of settings that folds open and shut (a button with aria-expanded heads it).
 *
 * @param {Object}  props
 * @param {string}  props.title       Heading (H3).
 * @param {string}  props.description One line, shown when open.
 * @param {string}  props.summary     One line, shown when shut ("3 of 7 on").
 * @param {boolean} props.defaultOpen Open at first (default true).
 * @param {Element} props.children    Settings.
 */
export function PanelGroup( {
	title,
	description,
	summary,
	defaultOpen = true,
	children,
} ) {
	const [ open, setOpen ] = useState( defaultOpen );
	const id = useId();
	return (
		<section className={ cx( 'kits-shell-pgroup', open && 'is-open' ) }>
			<h3 className="kits-shell-pgroup__head">
				<button
					type="button"
					aria-expanded={ open }
					aria-controls={ id }
					onClick={ () => setOpen( ! open ) }
				>
					<span className="kits-shell-pgroup__title">{ title }</span>
					{ ! open && summary && (
						<span className="kits-shell-pgroup__summary">
							{ summary }
						</span>
					) }
					<span
						className="kits-shell-pgroup__chev"
						aria-hidden="true"
					/>
				</button>
			</h3>
			<div
				className="kits-shell-pgroup__body"
				id={ id }
				hidden={ ! open }
			>
				{ description && (
					<p className="kits-shell-pgroup__desc">{ description }</p>
				) }
				{ children }
			</div>
		</section>
	);
}

/**
 * An On/Off toggle: two buttons side by side, the chosen one filled (a radio group, so arrow keys and screen
 * readers treat it as one choice).
 *
 * @param {Object}   props
 * @param {string}   props.label    Visible label.
 * @param {boolean}  props.checked  On.
 * @param {Function} props.onChange Called with the new state.
 * @param {string}   props.help     A line under it.
 * @param {boolean}  props.changed  Differs from the default.
 * @param {Function} props.onReset  Puts the default back.
 * @param {boolean}  props.disabled Read only.
 * @param {string}   props.onLabel  Words of On (default On).
 * @param {string}   props.offLabel Words of Off (default Off).
 */
export function OnOff( {
	label,
	checked,
	onChange,
	help,
	changed,
	onReset,
	disabled,
	onLabel,
	offLabel,
} ) {
	const id = useId();
	const name = `kits-onoff-${ id }`;
	const pick = ( v ) => ! disabled && v !== !! checked && onChange?.( v );
	return (
		<div className="kits-shell-onoff-field">
			<div className="kits-shell-onoff-field__row">
				<span className="kits-shell-field__label" id={ `${ id }-l` }>
					{ label }
				</span>
				<div
					className="kits-shell-onoff"
					role="radiogroup"
					aria-labelledby={ `${ id }-l` }
					aria-describedby={ help ? `${ id }-h` : undefined }
				>
					{ [
						[ true, onLabel || __( 'On', 'kits-core' ) ],
						[ false, offLabel || __( 'Off', 'kits-core' ) ],
					].map( ( [ v, words ] ) => (
						<label
							key={ String( v ) }
							className={ cx(
								'kits-shell-onoff__opt',
								v === !! checked && 'is-on'
							) }
							htmlFor={ `${ id }-${ v ? 'on' : 'off' }` }
						>
							<input
								id={ `${ id }-${ v ? 'on' : 'off' }` }
								type="radio"
								name={ name }
								checked={ v === !! checked }
								disabled={ disabled }
								onChange={ () => pick( v ) }
							/>
							<span>{ words }</span>
						</label>
					) ) }
				</div>
			</div>
			{ ( help || changed ) && (
				<p className="kits-shell-field__help" id={ `${ id }-h` }>
					{ help }
					{ changed && (
						<span className="kits-shell-field__changed">
							{ ' ' }
							{ __( 'Changed from the default.', 'kits-core' ) }
						</span>
					) }
					{ changed && onReset && (
						<>
							{ ' ' }
							<button
								type="button"
								className="kits-shell-link"
								onClick={ onReset }
							>
								{ __( 'Reset to default', 'kits-core' ) }
							</button>
						</>
					) }
				</p>
			) }
		</div>
	);
}
