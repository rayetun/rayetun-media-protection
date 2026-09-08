/**
 * Protected File Library — block editor. Pick multiple files; each is registered
 * as a protected file with the chosen policy and served through a signed link on
 * the front end. Optional per-file categories drive a filter bar.
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
	RangeControl,
	SelectControl,
	ToggleControl,
	TextControl,
	FormTokenField,
	ColorPalette,
	Button,
	Spinner,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import metadata from './block.json';
import { FileIcon } from '../shared/FileIcon';
import './style.css';
import './editor.css';

const BASE = {
	columns: 2,
	gap: 12,
	showInfo: true,
	policyId: 0,
	accent: '#059669',
	template: 'card',
	labels: { download: __( 'Download', 'rayetun-media-protection' ), locked: __( 'Log in to download', 'rayetun-media-protection' ) },
	filters: { enabled: false, allLabel: __( 'All', 'rayetun-media-protection' ) },
};

function mergeConfig( config ) {
	return {
		...BASE,
		...config,
		labels: { ...BASE.labels, ...( config.labels || {} ) },
		filters: { ...BASE.filters, ...( config.filters || {} ) },
	};
}

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
	const { items, config, inited } = attributes;
	const blockProps = useBlockProps( { className: 'mg-library-editor' } );
	const [ policies, setPolicies ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const cfg = mergeConfig( config );

	useEffect( () => {
		apiFetch( { path: 'markguard/v1/blocks/defaults' } )
			.then( ( d ) => {
				setPolicies( d.policies || [] );
				// A brand-new block inherits the site-wide defaults once.
				if ( ! inited && items.length === 0 && d.library ) {
					setAttributes( { config: d.library, inited: true } );
				}
			} )
			.catch( () => setPolicies( [] ) );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const setCfg = ( patch ) => setAttributes( { config: { ...cfg, ...patch } } );
	const setNested = ( key, patch ) => setAttributes( { config: { ...cfg, [ key ]: { ...cfg[ key ], ...patch } } } );

	const resolveItems = async ( list, policyId ) => {
		setBusy( true );
		try {
			const r = await apiFetch( {
				path: 'markguard/v1/blocks/library/resolve',
				method: 'POST',
				data: { items: list.map( ( i ) => ( { attachmentId: i.id, fileId: i.fileId || 0 } ) ), policyId },
			} );
			const byId = {};
			( r.items || [] ).forEach( ( x ) => ( byId[ x.attachmentId ] = x ) );
			return list.map( ( i ) => {
				const x = byId[ i.id ];
				return x ? { ...i, fileId: x.fileId, name: x.name, size: x.size, mime: x.mime } : i;
			} );
		} catch ( e ) {
			return list;
		} finally {
			setBusy( false );
		}
	};

	const onSelect = async ( media ) => {
		const list = Array.isArray( media ) ? media : [ media ];
		const prev = {};
		items.forEach( ( i ) => ( prev[ i.id ] = i ) );
		const base = list.map( ( m ) => ( {
			id: m.id,
			fileId: prev[ m.id ]?.fileId || 0,
			filters: prev[ m.id ]?.filters || [],
			name: m.title || m.filename || '',
			size: prev[ m.id ]?.size || 0,
			mime: prev[ m.id ]?.mime || '',
		} ) );
		setAttributes( { items: base } );
		setAttributes( { items: await resolveItems( base, cfg.policyId ) } );
	};

	const onPolicy = async ( v ) => {
		const p = parseInt( v, 10 );
		setCfg( { policyId: p } );
		if ( items.length ) {
			setAttributes( { items: await resolveItems( items, p ) } );
		}
	};

	const setItemFilters = ( id, tokens ) => {
		const filters = ( tokens || [] ).map( ( t ) => String( t ).trim() ).filter( Boolean );
		setAttributes( { items: items.map( ( i ) => ( i.id === id ? { ...i, filters } : i ) ) } );
	};

	const allTags = [ ...new Set( items.reduce( ( acc, i ) => acc.concat( i.filters || [] ), [] ) ) ];
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
				<PanelBody title={ __( 'Layout', 'rayetun-media-protection' ) } initialOpen={ true }>
					<SelectControl __nextHasNoMarginBottom label={ __( 'Template', 'rayetun-media-protection' ) } value={ cfg.template } options={ [ { label: __( 'Card (row)', 'rayetun-media-protection' ), value: 'card' }, { label: __( 'Tile (stacked)', 'rayetun-media-protection' ), value: 'tile' } ] } onChange={ ( v ) => setCfg( { template: v } ) } />
					<RangeControl __nextHasNoMarginBottom label={ __( 'Columns', 'rayetun-media-protection' ) } min={ 1 } max={ 6 } value={ cfg.columns } onChange={ ( v ) => setCfg( { columns: v } ) } />
					<RangeControl __nextHasNoMarginBottom label={ __( 'Gap (px)', 'rayetun-media-protection' ) } min={ 0 } max={ 40 } value={ cfg.gap } onChange={ ( v ) => setCfg( { gap: v } ) } />
					<ToggleControl __nextHasNoMarginBottom label={ __( 'Show file info (type · size)', 'rayetun-media-protection' ) } checked={ cfg.showInfo } onChange={ ( v ) => setCfg( { showInfo: v } ) } />
					<p style={ { margin: '8px 0 4px' } }>{ __( 'Accent color', 'rayetun-media-protection' ) }</p>
					<ColorPalette value={ cfg.accent } onChange={ ( v ) => setCfg( { accent: v || '#059669' } ) } />
				</PanelBody>
				<PanelBody title={ __( 'Protection', 'rayetun-media-protection' ) } initialOpen={ false }>
					<SelectControl __nextHasNoMarginBottom label={ __( 'Who can download', 'rayetun-media-protection' ) } value={ String( cfg.policyId ) } options={ policyOptions } onChange={ onPolicy } />
					<TextControl __nextHasNoMarginBottom label={ __( 'Download button label', 'rayetun-media-protection' ) } value={ cfg.labels.download } onChange={ ( v ) => setNested( 'labels', { download: v } ) } />
					<TextControl __nextHasNoMarginBottom label={ __( 'Locked message', 'rayetun-media-protection' ) } value={ cfg.labels.locked } onChange={ ( v ) => setNested( 'labels', { locked: v } ) } />
				</PanelBody>
				<PanelBody title={ __( 'Filters', 'rayetun-media-protection' ) } initialOpen={ false }>
					<ToggleControl __nextHasNoMarginBottom label={ __( 'Enable filter bar', 'rayetun-media-protection' ) } checked={ cfg.filters.enabled } onChange={ ( v ) => setNested( 'filters', { enabled: v } ) } />
					{ cfg.filters.enabled && (
						<>
							<TextControl __nextHasNoMarginBottom label={ __( '“All” label', 'rayetun-media-protection' ) } value={ cfg.filters.allLabel } onChange={ ( v ) => setNested( 'filters', { allLabel: v } ) } />
							<p className="mg-library-editor__hint">{ __( 'Give each file one or more categories. Press Enter or comma after each.', 'rayetun-media-protection' ) }</p>
							{ items.map( ( i ) => (
								<div className="mg-library-editor__tagrow" key={ i.id }>
									<span className="mg-library-editor__tagtitle">{ i.name || `#${ i.id }` }</span>
									<FormTokenField
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										label={ __( 'Categories', 'rayetun-media-protection' ) }
										value={ i.filters || [] }
										suggestions={ allTags }
										onChange={ ( tokens ) => setItemFilters( i.id, tokens ) }
									/>
								</div>
							) ) }
						</>
					) }
				</PanelBody>
			</InspectorControls>

			{ items.length === 0 && (
				<MediaUploadCheck>
					<MediaUpload
						multiple
						onSelect={ onSelect }
						render={ ( { open } ) => (
							<div className="mg-library-editor__placeholder">
								<p>{ __( 'Protected File Library', 'rayetun-media-protection' ) }</p>
								<Button variant="primary" onClick={ open }>{ __( 'Select files', 'rayetun-media-protection' ) }</Button>
								<span className="mg-library-editor__hint">{ __( 'Tip: hold Ctrl (⌘ on Mac) to pick several files at once.', 'rayetun-media-protection' ) }</span>
							</div>
						) }
					/>
				</MediaUploadCheck>
			) }

			{ items.length > 0 && (
				<>
					<div
						className={ `mg-library mg-library--${ cfg.template }` }
						style={ { '--mg-library-cols': cfg.columns, '--mg-library-gap': `${ cfg.gap }px`, '--mg-accent': cfg.accent } }
					>
						<ul className="mg-library__grid">
							{ items.map( ( i ) => (
								<li className="mg-library__cell" key={ i.id }>
									<div className="mg-download">
										<FileIcon mime={ i.mime } />
										<div className="mg-download__meta">
											<span className="mg-download__name">{ i.name || `#${ i.id }` }</span>
											{ cfg.showInfo && <span className="mg-download__info">{ shortMime( i.mime ) }{ i.mime ? ' · ' : '' }{ formatBytes( i.size ) }</span> }
										</div>
										<span className="mg-download__button">{ cfg.labels.download }</span>
									</div>
								</li>
							) ) }
						</ul>
					</div>
					<MediaUploadCheck>
						<MediaUpload
							multiple
							value={ items.map( ( i ) => i.id ) }
							onSelect={ onSelect }
							render={ ( { open } ) => (
								<Button variant="secondary" className="mg-library-editor__edit" onClick={ open }>{ __( 'Edit files', 'rayetun-media-protection' ) }</Button>
							) }
						/>
					</MediaUploadCheck>
					{ busy && <Spinner /> }
				</>
			) }
		</div>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
