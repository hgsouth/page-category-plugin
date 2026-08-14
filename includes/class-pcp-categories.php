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

class PCP_Categories {

	const OPTION_KEY     = 'pcp_categories';
	const NEXT_ID_KEY    = 'pcp_next_category_id';
	const META_KEY       = '_pcp_page_category';

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
			if ( empty( $category['id'] ) || ! isset( $category['name'] ) ) {
				continue;
			}

			$clean[] = array(
				'id'    => (int) $category['id'],
				'name'  => (string) $category['name'],
				'color' => isset( $category['color'] ) ? (string) $category['color'] : self::DEFAULT_COLORS[0],
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
		$name = sanitize_text_field( $name );

		if ( '' === $name ) {
			return new WP_Error( 'pcp_empty_name', __( 'Category name cannot be empty.', 'page-categories' ) );
		}

		$categories = self::get_all();
		$color      = sanitize_hex_color( $color );

		if ( ! $color ) {
			$color = self::DEFAULT_COLORS[ count( $categories ) % count( self::DEFAULT_COLORS ) ];
		}

		$id = (int) get_option( self::NEXT_ID_KEY, 1 );

		$categories[] = array(
			'id'    => $id,
			'name'  => $name,
			'color' => $color,
		);

		update_option( self::OPTION_KEY, $categories );
		update_option( self::NEXT_ID_KEY, $id + 1 );

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

			if ( ! isset( $existing[ $id ] ) ) {
				continue;
			}

			$name  = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
			$color = isset( $row['color'] ) ? sanitize_hex_color( $row['color'] ) : '';

			$updated[] = array(
				'id'    => $id,
				'name'  => '' !== $name ? $name : $existing[ $id ]['name'],
				'color' => $color ? $color : $existing[ $id ]['color'],
			);
		}

		update_option( self::OPTION_KEY, $updated );
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

		update_option( self::OPTION_KEY, $categories );

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

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_value AS category_id, COUNT(*) AS total
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = %s
				   AND p.post_type = 'page'
				   AND p.post_status NOT IN ( 'trash', 'auto-draft' )
				 GROUP BY pm.meta_value",
				self::META_KEY
			),
			ARRAY_A
		);

		$counts = array();

		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row['category_id'] ] = (int) $row['total'];
		}

		return $counts;
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
