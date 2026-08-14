<?php
/**
 * "Page Categories" management screen under the Pages menu.
 *
 * Add, rename, recolor, reorder (drag and drop), and delete categories.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PCP_Admin_Page {

	const MENU_SLUG  = 'pcp-page-categories';
	const CAPABILITY = 'manage_options';

	/**
	 * Hook everything up.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'render_notices' ) );
	}

	/**
	 * Add the submenu under Pages.
	 */
	public function add_menu() {
		$hook = add_submenu_page(
			'edit.php?post_type=page',
			__( 'Page Categories', 'page-categories' ),
			__( 'Page Categories', 'page-categories' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);

		if ( $hook ) {
			add_action( 'load-' . $hook, array( $this, 'handle_actions' ) );
		}
	}

	/**
	 * Enqueue styles and the drag-to-reorder script on this screen only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'pages_page_' . self::MENU_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'pcp-admin', PCP_PLUGIN_URL . 'assets/admin.css', array(), PCP_VERSION );
		wp_enqueue_script( 'pcp-manage', PCP_PLUGIN_URL . 'assets/manage.js', array( 'jquery', 'jquery-ui-sortable' ), PCP_VERSION, true );

		wp_localize_script(
			'pcp-manage',
			'pcpManage',
			array(
				'confirmDelete' => __( 'Delete this category? It will be removed from any pages using it. This cannot be undone.', 'page-categories' ),
			)
		);
	}

	/**
	 * Process add / save / delete submissions before the page renders.
	 */
	public function handle_actions() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to manage page categories.', 'page-categories' ) );
		}

		$redirect = add_query_arg(
			array(
				'post_type' => 'page',
				'page'      => self::MENU_SLUG,
			),
			admin_url( 'edit.php' )
		);

		// Add a new category.
		if ( isset( $_POST['pcp_add'] ) ) {
			check_admin_referer( 'pcp_add_category', 'pcp_add_nonce' );

			$name  = isset( $_POST['pcp_new_name'] ) && is_string( $_POST['pcp_new_name'] ) ? wp_unslash( $_POST['pcp_new_name'] ) : '';
			$color = isset( $_POST['pcp_new_color'] ) && is_string( $_POST['pcp_new_color'] ) ? wp_unslash( $_POST['pcp_new_color'] ) : '';

			$result = PCP_Categories::add( $name, $color );

			$message = is_wp_error( $result ) ? 'empty_name' : 'added';
			wp_safe_redirect( add_query_arg( 'pcp_msg', $message, $redirect ) );
			exit;
		}

		// Save edits/order, and optionally delete one category in the same submit.
		if ( isset( $_POST['pcp_save'] ) || isset( $_POST['pcp_delete'] ) ) {
			check_admin_referer( 'pcp_manage_categories', 'pcp_manage_nonce' );

			$submitted = isset( $_POST['pcp_categories'] ) && is_array( $_POST['pcp_categories'] )
				? wp_unslash( $_POST['pcp_categories'] )
				: array();

			PCP_Categories::update_all( $submitted );

			$message = 'saved';

			if ( isset( $_POST['pcp_delete'] ) && is_scalar( $_POST['pcp_delete'] ) ) {
				PCP_Categories::delete( (int) $_POST['pcp_delete'] );
				$message = 'deleted';
			}

			wp_safe_redirect( add_query_arg( 'pcp_msg', $message, $redirect ) );
			exit;
		}
	}

	/**
	 * Show the outcome of the last action.
	 */
	public function render_notices() {
		$screen = get_current_screen();

		if ( ! $screen || 'pages_page_' . self::MENU_SLUG !== $screen->id || ! isset( $_GET['pcp_msg'] ) ) {
			return;
		}

		$messages = array(
			'added'      => array( 'success', __( 'Category added.', 'page-categories' ) ),
			'saved'      => array( 'success', __( 'Categories saved.', 'page-categories' ) ),
			'deleted'    => array( 'success', __( 'Category deleted and removed from its pages.', 'page-categories' ) ),
			'empty_name' => array( 'error', __( 'Please enter a category name.', 'page-categories' ) ),
		);

		$key = sanitize_key( $_GET['pcp_msg'] );

		if ( ! isset( $messages[ $key ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $messages[ $key ][0] ),
			esc_html( $messages[ $key ][1] )
		);
	}

	/**
	 * Render the management screen.
	 */
	public function render_page() {
		$categories = PCP_Categories::get_all();
		$counts     = PCP_Categories::get_usage_counts();
		?>
		<div class="wrap pcp-wrap">
			<h1><?php esc_html_e( 'Page Categories', 'page-categories' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Internal categories for organizing Pages in the admin. These are never shown on your site and have no effect on taxonomies, URLs, or site structure.', 'page-categories' ); ?>
			</p>

			<h2><?php esc_html_e( 'Add New Category', 'page-categories' ); ?></h2>
			<form method="post" class="pcp-add-form">
				<?php wp_nonce_field( 'pcp_add_category', 'pcp_add_nonce' ); ?>
				<input type="text" name="pcp_new_name" class="regular-text" placeholder="<?php esc_attr_e( 'Category name', 'page-categories' ); ?>" required />
				<input type="color" name="pcp_new_color" value="<?php echo esc_attr( PCP_Categories::DEFAULT_COLORS[ count( $categories ) % count( PCP_Categories::DEFAULT_COLORS ) ] ); ?>" title="<?php esc_attr_e( 'Category color', 'page-categories' ); ?>" />
				<?php submit_button( __( 'Add Category', 'page-categories' ), 'primary', 'pcp_add', false ); ?>
			</form>

			<h2><?php esc_html_e( 'Manage Categories', 'page-categories' ); ?></h2>

			<?php if ( empty( $categories ) ) : ?>
				<p><?php esc_html_e( 'No categories yet. Add your first one above.', 'page-categories' ); ?></p>
			<?php else : ?>
				<form method="post">
					<?php wp_nonce_field( 'pcp_manage_categories', 'pcp_manage_nonce' ); ?>
					<table class="wp-list-table widefat fixed striped pcp-table">
						<thead>
							<tr>
								<th class="pcp-col-drag"><span class="screen-reader-text"><?php esc_html_e( 'Reorder', 'page-categories' ); ?></span></th>
								<th><?php esc_html_e( 'Name', 'page-categories' ); ?></th>
								<th class="pcp-col-color"><?php esc_html_e( 'Color', 'page-categories' ); ?></th>
								<th><?php esc_html_e( 'Preview', 'page-categories' ); ?></th>
								<th class="pcp-col-count"><?php esc_html_e( 'Pages', 'page-categories' ); ?></th>
								<th class="pcp-col-actions"><?php esc_html_e( 'Actions', 'page-categories' ); ?></th>
							</tr>
						</thead>
						<tbody id="pcp-cat-rows">
							<?php foreach ( $categories as $category ) : ?>
								<?php
								$count      = isset( $counts[ $category['id'] ] ) ? $counts[ $category['id'] ] : 0;
								$filter_url = add_query_arg(
									array(
										'post_type'  => 'page',
										'pcp_filter' => $category['id'],
									),
									admin_url( 'edit.php' )
								);
								?>
								<tr>
									<td class="pcp-col-drag">
										<span class="pcp-drag-handle dashicons dashicons-menu" title="<?php esc_attr_e( 'Drag to reorder', 'page-categories' ); ?>"></span>
									</td>
									<td>
										<input type="text" class="regular-text pcp-name-input" name="pcp_categories[<?php echo (int) $category['id']; ?>][name]" value="<?php echo esc_attr( $category['name'] ); ?>" required />
									</td>
									<td class="pcp-col-color">
										<input type="color" class="pcp-color-input" name="pcp_categories[<?php echo (int) $category['id']; ?>][color]" value="<?php echo esc_attr( $category['color'] ); ?>" />
									</td>
									<td><?php echo pcp_badge_html( $category ); // phpcs:ignore WordPress.Security.EscapeOutput -- badge builder escapes internally. ?></td>
									<td class="pcp-col-count">
										<?php if ( $count > 0 ) : ?>
											<a href="<?php echo esc_url( $filter_url ); ?>"><?php echo (int) $count; ?></a>
										<?php else : ?>
											0
										<?php endif; ?>
									</td>
									<td class="pcp-col-actions">
										<button type="submit" class="button-link-delete pcp-delete-btn" name="pcp_delete" value="<?php echo (int) $category['id']; ?>" formnovalidate>
											<?php esc_html_e( 'Delete', 'page-categories' ); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p class="description"><?php esc_html_e( 'Drag rows to change the order. The order here is used when sorting the Pages list by category.', 'page-categories' ); ?></p>
					<?php submit_button( __( 'Save Changes', 'page-categories' ), 'primary', 'pcp_save' ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}
}
