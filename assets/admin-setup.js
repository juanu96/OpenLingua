(function () {
	'use strict';
	var form = document.querySelector('[data-openlingua-setup]');
	if (!form) return;
	var steps = Array.prototype.slice.call(form.querySelectorAll('[data-step]'));
	var progress = Array.prototype.slice.call(document.querySelectorAll('.openlingua-setup__progress li'));
	var back = form.querySelector('[data-setup-back]');
	var next = form.querySelector('[data-setup-next]');
	var finish = form.querySelector('[data-setup-finish]');
	var stepLabel = form.querySelector('[data-step-label]');
	var languageInputs = Array.prototype.slice.call(form.querySelectorAll('[name="enabled_languages[]"]'));
	var primary = form.querySelector('[data-primary-language]');
	var current = 0;
	var text = window.openlinguaSetupL10n || { language: 'language', languages: 'languages', dropdown: 'Dropdown', inline: 'Inline', flags: 'Flags', noFlags: 'No flags', names: 'Names', empty: '—' };

	function checkedLanguages() { return languageInputs.filter(function (input) { return input.checked; }); }
	function selectedRadio(name) { return form.querySelector('[name="' + name + '"]:checked'); }
	function labelFor(input) { return input ? input.closest('label').querySelector('strong, span').textContent.trim() : text.empty; }
	function updateLanguageCount() {
		var count = checkedLanguages().length;
		var node = form.querySelector('[data-language-count]');
		if (node) node.textContent = count + ' ' + (count === 1 ? text.language : text.languages);
	}
	function ensurePrimaryLanguage() {
		var match = languageInputs.find(function (input) { return input.value === primary.value; });
		if (match) match.checked = true;
		updateLanguageCount();
	}
	function updatePreview() {
		var preview = form.querySelector('[data-switcher-preview]');
		if (!preview) return;
		var languages = checkedLanguages().slice(0, 3);
		var showFlags = form.elements.show_flag.checked;
		var showNames = form.elements.show_name.checked;
		var nativeNames = form.elements.show_native_name.checked;
		var dropdown = form.elements.dropdown.checked;
		preview.className = dropdown ? 'is-dropdown' : 'is-list';
		preview.innerHTML = '';
		var menu = dropdown ? document.createElement('div') : preview;
		if (dropdown) menu.className = 'openlingua-setup__preview-menu';
		languages.forEach(function (input, index) {
			var item = document.createElement('span');
			if (showFlags) {
				var flag = input.dataset.flagUrl ? document.createElement('img') : document.createElement('b');
				if (input.dataset.flagUrl) { flag.src = input.dataset.flagUrl; flag.alt = ''; }
				else flag.textContent = input.dataset.flag;
				item.appendChild(flag);
			}
			if (showNames) item.appendChild(document.createTextNode(nativeNames ? input.dataset.native : input.dataset.english));
			if (!showFlags && !showNames) item.appendChild(document.createTextNode(input.value.toUpperCase()));
			if (dropdown && index === 0) { var arrow = document.createElement('i'); arrow.textContent = '⌄'; item.appendChild(arrow); }
			if (dropdown && index === 0) preview.appendChild(item);
			else menu.appendChild(item);
		});
		if (dropdown && languages.length > 1) preview.appendChild(menu);
	}
	function updateSummary() {
		var nativeNames = form.elements.show_native_name.checked;
		var languages = checkedLanguages().map(function (input) { return nativeNames ? input.dataset.native : input.dataset.english; });
		var url = selectedRadio('url_mode');
		var status = selectedRadio('new_translation_status');
		form.querySelector('[data-summary-languages]').textContent = languages.join(', ') || text.empty;
		form.querySelector('[data-summary-url]').textContent = url ? url.closest('label').querySelector('strong').textContent : text.empty;
		form.querySelector('[data-summary-switcher]').textContent = (form.elements.dropdown.checked ? text.dropdown + ' · ' : text.inline + ' · ') + (form.elements.show_flag.checked ? text.flags : text.noFlags) + (form.elements.show_name.checked ? ' · ' + text.names : '');
		form.querySelector('[data-summary-status]').textContent = labelFor(status);
	}
	function render() {
		steps.forEach(function (step, index) { step.hidden = index !== current; step.classList.toggle('is-active', index === current); });
		progress.forEach(function (item, index) { item.classList.toggle('is-active', index === current); item.classList.toggle('is-complete', index < current); });
		back.hidden = current === 0;
		next.hidden = current === steps.length - 1;
		finish.hidden = current !== steps.length - 1;
		stepLabel.textContent = (current + 1) + ' / ' + steps.length;
		if (current === 2) updatePreview();
		if (current === 4) updateSummary();
		var firstControl = steps[current].querySelector('input:not([type="hidden"]), select');
		if (firstControl) firstControl.focus({ preventScroll: true });
		window.scrollTo({ top: 0, behavior: 'smooth' });
	}

	form.querySelector('[data-language-search]').addEventListener('input', function (event) {
		var query = event.target.value.toLowerCase().trim();
		form.querySelectorAll('[data-language-option]').forEach(function (option) { option.hidden = query && option.dataset.search.indexOf(query) === -1; });
	});
	primary.addEventListener('change', ensurePrimaryLanguage);
	languageInputs.forEach(function (input) { input.addEventListener('change', function () { ensurePrimaryLanguage(); updatePreview(); }); });
	form.querySelectorAll('[data-preview-control]').forEach(function (control) { control.addEventListener('change', updatePreview); });
	next.addEventListener('click', function () { if (current < steps.length - 1) { current += 1; render(); } });
	back.addEventListener('click', function () { if (current > 0) { current -= 1; render(); } });
	ensurePrimaryLanguage();
	render();
}());
