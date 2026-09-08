/**
 * Two-pane watermark preset editor: controls on the left, live preview on the
 * right. Renders inside a Modal.
 */
import { __ } from '@wordpress/i18n';
import { useState, useRef } from '@wordpress/element';
import {
	Modal,
	TextControl,
	RangeControl,
	ToggleControl,
	Button,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	Flex,
	FlexBlock,
} from '@wordpress/components';
import PositionGrid from '../components/PositionGrid';
import TokenInserter from '../components/TokenInserter';
import LivePreview from './LivePreview';

export const DEFAULT_CONFIG = {
	type: 'text',
	text: '© {site} — {user_email}',
	overlayId: 0,
	overlayPath: '',
	position: 'bottom-right',
	offsetX: 20,
	offsetY: 20,
	offsetUnit: 'px',
	opacity: 60,
	rotation: 0,
	fontSize: 28,
	fontColor: '#FFFFFF',
	imageScale: 0.2,
};

export default function PresetEditor( { preset, onClose, onSave, saving } ) {
	const [ name, setName ] = useState( preset?.name || '' );
	const [ isDefault, setIsDefault ] = useState( !! preset?.isDefaultUpload );
	const [ cfg, setCfg ] = useState( { ...DEFAULT_CONFIG, ...( preset?.rule || {} ) } );
	const textRef = useRef( null );

	const update = ( patch ) => setCfg( ( c ) => ( { ...c, ...patch } ) );

	const insertToken = ( token ) => {
		const el = textRef.current;
		if ( ! el ) {
			update( { text: cfg.text + token } );
			return;
		}
		const start = el.selectionStart ?? cfg.text.length;
		const end = el.selectionEnd ?? cfg.text.length;
		const next = cfg.text.slice( 0, start ) + token + cfg.text.slice( end );
		update( { text: next } );
		requestAnimationFrame( () => {
			el.focus();
			el.selectionStart = el.selectionEnd = start + token.length;
		} );
	};

	const chooseImage = () => {
		const frame = window.wp.media( {
			title: __( 'Choose overlay image', 'rayetun-media-protection' ),
			button: { text: __( 'Use this image', 'rayetun-media-protection' ) },
			library: { type: 'image' },
			multiple: false,
		} );
		frame.on( 'select', () => {
			const att = frame.state().get( 'selection' ).first().toJSON();
			update( { overlayId: att.id, overlayPath: att.url } );
		} );
		frame.open();
	};

	const save = () => onSave( { name: name.trim() || __( 'Untitled preset', 'rayetun-media-protection' ), rule: cfg, isDefaultUpload: isDefault } );

	return (
		<Modal
			title={ preset ? __( 'Edit watermark preset', 'rayetun-media-protection' ) : __( 'New watermark preset', 'rayetun-media-protection' ) }
			onRequestClose={ onClose }
			className="mg-editor-modal"
			shouldCloseOnClickOutside={ false }
		>
			<div className="mg-editor">
				<div className="mg-editor__controls">
					<TextControl
						__nextHasNoMarginBottom
						label={ __( 'Preset name', 'rayetun-media-protection' ) }
						value={ name }
						onChange={ setName }
						placeholder={ __( 'e.g. Buyer stamp', 'rayetun-media-protection' ) }
					/>

					<ToggleGroupControl
						__nextHasNoMarginBottom
						label={ __( 'Type', 'rayetun-media-protection' ) }
						value={ cfg.type }
						onChange={ ( v ) => update( { type: v } ) }
						isBlock
					>
						<ToggleGroupControlOption value="text" label={ __( 'Text', 'rayetun-media-protection' ) } />
						<ToggleGroupControlOption value="image" label={ __( 'Image', 'rayetun-media-protection' ) } />
					</ToggleGroupControl>

					{ cfg.type === 'text' ? (
						<div className="mg-field">
							<label className="mg-field__label" htmlFor="mg-wm-text">
								{ __( 'Watermark text', 'rayetun-media-protection' ) }
							</label>
							<textarea
								id="mg-wm-text"
								ref={ textRef }
								className="mg-textarea"
								rows={ 2 }
								value={ cfg.text }
								onChange={ ( e ) => update( { text: e.target.value } ) }
							/>
							<TokenInserter onInsert={ insertToken } />
							<Flex className="mg-field" align="flex-end" gap={ 4 }>
								<FlexBlock>
									<RangeControl
										__nextHasNoMarginBottom
										label={ __( 'Font size', 'rayetun-media-protection' ) }
										value={ cfg.fontSize }
										onChange={ ( v ) => update( { fontSize: v } ) }
										min={ 8 }
										max={ 120 }
									/>
								</FlexBlock>
								<div className="mg-color-field">
									<label className="mg-field__label" htmlFor="mg-wm-color">
										{ __( 'Color', 'rayetun-media-protection' ) }
									</label>
									<input
										id="mg-wm-color"
										type="color"
										className="mg-color-input"
										value={ cfg.fontColor }
										onChange={ ( e ) => update( { fontColor: e.target.value } ) }
									/>
								</div>
							</Flex>
						</div>
					) : (
						<div className="mg-field">
							<Button variant="secondary" onClick={ chooseImage }>
								{ cfg.overlayPath ? __( 'Replace overlay image', 'rayetun-media-protection' ) : __( 'Choose overlay image', 'rayetun-media-protection' ) }
							</Button>
							{ cfg.overlayPath && (
								<img className="mg-overlay-thumb" src={ cfg.overlayPath } alt="" />
							) }
							<RangeControl
								__nextHasNoMarginBottom
								label={ __( 'Size (% of file width)', 'rayetun-media-protection' ) }
								value={ Math.round( cfg.imageScale * 100 ) }
								onChange={ ( v ) => update( { imageScale: v / 100 } ) }
								min={ 5 }
								max={ 100 }
							/>
						</div>
					) }

					<div className="mg-field">
						<span className="mg-field__label">{ __( 'Position', 'rayetun-media-protection' ) }</span>
						<PositionGrid value={ cfg.position } onChange={ ( p ) => update( { position: p } ) } />
					</div>

					<Flex gap={ 4 }>
						<FlexBlock>
							<RangeControl
								__nextHasNoMarginBottom
								label={ __( 'Offset X', 'rayetun-media-protection' ) }
								value={ cfg.offsetX }
								onChange={ ( v ) => update( { offsetX: v } ) }
								min={ 0 }
								max={ 300 }
							/>
						</FlexBlock>
						<FlexBlock>
							<RangeControl
								__nextHasNoMarginBottom
								label={ __( 'Offset Y', 'rayetun-media-protection' ) }
								value={ cfg.offsetY }
								onChange={ ( v ) => update( { offsetY: v } ) }
								min={ 0 }
								max={ 300 }
							/>
						</FlexBlock>
					</Flex>

					<RangeControl
						__nextHasNoMarginBottom
						label={ __( 'Opacity', 'rayetun-media-protection' ) }
						value={ cfg.opacity }
						onChange={ ( v ) => update( { opacity: v } ) }
						min={ 0 }
						max={ 100 }
					/>
					<RangeControl
						__nextHasNoMarginBottom
						label={ __( 'Rotation', 'rayetun-media-protection' ) }
						value={ cfg.rotation }
						onChange={ ( v ) => update( { rotation: v } ) }
						min={ -180 }
						max={ 180 }
					/>

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Use as default for new uploads', 'rayetun-media-protection' ) }
						checked={ isDefault }
						onChange={ setIsDefault }
					/>
				</div>

				<div className="mg-editor__preview">
					<LivePreview rule={ cfg } />
				</div>
			</div>

			<div className="mg-editor__footer">
				<Button variant="tertiary" onClick={ onClose }>
					{ __( 'Cancel', 'rayetun-media-protection' ) }
				</Button>
				<Button variant="primary" onClick={ save } isBusy={ saving } disabled={ saving }>
					{ __( 'Save preset', 'rayetun-media-protection' ) }
				</Button>
			</div>
		</Modal>
	);
}
