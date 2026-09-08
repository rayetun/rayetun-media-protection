<?php
/**
 * Remove unused TCPDF fonts after `composer install` / `composer update`.
 *
 * TCPDF ships ~25 MB of embedded fonts (DejaVu, CJK, FreeFont). MarkGuard's PDF
 * watermark engine only ever calls SetFont('helvetica') — a core font — so we
 * keep just the standard-14 core font metrics and delete the rest. This keeps
 * the release ZIP well under the WordPress.org 10 MB upload limit and is applied
 * automatically on every dependency install (wired via composer.json scripts),
 * so both the manual package and the CI/SVN deploy stay slim.
 *
 * Dev-only tooling — not shipped in the plugin ZIP (see .distignore).
 *
 * @package Rayetun\MarkGuard
 */

declare( strict_types=1 );

$fonts_dir = __DIR__ . '/../vendor/tecnickcom/tcpdf/fonts';
if ( ! is_dir( $fonts_dir ) ) {
	// TCPDF not installed (e.g. --no-dev on a machine without it yet) — nothing to do.
	return;
}

$keep = array(
	'index.php',
	'helvetica.php', 'helveticab.php', 'helveticabi.php', 'helveticai.php',
	'courier.php', 'courierb.php', 'courierbi.php', 'courieri.php',
	'times.php', 'timesb.php', 'timesbi.php', 'timesi.php',
	'symbol.php', 'zapfdingbats.php',
);

$removed_files = 0;
$freed_bytes   = 0;

$rii = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $fonts_dir, FilesystemIterator::SKIP_DOTS ),
	RecursiveIteratorIterator::CHILD_FIRST
);

foreach ( $rii as $item ) {
	/** @var SplFileInfo $item */
	if ( $item->isDir() ) {
		// Drop any subdirectory (e.g. dejavu-fonts-ttf-*/, utils/) once emptied.
		@rmdir( $item->getPathname() );
		continue;
	}
	if ( in_array( $item->getFilename(), $keep, true ) ) {
		continue;
	}
	$freed_bytes += (int) $item->getSize();
	if ( @unlink( $item->getPathname() ) ) {
		$removed_files++;
	}
}

printf(
	"[markguard] Trimmed TCPDF fonts: removed %d file(s), freed %.1f MB (kept the standard-14 core fonts).\n",
	$removed_files,
	$freed_bytes / 1048576
);
