/**
 * ART Forms frontend runtime.
 */
(function () {
	'use strict';

	function ready(fn) {
		if (document.readyState !== 'loading') {
			fn();
		} else {
			document.addEventListener('DOMContentLoaded', fn);
		}
	}

	function getUtm() {
		var params = new URLSearchParams(window.location.search || '');
		return {
			utm_source: params.get('utm_source') || '',
			utm_medium: params.get('utm_medium') || '',
			utm_campaign: params.get('utm_campaign') || '',
			utm_content: params.get('utm_content') || '',
			utm_term: params.get('utm_term') || ''
		};
	}

	function collectFields(form) {
		var data = {};
		var elements = form.querySelectorAll('input, select, textarea');

		elements.forEach(function (el) {
			if (!el.name || el.disabled) {
				return;
			}

			var name = el.name;
			var type = (el.type || '').toLowerCase();

			if (type === 'checkbox') {
				var base = name.endsWith('[]') ? name.slice(0, -2) : name;
				if (name.endsWith('[]')) {
					if (!Array.isArray(data[base])) {
						data[base] = [];
					}
					if (el.checked) {
						data[base].push(el.value);
					}
				} else {
					if (el.checked) {
						data[name] = el.value || '1';
					}
				}
				return;
			}

			if (type === 'radio') {
				if (el.checked) {
					data[name] = el.value;
				}
				return;
			}

			data[name] = el.value;
		});

		return data;
	}

	function showMessage(form, message, isError) {
		var box = form.querySelector('[data-art-form-success]');
		if (!box) {
			box = document.createElement('div');
			box.setAttribute('data-art-form-success', '');
			form.appendChild(box);
		}
		box.textContent = message || '';
		box.style.display = message ? 'block' : 'none';
		box.setAttribute('data-art-form-status', isError ? 'error' : 'success');
	}

	function handleSubmit(event) {
		var form = event.target;
		if (!(form instanceof HTMLFormElement)) {
			return;
		}

		var formId = form.getAttribute('data-art-form-id');
		if (!formId) {
			return;
		}

		event.preventDefault();

		if (typeof artForms === 'undefined' || !artForms.restUrl) {
			return;
		}

		var submitBtn = form.querySelector('[type="submit"]');
		if (submitBtn) {
			submitBtn.disabled = true;
		}

		showMessage(form, (artForms.strings && artForms.strings.sending) || '…', false);

		var utm = getUtm();
		var payload = {
			form_id: parseInt(formId, 10),
			fields: collectFields(form),
			meta: {
				page_url: window.location.href,
				referrer: document.referrer || '',
				user_agent: navigator.userAgent || '',
				utm_source: utm.utm_source,
				utm_medium: utm.utm_medium,
				utm_campaign: utm.utm_campaign,
				utm_content: utm.utm_content,
				utm_term: utm.utm_term
			}
		};

		fetch(artForms.restUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json'
			},
			body: JSON.stringify(payload)
		})
			.then(function (response) {
				return response.json().then(function (data) {
					return { ok: response.ok, data: data };
				});
			})
			.then(function (result) {
				if (submitBtn) {
					submitBtn.disabled = false;
				}

				if (!result.ok || !result.data || !result.data.success) {
					var msg =
						(result.data && result.data.message) ||
						(artForms.strings && artForms.strings.error) ||
						'Error';
					showMessage(form, msg, true);
					return;
				}

				showMessage(form, result.data.success_message || '', false);
				form.reset();

				if (result.data.redirect_url) {
					var delaySec = parseInt(result.data.redirect_delay, 10);
					if (isNaN(delaySec) || delaySec < 0) {
						delaySec = 3;
					}
					window.setTimeout(function () {
						window.location.href = result.data.redirect_url;
					}, delaySec * 1000);
				}
			})
			.catch(function () {
				if (submitBtn) {
					submitBtn.disabled = false;
				}
				showMessage(
					form,
					(artForms.strings && artForms.strings.error) || 'Error',
					true
				);
			});
	}

	ready(function () {
		document.addEventListener(
			'submit',
			function (event) {
				var form = event.target;
				if (form && form.matches && form.matches('form[data-art-form-id]')) {
					handleSubmit(event);
				}
			},
			true
		);
	});
})();
