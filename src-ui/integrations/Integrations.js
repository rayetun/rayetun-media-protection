/**
 * Integrations screen — shows which e-commerce plugins are active and controls
 * the shared e-commerce watermark (on/off + which preset to stamp).
 */
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { Spinner, ToggleControl, SelectControl, Notice, Snackbar } from '@wordpress/components';
import PageHeader from '../components/PageHeader';
import { mgFetch } from '../app/api';

export default function Integrations() {
	const [ data, setData ] = useState( null );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		mgFetch( '/integrations' ).then( setData );
	}, [] );

	const save = async ( enabled, presetId ) => {
		setData( ( d ) => ( { ...d, config: { enabled, presetId } } ) );
		try {
			const res = await mgFetch( '/integrations', { method: 'POST', data: { enabled, presetId } } );
			setData( ( d ) => ( { ...d, config: res } ) );
			setNotice( __( 'Saved.', 'rayetun-media-protection' ) );
		} catch ( e ) {
			setNotice( __( 'Could not save.', 'rayetun-media-protection' ) );
		}
	};

	if ( ! data ) {
		return (
			<>
				<PageHeader title={ __( 'Integrations', 'rayetun-media-protection' ) } />
				<div className="mg-card mg-placeholder"><Spinner /></div>
			</>
		);
	}

	const { enabled, presetId } = data.config;
	const presetOptions = [
		{ label: __( '— Select a preset —', 'rayetun-media-protection' ), value: '0' },
		...data.presets.map( ( p ) => ( { label: p.name, value: String( p.id ) } ) ),
	];

	return (
		<>
			<PageHeader
				title={ __( 'Integrations', 'rayetun-media-protection' ) }
				subtitle={ __( 'Automatically watermark downloads sold through your store.', 'rayetun-media-protection' ) }
			/>

			<h2 className="mg-section-title">{ __( 'Detected plugins', 'rayetun-media-protection' ) }</h2>
			<div className="mg-grid mg-grid--features">
				{ data.integrations.map( ( i ) => (
					<div key={ i.id } className="mg-card mg-feature-card">
						<div className="mg-feature-card__head">
							<h3 className="mg-feature-card__title">{ i.label }</h3>
							<span className={ `mg-badge ${ i.active ? '' : 'mg-badge--muted' }` }>
								{ i.active ? __( 'Active', 'rayetun-media-protection' ) : __( 'Not installed', 'rayetun-media-protection' ) }
							</span>
						</div>
						<p className="mg-feature-card__desc">
							{ i.active
								? __( 'Downloads from this plugin will be watermarked when e-commerce watermarking is on.', 'rayetun-media-protection' )
								: __( 'Install and activate this plugin to watermark its downloads.', 'rayetun-media-protection' ) }
						</p>
					</div>
				) ) }
			</div>

			<h2 className="mg-section-title">{ __( 'E-commerce watermark', 'rayetun-media-protection' ) }</h2>
			<div className="mg-card mg-form">
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Stamp a watermark on each buyer’s downloads', 'rayetun-media-protection' ) }
					help={ __( 'Uses tokens like the buyer’s email and order number for traceable delivery.', 'rayetun-media-protection' ) }
					checked={ enabled }
					onChange={ ( v ) => save( v, presetId ) }
				/>

				{ enabled && data.presets.length === 0 && (
					<Notice status="warning" isDismissible={ false }>
						{ __( 'Create a watermark preset first (Watermarks → New preset), then choose it here.', 'rayetun-media-protection' ) }
					</Notice>
				) }

				{ enabled && data.presets.length > 0 && (
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Watermark preset to apply', 'rayetun-media-protection' ) }
						value={ String( presetId ) }
						options={ presetOptions }
						onChange={ ( v ) => save( enabled, parseInt( v, 10 ) ) }
					/>
				) }

				{ enabled && presetId === 0 && data.presets.length > 0 && (
					<p className="mg-field__hint">{ __( 'Choose a preset above, or any legacy inline watermark will be used.', 'rayetun-media-protection' ) }</p>
				) }
			</div>

			{ notice && <Snackbar onRemove={ () => setNotice( null ) }>{ notice }</Snackbar> }
		</>
	);
}
