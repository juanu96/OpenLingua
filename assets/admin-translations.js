(function () {
	'use strict';

	document.addEventListener('click', function (event) {
		var link = event.target.closest('[data-openlingua-confirm]');
		if (link && !window.confirm(link.getAttribute('data-openlingua-confirm'))) {
			event.preventDefault();
		}
	});
}());
