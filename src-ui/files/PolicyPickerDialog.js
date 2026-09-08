/**
 * Choose which access policy applies to a file (or make it a public link).
 * Used both when protecting a new file and when changing an existing file's
 * policy.
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { Modal, SelectControl, Button } from '@wordpress/components';

export default function PolicyPickerDialog( { title, filename, policies, current = 0, confirmLabel, onConfirm, onClose, busy } ) {
	const [ policyId, setPolicyId ] = useState( String( current ) );

	const options = [
		{ label: __( 'Public link — anyone with the link', 'rayetun-media-protection' ), value: '0' },
		...( policies || [] ).map( ( p ) => ( { label: p.name, value: String( p.id ) } ) ),
	];

	return (
		<Modal title={ title } onRequestClose={ onClose } className="mg-editor-modal mg-editor-modal--narrow">
			<div className="mg-form">
				{ filename && <p className="mg-field__hint">{ filename }</p> }
				<SelectControl
					__nextHasNoMarginBottom
					label={ __( 'Access policy', 'rayetun-media-protection' ) }
					value={ policyId }
					options={ options }
					onChange={ setPolicyId }
				/>
				{ ( policies || [] ).length === 0 && (
					<p className="mg-field__hint">
						{ __( 'Tip: create reusable policies under Access Policies to require login or limit downloads.', 'rayetun-media-protection' ) }
					</p>
				) }
			</div>
			<div className="mg-editor__footer">
				<Button variant="tertiary" onClick={ onClose }>{ __( 'Cancel', 'rayetun-media-protection' ) }</Button>
				<Button variant="primary" onClick={ () => onConfirm( parseInt( policyId, 10 ) ) } isBusy={ busy } disabled={ busy }>
					{ confirmLabel || __( 'Apply', 'rayetun-media-protection' ) }
				</Button>
			</div>
		</Modal>
	);
}
