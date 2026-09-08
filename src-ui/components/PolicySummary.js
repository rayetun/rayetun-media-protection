/**
 * Renders a plain-English summary of an access policy.
 */
import { __, sprintf, _n } from '@wordpress/i18n';

export function policyPhrase( rule, roleNames = {} ) {
	const clauses = [];

	if ( rule.allowedRoles && rule.allowedRoles.length ) {
		const names = rule.allowedRoles.map( ( r ) => roleNames[ r ] || r ).join( ', ' );
		/* translators: %s: comma-separated list of role names */
		clauses.push( sprintf( __( 'users with role %s', 'rayetun-media-protection' ), names ) );
	} else if ( rule.requireLogin ) {
		clauses.push( __( 'logged-in users', 'rayetun-media-protection' ) );
	} else {
		clauses.push( __( 'anyone with the link', 'rayetun-media-protection' ) );
	}

	if ( rule.maxClicks > 0 ) {
		clauses.push(
			sprintf(
				/* translators: %d: number of downloads */
				_n( 'up to %d download per link', 'up to %d downloads per link', rule.maxClicks, 'rayetun-media-protection' ),
				rule.maxClicks
			)
		);
	}

	if ( rule.allowedHost ) {
		/* translators: %s: allowed hostname the file may be embedded on */
		clauses.push( sprintf( __( 'only embeddable on %s', 'rayetun-media-protection' ), rule.allowedHost ) );
	}

	return clauses.join( __( '; ', 'rayetun-media-protection' ) );
}

export default function PolicySummary( { rule, roleNames } ) {
	return <span className="mg-policy-summary">{ policyPhrase( rule, roleNames ) }</span>;
}
