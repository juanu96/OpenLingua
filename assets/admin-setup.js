(function () {
	'use strict';
	var form = document.querySelector('[data-openlingua-setup]');
	if (!form) return;
	var steps = Array.prototype.slice.call(form.querySelectorAll('[data-step]'));
	var progress = Array.prototype.slice.call(document.querySelectorAll('.openlingua-setup__progress li'));
	var back = form.querySelector('[data-setup-back]');
	var next = form.querySelector('[data-setup-next]');
	var finish = form.querySelector('[data-setup-finish]');
	var current = 0;
	function render() {
		steps.forEach(function (step, index) { step.hidden = index !== current; step.classList.toggle('is-active', index === current); });
		progress.forEach(function (item, index) { item.classList.toggle('is-active', index === current); item.classList.toggle('is-complete', index < current); });
		back.hidden = current === 0;
		next.hidden = current === steps.length - 1;
		finish.hidden = current !== steps.length - 1;
		var firstControl = steps[current].querySelector('select, input');
		if (firstControl) firstControl.focus({ preventScroll: true });
		window.scrollTo({ top: 0, behavior: 'smooth' });
	}
	next.addEventListener('click', function () { if (current < steps.length - 1) { current += 1; render(); } });
	back.addEventListener('click', function () { if (current > 0) { current -= 1; render(); } });
	render();
}());
