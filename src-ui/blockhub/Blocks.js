/**
 * Blocks hub — one card per registered RayEtun Media Protection block, each with its site-wide
 * defaults. Driven by the /blocks registry so pro blocks appear automatically.
 */
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { Spinner, Button } from '@wordpress/components';
import PageHeader from '../components/PageHeader';
import GalleryDefaults from './GalleryDefaults';
import DownloadDefaults from './DownloadDefaults';
import LibraryDefaults from './LibraryDefaults';
import { mgFetch } from '../app/api';

// Maps a block's defaultsKey to its settings form.
const FORMS = {
	gallery: GalleryDefaults,
	download: DownloadDefaults,
	library: LibraryDefaults,
};

export default function Blocks() {
	const [ blocks, setBlocks ] = useState( null );
	const [ openId, setOpenId ] = useState( null );

	useEffect( () => {
		mgFetch( '/blocks' ).then( ( d ) => setBlocks( d.blocks ) );
	}, [] );

	return (
		<>
			<PageHeader
				title={ __( 'Blocks', 'rayetun-media-protection' ) }
				subtitle={ __( 'RayEtun Media Protection editor blocks and their site-wide defaults. New blocks inherit these; each block can still override them.', 'rayetun-media-protection' ) }
			/>

			{ ! blocks && <div className="mg-card mg-placeholder"><Spinner /></div> }

			{ blocks && blocks.map( ( b ) => {
				const Form = FORMS[ b.defaultsKey ];
				const isOpen = openId === b.id;
				return (
					<div key={ b.id } className="mg-card mg-block-card">
						<div className="mg-block-card__head">
							<span className={ `dashicons dashicons-${ b.icon } mg-block-card__icon` } aria-hidden="true" />
							<div className="mg-block-card__text">
								<h2 className="mg-block-card__title">
									{ b.title }
									{ b.pro && <span className="mg-badge mg-badge--pro">{ __( 'Pro', 'rayetun-media-protection' ) }</span> }
								</h2>
								<p className="mg-block-card__desc">{ b.description }</p>
							</div>
							{ Form && (
								<Button variant="secondary" aria-expanded={ isOpen } onClick={ () => setOpenId( isOpen ? null : b.id ) }>
									{ isOpen ? __( 'Close', 'rayetun-media-protection' ) : __( 'Defaults', 'rayetun-media-protection' ) }
								</Button>
							) }
						</div>
						{ Form && isOpen && (
							<div className="mg-block-card__body">
								<Form />
							</div>
						) }
					</div>
				);
			} ) }
		</>
	);
}
