/**
 * Temporary screen placeholder used while a screen is being built across
 * milestones 8.1–8.8. Replaced screen-by-screen.
 */
import { __ } from '@wordpress/i18n';
import PageHeader from './PageHeader';

export default function Placeholder( { title } ) {
	return (
		<>
			<PageHeader
				title={ title }
				subtitle={ __( 'This screen is coming together.', 'rayetun-media-protection' ) }
			/>
			<div className="mg-card mg-placeholder">
				<span className="mg-placeholder__badge">
					{ __( 'In progress', 'rayetun-media-protection' ) }
				</span>
				<p>
					{ __(
						'The interface for this area is being built. The feature already works via WP-CLI.',
						'rayetun-media-protection'
					) }
				</p>
			</div>
		</>
	);
}
