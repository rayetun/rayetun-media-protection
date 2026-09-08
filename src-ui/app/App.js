/**
 * RayEtun Media Protection admin shell: sidebar + routed main column. Hash-based routing keeps
 * deep links working without a router dependency.
 */
import { useState, useEffect, useRef } from '@wordpress/element';
import Sidebar from '../components/Sidebar';
import { ROUTES, routeFromHash } from './routes';

export default function App() {
	const [ current, setCurrent ] = useState( routeFromHash() );
	const [ announce, setAnnounce ] = useState( '' );
	const firstRender = useRef( true );

	useEffect( () => {
		const onHashChange = () => setCurrent( routeFromHash() );
		window.addEventListener( 'hashchange', onHashChange );
		return () => window.removeEventListener( 'hashchange', onHashChange );
	}, [] );

	const navigate = ( key ) => {
		window.location.hash = `#/${ key }`;
		setCurrent( key );
	};

	const active = ROUTES.find( ( r ) => r.key === current ) || ROUTES[ 0 ];
	const Screen = active.component;

	// Announce the screen name to assistive tech on navigation (a targeted live
	// region — not aria-live on the whole main column, which would re-read the
	// entire page on every change). Skip the initial render.
	useEffect( () => {
		if ( firstRender.current ) {
			firstRender.current = false;
			return;
		}
		setAnnounce( active.label );
	}, [ current, active.label ] );

	return (
		<div className="mg-app">
			<Sidebar current={ current } onNavigate={ navigate } />
			<main className="mg-main" id="mg-main">
				<Screen />
			</main>
			<div className="screen-reader-text" role="status" aria-live="polite">{ announce }</div>
		</div>
	);
}
