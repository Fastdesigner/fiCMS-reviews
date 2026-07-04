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
	reviews__debug_account_fields(form);
	return true;
}

function reviews__debug_account_fields(form) {
	let fields = Array.from(form.querySelectorAll('input,select')).filter(elem => (elem.name || elem.dataset.name || '') == 'integration_account_ref' || (elem.value || '') == 'default');
	if (fields.length <= 1) return false;
	console.log('[fiCMS-reviews account-field]',fields.map(elem => ({tag:elem.tagName.toLowerCase(),type:elem.type || '',name:elem.name || '',data_name:elem.dataset.name || '',value:elem.value || '',hidden:elem.closest('.forms__hidden') ? 1 : 0})));
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
	post.set('action','load');
	post.set('id','integration-' + (post.get('integration_id') || 'new'));
	settings__load(form,post);
	return true;
}

function reviews__open_integrations(event) {
	event.preventDefault();
	event.stopImmediatePropagation();
	return typeof settings__open === 'function' ? settings__open('general-integrations') : false;
}

function reviews__mutations_settings() {
	return [{selector:'[data-setting="pages-reviews"] form',callback:reviews__init}];
}

if (typeof mutations__add === 'function') mutations__add('[data-setting="pages-reviews"] form',reviews__init);
document.querySelectorAll('[data-setting="pages-reviews"] form').forEach(form => reviews__init(form));
