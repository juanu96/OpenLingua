( function () {
	'use strict';

	function mountLanguageSelector() {
		var template = document.getElementById( 'openlingua-menu-language-template' );
		var menuName = document.getElementById( 'menu-name' );
		if ( ! template || ! menuName || document.getElementById( 'openlingua-menu-language' ) ) {
			return;
		}

		var row = menuName.closest( '.menu-name-label' ) || menuName.parentNode;
		row.classList.add( 'openlingua-menu-name-row' );
		row.appendChild( template.content.cloneNode( true ) );

		var select = document.getElementById( 'openlingua-menu-language' );
		select.addEventListener( 'change', function () {
			if ( select.value ) {
				window.location.href = select.value;
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', mountLanguageSelector );
	} else {
		mountLanguageSelector();
	}
}() );
