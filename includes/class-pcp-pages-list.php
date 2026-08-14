<?php
/**
 * Pages list table integration: color-coded column, sorting, filtering,
 * Quick Edit, and Bulk Edit.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PCP_Pages_List {

	const COLUMN_KEY = 'pcp_category';

	/**
	 * Hook everything up.
	 */
	public function register() {
		add_filter( 'manage_pages_columns', array( $this, 'add_column' ) );
		add_action( 'manage_pages_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_filter( 'manage_edit-page_sortable_columns', array( $this, 'make_sortable' ) );

		add_action( 'restrict_manage_posts', array( $this, 'render_filter_dropdown' ), 10, 1 );
		add_action( 'pre_get_posts', array( $this, 'apply_filter_and_sort' ) );
		add_filter( 'posts_clauses', array( $this, 'sort_by_category_order' ), 10, 2 );

		add_action( 'quick_edit_custom_box', array( $this, 'render_quick_edit_field' ), 10, 2 );
		add_action( 'bulk_edit_custom_box', array( $this, 'render_bulk_edit_field' ), 10, 2 );
		add_action( 'save_post_page', array( $this, 'save_quick_edit' ), 10, 1 );
		add_action( 'bulk_edit_posts', array( $this, 'save_bulk_edit' ), 10, 2 );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue the badge styles and Quick Edit script on the Pages list only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'edit.php' !== $hook_suffix ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || 'page' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style( 'pcp-admin', PCP_PLUGIN_URL . 'assets/admin.css', array(), PCP_VERSION );
		wp_enqueue_script( 'pcp-quick-edit', PCP_PLUGIN_URL . 'assets/quick-edit.js', array( 'jquery', 'inline-edit-post' ), PCP_VERSION, true );
	}

	/**
	 * Insert the Category column after the title.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_column( $columns ) {
		$new = array();

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;

			if ( 'title' === $key ) {
				$new[ self::COLUMN_KEY ] = __( 'Category', 'page-categories' );
			}
		}

		// If there was no title column for some reason, still add ours.
		if ( ! isset( $new[ self::COLUMN_KEY ] ) ) {
			$new[ self::COLUMN_KEY ] = __( 'Category', 'page-categories' );
		}

		return $new;
	}

	/**
	 * Render the badge in the column. The data attribute feeds Quick Edit.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Page ID.
	 */
	public function render_column( $column, $post_id ) {
		if ( self::COLUMN_KEY !== $column ) {
			return;
		}

		$category_id = PCP_Categories::get_for_page( $post_id );
		$category    = $category_id ? PCP_Categories::get( $category_id ) : null;

		if ( $category ) {
			printf(
				'<span class="pcp-cat-value" data-pcp-category="%1$d">%2$s</span>',
				(int) $category['id'],
				pcp_badge_html( $category ) // phpcs:ignore WordPress.Security.EscapeOutput -- badge builder escapes internally.
			);
		} else {
			echo '<span class="pcp-cat-value pcp-none" data-pcp-category="0" aria-hidden="true">&#8212;</span>';
		}
	}

	/**
	 * Make the column sortable.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public function make_sortable( $columns ) {
		$columns[ self::COLUMN_KEY ] = self::COLUMN_KEY;

		return $columns;
	}

	/**
	 * Dropdown above the Pages list to filter by category.
	 *
	 * @param string $post_type Current list table post type.
	 */
	public function render_filter_dropdown( $post_type ) {
		if ( 'page' !== $post_type ) {
			return;
		}

		$categories = PCP_Categories::get_all();

		if ( empty( $categories ) ) {
			return;
		}

		$current = isset( $_GET['pcp_filter'] ) && is_string( $_GET['pcp_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['pcp_filter'] ) ) : '';
		?>
		<label class="screen-reader-text" for="pcp-filter"><?php esc_html_e( 'Filter by page category', 'page-categories' ); ?></label>
		<select name="pcp_filter" id="pcp-filter">
			<option value=""><?php esc_html_e( 'All page categories', 'page-categories' ); ?></option>
			<?php foreach ( $categories as $category ) : ?>
				<option value="<?php echo (int) $category['id']; ?>" <?php selected( $current, (string) $category['id'] ); ?>>
					<?php echo esc_html( $category['name'] ); ?>
				</option>
			<?php endforeach; ?>
			<option value="none" <?php selected( $current, 'none' ); ?>><?php esc_html_e( '— Uncategorized —', 'page-categories' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Apply the category filter and flag category sorting on the main Pages query.
	 *
	 * @param WP_Query $query The query.
	 */
	public function apply_filter_and_sort( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'page' !== $query->get( 'post_type' ) ) {
			return;
		}

		global $pagenow;

		if ( 'edit.php' !== $pagenow ) {
			return;
		}

		// Filtering.
		$filter = isset( $_GET['pcp_filter'] ) && is_string( $_GET['pcp_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['pcp_filter'] ) ) : '';

		if ( 'none' === $filter ) {
			$meta_query   = (array) $query->get( 'meta_query' );
			$meta_query[] = array(
				'key'     => PCP_Categories::META_KEY,
				'compare' => 'NOT EXISTS',
			);
			$query->set( 'meta_query', $meta_query );
		} elseif ( '' !== $filter && (int) $filter > 0 ) {
			$meta_query   = (array) $query->get( 'meta_query' );
			$meta_query[] = array(
				'key'   => PCP_Categories::META_KEY,
				'value' => (string) (int) $filter,
			);
			$query->set( 'meta_query', $meta_query );
		}

		// Sorting: flag the query; the SQL is built in posts_clauses so pages
		// without a category are still included and the user-defined category
		// order is respected.
		if ( self::COLUMN_KEY === $query->get( 'orderby' ) ) {
			$query->set( 'pcp_orderby_category', true );
		}
	}

	/**
	 * Sort pages by the user-defined category order via FIELD(), keeping
	 * uncategorized pages in the list.
	 *
	 * @param array    $clauses SQL clauses.
	 * @param WP_Query $query   The query.
	 * @return array
	 */
	public function sort_by_category_order( $clauses, $query ) {
		if ( ! $query->get( 'pcp_orderby_category' ) ) {
			return $clauses;
		}

		global $wpdb;

		$order = strtoupper( (string) $query->get( 'order' ) );
		$order = 'DESC' === $order ? 'DESC' : 'ASC';

		$clauses['join'] .= $wpdb->prepare(
			" LEFT JOIN {$wpdb->postmeta} pcp_pm ON pcp_pm.post_id = {$wpdb->posts}.ID AND pcp_pm.meta_key = %s ",
			PCP_Categories::META_KEY
		);

		$ids = array_map( 'intval', wp_list_pluck( PCP_Categories::get_all(), 'id' ) );

		if ( ! empty( $ids ) ) {
			// FIELD() returns 0 for values not in the list, so uncategorized
			// pages (and orphaned values) group together before/after the rest.
			$field_list          = implode( ',', $ids );
			$clauses['orderby']  = "FIELD( IFNULL( pcp_pm.meta_value, 0 ), {$field_list} ) {$order}, {$wpdb->posts}.post_title ASC";
		} else {
			$clauses['orderby'] = "{$wpdb->posts}.post_title ASC";
		}

		return $clauses;
	}

	/**
	 * Category dropdown in Quick Edit.
	 *
	 * @param string $column_name Column being rendered.
	 * @param string $post_type   Post type.
	 */
	public function render_quick_edit_field( $column_name, $post_type ) {
		if ( self::COLUMN_KEY !== $column_name || 'page' !== $post_type ) {
			return;
		}
		?>
		<fieldset class="inline-edit-col-right pcp-quick-edit">
			<div class="inline-edit-col">
				<label>
					<span class="title"><?php esc_html_e( 'Category', 'page-categories' ); ?></span>
					<select name="pcp_quick_category">
						<option value="0"><?php esc_html_e( '— None —', 'page-categories' ); ?></option>
						<?php foreach ( PCP_Categories::get_all() as $category ) : ?>
							<option value="<?php echo (int) $category['id']; ?>"><?php echo esc_html( $category['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Category dropdown in Bulk Edit.
	 *
	 * @param string $column_name Column being rendered.
	 * @param string $post_type   Post type.
	 */
	public function render_bulk_edit_field( $column_name, $post_type ) {
		if ( self::COLUMN_KEY !== $column_name || 'page' !== $post_type ) {
			return;
		}
		?>
		<fieldset class="inline-edit-col-right pcp-bulk-edit">
			<div class="inline-edit-col">
				<label>
					<span class="title"><?php esc_html_e( 'Category', 'page-categories' ); ?></span>
					<select name="pcp_bulk_category">
						<option value=""><?php esc_html_e( '— No Change —', 'page-categories' ); ?></option>
						<option value="0"><?php esc_html_e( '— None —', 'page-categories' ); ?></option>
						<?php foreach ( PCP_Categories::get_all() as $category ) : ?>
							<option value="<?php echo (int) $category['id']; ?>"><?php echo esc_html( $category['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Save the Quick Edit selection. Runs during the inline-save AJAX request,
	 * which WordPress protects with its own inline-edit nonce.
	 *
	 * @param int $post_id Page ID.
	 */
	public function save_quick_edit( $post_id ) {
		if ( ! isset( $_POST['pcp_quick_category'], $_POST['_inline_edit'] )
			|| ! is_scalar( $_POST['pcp_quick_category'] )
			|| ! is_string( $_POST['_inline_edit'] )
		) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_inline_edit'] ) ), 'inlineeditnonce' ) ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_page', $post_id ) ) {
			return;
		}

		PCP_Categories::set_for_page( $post_id, (int) $_POST['pcp_quick_category'] );
	}

	/**
	 * Save the Bulk Edit selection for all selected pages.
	 *
	 * @param int[] $post_ids         Updated post IDs.
	 * @param array $shared_post_data Submitted bulk edit data.
	 */
	public function save_bulk_edit( $post_ids, $shared_post_data ) {
		if ( ! isset( $shared_post_data['pcp_bulk_category'] )
			|| ! is_scalar( $shared_post_data['pcp_bulk_category'] )
			|| '' === $shared_post_data['pcp_bulk_category']
		) {
			return;
		}

		$category_id = (int) $shared_post_data['pcp_bulk_category'];

		foreach ( (array) $post_ids as $post_id ) {
			if ( 'page' !== get_post_type( $post_id ) || ! current_user_can( 'edit_page', $post_id ) ) {
				continue;
			}

			PCP_Categories::set_for_page( $post_id, $category_id );
		}
	}
}
