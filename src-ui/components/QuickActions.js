/**
 * Quick-action cards that jump to the relevant screen.
 */
import { __ } from '@wordpress/i18n';
import { Icon, image, lock, chartBar } from '@wordpress/icons';

function navigate( key ) {
	window.location.hash = `#/${ key }`;
}

const ACTIONS = [
	{
		key: 'watermarks',
		icon: image,
		title: __( 'Create a watermark preset', 'rayetun-media-protection' ),
		desc: __( 'Design a reusable text or image watermark.', 'rayetun-media-protection' ),
	},
	{
		key: 'files',
		icon: lock,
		title: __( 'Protect a file', 'rayetun-media-protection' ),
		desc: __( 'Lock a download behind a signed, expiring link.', 'rayetun-media-protection' ),
	},
	{
		key: 'analytics',
		icon: chartBar,
		title: __( 'View analytics', 'rayetun-media-protection' ),
		desc: __( 'See downloads and blocked attempts.', 'rayetun-media-protection' ),
	},
];

export default function QuickActions() {
	return (
		<>
			<h2 className="mg-section-title">{ __( 'Quick actions', 'rayetun-media-protection' ) }</h2>
			<div className="mg-grid mg-grid--actions">
				{ ACTIONS.map( ( a ) => (
					<button
						key={ a.key }
						type="button"
						className="mg-quick-action"
						onClick={ () => navigate( a.key ) }
					>
						<span className="mg-quick-action__title">
							<Icon className="mg-quick-action__icon" icon={ a.icon } size={ 20 } />
							{ a.title }
						</span>
						<span className="mg-quick-action__desc">{ a.desc }</span>
					</button>
				) ) }
			</div>
		</>
	);
}
