<?php
/**
 * Plugin Name:       Easy Page Sorting
 * Description:       Internal-only, color-coded categories for Pages. Tag, sort, and filter pages in the admin without touching taxonomies or site structure.
 * Version:           2.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            hgsouth
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       easy-page-sorting
 * Update URI:        false
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EPS_VERSION', '2.0.0' );
define( 'EPS_PLUGIN_FILE', __FILE__ );
define( 'EPS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EPS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once EPS_PLUGIN_DIR . 'includes/class-eps-categories.php';
require_once EPS_PLUGIN_DIR . 'includes/class-eps-sort-screen.php';
require_once EPS_PLUGIN_DIR . 'includes/class-eps-admin-page.php';
require_once EPS_PLUGIN_DIR . 'includes/class-eps-pages-list.php';
require_once EPS_PLUGIN_DIR . 'includes/class-eps-meta-box.php';

/**
 * Boot the plugin. Everything is admin-only; the front end is never touched.
 */
function eps_init() {
	if ( ! is_admin() ) {
		return;
	}

	eps_maybe_migrate_legacy_data();

	( new EPS_Admin_Page() )->register();
	( new EPS_Pages_List() )->register();
	( new EPS_Meta_Box() )->register();
}
add_action( 'plugins_loaded', 'eps_init' );

/**
 * One-time migration from the plugin's original "Page Categories" identity
 * (folder page-categories, prefix pcp_).
 *
 * Copies the category list and moves every page assignment to the new keys,
 * so data survives the rename — and so deleting the old plugin copy (whose
 * uninstall.php removes only the legacy keys) cannot touch the migrated data.
 * Runs once: skipped as soon as the new option exists or no legacy data does.
 */
function eps_maybe_migrate_legacy_data() {
	if ( false !== get_option( EPS_Categories::OPTION_KEY, false ) ) {
		return;
	}

	$legacy = get_option( 'pcp_categories', false );

	if ( false === $legacy ) {
		return;
	}

	update_option( EPS_Categories::OPTION_KEY, $legacy, false );
	update_option( EPS_Categories::NEXT_ID_KEY, (int) get_option( 'pcp_next_category_id', 1 ), false );

	global $wpdb;

	// Move assignments via the meta API so per-post caches stay consistent.
	$post_ids = $wpdb->get_col(
		$wpdb->prepare( "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", '_pcp_page_category' )
	);

	foreach ( $post_ids as $post_id ) {
		$post_id = (int) $post_id;
		$value   = get_post_meta( $post_id, '_pcp_page_category', true );

		if ( '' !== $value ) {
			update_post_meta( $post_id, EPS_Categories::META_KEY, (int) $value );
		}

		delete_post_meta( $post_id, '_pcp_page_category' );
	}
}

/**
 * Compute a readable text color (dark or white) for a given background hex color.
 *
 * @param string $hex Hex color, e.g. #3b82f6.
 * @return string Hex color for the label text.
 */
function eps_text_color_for( $hex ) {
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
function eps_badge_html( $category ) {
	// Defense in depth: colors are validated on save, but re-validate at render
	// so a tampered option can never inject anything into the style attribute.
	$color = isset( $category['color'] ) && is_string( $category['color'] ) ? sanitize_hex_color( $category['color'] ) : '';

	if ( ! $color ) {
		$color = '#787c82';
	}

	return sprintf(
		'<span class="eps-badge" style="background-color:%1$s;color:%2$s;">%3$s</span>',
		esc_attr( $color ),
		esc_attr( eps_text_color_for( $color ) ),
		esc_html( $category['name'] )
	);
}
