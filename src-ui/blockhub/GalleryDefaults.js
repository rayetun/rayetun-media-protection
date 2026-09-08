/**
 * Site-wide default settings for the Protected Gallery block, edited in the
 * Blocks hub. Mirrors the block inspector so defaults and per-block overrides
 * feel identical. Persists through /gallery/defaults.
 */
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import {
	Spinner,
	SelectControl,
	RangeControl,
	ToggleControl,
	TextControl,
	ColorPalette,
	Button,
	Snackbar,
} from '@wordpress/components';
import { mgFetch } from '../app/api';

export default function GalleryDefaults() {
	const [ meta, setMeta ] = useState( null );
	const [ cfg, setCfg ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		mgFetch( '/gallery/defaults' ).then( ( d ) => {
			setMeta( d );
			setCfg( d.config );
		} );
	}, [] );

	if ( ! cfg || ! meta ) {
		return <div className="mg-placeholder"><Spinner /></div>;
	}

	const set = ( patch ) => setCfg( { ...cfg, ...patch } );
	const setN = ( key, patch ) => setCfg( { ...cfg, [ key ]: { ...cfg[ key ], ...patch } } );

	const presetOptions = [ { label: __( 'Default upload preset', 'rayetun-media-protection' ), value: '0' }, ...meta.presets.map( ( p ) => ( { label: p.name, value: String( p.id ) } ) ) ];
	const policyOptions = [
		{ label: __( 'Everyone', 'rayetun-media-protection' ), value: '-1' },
		{ label: __( 'Logged-in users', 'rayetun-media-protection' ), value: '0' },
		...meta.policies.map( ( p ) => ( { label: p.name, value: String( p.id ) } ) ),
	];
	const layoutOptions = meta.layouts.map( ( l ) => ( { label: l.label, value: l.slug } ) );
	const overlayOptions = meta.overlayStyles.map( ( s ) => ( { label: s, value: s } ) );

	const save = async () => {
		setSaving( true );
		try {
			const r = await mgFetch( '/gallery/defaults', { method: 'POST', data: { config: cfg } } );
			setCfg( r.config );
			setNotice( __( 'Saved.', 'rayetun-media-protection' ) );
		} catch ( e ) {
			setNotice( __( 'Could not save.', 'rayetun-media-protection' ) );
		} finally {
			setSaving( false );
		}
	};

	return (
		<div className="mg-form">
			{ layoutOptions.length > 1 && (
				<SelectControl __nextHasNoMarginBottom label={ __( 'Layout', 'rayetun-media-protection' ) } value={ cfg.layout } options={ layoutOptions } onChange={ ( v ) => set( { layout: v } ) } />
			) }
			<RangeControl __nextHasNoMarginBottom label={ __( 'Columns', 'rayetun-media-protection' ) } min={ 1 } max={ 8 } value={ cfg.columns } onChange={ ( v ) => set( { columns: v } ) } />
			<RangeControl __nextHasNoMarginBottom label={ __( 'Gap (px)', 'rayetun-media-protection' ) } min={ 0 } max={ 48 } value={ cfg.gap } onChange={ ( v ) => set( { gap: v } ) } />
			<RangeControl __nextHasNoMarginBottom label={ __( 'Corner radius (px)', 'rayetun-media-protection' ) } min={ 0 } max={ 32 } value={ cfg.radius } onChange={ ( v ) => set( { radius: v } ) } />

			<SelectControl __nextHasNoMarginBottom label={ __( 'Overlay style', 'rayetun-media-protection' ) } value={ cfg.overlay.style } options={ overlayOptions } onChange={ ( v ) => setN( 'overlay', { style: v } ) } />
			<RangeControl __nextHasNoMarginBottom label={ __( 'Overlay opacity', 'rayetun-media-protection' ) } min={ 0 } max={ 1 } step={ 0.02 } value={ cfg.overlay.opacity } onChange={ ( v ) => setN( 'overlay', { opacity: v } ) } />
			<div>
				<span className="mg-field__label">{ __( 'Overlay color', 'rayetun-media-protection' ) }</span>
				<ColorPalette value={ cfg.overlay.color } onChange={ ( v ) => setN( 'overlay', { color: v || '#ffffff' } ) } />
			</div>
			<div>
				<span className="mg-field__label">{ __( 'Accent color', 'rayetun-media-protection' ) }</span>
				<ColorPalette value={ cfg.accent } onChange={ ( v ) => set( { accent: v || '#059669' } ) } />
			</div>

			<SelectControl __nextHasNoMarginBottom label={ __( 'Watermark preset (lightbox preview)', 'rayetun-media-protection' ) } value={ String( cfg.presetId ) } options={ presetOptions } onChange={ ( v ) => set( { presetId: parseInt( v, 10 ) } ) } />
			<SelectControl __nextHasNoMarginBottom label={ __( 'Who can download the original', 'rayetun-media-protection' ) } value={ String( cfg.policyId ) } options={ policyOptions } onChange={ ( v ) => set( { policyId: parseInt( v, 10 ) } ) } />

			<ToggleControl __nextHasNoMarginBottom label={ __( 'Lightbox: show image title', 'rayetun-media-protection' ) } checked={ cfg.lightbox.showTitle } onChange={ ( v ) => setN( 'lightbox', { showTitle: v } ) } />
			<ToggleControl __nextHasNoMarginBottom label={ __( 'Lightbox: show download button', 'rayetun-media-protection' ) } checked={ cfg.lightbox.showDownload } onChange={ ( v ) => setN( 'lightbox', { showDownload: v } ) } />
			<TextControl __nextHasNoMarginBottom label={ __( 'Download button label', 'rayetun-media-protection' ) } value={ cfg.lightbox.downloadLabel } onChange={ ( v ) => setN( 'lightbox', { downloadLabel: v } ) } />
			<TextControl __nextHasNoMarginBottom label={ __( 'Locked message', 'rayetun-media-protection' ) } value={ cfg.lightbox.loginText } onChange={ ( v ) => setN( 'lightbox', { loginText: v } ) } />

			<ToggleControl __nextHasNoMarginBottom label={ __( 'Enable filter bar by default', 'rayetun-media-protection' ) } checked={ cfg.filters.enabled } onChange={ ( v ) => setN( 'filters', { enabled: v } ) } />
			<TextControl __nextHasNoMarginBottom label={ __( '“All” filter label', 'rayetun-media-protection' ) } value={ cfg.filters.allLabel } onChange={ ( v ) => setN( 'filters', { allLabel: v } ) } />

			<div>
				<Button variant="primary" isBusy={ saving } onClick={ save }>{ __( 'Save gallery defaults', 'rayetun-media-protection' ) }</Button>
			</div>

			{ notice && <Snackbar onRemove={ () => setNotice( null ) }>{ notice }</Snackbar> }
		</div>
	);
}
