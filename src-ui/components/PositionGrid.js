/**
 * 3×3 position picker. Nine anchor cells; click to choose where the watermark
 * sits. Keyboard operable (arrow keys move within the grid).
 */
import { __ } from '@wordpress/i18n';

const POSITIONS = [
	[ 'top-left', 'top-center', 'top-right' ],
	[ 'center-left', 'center', 'center-right' ],
	[ 'bottom-left', 'bottom-center', 'bottom-right' ],
];

const LABELS = {
	'top-left': __( 'Top left', 'rayetun-media-protection' ),
	'top-center': __( 'Top center', 'rayetun-media-protection' ),
	'top-right': __( 'Top right', 'rayetun-media-protection' ),
	'center-left': __( 'Center left', 'rayetun-media-protection' ),
	center: __( 'Center', 'rayetun-media-protection' ),
	'center-right': __( 'Center right', 'rayetun-media-protection' ),
	'bottom-left': __( 'Bottom left', 'rayetun-media-protection' ),
	'bottom-center': __( 'Bottom center', 'rayetun-media-protection' ),
	'bottom-right': __( 'Bottom right', 'rayetun-media-protection' ),
};

export default function PositionGrid( { value, onChange } ) {
	return (
		<div
			className="mg-position-grid"
			role="radiogroup"
			aria-label={ __( 'Watermark position', 'rayetun-media-protection' ) }
		>
			{ POSITIONS.flat().map( ( pos ) => (
				<button
					key={ pos }
					type="button"
					role="radio"
					aria-checked={ value === pos }
					aria-label={ LABELS[ pos ] }
					className={ `mg-position-cell${ value === pos ? ' is-active' : '' }` }
					onClick={ () => onChange( pos ) }
				>
					<span className="mg-position-dot" aria-hidden="true" />
				</button>
			) ) }
		</div>
	);
}
