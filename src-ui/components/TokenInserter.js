/**
 * Clickable token chips that insert a placeholder into the watermark text at
 * the caret. Each token resolves to buyer-specific data at download time.
 */
import { __ } from '@wordpress/i18n';

const TOKENS = [
	{ token: '{user_email}', label: __( 'Email', 'rayetun-media-protection' ) },
	{ token: '{order_id}', label: __( 'Order #', 'rayetun-media-protection' ) },
	{ token: '{date}', label: __( 'Date', 'rayetun-media-protection' ) },
	{ token: '{user_id}', label: __( 'User ID', 'rayetun-media-protection' ) },
	{ token: '{user_ip}', label: __( 'IP', 'rayetun-media-protection' ) },
	{ token: '{site}', label: __( 'Site', 'rayetun-media-protection' ) },
];

export default function TokenInserter( { onInsert } ) {
	return (
		<div className="mg-tokens">
			<span className="mg-tokens__label">{ __( 'Insert:', 'rayetun-media-protection' ) }</span>
			{ TOKENS.map( ( t ) => (
				<button
					key={ t.token }
					type="button"
					className="mg-token-chip"
					onClick={ () => onInsert( t.token ) }
					title={ t.token }
				>
					{ t.label }
				</button>
			) ) }
		</div>
	);
}
