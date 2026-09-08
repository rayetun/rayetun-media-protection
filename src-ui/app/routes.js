/**
 * Route definitions for the RayEtun Media Protection admin SPA. Each entry is a client-side
 * "screen"; the sidebar renders from this list and the router matches on the
 * hash. Screen components are filled in across milestones 8.1–8.8.
 */
import { __ } from '@wordpress/i18n';
import {
	home,
	image,
	lock,
	shield,
	plugins,
	chartBar,
	cog,
	blockDefault,
} from '@wordpress/icons';

import Dashboard from '../dashboard/Dashboard';
import Watermarks from '../watermarks/Watermarks';
import ProtectedFiles from '../files/ProtectedFiles';
import AccessPolicies from '../policies/AccessPolicies';
import Blocks from '../blockhub/Blocks';
import Integrations from '../integrations/Integrations';
import Analytics from '../analytics/Analytics';
import Settings from '../settings/Settings';

export const ROUTES = [
	{ key: 'dashboard', label: __( 'Dashboard', 'rayetun-media-protection' ), icon: home, component: Dashboard },
	{ key: 'watermarks', label: __( 'Watermarks', 'rayetun-media-protection' ), icon: image, component: Watermarks },
	{ key: 'files', label: __( 'Protected Files', 'rayetun-media-protection' ), icon: lock, component: ProtectedFiles },
	{ key: 'policies', label: __( 'Access Policies', 'rayetun-media-protection' ), icon: shield, component: AccessPolicies },
	{ key: 'blocks', label: __( 'Blocks', 'rayetun-media-protection' ), icon: blockDefault, component: Blocks },
	{ key: 'integrations', label: __( 'Integrations', 'rayetun-media-protection' ), icon: plugins, component: Integrations },
	{ key: 'analytics', label: __( 'Analytics', 'rayetun-media-protection' ), icon: chartBar, component: Analytics },
	{ key: 'settings', label: __( 'Settings', 'rayetun-media-protection' ), icon: cog, component: Settings },
];

export const DEFAULT_ROUTE = 'dashboard';

export function routeFromHash() {
	const hash = window.location.hash.replace( /^#\/?/, '' );
	const match = ROUTES.find( ( r ) => r.key === hash );
	return match ? match.key : DEFAULT_ROUTE;
}
