<?php
/**
 * Plugin Name:       Page Categories (Internal)
 * Description:       Internal-only, color-coded categories for Pages. Tag, sort, and filter pages in the admin without touching taxonomies or site structure.
 * Version:           1.1.2
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            hgsouth
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       page-categories
 * Update URI:        false
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PCP_VERSION', '1.1.2' );
define( 'PCP_PLUGIN_FILE', __FILE__ );
define( 'PCP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once PCP_PLUGIN_DIR . 'includes/class-pcp-categories.php';
require_once PCP_PLUGIN_DIR . 'includes/class-pcp-sort-screen.php';
require_once PCP_PLUGIN_DIR . 'includes/class-pcp-admin-page.php';
require_once PCP_PLUGIN_DIR . 'includes/class-pcp-pages-list.php';
require_once PCP_PLUGIN_DIR . 'includes/class-pcp-meta-box.php';

/**
 * Boot the plugin. Everything is admin-only; the front end is never touched.
 */
function pcp_init() {
	if ( ! is_admin() ) {
		return;
	}

	( new PCP_Admin_Page() )->register();
	( new PCP_Pages_List() )->register();
	( new PCP_Meta_Box() )->register();
}
add_action( 'plugins_loaded', 'pcp_init' );

/**
 * Compute a readable text color (dark or white) for a given background hex color.
 *
 * @param string $hex Hex color, e.g. #3b82f6.
 * @return string Hex color for the label text.
 */
function pcp_text_color_for( $hex ) {
	$hex = ltrim( (string) $hex, '#' );

	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}

	if ( 6 !== strlen( $hex ) ) {
		return '#1d2327';
	}

	$r = hexdec( substr( $hex, 0, 2 ) );
	$g = hexdec( substr( $hex, 2, 2 ) );
	$b = hexdec( substr( $hex, 4, 2 ) );

	$luminance = ( 0.299 * $r ) + ( 0.587 * $g ) + ( 0.114 * $b );

	return $luminance > 150 ? '#1d2327' : '#ffffff';
}

/**
 * Render a color-coded category badge.
 *
 * @param array $category Category with 'name' and 'color' keys.
 * @return string Badge HTML.
 */
function pcp_badge_html( $category ) {
	// Defense in depth: colors are validated on save, but re-validate at render
	// so a tampered option can never inject anything into the style attribute.
	$color = isset( $category['color'] ) && is_string( $category['color'] ) ? sanitize_hex_color( $category['color'] ) : '';

	if ( ! $color ) {
		$color = '#787c82';
	}

	return sprintf(
		'<span class="pcp-badge" style="background-color:%1$s;color:%2$s;">%3$s</span>',
		esc_attr( $color ),
		esc_attr( pcp_text_color_for( $color ) ),
		esc_html( $category['name'] )
	);
}
