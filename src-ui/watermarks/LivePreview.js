/**
 * Hybrid watermark preview:
 *  - an instant client-side approximation over a sample backdrop, and
 *  - a "Render real preview" button that runs the actual engine server-side
 *    and shows pixel-accurate output.
 * The real preview clears whenever the rule changes, so the approximation is
 * always what's shown until the author asks for an exact render again.
 */
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { mgFetch } from '../app/api';

const PREVIEW_W = 480;
const SCALE = PREVIEW_W / 800;

function resolveTokens( text ) {
	const today = new Date().toISOString().slice( 0, 10 );
	return String( text )
		.replaceAll( '{user_email}', 'buyer@example.com' )
		.replaceAll( '{order_id}', '1042' )
		.replaceAll( '{date}', today )
		.replaceAll( '{datetime}', today + 'T09:30:00Z' )
		.replaceAll( '{user_id}', '42' )
		.replaceAll( '{user_ip}', '203.0.113.7' )
		.replaceAll( '{site}', window.location.hostname );
}

function alignment( position ) {
	const [ v, h ] = ( () => {
		const parts = position.split( '-' );
		if ( position === 'center' ) return [ 'center', 'center' ];
		return [ parts[ 0 ], parts[ 1 ] ];
	} )();
	const map = { top: 'flex-start', bottom: 'flex-end', left: 'flex-start', right: 'flex-end', center: 'center' };
	return { alignItems: map[ v ] || 'center', justifyContent: map[ h ] || 'center' };
}

export default function LivePreview( { rule } ) {
	const [ real, setReal ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ err, setErr ] = useState( false );

	useEffect( () => {
		setReal( null );
		setErr( false );
	}, [ JSON.stringify( rule ) ] );

	const renderReal = async () => {
		setLoading( true );
		setErr( false );
		try {
			const res = await mgFetch( '/watermark-presets/preview', { method: 'POST', data: { rule } } );
			setReal( res.image );
		} catch ( e ) {
			setErr( true );
		} finally {
			setLoading( false );
		}
	};

	const align = alignment( rule.position );
	const pad = {
		paddingTop: rule.offsetY * SCALE,
		paddingBottom: rule.offsetY * SCALE,
		paddingLeft: rule.offsetX * SCALE,
		paddingRight: rule.offsetX * SCALE,
	};
	const transform = `rotate(${ rule.rotation }deg)`;
	const opacity = rule.opacity / 100;

	return (
		<div className="mg-preview">
			<div className="mg-preview__stage" style={ { width: '100%', maxWidth: PREVIEW_W, aspectRatio: '8 / 5' } }>
				{ real ? (
					<img className="mg-preview__real" src={ real } alt={ __( 'Rendered watermark preview', 'rayetun-media-protection' ) } />
				) : (
					<div className="mg-preview__canvas" style={ { ...align, ...pad } }>
						{ rule.type === 'text' ? (
							<span
								className="mg-preview__text"
								style={ {
									fontSize: Math.max( 8, rule.fontSize * SCALE ),
									color: rule.fontColor,
									opacity,
									transform,
								} }
							>
								{ resolveTokens( rule.text ) || __( 'Your watermark text', 'rayetun-media-protection' ) }
							</span>
						) : rule.overlayPath ? (
							<img
								className="mg-preview__overlay"
								src={ rule.overlayPath }
								alt=""
								style={ { width: PREVIEW_W * rule.imageScale, opacity, transform } }
							/>
						) : (
							<span className="mg-preview__placeholder" style={ { opacity } }>
								{ __( 'Choose an overlay image', 'rayetun-media-protection' ) }
							</span>
						) }
					</div>
				) }
			</div>

			<div className="mg-preview__actions">
				<Button variant="secondary" onClick={ renderReal } disabled={ loading }>
					{ loading ? <Spinner /> : __( 'Render real preview', 'rayetun-media-protection' ) }
				</Button>
				{ real && (
					<span className="mg-preview__badge">{ __( 'Exact engine output', 'rayetun-media-protection' ) }</span>
				) }
				{ err && (
					<span className="mg-preview__err">{ __( 'Preview unavailable on this server.', 'rayetun-media-protection' ) }</span>
				) }
			</div>
			<p className="mg-preview__note">
				{ __( 'Preview shown on a sample photo. Tokens use example values.', 'rayetun-media-protection' ) }
			</p>
		</div>
	);
}
