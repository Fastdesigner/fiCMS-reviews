function reviews__form(obj) {
	let elem = (obj instanceof Event) ? obj.target : obj;
	return elem ? elem.closest('[data-setting="pages-reviews"] form') : false;
}

function reviews__state(form) {
	return form ? form.querySelector('[data-reviews-integration]') : false;
}

function reviews__hint(form, active = false) {
	let hint = form ? form.querySelector('[data-reviews-connect-hint]') : false;
	if (!hint) return false;
	active ? hint.classList.remove('forms__hidden') : hint.classList.add('forms__hidden');
	return true;
}

function reviews__init(form) {
	let state = reviews__state(form);
	if (!state) return false;
	let connected = state.getAttribute('data-reviews-connected') == '1';
	if (connected) reviews__hint(form,false);
	form.querySelectorAll('button[data-action="save_integration"]').forEach(button => {
		button.disabled = !connected && state.getAttribute('data-reviews-provider') == 'google';
	});
	return true;
}

function reviews__reload(form, id) {
	let post = new FormData();
	post.append('action','load');
	post.append('id','integration-' + id);
	settings__load(form,post);
}

function reviews__poll(form, id, popup = false, count = 0, button = false) {
	if (!form || !form.isConnected || id == '' || count >= 18) {
		if (timer.reviews__poll) clearTimeout(timer.reviews__poll);
		reviews__hint(form,false);
		if (button) button.disabled = false;
		return false;
	}

	let post = new FormData();
	post.append('type','pages-reviews');
	post.append('settings',true);
	post.append('action','integration_status');
	post.append('id',id);

	fiCMS__refresh(false,post,false,{params:['loadwidget=settings','settingsType=pages-reviews']}).then(response => {
		let data = fiCMS__json(response);
		let result = data && data.result ? data.result : false;
		if (result && result.connected == 1) {
			if (timer.reviews__poll) clearTimeout(timer.reviews__poll);
			reviews__hint(form,false);
			reviews__reload(form,id);
			return;
		}
		if (popup && popup.closed) {
			if (timer.reviews__poll) clearTimeout(timer.reviews__poll);
			reviews__hint(form,false);
			if (button) button.disabled = false;
			return;
		}
		timer.reviews__poll = setTimeout(reviews__poll,10000,form,id,popup,count + 1,button);
	}).catch(() => {
		if (timer.reviews__poll) clearTimeout(timer.reviews__poll);
		reviews__hint(form,false);
		if (button) button.disabled = false;
	});

	return true;
}

function reviews__connect(event) {
	event.preventDefault();
	event.stopImmediatePropagation();

	let button = event.target.closest('[data-load]') || event.target;
	let form = reviews__form(button);
	if (!form) return false;

	let post = forms__read(button);
	if (!post) return false;
	if (!post.has('type')) post.append('type','pages-reviews');
	if (!post.has('settings')) post.append('settings',true);
	if (!post.has('action')) post.append('action',button.getAttribute('data-action') || 'save_connect_integration');
	if (!post.has('id')) post.append('id',button.getAttribute('data-actionid') || 'new');

	let popup = false;
	try {
		popup = window.open('', 'ficmsPopup', 'popup=yes,width=620,height=780,left=120,top=80,menubar=no,toolbar=no,status=no,scrollbars=yes,resizable=yes');
	} catch(e) {
		popup = false;
	}

	reviews__hint(form,true);
	button.disabled = true;

	fiCMS__refresh(false,post,button,{params:['loadwidget=settings','settingsType=pages-reviews']}).then(response => {
		let data = fiCMS__json(response);
		let result = data && data.result ? data.result : false;
		if (!result || result.result !== true || !result.redirect) {
			button.disabled = false;
			reviews__hint(form,false);
			if (popup) popup.close();
			return;
		}
		if (popup) popup.location.href = result.redirect;
		else window.open(result.redirect,'ficmsPopup','popup=yes,width=620,height=780,left=120,top=80,menubar=no,toolbar=no,status=no,scrollbars=yes,resizable=yes');
		reviews__poll(form,result.id || (button.getAttribute('data-actionid') || ''),popup,0,button);
	}).catch(() => {
		button.disabled = false;
		reviews__hint(form,false);
		if (popup) popup.close();
	});

	return false;
}

function reviews__mutations_settings() {
	return [{selector:'[data-setting="pages-reviews"] form',callback:reviews__init}];
}

if (typeof mutations__add === 'function') mutations__add('[data-setting="pages-reviews"] form',reviews__init);
document.querySelectorAll('[data-setting="pages-reviews"] form').forEach(form => reviews__init(form));
