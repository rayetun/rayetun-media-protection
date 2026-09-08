<?php
/**
 * The `markguard/protected-gallery` block: a watermark-protected image gallery.
 *
 * Server-rendered so the browser only ever receives clean small thumbnails and
 * watermarked large previews — never the clean full-resolution file, which is
 * downloaded (by authorized visitors) through the signed handler. Behaviour
 * (lightbox, filtering, right-click deterrent) is added by a dependency-free
 * vanilla `viewScript`; no front-end framework runtime is shipped.
 *
 * Extension points let the pro plugin add advanced layouts, overlay styles,
 * filter sources, and per-item markup without touching this file.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Gallery;

use Rayetun\MarkGuard\Media\CleanCopyService;

defined( 'ABSPATH' ) || exit;

final class GalleryBlock {

	public function register(): void {
		add_action( 'init', [ $this, 'register_block' ] );
	}

	public function register_block(): void {
		$dir = MARKGUARD_DIR . '/build/blocks/protected-gallery';
		if ( ! is_readable( $dir . '/block.json' ) ) {
			return; // Block assets not built yet.
		}
		register_block_type( $dir, [ 'render_callback' => [ $this, 'render' ] ] );
	}

	/**
	 * @param array<string, mixed> $attributes
	 */
	public function render( array $attributes ): string {
		$config = GalleryConfig::sanitize(
			GalleryConfig::merge(
				GalleryConfig::global_defaults(),
				is_array( $attributes['config'] ?? null ) ? $attributes['config'] : []
			)
		);

		$raw_items = is_array( $attributes['items'] ?? null ) ? $attributes['items'] : [];
		if ( empty( $raw_items ) ) {
			return '';
		}

		$service = new GalleryAssetService();
		$clean   = new CleanCopyService();
		$items   = [];
		$terms   = [];

		foreach ( $raw_items as $raw ) {
			$id = absint( $raw['id'] ?? 0 );
			if ( $id <= 0 ) {
				continue;
			}
			$asset = $service->ensure( $id, (int) $config['presetId'], (int) $config['thumbMax'], (int) $config['previewMax'], (int) $config['policyId'] );
			if ( empty( $asset['ok'] ) ) {
				continue;
			}

			$tags = [];
			foreach ( (array) ( $raw['filters'] ?? [] ) as $raw_tag ) {
				$label = sanitize_text_field( (string) $raw_tag );
				$slug  = sanitize_title( $label );
				if ( '' === $slug ) {
					continue;
				}
				$tags[] = $slug;
				if ( ! isset( $terms[ $slug ] ) ) {
					$terms[ $slug ] = $label;
				}
			}

			$download_url = '';
			$locked       = true;
			$file         = $clean->file_for_attachment( $id );
			if ( null !== $file && $clean->viewer_allowed( $file->policy ) ) {
				$download_url = $clean->link( $file );
				$locked       = false;
			}

			$items[] = [
				'id'       => $id,
				'asset'    => $asset,
				'tags'     => $tags,
				'lightbox' => [
					'preview'       => $asset['previewUrl'],
					'title'         => $asset['title'],
					'downloadUrl'   => $download_url,
					'locked'        => $locked,
					'loginUrl'      => $locked && ! is_user_logged_in() ? wp_login_url( get_permalink() ? get_permalink() : home_url( '/' ) ) : '',
					'downloadLabel' => (string) $config['lightbox']['downloadLabel'],
					'loginText'     => (string) $config['lightbox']['loginText'],
				],
			];
		}

		if ( empty( $items ) ) {
			return '';
		}

		/**
		 * Filters the gallery filter terms (slug => label). Pro can source these
		 * from a taxonomy instead of per-image tags.
		 *
		 * @param array<string, string>       $terms
		 * @param array<int, array<string, mixed>> $items
		 * @param array<string, mixed>        $config
		 */
		$terms = apply_filters( 'markguard_gallery_filter_terms', $terms, $items, $config );

		return $this->markup( $items, $terms, $config );
	}

	/**
	 * @param array<int, array<string, mixed>> $items
	 * @param array<string, string>            $terms
	 * @param array<string, mixed>             $config
	 */
	private function markup( array $items, array $terms, array $config ): string {
		$show_filters = ! empty( $config['filters']['enabled'] ) && ! empty( $terms );

		$classes = [ 'mg-gallery', 'mg-gallery--' . sanitize_html_class( (string) $config['layout'] ) ];
		if ( $show_filters ) {
			$classes[] = 'mg-gallery--has-filters';
		}
		/**
		 * Filters the gallery wrapper CSS classes (pro adds layout classes).
		 *
		 * @param string[]             $classes
		 * @param array<string, mixed> $config
		 */
		$classes = (array) apply_filters( 'markguard_gallery_wrapper_class', $classes, $config );

		$style = sprintf(
			'--mg-gallery-cols:%d;--mg-gallery-gap:%dpx;--mg-gallery-radius:%dpx;--mg-gallery-overlay-color:%s;--mg-gallery-overlay-opacity:%s;--mg-accent:%s;',
			(int) $config['columns'],
			(int) $config['gap'],
			(int) $config['radius'],
			esc_attr( (string) $config['overlay']['color'] ),
			esc_attr( (string) $config['overlay']['opacity'] ),
			esc_attr( (string) $config['accent'] )
		);

		$out = sprintf(
			'<div class="%s" style="%s" data-overlay-style="%s">',
			esc_attr( implode( ' ', array_map( 'sanitize_html_class', $classes ) ) ),
			esc_attr( $style ),
			esc_attr( (string) $config['overlay']['style'] )
		);

		/**
		 * Fires just inside the gallery wrapper, before the filter bar / grid.
		 *
		 * @param array<int, array<string, mixed>> $items
		 * @param array<string, mixed>             $config
		 */
		ob_start();
		do_action( 'markguard_gallery_before_grid', $items, $config );
		$out .= ob_get_clean();

		if ( $show_filters ) {
			$out .= $this->filter_bar( $terms, $config );
		}

		$out .= '<ul class="mg-gallery__grid">';
		foreach ( $items as $item ) {
			$out .= $this->item_html( $item, $config );
		}
		$out .= '</ul>';

		$out .= $this->lightbox_html( $config );

		$out .= '</div>';

		/**
		 * Filters the final gallery HTML.
		 *
		 * @param string                           $out
		 * @param array<int, array<string, mixed>> $items
		 * @param array<string, mixed>             $config
		 */
		return (string) apply_filters( 'markguard_gallery_render', $out, $items, $config );
	}

	/**
	 * @param array<string, string> $terms
	 * @param array<string, mixed>  $config
	 */
	private function filter_bar( array $terms, array $config ): string {
		$all = (string) $config['filters']['allLabel'];
		$out = '<div class="mg-gallery__filters" role="group" aria-label="' . esc_attr__( 'Filter gallery', 'rayetun-media-protection' ) . '">';
		$out .= sprintf(
			'<button type="button" class="mg-gallery__filter is-active" data-mg-filter="" aria-pressed="true">%s</button>',
			esc_html( $all )
		);
		foreach ( $terms as $slug => $label ) {
			$out .= sprintf(
				'<button type="button" class="mg-gallery__filter" data-mg-filter="%s" aria-pressed="false">%s</button>',
				esc_attr( (string) $slug ),
				esc_html( (string) $label )
			);
		}
		$out .= '</div>';
		return $out;
	}

	/**
	 * @param array<string, mixed> $item
	 * @param array<string, mixed> $config
	 */
	private function item_html( array $item, array $config ): string {
		$asset = $item['asset'];
		$data  = wp_json_encode( $item['lightbox'] );
		$tags  = implode( ' ', array_map( 'sanitize_html_class', (array) $item['tags'] ) );

		$html = sprintf(
			'<li class="mg-gallery__cell" data-mg-tags="%s">',
			esc_attr( $tags )
		);
		$html .= '<button type="button" class="mg-gallery__item" data-mg-item=\'' . esc_attr( (string) $data ) . '\'>';
		$html .= sprintf(
			'<img class="mg-gallery__thumb" src="%s" alt="%s" loading="lazy" draggable="false" />',
			esc_url( (string) $asset['thumbUrl'] ),
			esc_attr( (string) $asset['title'] )
		);
		if ( 'none' !== (string) $config['overlay']['style'] ) {
			$html .= '<span class="mg-gallery__overlay" aria-hidden="true"></span>';
		}
		$html .= '<span class="screen-reader-text">' . esc_html( (string) $asset['title'] ) . '</span>';
		$html .= '</button></li>';

		/**
		 * Filters a single gallery item's HTML.
		 *
		 * @param string               $html
		 * @param array<string, mixed> $item
		 * @param array<string, mixed> $config
		 */
		return (string) apply_filters( 'markguard_gallery_item_html', $html, $item, $config );
	}

	/**
	 * @param array<string, mixed> $config
	 */
	private function lightbox_html( array $config ): string {
		$show_title    = ! empty( $config['lightbox']['showTitle'] );
		$show_download = ! empty( $config['lightbox']['showDownload'] );

		$out  = '<div class="mg-gallery__lightbox" role="dialog" aria-modal="true" aria-label="' . esc_attr__( 'Image preview', 'rayetun-media-protection' ) . '" hidden>';
		$out .= '<div class="mg-gallery__backdrop" data-mg-close></div>';
		$out .= '<div class="mg-gallery__dialog">';
		$out .= '<button type="button" class="mg-gallery__close" data-mg-close aria-label="' . esc_attr__( 'Close', 'rayetun-media-protection' ) . '">&times;</button>';
		$out .= '<img class="mg-gallery__preview" src="" alt="" draggable="false" />';
		$out .= '<div class="mg-gallery__meta">';
		if ( $show_title ) {
			$out .= '<h2 class="mg-gallery__title"></h2>';
		}
		if ( $show_download ) {
			$out .= '<a class="mg-gallery__download" rel="nofollow" href="#" hidden></a>';
			$out .= '<p class="mg-gallery__locked" hidden></p>';
		}
		$out .= '</div></div></div>';
		return $out;
	}
}
