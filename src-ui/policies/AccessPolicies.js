/**
 * Access Policies screen — reusable named policies with CRUD.
 */
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { Button, Spinner, Snackbar } from '@wordpress/components';
import PageHeader from '../components/PageHeader';
import PolicyEditor from './PolicyEditor';
import { policyPhrase } from '../components/PolicySummary';
import { mgFetch } from '../app/api';

export default function AccessPolicies() {
	const [ policies, setPolicies ] = useState( null );
	const [ roles, setRoles ] = useState( {} );
	const [ editing, setEditing ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	const load = () =>
		mgFetch( '/access-policies' ).then( ( r ) => {
			setPolicies( r.policies );
			setRoles( r.roles );
		} );

	useEffect( () => {
		load();
	}, [] );

	const save = async ( payload ) => {
		setSaving( true );
		try {
			if ( editing && editing.id ) {
				await mgFetch( `/access-policies/${ editing.id }`, { method: 'PUT', data: { id: editing.id, ...payload } } );
			} else {
				await mgFetch( '/access-policies', { method: 'POST', data: payload } );
			}
			setEditing( null );
			await load();
			setNotice( __( 'Policy saved.', 'rayetun-media-protection' ) );
		} catch ( e ) {
			setNotice( __( 'Could not save the policy.', 'rayetun-media-protection' ) );
		} finally {
			setSaving( false );
		}
	};

	const remove = async ( id ) => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( __( 'Delete this policy? Files using it become public links.', 'rayetun-media-protection' ) ) ) {
			return;
		}
		await mgFetch( `/access-policies/${ id }`, { method: 'DELETE', data: { id } } );
		await load();
		setNotice( __( 'Policy deleted.', 'rayetun-media-protection' ) );
	};

	return (
		<>
			<PageHeader
				title={ __( 'Access Policies', 'rayetun-media-protection' ) }
				subtitle={ __( 'Reusable rules for who can download a file and how many times.', 'rayetun-media-protection' ) }
				actions={
					<Button variant="primary" onClick={ () => setEditing( {} ) }>
						{ __( 'New policy', 'rayetun-media-protection' ) }
					</Button>
				}
			/>

			{ ! policies && (
				<div className="mg-card mg-placeholder"><Spinner /></div>
			) }

			{ policies && policies.length === 0 && (
				<div className="mg-card mg-placeholder">
					<span className="mg-placeholder__badge">{ __( 'No policies yet', 'rayetun-media-protection' ) }</span>
					<p>{ __( 'Create a policy to control who can download your protected files.', 'rayetun-media-protection' ) }</p>
					<Button variant="primary" onClick={ () => setEditing( {} ) }>
						{ __( 'Create a policy', 'rayetun-media-protection' ) }
					</Button>
				</div>
			) }

			{ policies && policies.length > 0 && (
				<div className="mg-grid mg-grid--presets">
					{ policies.map( ( p ) => (
						<div key={ p.id } className="mg-card mg-preset-card">
							<h3 className="mg-preset-card__name">{ p.name }</h3>
							<p className="mg-preset-card__summary">{ policyPhrase( p.rule, roles ) }.</p>
							<div className="mg-preset-card__actions">
								<Button variant="secondary" size="small" onClick={ () => setEditing( p ) }>
									{ __( 'Edit', 'rayetun-media-protection' ) }
								</Button>
								<Button variant="tertiary" size="small" onClick={ () => setEditing( { name: p.name + __( ' (copy)', 'rayetun-media-protection' ), rule: p.rule } ) }>
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
				<PolicyEditor
					policy={ editing.id ? editing : ( editing.rule ? editing : null ) }
					roles={ roles }
					onClose={ () => setEditing( null ) }
					onSave={ save }
					saving={ saving }
				/>
			) }

			{ notice && <Snackbar onRemove={ () => setNotice( null ) }>{ notice }</Snackbar> }
		</>
	);
}
