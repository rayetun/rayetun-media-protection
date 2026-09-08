/**
 * Dashboard screen — onboarding, stats, feature modules, quick actions, and
 * system health. Feature toggles persist to options via the REST API.
 */
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { Spinner, Snackbar } from '@wordpress/components';
import PageHeader from '../components/PageHeader';
import StatCard from '../components/StatCard';
import FeatureToggleCard from '../components/FeatureToggleCard';
import QuickActions from '../components/QuickActions';
import Onboarding from '../components/Onboarding';
import { mgFetch } from '../app/api';

const FEATURES = [
	{
		key: 'autoWatermark',
		title: __( 'Auto-watermark uploads', 'rayetun-media-protection' ),
		description: __( 'Apply your default watermark preset to every new image and PDF added to the media library.', 'rayetun-media-protection' ),
	},
	{
		key: 'autoProtect',
		title: __( 'Auto-protect uploads', 'rayetun-media-protection' ),
		description: __( 'Move new uploads into protected storage so they are only reachable through signed links.', 'rayetun-media-protection' ),
	},
	{
		key: 'ecommerce',
		title: __( 'E-commerce watermarking', 'rayetun-media-protection' ),
		description: __( 'Stamp each buyer’s downloads with their email and order for traceable delivery.', 'rayetun-media-protection' ),
	},
	{
		key: 'deterrents',
		title: __( 'Deterrent layer', 'rayetun-media-protection' ),
		description: __( 'Discourage casual copying with right-click, drag, and devtools deterrents. Not a substitute for the file-serving protection above.', 'rayetun-media-protection' ),
	},
];

export default function Dashboard() {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ busyKey, setBusyKey ] = useState( null );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		let active = true;
		mgFetch( '/dashboard' )
			.then( ( res ) => active && setData( res ) )
			.catch( ( err ) => active && setError( err ) );
		return () => {
			active = false;
		};
	}, [] );

	const toggleFeature = async ( key, enabled ) => {
		setBusyKey( key );
		const previous = data.features;
		// Optimistic update.
		setData( { ...data, features: { ...data.features, [ key ]: enabled } } );
		try {
			const res = await mgFetch( '/features', {
				method: 'POST',
				data: { key, enabled },
			} );
			setData( ( d ) => ( { ...d, features: res.features } ) );
			setNotice( __( 'Saved.', 'rayetun-media-protection' ) );
		} catch ( err ) {
			setData( ( d ) => ( { ...d, features: previous } ) ); // Revert.
			setNotice( __( 'Could not save that change.', 'rayetun-media-protection' ) );
		} finally {
			setBusyKey( null );
		}
	};

	const dismissOnboarding = async () => {
		setData( { ...data, onboarding: { dismissed: true } } );
		try {
			await mgFetch( '/onboarding/dismiss', { method: 'POST' } );
		} catch ( err ) {
			/* non-critical */
		}
	};

	return (
		<>
			<PageHeader
				title={ __( 'Dashboard', 'rayetun-media-protection' ) }
				subtitle={ __( 'Overview of your protected media and document security.', 'rayetun-media-protection' ) }
			/>

			{ ! data && ! error && (
				<div className="mg-card mg-placeholder">
					<Spinner />
				</div>
			) }

			{ error && (
				<div className="mg-card" style={ { borderColor: 'var(--mg-danger)' } }>
					{ __( 'Could not load dashboard data.', 'rayetun-media-protection' ) }
				</div>
			) }

			{ data && (
				<>
					{ ! data.onboarding.dismissed && (
						<div style={ { marginBottom: 24 } }>
							<Onboarding
								stats={ data.stats }
								features={ data.features }
								integrations={ data.integrations }
								onDismiss={ dismissOnboarding }
							/>
						</div>
					) }

					<div className="mg-grid mg-grid--stats">
						<StatCard value={ data.stats.protectedFiles } label={ __( 'Protected files', 'rayetun-media-protection' ) } tone="primary" />
						<StatCard value={ data.stats.served7d } label={ __( 'Downloads served (7 days)', 'rayetun-media-protection' ) } />
						<StatCard value={ data.stats.blocked7d } label={ __( 'Blocked attempts (7 days)', 'rayetun-media-protection' ) } tone={ data.stats.blocked7d > 0 ? 'danger' : 'default' } />
						<StatCard value={ data.stats.presets } label={ __( 'Watermark presets', 'rayetun-media-protection' ) } />
					</div>

					<h2 className="mg-section-title">{ __( 'Modules', 'rayetun-media-protection' ) }</h2>
					<div className="mg-grid mg-grid--features">
						{ FEATURES.map( ( f ) => (
							<FeatureToggleCard
								key={ f.key }
								title={ f.title }
								description={ f.description }
								checked={ !! data.features[ f.key ] }
								busy={ busyKey === f.key }
								onChange={ ( val ) => toggleFeature( f.key, val ) }
								note={
									f.key === 'ecommerce' && ( data.integrations || [] ).length === 0
										? __( 'Install WooCommerce, EDD, or Download Monitor to use this.', 'rayetun-media-protection' )
										: null
								}
							/>
						) ) }
					</div>

					<QuickActions />

					<h2 className="mg-section-title">{ __( 'System health', 'rayetun-media-protection' ) }</h2>
					<div className="mg-card">
						<ul style={ { listStyle: 'none', margin: 0, padding: 0 } }>
							{ data.health.map( ( row ) => (
								<li
									key={ row.id }
									style={ {
										display: 'flex',
										justifyContent: 'space-between',
										alignItems: 'center',
										padding: '10px 0',
										borderBottom: '1px solid var(--mg-border)',
									} }
								>
									<span style={ { display: 'flex', alignItems: 'center', gap: 8 } }>
										<span
											aria-hidden="true"
											style={ {
												width: 8,
												height: 8,
												borderRadius: '50%',
												background: row.status === 'ok' ? 'var(--mg-success)' : 'var(--mg-warning)',
											} }
										/>
										{ row.label }
									</span>
									<span style={ { color: 'var(--mg-text-muted)', fontSize: 13 } }>
										{ row.detail }
										<span className="screen-reader-text">
											{ row.status === 'ok' ? __( ' — OK', 'rayetun-media-protection' ) : __( ' — needs attention', 'rayetun-media-protection' ) }
										</span>
									</span>
								</li>
							) ) }
						</ul>
					</div>

					{ notice && (
						<Snackbar onRemove={ () => setNotice( null ) }>{ notice }</Snackbar>
					) }
				</>
			) }
		</>
	);
}
