(function(){
	'use strict';
	var root=document.querySelector('[data-openlingua-provider-tabs]');
	if(!root){return;}
	var tabs=Array.prototype.slice.call(root.querySelectorAll('[data-openlingua-provider-tab]'));
	var panels=Array.prototype.slice.call(root.querySelectorAll('[data-openlingua-provider-panel]'));
	var query=new URLSearchParams(window.location.search);
	var requested=query.get('section');
	var saved='';
	try{saved=window.localStorage.getItem('openlingua-provider-tab')||'';}catch(e){}
	var available=tabs.map(function(tab){return tab.getAttribute('data-openlingua-provider-tab');});
	var active=available.indexOf(requested)!==-1?requested:(available.indexOf(saved)!==-1?saved:available[0]);
	function select(provider,focus){
		tabs.forEach(function(tab){var selected=tab.getAttribute('data-openlingua-provider-tab')===provider;tab.classList.toggle('nav-tab-active',selected);tab.setAttribute('aria-selected',selected?'true':'false');tab.tabIndex=selected?0:-1;if(selected&&focus){tab.focus();}});
		panels.forEach(function(panel){panel.hidden=panel.getAttribute('data-openlingua-provider-panel')!==provider;});
		try{window.localStorage.setItem('openlingua-provider-tab',provider);}catch(e){}
	}
	tabs.forEach(function(tab){tab.addEventListener('click',function(){select(tab.getAttribute('data-openlingua-provider-tab'),false);});tab.addEventListener('keydown',function(event){if(event.key!=='ArrowRight'&&event.key!=='ArrowLeft'){return;}event.preventDefault();var index=tabs.indexOf(tab);var next=event.key==='ArrowRight'?(index+1)%tabs.length:(index-1+tabs.length)%tabs.length;select(tabs[next].getAttribute('data-openlingua-provider-tab'),true);});});
	select(active,false);
})();
