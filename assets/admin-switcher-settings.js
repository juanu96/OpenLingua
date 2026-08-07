( function () {
	'use strict';

	function selected( name ) {
		var input = document.querySelector( '[name="' + name + '"]:checked' );
		return input ? input.value : '';
	}

	function checked( name ) {
		var input = document.querySelector( '[name="' + name + '"]' );
		return !! ( input && input.checked );
	}

	function escapeHtml( value ) {
		var element = document.createElement( 'span' );
		element.textContent = String( value || '' );
		return element.innerHTML;
	}

	function label( language ) {
		var parts = [];
		if ( checked( 'switcher_show_flag' ) ) { parts.push( '<span aria-hidden="true">' + escapeHtml( language.flag ) + '</span>' ); }
		if ( checked( 'switcher_show_name' ) ) { parts.push( '<span>' + escapeHtml( language.name ) + '</span>' ); }
		if ( checked( 'switcher_show_native_name' ) && language.nativeName !== language.name ) { parts.push( '<span>' + escapeHtml( language.nativeName ) + '</span>' ); }
		return parts.length ? parts.join( ' ' ) : escapeHtml( String( language.code || '' ).toUpperCase() );
	}

	function render() {
		var target = document.querySelector( '[data-openlingua-switcher-preview]' );
		var data = window.openLinguaSwitcherPreview || {};
		var languages = data.languages || [];
		if ( ! target || ! languages.length ) { return; }
		var current = languages[ data.current || 0 ];
		if ( selected( 'switcher_style' ) === 'dropdown' ) {
			target.innerHTML = '<div class="openlingua-preview-dropdown"><strong>' + label( current ) + ' <span aria-hidden="true">▾</span></strong><div>' + languages.slice( 1 ).map( function ( language ) { return '<span>' + label( language ) + '</span>'; } ).join( '' ) + '</div></div>';
			return;
		}
		var visible = checked( 'switcher_show_current' ) ? languages : languages.slice( 1 );
		target.innerHTML = '<div class="openlingua-preview-list">' + visible.map( function ( language ) { return '<span>' + label( language ) + '</span>'; } ).join( '' ) + '</div>';
	}

	document.addEventListener( 'change', function ( event ) {
		if ( event.target.closest( '.openlingua-switcher-config' ) ) { render(); }
	} );
	document.addEventListener( 'DOMContentLoaded', render );
}() );
