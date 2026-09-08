/**
 * Downloads-served vs blocked-attempts over time. A single-axis line + area
 * chart: served is a filled emerald line, blocked a dashed amber line. The two
 * series carry a legend and distinct line styles, so they are never told apart
 * by colour alone (colourblind-safe). A crosshair tooltip reads exact daily
 * values, and a collapsible data table gives the same numbers to screen readers
 * and to anyone who prefers the figures.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useState, useRef } from '@wordpress/element';

const VBW = 760;
const VBH = 260;
const PAD = { top: 16, right: 18, bottom: 30, left: 40 };
const INNER_W = VBW - PAD.left - PAD.right;
const INNER_H = VBH - PAD.top - PAD.bottom;

function niceMax( value ) {
	if ( value <= 5 ) {
		return 5;
	}
	const pow = Math.pow( 10, Math.floor( Math.log10( value ) ) );
	const steps = [ 1, 2, 2.5, 5, 10 ];
	for ( const s of steps ) {
		if ( value <= s * pow ) {
			return s * pow;
		}
	}
	return 10 * pow;
}

function shortDate( iso ) {
	const d = new Date( iso + 'T00:00:00Z' );
	return d.toLocaleDateString( undefined, { month: 'short', day: 'numeric', timeZone: 'UTC' } );
}

export default function TimeSeriesChart( { series } ) {
	const svgRef = useRef( null );
	const [ hover, setHover ] = useState( null ); // index into series
	const n = series.length;

	const maxV = Math.max( 1, ...series.map( ( d ) => Math.max( d.served, d.blocked ) ) );
	const maxY = niceMax( maxV );

	const x = ( i ) => PAD.left + ( n <= 1 ? INNER_W / 2 : ( i / ( n - 1 ) ) * INNER_W );
	const y = ( v ) => PAD.top + ( 1 - v / maxY ) * INNER_H;

	const linePath = ( key ) =>
		series.map( ( d, i ) => `${ i === 0 ? 'M' : 'L' }${ x( i ).toFixed( 1 ) },${ y( d[ key ] ).toFixed( 1 ) }` ).join( ' ' );

	const areaPath =
		`${ linePath( 'served' ) } L${ x( n - 1 ).toFixed( 1 ) },${ y( 0 ).toFixed( 1 ) } L${ x( 0 ).toFixed( 1 ) },${ y( 0 ).toFixed( 1 ) } Z`;

	// Y gridlines / labels at 0, ¼, ½, ¾, full.
	const yTicks = [ 0, 0.25, 0.5, 0.75, 1 ].map( ( f ) => Math.round( f * maxY ) );
	// ~6 evenly spaced x labels.
	const xTickIdx = [];
	const want = Math.min( 6, n );
	for ( let k = 0; k < want; k++ ) {
		xTickIdx.push( Math.round( ( k / Math.max( 1, want - 1 ) ) * ( n - 1 ) ) );
	}

	const onMove = ( e ) => {
		const rect = svgRef.current.getBoundingClientRect();
		const vbX = ( ( e.clientX - rect.left ) / rect.width ) * VBW;
		let i = Math.round( ( ( vbX - PAD.left ) / INNER_W ) * ( n - 1 ) );
		i = Math.max( 0, Math.min( n - 1, i ) );
		setHover( i );
	};

	const totalServed = series.reduce( ( s, d ) => s + d.served, 0 );
	const totalBlocked = series.reduce( ( s, d ) => s + d.blocked, 0 );
	const ariaSummary = sprintf(
		/* translators: 1: days, 2: served count, 3: blocked count */
		__( 'Daily activity over %1$d days: %2$d downloads served and %3$d attempts blocked in total.', 'rayetun-media-protection' ),
		n,
		totalServed,
		totalBlocked
	);

	const hv = hover !== null ? series[ hover ] : null;
	const hoverLeftPct = hover !== null ? ( x( hover ) / VBW ) * 100 : 0;

	return (
		<figure className="mg-chart">
			<figcaption className="mg-chart__legend">
				<span className="mg-legend-item">
					<svg width="22" height="10" aria-hidden="true" className="mg-legend-line">
						<line x1="1" y1="5" x2="21" y2="5" stroke="var(--mg-chart-served)" strokeWidth="2.5" />
					</svg>
					{ __( 'Downloads served', 'rayetun-media-protection' ) }
				</span>
				<span className="mg-legend-item">
					<svg width="22" height="10" aria-hidden="true" className="mg-legend-line">
						<line x1="1" y1="5" x2="21" y2="5" stroke="var(--mg-chart-blocked)" strokeWidth="2.5" strokeDasharray="4 3" />
					</svg>
					{ __( 'Blocked attempts', 'rayetun-media-protection' ) }
				</span>
			</figcaption>

			<div className="mg-chart__plot">
				<svg
					ref={ svgRef }
					viewBox={ `0 0 ${ VBW } ${ VBH }` }
					preserveAspectRatio="xMidYMid meet"
					className="mg-chart__svg"
					role="img"
					aria-label={ ariaSummary }
					onMouseMove={ onMove }
					onMouseLeave={ () => setHover( null ) }
				>
					{ /* Y grid + labels */ }
					{ yTicks.map( ( t, i ) => (
						<g key={ i }>
							<line
								x1={ PAD.left }
								y1={ y( t ) }
								x2={ VBW - PAD.right }
								y2={ y( t ) }
								stroke="var(--mg-border)"
								strokeWidth="1"
								opacity={ i === 0 ? 0.9 : 0.5 }
							/>
							<text x={ PAD.left - 8 } y={ y( t ) + 4 } textAnchor="end" className="mg-chart__axis">{ t }</text>
						</g>
					) ) }

					{ /* X labels */ }
					{ xTickIdx.map( ( idx ) => (
						<text key={ idx } x={ x( idx ) } y={ VBH - 10 } textAnchor="middle" className="mg-chart__axis">
							{ shortDate( series[ idx ].date ) }
						</text>
					) ) }

					{ /* Served area + line */ }
					<path d={ areaPath } fill="var(--mg-chart-served)" opacity="0.12" />
					<path d={ linePath( 'served' ) } fill="none" stroke="var(--mg-chart-served)" strokeWidth="2.5" strokeLinejoin="round" strokeLinecap="round" />
					{ /* Blocked dashed line */ }
					<path d={ linePath( 'blocked' ) } fill="none" stroke="var(--mg-chart-blocked)" strokeWidth="2.5" strokeDasharray="5 3" strokeLinejoin="round" strokeLinecap="round" />

					{ /* Hover crosshair + points */ }
					{ hv && (
						<g aria-hidden="true">
							<line x1={ x( hover ) } y1={ PAD.top } x2={ x( hover ) } y2={ PAD.top + INNER_H } stroke="var(--mg-text-muted)" strokeWidth="1" opacity="0.5" />
							<circle cx={ x( hover ) } cy={ y( hv.served ) } r="4.5" fill="var(--mg-chart-served)" stroke="var(--mg-surface)" strokeWidth="2" />
							<circle cx={ x( hover ) } cy={ y( hv.blocked ) } r="4.5" fill="var(--mg-chart-blocked)" stroke="var(--mg-surface)" strokeWidth="2" />
						</g>
					) }
				</svg>

				{ hv && (
					<div
						className="mg-chart__tip"
						style={ {
							left: `${ hoverLeftPct }%`,
							transform: `translateX(${ hoverLeftPct > 70 ? '-100%' : hoverLeftPct < 30 ? '0' : '-50%' })`,
						} }
					>
						<div className="mg-chart__tip-date">{ shortDate( hv.date ) }</div>
						<div className="mg-chart__tip-row"><span className="mg-dot" style={ { background: 'var(--mg-chart-served)' } } />{ __( 'Served', 'rayetun-media-protection' ) }<strong>{ hv.served }</strong></div>
						<div className="mg-chart__tip-row"><span className="mg-dot" style={ { background: 'var(--mg-chart-blocked)' } } />{ __( 'Blocked', 'rayetun-media-protection' ) }<strong>{ hv.blocked }</strong></div>
					</div>
				) }
			</div>

			<details className="mg-chart__data">
				<summary>{ __( 'View data table', 'rayetun-media-protection' ) }</summary>
				<div className="mg-table-wrap">
					<table className="mg-table">
						<thead>
							<tr>
								<th>{ __( 'Date', 'rayetun-media-protection' ) }</th>
								<th className="mg-num">{ __( 'Served', 'rayetun-media-protection' ) }</th>
								<th className="mg-num">{ __( 'Blocked', 'rayetun-media-protection' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ series.map( ( d ) => (
								<tr key={ d.date }>
									<td>{ shortDate( d.date ) }</td>
									<td className="mg-num">{ d.served }</td>
									<td className="mg-num">{ d.blocked }</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</div>
			</details>
		</figure>
	);
}
