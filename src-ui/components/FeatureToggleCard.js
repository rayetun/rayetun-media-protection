/**
 * A feature module card with a toggle, matching the portfolio's module cards.
 */
import { ToggleControl } from '@wordpress/components';

export default function FeatureToggleCard( { title, description, checked, onChange, note, busy } ) {
	return (
		<div className="mg-card mg-feature-card">
			<div className="mg-feature-card__head">
				<h3 className="mg-feature-card__title">{ title }</h3>
				<ToggleControl
					__nextHasNoMarginBottom
					checked={ checked }
					disabled={ busy }
					onChange={ onChange }
					label=""
					aria-label={ title }
				/>
			</div>
			<p className="mg-feature-card__desc">{ description }</p>
			{ note && <span className="mg-feature-card__note">{ note }</span> }
		</div>
	);
}
