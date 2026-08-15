/**
 * Management screen: drag-to-reorder, delete confirmation, live badge preview.
 */
( function ( $ ) {
	'use strict';

	function textColorFor( hex ) {
		hex = String( hex || '' ).replace( '#', '' );

		if ( hex.length === 3 ) {
			hex = hex[ 0 ] + hex[ 0 ] + hex[ 1 ] + hex[ 1 ] + hex[ 2 ] + hex[ 2 ];
		}

		if ( hex.length !== 6 ) {
			return '#1d2327';
		}

		var r = parseInt( hex.substr( 0, 2 ), 16 );
		var g = parseInt( hex.substr( 2, 2 ), 16 );
		var b = parseInt( hex.substr( 4, 2 ), 16 );
		var luminance = 0.299 * r + 0.587 * g + 0.114 * b;

		return luminance > 150 ? '#1d2327' : '#ffffff';
	}

	$( function () {
		var $rows = $( '#eps-cat-rows' );

		// Drag to reorder. Row order in the form is the saved order.
		if ( $rows.length && $.fn.sortable ) {
			$rows.sortable( {
				handle: '.eps-drag-handle',
				axis: 'y',
				placeholder: 'eps-sortable-placeholder',
				helper: function ( event, $row ) {
					// Keep cell widths while dragging.
					var $original = $row.children();
					var $helper = $row.clone();

					$helper.children().each( function ( index ) {
						$( this ).width( $original.eq( index ).width() );
					} );

					return $helper;
				},
			} );
		}

		// Confirm deletes.
		$rows.on( 'click', '.eps-delete-btn', function ( event ) {
			if (
				window.epsManage &&
				! window.confirm( window.epsManage.confirmDelete )
			) {
				event.preventDefault();
			}
		} );

		// Live badge preview while editing name or color.
		$rows.on( 'input change', '.eps-name-input, .eps-color-input', function () {
			var $row = $( this ).closest( 'tr' );
			var name = $row.find( '.eps-name-input' ).val();
			var color = $row.find( '.eps-color-input' ).val();

			$row.find( '.eps-badge' )
				.text( name || '—' )
				.css( {
					backgroundColor: color,
					color: textColorFor( color ),
				} );
		} );

		initSortTab();
	} );

	/**
	 * Sort Pages tab: select-all, unsaved-change tracking, trash confirmation.
	 */
	function initSortTab() {
		var $form = $( '#eps-sort-form' );

		if ( ! $form.length ) {
			return;
		}

		var dirty = false;

		$form.on( 'change', '#eps-check-all', function () {
			$form
				.find( 'input[name="eps_selected[]"]' )
				.prop( 'checked', $( this ).prop( 'checked' ) );
		} );

		// Highlight rows with an unsaved category change.
		$form.on( 'change', '.eps-row-select', function () {
			dirty = true;
			$( this ).closest( 'tr' ).addClass( 'eps-row-dirty' );
			$( '.eps-unsaved-note' ).prop( 'hidden', false );
		} );

		// Submitting saves the changes, so stop warning.
		$form.on( 'submit', function () {
			dirty = false;
		} );

		$( window ).on( 'beforeunload', function () {
			if ( dirty ) {
				// Modern browsers show their own generic message.
				return window.epsManage ? window.epsManage.unsavedWarn : '';
			}
		} );

		// Leaving via Trash would discard pending edits silently.
		$form.on( 'click', '.eps-trash-link', function ( event ) {
			if (
				dirty &&
				! window.confirm(
					window.epsManage
						? window.epsManage.unsavedWarn
						: 'Discard unsaved changes?'
				)
			) {
				event.preventDefault();
				return;
			}

			dirty = false;
		} );
	}
} )( jQuery );
