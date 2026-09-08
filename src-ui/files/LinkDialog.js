/**
 * Mint a signed download link for a file, choosing how long it stays valid.
 */
import { __ } from '@wordpress/i18n';
import { useState, useRef } from '@wordpress/element';
import { Modal, SelectControl, Button, ExternalLink } from '@wordpress/components';
import { mgFetch } from '../app/api';

const TTL_OPTIONS = [
	{ label: __( 'Never expires', 'rayetun-media-protection' ), value: '0' },
	{ label: __( '1 hour', 'rayetun-media-protection' ), value: '3600' },
	{ label: __( '24 hours', 'rayetun-media-protection' ), value: '86400' },
	{ label: __( '7 days', 'rayetun-media-protection' ), value: '604800' },
	{ label: __( '30 days', 'rayetun-media-protection' ), value: '2592000' },
];

export default function LinkDialog( { file, onClose } ) {
	const [ ttl, setTtl ] = useState( '86400' );
	const [ url, setUrl ] = useState( '' );
	const [ minting, setMinting ] = useState( false );
	const [ copied, setCopied ] = useState( false );
	const inputRef = useRef( null );

	const mint = async () => {
		setMinting( true );
		try {
			const res = await mgFetch( `/files/${ file.id }/links`, { method: 'POST', data: { ttl: parseInt( ttl, 10 ) } } );
			setUrl( res.url );
			setCopied( false );
		} finally {
			setMinting( false );
		}
	};

	const copy = async () => {
		// The async Clipboard API only works in a secure context (https or
		// localhost). Local dev over http://*.local falls back to selecting the
		// field and using execCommand, and finally to leaving it selected for
		// a manual Ctrl+C.
		try {
			if ( navigator.clipboard && window.isSecureContext ) {
				await navigator.clipboard.writeText( url );
				setCopied( true );
				return;
			}
		} catch ( e ) {
			/* fall through to legacy copy */
		}
		const el = inputRef.current;
		if ( el ) {
			el.focus();
			el.select();
			try {
				const ok = document.execCommand( 'copy' );
				setCopied( ok );
			} catch ( e ) {
				/* leave selected for manual copy */
			}
		}
	};

	return (
		<Modal title={ __( 'Create download link', 'rayetun-media-protection' ) } onRequestClose={ onClose } className="mg-editor-modal mg-editor-modal--narrow">
			<div className="mg-form">
				<p className="mg-field__hint">{ file.name }</p>
				<SelectControl
					__nextHasNoMarginBottom
					label={ __( 'Link lifetime', 'rayetun-media-protection' ) }
					value={ ttl }
					options={ TTL_OPTIONS }
					onChange={ setTtl }
				/>
				{ url && (
					<div className="mg-linkbox">
						<input ref={ inputRef } className="mg-linkbox__input" type="text" readOnly value={ url } onFocus={ ( e ) => e.target.select() } />
						<Button variant="secondary" onClick={ copy }>
							{ copied ? __( 'Copied!', 'rayetun-media-protection' ) : __( 'Copy', 'rayetun-media-protection' ) }
						</Button>
					</div>
				) }
				{ url && (
					<p className="mg-field__hint">
						<ExternalLink href={ url }>{ __( 'Open link in a new tab', 'rayetun-media-protection' ) }</ExternalLink>
					</p>
				) }
			</div>
			<div className="mg-editor__footer">
				<Button variant="tertiary" onClick={ onClose }>{ __( 'Close', 'rayetun-media-protection' ) }</Button>
				<Button variant="primary" onClick={ mint } isBusy={ minting } disabled={ minting }>
					{ url ? __( 'Create another', 'rayetun-media-protection' ) : __( 'Create link', 'rayetun-media-protection' ) }
				</Button>
			</div>
		</Modal>
	);
}
