<?php
/**
 * The `markguard/protected-library` block: a grid of protected files, each with
 * a signed, access-controlled download. Optional per-file filter categories
 * drive a client-side filter bar. Server-rendered; links are minted per request.
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

namespace Rayetun\MarkGuard\Blocks;

use Rayetun\MarkGuard\Support\FileType;

defined( 'ABSPATH' ) || exit;

final class ProtectedLibraryBlock {

	public function register(): void {
		add_action( 'init', [ $this, 'register_block' ] );
	}

	public function register_block(): void {
		$dir = MARKGUARD_DIR . '/build/blocks/protected-library';
		if ( ! is_readable( $dir . '/block.json' ) ) {
			return;
		}
		register_block_type( $dir, [ 'render_callback' => [ $this, 'render' ] ] );
	}

	/**
	 * @param array<string, mixed> $attributes
	 */
	public function render( array $attributes ): string {
		$raw_items = is_array( $attributes['items'] ?? null ) ? $attributes['items'] : [];
		if ( empty( $raw_items ) ) {
			return '';
		}
		$config = $this->config( is_array( $attributes['config'] ?? null ) ? $attributes['config'] : [] );

		$service = new ProtectedDownloadService();
		$items   = [];
		$terms   = [];

		foreach ( $raw_items as $raw ) {
			$file_id = absint( $raw['fileId'] ?? 0 );
			if ( $file_id <= 0 ) {
				continue;
			}
			$data = $service->render_data( $file_id );
			if ( null === $data ) {
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
			$items[] = [ 'data' => $data, 'tags' => $tags ];
		}

		if ( empty( $items ) ) {
			return '';
		}

		/** This filter mirrors the gallery's, for the library. */
		$terms = apply_filters( 'markguard_library_filter_terms', $terms, $items, $config );

		return $this->markup( $items, $terms, $config );
	}

	/**
	 * @param array<string, mixed> $c
	 * @return array<string, mixed>
	 */
	private function config( array $c ): array {
		$labels   = is_array( $c['labels'] ?? null ) ? $c['labels'] : [];
		$filters  = is_array( $c['filters'] ?? null ) ? $c['filters'] : [];
		$accent   = sanitize_hex_color( (string) ( $c['accent'] ?? '' ) );
		$defaults = [
			'columns'  => max( 1, min( 6, (int) ( $c['columns'] ?? 2 ) ) ),
			'gap'      => max( 0, min( 48, (int) ( $c['gap'] ?? 12 ) ) ),
			'showInfo' => ! isset( $c['showInfo'] ) || ! empty( $c['showInfo'] ),
			'accent'   => is_string( $accent ) && '' !== $accent ? $accent : '#059669',
			'template' => ( ( $c['template'] ?? 'card' ) === 'tile' ) ? 'tile' : 'card',
			'labels'   => [
				'download' => sanitize_text_field( (string) ( $labels['download'] ?? __( 'Download', 'rayetun-media-protection' ) ) ),
				'locked'   => sanitize_text_field( (string) ( $labels['locked'] ?? __( 'Log in to download', 'rayetun-media-protection' ) ) ),
			],
			'filters'  => [
				'enabled'  => ! empty( $filters['enabled'] ),
				'allLabel' => sanitize_text_field( (string) ( $filters['allLabel'] ?? __( 'All', 'rayetun-media-protection' ) ) ),
			],
		];
		return $defaults;
	}

	/**
	 * @param array<int, array<string, mixed>> $items
	 * @param array<string, string>            $terms
	 * @param array<string, mixed>             $config
	 */
	private function markup( array $items, array $terms, array $config ): string {
		$show_filters = ! empty( $config['filters']['enabled'] ) && ! empty( $terms );

		$classes = [ 'mg-library', 'mg-library--' . sanitize_html_class( (string) $config['template'] ) ];
		/** Pro can add layout classes. */
		$classes = (array) apply_filters( 'markguard_library_wrapper_class', $classes, $config );

		$style = sprintf( '--mg-library-cols:%d;--mg-library-gap:%dpx;--mg-accent:%s;', (int) $config['columns'], (int) $config['gap'], esc_attr( (string) $config['accent'] ) );

		$out = '<div class="' . esc_attr( implode( ' ', array_map( 'sanitize_html_class', $classes ) ) ) . '" style="' . esc_attr( $style ) . '">';

		if ( $show_filters ) {
			$out .= '<div class="mg-library__filters" role="group" aria-label="' . esc_attr__( 'Filter files', 'rayetun-media-protection' ) . '">';
			$out .= '<button type="button" class="mg-library__filter is-active" data-mg-filter="" aria-pressed="true">' . esc_html( (string) $config['filters']['allLabel'] ) . '</button>';
			foreach ( $terms as $slug => $label ) {
				$out .= '<button type="button" class="mg-library__filter" data-mg-filter="' . esc_attr( (string) $slug ) . '" aria-pressed="false">' . esc_html( (string) $label ) . '</button>';
			}
			$out .= '</div>';
		}

		$out .= '<ul class="mg-library__grid">';
		foreach ( $items as $item ) {
			$out .= $this->item_html( $item, $config );
		}
		$out .= '</ul></div>';

		/** Final library HTML. */
		return (string) apply_filters( 'markguard_library_render', $out, $items, $config );
	}

	/**
	 * @param array<string, mixed> $item
	 * @param array<string, mixed> $config
	 */
	private function item_html( array $item, array $config ): string {
		$data = $item['data'];
		$tags = implode( ' ', array_map( 'sanitize_html_class', (array) $item['tags'] ) );

		$html  = '<li class="mg-library__cell" data-mg-tags="' . esc_attr( $tags ) . '"><div class="mg-download">';
		$html .= FileType::icon_svg( $data['mime'] );
		$html .= '<div class="mg-download__meta"><span class="mg-download__name">' . esc_html( $data['name'] ) . '</span>';
		if ( ! empty( $config['showInfo'] ) ) {
			$html .= '<span class="mg-download__info">' . esc_html( FileType::label( $data['mime'] ) . ' · ' . size_format( (int) $data['size'] ) ) . '</span>';
		}
		$html .= '</div>';
		$html .= DownloadAction::render( $data, (string) $config['labels']['download'], (string) $config['labels']['locked'] );
		$html .= '</div></li>';

		/** Pro can override an item's markup. */
		return (string) apply_filters( 'markguard_library_item_html', $html, $item, $config );
	}
}
