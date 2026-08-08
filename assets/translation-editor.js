document.addEventListener('DOMContentLoaded', function () {
	document.addEventListener('click', function (event) {
		var link = event.target.closest('[data-openlingua-confirm]');
		if (link && !window.confirm(link.getAttribute('data-openlingua-confirm'))) {
			event.preventDefault();
		}
	});

	var fields = Array.prototype.slice.call(document.querySelectorAll('[data-openlingua-translation]'));
	var progress = document.querySelector('[data-openlingua-progress]');
	var bar = document.querySelector('[data-openlingua-progress-bar]');
	var search = document.querySelector('.openlingua-editor__search input');
	var richSegments = Array.prototype.slice.call(document.querySelectorAll('[data-openlingua-rich-segment]'));
	var memory = window.OpenLinguaTranslationMemory || { fields: {}, label: '' };
	var memoryGroups = {};

	function richVisual(field) {
		var segment = field.closest('[data-openlingua-rich-segment]');
		return segment ? segment.querySelector('[data-openlingua-target-visual]') : null;
	}

	function setFieldValue(field, value, showBadge) {
		field.value = value;
		var visual = richVisual(field);
		if (visual) { visual.innerHTML = value; }
		if (showBadge) {
			var target = field.closest('.openlingua-editor__target');
			if (target && !target.querySelector('.openlingua-memory-badge')) {
				var badge = document.createElement('span');
				badge.className = 'openlingua-memory-badge';
				badge.textContent = memory.label;
				target.appendChild(badge);
			}
		}
	}

	fields.forEach(function (field) {
		var config = memory.fields[field.id];
		if (!config) { return; }
		memoryGroups[config.key] = memoryGroups[config.key] || [];
		memoryGroups[config.key].push(field);
		if (config.applied) { setFieldValue(field, field.value, true); }
	});
	Object.keys(memoryGroups).forEach(function (key) {
		var translated = memoryGroups[key].find(function (field) { return field.value.trim() !== ''; });
		if (!translated) { return; }
		memoryGroups[key].forEach(function (field) {
			if (field.value.trim() === '') { setFieldValue(field, translated.value, true); }
		});
	});

	function propagateTranslation(field) {
		var config = memory.fields[field.id];
		if (!config || field.value.trim() === '') { return; }
		(memoryGroups[config.key] || []).forEach(function (peer) {
			if (peer !== field && peer.value.trim() === '') { setFieldValue(peer, field.value, true); }
		});
		updateProgress();
	}

	function updateProgress() {
		var complete = fields.filter(function (field) { return field.value.trim() !== ''; }).length;
		var percent = fields.length ? Math.round((complete / fields.length) * 100) : 0;
		progress.textContent = percent + '%';
		bar.style.width = percent + '%';
	}

	fields.forEach(function (field) {
		field.addEventListener('input', updateProgress);
		field.addEventListener('change', function () { propagateTranslation(field); });
	});
	richSegments.forEach(function (segment) {
		var toggle = segment.querySelector('[data-openlingua-html-toggle]');
		var toggleLabel = segment.querySelector('[data-openlingua-toggle-label]');
		var sourceVisual = segment.querySelector('[data-openlingua-source-visual]');
		var sourceCode = segment.querySelector('[data-openlingua-source-code]');
		var targetVisual = segment.querySelector('[data-openlingua-target-visual]');
		var targetCode = segment.querySelector('[data-openlingua-target-code]');

		function syncVisualToCode() {
			targetCode.value = targetVisual.innerHTML;
			updateProgress();
		}

		targetVisual.addEventListener('input', syncVisualToCode);
		targetVisual.addEventListener('blur', function () { syncVisualToCode(); propagateTranslation(targetCode); });
		targetVisual.addEventListener('paste', function (event) {
			var clipboard = event.clipboardData || window.clipboardData;
			if (!clipboard) { return; }
			event.preventDefault();
			var text = clipboard.getData('text/plain').replace(/\u00a0/g, ' ').replace(/\s+$/, '');
			document.execCommand('insertText', false, text);
			syncVisualToCode();
		});
		targetCode.addEventListener('input', function () {
			targetVisual.innerHTML = targetCode.value;
		});
		toggle.addEventListener('click', function () {
			var showCode = !segment.classList.contains('is-code-mode');
			if (showCode) { syncVisualToCode(); } else { targetVisual.innerHTML = targetCode.value; }
			segment.classList.toggle('is-code-mode', showCode);
			sourceVisual.hidden = showCode;
			sourceCode.hidden = !showCode;
			targetVisual.hidden = showCode;
			targetCode.hidden = !showCode;
			toggleLabel.textContent = showCode ? toggle.getAttribute('data-hide-html') : toggle.getAttribute('data-show-html');
			toggle.setAttribute('aria-pressed', showCode ? 'true' : 'false');
		});
	});

	var form = document.querySelector('.openlingua-editor form');
	if (form) {
		form.addEventListener('submit', function () {
			richSegments.forEach(function (segment) {
				if (!segment.classList.contains('is-code-mode')) {
					segment.querySelector('[data-openlingua-target-code]').value = segment.querySelector('[data-openlingua-target-visual]').innerHTML;
				}
			});
		});
	}
	if (search) {
		search.addEventListener('input', function () {
			var query = search.value.toLowerCase().trim();
			document.querySelectorAll('[data-openlingua-segment]').forEach(function (segment) {
				segment.hidden = query !== '' && segment.textContent.toLowerCase().indexOf(query) === -1;
			});
		});
	}
	updateProgress();
});
