/**
 * Watermarks screen — list of reusable presets with create/edit/duplicate/
 * delete, backed by the presets REST controller.
 */
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { Button, Spinner, Snackbar } from '@wordpress/components';
import PageHeader from '../components/PageHeader';
import PresetEditor from './PresetEditor';
import { mgFetch } from '../app/api';

function summary( rule ) {
	if ( rule.type === 'image' ) {
		return __( 'Image overlay', 'rayetun-media-protection' );
	}
	const text = rule.text || __( '(no text)', 'rayetun-media-protection' );
	return text.length > 42 ? text.slice( 0, 42 ) + '…' : text;
}

export default function Watermarks() {
	const [ presets, setPresets ] = useState( null );
	const [ editing, setEditing ] = useState( null ); // preset object, or {} for new
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	const load = () => mgFetch( '/watermark-presets' ).then( ( r ) => setPresets( r.presets ) );

	useEffect( () => {
		load();
	}, [] );

	const save = async ( payload ) => {
		setSaving( true );
		try {
			if ( editing && editing.id ) {
				await mgFetch( `/watermark-presets/${ editing.id }`, { method: 'PUT', data: { id: editing.id, ...payload } } );
			} else {
				await mgFetch( '/watermark-presets', { method: 'POST', data: payload } );
			}
			setEditing( null );
			await load();
			setNotice( __( 'Preset saved.', 'rayetun-media-protection' ) );
		} catch ( e ) {
			setNotice( __( 'Could not save the preset.', 'rayetun-media-protection' ) );
		} finally {
			setSaving( false );
		}
	};

	const remove = async ( id ) => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( __( 'Delete this preset?', 'rayetun-media-protection' ) ) ) {
			return;
		}
		await mgFetch( `/watermark-presets/${ id }`, { method: 'DELETE', data: { id } } );
		await load();
		setNotice( __( 'Preset deleted.', 'rayetun-media-protection' ) );
	};

	const duplicate = ( preset ) => {
		setEditing( {
			name: preset.name + __( ' (copy)', 'rayetun-media-protection' ),
			rule: preset.rule,
			isDefaultUpload: false,
		} );
	};

	return (
		<>
			<PageHeader
				title={ __( 'Watermarks', 'rayetun-media-protection' ) }
				subtitle={ __( 'Design reusable watermark presets, then apply them to uploads, files, and store downloads.', 'rayetun-media-protection' ) }
				actions={
					<Button variant="primary" onClick={ () => setEditing( {} ) }>
						{ __( 'New preset', 'rayetun-media-protection' ) }
					</Button>
				}
			/>

			{ ! presets && (
				<div className="mg-card mg-placeholder">
					<Spinner />
				</div>
			) }

			{ presets && presets.length === 0 && (
				<div className="mg-card mg-placeholder">
					<span className="mg-placeholder__badge">{ __( 'No presets yet', 'rayetun-media-protection' ) }</span>
					<p>{ __( 'Create your first watermark preset to brand images and stamp PDFs.', 'rayetun-media-protection' ) }</p>
					<Button variant="primary" onClick={ () => setEditing( {} ) }>
						{ __( 'Create a preset', 'rayetun-media-protection' ) }
					</Button>
				</div>
			) }

			{ presets && presets.length > 0 && (
				<div className="mg-grid mg-grid--presets">
					{ presets.map( ( p ) => (
						<div key={ p.id } className="mg-card mg-preset-card">
							<div className="mg-preset-card__head">
								<h3 className="mg-preset-card__name">{ p.name }</h3>
								{ p.isDefaultUpload && (
									<span className="mg-badge">{ __( 'Upload default', 'rayetun-media-protection' ) }</span>
								) }
							</div>
							<p className="mg-preset-card__summary">{ summary( p.rule ) }</p>
							<div className="mg-preset-card__meta">
								<span>{ p.rule.type === 'image' ? __( 'Image', 'rayetun-media-protection' ) : __( 'Text', 'rayetun-media-protection' ) }</span>
								<span>·</span>
								<span>{ p.rule.position }</span>
								<span>·</span>
								<span>{ p.rule.opacity }%</span>
							</div>
							<div className="mg-preset-card__actions">
								<Button variant="secondary" size="small" onClick={ () => setEditing( p ) }>
									{ __( 'Edit', 'rayetun-media-protection' ) }
								</Button>
								<Button variant="tertiary" size="small" onClick={ () => duplicate( p ) }>
									{ __( 'Duplicate', 'rayetun-media-protection' ) }
								</Button>
								<Button variant="tertiary" size="small" isDestructive onClick={ () => remove( p.id ) }>
									{ __( 'Delete', 'rayetun-media-protection' ) }
								</Button>
							</div>
						</div>
					) ) }
				</div>
			) }

			{ editing && (
				<PresetEditor
					preset={ editing.id ? editing : ( editing.rule ? editing : null ) }
					onClose={ () => setEditing( null ) }
					onSave={ save }
					saving={ saving }
				/>
			) }

			{ notice && <Snackbar onRemove={ () => setNotice( null ) }>{ notice }</Snackbar> }
		</>
	);
}
