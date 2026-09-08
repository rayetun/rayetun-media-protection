/**
 * Protected Gallery — block editor. Pick images, then tune the JSON-backed
 * config (layout, columns, overlay, watermark preset, download policy, lightbox,
 * filters). The front end is server-rendered; this is an approximate preview.
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
	Notice,
	Spinner,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import metadata from './block.json';
import './style.css';
import './editor.css';

const BASE = {
	columns: 3,
	gap: 12,
	layout: 'grid',
	radius: 8,
	thumbMax: 500,
	previewMax: 1400,
	presetId: 0,
	policyId: 0,
	accent: '#059669',
	overlay: { style: 'grid', opacity: 0.12, color: '#ffffff' },
	lightbox: {
		showTitle: true,
		showDownload: true,
		downloadLabel: __( 'Download original', 'rayetun-media-protection' ),
		loginText: __( 'Log in to download the original file.', 'rayetun-media-protection' ),
	},
	filters: { enabled: false, allLabel: __( 'All', 'rayetun-media-protection' ) },
};

function mergeConfig( config ) {
	return {
		...BASE,
		...config,
		overlay: { ...BASE.overlay, ...( config.overlay || {} ) },
		lightbox: { ...BASE.lightbox, ...( config.lightbox || {} ) },
		filters: { ...BASE.filters, ...( config.filters || {} ) },
	};
}

function Edit( { attributes, setAttributes } ) {
	const { items, config } = attributes;
	const blockProps = useBlockProps( { className: 'mg-gallery-editor' } );
	const [ meta, setMeta ] = useState( null );
	const cfg = mergeConfig( config );

	useEffect( () => {
		apiFetch( { path: 'markguard/v1/gallery/defaults' } )
			.then( ( d ) => {
				setMeta( d );
				if ( ! config || Object.keys( config ).length === 0 ) {
					setAttributes( { config: d.config } );
				}
			} )
			.catch( () => setMeta( { layouts: [ { slug: 'grid', label: 'Grid' } ], overlayStyles: [ 'grid', 'diagonal', 'dots', 'none' ], presets: [], policies: [] } ) );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const setCfg = ( patch ) => setAttributes( { config: { ...cfg, ...patch } } );
	const setNested = ( key, patch ) => setAttributes( { config: { ...cfg, [ key ]: { ...cfg[ key ], ...patch } } } );

	const onSelect = ( media ) => {
		const prev = {};
		items.forEach( ( i ) => ( prev[ i.id ] = i.filters || [] ) );
		setAttributes( {
			items: media.map( ( m ) => ( {
				id: m.id,
				url: ( m.sizes && m.sizes.thumbnail && m.sizes.thumbnail.url ) || m.url,
				title: m.title || '',
				filters: prev[ m.id ] || [],
			} ) ),
		} );
	};

	const setItemFilters = ( id, tokens ) => {
		const filters = ( tokens || [] ).map( ( t ) => String( t ).trim() ).filter( Boolean );
		setAttributes( { items: items.map( ( i ) => ( i.id === id ? { ...i, filters } : i ) ) } );
	};

	// Suggestions for the token field: every category already used in this
	// gallery, so tagging stays consistent (autocomplete instead of retyping).
	const allTags = [ ...new Set( items.reduce( ( acc, i ) => acc.concat( i.filters || [] ), [] ) ) ];

	const gridStyle = {
		'--mg-gallery-cols': cfg.columns,
		'--mg-gallery-gap': `${ cfg.gap }px`,
		'--mg-gallery-radius': `${ cfg.radius }px`,
		'--mg-gallery-overlay-color': cfg.overlay.color,
		'--mg-gallery-overlay-opacity': cfg.overlay.opacity,
		'--mg-accent': cfg.accent,
	};

	const presetOptions = meta
		? [ { label: __( 'Default upload preset', 'rayetun-media-protection' ), value: '0' }, ...meta.presets.map( ( p ) => ( { label: p.name, value: String( p.id ) } ) ) ]
		: [];
	const policyOptions = meta
		? [
			{ label: __( 'Everyone', 'rayetun-media-protection' ), value: '-1' },
			{ label: __( 'Logged-in users', 'rayetun-media-protection' ), value: '0' },
			...meta.policies.map( ( p ) => ( { label: p.name, value: String( p.id ) } ) ),
		]
		: [];
	const layoutOptions = meta ? meta.layouts.map( ( l ) => ( { label: l.label, value: l.slug } ) ) : [];
	const overlayOptions = meta ? meta.overlayStyles.map( ( s ) => ( { label: s, value: s } ) ) : [];

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Layout', 'rayetun-media-protection' ) } initialOpen={ true }>
					{ layoutOptions.length > 1 && (
						<SelectControl __nextHasNoMarginBottom label={ __( 'Layout', 'rayetun-media-protection' ) } value={ cfg.layout } options={ layoutOptions } onChange={ ( v ) => setCfg( { layout: v } ) } />
					) }
					<RangeControl __nextHasNoMarginBottom label={ __( 'Columns', 'rayetun-media-protection' ) } min={ 1 } max={ 8 } value={ cfg.columns } onChange={ ( v ) => setCfg( { columns: v } ) } />
					<RangeControl __nextHasNoMarginBottom label={ __( 'Gap (px)', 'rayetun-media-protection' ) } min={ 0 } max={ 48 } value={ cfg.gap } onChange={ ( v ) => setCfg( { gap: v } ) } />
					<RangeControl __nextHasNoMarginBottom label={ __( 'Corner radius (px)', 'rayetun-media-protection' ) } min={ 0 } max={ 32 } value={ cfg.radius } onChange={ ( v ) => setCfg( { radius: v } ) } />
					<p style={ { margin: '8px 0 4px' } }>{ __( 'Accent color (buttons, filters)', 'rayetun-media-protection' ) }</p>
					<ColorPalette value={ cfg.accent } onChange={ ( v ) => setCfg( { accent: v || '#059669' } ) } />
				</PanelBody>

				<PanelBody title={ __( 'Protection', 'rayetun-media-protection' ) } initialOpen={ false }>
					<SelectControl __nextHasNoMarginBottom label={ __( 'Watermark preset (lightbox preview)', 'rayetun-media-protection' ) } value={ String( cfg.presetId ) } options={ presetOptions } onChange={ ( v ) => setCfg( { presetId: parseInt( v, 10 ) } ) } />
					{ meta && meta.presets.length === 0 && cfg.presetId === 0 && (
						<Notice status="warning" isDismissible={ false }>{ __( 'Create a watermark preset (Watermarks screen) so the large preview can be watermarked.', 'rayetun-media-protection' ) }</Notice>
					) }
					<SelectControl __nextHasNoMarginBottom label={ __( 'Who can download the original', 'rayetun-media-protection' ) } value={ String( cfg.policyId ) } options={ policyOptions } onChange={ ( v ) => setCfg( { policyId: parseInt( v, 10 ) } ) } />
				</PanelBody>

				<PanelBody title={ __( 'Thumbnail overlay', 'rayetun-media-protection' ) } initialOpen={ false }>
					<SelectControl __nextHasNoMarginBottom label={ __( 'Overlay style', 'rayetun-media-protection' ) } value={ cfg.overlay.style } options={ overlayOptions } onChange={ ( v ) => setNested( 'overlay', { style: v } ) } />
					<RangeControl __nextHasNoMarginBottom label={ __( 'Overlay opacity', 'rayetun-media-protection' ) } min={ 0 } max={ 1 } step={ 0.02 } value={ cfg.overlay.opacity } onChange={ ( v ) => setNested( 'overlay', { opacity: v } ) } />
					<p style={ { margin: '8px 0 4px' } }>{ __( 'Overlay color', 'rayetun-media-protection' ) }</p>
					<ColorPalette value={ cfg.overlay.color } onChange={ ( v ) => setNested( 'overlay', { color: v || '#ffffff' } ) } />
				</PanelBody>

				<PanelBody title={ __( 'Lightbox', 'rayetun-media-protection' ) } initialOpen={ false }>
					<ToggleControl __nextHasNoMarginBottom label={ __( 'Show image title', 'rayetun-media-protection' ) } checked={ cfg.lightbox.showTitle } onChange={ ( v ) => setNested( 'lightbox', { showTitle: v } ) } />
					<ToggleControl __nextHasNoMarginBottom label={ __( 'Show download button', 'rayetun-media-protection' ) } checked={ cfg.lightbox.showDownload } onChange={ ( v ) => setNested( 'lightbox', { showDownload: v } ) } />
					<TextControl __nextHasNoMarginBottom label={ __( 'Download button label', 'rayetun-media-protection' ) } value={ cfg.lightbox.downloadLabel } onChange={ ( v ) => setNested( 'lightbox', { downloadLabel: v } ) } />
					<TextControl __nextHasNoMarginBottom label={ __( 'Locked message', 'rayetun-media-protection' ) } value={ cfg.lightbox.loginText } onChange={ ( v ) => setNested( 'lightbox', { loginText: v } ) } />
				</PanelBody>

				<PanelBody title={ __( 'Filters', 'rayetun-media-protection' ) } initialOpen={ false }>
					<ToggleControl __nextHasNoMarginBottom label={ __( 'Enable filter bar', 'rayetun-media-protection' ) } checked={ cfg.filters.enabled } onChange={ ( v ) => setNested( 'filters', { enabled: v } ) } />
					{ cfg.filters.enabled && (
						<>
							<TextControl __nextHasNoMarginBottom label={ __( '“All” label', 'rayetun-media-protection' ) } value={ cfg.filters.allLabel } onChange={ ( v ) => setNested( 'filters', { allLabel: v } ) } />
							<p className="mg-gallery-editor__hint">{ __( 'Give each image one or more categories to build the filter bar. Type a category and press Enter (or comma); reuse suggestions to keep them consistent.', 'rayetun-media-protection' ) }</p>
							{ items.map( ( i ) => (
								<div className="mg-gallery-editor__tagrow" key={ i.id }>
									<div className="mg-gallery-editor__taghead">
										<img className="mg-gallery-editor__tagthumb" src={ i.url } alt="" />
										<span className="mg-gallery-editor__tagtitle">{ i.title || `#${ i.id }` }</span>
									</div>
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

			{ ! meta && <div className="mg-gallery-editor__loading"><Spinner /></div> }

			{ meta && items.length === 0 && (
				<MediaUploadCheck>
					<MediaUpload
						multiple
						gallery
						allowedTypes={ [ 'image' ] }
						onSelect={ onSelect }
						render={ ( { open } ) => (
							<div className="mg-gallery-editor__placeholder">
								<p>{ __( 'Protected Gallery', 'rayetun-media-protection' ) }</p>
								<Button variant="primary" onClick={ open }>{ __( 'Select images', 'rayetun-media-protection' ) }</Button>
							</div>
						) }
					/>
				</MediaUploadCheck>
			) }

			{ meta && items.length > 0 && (
				<>
					<ul className={ `mg-gallery__grid mg-gallery--${ cfg.layout }` } style={ gridStyle } data-overlay-style={ cfg.overlay.style }>
						{ items.map( ( i ) => (
							<li className="mg-gallery__cell" key={ i.id }>
								<span className="mg-gallery__item">
									<img className="mg-gallery__thumb" src={ i.url } alt={ i.title } draggable="false" />
									{ cfg.overlay.style !== 'none' && <span className="mg-gallery__overlay" aria-hidden="true" /> }
								</span>
							</li>
						) ) }
					</ul>
					<MediaUploadCheck>
						<MediaUpload
							multiple
							gallery
							allowedTypes={ [ 'image' ] }
							value={ items.map( ( i ) => i.id ) }
							onSelect={ onSelect }
							render={ ( { open } ) => (
								<Button variant="secondary" onClick={ open } className="mg-gallery-editor__edit">{ __( 'Edit images', 'rayetun-media-protection' ) }</Button>
							) }
						/>
					</MediaUploadCheck>
				</>
			) }
		</div>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
