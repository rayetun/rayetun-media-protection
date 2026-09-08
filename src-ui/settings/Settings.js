/**
 * Settings screen — tabbed: General, Uploads, Access control, Security, Tools,
 * and an About/uninstall transparency panel.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect, useRef } from '@wordpress/element';
import {
	TabPanel,
	Spinner,
	SelectControl,
	__experimentalNumberControl as NumberControl,
	CheckboxControl,
	ToggleControl,
	Button,
	Notice,
	Snackbar,
} from '@wordpress/components';
import PageHeader from '../components/PageHeader';
import { mgFetch } from '../app/api';

function applyTheme( theme ) {
	const root = document.documentElement;
	if ( theme === 'light' || theme === 'dark' ) {
		root.setAttribute( 'data-mg-theme', theme );
	} else {
		root.removeAttribute( 'data-mg-theme' );
	}
	// Persist per-browser so the choice survives a reload immediately (the
	// Save button also syncs it to the server for other browsers/devices).
	try {
		window.localStorage.setItem( 'mg-theme', theme );
	} catch ( e ) {
		/* localStorage unavailable — the server save still persists it. */
	}
}

export default function Settings() {
	const [ data, setData ] = useState( null );
	const [ notice, setNotice ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const importRef = useRef( null );

	const load = () => mgFetch( '/settings' ).then( setData );
	useEffect( () => {
		load();
	}, [] );

	const post = async ( body, msg ) => {
		setSaving( true );
		try {
			const res = await mgFetch( '/settings', { method: 'POST', data: body } );
			setData( res );
			setNotice( msg || __( 'Saved.', 'rayetun-media-protection' ) );
		} catch ( e ) {
			setNotice( __( 'Could not save.', 'rayetun-media-protection' ) );
		} finally {
			setSaving( false );
		}
	};

	if ( ! data ) {
		return (
			<>
				<PageHeader title={ __( 'Settings', 'rayetun-media-protection' ) } />
				<div className="mg-card mg-placeholder"><Spinner /></div>
			</>
		);
	}

	const TABS = [
		{ name: 'general', title: __( 'General', 'rayetun-media-protection' ) },
		{ name: 'uploads', title: __( 'Uploads', 'rayetun-media-protection' ) },
		{ name: 'access', title: __( 'Access control', 'rayetun-media-protection' ) },
		{ name: 'security', title: __( 'Security', 'rayetun-media-protection' ) },
		{ name: 'tools', title: __( 'Tools', 'rayetun-media-protection' ) },
		{ name: 'about', title: __( 'About', 'rayetun-media-protection' ) },
	];

	return (
		<>
			<PageHeader
				title={ __( 'Settings', 'rayetun-media-protection' ) }
				subtitle={ __( 'Theme, retention, access control, and maintenance.', 'rayetun-media-protection' ) }
			/>
			<div className="mg-card">
				<TabPanel className="mg-tabs" tabs={ TABS }>
					{ ( tab ) => {
						if ( tab.name === 'general' ) {
							return <GeneralTab data={ data } post={ post } saving={ saving } />;
						}
						if ( tab.name === 'uploads' ) {
							return <UploadsTab data={ data } post={ post } saving={ saving } />;
						}
						if ( tab.name === 'access' ) {
							return <AccessTab data={ data } post={ post } saving={ saving } />;
						}
						if ( tab.name === 'security' ) {
							return <SecurityTab setNotice={ setNotice } />;
						}
						if ( tab.name === 'tools' ) {
							return <ToolsTab importRef={ importRef } reload={ load } setNotice={ setNotice } />;
						}
						return <AboutTab />;
					} }
				</TabPanel>
			</div>

			{ notice && <Snackbar onRemove={ () => setNotice( null ) }>{ notice }</Snackbar> }
		</>
	);
}

function GeneralTab( { data, post, saving } ) {
	const [ theme, setTheme ] = useState( data.general.theme );
	const [ days, setDays ] = useState( data.general.logRetentionDays );

	return (
		<div className="mg-form mg-tab-body">
			<SelectControl
				__nextHasNoMarginBottom
				label={ __( 'Admin theme', 'rayetun-media-protection' ) }
				value={ theme }
				options={ [
					{ label: __( 'Follow system', 'rayetun-media-protection' ), value: 'auto' },
					{ label: __( 'Light', 'rayetun-media-protection' ), value: 'light' },
					{ label: __( 'Dark', 'rayetun-media-protection' ), value: 'dark' },
				] }
				onChange={ ( v ) => {
					setTheme( v );
					applyTheme( v );
				} }
			/>
			<NumberControl
				__next40pxDefaultSize
				label={ __( 'Keep download logs for (days)', 'rayetun-media-protection' ) }
				min={ 1 }
				max={ 3650 }
				value={ days }
				onChange={ ( v ) => setDays( parseInt( v, 10 ) || 90 ) }
			/>
			<div>
				<Button variant="primary" isBusy={ saving } onClick={ () => post( { group: 'general', theme, logRetentionDays: days } ) }>
					{ __( 'Save general settings', 'rayetun-media-protection' ) }
				</Button>
			</div>
		</div>
	);
}

function UploadsTab( { data, post, saving } ) {
	const [ policyId, setPolicyId ] = useState( String( data.uploads.autoProtectPolicyId ) );
	const cd = data.cleanDownloads || { enabled: false, policyId: 0 };
	const [ cleanEnabled, setCleanEnabled ] = useState( !! cd.enabled );
	const [ cleanPolicy, setCleanPolicy ] = useState( String( cd.policyId || 0 ) );

	const protectOptions = [
		{ label: __( 'Public link — anyone with the link', 'rayetun-media-protection' ), value: '0' },
		...data.policies.map( ( p ) => ( { label: p.name, value: String( p.id ) } ) ),
	];
	const cleanOptions = [
		{ label: __( 'Logged-in users', 'rayetun-media-protection' ), value: '0' },
		...data.policies.map( ( p ) => ( { label: p.name, value: String( p.id ) } ) ),
	];

	return (
		<div className="mg-form mg-tab-body">
			<SelectControl
				__nextHasNoMarginBottom
				label={ __( 'Default policy for auto-protected uploads', 'rayetun-media-protection' ) }
				help={ __( 'When “Auto-protect uploads” is on, new non-image files get this access policy.', 'rayetun-media-protection' ) }
				value={ policyId }
				options={ protectOptions }
				onChange={ setPolicyId }
			/>
			<div>
				<Button variant="primary" isBusy={ saving } onClick={ () => post( { group: 'uploads', autoProtectPolicyId: parseInt( policyId, 10 ) } ) }>
					{ __( 'Save upload settings', 'rayetun-media-protection' ) }
				</Button>
			</div>

			<hr className="mg-divider" />

			<span className="mg-field__label">{ __( 'Clean-copy downloads', 'rayetun-media-protection' ) }</span>
			<p className="mg-field__hint">
				{ __( 'Show the watermarked image to everyone, but let authorized visitors download the clean, un-watermarked original. Works with auto-watermarking: when an image is watermarked on upload, its clean original is saved to protected storage. Applies to new uploads.', 'rayetun-media-protection' ) }
			</p>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Offer clean-copy downloads', 'rayetun-media-protection' ) }
				checked={ cleanEnabled }
				onChange={ setCleanEnabled }
			/>
			{ cleanEnabled && (
				<SelectControl
					__nextHasNoMarginBottom
					label={ __( 'Who can download the clean original', 'rayetun-media-protection' ) }
					help={ __( 'The clean original is served through a signed, access-controlled link.', 'rayetun-media-protection' ) }
					value={ cleanPolicy }
					options={ cleanOptions }
					onChange={ setCleanPolicy }
				/>
			) }
			<p className="mg-field__hint">
				{ __( 'Place the download button in any post or page with this shortcode (use the media attachment ID):', 'rayetun-media-protection' ) }
				{ ' ' }
				<code>[markguard_clean_download id=&quot;123&quot;]</code>
			</p>
			<div>
				<Button variant="primary" isBusy={ saving } onClick={ () => post( { group: 'cleanDownloads', enabled: cleanEnabled, policyId: parseInt( cleanPolicy, 10 ) } ) }>
					{ __( 'Save clean-copy settings', 'rayetun-media-protection' ) }
				</Button>
			</div>
		</div>
	);
}

function AccessTab( { data, post, saving } ) {
	const [ manage, setManage ] = useState( data.capabilities.manage );
	const [ analytics, setAnalytics ] = useState( data.capabilities.analytics );

	const toggle = ( list, setList, slug, on ) => {
		const set = new Set( list );
		on ? set.add( slug ) : set.delete( slug );
		setList( [ ...set ] );
	};

	return (
		<div className="mg-form mg-tab-body">
			<p className="mg-field__hint">{ __( 'Choose which roles can manage RayEtun Media Protection and view analytics. Administrators always have full access.', 'rayetun-media-protection' ) }</p>
			<div className="mg-caps-grid">
				<div>
					<span className="mg-field__label">{ __( 'Can manage RayEtun Media Protection', 'rayetun-media-protection' ) }</span>
					{ Object.entries( data.roles ).map( ( [ slug, label ] ) => (
						<CheckboxControl
							__nextHasNoMarginBottom
							key={ slug }
							label={ label }
							disabled={ slug === 'administrator' }
							checked={ manage.includes( slug ) }
							onChange={ ( on ) => toggle( manage, setManage, slug, on ) }
						/>
					) ) }
				</div>
				<div>
					<span className="mg-field__label">{ __( 'Can view analytics', 'rayetun-media-protection' ) }</span>
					{ Object.entries( data.roles ).map( ( [ slug, label ] ) => (
						<CheckboxControl
							__nextHasNoMarginBottom
							key={ slug }
							label={ label }
							disabled={ slug === 'administrator' }
							checked={ analytics.includes( slug ) }
							onChange={ ( on ) => toggle( analytics, setAnalytics, slug, on ) }
						/>
					) ) }
				</div>
			</div>
			<div>
				<Button variant="primary" isBusy={ saving } onClick={ () => post( { group: 'capabilities', manage, analytics } ) }>
					{ __( 'Save access control', 'rayetun-media-protection' ) }
				</Button>
			</div>
		</div>
	);
}

function SecurityTab( { setNotice } ) {
	const [ busy, setBusy ] = useState( false );
	const rotate = async () => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( __( 'Rotate the signing key? Every existing download link will stop working immediately.', 'rayetun-media-protection' ) ) ) {
			return;
		}
		setBusy( true );
		try {
			await mgFetch( '/settings/rotate-salt', { method: 'POST' } );
			setNotice( __( 'Signing key rotated. All old links are now invalid.', 'rayetun-media-protection' ) );
		} finally {
			setBusy( false );
		}
	};
	return (
		<div className="mg-form mg-tab-body">
			<div>
				<span className="mg-field__label">{ __( 'Signing key', 'rayetun-media-protection' ) }</span>
				<p className="mg-field__hint">{ __( 'Every download link is signed with this key. Rotating it instantly invalidates all previously shared links — useful if a link leaked.', 'rayetun-media-protection' ) }</p>
			</div>
			<div>
				<Button variant="secondary" isDestructive isBusy={ busy } onClick={ rotate }>
					{ __( 'Rotate signing key', 'rayetun-media-protection' ) }
				</Button>
			</div>
		</div>
	);
}

function ToolsTab( { importRef, reload, setNotice } ) {
	const doExport = async () => {
		const bundle = await mgFetch( '/settings/export' );
		const blob = new Blob( [ JSON.stringify( bundle, null, 2 ) ], { type: 'application/json' } );
		const url = URL.createObjectURL( blob );
		const a = document.createElement( 'a' );
		a.href = url;
		a.download = 'markguard-settings.json';
		document.body.appendChild( a );
		a.click();
		a.remove();
		URL.revokeObjectURL( url );
	};

	const onFile = ( e ) => {
		const file = e.target.files?.[ 0 ];
		if ( ! file ) return;
		const reader = new FileReader();
		reader.onload = async () => {
			try {
				const bundle = JSON.parse( reader.result );
				const res = await mgFetch( '/settings/import', { method: 'POST', data: bundle } );
				setNotice(
					sprintf(
						/* translators: 1: number of presets imported, 2: number of policies imported */
						__( 'Imported %1$d presets and %2$d policies.', 'rayetun-media-protection' ),
						res.imported.presets,
						res.imported.policies
					)
				);
				reload();
			} catch ( err ) {
				setNotice( __( 'That file could not be imported.', 'rayetun-media-protection' ) );
			}
		};
		reader.readAsText( file );
		e.target.value = '';
	};

	return (
		<div className="mg-form mg-tab-body">
			<div>
				<span className="mg-field__label">{ __( 'Export', 'rayetun-media-protection' ) }</span>
				<p className="mg-field__hint">{ __( 'Download your watermark presets, access policies, and settings as a JSON file.', 'rayetun-media-protection' ) }</p>
				<Button variant="secondary" onClick={ doExport }>{ __( 'Export settings', 'rayetun-media-protection' ) }</Button>
			</div>
			<div>
				<span className="mg-field__label">{ __( 'Import', 'rayetun-media-protection' ) }</span>
				<p className="mg-field__hint">{ __( 'Restore presets and policies from an exported file. Existing items are kept; imported ones are added.', 'rayetun-media-protection' ) }</p>
				<input ref={ importRef } type="file" accept="application/json" style={ { display: 'none' } } onChange={ onFile } />
				<Button variant="secondary" onClick={ () => importRef.current?.click() }>{ __( 'Import settings', 'rayetun-media-protection' ) }</Button>
			</div>
		</div>
	);
}

function AboutTab() {
	return (
		<div className="mg-tab-body">
			<p>{ __( 'RayEtun Media Protection stores everything in your own WordPress database and uploads folder. There is no telemetry, no account, and no external service.', 'rayetun-media-protection' ) }</p>
			<span className="mg-field__label">{ __( 'What uninstalling removes', 'rayetun-media-protection' ) }</span>
			<ul className="mg-list">
				<li>{ __( 'RayEtun Media Protection’s database tables (protected files, access log, presets, policies)', 'rayetun-media-protection' ) }</li>
				<li>{ __( 'All RayEtun Media Protection options and capabilities', 'rayetun-media-protection' ) }</li>
				<li>{ __( 'The protected-uploads directory and its contents', 'rayetun-media-protection' ) }</li>
			</ul>
			<p className="mg-field__hint">{ __( 'Your posts, pages, and original media are never touched.', 'rayetun-media-protection' ) }</p>
		</div>
	);
}
