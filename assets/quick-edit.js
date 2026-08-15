/**
 * Populate the Quick Edit category dropdown with the page's current value.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		if ( typeof window.inlineEditPost === 'undefined' ) {
			return;
		}

		var wpInlineEdit = window.inlineEditPost.edit;

		window.inlineEditPost.edit = function ( post ) {
			wpInlineEdit.apply( this, arguments );

			var postId = 0;

			if ( typeof post === 'object' ) {
				postId = parseInt( this.getId( post ), 10 );
			} else {
				postId = parseInt( post, 10 );
			}

			if ( ! postId ) {
				return;
			}

			var current = $( '#post-' + postId )
				.find( '.eps-cat-value' )
				.data( 'epsCategory' );

			$( 'select[name="eps_quick_category"]', '#edit-' + postId ).val(
				String( current || 0 )
			);
		};
	} );
} )( jQuery );
