function reviews__form(obj) {
	let elem = (obj instanceof Event) ? obj.target : obj;
	if (!elem) return false;
	return elem.closest('form[data-setting="pages-reviews"]') || elem.closest('[data-setting="pages-reviews"] form') || elem.closest('form');
}

function reviews__state(form) {
	return form ? form.querySelector('[data-reviews-integration]') : false;
}

function reviews__init(form) {
	let state = reviews__state(form);
	if (!state) return false;
	return true;
}

function reviews__provider_change(obj) {
	let elem = (obj instanceof Event) ? obj.target : obj;
	let form = reviews__form(elem);
	if (!form) return false;
	let processed = forms__process(elem,false,false);
	if (!processed) return false;
	let post = new FormData();
	for (let key in processed.data) post.append(key,typeof processed.data[key] === 'object' ? JSON.stringify(processed.data[key]) : processed.data[key]);
	if ((elem.name || elem.dataset.name || '') == 'integration_provider') post.set('integration_provider',elem.getAttribute('data-value') || elem.value || post.get('integration_provider') || '');
	if ((elem.name || elem.dataset.name || '') == 'integration_oauth_account') {
		let value = elem.getAttribute('data-value') || elem.value || post.get('integration_oauth_account') || '';
		let parts = value.split('|');
		post.set('integration_oauth_account',value);
		if (parts[0]) post.set('integration_provider',parts[0]);
		if (parts[1]) post.set('integration_account_ref',parts[1]);
	}
	post.set('action','load');
	post.set('id','integration-' + (post.get('integration_id') || 'new'));
	settings__load(form,post);
	return true;
}

function reviews__open_integrations(event) {
	event.preventDefault();
	event.stopPropagation();
	let panel = event.target.closest('[data-panel="true"][data-id]');
	if (panel) panel.removeAttribute('data-form');
	if (typeof settings__open === 'function') settings__open('general-integrations');
	return false;
}

function reviews__mutations_settings() {
	return [{selector:'[data-setting="pages-reviews"] form',callback:reviews__init}];
}

if (typeof mutations__add === 'function') mutations__add('[data-setting="pages-reviews"] form',reviews__init);
document.querySelectorAll('[data-setting="pages-reviews"] form').forEach(form => reviews__init(form));
