/**
 * Reusable horizontal bar list. Each row has a label, a total, and one or more
 * coloured segments (single segment for a plain magnitude chart; two segments —
 * served + blocked — for a composite bar). Bars are plain HTML so the numbers
 * stay crisp and selectable; widths are a share of the largest row's total.
 *
 * Value labels sit at the row end (never a number inside every segment), the
 * track is recessive, and each segment carries a hover title. A single hue with
 * direct labels needs no legend; callers that pass two segments supply their own
 * legend alongside.
 */
export default function HorizontalBars( { rows, max, ariaLabel } ) {
	const scale = Math.max( 1, max || Math.max( ...rows.map( ( r ) => r.total ), 1 ) );

	return (
		<ul className="mg-bars" aria-label={ ariaLabel }>
			{ rows.map( ( row ) => (
				<li key={ row.key } className="mg-bars__row">
					<div className="mg-bars__head">
						<span className="mg-bars__label" title={ row.label }>{ row.label }</span>
						<span className="mg-bars__value">{ row.total }</span>
					</div>
					<div className="mg-bars__track">
						{ row.segments
							.filter( ( s ) => s.value > 0 )
							.map( ( s, i ) => (
								<div
									key={ i }
									className="mg-bars__seg"
									style={ { width: `${ ( s.value / scale ) * 100 }%`, background: s.color } }
									title={ `${ s.name }: ${ s.value }` }
								/>
							) ) }
					</div>
				</li>
			) ) }
		</ul>
	);
}
