<?php
/**
 * "Page Category" meta box on the page edit screen (works in both the block
 * editor and the classic editor).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EPS_Meta_Box {

	/**
	 * Hook everything up.
	 */
	public function register() {
		add_action( 'add_meta_boxes_page', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_page', array( $this, 'save' ) );
	}

	/**
	 * Register the meta box in the sidebar.
	 */
	public function add_meta_box() {
		add_meta_box(
			'eps-page-category',
			__( 'Page Category (Internal)', 'easy-page-sorting' ),
			array( $this, 'render' ),
			'page',
			'side',
			'default'
		);
	}

	/**
	 * Render the category dropdown.
	 *
	 * @param WP_Post $post Current page.
	 */
	public function render( $post ) {
		$categories = EPS_Categories::get_all();
		$current    = EPS_Categories::get_for_page( $post->ID );

		wp_nonce_field( 'eps_save_meta_box', 'eps_meta_box_nonce' );

		if ( empty( $categories ) ) {
			printf(
				'<p>%s <a href="%s">%s</a></p>',
				esc_html__( 'No categories yet.', 'easy-page-sorting' ),
				esc_url( admin_url( 'edit.php?post_type=page&page=' . EPS_Admin_Page::MENU_SLUG ) ),
				esc_html__( 'Create one', 'easy-page-sorting' )
			);
			return;
		}
		?>
		<label class="screen-reader-text" for="eps-meta-category"><?php esc_html_e( 'Page category', 'easy-page-sorting' ); ?></label>
		<select name="eps_meta_category" id="eps-meta-category" style="width:100%;">
			<option value="0"><?php esc_html_e( '— None —', 'easy-page-sorting' ); ?></option>
			<?php foreach ( $categories as $category ) : ?>
				<option value="<?php echo (int) $category['id']; ?>" <?php selected( $current, $category['id'] ); ?>>
					<?php echo esc_html( $category['name'] ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php esc_html_e( 'Internal only — never shown on the site.', 'easy-page-sorting' ); ?></p>
		<?php
	}

	/**
	 * Save the selection.
	 *
	 * @param int $post_id Page ID.
	 */
	public function save( $post_id ) {
		if ( ! isset( $_POST['eps_meta_category'], $_POST['eps_meta_box_nonce'] )
			|| ! is_scalar( $_POST['eps_meta_category'] )
			|| ! is_string( $_POST['eps_meta_box_nonce'] )
		) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eps_meta_box_nonce'] ) ), 'eps_save_meta_box' ) ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_page', $post_id ) ) {
			return;
		}

		EPS_Categories::set_for_page( $post_id, (int) $_POST['eps_meta_category'] );
	}
}
