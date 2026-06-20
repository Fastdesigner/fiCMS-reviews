function reviews__form(obj) {
	let elem = (obj instanceof Event) ? obj.target : obj;
	if (!elem) return false;
	return elem.closest('form[data-setting="pages-reviews"]') || elem.closest('[data-setting="pages-reviews"] form') || elem.closest('form');
}

function reviews__state(form) {
	return form ? form.querySelector('[data-reviews-integration]') : false;
}

function reviews__debug(stage, data = {}) {
	console.log('FICMS_REVIEWS_OAUTH_FLOW', stage, data);
}

function reviews__overlay_styles() {
	if (document.getElementById('reviews-oauth-overlay-style')) return true;
	let style = document.createElement('style');
	style.id = 'reviews-oauth-overlay-style';
	style.textContent = 'form[data-reviews-oauth-active="true"]{position:relative}[data-reviews-oauth-overlay]{position:absolute;inset:0;z-index:20;display:grid;align-content:center;gap:var(--system-gap,1rem);padding:1.5rem;background:color-mix(in srgb,canvas 88%,transparent);backdrop-filter:blur(5px);border-radius:var(--system-border-radius,8px);text-align:center}[data-reviews-oauth-overlay] p{margin:0;font-size:1rem;line-height:1.4}[data-reviews-oauth-actions]{display:flex;gap:var(--system-gap,.75rem);justify-content:center;flex-wrap:wrap}[data-reviews-oauth-actions] button{min-width:8rem}';
	document.head.append(style);
	return true;
}

function reviews__overlay_data(form) {
	let data = form ? form.querySelector('[data-reviews-oauth-message]') : false;
	let provider = data ? (data.getAttribute('data-reviews-oauth-provider') || 'Google') : 'Google';
	let message = data ? (data.getAttribute('data-reviews-oauth-message') || '') : '';
	return {
		provider:provider,
		message:(message || 'Schließen Sie die Autorisierung mit %provider% ab.').replace('%provider%',provider),
		refresh:data ? (data.getAttribute('data-reviews-oauth-refresh') || 'Aktualisieren') : 'Aktualisieren',
		cancel:data ? (data.getAttribute('data-reviews-oauth-cancel') || 'Abbrechen') : 'Abbrechen'
	};
}

function reviews__overlay(form, active = true, options = {}) {
	if (!form) return false;
	let overlay = form.querySelector('[data-reviews-oauth-overlay]');
	if (!active) {
		form.removeAttribute('data-reviews-oauth-active');
		if (overlay) overlay.remove();
		return true;
	}

	reviews__overlay_styles();
	let data = reviews__overlay_data(form);
	if (!overlay) {
		overlay = document.createElement('div');
		overlay.setAttribute('data-reviews-oauth-overlay','true');
		let message = document.createElement('p');
		let actions = document.createElement('div');
		let refresh = document.createElement('button');
		let cancel = document.createElement('button');
		actions.setAttribute('data-reviews-oauth-actions','true');
		refresh.type = 'button';
		cancel.type = 'button';
		refresh.className = 'system-button';
		cancel.className = 'system-button';
		refresh.setAttribute('data-reviews-oauth-refresh-button','true');
		cancel.setAttribute('data-reviews-oauth-cancel-button','true');
		actions.append(refresh,cancel);
		overlay.append(message,actions);
		form.append(overlay);
		refresh.addEventListener('click',event => {
			event.preventDefault();
			event.stopPropagation();
			let id = overlay.getAttribute('data-reviews-oauth-id') || '';
			if (id != '') reviews__poll(form,id,false,0,options.button || false,true);
		});
		cancel.addEventListener('click',event => {
			event.preventDefault();
			event.stopPropagation();
			if (timer.reviews__poll) clearTimeout(timer.reviews__poll);
			reviews__overlay(form,false);
			reviews__hint(form,false);
			if (options.button) options.button.disabled = false;
		});
	}
	overlay.querySelector('p').textContent = data.message;
	overlay.querySelector('[data-reviews-oauth-refresh-button]').textContent = data.refresh;
	overlay.querySelector('[data-reviews-oauth-cancel-button]').textContent = data.cancel;
	if (options.id) overlay.setAttribute('data-reviews-oauth-id',options.id);
	form.setAttribute('data-reviews-oauth-active','true');
	return overlay;
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
	form.querySelectorAll('button[data-action="save_connect_integration"]').forEach(button => {
		if (button.getAttribute('data-reviews-connect-bound') == '1') return;
		button.setAttribute('data-reviews-connect-bound','1');
		button.addEventListener('click',reviews__connect,true);
	});
	return true;
}

function reviews__reload(form, id, reason = 'manual') {
	let post = new FormData();
	post.append('action','load');
	post.append('id','integration-' + id);
	reviews__debug('reload:start',{id,reason});
	settings__load(form,post);
	setTimeout(() => {
		let state = document.querySelector('[data-reviews-integration="' + String(id).replace(/\\/g,'\\\\').replace(/"/g,'\\"') + '"]');
		let nextForm = state ? reviews__form(state) : form;
		if (nextForm) reviews__init(nextForm);
	},250);
	return true;
}

function reviews__poll(form, id, popup = false, count = 0, button = false) {
	if (!form || !form.isConnected || id == '' || count >= 18) {
		if (timer.reviews__poll) clearTimeout(timer.reviews__poll);
		reviews__overlay(form,false);
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
		reviews__debug('poll:response',{id,count,connected:result ? result.connected : null,ready:result ? result.ready : null,locations:!!(result && result.locations && result.locations.result)});
		if (result && result.connected == 1) {
			if (timer.reviews__poll) clearTimeout(timer.reviews__poll);
			reviews__overlay(form,false);
			reviews__hint(form,false);
			if (button) button.disabled = false;
			reviews__reload(form,id,'oauth-connected');
			return;
		}
		if (popup && popup.closed) popup = false;
		if (timer.reviews__poll) clearTimeout(timer.reviews__poll);
		timer.reviews__poll = setTimeout(reviews__poll,10000,form,id,popup,count + 1,button);
	}).catch(() => {
		reviews__debug('poll:error',{id,count});
		if (timer.reviews__poll) clearTimeout(timer.reviews__poll);
		timer.reviews__poll = setTimeout(reviews__poll,10000,form,id,popup,count + 1,button);
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
	if (!post.has('id')) post.append('id',button.getAttribute('data-actionid') || post.get('integration_id') || 'new');

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
		reviews__debug('connect:response',{result:!!(result && result.result),redirect:!!(result && result.redirect),id:result ? result.id : false});
		if (!result || result.result !== true || !result.redirect) {
			button.disabled = false;
			reviews__overlay(form,false);
			reviews__hint(form,false);
			if (popup) popup.close();
			return;
		}
		let id = result.id || (button.getAttribute('data-actionid') || post.get('id') || '');
		window.fiCMSReviewsOAuth = {form,id,popup,button};
		reviews__overlay(form,true,{id:id,button:button});
		if (popup) popup.location.href = result.redirect;
		else window.open(result.redirect,'ficmsPopup','popup=yes,width=620,height=780,left=120,top=80,menubar=no,toolbar=no,status=no,scrollbars=yes,resizable=yes');
		reviews__poll(form,id,popup,0,button);
	}).catch(() => {
		button.disabled = false;
		reviews__overlay(form,false);
		reviews__hint(form,false);
		if (popup) popup.close();
	});

	return false;
}

function reviews__oauth_message(event) {
	let data = event && event.data ? event.data : false;
	if (!data || data.type !== 'ficms.oauth' || !window.fiCMSReviewsOAuth) return;
	if (event.origin !== window.location.origin) return;
	reviews__debug('oauth:message',{provider:data.provider || '',result:data.result === true});
	if (data.result !== true) return;
	let state = window.fiCMSReviewsOAuth;
	reviews__poll(state.form,state.id,state.popup || false,0,state.button || false);
}

function reviews__mutations_settings() {
	return [{selector:'[data-setting="pages-reviews"] form',callback:reviews__init}];
}

if (typeof mutations__add === 'function') mutations__add('[data-setting="pages-reviews"] form',reviews__init);
window.removeEventListener('message',reviews__oauth_message);
window.addEventListener('message',reviews__oauth_message);
document.querySelectorAll('[data-setting="pages-reviews"] form').forEach(form => reviews__init(form));
