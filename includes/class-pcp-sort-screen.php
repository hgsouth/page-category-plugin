<?php
/**
 * "Sort Pages" tab: browse every page, filter by category, and retag or
 * retire pages without leaving the screen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PCP_Sort_Screen {

	const PER_PAGE = 50;

	/**
	 * Handle a submission from this tab.
	 *
	 * Called from PCP_Admin_Page::handle_actions(), which has already
	 * confirmed the request is a POST from a user with the screen capability.
	 *
	 * @param string $base_url Screen URL to redirect back to.
	 * @return void Redirects and exits when it handled the request.
	 */
	public function handle_actions( $base_url ) {
		if ( ! isset( $_POST['pcp_save_assignments'] ) && ! isset( $_POST['pcp_bulk_apply'] ) ) {
			return;
		}

		check_admin_referer( 'pcp_sort_pages', 'pcp_sort_nonce' );

		// Preserve the filter/sort/paging context across the redirect.
		$redirect = add_query_arg( $this->current_args(), $base_url );

		if ( isset( $_POST['pcp_bulk_apply'] ) ) {
			$this->handle_bulk_assign( $redirect );
		}

		$this->handle_save_assignments( $redirect );
	}

	/**
	 * Save every changed per-row category dropdown.
	 *
	 * @param string $redirect Where to send the user afterwards.
	 * @return void
	 */
	private function handle_save_assignments( $redirect ) {
		$submitted = isset( $_POST['pcp_page_category'] ) && is_array( $_POST['pcp_page_category'] )
			? wp_unslash( $_POST['pcp_page_category'] )
			: array();

		$changed = 0;

		foreach ( $submitted as $post_id => $category_id ) {
			if ( ! is_scalar( $category_id ) ) {
				continue;
			}

			$post_id     = (int) $post_id;
			$category_id = (int) $category_id;

			if ( ! $this->can_edit_page( $post_id ) ) {
				continue;
			}

			// Only write when the value actually changed.
			if ( PCP_Categories::get_for_page( $post_id ) === $category_id ) {
				continue;
			}

			PCP_Categories::set_for_page( $post_id, $category_id );
			++$changed;
		}

		wp_safe_redirect( add_query_arg( 'pcp_msg', $changed > 0 ? 'assignments_saved' : 'no_changes', $redirect ) );
		exit;
	}

	/**
	 * Apply the bulk "Assign to" dropdown to every checked page.
	 *
	 * @param string $redirect Where to send the user afterwards.
	 * @return void
	 */
	private function handle_bulk_assign( $redirect ) {
		$target = isset( $_POST['pcp_bulk_category'] ) && is_scalar( $_POST['pcp_bulk_category'] )
			? (string) wp_unslash( $_POST['pcp_bulk_category'] )
			: '';

		$selected = isset( $_POST['pcp_selected'] ) && is_array( $_POST['pcp_selected'] )
			? wp_unslash( $_POST['pcp_selected'] )
			: array();

		if ( '' === $target || empty( $selected ) ) {
			wp_safe_redirect( add_query_arg( 'pcp_msg', 'bulk_incomplete', $redirect ) );
			exit;
		}

		$category_id = (int) $target;
		$changed     = 0;

		foreach ( $selected as $post_id ) {
			if ( ! is_scalar( $post_id ) ) {
				continue;
			}

			$post_id = (int) $post_id;

			if ( ! $this->can_edit_page( $post_id ) ) {
				continue;
			}

			PCP_Categories::set_for_page( $post_id, $category_id );
			++$changed;
		}

		wp_safe_redirect( add_query_arg( 'pcp_msg', $changed > 0 ? 'bulk_assigned' : 'no_changes', $redirect ) );
		exit;
	}

	/**
	 * Confirm an ID is a real page the current user may edit.
	 *
	 * @param int $post_id Candidate page ID.
	 * @return bool
	 */
	private function can_edit_page( $post_id ) {
		if ( $post_id <= 0 || 'page' !== get_post_type( $post_id ) ) {
			return false;
		}

		return current_user_can( 'edit_page', $post_id );
	}

	/**
	 * Read the current filter/sort/paging state from the request.
	 *
	 * @return array Sanitized args, omitting anything left at its default.
	 */
	private function current_args() {
		$args = array( 'tab' => 'sort' );

		$category = isset( $_REQUEST['pcp_cat'] ) && is_scalar( $_REQUEST['pcp_cat'] )
			? sanitize_text_field( wp_unslash( $_REQUEST['pcp_cat'] ) )
			: '';

		if ( 'none' === $category || ( '' !== $category && (int) $category > 0 ) ) {
			$args['pcp_cat'] = $category;
		}

		$search = isset( $_REQUEST['pcp_s'] ) && is_string( $_REQUEST['pcp_s'] )
			? sanitize_text_field( wp_unslash( $_REQUEST['pcp_s'] ) )
			: '';

		if ( '' !== $search ) {
			$args['pcp_s'] = $search;
		}

		$orderby = isset( $_REQUEST['pcp_orderby'] ) && is_string( $_REQUEST['pcp_orderby'] )
			? sanitize_key( $_REQUEST['pcp_orderby'] )
			: '';

		if ( in_array( $orderby, array( 'title', 'modified' ), true ) ) {
			$args['pcp_orderby'] = $orderby;
		}

		$order = isset( $_REQUEST['pcp_order'] ) && is_string( $_REQUEST['pcp_order'] )
			? strtolower( sanitize_key( $_REQUEST['pcp_order'] ) )
			: '';

		if ( in_array( $order, array( 'asc', 'desc' ), true ) ) {
			$args['pcp_order'] = $order;
		}

		$paged = isset( $_REQUEST['paged'] ) && is_scalar( $_REQUEST['paged'] ) ? (int) $_REQUEST['paged'] : 0;

		if ( $paged > 1 ) {
			$args['paged'] = $paged;
		}

		return $args;
	}

	/**
	 * Build the page query from the current filter state.
	 *
	 * @return WP_Query
	 */
	private function build_query() {
		$args    = $this->current_args();
		$orderby = isset( $args['pcp_orderby'] ) ? $args['pcp_orderby'] : 'modified';
		$order   = isset( $args['pcp_order'] ) ? strtoupper( $args['pcp_order'] ) : 'ASC';

		$query_args = array(
			'post_type'              => 'page',
			'post_status'            => PCP_Categories::listed_statuses(),
			'posts_per_page'         => self::PER_PAGE,
			'paged'                  => isset( $args['paged'] ) ? $args['paged'] : 1,
			'orderby'                => $orderby,
			'order'                  => $order,
			// Row counts drive the pager; term data is never read on this screen.
			'no_found_rows'          => false,
			'update_post_term_cache' => false,
		);

		if ( isset( $args['pcp_s'] ) ) {
			$query_args['s'] = $args['pcp_s'];
		}

		if ( isset( $args['pcp_cat'] ) ) {
			if ( 'none' === $args['pcp_cat'] ) {
				$query_args['meta_query'] = array(
					'relation' => 'OR',
					array(
						'key'     => PCP_Categories::META_KEY,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => PCP_Categories::META_KEY,
						'value'   => array( '', '0' ),
						'compare' => 'IN',
					),
				);
			} else {
				$query_args['meta_query'] = array(
					array(
						'key'   => PCP_Categories::META_KEY,
						'value' => (string) (int) $args['pcp_cat'],
					),
				);
			}
		}

		return new WP_Query( $query_args );
	}

	/**
	 * Build a URL for this tab with the given args merged over current state.
	 *
	 * @param string $base_url  Screen URL.
	 * @param array  $overrides Args to change; null values are removed.
	 * @return string
	 */
	private function build_url( $base_url, $overrides = array() ) {
		$args = array_merge( $this->current_args(), $overrides );

		foreach ( $args as $key => $value ) {
			if ( null === $value ) {
				unset( $args[ $key ] );
			}
		}

		return add_query_arg( $args, $base_url );
	}

	/**
	 * Render the Sort tab.
	 *
	 * @param string $base_url Screen URL.
	 * @return void
	 */
	public function render( $base_url ) {
		$categories = PCP_Categories::get_all();
		$counts     = PCP_Categories::get_usage_counts();
		$args       = $this->current_args();
		$active_cat = isset( $args['pcp_cat'] ) ? $args['pcp_cat'] : '';
		$search     = isset( $args['pcp_s'] ) ? $args['pcp_s'] : '';
		$query      = $this->build_query();
		?>
		<p class="description">
			<?php esc_html_e( 'Browse every page, filter by category, and retag or retire pages that are out of date. Sorted by oldest edit first so stale pages surface at the top.', 'page-categories' ); ?>
		</p>

		<?php $this->render_filter_chips( $base_url, $categories, $counts, $active_cat ); ?>

		<form method="get" class="pcp-search-form">
			<input type="hidden" name="post_type" value="page" />
			<input type="hidden" name="page" value="<?php echo esc_attr( PCP_Admin_Page::MENU_SLUG ); ?>" />
			<input type="hidden" name="tab" value="sort" />
			<?php if ( '' !== $active_cat ) : ?>
				<input type="hidden" name="pcp_cat" value="<?php echo esc_attr( $active_cat ); ?>" />
			<?php endif; ?>
			<label class="screen-reader-text" for="pcp-search"><?php esc_html_e( 'Search pages', 'page-categories' ); ?></label>
			<input type="search" id="pcp-search" name="pcp_s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search pages…', 'page-categories' ); ?>" />
			<?php submit_button( __( 'Search', 'page-categories' ), 'secondary', '', false ); ?>
			<?php if ( '' !== $search ) : ?>
				<a class="button-link" href="<?php echo esc_url( $this->build_url( $base_url, array( 'pcp_s' => null, 'paged' => null ) ) ); ?>">
					<?php esc_html_e( 'Clear search', 'page-categories' ); ?>
				</a>
			<?php endif; ?>
		</form>

		<?php if ( ! $query->have_posts() ) : ?>
			<p><?php esc_html_e( 'No pages match this filter.', 'page-categories' ); ?></p>
			<?php
			wp_reset_postdata();
			return;
		endif;
		?>

		<form method="post" id="pcp-sort-form">
			<?php wp_nonce_field( 'pcp_sort_pages', 'pcp_sort_nonce' ); ?>

			<div class="tablenav top">
				<div class="alignleft actions">
					<label class="screen-reader-text" for="pcp-bulk-category"><?php esc_html_e( 'Assign selected pages to', 'page-categories' ); ?></label>
					<select name="pcp_bulk_category" id="pcp-bulk-category">
						<option value=""><?php esc_html_e( 'Assign selected to…', 'page-categories' ); ?></option>
						<option value="0"><?php esc_html_e( '— None —', 'page-categories' ); ?></option>
						<?php foreach ( $categories as $category ) : ?>
							<option value="<?php echo (int) $category['id']; ?>"><?php echo esc_html( $category['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php submit_button( __( 'Apply', 'page-categories' ), 'secondary', 'pcp_bulk_apply', false ); ?>
				</div>
				<?php $this->render_pagination( $base_url, $query ); ?>
			</div>

			<table class="wp-list-table widefat fixed striped pcp-sort-table">
				<thead>
					<tr>
						<td class="manage-column column-cb check-column">
							<label class="screen-reader-text" for="pcp-check-all"><?php esc_html_e( 'Select all pages', 'page-categories' ); ?></label>
							<input type="checkbox" id="pcp-check-all" />
						</td>
						<?php $this->render_sortable_header( $base_url, 'title', __( 'Page', 'page-categories' ), 'column-primary' ); ?>
						<th class="pcp-col-cat"><?php esc_html_e( 'Category', 'page-categories' ); ?></th>
						<th class="pcp-col-status"><?php esc_html_e( 'Status', 'page-categories' ); ?></th>
						<th class="pcp-col-author"><?php esc_html_e( 'Author', 'page-categories' ); ?></th>
						<?php $this->render_sortable_header( $base_url, 'modified', __( 'Last Modified', 'page-categories' ), 'pcp-col-modified' ); ?>
					</tr>
				</thead>
				<tbody>
					<?php
					while ( $query->have_posts() ) :
						$query->the_post();
						$this->render_row( get_post(), $categories );
					endwhile;
					wp_reset_postdata();
					?>
				</tbody>
			</table>

			<div class="tablenav bottom">
				<div class="alignleft actions">
					<?php submit_button( __( 'Save Assignments', 'page-categories' ), 'primary', 'pcp_save_assignments', false ); ?>
					<span class="pcp-unsaved-note" hidden><?php esc_html_e( 'You have unsaved category changes.', 'page-categories' ); ?></span>
				</div>
				<?php $this->render_pagination( $base_url, $query ); ?>
			</div>
		</form>
		<?php
	}

	/**
	 * Render the category filter chips.
	 *
	 * @param string $base_url   Screen URL.
	 * @param array  $categories All categories.
	 * @param array  $counts     Usage counts keyed by category ID.
	 * @param string $active     Currently active filter value.
	 * @return void
	 */
	private function render_filter_chips( $base_url, $categories, $counts, $active ) {
		?>
		<div class="pcp-chips">
			<a class="pcp-chip<?php echo '' === $active ? ' pcp-chip-active' : ''; ?>"
				href="<?php echo esc_url( $this->build_url( $base_url, array( 'pcp_cat' => null, 'paged' => null ) ) ); ?>">
				<?php esc_html_e( 'All', 'page-categories' ); ?>
				<span class="pcp-chip-count"><?php echo (int) PCP_Categories::get_total_page_count(); ?></span>
			</a>

			<?php foreach ( $categories as $category ) : ?>
				<?php $count = isset( $counts[ $category['id'] ] ) ? $counts[ $category['id'] ] : 0; ?>
				<a class="pcp-chip<?php echo (string) $category['id'] === (string) $active ? ' pcp-chip-active' : ''; ?>"
					href="<?php echo esc_url( $this->build_url( $base_url, array( 'pcp_cat' => $category['id'], 'paged' => null ) ) ); ?>">
					<span class="pcp-chip-dot" style="background-color:<?php echo esc_attr( $category['color'] ); ?>"></span>
					<?php echo esc_html( $category['name'] ); ?>
					<span class="pcp-chip-count"><?php echo (int) $count; ?></span>
				</a>
			<?php endforeach; ?>

			<a class="pcp-chip<?php echo 'none' === $active ? ' pcp-chip-active' : ''; ?>"
				href="<?php echo esc_url( $this->build_url( $base_url, array( 'pcp_cat' => 'none', 'paged' => null ) ) ); ?>">
				<?php esc_html_e( 'Uncategorized', 'page-categories' ); ?>
				<span class="pcp-chip-count"><?php echo (int) PCP_Categories::get_uncategorized_count(); ?></span>
			</a>
		</div>
		<?php
	}

	/**
	 * Render a sortable column header.
	 *
	 * @param string $base_url Screen URL.
	 * @param string $key      Sort key ('title' or 'modified').
	 * @param string $label    Header label.
	 * @param string $class    Extra CSS classes.
	 * @return void
	 */
	private function render_sortable_header( $base_url, $key, $label, $class = '' ) {
		$args    = $this->current_args();
		$current = isset( $args['pcp_orderby'] ) ? $args['pcp_orderby'] : 'modified';
		$order   = isset( $args['pcp_order'] ) ? $args['pcp_order'] : 'asc';

		$is_active  = ( $current === $key );
		$next_order = ( $is_active && 'asc' === $order ) ? 'desc' : 'asc';

		$classes = trim( 'manage-column sortable ' . $class . ( $is_active ? ' sorted ' . $order : ' desc' ) );
		$url     = $this->build_url(
			$base_url,
			array(
				'pcp_orderby' => $key,
				'pcp_order'   => $next_order,
				'paged'       => null,
			)
		);
		?>
		<th scope="col" class="<?php echo esc_attr( $classes ); ?>">
			<a href="<?php echo esc_url( $url ); ?>">
				<span><?php echo esc_html( $label ); ?></span>
				<span class="sorting-indicator"></span>
			</a>
		</th>
		<?php
	}

	/**
	 * Render one page row.
	 *
	 * @param WP_Post $post       The page.
	 * @param array   $categories All categories.
	 * @return void
	 */
	private function render_row( $post, $categories ) {
		$current   = PCP_Categories::get_for_page( $post->ID );
		$can_edit  = current_user_can( 'edit_page', $post->ID );
		$edit_link = get_edit_post_link( $post->ID );
		$view_link = get_permalink( $post->ID );
		$modified  = get_post_modified_time( 'U', true, $post );
		$status    = get_post_status_object( $post->post_status );
		$title     = get_the_title( $post );

		if ( '' === trim( $title ) ) {
			$title = __( '(no title)', 'page-categories' );
		}
		?>
		<tr>
			<th scope="row" class="check-column">
				<?php if ( $can_edit ) : ?>
					<label class="screen-reader-text" for="pcp-cb-<?php echo (int) $post->ID; ?>">
						<?php
						/* translators: %s: page title. */
						printf( esc_html__( 'Select %s', 'page-categories' ), esc_html( $title ) );
						?>
					</label>
					<input type="checkbox" id="pcp-cb-<?php echo (int) $post->ID; ?>" name="pcp_selected[]" value="<?php echo (int) $post->ID; ?>" />
				<?php endif; ?>
			</th>

			<td class="column-primary">
				<strong>
					<?php if ( $edit_link ) : ?>
						<a href="<?php echo esc_url( $edit_link ); ?>"><?php echo esc_html( $title ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $title ); ?>
					<?php endif; ?>
				</strong>
				<div class="row-actions">
					<?php if ( $edit_link ) : ?>
						<span class="edit"><a href="<?php echo esc_url( $edit_link ); ?>"><?php esc_html_e( 'Edit', 'page-categories' ); ?></a> | </span>
					<?php endif; ?>
					<?php if ( $view_link ) : ?>
						<span class="view"><a href="<?php echo esc_url( $view_link ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'page-categories' ); ?></a></span>
					<?php endif; ?>
					<?php if ( current_user_can( 'delete_page', $post->ID ) ) : ?>
						<span class="trash"> | <a class="submitdelete pcp-trash-link" href="<?php echo esc_url( get_delete_post_link( $post->ID ) ); ?>"><?php esc_html_e( 'Trash', 'page-categories' ); ?></a></span>
					<?php endif; ?>
				</div>
			</td>

			<td class="pcp-col-cat">
				<?php if ( $can_edit ) : ?>
					<label class="screen-reader-text" for="pcp-cat-<?php echo (int) $post->ID; ?>">
						<?php
						/* translators: %s: page title. */
						printf( esc_html__( 'Category for %s', 'page-categories' ), esc_html( $title ) );
						?>
					</label>
					<select class="pcp-row-select" id="pcp-cat-<?php echo (int) $post->ID; ?>" name="pcp_page_category[<?php echo (int) $post->ID; ?>]">
						<option value="0" <?php selected( $current, 0 ); ?>><?php esc_html_e( '— None —', 'page-categories' ); ?></option>
						<?php foreach ( $categories as $category ) : ?>
							<option value="<?php echo (int) $category['id']; ?>" <?php selected( $current, $category['id'] ); ?>>
								<?php echo esc_html( $category['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
					<?php
					$assigned = $current ? PCP_Categories::get( $current ) : null;
					echo $assigned ? pcp_badge_html( $assigned ) : '<span class="pcp-none">&#8212;</span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- badge builder escapes internally.
					?>
				<?php endif; ?>
			</td>

			<td class="pcp-col-status"><?php echo esc_html( $status ? $status->label : $post->post_status ); ?></td>

			<td class="pcp-col-author"><?php echo esc_html( get_the_author_meta( 'display_name', $post->post_author ) ); ?></td>

			<td class="pcp-col-modified">
				<span title="<?php echo esc_attr( get_post_modified_time( 'Y/m/d g:i a', false, $post ) ); ?>">
					<?php
					printf(
						/* translators: %s: human-readable time difference, e.g. "3 years". */
						esc_html__( '%s ago', 'page-categories' ),
						esc_html( human_time_diff( $modified, time() ) )
					);
					?>
				</span>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render pagination links.
	 *
	 * @param string   $base_url Screen URL.
	 * @param WP_Query $query    The page query.
	 * @return void
	 */
	private function render_pagination( $base_url, $query ) {
		if ( $query->max_num_pages < 2 ) {
			return;
		}

		$args    = $this->current_args();
		$current = isset( $args['paged'] ) ? $args['paged'] : 1;

		// add_query_arg() would URL-encode paginate_links()'s %#% token, so pass
		// a plain placeholder through and swap it back afterwards.
		$paginate_base = str_replace(
			'PCPPAGEDTOKEN',
			'%#%',
			$this->build_url( $base_url, array( 'paged' => 'PCPPAGEDTOKEN' ) )
		);

		$links = paginate_links(
			array(
				'base'      => $paginate_base,
				'format'    => '',
				'current'   => $current,
				'total'     => $query->max_num_pages,
				'prev_text' => '&laquo;',
				'next_text' => '&raquo;',
				'type'      => 'plain',
			)
		);

		if ( ! $links ) {
			return;
		}
		?>
		<div class="tablenav-pages">
			<span class="displaying-num">
				<?php
				printf(
					/* translators: %s: number of pages found. */
					esc_html( _n( '%s page', '%s pages', (int) $query->found_posts, 'page-categories' ) ),
					esc_html( number_format_i18n( (int) $query->found_posts ) )
				);
				?>
			</span>
			<span class="pagination-links"><?php echo wp_kses_post( $links ); ?></span>
		</div>
		<?php
	}
}
