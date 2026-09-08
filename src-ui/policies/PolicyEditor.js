/**
 * Access policy editor modal. Login / roles / max-downloads / hotlink host,
 * with a live plain-English summary.
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import {
	Modal,
	TextControl,
	ToggleControl,
	CheckboxControl,
	__experimentalNumberControl as NumberControl,
	Button,
} from '@wordpress/components';
import { policyPhrase } from '../components/PolicySummary';

const DEFAULT_RULE = {
	requireLogin: false,
	allowedRoles: [],
	maxClicks: 0,
	allowedHost: '',
};

export default function PolicyEditor( { policy, roles, onClose, onSave, saving } ) {
	const [ name, setName ] = useState( policy?.name || '' );
	const [ rule, setRule ] = useState( { ...DEFAULT_RULE, ...( policy?.rule || {} ) } );

	const update = ( patch ) => setRule( ( r ) => ( { ...r, ...patch } ) );

	const toggleRole = ( slug, on ) => {
		const set = new Set( rule.allowedRoles );
		if ( on ) {
			set.add( slug );
		} else {
			set.delete( slug );
		}
		update( { allowedRoles: [ ...set ] } );
	};

	const save = () => onSave( { name: name.trim() || __( 'Untitled policy', 'rayetun-media-protection' ), rule } );

	return (
		<Modal
			title={ policy ? __( 'Edit access policy', 'rayetun-media-protection' ) : __( 'New access policy', 'rayetun-media-protection' ) }
			onRequestClose={ onClose }
			className="mg-editor-modal mg-editor-modal--narrow"
		>
			<div className="mg-form">
				<TextControl
					__nextHasNoMarginBottom
					label={ __( 'Policy name', 'rayetun-media-protection' ) }
					value={ name }
					onChange={ setName }
					placeholder={ __( 'e.g. Members only', 'rayetun-media-protection' ) }
				/>

				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Require login', 'rayetun-media-protection' ) }
					help={ __( 'Only logged-in users may download.', 'rayetun-media-protection' ) }
					checked={ rule.requireLogin }
					onChange={ ( v ) => update( { requireLogin: v } ) }
				/>

				<div className="mg-field">
					<span className="mg-field__label">{ __( 'Allowed roles', 'rayetun-media-protection' ) }</span>
					<p className="mg-field__hint">
						{ __( 'If none are checked, any logged-in user (or anyone, if login is not required) may download.', 'rayetun-media-protection' ) }
					</p>
					<div className="mg-roles">
						{ Object.entries( roles || {} ).map( ( [ slug, label ] ) => (
							<CheckboxControl
								__nextHasNoMarginBottom
								key={ slug }
								label={ label }
								checked={ rule.allowedRoles.includes( slug ) }
								onChange={ ( on ) => toggleRole( slug, on ) }
							/>
						) ) }
					</div>
				</div>

				<NumberControl
					__next40pxDefaultSize
					label={ __( 'Max downloads per link (0 = unlimited)', 'rayetun-media-protection' ) }
					min={ 0 }
					value={ rule.maxClicks }
					onChange={ ( v ) => update( { maxClicks: parseInt( v, 10 ) || 0 } ) }
				/>

				<TextControl
					__nextHasNoMarginBottom
					label={ __( 'Hotlink host (optional)', 'rayetun-media-protection' ) }
					help={ __( 'Only allow the file to be embedded from this domain. Leave blank to allow any.', 'rayetun-media-protection' ) }
					value={ rule.allowedHost }
					onChange={ ( v ) => update( { allowedHost: v } ) }
					placeholder="example.com"
				/>

				<div className="mg-summary-box">
					<span className="mg-field__label">{ __( 'This policy allows', 'rayetun-media-protection' ) }</span>
					<p className="mg-summary-box__text">{ policyPhrase( rule, roles ) }.</p>
				</div>
			</div>

			<div className="mg-editor__footer">
				<Button variant="tertiary" onClick={ onClose }>{ __( 'Cancel', 'rayetun-media-protection' ) }</Button>
				<Button variant="primary" onClick={ save } isBusy={ saving } disabled={ saving }>
					{ __( 'Save policy', 'rayetun-media-protection' ) }
				</Button>
			</div>
		</Modal>
	);
}
