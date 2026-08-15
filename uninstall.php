<?php
/**
 * Clean up all plugin data when the plugin is deleted.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'eps_categories' );
delete_option( 'eps_next_category_id' );

// Remove every category assignment.
delete_metadata( 'post', 0, '_eps_page_category', '', true );

// Legacy keys from the plugin's original "Page Categories" identity, in case
// an install was never migrated or the old copy was removed without cleanup.
delete_option( 'pcp_categories' );
delete_option( 'pcp_next_category_id' );
delete_metadata( 'post', 0, '_pcp_page_category', '', true );
