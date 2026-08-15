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
		var $rows = $( '#pcp-cat-rows' );

		// Drag to reorder. Row order in the form is the saved order.
		if ( $rows.length && $.fn.sortable ) {
			$rows.sortable( {
				handle: '.pcp-drag-handle',
				axis: 'y',
				placeholder: 'pcp-sortable-placeholder',
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
		$rows.on( 'click', '.pcp-delete-btn', function ( event ) {
			if (
				window.pcpManage &&
				! window.confirm( window.pcpManage.confirmDelete )
			) {
				event.preventDefault();
			}
		} );

		// Live badge preview while editing name or color.
		$rows.on( 'input change', '.pcp-name-input, .pcp-color-input', function () {
			var $row = $( this ).closest( 'tr' );
			var name = $row.find( '.pcp-name-input' ).val();
			var color = $row.find( '.pcp-color-input' ).val();

			$row.find( '.pcp-badge' )
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
		var $form = $( '#pcp-sort-form' );

		if ( ! $form.length ) {
			return;
		}

		var dirty = false;

		$form.on( 'change', '#pcp-check-all', function () {
			$form
				.find( 'input[name="pcp_selected[]"]' )
				.prop( 'checked', $( this ).prop( 'checked' ) );
		} );

		// Highlight rows with an unsaved category change.
		$form.on( 'change', '.pcp-row-select', function () {
			dirty = true;
			$( this ).closest( 'tr' ).addClass( 'pcp-row-dirty' );
			$( '.pcp-unsaved-note' ).prop( 'hidden', false );
		} );

		// Submitting saves the changes, so stop warning.
		$form.on( 'submit', function () {
			dirty = false;
		} );

		$( window ).on( 'beforeunload', function () {
			if ( dirty ) {
				// Modern browsers show their own generic message.
				return window.pcpManage ? window.pcpManage.unsavedWarn : '';
			}
		} );

		// Leaving via Trash would discard pending edits silently.
		$form.on( 'click', '.pcp-trash-link', function ( event ) {
			if (
				dirty &&
				! window.confirm(
					window.pcpManage
						? window.pcpManage.unsavedWarn
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
