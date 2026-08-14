<?php
/**
 * "Page Category" meta box on the page edit screen (works in both the block
 * editor and the classic editor).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PCP_Meta_Box {

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
			'pcp-page-category',
			__( 'Page Category (Internal)', 'page-categories' ),
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
		$categories = PCP_Categories::get_all();
		$current    = PCP_Categories::get_for_page( $post->ID );

		wp_nonce_field( 'pcp_save_meta_box', 'pcp_meta_box_nonce' );

		if ( empty( $categories ) ) {
			printf(
				'<p>%s <a href="%s">%s</a></p>',
				esc_html__( 'No categories yet.', 'page-categories' ),
				esc_url( admin_url( 'edit.php?post_type=page&page=' . PCP_Admin_Page::MENU_SLUG ) ),
				esc_html__( 'Create one', 'page-categories' )
			);
			return;
		}
		?>
		<label class="screen-reader-text" for="pcp-meta-category"><?php esc_html_e( 'Page category', 'page-categories' ); ?></label>
		<select name="pcp_meta_category" id="pcp-meta-category" style="width:100%;">
			<option value="0"><?php esc_html_e( '— None —', 'page-categories' ); ?></option>
			<?php foreach ( $categories as $category ) : ?>
				<option value="<?php echo (int) $category['id']; ?>" <?php selected( $current, $category['id'] ); ?>>
					<?php echo esc_html( $category['name'] ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php esc_html_e( 'Internal only — never shown on the site.', 'page-categories' ); ?></p>
		<?php
	}

	/**
	 * Save the selection.
	 *
	 * @param int $post_id Page ID.
	 */
	public function save( $post_id ) {
		if ( ! isset( $_POST['pcp_meta_category'], $_POST['pcp_meta_box_nonce'] )
			|| ! is_scalar( $_POST['pcp_meta_category'] )
			|| ! is_string( $_POST['pcp_meta_box_nonce'] )
		) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pcp_meta_box_nonce'] ) ), 'pcp_save_meta_box' ) ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_page', $post_id ) ) {
			return;
		}

		PCP_Categories::set_for_page( $post_id, (int) $_POST['pcp_meta_category'] );
	}
}
