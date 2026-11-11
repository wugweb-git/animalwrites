/**
 * Kits admin shell: the parts every plugin screen is built from (docs/kits-plugin-ui.md).
 *
 * The pattern follows a site-tools dashboard: an app header with the plugin's icon, name and tabs; on a dashboard a
 * row of chips (time range), a grid of tiles (one big number, one sub-line, a link) and section cards under them; on
 * a settings screen groups as cards and connected services as rows with a status and an Edit button. The look is
 * Kits (black, white and greys, one yellow accent); WordPress packages are plumbing only.
 *
 * Built into build/shell/ as the script handle `kits-shell`, which sets window.kits.shell. A plugin's own build maps
 * the import `@kits/shell` to that global (see the plugin's webpack.config.js) and lists `kits-shell` as a dependency;
 * kits_shell_app() in includes/admin-shell.php enqueues both.
 */
import { useEffect, useId, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { DataList, FieldReview, RowAction } from './list';
import { SetupFlow } from './setup';
import { EmptyState, LoadingState, ErrorState } from './states';
import { Panel, PanelGroup, OnOff } from './panel';
import { MiniChart, HashList, LinkedSetting, MetricPicker } from './extras';
import './shell.css';
import './more.css';

export {
	DataList,
	FieldReview,
	RowAction,
	SetupFlow,
	Panel,
	PanelGroup,
	OnOff,
	MiniChart,
	HashList,
	LinkedSetting,
	MetricPicker,
};

const cx = ( ...names ) => names.filter( Boolean ).join( ' ' );

/**
 * The whole screen: header on top, content under it.
 *
 * @param {Object}  props
 * @param {Element} props.header   An AppHeader.
 * @param {Element} props.children The screen.
 */
export function AppShell( { header, children } ) {
	return (
		<div className="kits-shell">
			{ header }
			<div className="kits-shell__body">{ children }</div>
		</div>
	);
}

/**
 * App header: icon, name, the plugin's tabs (a real list, aria-current on the open one) and a slot on the right.
 *
 * @param {Object}  props
 * @param {string}  props.icon    Icon URL (SVG).
 * @param {string}  props.name    Plugin name.
 * @param {string}  props.version Version shown beside the name.
 * @param {Array}   props.tabs    [ { slug, label, url, current } ].
 * @param {Element} props.aside   Right-hand slot (a link back to the current screens, a Save button).
 */
export function AppHeader( { icon, name, version, tabs = [], aside } ) {
	return (
		<header className="kits-shell-header">
			<div className="kits-shell-header__brand">
				{ icon && (
					<img
						className="kits-shell-header__icon"
						src={ icon }
						alt=""
						width="32"
						height="32"
					/>
				) }
				<span className="kits-shell-header__name">{ name }</span>
				{ version && (
					<span className="kits-shell-header__version">
						{ version }
					</span>
				) }
			</div>
			{ tabs.length > 0 && (
				<nav
					className="kits-shell-header__tabs"
					aria-label={ __( 'Screens', 'kits-core' ) }
				>
					<ul>
						{ tabs.map( ( t ) => (
							<li key={ t.slug }>
								<a
									href={ t.url }
									className={ cx(
										'kits-shell-tab',
										t.current && 'is-current'
									) }
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
				<div className="kits-shell-header__aside">{ aside }</div>
			) }
		</header>
	);
}

/**
 * A row of chips: one choice of several (a time range), or links (a settings group list).
 * With onChange the chips are toggle buttons (aria-pressed); with an href per chip they are links (aria-current).
 *
 * @param {Object}   props
 * @param {string}   props.label    What the row chooses (read by screen readers).
 * @param {Array}    props.chips    [ { value, label, href? } ].
 * @param {string}   props.value    The chosen value.
 * @param {Function} props.onChange Called with a chip's value.
 * @param {Element}  props.after    Text after the chips (a "Counted at" line).
 */
export function ChipRow( { label, chips = [], value, onChange, after } ) {
	const list = useRef( null );
	// On a phone the chips scroll sideways: bring the chosen one into view.
	useEffect( () => {
		const ul = list.current;
		const on = ul?.querySelector( '.is-on' );
		if ( ! on || ul.scrollWidth <= ul.clientWidth ) {
			return;
		}
		const left =
			on.getBoundingClientRect().left -
			ul.getBoundingClientRect().left +
			ul.scrollLeft;
		if (
			left < ul.scrollLeft ||
			left + on.offsetWidth > ul.scrollLeft + ul.clientWidth
		) {
			ul.scrollLeft = Math.max( 0, left - 16 );
		}
	}, [ value ] );
	return (
		<div className="kits-shell-chips">
			<ul aria-label={ label } ref={ list }>
				{ chips.map( ( c ) => {
					const on = c.value === value;
					return (
						<li key={ c.value }>
							{ c.href ? (
								<a
									className={ cx(
										'kits-shell-chip',
										on && 'is-on'
									) }
									href={ c.href }
									aria-current={ on ? 'true' : undefined }
									onClick={
										onChange
											? () => onChange( c.value )
											: undefined
									}
								>
									{ c.label }
								</a>
							) : (
								<button
									type="button"
									className={ cx(
										'kits-shell-chip',
										on && 'is-on'
									) }
									aria-pressed={ on }
									onClick={ () => onChange?.( c.value ) }
								>
									{ c.label }
								</button>
							) }
						</li>
					);
				} ) }
			</ul>
			{ after && <p className="kits-shell-chips__after">{ after }</p> }
		</div>
	);
}

/**
 * Tiles in a grid: four across on wide screens, two on tablets, one on phones.
 *
 * @param {Object}  props
 * @param {string}  props.label    The grid's name (a list for screen readers).
 * @param {Element} props.children Tiles.
 */
export function TileGrid( { label, children } ) {
	return (
		<ul className="kits-shell-tiles" aria-label={ label }>
			{ children }
		</ul>
	);
}

const toneLabel = () => ( {
	warn: __( 'Needs attention', 'kits-core' ),
	error: __( 'Not working', 'kits-core' ),
} );

/**
 * One tile: a label, one big number (a link when it has one), a change badge, one sub-line.
 *
 * @param {Object}  props
 * @param {string}  props.label   What is counted.
 * @param {string}  props.value   The number or word, already formatted. null while counting.
 * @param {string}  props.delta   A change since the chosen range ("+3 in 28 days"), or ''.
 * @param {string}  props.sub     The one sub-line.
 * @param {string}  props.href    Where the number goes.
 * @param {string}  props.tone    '' | 'warn' | 'error'.
 * @param {boolean} props.loading Show "Counting…" instead of the number.
 * @param {string}  props.missing The plugin the number comes from, when it is not active: the tile says
 *                                "{plugin} not active" in place of the number (or pass hide to leave the tile out).
 * @param {boolean} props.hide    Leave the tile out when its source is missing.
 */
export function Tile( {
	label,
	value,
	delta,
	sub,
	href,
	tone = '',
	loading,
	missing,
	hide,
} ) {
	if ( missing && hide ) {
		return null;
	}
	if ( missing ) {
		return (
			<li className="kits-shell-tile is-missing">
				<p className="kits-shell-tile__label">{ label }</p>
				<p className="kits-shell-tile__value is-missing">
					{ sprintf(
						/* translators: %s: plugin name. */
						__( '%s not active', 'kits-core' ),
						missing
					) }
				</p>
				{ sub && <p className="kits-shell-tile__sub">{ sub }</p> }
			</li>
		);
	}
	const tones = toneLabel();
	const number = loading ? (
		<span className="kits-shell-tile__counting">
			{ __( 'Counting…', 'kits-core' ) }
		</span>
	) : (
		value
	);
	return (
		<li className={ cx( 'kits-shell-tile', tone && `is-${ tone }` ) }>
			<p className="kits-shell-tile__label">
				{ label }
				{ tones[ tone ] && (
					<span className="kits-shell-tile__tone">
						{ tones[ tone ] }
					</span>
				) }
			</p>
			<p className="kits-shell-tile__value">
				{ href && ! loading ? <a href={ href }>{ number }</a> : number }
				{ delta && ! loading && (
					<span className="kits-shell-tile__delta">{ delta }</span>
				) }
			</p>
			{ sub && <p className="kits-shell-tile__sub">{ sub }</p> }
		</li>
	);
}

/**
 * A section card under the tiles (or anywhere): a heading, an optional line and actions, the content.
 *
 * @param {Object}  props
 * @param {string}  props.id          Anchor.
 * @param {string}  props.title       Heading (H2).
 * @param {string}  props.description One line under the heading.
 * @param {Element} props.actions     Buttons or links on the right of the heading.
 * @param {Element} props.children    Content.
 */
export function SectionCard( { id, title, description, actions, children } ) {
	return (
		<section className="kits-shell-card" id={ id }>
			<div className="kits-shell-card__head">
				<div>
					<h2 className="kits-shell-card__title">{ title }</h2>
					{ description && (
						<p className="kits-shell-card__desc">{ description }</p>
					) }
				</div>
				{ actions && (
					<div className="kits-shell-card__actions">{ actions }</div>
				) }
			</div>
			<div className="kits-shell-card__body">{ children }</div>
		</section>
	);
}

/**
 * A settings group as a card: heading, description, the fields, and a footer (Save, Restore defaults, a message).
 *
 * @param {Object}  props
 * @param {string}  props.id          Anchor (the URL keeps it: #titles).
 * @param {string}  props.title       Heading (H2).
 * @param {string}  props.description One line under the heading.
 * @param {Element} props.footer      Buttons and the save message.
 * @param {Element} props.children    Blocks and fields.
 */
export function SettingsGroup( { id, title, description, footer, children } ) {
	return (
		<section className="kits-shell-card kits-shell-group" id={ id }>
			<div className="kits-shell-card__head">
				<div>
					<h2 className="kits-shell-card__title">{ title }</h2>
					{ description && (
						<p className="kits-shell-card__desc">{ description }</p>
					) }
				</div>
			</div>
			<div className="kits-shell-card__body">{ children }</div>
			{ footer && (
				<div className="kits-shell-group__foot">{ footer }</div>
			) }
		</section>
	);
}

/**
 * A block inside a settings group (one module): heading (H3), a line, and its fields.
 *
 * @param {Object}  props
 * @param {string}  props.title       Heading.
 * @param {string}  props.description Line.
 * @param {string}  props.note        A second line in a quieter tone ("Off: switch it on under Modules.").
 * @param {Element} props.children    Fields.
 */
export function SettingsBlock( { title, description, note, children } ) {
	return (
		<div className="kits-shell-block">
			<h3 className="kits-shell-block__title">{ title }</h3>
			{ description && (
				<p className="kits-shell-block__desc">{ description }</p>
			) }
			{ note && <p className="kits-shell-block__note">{ note }</p> }
			{ children }
		</div>
	);
}

/**
 * A labelled field with its help line, "Changed from the default" and a Reset link.
 *
 * @param {Object}   props
 * @param {string}   props.label   Visible label.
 * @param {string}   props.help    Help line.
 * @param {boolean}  props.changed Differs from the default.
 * @param {Function} props.onReset Puts the default back (shown only when changed).
 * @param {Function} props.render  ( id, describedBy ) => the control.
 */
export function Field( { label, help, changed, onReset, render } ) {
	const id = useId();
	const helpId = `${ id }-help`;
	return (
		<div className="kits-shell-field">
			<label className="kits-shell-field__label" htmlFor={ id }>
				{ label }
			</label>
			{ render( id, helpId ) }
			<p className="kits-shell-field__help" id={ helpId }>
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
		</div>
	);
}

/**
 * An on/off switch with its label (a checkbox with role="switch").
 *
 * @param {Object}   props
 * @param {string}   props.label    Visible label.
 * @param {boolean}  props.checked  On.
 * @param {Function} props.onChange Called with the new state.
 * @param {boolean}  props.disabled Read only.
 * @param {string}   props.help     A line under it.
 */
export function Switch( { label, checked, onChange, disabled, help } ) {
	const id = useId();
	return (
		<div className="kits-shell-switch">
			<input
				id={ id }
				type="checkbox"
				role="switch"
				checked={ !! checked }
				disabled={ disabled }
				aria-describedby={ help ? `${ id }-help` : undefined }
				onChange={ ( e ) => onChange?.( e.target.checked ) }
			/>
			<label htmlFor={ id }>{ label }</label>
			{ help && (
				<p className="kits-shell-switch__help" id={ `${ id }-help` }>
					{ help }
				</p>
			) }
		</div>
	);
}

/**
 * A connected service as a row: icon, name, status, a one-line summary and an Edit button that opens its details.
 *
 * @param {Object}  props
 * @param {string}  props.icon      Icon URL.
 * @param {string}  props.name      Service name.
 * @param {string}  props.status    Status words ("Active, 1.0.282").
 * @param {string}  props.tone      '' (connected) | 'warn' | 'error'.
 * @param {string}  props.summary   One line.
 * @param {string}  props.editLabel The button's words (default Edit).
 * @param {Element} props.children  The details shown after Edit.
 */
export function ServiceRow( {
	icon,
	name,
	status,
	tone = '',
	summary,
	editLabel,
	children,
} ) {
	const [ open, setOpen ] = useState( false );
	const id = useId();
	return (
		<li className={ cx( 'kits-shell-service', open && 'is-open' ) }>
			<div className="kits-shell-service__row">
				{ icon ? (
					<img
						className="kits-shell-service__icon"
						src={ icon }
						alt=""
						width="32"
						height="32"
					/>
				) : (
					<span
						className="kits-shell-service__icon is-blank"
						aria-hidden="true"
					>
						{ String( name || '' ).charAt( 0 ) }
					</span>
				) }
				<div className="kits-shell-service__text">
					<p className="kits-shell-service__name">{ name }</p>
					{ summary && (
						<p className="kits-shell-service__summary">
							{ summary }
						</p>
					) }
				</div>
				<p
					className={ cx(
						'kits-shell-status',
						tone ? `is-${ tone }` : 'is-ok'
					) }
				>
					{ status }
				</p>
				{ children && (
					<button
						type="button"
						className="kits-shell-button is-secondary"
						aria-expanded={ open }
						aria-controls={ id }
						onClick={ () => setOpen( ! open ) }
					>
						{ open
							? __( 'Close', 'kits-core' )
							: editLabel || __( 'Edit', 'kits-core' ) }
					</button>
				) }
			</div>
			{ children && (
				<div
					className="kits-shell-service__details"
					id={ id }
					hidden={ ! open }
				>
					{ children }
				</div>
			) }
		</li>
	);
}

/**
 * The list ServiceRows go in.
 *
 * @param {Object}  props
 * @param {string}  props.label    The list's name.
 * @param {Element} props.children ServiceRows.
 */
export function ServiceList( { label, children } ) {
	return (
		<ul className="kits-shell-services" aria-label={ label }>
			{ children }
		</ul>
	);
}

/**
 * A notice on the screen (never on other admin screens): info, success, warning or error, dismissible.
 *
 * @param {Object}   props
 * @param {string}   props.tone      'info' | 'success' | 'warning' | 'error'.
 * @param {Function} props.onDismiss Shows a Dismiss button.
 * @param {Element}  props.children  The message.
 */
export function Notice( { tone = 'info', onDismiss, children } ) {
	return (
		<div
			className={ cx( 'kits-shell-notice', `is-${ tone }` ) }
			role={ 'error' === tone ? 'alert' : 'status' }
		>
			<div className="kits-shell-notice__text">{ children }</div>
			{ onDismiss && (
				<button
					type="button"
					className="kits-shell-link"
					onClick={ onDismiss }
				>
					{ __( 'Dismiss', 'kits-core' ) }
				</button>
			) }
		</div>
	);
}

/**
 * A button in the Kits look.
 *
 * @param {Object}  props
 * @param {string}  props.variant  'primary' (black) | 'secondary' (outlined) | 'link'.
 * @param {boolean} props.busy     Working: disabled, aria-busy.
 * @param {Object}  props.rest     Any other button attributes.
 * @param {Element} props.children Words.
 */
export function Button( { variant = 'secondary', busy, children, ...rest } ) {
	return (
		<button
			type="button"
			className={
				'link' === variant
					? 'kits-shell-link'
					: cx( 'kits-shell-button', `is-${ variant }` )
			}
			aria-busy={ busy || undefined }
			disabled={ busy || rest.disabled }
			{ ...rest }
		>
			{ children }
		</button>
	);
}

/**
 * A two-column reference table (read-only), as on a dashboard's "where things are" section.
 *
 * @param {Object} props
 * @param {string} props.caption Table caption (visually hidden).
 * @param {Array}  props.head    [ first column, second column ].
 * @param {Array}  props.rows    [ [ cell, cell ] ] (strings or elements).
 */
export function RefTable( { caption, head = [], rows = [] } ) {
	return (
		<table className="kits-shell-table">
			<caption className="screen-reader-text">{ caption }</caption>
			<thead>
				<tr>
					{ head.map( ( h ) => (
						<th key={ h } scope="col">
							{ h }
						</th>
					) ) }
				</tr>
			</thead>
			<tbody>
				{ rows.map( ( r, i ) => (
					<tr key={ i }>
						{ r.map( ( c, j ) =>
							0 === j ? (
								<th key={ j } scope="row">
									{ c }
								</th>
							) : (
								<td key={ j }>{ c }</td>
							)
						) }
					</tr>
				) ) }
			</tbody>
		</table>
	);
}

const shell = {
	AppShell,
	AppHeader,
	ChipRow,
	TileGrid,
	Tile,
	SectionCard,
	SettingsGroup,
	SettingsBlock,
	Field,
	Switch,
	ServiceList,
	ServiceRow,
	Notice,
	EmptyState,
	LoadingState,
	ErrorState,
	Button,
	RefTable,
	DataList,
	FieldReview,
	RowAction,
	SetupFlow,
	Panel,
	PanelGroup,
	OnOff,
	MiniChart,
	HashList,
	LinkedSetting,
	MetricPicker,
};

window.kits = window.kits || {};
window.kits.shell = shell;

export default shell;
