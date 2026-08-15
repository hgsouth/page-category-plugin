<?php
/**
 * Data layer for page categories.
 *
 * Categories live in a single option; assignments live in hidden post meta.
 * No taxonomies are registered and nothing is exposed on the front end.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EPS_Categories {

	const OPTION_KEY      = 'eps_categories';
	const NEXT_ID_KEY     = 'eps_next_category_id';
	const META_KEY        = '_eps_page_category';
	const MAX_NAME_LENGTH = 100;

	/**
	 * Default palette cycled through when adding categories without a color.
	 *
	 * @var string[]
	 */
	const DEFAULT_COLORS = array(
		'#2271b1', // blue
		'#00844a', // green
		'#996800', // amber
		'#b32d2e', // red
		'#7b5aa6', // purple
		'#0e7490', // teal
		'#a4286a', // magenta
		'#50575e', // gray
	);

	/**
	 * Get all categories in their saved display order.
	 *
	 * @return array[] Each item: ['id' => int, 'name' => string, 'color' => string].
	 */
	public static function get_all() {
		$categories = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $categories ) ) {
			return array();
		}

		$clean = array();

		foreach ( $categories as $category ) {
			if ( ! is_array( $category ) || empty( $category['id'] ) || ! isset( $category['name'] ) || ! is_scalar( $category['name'] ) ) {
				continue;
			}

			// Re-validate the stored color so a tampered option degrades to a
			// safe default instead of reaching any output path.
			$color = isset( $category['color'] ) && is_string( $category['color'] ) ? sanitize_hex_color( $category['color'] ) : '';

			$clean[] = array(
				'id'    => (int) $category['id'],
				'name'  => (string) $category['name'],
				'color' => $color ? $color : self::DEFAULT_COLORS[0],
			);
		}

		return $clean;
	}

	/**
	 * Get a single category by ID.
	 *
	 * @param int $id Category ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		foreach ( self::get_all() as $category ) {
			if ( (int) $id === $category['id'] ) {
				return $category;
			}
		}

		return null;
	}

	/**
	 * Add a new category.
	 *
	 * @param string $name  Category name.
	 * @param string $color Hex color; falls back to the default palette.
	 * @return int|WP_Error New category ID, or an error for an empty name.
	 */
	public static function add( $name, $color = '' ) {
		$name = is_string( $name ) ? sanitize_text_field( $name ) : '';
		$name = mb_substr( $name, 0, self::MAX_NAME_LENGTH );

		if ( '' === $name ) {
			return new WP_Error( 'eps_empty_name', __( 'Category name cannot be empty.', 'easy-page-sorting' ) );
		}

		$categories = self::get_all();
		$color      = is_string( $color ) ? sanitize_hex_color( $color ) : '';

		if ( ! $color ) {
			$color = self::DEFAULT_COLORS[ count( $categories ) % count( self::DEFAULT_COLORS ) ];
		}

		$id = (int) get_option( self::NEXT_ID_KEY, 1 );

		$categories[] = array(
			'id'    => $id,
			'name'  => $name,
			'color' => $color,
		);

		// Admin-only data: never autoload on front-end requests.
		update_option( self::OPTION_KEY, $categories, false );
		update_option( self::NEXT_ID_KEY, $id + 1, false );

		return $id;
	}

	/**
	 * Replace the full category list (used for rename, recolor, and reorder in one save).
	 *
	 * Only IDs that already exist are kept; the incoming array order becomes
	 * the new display order.
	 *
	 * @param array $submitted Map of id => ['name' => ..., 'color' => ...] in display order.
	 * @return void
	 */
	public static function update_all( $submitted ) {
		$existing = array();

		foreach ( self::get_all() as $category ) {
			$existing[ $category['id'] ] = $category;
		}

		$updated = array();

		foreach ( (array) $submitted as $id => $row ) {
			$id = (int) $id;

			if ( ! isset( $existing[ $id ] ) || ! is_array( $row ) ) {
				continue;
			}

			$name  = isset( $row['name'] ) && is_string( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
			$name  = mb_substr( $name, 0, self::MAX_NAME_LENGTH );
			$color = isset( $row['color'] ) && is_string( $row['color'] ) ? sanitize_hex_color( $row['color'] ) : '';

			$updated[] = array(
				'id'    => $id,
				'name'  => '' !== $name ? $name : $existing[ $id ]['name'],
				'color' => $color ? $color : $existing[ $id ]['color'],
			);
		}

		update_option( self::OPTION_KEY, $updated, false );
	}

	/**
	 * Delete a category and clear it from any pages that use it.
	 *
	 * @param int $id Category ID.
	 * @return void
	 */
	public static function delete( $id ) {
		$id         = (int) $id;
		$categories = array();

		foreach ( self::get_all() as $category ) {
			if ( $category['id'] !== $id ) {
				$categories[] = $category;
			}
		}

		update_option( self::OPTION_KEY, $categories, false );

		// Remove the assignment from every page that used this category.
		delete_metadata( 'post', 0, self::META_KEY, (string) $id, true );
	}

	/**
	 * Get how many pages are assigned to each category.
	 *
	 * @return array Map of category id => page count.
	 */
	public static function get_usage_counts() {
		global $wpdb;

		// Same status list the Sort tab queries, so chip counts match the rows shown.
		$statuses     = self::listed_statuses();
		$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );

		$sql = "SELECT pm.meta_value AS category_id, COUNT(*) AS total
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s
				  AND p.post_type = 'page'
				  AND p.post_status IN ( {$placeholders} )
				GROUP BY pm.meta_value";

		$rows = $wpdb->get_results(
			$wpdb->prepare( $sql, array_merge( array( self::META_KEY ), $statuses ) ), // phpcs:ignore WordPress.DB.PreparedSQL -- placeholders built from a fixed status list.
			ARRAY_A
		);

		$counts = array();

		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row['category_id'] ] = (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * Statuses considered "live" for counting and listing purposes.
	 *
	 * @return string[]
	 */
	public static function listed_statuses() {
		return array( 'publish', 'future', 'draft', 'pending', 'private' );
	}

	/**
	 * Count pages that have no category assigned.
	 *
	 * Covers both a missing meta row and an empty/zero value.
	 *
	 * @return int
	 */
	public static function get_uncategorized_count() {
		global $wpdb;

		$statuses     = self::listed_statuses();
		$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );

		$sql = "SELECT COUNT(*)
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm
					ON pm.post_id = p.ID AND pm.meta_key = %s
				WHERE p.post_type = 'page'
				  AND p.post_status IN ( {$placeholders} )
				  AND ( pm.meta_value IS NULL OR pm.meta_value = '' OR pm.meta_value = '0' )";

		$params = array_merge( array( self::META_KEY ), $statuses );

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL -- placeholders built from a fixed status list.
	}

	/**
	 * Count all pages in the listed statuses.
	 *
	 * @return int
	 */
	public static function get_total_page_count() {
		$counts = wp_count_posts( 'page' );
		$total  = 0;

		foreach ( self::listed_statuses() as $status ) {
			if ( isset( $counts->$status ) ) {
				$total += (int) $counts->$status;
			}
		}

		return $total;
	}

	/**
	 * Get the category assigned to a page.
	 *
	 * @param int $post_id Page ID.
	 * @return int Category ID, or 0 if unassigned.
	 */
	public static function get_for_page( $post_id ) {
		return (int) get_post_meta( $post_id, self::META_KEY, true );
	}

	/**
	 * Assign a category to a page (0 clears the assignment).
	 *
	 * @param int $post_id     Page ID.
	 * @param int $category_id Category ID, or 0 to clear.
	 * @return void
	 */
	public static function set_for_page( $post_id, $category_id ) {
		$category_id = (int) $category_id;

		if ( $category_id > 0 && self::get( $category_id ) ) {
			update_post_meta( $post_id, self::META_KEY, $category_id );
		} else {
			delete_post_meta( $post_id, self::META_KEY );
		}
	}
}
