/**
 * Protected Files screen — list managed files with policy, activity, and
 * actions (change policy, create link, delete). Protect new files from the
 * media library.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { Button, Spinner, Snackbar } from '@wordpress/components';
import PageHeader from '../components/PageHeader';
import LinkDialog from './LinkDialog';
import PolicyPickerDialog from './PolicyPickerDialog';
import { mgFetch } from '../app/api';

function formatBytes( n ) {
	if ( ! n ) return '0 B';
	const u = [ 'B', 'KB', 'MB', 'GB' ];
	const i = Math.floor( Math.log( n ) / Math.log( 1024 ) );
	return ( n / Math.pow( 1024, i ) ).toFixed( i ? 1 : 0 ) + ' ' + u[ i ];
}
function formatDate( mysqlUtc ) {
	if ( ! mysqlUtc ) return __( 'Never', 'rayetun-media-protection' );
	const d = new Date( mysqlUtc.replace( ' ', 'T' ) + 'Z' );
	if ( isNaN( d ) ) return mysqlUtc;
	return d.toLocaleDateString( undefined, { year: 'numeric', month: 'short', day: 'numeric' } );
}
function shortMime( m ) {
	if ( ! m ) return '—';
	if ( m === 'application/pdf' ) return 'PDF';
	if ( m.startsWith( 'image/' ) ) return m.replace( 'image/', '' ).toUpperCase();
	return m.split( '/' ).pop().toUpperCase();
}

export default function ProtectedFiles() {
	const [ data, setData ] = useState( null );
	const [ search, setSearch ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ linkFile, setLinkFile ] = useState( null );
	const [ policyFile, setPolicyFile ] = useState( null ); // existing file, change policy
	const [ protecting, setProtecting ] = useState( null ); // { attachmentId, name } awaiting policy
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	const load = ( p = page, s = search ) =>
		mgFetch( `/files?page=${ p }&per_page=20&search=${ encodeURIComponent( s ) }` ).then( setData );

	useEffect( () => {
		load( 1, search );
		setPage( 1 );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ search ] );

	const chooseFile = () => {
		const frame = window.wp.media( {
			title: __( 'Choose a file to protect', 'rayetun-media-protection' ),
			button: { text: __( 'Protect this file', 'rayetun-media-protection' ) },
			multiple: false,
		} );
		frame.on( 'select', () => {
			const att = frame.state().get( 'selection' ).first().toJSON();
			setProtecting( { attachmentId: att.id, name: att.filename || att.title } );
		} );
		frame.open();
	};

	const doProtect = async ( policyId ) => {
		setBusy( true );
		try {
			await mgFetch( '/files/protect', { method: 'POST', data: { attachmentId: protecting.attachmentId, policyId } } );
			setProtecting( null );
			await load();
			setNotice( __( 'File protected.', 'rayetun-media-protection' ) );
		} catch ( e ) {
			setNotice( __( 'Could not protect that file.', 'rayetun-media-protection' ) );
		} finally {
			setBusy( false );
		}
	};

	const changePolicy = async ( policyId ) => {
		setBusy( true );
		try {
			await mgFetch( `/files/${ policyFile.id }`, { method: 'PATCH', data: { policyId, custom: {} } } );
			setPolicyFile( null );
			await load();
			setNotice( __( 'Policy updated.', 'rayetun-media-protection' ) );
		} finally {
			setBusy( false );
		}
	};

	const remove = async ( file ) => {
		// eslint-disable-next-line no-alert
		/* translators: %s: file name */
		if ( ! window.confirm( sprintf( __( 'Delete “%s” from protected storage? This cannot be undone.', 'rayetun-media-protection' ), file.name ) ) ) {
			return;
		}
		await mgFetch( `/files/${ file.id }`, { method: 'DELETE', data: { id: file.id } } );
		await load();
		setNotice( __( 'File deleted.', 'rayetun-media-protection' ) );
	};

	const totalPages = data ? Math.max( 1, Math.ceil( data.total / data.perPage ) ) : 1;

	return (
		<>
			<PageHeader
				title={ __( 'Protected Files', 'rayetun-media-protection' ) }
				subtitle={ __( 'Files served only through signed links, with per-file access control.', 'rayetun-media-protection' ) }
				actions={
					<Button variant="primary" onClick={ chooseFile }>
						{ __( 'Protect a file', 'rayetun-media-protection' ) }
					</Button>
				}
			/>

			<div style={ { maxWidth: 360, marginBottom: 16 } }>
				<input
					type="search"
					className="mg-search"
					value={ search }
					onChange={ ( e ) => setSearch( e.target.value ) }
					placeholder={ __( 'Search files…', 'rayetun-media-protection' ) }
					aria-label={ __( 'Search files', 'rayetun-media-protection' ) }
				/>
			</div>

			{ ! data && <div className="mg-card mg-placeholder"><Spinner /></div> }

			{ data && data.files.length === 0 && (
				<div className="mg-card mg-placeholder">
					<span className="mg-placeholder__badge">{ __( 'No protected files', 'rayetun-media-protection' ) }</span>
					<p>{ __( 'Protect a file to serve it only through signed, access-controlled links.', 'rayetun-media-protection' ) }</p>
					<Button variant="primary" onClick={ chooseFile }>{ __( 'Protect a file', 'rayetun-media-protection' ) }</Button>
				</div>
			) }

			{ data && data.files.length > 0 && (
				<div className="mg-card mg-table-wrap">
					<table className="mg-table">
						<thead>
							<tr>
								<th>{ __( 'File', 'rayetun-media-protection' ) }</th>
								<th>{ __( 'Type', 'rayetun-media-protection' ) }</th>
								<th>{ __( 'Size', 'rayetun-media-protection' ) }</th>
								<th>{ __( 'Policy', 'rayetun-media-protection' ) }</th>
								<th className="mg-num">{ __( 'Served', 'rayetun-media-protection' ) }</th>
								<th className="mg-num">{ __( 'Blocked', 'rayetun-media-protection' ) }</th>
								<th>{ __( 'Last access', 'rayetun-media-protection' ) }</th>
								<th>{ __( 'Actions', 'rayetun-media-protection' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ data.files.map( ( f ) => (
								<tr key={ f.id }>
									<td className="mg-table__name">{ f.name }</td>
									<td>{ shortMime( f.mime ) }</td>
									<td>{ formatBytes( f.size ) }</td>
									<td><span className="mg-badge mg-badge--muted">{ f.policyLabel }</span></td>
									<td className="mg-num">{ f.activity.served }</td>
									<td className="mg-num">{ f.activity.blocked > 0 ? <span className="mg-danger-text">{ f.activity.blocked }</span> : 0 }</td>
									<td>{ formatDate( f.activity.last ) }</td>
									<td className="mg-actions-cell">
										<div className="mg-row-actions">
											<Button variant="secondary" size="small" onClick={ () => setLinkFile( f ) }>{ __( 'Link', 'rayetun-media-protection' ) }</Button>
											<Button variant="tertiary" size="small" onClick={ () => setPolicyFile( f ) }>{ __( 'Policy', 'rayetun-media-protection' ) }</Button>
											<Button variant="tertiary" size="small" isDestructive onClick={ () => remove( f ) }>{ __( 'Delete', 'rayetun-media-protection' ) }</Button>
										</div>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>

					{ totalPages > 1 && (
						<div className="mg-pager">
							<Button size="small" variant="secondary" disabled={ page <= 1 } onClick={ () => { const p = page - 1; setPage( p ); load( p ); } }>
								{ __( 'Previous', 'rayetun-media-protection' ) }
							</Button>
							<span>
							{
								/* translators: 1: current page number, 2: total number of pages */
								sprintf( __( 'Page %1$d of %2$d', 'rayetun-media-protection' ), page, totalPages )
							}
						</span>
							<Button size="small" variant="secondary" disabled={ page >= totalPages } onClick={ () => { const p = page + 1; setPage( p ); load( p ); } }>
								{ __( 'Next', 'rayetun-media-protection' ) }
							</Button>
						</div>
					) }
				</div>
			) }

			{ linkFile && <LinkDialog file={ linkFile } onClose={ () => setLinkFile( null ) } /> }

			{ policyFile && (
				<PolicyPickerDialog
					title={ __( 'Change access policy', 'rayetun-media-protection' ) }
					filename={ policyFile.name }
					policies={ data.policies }
					current={ policyFile.policyId }
					confirmLabel={ __( 'Update policy', 'rayetun-media-protection' ) }
					onConfirm={ changePolicy }
					onClose={ () => setPolicyFile( null ) }
					busy={ busy }
				/>
			) }

			{ protecting && (
				<PolicyPickerDialog
					title={ __( 'Protect file', 'rayetun-media-protection' ) }
					filename={ protecting.name }
					policies={ data ? data.policies : [] }
					confirmLabel={ __( 'Protect file', 'rayetun-media-protection' ) }
					onConfirm={ doProtect }
					onClose={ () => setProtecting( null ) }
					busy={ busy }
				/>
			) }

			{ notice && <Snackbar onRemove={ () => setNotice( null ) }>{ notice }</Snackbar> }
		</>
	);
}
