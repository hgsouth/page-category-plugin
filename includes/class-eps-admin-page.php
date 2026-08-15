<?php
/**
 * "Page Categories" management screen under the Pages menu.
 *
 * Add, rename, recolor, reorder (drag and drop), and delete categories.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EPS_Admin_Page {

	const MENU_SLUG  = 'easy-page-sorting';
	const CAPABILITY = 'manage_options';

	/**
	 * Sort tab handler.
	 *
	 * @var EPS_Sort_Screen
	 */
	private $sort_screen;

	/**
	 * Set up collaborators.
	 */
	public function __construct() {
		$this->sort_screen = new EPS_Sort_Screen();
	}

	/**
	 * Base URL for this screen.
	 *
	 * @return string
	 */
	public static function base_url() {
		return add_query_arg(
			array(
				'post_type' => 'page',
				'page'      => self::MENU_SLUG,
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Which tab is active.
	 *
	 * @return string 'manage' or 'sort'.
	 */
	private function current_tab() {
		$tab = isset( $_REQUEST['tab'] ) && is_string( $_REQUEST['tab'] ) ? sanitize_key( $_REQUEST['tab'] ) : '';

		return 'sort' === $tab ? 'sort' : 'manage';
	}

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
			__( 'Easy Page Sorting', 'easy-page-sorting' ),
			__( 'Easy Page Sorting', 'easy-page-sorting' ),
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

		wp_enqueue_style( 'eps-admin', EPS_PLUGIN_URL . 'assets/admin.css', array(), EPS_VERSION );
		wp_enqueue_script( 'eps-manage', EPS_PLUGIN_URL . 'assets/manage.js', array( 'jquery', 'jquery-ui-sortable' ), EPS_VERSION, true );

		wp_localize_script(
			'eps-manage',
			'epsManage',
			array(
				'confirmDelete' => __( 'Delete this category? It will be removed from any pages using it. This cannot be undone.', 'easy-page-sorting' ),
				'unsavedWarn'   => __( 'You have unsaved category changes. Leave this page and lose them?', 'easy-page-sorting' ),
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
			wp_die( esc_html__( 'You are not allowed to manage page categories.', 'easy-page-sorting' ) );
		}

		$redirect = self::base_url();

		// Sort tab submissions redirect and exit inside their own handler.
		$this->sort_screen->handle_actions( $redirect );

		// Add a new category.
		if ( isset( $_POST['eps_add'] ) ) {
			check_admin_referer( 'eps_add_category', 'eps_add_nonce' );

			$name  = isset( $_POST['eps_new_name'] ) && is_string( $_POST['eps_new_name'] ) ? wp_unslash( $_POST['eps_new_name'] ) : '';
			$color = isset( $_POST['eps_new_color'] ) && is_string( $_POST['eps_new_color'] ) ? wp_unslash( $_POST['eps_new_color'] ) : '';

			$result = EPS_Categories::add( $name, $color );

			$message = is_wp_error( $result ) ? 'empty_name' : 'added';
			wp_safe_redirect( add_query_arg( 'eps_msg', $message, $redirect ) );
			exit;
		}

		// Save edits/order, and optionally delete one category in the same submit.
		if ( isset( $_POST['eps_save'] ) || isset( $_POST['eps_delete'] ) ) {
			check_admin_referer( 'eps_manage_categories', 'eps_manage_nonce' );

			$submitted = isset( $_POST['eps_categories'] ) && is_array( $_POST['eps_categories'] )
				? wp_unslash( $_POST['eps_categories'] )
				: array();

			EPS_Categories::update_all( $submitted );

			$message = 'saved';

			if ( isset( $_POST['eps_delete'] ) && is_scalar( $_POST['eps_delete'] ) ) {
				EPS_Categories::delete( (int) $_POST['eps_delete'] );
				$message = 'deleted';
			}

			wp_safe_redirect( add_query_arg( 'eps_msg', $message, $redirect ) );
			exit;
		}
	}

	/**
	 * Show the outcome of the last action.
	 */
	public function render_notices() {
		$screen = get_current_screen();

		if ( ! $screen || 'pages_page_' . self::MENU_SLUG !== $screen->id ) {
			return;
		}

		// Core's post.php redirects back here with trashed=N after a Trash
		// action on the Sort tab; acknowledge it like the Pages list does.
		if ( isset( $_GET['trashed'] ) && is_scalar( $_GET['trashed'] ) && (int) $_GET['trashed'] > 0 ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html__( 'Page moved to the Trash.', 'easy-page-sorting' )
			);
		}

		if ( ! isset( $_GET['eps_msg'] ) ) {
			return;
		}

		$messages = array(
			'added'             => array( 'success', __( 'Category added.', 'easy-page-sorting' ) ),
			'saved'             => array( 'success', __( 'Categories saved.', 'easy-page-sorting' ) ),
			'deleted'           => array( 'success', __( 'Category deleted and removed from its pages.', 'easy-page-sorting' ) ),
			'empty_name'        => array( 'error', __( 'Please enter a category name.', 'easy-page-sorting' ) ),
			'assignments_saved' => array( 'success', __( 'Page categories updated.', 'easy-page-sorting' ) ),
			'bulk_assigned'     => array( 'success', __( 'Selected pages were assigned.', 'easy-page-sorting' ) ),
			'bulk_incomplete'   => array( 'error', __( 'Select at least one page and a category to assign.', 'easy-page-sorting' ) ),
			'no_changes'        => array( 'info', __( 'No changes to save.', 'easy-page-sorting' ) ),
		);

		$key = sanitize_key( $_GET['eps_msg'] );

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
		$tab      = $this->current_tab();
		$base_url = self::base_url();
		?>
		<div class="wrap eps-wrap">
			<h1><?php esc_html_e( 'Easy Page Sorting', 'easy-page-sorting' ); ?></h1>

			<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Easy Page Sorting tabs', 'easy-page-sorting' ); ?>">
				<a href="<?php echo esc_url( $base_url ); ?>" class="nav-tab<?php echo 'manage' === $tab ? ' nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Manage Categories', 'easy-page-sorting' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'sort', $base_url ) ); ?>" class="nav-tab<?php echo 'sort' === $tab ? ' nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Sort Pages', 'easy-page-sorting' ); ?>
				</a>
			</nav>

			<?php
			if ( 'sort' === $tab ) {
				$this->sort_screen->render( $base_url );
			} else {
				$this->render_manage_tab( $base_url );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render the category management tab.
	 *
	 * @param string $base_url Screen URL.
	 */
	private function render_manage_tab( $base_url ) {
		$categories = EPS_Categories::get_all();
		$counts     = EPS_Categories::get_usage_counts();
		?>
			<p class="description">
				<?php esc_html_e( 'Internal categories for organizing Pages in the admin. These are never shown on your site and have no effect on taxonomies, URLs, or site structure.', 'easy-page-sorting' ); ?>
			</p>

			<h2><?php esc_html_e( 'Add New Category', 'easy-page-sorting' ); ?></h2>
			<form method="post" class="eps-add-form">
				<?php wp_nonce_field( 'eps_add_category', 'eps_add_nonce' ); ?>
				<input type="text" name="eps_new_name" class="regular-text" placeholder="<?php esc_attr_e( 'Category name', 'easy-page-sorting' ); ?>" required />
				<input type="color" name="eps_new_color" value="<?php echo esc_attr( EPS_Categories::DEFAULT_COLORS[ count( $categories ) % count( EPS_Categories::DEFAULT_COLORS ) ] ); ?>" title="<?php esc_attr_e( 'Category color', 'easy-page-sorting' ); ?>" />
				<?php submit_button( __( 'Add Category', 'easy-page-sorting' ), 'primary', 'eps_add', false ); ?>
			</form>

			<h2><?php esc_html_e( 'Manage Categories', 'easy-page-sorting' ); ?></h2>

			<?php if ( empty( $categories ) ) : ?>
				<p><?php esc_html_e( 'No categories yet. Add your first one above.', 'easy-page-sorting' ); ?></p>
			<?php else : ?>
				<form method="post">
					<?php wp_nonce_field( 'eps_manage_categories', 'eps_manage_nonce' ); ?>
					<?php // First submit in the form is the implicit-submission default; without this, Enter in a name field would hit a row's Delete button. ?>
					<button type="submit" name="eps_save" value="1" class="eps-default-submit" tabindex="-1" aria-hidden="true"></button>
					<table class="wp-list-table widefat fixed striped eps-table">
						<thead>
							<tr>
								<th class="eps-col-drag"><span class="screen-reader-text"><?php esc_html_e( 'Reorder', 'easy-page-sorting' ); ?></span></th>
								<th class="eps-col-name"><?php esc_html_e( 'Name', 'easy-page-sorting' ); ?></th>
								<th class="eps-col-color"><?php esc_html_e( 'Color', 'easy-page-sorting' ); ?></th>
								<th class="eps-col-preview"><?php esc_html_e( 'Preview', 'easy-page-sorting' ); ?></th>
								<th class="eps-col-count"><?php esc_html_e( 'Pages', 'easy-page-sorting' ); ?></th>
								<th class="eps-col-actions"><?php esc_html_e( 'Actions', 'easy-page-sorting' ); ?></th>
							</tr>
						</thead>
						<tbody id="eps-cat-rows">
							<?php foreach ( $categories as $category ) : ?>
								<?php
								$count = isset( $counts[ $category['id'] ] ) ? $counts[ $category['id'] ] : 0;

								// Drill into this category on the Sort tab.
								$filter_url = add_query_arg(
									array(
										'tab'     => 'sort',
										'eps_cat' => $category['id'],
									),
									$base_url
								);
								?>
								<tr>
									<td class="eps-col-drag">
										<span class="eps-drag-handle dashicons dashicons-menu" title="<?php esc_attr_e( 'Drag to reorder', 'easy-page-sorting' ); ?>"></span>
									</td>
									<td>
										<input type="text" class="regular-text eps-name-input" name="eps_categories[<?php echo (int) $category['id']; ?>][name]" value="<?php echo esc_attr( $category['name'] ); ?>" required />
									</td>
									<td class="eps-col-color">
										<input type="color" class="eps-color-input" name="eps_categories[<?php echo (int) $category['id']; ?>][color]" value="<?php echo esc_attr( $category['color'] ); ?>" />
									</td>
									<td><?php echo eps_badge_html( $category ); // phpcs:ignore WordPress.Security.EscapeOutput -- badge builder escapes internally. ?></td>
									<td class="eps-col-count">
										<?php if ( $count > 0 ) : ?>
											<a href="<?php echo esc_url( $filter_url ); ?>" title="<?php esc_attr_e( 'View these pages on the Sort Pages tab', 'easy-page-sorting' ); ?>">
												<?php echo (int) $count; ?>
											</a>
										<?php else : ?>
											0
										<?php endif; ?>
									</td>
									<td class="eps-col-actions">
										<button type="submit" class="button-link-delete eps-delete-btn" name="eps_delete" value="<?php echo (int) $category['id']; ?>" formnovalidate>
											<?php esc_html_e( 'Delete', 'easy-page-sorting' ); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p class="description"><?php esc_html_e( 'Drag rows to change the order. The order here is used when sorting the Pages list by category.', 'easy-page-sorting' ); ?></p>
					<?php submit_button( __( 'Save Changes', 'easy-page-sorting' ), 'primary', 'eps_save' ); ?>
				</form>
			<?php endif; ?>
		<?php
	}
}
