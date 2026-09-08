/**
 * First-run checklist. Steps derive from real state and self-complete; the card
 * hides once all applicable steps are done or the user dismisses it.
 */
import { __ } from '@wordpress/i18n';
import { Button, Icon } from '@wordpress/components';
import { check, close } from '@wordpress/icons';

export default function Onboarding( { stats, features, integrations, onDismiss } ) {
	const hasIntegration = ( integrations || [] ).length > 0;

	const steps = [
		{
			label: __( 'Create your first watermark preset', 'rayetun-media-protection' ),
			done: stats.presets > 0,
		},
		{
			label: __( 'Protect your first file', 'rayetun-media-protection' ),
			done: stats.protectedFiles > 0,
		},
	];
	if ( hasIntegration ) {
		steps.push( {
			label: __( 'Turn on watermarking for your store downloads', 'rayetun-media-protection' ),
			done: !! features.ecommerce,
		} );
	}

	const allDone = steps.every( ( s ) => s.done );
	if ( allDone ) {
		return null;
	}

	return (
		<div className="mg-card mg-onboarding">
			<div className="mg-onboarding__head">
				<h2 style={ { margin: 0, fontSize: 16 } }>
					{ __( 'Get started with RayEtun Media Protection', 'rayetun-media-protection' ) }
				</h2>
				<Button
					icon={ close }
					label={ __( 'Dismiss', 'rayetun-media-protection' ) }
					onClick={ onDismiss }
					size="small"
				/>
			</div>
			<ul className="mg-steps">
				{ steps.map( ( step ) => (
					<li key={ step.label } className={ `mg-step${ step.done ? ' is-done' : '' }` }>
						<span className="mg-step__check" aria-hidden="true">
							{ step.done && <Icon icon={ check } size={ 14 } /> }
						</span>
						<span className="mg-step__label">{ step.label }</span>
						<span className="screen-reader-text">
							{ step.done ? __( '(done)', 'rayetun-media-protection' ) : __( '(to do)', 'rayetun-media-protection' ) }
						</span>
					</li>
				) ) }
			</ul>
		</div>
	);
}
