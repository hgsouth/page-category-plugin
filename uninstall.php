<?php
/**
 * Clean up all plugin data when the plugin is deleted.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'pcp_categories' );
delete_option( 'pcp_next_category_id' );

// Remove every category assignment.
delete_metadata( 'post', 0, '_pcp_page_category', '', true );
