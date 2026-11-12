/**
 * Calendar grid: one month, Monday first, as the PlanWeb Calendar draws it (planweb_month_grid()). Previous, next and
 * Today move the month; today's cell is marked (aria-current="date") and past days are greyed. Each day shows what
 * the screen hands it for that date (its own links or buttons). On phones the grid becomes a list of the days that
 * have something on them.
 */
import { __ } from '@wordpress/i18n';
import { Button } from './index';

const pad = ( n ) => String( n ).padStart( 2, '0' );

/**
 * A month as Y-m, moved by some months.
 *
 * @param {string} month Y-m.
 * @param {number} by    Months to move (may be negative).
 * @return {string} Y-m.
 */
export function shiftMonth( month, by ) {
	const [ y, m ] = month.split( '-' ).map( Number );
	const d = new Date( Date.UTC( y, m - 1 + by, 1 ) );
	return `${ d.getUTCFullYear() }-${ pad( d.getUTCMonth() + 1 ) }`;
}

/**
 * The weeks of a month: rows of seven Y-m-d dates, '' before the first and after the last day.
 *
 * @param {string} month Y-m.
 * @return {string[][]} Weeks.
 */
export function monthWeeks( month ) {
	const [ y, m ] = month.split( '-' ).map( Number );
	const days = new Date( Date.UTC( y, m, 0 ) ).getUTCDate();
	const lead = ( new Date( Date.UTC( y, m - 1, 1 ) ).getUTCDay() + 6 ) % 7; // Monday = 0
	const cells = Array( lead ).fill( '' );
	for ( let d = 1; d <= days; d++ ) {
		cells.push( `${ month }-${ pad( d ) }` );
	}
	while ( cells.length % 7 ) {
		cells.push( '' );
	}
	const weeks = [];
	for ( let i = 0; i < cells.length; i += 7 ) {
		weeks.push( cells.slice( i, i + 7 ) );
	}
	return weeks;
}

const locale = () => document.documentElement.lang || undefined;
const monthName = ( month, opts ) =>
	new Intl.DateTimeFormat( locale(), { ...opts, timeZone: 'UTC' } ).format(
		new Date( `${ month }-01T12:00:00Z` )
	);
const dayName = ( ymd ) =>
	new Intl.DateTimeFormat( locale(), {
		weekday: 'short',
		day: 'numeric',
		month: 'short',
		timeZone: 'UTC',
	} ).format( new Date( `${ ymd }T12:00:00Z` ) );

/**
 * @param {Object}   props
 * @param {string}   props.month   The month shown, Y-m.
 * @param {string}   props.today   Today, Y-m-d (the site's date, from the server).
 * @param {Object}   props.days    Y-m-d => what the day shows (nodes); days left out are empty.
 * @param {Function} props.onMonth ( Y-m ) => void, for previous, next and Today.
 * @param {boolean}  props.loading The month's entries are loading.
 */
export function CalendarGrid( { month, today, days = {}, onMonth, loading } ) {
	const weeks = monthWeeks( month );
	const thisMonth = today.slice( 0, 7 );
	const prev = shiftMonth( month, -1 );
	const next = shiftMonth( month, 1 );
	const heads = weeks[ 0 ].map( ( _, i ) =>
		new Intl.DateTimeFormat( locale(), {
			weekday: 'short',
			timeZone: 'UTC',
		} ).format( new Date( Date.UTC( 2024, 0, 1 + i, 12 ) ) )
	); // 1 Jan 2024 was a Monday
	const busy = Object.keys( days ).filter(
		( d ) => d.slice( 0, 7 ) === month && days[ d ]
	);
	busy.sort();
	return (
		<div className="kits-shell-cal" aria-busy={ loading || undefined }>
			<div className="kits-shell-cal__nav">
				<Button variant="secondary" onClick={ () => onMonth( prev ) }>
					{ `‹ ${ monthName( prev, { month: 'long' } ) }` }
				</Button>
				<strong className="kits-shell-cal__title" aria-live="polite">
					{ monthName( month, { month: 'long', year: 'numeric' } ) }
				</strong>
				<Button variant="secondary" onClick={ () => onMonth( next ) }>
					{ `${ monthName( next, { month: 'long' } ) } ›` }
				</Button>
				<Button
					variant="link"
					disabled={ month === thisMonth }
					onClick={ () => onMonth( thisMonth ) }
				>
					{ __( 'Today', 'kits-core' ) }
				</Button>
			</div>
			<table className="kits-shell-cal__grid">
				<caption className="screen-reader-text">
					{ monthName( month, { month: 'long', year: 'numeric' } ) }
				</caption>
				<thead>
					<tr>
						{ heads.map( ( h ) => (
							<th key={ h } scope="col">
								{ h }
							</th>
						) ) }
					</tr>
				</thead>
				<tbody>
					{ weeks.map( ( w ) => (
						<tr key={ w.join( '|' ) }>
							{ w.map( ( d, i ) => {
								if ( ! d ) {
									return (
										<td
											key={ `x${ i }` }
											className="is-outside"
										/>
									);
								}
								let cls = '';
								if ( d === today ) {
									cls = 'is-today';
								} else if ( d < today ) {
									cls = 'is-past';
								}
								return (
									<td
										key={ d }
										className={ cls || undefined }
										aria-current={
											d === today ? 'date' : undefined
										}
									>
										<span className="kits-shell-cal__day">
											{ Number( d.slice( 8 ) ) }
										</span>
										{ days[ d ] }
									</td>
								);
							} ) }
						</tr>
					) ) }
				</tbody>
			</table>
			<ul
				className="kits-shell-cal__list"
				aria-label={ monthName( month, {
					month: 'long',
					year: 'numeric',
				} ) }
			>
				{ busy.length ? (
					busy.map( ( d ) => (
						<li
							key={ d }
							className={
								( d === today && 'is-today' ) ||
								( d < today && 'is-past' ) ||
								undefined
							}
							aria-current={ d === today ? 'date' : undefined }
						>
							<span className="kits-shell-cal__day">
								{ dayName( d ) }
							</span>
							{ days[ d ] }
						</li>
					) )
				) : (
					<li>{ __( 'Nothing this month.', 'kits-core' ) }</li>
				) }
			</ul>
		</div>
	);
}
