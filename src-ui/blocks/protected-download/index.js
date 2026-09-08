/**
 * Protected Download — block editor. Pick any file; it's registered as a
 * protected file with the chosen policy, and the front end serves it through a
 * signed link only to authorized visitors.
 */
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import {
	useBlockProps,
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	ToggleControl,
	TextControl,
	ColorPalette,
	Button,
	Spinner,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import metadata from './block.json';
import { FileIcon } from '../shared/FileIcon';
import './style.css';
import './editor.css';

function formatBytes( n ) {
	if ( ! n ) {
		return '0 B';
	}
	const u = [ 'B', 'KB', 'MB', 'GB' ];
	const i = Math.floor( Math.log( n ) / Math.log( 1024 ) );
	return ( n / Math.pow( 1024, i ) ).toFixed( i ? 1 : 0 ) + ' ' + u[ i ];
}

function shortMime( m ) {
	if ( ! m ) {
		return '';
	}
	if ( m === 'application/pdf' ) {
		return 'PDF';
	}
	if ( m.startsWith( 'image/' ) ) {
		return m.replace( 'image/', '' ).toUpperCase();
	}
	return m.split( '/' ).pop().toUpperCase();
}

function Edit( { attributes, setAttributes } ) {
	const { fileId, attachmentId, policyId, label, lockedText, showInfo, accent, template, inited, name, size, mime } = attributes;
	const blockProps = useBlockProps( { className: 'mg-download-editor' } );
	const [ policies, setPolicies ] = useState( null );
	const [ busy, setBusy ] = useState( false );

	useEffect( () => {
		apiFetch( { path: 'markguard/v1/blocks/defaults' } )
			.then( ( d ) => {
				setPolicies( d.policies || [] );
				// A brand-new block inherits the site-wide defaults once.
				if ( ! inited && ! fileId && d.download ) {
					setAttributes( { ...d.download, inited: true } );
				}
			} )
			.catch( () => setPolicies( [] ) );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const resolve = async ( attId, polId, fId ) => {
		setBusy( true );
		try {
			const r = await apiFetch( {
				path: 'markguard/v1/blocks/download/resolve',
				method: 'POST',
				data: { attachmentId: attId, policyId: polId, fileId: fId },
			} );
			if ( r.ok ) {
				setAttributes( { fileId: r.fileId, name: r.name, size: r.size, mime: r.mime } );
			}
		} catch ( e ) {
			/* leave attributes as-is on failure */
		} finally {
			setBusy( false );
		}
	};

	const onSelect = ( media ) => {
		setAttributes( { attachmentId: media.id, name: media.title || media.filename || '' } );
		resolve( media.id, policyId, fileId );
	};

	const onPolicy = ( v ) => {
		const p = parseInt( v, 10 );
		setAttributes( { policyId: p } );
		if ( attachmentId ) {
			resolve( attachmentId, p, fileId );
		}
	};

	const policyOptions = policies
		? [
			{ label: __( 'Everyone', 'rayetun-media-protection' ), value: '-1' },
			{ label: __( 'Logged-in users', 'rayetun-media-protection' ), value: '0' },
			...policies.map( ( p ) => ( { label: p.name, value: String( p.id ) } ) ),
		]
		: [];

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'File', 'rayetun-media-protection' ) } initialOpen={ true }>
					<MediaUploadCheck>
						<MediaUpload
							onSelect={ onSelect }
							render={ ( { open } ) => (
								<Button variant="secondary" onClick={ open } style={ { marginBottom: 12 } }>
									{ attachmentId ? __( 'Replace file', 'rayetun-media-protection' ) : __( 'Choose file', 'rayetun-media-protection' ) }
								</Button>
							) }
						/>
					</MediaUploadCheck>
					<SelectControl __nextHasNoMarginBottom label={ __( 'Who can download', 'rayetun-media-protection' ) } value={ String( policyId ) } options={ policyOptions } onChange={ onPolicy } />
					<ToggleControl __nextHasNoMarginBottom label={ __( 'Show file info (type · size)', 'rayetun-media-protection' ) } checked={ showInfo } onChange={ ( v ) => setAttributes( { showInfo: v } ) } />
					<TextControl __nextHasNoMarginBottom label={ __( 'Button label', 'rayetun-media-protection' ) } value={ label } placeholder={ __( 'Download', 'rayetun-media-protection' ) } onChange={ ( v ) => setAttributes( { label: v } ) } />
					<TextControl __nextHasNoMarginBottom label={ __( 'Locked message', 'rayetun-media-protection' ) } value={ lockedText } placeholder={ __( 'Log in to download this file.', 'rayetun-media-protection' ) } onChange={ ( v ) => setAttributes( { lockedText: v } ) } />
				</PanelBody>
				<PanelBody title={ __( 'Style', 'rayetun-media-protection' ) } initialOpen={ false }>
					<SelectControl __nextHasNoMarginBottom label={ __( 'Template', 'rayetun-media-protection' ) } value={ template || 'card' } options={ [ { label: __( 'Card (row)', 'rayetun-media-protection' ), value: 'card' }, { label: __( 'Tile (stacked)', 'rayetun-media-protection' ), value: 'tile' } ] } onChange={ ( v ) => setAttributes( { template: v } ) } />
					<p style={ { margin: '8px 0 4px' } }>{ __( 'Accent color', 'rayetun-media-protection' ) }</p>
					<ColorPalette value={ accent } onChange={ ( v ) => setAttributes( { accent: v || '#059669' } ) } />
				</PanelBody>
			</InspectorControls>

			{ ! fileId && (
				<MediaUploadCheck>
					<MediaUpload
						onSelect={ onSelect }
						render={ ( { open } ) => (
							<div className="mg-download-editor__placeholder">
								<p>{ __( 'Protected Download', 'rayetun-media-protection' ) }</p>
								<Button variant="primary" onClick={ open }>{ __( 'Choose a file', 'rayetun-media-protection' ) }</Button>
							</div>
						) }
					/>
				</MediaUploadCheck>
			) }

			{ !! fileId && (
				<div className={ `mg-download mg-download--${ template || 'card' }` } style={ { '--mg-accent': accent || '#059669' } }>
					<FileIcon mime={ mime } />
					<div className="mg-download__meta">
						<span className="mg-download__name">{ name || `#${ attachmentId }` }</span>
						{ showInfo && <span className="mg-download__info">{ shortMime( mime ) }{ mime ? ' · ' : '' }{ formatBytes( size ) }</span> }
					</div>
					<span className="mg-download__button">{ label || __( 'Download', 'rayetun-media-protection' ) }</span>
					{ busy && <Spinner /> }
				</div>
			) }
		</div>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
