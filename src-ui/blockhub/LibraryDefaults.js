/**
 * Site-wide default settings for the Protected File Library block, edited in the
 * Blocks hub. Persists through /blocks/defaults.
 */
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { Spinner, SelectControl, RangeControl, ToggleControl, TextControl, ColorPalette, Button, Snackbar } from '@wordpress/components';
import { mgFetch } from '../app/api';

export default function LibraryDefaults() {
	const [ data, setData ] = useState( null );
	const [ cfg, setCfg ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		mgFetch( '/blocks/defaults' ).then( ( d ) => {
			setData( d );
			setCfg( d.library );
		} );
	}, [] );

	if ( ! cfg || ! data ) {
		return <div className="mg-placeholder"><Spinner /></div>;
	}

	const set = ( patch ) => setCfg( { ...cfg, ...patch } );
	const setN = ( key, patch ) => setCfg( { ...cfg, [ key ]: { ...cfg[ key ], ...patch } } );
	const policyOptions = [
		{ label: __( 'Everyone', 'rayetun-media-protection' ), value: '-1' },
		{ label: __( 'Logged-in users', 'rayetun-media-protection' ), value: '0' },
		...data.policies.map( ( p ) => ( { label: p.name, value: String( p.id ) } ) ),
	];

	const save = async () => {
		setSaving( true );
		try {
			const r = await mgFetch( '/blocks/defaults', { method: 'POST', data: { block: 'library', config: cfg } } );
			setCfg( r.library );
			setNotice( __( 'Saved.', 'rayetun-media-protection' ) );
		} catch ( e ) {
			setNotice( __( 'Could not save.', 'rayetun-media-protection' ) );
		} finally {
			setSaving( false );
		}
	};

	return (
		<div className="mg-form">
			<SelectControl __nextHasNoMarginBottom label={ __( 'Template', 'rayetun-media-protection' ) } value={ cfg.template } options={ [ { label: __( 'Card (row)', 'rayetun-media-protection' ), value: 'card' }, { label: __( 'Tile (stacked)', 'rayetun-media-protection' ), value: 'tile' } ] } onChange={ ( v ) => set( { template: v } ) } />
			<RangeControl __nextHasNoMarginBottom label={ __( 'Columns', 'rayetun-media-protection' ) } min={ 1 } max={ 6 } value={ cfg.columns } onChange={ ( v ) => set( { columns: v } ) } />
			<RangeControl __nextHasNoMarginBottom label={ __( 'Gap (px)', 'rayetun-media-protection' ) } min={ 0 } max={ 48 } value={ cfg.gap } onChange={ ( v ) => set( { gap: v } ) } />
			<SelectControl __nextHasNoMarginBottom label={ __( 'Who can download', 'rayetun-media-protection' ) } value={ String( cfg.policyId ) } options={ policyOptions } onChange={ ( v ) => set( { policyId: parseInt( v, 10 ) } ) } />
			<ToggleControl __nextHasNoMarginBottom label={ __( 'Show file info (type · size)', 'rayetun-media-protection' ) } checked={ cfg.showInfo } onChange={ ( v ) => set( { showInfo: v } ) } />
			<TextControl __nextHasNoMarginBottom label={ __( 'Download button label', 'rayetun-media-protection' ) } value={ cfg.labels.download } onChange={ ( v ) => setN( 'labels', { download: v } ) } />
			<TextControl __nextHasNoMarginBottom label={ __( 'Locked message', 'rayetun-media-protection' ) } value={ cfg.labels.locked } onChange={ ( v ) => setN( 'labels', { locked: v } ) } />
			<ToggleControl __nextHasNoMarginBottom label={ __( 'Enable filter bar by default', 'rayetun-media-protection' ) } checked={ cfg.filters.enabled } onChange={ ( v ) => setN( 'filters', { enabled: v } ) } />
			<TextControl __nextHasNoMarginBottom label={ __( '“All” filter label', 'rayetun-media-protection' ) } value={ cfg.filters.allLabel } onChange={ ( v ) => setN( 'filters', { allLabel: v } ) } />
			<div>
				<span className="mg-field__label">{ __( 'Accent color', 'rayetun-media-protection' ) }</span>
				<ColorPalette value={ cfg.accent } onChange={ ( v ) => set( { accent: v || '#059669' } ) } />
			</div>
			<div>
				<Button variant="primary" isBusy={ saving } onClick={ save }>{ __( 'Save library defaults', 'rayetun-media-protection' ) }</Button>
			</div>
			{ notice && <Snackbar onRemove={ () => setNotice( null ) }>{ notice }</Snackbar> }
		</div>
	);
}
