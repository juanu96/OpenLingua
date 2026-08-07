( function () {
	'use strict';

	var finePointer = window.matchMedia( '(hover: hover) and (pointer: fine)' );

	function close( details ) {
		details.open = false;
		details.removeAttribute( 'data-openlingua-pinned' );
	}

	document.addEventListener( 'pointerenter', function ( event ) {
		if ( ! finePointer.matches ) { return; }
		var details = event.target.closest && event.target.closest( '.openlingua-switcher--dropdown' );
		if ( details ) { details.open = true; }
	}, true );

	document.addEventListener( 'pointerleave', function ( event ) {
		if ( ! finePointer.matches ) { return; }
		var details = event.target.closest && event.target.closest( '.openlingua-switcher--dropdown' );
		if ( details && ! details.contains( event.relatedTarget ) && ! details.hasAttribute( 'data-openlingua-pinned' ) ) { details.open = false; }
	}, true );

	document.addEventListener( 'click', function ( event ) {
		var summary = event.target.closest && event.target.closest( '.openlingua-switcher--dropdown > summary' );
		if ( summary && finePointer.matches ) {
			event.preventDefault();
			var details = summary.parentElement;
			if ( details.hasAttribute( 'data-openlingua-pinned' ) ) { close( details ); }
			else { details.open = true; details.setAttribute( 'data-openlingua-pinned', '1' ); }
			return;
		}
		if ( ! event.target.closest( '.openlingua-switcher--dropdown' ) ) {
			document.querySelectorAll( '.openlingua-switcher--dropdown[data-openlingua-pinned]' ).forEach( close );
		}
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' !== event.key ) { return; }
		document.querySelectorAll( '.openlingua-switcher--dropdown[open]' ).forEach( close );
	} );
}() );
