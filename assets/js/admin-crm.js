/**
 * ART Forms CRM (Ответы).
 */
(function () {
	'use strict';

	var root = document.getElementById('art-forms-crm');
	if (!root || typeof artFormsCrm === 'undefined') {
		return;
	}

	var formId = parseInt(root.getAttribute('data-form-id') || '0', 10);
	var navIds = [];
	try {
		navIds = JSON.parse(root.getAttribute('data-nav-ids') || '[]') || [];
	} catch (e) {
		navIds = [];
	}

	var currentId = 0;
	var modal = document.getElementById('art-forms-crm-modal');
	var cardData = null;
	var cardEditing = false;

	function str(key, fallback) {
		if (artFormsCrm.strings && artFormsCrm.strings[key]) {
			return artFormsCrm.strings[key];
		}
		return fallback || key;
	}

	function post(action, data) {
		var body = new window.FormData();
		body.append('action', action);
		body.append('nonce', artFormsCrm.nonce);
		Object.keys(data || {}).forEach(function (k) {
			var v = data[k];
			if (Array.isArray(v)) {
				v.forEach(function (item) {
					body.append(k + '[]', item);
				});
			} else if (v !== undefined && v !== null) {
				body.append(k, v);
			}
		});
		return fetch(artFormsCrm.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		}).then(function (r) {
			return r.json();
		});
	}

	function esc(text) {
		var d = document.createElement('div');
		d.textContent = text == null ? '' : String(text);
		return d.innerHTML;
	}

	function openCard(id) {
		currentId = parseInt(id, 10) || 0;
		if (!currentId || !modal) {
			return;
		}
		modal.hidden = false;
		document.body.classList.add('art-forms-crm-modal-open');
		document.getElementById('art-forms-crm-modal-title').textContent =
			str('loading', '…');
		document.getElementById('art-forms-crm-modal-fields').innerHTML = '';
		document.getElementById('art-forms-crm-modal-comments').innerHTML = '';
		document.getElementById('art-forms-crm-modal-related').innerHTML = '';
		document.getElementById('art-forms-crm-modal-meta').innerHTML = '';
		var actBox = document.getElementById('art-forms-crm-modal-activity');
		if (actBox) {
			actBox.innerHTML = '';
		}

		post('art_forms_crm_get_card', { id: currentId }).then(function (res) {
			if (!res || !res.success) {
				alert((res && res.data && res.data.message) || str('error'));
				closeModal();
				return;
			}
			renderCard(res.data);
			updateUrl(currentId);
		});
	}

	function closeModal() {
		if (!modal) {
			return;
		}
		modal.hidden = true;
		document.body.classList.remove('art-forms-crm-modal-open');
		updateUrl(0);
		currentId = 0;
	}

	function updateUrl(viewId) {
		if (!window.history || !window.history.replaceState) {
			return;
		}
		var url = new URL(window.location.href);
		if (viewId > 0) {
			url.searchParams.set('view', String(viewId));
		} else {
			url.searchParams.delete('view');
		}
		window.history.replaceState({}, '', url.toString());
	}

	function setEditMode(on) {
		cardEditing = !!on;
		var editBtn = document.getElementById('art-forms-crm-edit-fields');
		var saveBtn = document.getElementById('art-forms-crm-save-fields');
		var cancelBtn = document.getElementById('art-forms-crm-cancel-fields');
		if (editBtn) {
			editBtn.hidden = cardEditing;
		}
		if (saveBtn) {
			saveBtn.hidden = !cardEditing;
		}
		if (cancelBtn) {
			cancelBtn.hidden = !cardEditing;
		}
		if (cardData) {
			renderMeta(cardData);
			renderFields(cardData);
		}
	}

	function renderMeta(data) {
		var nameLine = '<p><strong>' + esc(str('name', 'Имя')) + ':</strong> ';
		if (cardEditing) {
			nameLine +=
				'<input type="text" class="art-forms-crm-edit-input" id="art-forms-crm-edit-name" value="' +
				esc(data.contact_name || '') +
				'" />';
		} else {
			nameLine += esc(data.contact_name || '—');
		}
		nameLine += '</p>';

		var emailLine =
			'<p><strong>Email:</strong> ';
		if (cardEditing) {
			emailLine +=
				'<input type="email" class="art-forms-crm-edit-input" id="art-forms-crm-edit-email" value="' +
				esc(data.contact_email || '') +
				'" />';
		} else {
			emailLine += esc(data.contact_email || '—');
			if (data.profile_url) {
				emailLine +=
					' <a class="art-forms-crm-profile-link" href="' +
					esc(data.profile_url) +
					'" target="_blank" rel="noopener noreferrer">' +
					esc(str('profile', 'профиль')) +
					'</a>';
			}
		}
		emailLine += '</p>';

		var phoneLine = '<p><strong>Телефон:</strong> ';
		if (cardEditing) {
			phoneLine +=
				'<input type="text" class="art-forms-crm-edit-input" id="art-forms-crm-edit-phone" value="' +
				esc(data.contact_phone || '') +
				'" />';
		} else {
			phoneLine += esc(data.contact_phone || '—');
		}
		phoneLine += '</p>';

		var prio = parseInt(data.priority, 10) || 0;
		var prioLabels = data.priorities || {};
		var prioLine = '<p><strong>' + esc(str('priority', 'Приоритет')) + ':</strong> ';
		if (cardEditing) {
			prioLine += '<select class="art-forms-crm-edit-input" id="art-forms-crm-edit-priority">';
			Object.keys(prioLabels).forEach(function (k) {
				prioLine +=
					'<option value="' +
					esc(k) +
					'"' +
					(parseInt(k, 10) === prio ? ' selected' : '') +
					'>' +
					esc(prioLabels[k]) +
					'</option>';
			});
			prioLine += '</select>';
		} else {
			prioLine +=
				'<span class="art-forms-crm-priority art-forms-crm-priority-' +
				esc(String(prio)) +
				'">' +
				esc(data.priority_label || prioLabels[prio] || '') +
				'</span>';
		}
		prioLine += '</p>';

		var tags = Array.isArray(data.tags) ? data.tags : [];
		var tagsLine = '<p><strong>' + esc(str('tags', 'Теги')) + ':</strong> ';
		if (cardEditing) {
			tagsLine +=
				'<input type="text" class="art-forms-crm-edit-input" id="art-forms-crm-edit-tags" value="' +
				esc(tags.join(', ')) +
				'" placeholder="' +
				esc(str('tagsHint', 'через запятую')) +
				'" />';
		} else if (tags.length) {
			tagsLine += tags
				.map(function (t) {
					return '<span class="art-forms-crm-tag">' + esc(t) + '</span>';
				})
				.join(' ');
		} else {
			tagsLine += '—';
		}
		tagsLine += '</p>';

		var metaHtml =
			nameLine +
			emailLine +
			phoneLine +
			prioLine +
			tagsLine +
			'<p><strong>Дата:</strong> ' +
			esc(data.created_at || '') +
			'</p>';
		if (data.page_url) {
			metaHtml += '<p><strong>URL:</strong> ' + esc(data.page_url) + '</p>';
		}
		if (data.utm && data.utm.length) {
			metaHtml += '<p><strong>UTM:</strong> ' + esc(data.utm.join(' | ')) + '</p>';
		}
		if (data.status_badge) {
			metaHtml += '<p><strong>Доставка:</strong> ' + data.status_badge + '</p>';
		}
		document.getElementById('art-forms-crm-modal-meta').innerHTML = metaHtml;
	}

	function fieldRawArray(raw) {
		if (Array.isArray(raw)) {
			return raw.map(String);
		}
		if (raw == null || raw === '') {
			return [];
		}
		return [String(raw)];
	}

	function renderFieldEditor(f) {
		var type = f.type || 'text';
		var key = f.key;
		var raw = f.raw;
		var html = '';

		if (type === 'textarea') {
			html =
				'<textarea class="art-forms-crm-edit-input art-forms-crm-edit-field" data-key="' +
				esc(key) +
				'" rows="3">' +
				esc(raw == null ? '' : String(raw)) +
				'</textarea>';
		} else if (type === 'select' || type === 'radio') {
			html =
				'<select class="art-forms-crm-edit-input art-forms-crm-edit-field" data-key="' +
				esc(key) +
				'">';
			(f.options || []).forEach(function (opt) {
				html +=
					'<option value="' +
					esc(opt) +
					'"' +
					(String(raw) === String(opt) ? ' selected' : '') +
					'>' +
					esc(opt) +
					'</option>';
			});
			html += '</select>';
		} else if (type === 'checkbox') {
			var selected = fieldRawArray(raw);
			html = '<div class="art-forms-crm-edit-checks" data-key="' + esc(key) + '">';
			(f.options || []).forEach(function (opt) {
				var on = selected.indexOf(String(opt)) !== -1;
				html +=
					'<label><input type="checkbox" value="' +
					esc(opt) +
					'"' +
					(on ? ' checked' : '') +
					'/> ' +
					esc(opt) +
					'</label>';
			});
			html += '</div>';
		} else if (type === 'consent') {
			html =
				'<label><input type="checkbox" class="art-forms-crm-edit-field" data-key="' +
				esc(key) +
				'" value="1"' +
				(raw && String(raw) !== '0' ? ' checked' : '') +
				'/> ' +
				esc(f.label || '') +
				'</label>';
		} else {
			var inputType = type === 'email' ? 'email' : type === 'tel' ? 'tel' : 'text';
			html =
				'<input type="' +
				inputType +
				'" class="art-forms-crm-edit-input art-forms-crm-edit-field" data-key="' +
				esc(key) +
				'" value="' +
				esc(raw == null ? '' : String(raw)) +
				'" />';
		}
		return html;
	}

	function renderFields(data) {
		var fieldsHtml = '<dl class="art-forms-crm-fields-list">';
		(data.fields || []).forEach(function (f) {
			var wrapClass = f.type === 'consent' ? ' class="is-consent"' : '';
			fieldsHtml += '<div' + wrapClass + '><dt>' + esc(f.label) + '</dt><dd>';
			if (cardEditing) {
				fieldsHtml += renderFieldEditor(f);
			} else {
				fieldsHtml += esc(f.value);
				if (f.type === 'consent' && f.privacy_url) {
					fieldsHtml +=
						' <a class="art-forms-crm-consent-doc" href="' +
						esc(f.privacy_url) +
						'" target="_blank" rel="noopener noreferrer">' +
						esc(str('consentDoc', 'документ')) +
						'</a>';
				}
			}
			fieldsHtml += '</dd></div>';
		});
		fieldsHtml += '</dl>';
		document.getElementById('art-forms-crm-modal-fields').innerHTML = fieldsHtml;
	}

	function collectEditedPayload() {
		var payload = {};
		document.querySelectorAll('.art-forms-crm-edit-field[data-key]').forEach(function (el) {
			var key = el.getAttribute('data-key');
			if (!key) {
				return;
			}
			if (el.type === 'checkbox') {
				payload[key] = el.checked ? '1' : '';
			} else {
				payload[key] = el.value;
			}
		});
		document.querySelectorAll('.art-forms-crm-edit-checks[data-key]').forEach(function (wrap) {
			var key = wrap.getAttribute('data-key');
			var vals = [];
			wrap.querySelectorAll('input[type="checkbox"]:checked').forEach(function (cb) {
				vals.push(cb.value);
			});
			payload[key] = vals;
		});
		return payload;
	}

	function syncListFromCard(data) {
		if (!data || !data.id) {
			return;
		}
		var id = String(data.id);
		var prio = parseInt(data.priority, 10);
		if (isNaN(prio)) {
			prio = 0;
		}
		var label =
			data.priority_label ||
			(data.priorities && data.priorities[prio]) ||
			String(prio);
		var tagsArr = Array.isArray(data.tags) ? data.tags : [];
		var tagsStr = tagsArr.length ? tagsArr.join(', ') : '—';

		var row = root.querySelector('.art-forms-crm-row[data-id="' + id + '"]');
		if (row) {
			var cell = row.querySelector('td[data-col="priority"]');
			if (cell) {
				cell.setAttribute('data-full', label);
				var badge = cell.querySelector('.art-forms-crm-priority');
				if (!badge) {
					badge = document.createElement('span');
					badge.className = 'art-forms-crm-priority art-forms-crm-cell-text';
					cell.innerHTML = '';
					cell.appendChild(badge);
				}
				badge.className =
					'art-forms-crm-priority art-forms-crm-priority-' +
					prio +
					' art-forms-crm-cell-text';
				badge.textContent = label;
			}
			var tagsCell = row.querySelector('td[data-col="tags"]');
			if (tagsCell) {
				tagsCell.setAttribute('data-full', tagsStr);
				var tText = tagsCell.querySelector('.art-forms-crm-cell-text');
				if (!tText) {
					tText = document.createElement('span');
					tText.className = 'art-forms-crm-cell-text';
					tagsCell.innerHTML = '';
					tagsCell.appendChild(tText);
				}
				tText.textContent = tagsStr;
			}
			var nameCell = row.querySelector('td[data-col="name"]');
			if (nameCell) {
				var cname = (data.contact_name || '').trim();
				nameCell.setAttribute('data-full', cname);
				var nText = nameCell.querySelector('.art-forms-crm-cell-text');
				if (!nText) {
					nText = document.createElement('span');
					nText.className = 'art-forms-crm-cell-text';
					nameCell.innerHTML = '';
					nameCell.appendChild(nText);
				}
				nText.textContent = cname || '—';
			}
		}

		var card = root.querySelector('.art-forms-crm-board-card[data-id="' + id + '"]');
		if (card) {
			var pEl = card.querySelector('.art-forms-crm-board-card-priority');
			if (prio > 0) {
				if (!pEl) {
					pEl = document.createElement('span');
					pEl.className = 'art-forms-crm-board-card-priority';
					var dateEl = card.querySelector('.art-forms-crm-board-card-date');
					var openBtn = card.querySelector('.art-forms-crm-board-card-open');
					if (openBtn && dateEl) {
						openBtn.insertBefore(pEl, dateEl);
					}
				}
				pEl.className =
					'art-forms-crm-board-card-priority art-forms-crm-priority art-forms-crm-priority-' +
					prio;
				pEl.textContent = label;
			} else if (pEl) {
				pEl.remove();
			}
			var tEl = card.querySelector('.art-forms-crm-board-card-tags');
			if (tagsArr.length) {
				if (!tEl) {
					tEl = document.createElement('span');
					tEl.className = 'art-forms-crm-board-card-tags';
					var dateEl2 = card.querySelector('.art-forms-crm-board-card-date');
					var openBtn2 = card.querySelector('.art-forms-crm-board-card-open');
					if (openBtn2 && dateEl2) {
						openBtn2.insertBefore(tEl, dateEl2);
					}
				}
				tEl.textContent = tagsArr.join(', ');
			} else if (tEl) {
				tEl.remove();
			}
		}
	}

	function renderCard(data) {
		cardData = data;

		document.getElementById('art-forms-crm-modal-title').textContent =
			'#' + data.id;

		var stageSelect = document.getElementById('art-forms-crm-modal-stage');
		stageSelect.innerHTML = '';
		(data.stages || []).forEach(function (st) {
			var opt = document.createElement('option');
			opt.value = String(st.id);
			opt.textContent = st.title;
			if (parseInt(st.id, 10) === parseInt(data.stage_id, 10)) {
				opt.selected = true;
			}
			stageSelect.appendChild(opt);
		});

		var starBtn = document.getElementById('art-forms-crm-modal-star');
		starBtn.classList.toggle('is-on', !!data.is_starred);
		starBtn.setAttribute('data-id', String(data.id));

		var del = document.getElementById('art-forms-crm-modal-delete');
		del.href = data.delete_url || '#';
		del.onclick = function () {
			return window.confirm(str('confirmDelete'));
		};

		setEditMode(false);

		renderComments(data.comments || []);
		renderActivity(data.activity || []);

		var relatedHtml =
			'<h3>' +
			esc(str('related')) +
			': ' +
			esc(String(data.related_count || 1)) +
			'</h3><ul>';
		(data.related || []).forEach(function (r) {
			relatedHtml +=
				'<li><button type="button" class="button-link art-forms-crm-open-card" data-id="' +
				esc(String(r.id)) +
				'">#' +
				esc(String(r.id)) +
				'</button> — ' +
				esc(r.form_title || '') +
				' · ' +
				esc(r.created_at) +
				'</li>';
		});
		relatedHtml += '</ul>';
		document.getElementById('art-forms-crm-modal-related').innerHTML = relatedHtml;
	}

	function renderComments(comments) {
		var box = document.getElementById('art-forms-crm-modal-comments');
		if (!comments.length) {
			box.innerHTML = '';
			return;
		}
		box.innerHTML = comments
			.map(function (c) {
				var del = c.can_delete
					? '<button type="button" class="button-link-delete art-forms-crm-comment-del" data-id="' +
					  esc(String(c.id)) +
					  '">' +
					  esc(str('delete')) +
					  '</button>'
					: '';
				return (
					'<div class="art-forms-crm-comment" data-id="' +
					esc(String(c.id)) +
					'"><div class="art-forms-crm-comment-head"><strong>' +
					esc(c.author_name) +
					'</strong> <span>' +
					esc(c.created_at) +
					'</span> ' +
					del +
					'</div><div class="art-forms-crm-comment-body">' +
					esc(c.body) +
					'</div></div>'
				);
			})
			.join('');
	}

	function renderActivity(items) {
		var box = document.getElementById('art-forms-crm-modal-activity');
		if (!box) {
			return;
		}
		if (!items || !items.length) {
			box.innerHTML =
				'<p class="art-forms-crm-activity-empty">' +
				esc(str('noActivity', 'Пока нет записей')) +
				'</p>';
			return;
		}
		box.innerHTML = items
			.map(function (a) {
				return (
					'<div class="art-forms-crm-activity-item" data-type="' +
					esc(a.event_type || '') +
					'"><div class="art-forms-crm-activity-head"><strong>' +
					esc(a.author_name || '') +
					'</strong><span>' +
					esc(a.created_at || '') +
					'</span></div><div class="art-forms-crm-activity-summary">' +
					esc(a.summary || '') +
					'</div></div>'
				);
			})
			.join('');
	}

	function navigate(delta) {
		if (!navIds.length || !currentId) {
			return;
		}
		var idx = navIds.indexOf(currentId);
		if (idx < 0) {
			idx = 0;
		}
		var next = navIds[idx + delta];
		if (next) {
			openCard(next);
		}
	}

	function selectedIds() {
		return Array.prototype.map.call(
			document.querySelectorAll('.art-forms-crm-row-check:checked'),
			function (el) {
				return parseInt(el.value, 10);
			}
		);
	}

	function updateBulkBar() {
		var bar = document.getElementById('art-forms-crm-bulk-bar');
		if (!bar) {
			return;
		}
		bar.hidden = selectedIds().length === 0;
	}

	function savePrefs(extra) {
		var data = Object.assign({ form_id: formId }, extra || {});
		return post('art_forms_crm_save_prefs', data);
	}

	// Events
	root.addEventListener('click', function (e) {
		var openBtn = e.target.closest('.art-forms-crm-open-card');
		if (openBtn) {
			e.preventDefault();
			openCard(openBtn.getAttribute('data-id'));
			return;
		}

		var star = e.target.closest('.art-forms-crm-star');
		if (star && star.id !== 'art-forms-crm-modal-star') {
			e.preventDefault();
			e.stopPropagation();
			var sid = star.getAttribute('data-id');
			post('art_forms_crm_toggle_star', { id: sid }).then(function (res) {
				if (res && res.success) {
					star.classList.toggle('is-on', !!res.data.is_starred);
				}
			});
			return;
		}

		if (e.target.closest('[data-crm-close]')) {
			closeModal();
		}
	});

	if (modal) {
		document.getElementById('art-forms-crm-prev').addEventListener('click', function () {
			navigate(-1);
		});
		document.getElementById('art-forms-crm-next').addEventListener('click', function () {
			navigate(1);
		});
		document.getElementById('art-forms-crm-modal-star').addEventListener('click', function () {
			var btn = this;
			post('art_forms_crm_toggle_star', { id: currentId }).then(function (res) {
				if (res && res.success) {
					btn.classList.toggle('is-on', !!res.data.is_starred);
					var rowStar = root.querySelector(
						'.art-forms-crm-star[data-id="' + currentId + '"]'
					);
					if (rowStar) {
						rowStar.classList.toggle('is-on', !!res.data.is_starred);
					}
				}
			});
		});

		var editBtn = document.getElementById('art-forms-crm-edit-fields');
		var saveBtn = document.getElementById('art-forms-crm-save-fields');
		var cancelBtn = document.getElementById('art-forms-crm-cancel-fields');
		if (editBtn) {
			editBtn.addEventListener('click', function () {
				setEditMode(true);
			});
		}
		if (cancelBtn) {
			cancelBtn.addEventListener('click', function () {
				setEditMode(false);
			});
		}
		if (saveBtn) {
			saveBtn.addEventListener('click', function () {
				var emailEl = document.getElementById('art-forms-crm-edit-email');
				var phoneEl = document.getElementById('art-forms-crm-edit-phone');
				var nameEl = document.getElementById('art-forms-crm-edit-name');
				var email = emailEl ? emailEl.value.trim() : '';
				if (email && email.indexOf('@') === -1) {
					alert(str('invalidEmail'));
					return;
				}
				saveBtn.disabled = true;
				post('art_forms_crm_update_fields', {
					id: currentId,
					contact_name: nameEl ? nameEl.value : '',
					contact_email: email,
					contact_phone: phoneEl ? phoneEl.value : '',
					priority: (document.getElementById('art-forms-crm-edit-priority') || {}).value || 0,
					tags: (document.getElementById('art-forms-crm-edit-tags') || {}).value || '',
					payload: JSON.stringify(collectEditedPayload()),
				}).then(function (res) {
					saveBtn.disabled = false;
					if (res && res.success && res.data) {
						renderCard(res.data);
						syncListFromCard(res.data);
					} else {
						alert((res && res.data && res.data.message) || str('error'));
					}
				});
			});
		}

		document.getElementById('art-forms-crm-modal-stage').addEventListener('change', function () {
			var stageId = this.value;
			post('art_forms_crm_set_stage', { id: currentId, stage_id: stageId }).then(
				function (res) {
					if (res && res.success) {
						var stage = (res.data && res.data.stage) || {};
						var row = root.querySelector(
							'.art-forms-crm-row[data-id="' + currentId + '"]'
						);
						if (row) {
							row.setAttribute('data-stage-id', stageId);
							var urlStage = '0';
							try {
								urlStage = new URL(window.location.href).searchParams.get('stage_id') || '0';
							} catch (err) {
								urlStage = '0';
							}
							if (
								(urlStage === '0' || urlStage === '') &&
								stage.slug === 'archive' &&
								root.getAttribute('data-layout') === 'table'
							) {
								row.remove();
							}
						}
						// Refresh activity after stage change.
						post('art_forms_crm_get_card', { id: currentId }).then(function (cardRes) {
							if (cardRes && cardRes.success && cardRes.data) {
								renderActivity(cardRes.data.activity || []);
							}
						});
					}
				}
			);
		});
		document.getElementById('art-forms-crm-comment-add').addEventListener('click', function () {
			var ta = document.getElementById('art-forms-crm-comment-body');
			var body = (ta.value || '').trim();
			if (!body) {
				alert(str('commentEmpty'));
				return;
			}
			post('art_forms_crm_add_comment', { id: currentId, body: body }).then(function (res) {
				if (res && res.success) {
					ta.value = '';
					var box = document.getElementById('art-forms-crm-modal-comments');
					var empty = box.querySelector('.art-forms-crm-muted');
					if (empty) {
						box.innerHTML = '';
					}
					var c = res.data.comment;
					var html =
						'<div class="art-forms-crm-comment" data-id="' +
						esc(String(c.id)) +
						'"><div class="art-forms-crm-comment-head"><strong>' +
						esc(c.author_name) +
						'</strong> <span>' +
						esc(c.created_at) +
						'</span> <button type="button" class="button-link-delete art-forms-crm-comment-del" data-id="' +
						esc(String(c.id)) +
						'">' +
						esc(str('delete')) +
						'</button></div><div class="art-forms-crm-comment-body">' +
						esc(c.body) +
						'</div></div>';
					box.insertAdjacentHTML('beforeend', html);
				} else {
					alert((res && res.data && res.data.message) || str('error'));
				}
			});
		});
		document
			.getElementById('art-forms-crm-modal-comments')
			.addEventListener('click', function (e) {
				var delBtn = e.target.closest('.art-forms-crm-comment-del');
				if (!delBtn) {
					return;
				}
				var cid = delBtn.getAttribute('data-id');
				post('art_forms_crm_delete_comment', { comment_id: cid }).then(function (res) {
					if (res && res.success) {
						var el = document.querySelector(
							'.art-forms-crm-comment[data-id="' + cid + '"]'
						);
						if (el) {
							el.remove();
						}
					}
				});
			});
	}

	document.addEventListener('keydown', function (e) {
		if (!modal || modal.hidden) {
			return;
		}
		if (e.key === 'Escape') {
			closeModal();
		} else if (e.key === 'ArrowLeft') {
			navigate(-1);
		} else if (e.key === 'ArrowRight') {
			navigate(1);
		}
	});

	// Row click (except controls)
	root.querySelectorAll('.art-forms-crm-row').forEach(function (row) {
		row.addEventListener('click', function (e) {
			if (
				e.target.closest('input, button, a, .art-forms-crm-star, .art-forms-crm-col-check')
			) {
				return;
			}
			openCard(row.getAttribute('data-id'));
		});
	});

	var checkAll = document.getElementById('art-forms-crm-check-all');
	if (checkAll) {
		checkAll.addEventListener('change', function () {
			root.querySelectorAll('.art-forms-crm-row-check').forEach(function (cb) {
				cb.checked = checkAll.checked;
			});
			updateBulkBar();
		});
	}
	root.querySelectorAll('.art-forms-crm-row-check').forEach(function (cb) {
		cb.addEventListener('change', updateBulkBar);
	});

	var bulkAction = document.getElementById('art-forms-crm-bulk-action');
	var bulkStage = document.getElementById('art-forms-crm-bulk-stage');
	if (bulkAction && bulkStage) {
		bulkAction.addEventListener('change', function () {
			bulkStage.hidden = bulkAction.value !== 'stage';
		});
	}
	var bulkApply = document.getElementById('art-forms-crm-bulk-apply');
	if (bulkApply) {
		bulkApply.addEventListener('click', function () {
			var ids = selectedIds();
			if (!ids.length) {
				alert(str('noSelection'));
				return;
			}
			var action = bulkAction.value;
			if (!action) {
				return;
			}
			if (action === 'delete' && !window.confirm(str('confirmDelete'))) {
				return;
			}
			var payload = { bulk_action: action, ids: ids };
			if (action === 'stage') {
				payload.stage_id = bulkStage.value;
			}
			post('art_forms_crm_bulk', payload).then(function (res) {
				if (res && res.success) {
					window.location.reload();
				} else {
					alert((res && res.data && res.data.message) || str('error'));
				}
			});
		});
	}

	// Columns popover
	var colBtn = document.getElementById('art-forms-crm-columns-btn');
	var colPop = document.getElementById('art-forms-crm-columns-popover');
	function placeColumnsPopover() {
		if (!colBtn || !colPop) {
			return;
		}
		var rect = colBtn.getBoundingClientRect();
		var gap = 6;
		var maxH = Math.min(360, window.innerHeight - 24);
		colPop.style.maxHeight = maxH + 'px';
		colPop.style.left = '0px';
		colPop.style.top = '0px';
		colPop.style.visibility = 'hidden';
		colPop.hidden = false;
		var popW = colPop.offsetWidth || 240;
		var popH = Math.min(colPop.scrollHeight, maxH);
		var left = rect.left;
		var top = rect.bottom + gap;
		if (left + popW > window.innerWidth - 12) {
			left = Math.max(12, window.innerWidth - popW - 12);
		}
		if (left < 12) {
			left = 12;
		}
		if (top + popH > window.innerHeight - 12) {
			top = Math.max(12, rect.top - gap - popH);
		}
		colPop.style.left = left + 'px';
		colPop.style.top = top + 'px';
		colPop.style.visibility = '';
	}
	if (colBtn && colPop) {
		colPop.hidden = true;
		colBtn.addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();
			if (colPop.hidden) {
				placeColumnsPopover();
			} else {
				colPop.hidden = true;
			}
		});
		document.addEventListener('click', function (e) {
			if (colPop.hidden) {
				return;
			}
			if (e.target.closest('#art-forms-crm-columns-popover, #art-forms-crm-columns-btn')) {
				return;
			}
			colPop.hidden = true;
		});
		window.addEventListener('resize', function () {
			if (!colPop.hidden) {
				placeColumnsPopover();
			}
		});
		window.addEventListener(
			'scroll',
			function () {
				if (!colPop.hidden) {
					placeColumnsPopover();
				}
			},
			true
		);
		colPop.querySelectorAll('.art-forms-crm-col-toggle').forEach(function (cb) {
			cb.addEventListener('change', function () {
				var key = cb.value;
				var show = cb.checked;
				root.querySelectorAll('#art-forms-crm-table [data-col="' + key + '"]').forEach(
					function (cell) {
						cell.hidden = !show;
					}
				);
				var hiddenCols = [];
				colPop.querySelectorAll('.art-forms-crm-col-toggle').forEach(function (c) {
					if (!c.checked) {
						hiddenCols.push(c.value);
					}
				});
				savePrefs({ hidden_columns: JSON.stringify(hiddenCols) });
				var crmTable = document.getElementById('art-forms-crm-table');
				if (crmTable && typeof crmTable._artFormsSyncWidth === 'function') {
					crmTable._artFormsSyncWidth();
				}
			});
		});

		function collectColumnLabels() {
			var labels = {};
			colPop.querySelectorAll('.art-forms-crm-col-alias').forEach(function (input) {
				var key = input.getAttribute('data-col') || '';
				var original = input.getAttribute('data-original') || '';
				var value = (input.value || '').trim();
				if (!key) {
					return;
				}
				if (value && value !== original) {
					labels[key] = value;
				}
			});
			return labels;
		}

		function applyColumnLabel(key, displayLabel, original) {
			var th = root.querySelector('thead th[data-col="' + key + '"]');
			if (!th) {
				return;
			}
			var labelEl = th.querySelector('.art-forms-crm-sort-label');
			if (labelEl) {
				labelEl.textContent = displayLabel;
			}
			th.setAttribute('title', original || displayLabel);
		}

		function saveColumnLabels() {
			var labels = collectColumnLabels();
			colPop.querySelectorAll('.art-forms-crm-col-alias').forEach(function (input) {
				var key = input.getAttribute('data-col') || '';
				var original = input.getAttribute('data-original') || '';
				var value = (input.value || '').trim();
				var display = value || original;
				if (!value) {
					input.value = original;
					display = original;
				}
				applyColumnLabel(key, display, original);
			});
			savePrefs({ column_labels: JSON.stringify(labels) });
		}

		colPop.querySelectorAll('.art-forms-crm-col-alias').forEach(function (input) {
			input.addEventListener('change', saveColumnLabels);
			input.addEventListener('keydown', function (e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					input.blur();
				}
			});
		});
	}

	// Layout preference
	root.querySelectorAll('[data-crm-layout]').forEach(function (a) {
		a.addEventListener('click', function () {
			savePrefs({ layout: a.getAttribute('data-crm-layout') });
		});
	});

	// Stages editor
	var addStageBtn = document.getElementById('art-forms-crm-add-stage');
	if (addStageBtn) {
		addStageBtn.addEventListener('click', function () {
			var title = document.getElementById('art-forms-crm-new-stage-title').value.trim();
			var color = document.getElementById('art-forms-crm-new-stage-color').value;
			if (!title) {
				alert(str('stageTitle'));
				return;
			}
			post('art_forms_crm_stage_save', {
				form_id: formId,
				stage_id: 0,
				title: title,
				color: color
			}).then(function (res) {
				if (res && res.success) {
					window.location.reload();
				} else {
					alert((res && res.data && res.data.message) || str('error'));
				}
			});
		});

		document.querySelectorAll('#art-forms-crm-stages-editor li').forEach(function (li) {
			var saveBtn = li.querySelector('.art-forms-crm-stage-save');
			var delBtn = li.querySelector('.art-forms-crm-stage-delete');
			if (saveBtn) {
				saveBtn.addEventListener('click', function () {
					post('art_forms_crm_stage_save', {
						form_id: formId,
						stage_id: li.getAttribute('data-stage-id'),
						title: li.querySelector('.art-forms-crm-stage-title').value,
						color: li.querySelector('.art-forms-crm-stage-color').value
					}).then(function (res) {
						if (!(res && res.success)) {
							alert((res && res.data && res.data.message) || str('error'));
						}
					});
				});
			}
			if (delBtn) {
				delBtn.addEventListener('click', function () {
					if (!window.confirm(str('confirmStageDel'))) {
						return;
					}
					post('art_forms_crm_stage_delete', {
						stage_id: li.getAttribute('data-stage-id')
					}).then(function (res) {
						if (res && res.success) {
							window.location.reload();
						} else {
							alert((res && res.data && res.data.message) || str('error'));
						}
					});
				});
			}
		});

		// Simple drag reorder for stages
		var editor = document.getElementById('art-forms-crm-stages-editor');
		var dragEl = null;
		if (editor) {
			editor.querySelectorAll('li').forEach(function (li) {
				li.setAttribute('draggable', 'true');
				li.addEventListener('dragstart', function () {
					dragEl = li;
					li.classList.add('is-dragging');
				});
				li.addEventListener('dragend', function () {
					li.classList.remove('is-dragging');
					dragEl = null;
					var ids = Array.prototype.map.call(editor.querySelectorAll('li'), function (el) {
						return el.getAttribute('data-stage-id');
					});
					post('art_forms_crm_stage_reorder', { form_id: formId, ids: ids });
				});
				li.addEventListener('dragover', function (e) {
					e.preventDefault();
					if (!dragEl || dragEl === li) {
						return;
					}
					var rect = li.getBoundingClientRect();
					var before = e.clientY < rect.top + rect.height / 2;
					editor.insertBefore(dragEl, before ? li : li.nextSibling);
				});
			});
		}
	}

	// Kanban DnD (между колонками и внутри колонки).
	var board = document.getElementById('art-forms-crm-board');
	if (board) {
		var dragCard = null;

		function persistBoardColumn(col) {
			var stageId = col.getAttribute('data-stage-id');
			if (!stageId) {
				return;
			}
			var ids = Array.prototype.map.call(col.querySelectorAll('.art-forms-crm-board-card'), function (el) {
				return el.getAttribute('data-id');
			});
			post('art_forms_crm_board_reorder', {
				form_id: formId,
				stage_id: stageId,
				ids: ids
			});
		}

		board.querySelectorAll('.art-forms-crm-board-card').forEach(function (card) {
			card.addEventListener('dragstart', function (e) {
				dragCard = card;
				card.classList.add('is-dragging');
				e.dataTransfer.effectAllowed = 'move';
				e.dataTransfer.setData('text/plain', card.getAttribute('data-id'));
			});
			card.addEventListener('dragend', function () {
				card.classList.remove('is-dragging');
				dragCard = null;
				board.querySelectorAll('.art-forms-crm-board-cards.is-drop-target').forEach(function (c) {
					c.classList.remove('is-drop-target');
				});
			});
		});

		board.querySelectorAll('.art-forms-crm-board-cards').forEach(function (col) {
			col.addEventListener('dragover', function (e) {
				e.preventDefault();
				col.classList.add('is-drop-target');
				if (!dragCard) {
					return;
				}
				var cards = col.querySelectorAll('.art-forms-crm-board-card:not(.is-dragging)');
				var insertBeforeEl = null;
				for (var i = 0; i < cards.length; i++) {
					var rect = cards[i].getBoundingClientRect();
					if (e.clientY < rect.top + rect.height / 2) {
						insertBeforeEl = cards[i];
						break;
					}
				}
				if (insertBeforeEl) {
					col.insertBefore(dragCard, insertBeforeEl);
				} else {
					col.appendChild(dragCard);
				}
			});
			col.addEventListener('dragleave', function (e) {
				if (!col.contains(e.relatedTarget)) {
					col.classList.remove('is-drop-target');
				}
			});
			col.addEventListener('drop', function (e) {
				e.preventDefault();
				col.classList.remove('is-drop-target');
				var id = e.dataTransfer.getData('text/plain');
				var stageId = col.getAttribute('data-stage-id');
				var card =
					dragCard ||
					board.querySelector('.art-forms-crm-board-card[data-id="' + id + '"]');
				if (!card || !stageId) {
					return;
				}
				card.setAttribute('data-stage-id', stageId);
				persistBoardColumn(col);
			});
		});
	}

	// Open deep-linked card
	var initialView = parseInt(root.getAttribute('data-view-id') || '0', 10);
	if (initialView > 0) {
		openCard(initialView);
	}

	// Column resize + overflow tooltips
	var table = document.getElementById('art-forms-crm-table');
	if (table) {
		var widths = {};
		try {
			widths = JSON.parse(table.getAttribute('data-column-widths') || '{}') || {};
		} catch (err) {
			widths = {};
		}

		function applyColWidth(col, px) {
			px = Math.max(28, Math.min(600, Math.round(px)));
			table.style.setProperty('--crm-col-' + col, px + 'px');
			var th = table.querySelector('thead th[data-col="' + col + '"]');
			if (th) {
				th.style.width = px + 'px';
				th.style.minWidth = px + 'px';
				th.style.maxWidth = px + 'px';
			}
			// Clear leftover inline sizes on body cells from older builds.
			table.querySelectorAll('tbody td[data-col="' + col + '"]').forEach(function (td) {
				td.style.width = '';
				td.style.minWidth = '';
				td.style.maxWidth = '';
			});
			widths[col] = px;
			syncTableWidth();
		}

		function readColWidth(th) {
			var col = th.getAttribute('data-col') || '';
			if (col && widths[col]) {
				return widths[col];
			}
			var inline = parseInt(th.style.width, 10);
			if (inline > 0) {
				return inline;
			}
			var cssVar = col
				? parseInt(
						window.getComputedStyle(table).getPropertyValue('--crm-col-' + col),
						10
				  )
				: 0;
			if (cssVar > 0) {
				return cssVar;
			}
			return Math.max(28, Math.round(th.getBoundingClientRect().width) || 160);
		}

		function syncTableWidth() {
			var total = 0;
			table.querySelectorAll('thead th').forEach(function (th) {
				if (th.hidden || th.getAttribute('hidden') !== null) {
					return;
				}
				total += readColWidth(th);
			});
			if (total > 0) {
				table.style.width = total + 'px';
				table.style.minWidth = total + 'px';
				table.style.maxWidth = total + 'px';
			}
		}

		// Lock each header to its CSS/pref width so free space is not redistributed.
		table.querySelectorAll('thead th[data-col]').forEach(function (th) {
			var col = th.getAttribute('data-col');
			var px = readColWidth(th);
			widths[col] = px;
			th.style.width = px + 'px';
			th.style.minWidth = px + 'px';
			th.style.maxWidth = px + 'px';
			table.style.setProperty('--crm-col-' + col, px + 'px');
		});
		syncTableWidth();
		table._artFormsSyncWidth = syncTableWidth;

		var saveTimer = null;
		function persistWidths() {
			window.clearTimeout(saveTimer);
			saveTimer = window.setTimeout(function () {
				savePrefs({ column_widths: JSON.stringify(widths) });
			}, 400);
		}

		table.querySelectorAll('.art-forms-crm-col-resizer').forEach(function (handle) {
			handle.addEventListener('mousedown', function (e) {
				e.preventDefault();
				e.stopPropagation();
				var col = handle.getAttribute('data-col');
				var th = handle.closest('th');
				if (!col || !th) {
					return;
				}

				// Disable HTML5 column drag while resizing.
				var dragables = table.querySelectorAll('th.art-forms-crm-th-draggable');
				dragables.forEach(function (el) {
					el.setAttribute('draggable', 'false');
				});

				var startX = e.clientX;
				var startW = th.getBoundingClientRect().width;
				handle.classList.add('is-active');
				document.body.classList.add('art-forms-crm-resizing');

				function onMove(ev) {
					ev.preventDefault();
					applyColWidth(col, startW + (ev.clientX - startX));
				}
				function onUp() {
					handle.classList.remove('is-active');
					document.body.classList.remove('art-forms-crm-resizing');
					document.removeEventListener('mousemove', onMove);
					document.removeEventListener('mouseup', onUp);
					dragables.forEach(function (el) {
						el.setAttribute('draggable', 'true');
					});
					persistWidths();
				}
				document.addEventListener('mousemove', onMove);
				document.addEventListener('mouseup', onUp);
			});

			handle.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
			});
			handle.addEventListener('dragstart', function (e) {
				e.preventDefault();
				e.stopPropagation();
			});
		});

		// Column reorder (drag header; check stays first).
		var dragTh = null;
		var didColDrag = false;
		table.querySelectorAll('th.art-forms-crm-th-draggable').forEach(function (th) {
			th.addEventListener('dragstart', function (e) {
				if (e.target.closest('.art-forms-crm-col-resizer')) {
					e.preventDefault();
					return;
				}
				dragTh = th;
				didColDrag = false;
				th.classList.add('is-col-dragging');
				e.dataTransfer.effectAllowed = 'move';
				e.dataTransfer.setData('text/plain', th.getAttribute('data-col') || '');
			});
			th.addEventListener('dragend', function () {
				th.classList.remove('is-col-dragging');
				table.querySelectorAll('th.is-col-drop').forEach(function (el) {
					el.classList.remove('is-col-drop');
				});
				if (didColDrag) {
					syncBodyToHeaderOrder();
					persistOrder();
				}
				dragTh = null;
			});
			th.addEventListener('dragover', function (e) {
				e.preventDefault();
				if (!dragTh || dragTh === th) {
					return;
				}
				th.classList.add('is-col-drop');
				var rect = th.getBoundingClientRect();
				var before = e.clientX < rect.left + rect.width / 2;
				var headerRow = th.parentNode;
				headerRow.insertBefore(dragTh, before ? th : th.nextSibling);
				didColDrag = true;
			});
			th.addEventListener('dragleave', function () {
				th.classList.remove('is-col-drop');
			});
			// Avoid accidental navigation while dragging.
			th.querySelectorAll('.art-forms-crm-sort-link').forEach(function (link) {
				link.addEventListener('click', function (e) {
					if (didColDrag) {
						e.preventDefault();
						didColDrag = false;
					}
				});
			});
		});

		function currentOrder() {
			return Array.prototype.map
				.call(table.querySelectorAll('thead th[data-col]'), function (th) {
					return th.getAttribute('data-col');
				})
				.filter(function (col) {
					return col && col !== 'check';
				});
		}

		function syncBodyToHeaderOrder() {
			var order = currentOrder();
			table.querySelectorAll('tbody tr').forEach(function (row) {
				var check = row.querySelector('td[data-col="check"]');
				var map = {};
				Array.prototype.forEach.call(row.querySelectorAll('td[data-col]'), function (td) {
					map[td.getAttribute('data-col')] = td;
				});
				order.forEach(function (col) {
					if (map[col]) {
						row.appendChild(map[col]);
					}
				});
				if (check) {
					row.insertBefore(check, row.firstChild);
				}
			});
			table.setAttribute('data-column-order', JSON.stringify(order));
		}

		function persistOrder() {
			savePrefs({ column_order: JSON.stringify(currentOrder()) });
		}

		var tip = document.createElement('div');
		tip.className = 'art-forms-crm-tooltip';
		tip.hidden = true;
		document.body.appendChild(tip);

		function hideTip() {
			tip.hidden = true;
			tip.textContent = '';
		}

		function showTip(text, x, y) {
			tip.textContent = text;
			tip.hidden = false;
			var pad = 12;
			var rect = tip.getBoundingClientRect();
			var left = x + pad;
			var top = y + pad;
			if (left + rect.width > window.innerWidth - 8) {
				left = x - rect.width - pad;
			}
			if (top + rect.height > window.innerHeight - 8) {
				top = y - rect.height - pad;
			}
			tip.style.left = Math.max(8, left) + 'px';
			tip.style.top = Math.max(8, top) + 'px';
		}

		table.addEventListener('mouseover', function (e) {
			var cell = e.target.closest('td.art-forms-crm-cell');
			if (!cell || !table.contains(cell)) {
				return;
			}
			var textEl = cell.querySelector('.art-forms-crm-cell-text') || cell;
			var full = cell.getAttribute('data-full') || textEl.textContent || '';
			full = String(full).trim();
			if (!full) {
				hideTip();
				return;
			}
			var overflow =
				textEl.scrollWidth > textEl.clientWidth + 1 ||
				cell.scrollWidth > cell.clientWidth + 1;
			if (!overflow) {
				hideTip();
				return;
			}
			showTip(full, e.clientX, e.clientY);
		});

		table.addEventListener('mousemove', function (e) {
			if (tip.hidden) {
				return;
			}
			var cell = e.target.closest('td.art-forms-crm-cell');
			if (!cell) {
				hideTip();
				return;
			}
			showTip(tip.textContent, e.clientX, e.clientY);
		});

		table.addEventListener('mouseout', function (e) {
			var related = e.relatedTarget;
			if (related && e.currentTarget.contains(related)) {
				var stillCell = related.closest && related.closest('td.art-forms-crm-cell');
				if (stillCell) {
					return;
				}
			}
			hideTip();
		});
	}

	// Client-side live filter of currently loaded rows/cards (no server load).
	var liveSearch = document.getElementById('art-forms-crm-live-search');
	if (liveSearch) {
		var liveTimer = null;
		function applyLiveFilter() {
			var q = (liveSearch.value || '').trim().toLowerCase();
			root.querySelectorAll('.art-forms-crm-row').forEach(function (row) {
				if (!q) {
					row.hidden = false;
					return;
				}
				var hay = row.textContent || '';
				row.querySelectorAll('[data-full]').forEach(function (el) {
					hay += ' ' + (el.getAttribute('data-full') || '');
				});
				row.hidden = hay.toLowerCase().indexOf(q) === -1;
			});
			root.querySelectorAll('.art-forms-crm-board-card').forEach(function (card) {
				if (!q) {
					card.hidden = false;
					return;
				}
				var hay = card.textContent || '';
				hay += ' ' + (card.getAttribute('data-full') || '');
				card.hidden = hay.toLowerCase().indexOf(q) === -1;
			});
		}
		liveSearch.addEventListener('input', function () {
			window.clearTimeout(liveTimer);
			liveTimer = window.setTimeout(applyLiveFilter, 150);
		});
	}
})();
