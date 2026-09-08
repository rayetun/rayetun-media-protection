/**
 * Analytics screen — reads RayEtun Media Protection's own access log (nothing external) and
 * charts it: headline totals, served-vs-blocked over time, the busiest files,
 * and why attempts were blocked. Range switches between 7, 30, and 90 days.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import PageHeader from '../components/PageHeader';
import StatCard from '../components/StatCard';
import TimeSeriesChart from './TimeSeriesChart';
import HorizontalBars from './HorizontalBars';
import { mgFetch } from '../app/api';

const SERVED_COLOR = 'var(--mg-chart-served)';
const BLOCKED_COLOR = 'var(--mg-chart-blocked)';

const RANGES = [
	{ value: '7', label: __( '7 days', 'rayetun-media-protection' ) },
	{ value: '30', label: __( '30 days', 'rayetun-media-protection' ) },
	{ value: '90', label: __( '90 days', 'rayetun-media-protection' ) },
];

function EmptyState() {
	return (
		<div className="mg-card mg-placeholder">
			<span className="mg-placeholder__badge">{ __( 'No activity yet', 'rayetun-media-protection' ) }</span>
			<p>{ __( 'Once your protected files are downloaded or blocked, the trends will appear here.', 'rayetun-media-protection' ) }</p>
		</div>
	);
}

export default function Analytics() {
	const [ range, setRange ] = useState( '30' );
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	useEffect( () => {
		let active = true;
		setLoading( true );
		mgFetch( `/analytics?range=${ range }` )
			.then( ( res ) => active && setData( res ) )
			.finally( () => active && setLoading( false ) );
		return () => {
			active = false;
		};
	}, [ range ] );

	const hasActivity = data && ( data.totals.served > 0 || data.totals.blocked > 0 );

	const topFilesRows = ( data?.topFiles || [] ).map( ( f ) => ( {
		key: f.id,
		label: f.name,
		total: f.served + f.blocked,
		segments: [
			{ value: f.served, color: SERVED_COLOR, name: __( 'Served', 'rayetun-media-protection' ) },
			{ value: f.blocked, color: BLOCKED_COLOR, name: __( 'Blocked', 'rayetun-media-protection' ) },
		],
	} ) );

	const denialRows = ( data?.denials || [] ).map( ( d ) => ( {
		key: d.reason,
		label: d.label,
		total: d.count,
		segments: [ { value: d.count, color: BLOCKED_COLOR, name: d.label } ],
	} ) );

	const denialPct = data ? Math.round( data.totals.denialRate * 100 ) : 0;

	return (
		<>
			<PageHeader
				title={ __( 'Analytics', 'rayetun-media-protection' ) }
				subtitle={ __( 'How your protected files are being accessed — served, blocked, and why.', 'rayetun-media-protection' ) }
				actions={
					<div className="mg-range" role="group" aria-label={ __( 'Time range', 'rayetun-media-protection' ) }>
						{ RANGES.map( ( r ) => (
							<button
								key={ r.value }
								type="button"
								className={ `mg-range__btn${ range === r.value ? ' is-active' : '' }` }
								aria-pressed={ range === r.value }
								onClick={ () => setRange( r.value ) }
							>
								{ r.label }
							</button>
						) ) }
					</div>
				}
			/>

			{ loading && ! data && (
				<div className="mg-card mg-placeholder"><Spinner /></div>
			) }

			{ data && (
				<div className={ loading ? 'mg-fadeable is-loading' : 'mg-fadeable' }>
					<div className="mg-grid mg-grid--stats">
						<StatCard value={ data.totals.served } label={ __( 'Downloads served', 'rayetun-media-protection' ) } tone="primary" />
						<StatCard value={ data.totals.blocked } label={ __( 'Blocked attempts', 'rayetun-media-protection' ) } tone={ data.totals.blocked > 0 ? 'danger' : 'default' } />
						<StatCard value={ data.totals.activeFiles } label={ __( 'Files accessed', 'rayetun-media-protection' ) } />
						<StatCard value={ `${ denialPct }%` } label={ __( 'Block rate', 'rayetun-media-protection' ) } />
					</div>

					{ ! hasActivity && <EmptyState /> }

					{ hasActivity && (
						<>
							<h2 className="mg-section-title">{ __( 'Activity over time', 'rayetun-media-protection' ) }</h2>
							<div className="mg-card">
								<TimeSeriesChart series={ data.series } />
							</div>

							<div className="mg-grid mg-grid--charts">
								<div>
									<h2 className="mg-section-title">{ __( 'Busiest files', 'rayetun-media-protection' ) }</h2>
									<div className="mg-card">
										{ topFilesRows.length > 0 ? (
											<>
												<div className="mg-chart__legend mg-chart__legend--tight">
													<span className="mg-legend-item"><span className="mg-dot" style={ { background: SERVED_COLOR } } />{ __( 'Served', 'rayetun-media-protection' ) }</span>
													<span className="mg-legend-item"><span className="mg-dot" style={ { background: BLOCKED_COLOR } } />{ __( 'Blocked', 'rayetun-media-protection' ) }</span>
												</div>
												<HorizontalBars rows={ topFilesRows } ariaLabel={ __( 'Busiest protected files by number of served and blocked requests', 'rayetun-media-protection' ) } />
											</>
										) : (
											<p className="mg-field__hint">{ __( 'No file activity in this range.', 'rayetun-media-protection' ) }</p>
										) }
									</div>
								</div>

								<div>
									<h2 className="mg-section-title">{ __( 'Why attempts were blocked', 'rayetun-media-protection' ) }</h2>
									<div className="mg-card">
										{ denialRows.length > 0 ? (
											<HorizontalBars rows={ denialRows } ariaLabel={ __( 'Blocked attempts grouped by reason', 'rayetun-media-protection' ) } />
										) : (
											<p className="mg-field__hint">{ __( 'No blocked attempts in this range — every request was allowed.', 'rayetun-media-protection' ) }</p>
										) }
									</div>
								</div>
							</div>

							<p className="mg-analytics__note">
								{ sprintf(
									/* translators: %d: number of days */
									__( 'Based on access-log entries from the last %d days. IP and browser details are stored only as salted hashes.', 'rayetun-media-protection' ),
									data.range
								) }
							</p>
						</>
					) }
				</div>
			) }
		</>
	);
}
