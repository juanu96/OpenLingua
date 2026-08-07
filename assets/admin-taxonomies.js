(function () {
	'use strict';
	var modal = document.querySelector('[data-openlingua-taxonomy-modal]');
	if (!modal) return;
	var form = modal.querySelector('form');
	var previousFocus = null;

	function close() {
		modal.hidden = true;
		document.body.classList.remove('openlingua-modal-open');
		if (previousFocus) previousFocus.focus();
	}

	document.addEventListener('click', function (event) {
		var button = event.target.closest('[data-openlingua-taxonomy-edit]');
		if (button) {
			var data = JSON.parse(button.getAttribute('data-term'));
			previousFocus = button;
			form.elements.source_id.value = data.sourceId;
			form.elements.target_id.value = data.targetId;
			form.elements.taxonomy.value = data.taxonomy;
			form.elements.language.value = data.language;
			form.elements.name.value = data.name || '';
			form.elements.slug.value = data.slug || '';
			form.elements.description.value = data.description || '';
			modal.querySelector('[data-openlingua-taxonomy-title]').textContent = data.flag + ' ' + data.languageName;
			modal.querySelector('[data-openlingua-taxonomy-source]').textContent = data.sourceName;
			modal.querySelector('[data-openlingua-taxonomy-url]').textContent = '/' + (data.language || '') + '/…/' + (data.slug || '') + '/';
			modal.hidden = false;
			document.body.classList.add('openlingua-modal-open');
			form.elements.name.focus();
			return;
		}
		if (event.target.closest('[data-openlingua-taxonomy-close]')) close();
	});

	document.addEventListener('keydown', function (event) { if ('Escape' === event.key && !modal.hidden) close(); });
	form.elements.slug.addEventListener('input', function () {
		modal.querySelector('[data-openlingua-taxonomy-url]').textContent = '/' + form.elements.language.value + '/…/' + form.elements.slug.value + '/';
	});
}());
