(function () {
	'use strict';
	var modal = document.querySelector('[data-openlingua-taxonomy-modal]');
	if (!modal) return;
	var form = modal.querySelector('form');
	var previousFocus = null;
	var seoSection = modal.querySelector('[data-openlingua-taxonomy-seo]');
	var seoFields = modal.querySelector('[data-openlingua-taxonomy-seo-fields]');

	function renderSeo(fields) {
		seoFields.textContent = '';
		(fields || []).forEach(function (field) {
			var label = document.createElement('label');
			var title = document.createElement('span');
			var source = document.createElement('small');
			var input = document.createElement('textarea');
			title.textContent = field.provider + ' — ' + field.label;
			source.textContent = field.source || '';
			input.name = 'seo_translation[' + field.id + ']';
			input.rows = /description/i.test(field.label) ? 4 : 2;
			input.value = field.target || '';
			label.appendChild(title);
			label.appendChild(source);
			label.appendChild(input);
			seoFields.appendChild(label);
		});
		seoSection.hidden = !(fields || []).length;
	}

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
			renderSeo(data.seoFields);
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
