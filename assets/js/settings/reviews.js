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

function reviews__language(key,fallback) {
	let value = typeof settings__language_apply === 'function' ? settings__language_apply(key) : key;
	return value && value !== key ? value : fallback;
}

function reviews__oauth_context(obj) {
	let elem = (obj instanceof Event) ? obj.target : obj;
	let form = reviews__form(elem);
	let state = reviews__state(form);
	let value = form ? (form.querySelector('[name="integration_oauth_account"]')?.value || '') : '';
	let parts = value.split('|');
	let provider = parts[0] || state?.dataset.reviewsProvider || form?.querySelector('[name="integration_provider"]')?.value || '';
	let accountRef = parts[1] || form?.querySelector('[name="integration_account_ref"]')?.value || 'default';
	return {elem,form,state,provider:provider.trim(),accountRef:accountRef.trim() || 'default',id:(provider.trim() ? provider.trim() + '|' + (accountRef.trim() || 'default') : '')};
}

function reviews__oauth_styles() {
	if (document.getElementById('reviews-oauth-overlay-style')) return true;
	let style = document.createElement('style');
	style.id = 'reviews-oauth-overlay-style';
	style.textContent = '[data-reviews-oauth-active="true"]{position:relative}[data-reviews-oauth-overlay]{position:absolute;inset:0;z-index:20;display:grid;align-content:center;gap:var(--system-gap,1rem);padding:1.5rem;background:color-mix(in srgb,canvas 88%,transparent);backdrop-filter:blur(5px);border-radius:var(--system-border-radius,8px);text-align:center}[data-reviews-oauth-overlay] p{margin:0;font-size:1rem;line-height:1.4}[data-reviews-oauth-actions]{display:flex;gap:var(--system-gap,.75rem);justify-content:center;flex-wrap:wrap}[data-reviews-oauth-actions] button{min-width:8rem}';
	document.head.append(style);
	return true;
}

function reviews__oauth_overlay(ctx,active = true,options = {}) {
	if (!ctx.form) return false;
	let overlay = ctx.form.querySelector('[data-reviews-oauth-overlay]');
	if (!active) {
		ctx.form.removeAttribute('data-reviews-oauth-active');
		if (overlay) overlay.remove();
		return true;
	}
	reviews__oauth_styles();
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
		ctx.form.append(overlay);
		refresh.addEventListener('click',event => {
			event.preventDefault();
			event.stopPropagation();
			reviews__oauth_poll(ctx,0,options.button || false);
		});
		cancel.addEventListener('click',event => {
			event.preventDefault();
			event.stopPropagation();
			if (window.fiCMSReviewsOAuth?.timer) clearTimeout(window.fiCMSReviewsOAuth.timer);
			reviews__oauth_overlay(ctx,false);
			if (options.button) options.button.disabled = false;
		});
	}
	let provider = ctx.provider.charAt(0).toUpperCase() + ctx.provider.slice(1);
	overlay.querySelector('p').textContent = reviews__language('_reviews_integration_oauth_overlay','Schließen Sie die Autorisierung mit %provider% ab.').replace('%provider%',provider);
	overlay.querySelector('[data-reviews-oauth-refresh-button]').textContent = reviews__language('_reviews_integration_oauth_refresh','Aktualisieren');
	overlay.querySelector('[data-reviews-oauth-cancel-button]').textContent = reviews__language('_reviews_integration_oauth_cancel','Abbrechen');
	ctx.form.setAttribute('data-reviews-oauth-active','true');
	return overlay;
}

function reviews__oauth_reload(ctx) {
	if (!ctx.form || !ctx.form.isConnected) return false;
	let processed = forms__process(ctx.form,false,false);
	if (!processed) return false;
	let post = new FormData();
	for (let key in processed.data) post.append(key,typeof processed.data[key] === 'object' ? JSON.stringify(processed.data[key]) : processed.data[key]);
	post.set('integration_provider',ctx.provider);
	post.set('integration_account_ref',ctx.accountRef);
	post.set('integration_oauth_account',ctx.id);
	post.set('action','load');
	post.set('id','integration-' + (post.get('integration_id') || 'new'));
	settings__load(ctx.form,post);
	return true;
}

function reviews__oauth_poll(ctx,count = 0,button = false) {
	if (!ctx.form || !ctx.form.isConnected || ctx.id == '' || count >= 18) {
		if (window.fiCMSReviewsOAuth?.timer) clearTimeout(window.fiCMSReviewsOAuth.timer);
		reviews__oauth_overlay(ctx,false);
		if (button) button.disabled = false;
		return false;
	}
	let post = new FormData();
	post.append('type','general-integrations');
	post.append('settings',true);
	post.append('action','oauth_status');
	post.append('id',ctx.id);
	fiCMS__refresh(false,post,false,{params:['loadwidget=settings','settingsType=general-integrations']}).then(response => {
		let data = fiCMS__json(response);
		let result = data && data.result ? data.result : false;
		if (result && result.connected == 1) {
			if (window.fiCMSReviewsOAuth?.timer) clearTimeout(window.fiCMSReviewsOAuth.timer);
			reviews__oauth_overlay(ctx,false);
			if (button) button.disabled = false;
			reviews__oauth_reload(ctx);
			return;
		}
		if (window.fiCMSReviewsOAuth?.timer) clearTimeout(window.fiCMSReviewsOAuth.timer);
		window.fiCMSReviewsOAuth.timer = setTimeout(reviews__oauth_poll,10000,ctx,count + 1,button);
	}).catch(() => {
		if (window.fiCMSReviewsOAuth?.timer) clearTimeout(window.fiCMSReviewsOAuth.timer);
		window.fiCMSReviewsOAuth.timer = setTimeout(reviews__oauth_poll,10000,ctx,count + 1,button);
	});
	return true;
}

function reviews__open_integrations(event) {
	event.preventDefault();
	event.stopImmediatePropagation();
	let button = event.target.closest('[data-load]') || event.target;
	let ctx = reviews__oauth_context(button);
	if (!ctx.form || ctx.id == '') return false;
	let post = new FormData();
	post.append('type','general-integrations');
	post.append('settings',true);
	post.append('action','connect_oauth');
	post.append('id',ctx.id);
	let popup = false;
	try {
		popup = window.open('', 'ficmsPopup', 'popup=yes,width=620,height=780,left=120,top=80,menubar=no,toolbar=no,status=no,scrollbars=yes,resizable=yes');
	} catch(e) {
		popup = false;
	}
	button.disabled = true;
	reviews__oauth_overlay(ctx,true,{button});
	fiCMS__refresh(false,post,false,{params:['loadwidget=settings','settingsType=general-integrations']}).then(response => {
		let data = fiCMS__json(response);
		let result = data && data.result ? data.result : false;
		if (!result || result.result !== true || !result.redirect) {
			button.disabled = false;
			reviews__oauth_overlay(ctx,false);
			if (popup) popup.close();
			return;
		}
		window.fiCMSReviewsOAuth = {ctx,popup,button,timer:false};
		if (popup) popup.location.href = result.redirect;
		else window.open(result.redirect,'ficmsPopup','popup=yes,width=620,height=780,left=120,top=80,menubar=no,toolbar=no,status=no,scrollbars=yes,resizable=yes');
		reviews__oauth_poll(ctx,0,button);
	}).catch(() => {
		button.disabled = false;
		reviews__oauth_overlay(ctx,false);
		if (popup) popup.close();
	});
	return false;
}

function reviews__oauth_message(event) {
	let data = event && event.data ? event.data : false;
	if (!data || data.type !== 'ficms.oauth' || !window.fiCMSReviewsOAuth) return;
	if (event.origin !== window.location.origin || data.result !== true) return;
	reviews__oauth_poll(window.fiCMSReviewsOAuth.ctx,0,window.fiCMSReviewsOAuth.button || false);
}

window.removeEventListener('message',reviews__oauth_message);
window.addEventListener('message',reviews__oauth_message);

function reviews__mutations_settings() {
	return [{selector:'[data-setting="pages-reviews"] form',callback:reviews__init}];
}

if (typeof mutations__add === 'function') mutations__add('[data-setting="pages-reviews"] form',reviews__init);
document.querySelectorAll('[data-setting="pages-reviews"] form').forEach(form => reviews__init(form));
