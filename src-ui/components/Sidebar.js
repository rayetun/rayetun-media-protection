/**
 * Left-rail navigation. Renders from ROUTES; highlights the active screen and
 * exposes a Support link in the footer.
 */
import { __ } from '@wordpress/i18n';
import { Icon } from '@wordpress/components';
import { ROUTES } from '../app/routes';

const data = window.markguardData || {};

export default function Sidebar( { current, onNavigate } ) {
	return (
		<nav className="mg-sidebar" aria-label={ __( 'RayEtun Media Protection', 'rayetun-media-protection' ) }>
			<div className="mg-brand">
				<svg
					className="mg-brand__logo"
					viewBox="0 0 20 20"
					fill="currentColor"
					aria-hidden="true"
				>
					<path d="M10 1.5 3 4v5c0 4.2 2.9 8.1 7 9.5 4.1-1.4 7-5.3 7-9.5V4l-7-2.5Zm0 2.1 5 1.8V9c0 3.2-2.1 6.2-5 7.4V3.6Z" />
				</svg>
				<span className="mg-brand__name">RayEtun Media Protection</span>
				{ data.version && (
					<span className="mg-brand__version">v{ data.version }</span>
				) }
			</div>

			<div className="mg-nav">
				{ ROUTES.map( ( route ) => (
					<button
						key={ route.key }
						type="button"
						className={ `mg-nav__item${ current === route.key ? ' is-active' : '' }` }
						aria-current={ current === route.key ? 'page' : undefined }
						onClick={ () => onNavigate( route.key ) }
					>
						<Icon className="mg-nav__icon" icon={ route.icon } size={ 20 } />
						<span className="mg-nav__label">{ route.label }</span>
					</button>
				) ) }
			</div>

			<div className="mg-sidebar__footer">
				{ data.supportUrl && (
					<a className="components-button is-link" href={ data.supportUrl } target="_blank" rel="noreferrer">
						{ __( 'Support', 'rayetun-media-protection' ) }
						<span className="screen-reader-text">{ __( '(opens in a new tab)', 'rayetun-media-protection' ) }</span>
					</a>
				) }
			</div>
		</nav>
	);
}
