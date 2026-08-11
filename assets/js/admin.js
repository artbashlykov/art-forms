/**
 * ART Forms admin UI: schema editor, copy, code checker.
 */
(function () {
	'use strict';

	var dirty = false;
	var allowLeave = false;

	function str(key, fallback) {
		if (
			window.artFormsAdmin &&
			artFormsAdmin.strings &&
			artFormsAdmin.strings[key]
		) {
			return artFormsAdmin.strings[key];
		}
		return fallback;
	}

	function markDirty() {
		dirty = true;
	}

	function clearDirty() {
		dirty = false;
	}

	function parseJson(id, fallback) {
		var el = document.getElementById(id);
		if (!el) {
			return fallback;
		}
		try {
			return JSON.parse(el.textContent || '{}');
		} catch (e) {
			return fallback;
		}
	}

	/**
	 * Transliterate Russian (and common chars) to Latin slug with underscores.
	 * Kept for rare manual key edits after unlock.
	 */
	function transliterateToKey(text) {
		var map = {
			а: 'a',
			б: 'b',
			в: 'v',
			г: 'g',
			д: 'd',
			е: 'e',
			ё: 'e',
			ж: 'zh',
			з: 'z',
			и: 'i',
			й: 'y',
			к: 'k',
			л: 'l',
			м: 'm',
			н: 'n',
			о: 'o',
			п: 'p',
			р: 'r',
			с: 's',
			т: 't',
			у: 'u',
			ф: 'f',
			х: 'h',
			ц: 'ts',
			ч: 'ch',
			ш: 'sh',
			щ: 'sch',
			ъ: '',
			ы: 'y',
			ь: '',
			э: 'e',
			ю: 'yu',
			я: 'ya'
		};

		var input = String(text || '').toLowerCase();
		var out = '';
		for (var i = 0; i < input.length; i++) {
			var ch = input.charAt(i);
			if (Object.prototype.hasOwnProperty.call(map, ch)) {
				out += map[ch];
			} else if (/[a-z0-9]/.test(ch)) {
				out += ch;
			} else if (/\s/.test(ch) || ch === '-' || ch === '_') {
				out += '_';
			}
		}

		out = out.replace(/_+/g, '_').replace(/^_+|_+$/g, '');
		if (out.length > 40) {
			out = out.substring(0, 40).replace(/_+$/g, '');
		}
		return out || 'f1';
	}

	function collectUsedKeys(schema) {
		var used = {};
		(schema.steps || []).forEach(function (step) {
			(step.fields || []).forEach(function (field) {
				if (field && field.key) {
					used[field.key] = true;
				}
			});
		});
		return used;
	}

	function nextAutoKey(schema) {
		var used = collectUsedKeys(schema);
		var n = 1;
		while (used['f' + n]) {
			n += 1;
		}
		return 'f' + n;
	}

	function ensureFieldKeys(schema) {
		(schema.steps || []).forEach(function (step) {
			(step.fields || []).forEach(function (field) {
				if (!field.key) {
					field.key = nextAutoKey(schema);
				}
			});
		});
	}

	function typeLabel(type) {
		var labels = {
			text: str('typeText', 'Текст'),
			email: str('typeEmail', 'Email'),
			tel: str('typeTel', 'Телефон'),
			textarea: str('typeTextarea', 'Многострочный текст'),
			select: str('typeSelect', 'Выпадающий список'),
			radio: str('typeRadio', 'Варианты (один)'),
			checkbox: str('typeCheckbox', 'Варианты (несколько)'),
			hidden: str('typeHidden', 'Скрытое поле'),
			consent: str('typeConsent', 'Согласие на ПДн')
		};
		return labels[type] || type;
	}

	function typeHint(type) {
		var hints = {
			text: str('hintText', 'Одна строка текста.'),
			email: str('hintEmail', 'Проверка формата email.'),
			tel: str('hintTel', 'Поле для телефона.'),
			textarea: str('hintTextarea', 'Длинный текст в несколько строк.'),
			select: str(
				'hintSelect',
				'Меню на странице: клиент выбирает один пункт.'
			),
			radio: str(
				'hintRadio',
				'Варианты на экране: можно выбрать только один.'
			),
			checkbox: str(
				'hintCheckbox',
				'Несколько галочек: можно отметить сразу несколько.'
			),
			hidden: str(
				'hintHidden',
				'Не видно клиенту. Для оффера, тарифа и других служебных данных.'
			),
			consent: str(
				'hintConsent',
				'Обязательная галочка согласия. Можно добавить ссылку на политику.'
			)
		};
		return hints[type] || '';
	}

	function syncSchema(schema) {
		var input = document.getElementById('art-forms-schema-json');
		if (input) {
			input.value = JSON.stringify(schema);
		}
	}

	var editorUi = {
		activeStep: 0,
		expandedFieldKey: null
	};

	function clampEditorUi(schema) {
		if (!schema.steps || !schema.steps.length) {
			editorUi.activeStep = 0;
			editorUi.expandedFieldKey = null;
			return;
		}

		if (editorUi.activeStep < 0) {
			editorUi.activeStep = 0;
		}
		if (editorUi.activeStep >= schema.steps.length) {
			editorUi.activeStep = schema.steps.length - 1;
		}

		if (!editorUi.expandedFieldKey) {
			return;
		}

		var foundStep = -1;
		schema.steps.forEach(function (step, stepIndex) {
			(step.fields || []).forEach(function (field) {
				if (field && field.key === editorUi.expandedFieldKey) {
					foundStep = stepIndex;
				}
			});
		});

		if (foundStep < 0) {
			editorUi.expandedFieldKey = null;
			return;
		}

		editorUi.activeStep = foundStep;
	}

	function focusStep(schema, root, fieldTypes, stepIndex, scroll) {
		editorUi.activeStep = stepIndex;
		editorUi.expandedFieldKey = null;
		renderEditor(root, schema, fieldTypes);
		if (scroll) {
			window.requestAnimationFrame(function () {
				scrollToElement(stepAnchorId(editorUi.activeStep));
			});
		}
	}

	function focusField(schema, root, fieldTypes, stepIndex, fieldKey, scroll) {
		editorUi.activeStep = stepIndex;
		editorUi.expandedFieldKey = fieldKey || null;
		renderEditor(root, schema, fieldTypes);
		if (!scroll || !fieldKey) {
			return;
		}
		window.requestAnimationFrame(function () {
			var fields = schema.steps[editorUi.activeStep]
				? schema.steps[editorUi.activeStep].fields || []
				: [];
			var fieldIndex = -1;
			fields.forEach(function (field, index) {
				if (field.key === fieldKey) {
					fieldIndex = index;
				}
			});
			if (fieldIndex >= 0) {
				scrollToElement(fieldAnchorId(editorUi.activeStep, fieldIndex));
			}
		});
	}

	function stepAnchorId(stepIndex) {
		return 'art-forms-step-' + stepIndex;
	}

	function fieldAnchorId(stepIndex, fieldIndex) {
		return 'art-forms-field-' + stepIndex + '-' + fieldIndex;
	}

	function scrollToElement(id) {
		var el = document.getElementById(id);
		if (!el) {
			return;
		}
		el.scrollIntoView({ behavior: 'smooth', block: 'center' });
		el.classList.remove('is-flash');
		// Force reflow so animation can restart.
		void el.offsetWidth;
		el.classList.add('is-flash');
		window.setTimeout(function () {
			el.classList.remove('is-flash');
		}, 1200);
	}

	function moveField(schema, fromStep, fromField, toStep, toField) {
		if (
			!schema.steps[fromStep] ||
			!schema.steps[toStep] ||
			fromField < 0 ||
			toField < 0
		) {
			return false;
		}
		var source = schema.steps[fromStep].fields || [];
		if (fromField >= source.length) {
			return false;
		}
		if (fromStep === toStep && fromField === toField) {
			return false;
		}

		var field = source.splice(fromField, 1)[0];
		schema.steps[toStep].fields = schema.steps[toStep].fields || [];
		var target = schema.steps[toStep].fields;
		var insertAt = toField;
		if (fromStep === toStep && fromField < toField) {
			insertAt -= 1;
		}
		if (insertAt < 0) {
			insertAt = 0;
		}
		if (insertAt > target.length) {
			insertAt = target.length;
		}
		target.splice(insertAt, 0, field);
		return true;
	}

	/**
	 * New unique key for a duplicated field: sourceKey + digit (f1 → f11, f12…).
	 */
	function allocDuplicateKey(used, sourceKey) {
		var base = String(sourceKey || 'f').replace(/_+$/g, '') || 'f';
		var n = 1;
		var candidate = base + String(n);
		while (used[candidate]) {
			n += 1;
			candidate = base + String(n);
		}
		used[candidate] = true;
		return candidate;
	}

	function nextDuplicateKey(schema, sourceKey) {
		return allocDuplicateKey(collectUsedKeys(schema), sourceKey);
	}

	function cloneFieldForDuplicate(field, newKey) {
		var copy = JSON.parse(JSON.stringify(field || {}));
		copy.key = newKey;
		copy.locked = false;
		delete copy.unlock_key;
		return copy;
	}

	function moveStep(schema, fromIndex, toIndex) {
		if (fromIndex === toIndex || fromIndex < 0 || toIndex < 0) {
			return false;
		}
		if (fromIndex >= schema.steps.length || toIndex > schema.steps.length) {
			return false;
		}
		var item = schema.steps.splice(fromIndex, 1)[0];
		if (fromIndex < toIndex) {
			toIndex -= 1;
		}
		schema.steps.splice(toIndex, 0, item);
		return true;
	}

	function duplicateStep(schema, stepIndex) {
		var source = schema.steps[stepIndex];
		if (!source) {
			return;
		}
		var used = collectUsedKeys(schema);
		var copy = JSON.parse(JSON.stringify(source));
		copy.id = 'step_' + Date.now();
		var baseTitle = source.title || str('stepBadge', 'Блок');
		copy.title =
			baseTitle + ' (' + str('copySuffix', 'копия') + ')';
		copy.fields = (copy.fields || []).map(function (field) {
			return cloneFieldForDuplicate(
				field,
				allocDuplicateKey(used, field.key)
			);
		});
		schema.steps.splice(stepIndex + 1, 0, copy);
		editorUi.activeStep = stepIndex + 1;
		editorUi.expandedFieldKey = null;
	}

	function countSchema(schema) {
		var steps = schema.steps || [];
		var fields = 0;
		steps.forEach(function (step) {
			fields += (step.fields || []).length;
		});
		return { steps: steps.length, fields: fields };
	}

	function pluralRu(n, one, few, many) {
		var mod10 = n % 10;
		var mod100 = n % 100;
		if (mod10 === 1 && mod100 !== 11) {
			return one;
		}
		if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) {
			return few;
		}
		return many;
	}

	function updateSchemaStats(schema) {
		var el = document.getElementById('art-forms-schema-stats');
		if (!el) {
			return;
		}
		var counts = countSchema(schema);
		var fieldsWord = pluralRu(
			counts.fields,
			str('statsFieldOne', 'поле'),
			str('statsFieldFew', 'поля'),
			str('statsFieldMany', 'полей')
		);
		var blocksWord = pluralRu(
			counts.steps,
			str('statsBlockOne', 'блок'),
			str('statsBlockFew', 'блока'),
			str('statsBlockMany', 'блоков')
		);
		el.textContent = counts.fields + ' ' + fieldsWord + ' · ' + counts.steps + ' ' + blocksWord;
	}

	function schemaContactWarnings(schema) {
		var hasEmail = false;
		var hasTel = false;
		var hasConsent = false;
		(schema.steps || []).forEach(function (step) {
			(step.fields || []).forEach(function (field) {
				if (!field || !field.type) {
					return;
				}
				if (field.type === 'email') {
					hasEmail = true;
				}
				if (field.type === 'tel') {
					hasTel = true;
				}
				if (field.type === 'consent') {
					hasConsent = true;
				}
			});
		});
		var warnings = [];
		if (!hasEmail) {
			warnings.push(str('warnNoEmail', 'нет поля Email'));
		}
		if (!hasTel) {
			warnings.push(str('warnNoTel', 'нет поля Телефон'));
		}
		if (!hasConsent) {
			warnings.push(str('warnNoConsent', 'нет согласия на ПДн'));
		}
		return warnings;
	}

	var fieldDrag = null;
	var stepDrag = null;
	var stepDragMoved = false;

	function clearFieldDropMarks() {
		document.querySelectorAll(".art-forms-field-card.art-forms-drop-before").forEach(function (el) {
			el.classList.remove("art-forms-drop-before");
		});
	}

	function bindFieldDrag(handle, schema, root, fieldTypes, stepIndex, fieldIndex) {
		handle.draggable = true;
		handle.title = str("fieldDrag", "Перетащить");
		handle.addEventListener("dragstart", function (event) {
			fieldDrag = { stepIndex: stepIndex, fieldIndex: fieldIndex };
			handle.classList.add("is-dragging");
			var card = handle.closest(".art-forms-field-card");
			if (card) {
				card.classList.add("is-dragging");
			}
			if (event.dataTransfer) {
				event.dataTransfer.effectAllowed = "move";
				event.dataTransfer.setData("text/plain", "field");
			}
			event.stopPropagation();
		});
		handle.addEventListener("dragend", function () {
			fieldDrag = null;
			handle.classList.remove("is-dragging");
			document.querySelectorAll(".art-forms-field-card.is-dragging").forEach(function (el) {
				el.classList.remove("is-dragging");
			});
			clearFieldDropMarks();
		});
	}

	function bindFieldDropTarget(card, schema, root, fieldTypes, stepIndex, fieldIndex) {
		card.addEventListener("dragover", function (event) {
			if (!fieldDrag || fieldDrag.stepIndex !== stepIndex) {
				return;
			}
			event.preventDefault();
			clearFieldDropMarks();
			card.classList.add("art-forms-drop-before");
		});
		card.addEventListener("dragleave", function () {
			card.classList.remove("art-forms-drop-before");
		});
		card.addEventListener("drop", function (event) {
			event.preventDefault();
			event.stopPropagation();
			if (!fieldDrag || fieldDrag.stepIndex !== stepIndex) {
				clearFieldDropMarks();
				return;
			}
			var from = fieldDrag.fieldIndex;
			var to = fieldIndex;
			fieldDrag = null;
			clearFieldDropMarks();
			if (moveField(schema, stepIndex, from, stepIndex, to)) {
				renderEditor(root, schema, fieldTypes);
				syncSchema(schema);
				markDirty();
			}
		});
	}

	function control(labelText, inputEl) {

		var wrap = document.createElement('div');
		wrap.className = 'art-forms-control';
		var lab = document.createElement('span');
		lab.className = 'art-forms-control-label';
		lab.textContent = labelText;
		wrap.appendChild(lab);
		wrap.appendChild(inputEl);
		return wrap;
	}

	function appendHint(wrap, text) {
		if (!text) {
			return;
		}
		var hint = document.createElement('p');
		hint.className = 'art-forms-type-hint';
		hint.textContent = text;
		wrap.appendChild(hint);
	}

	function renderEditor(root, schema, fieldTypes) {
		ensureFieldKeys(schema);
		clampEditorUi(schema);
		root.innerHTML = '';

		var tabs = document.createElement('div');
		tabs.className = 'art-forms-step-tabs';
		tabs.setAttribute('role', 'tablist');

		schema.steps.forEach(function (step, stepIndex) {
			var tab = document.createElement('button');
			tab.type = 'button';
			tab.className = 'art-forms-step-tab';
			tab.setAttribute('role', 'tab');
			tab.setAttribute(
				'aria-selected',
				stepIndex === editorUi.activeStep ? 'true' : 'false'
			);
			tab.draggable = true;
			tab.title = str('stepDrag', 'Перетащите, чтобы поменять порядок блоков');
			if (stepIndex === editorUi.activeStep) {
				tab.className += ' is-active';
			}
			var count = (step.fields || []).length;
			tab.textContent =
				str('stepBadge', 'Блок') +
				' ' +
				(stepIndex + 1) +
				(step.title ? ' · ' + step.title : '') +
				(count ? ' (' + count + ')' : '');

			tab.addEventListener('dragstart', function (event) {
				stepDrag = { stepIndex: stepIndex };
				stepDragMoved = false;
				tab.classList.add('is-dragging');
				if (event.dataTransfer) {
					event.dataTransfer.effectAllowed = 'move';
					event.dataTransfer.setData('text/plain', 'step');
				}
			});
			tab.addEventListener('dragend', function () {
				stepDrag = null;
				tab.classList.remove('is-dragging');
				document
					.querySelectorAll('.art-forms-step-tab.art-forms-drop-before')
					.forEach(function (el) {
						el.classList.remove('art-forms-drop-before');
					});
			});
			tab.addEventListener('dragover', function (event) {
				if (!stepDrag) {
					return;
				}
				event.preventDefault();
				document
					.querySelectorAll('.art-forms-step-tab.art-forms-drop-before')
					.forEach(function (el) {
						el.classList.remove('art-forms-drop-before');
					});
				tab.classList.add('art-forms-drop-before');
			});
			tab.addEventListener('drop', function (event) {
				event.preventDefault();
				event.stopPropagation();
				if (!stepDrag) {
					return;
				}
				var from = stepDrag.stepIndex;
				var to = stepIndex;
				stepDrag = null;
				stepDragMoved = true;
				if (moveStep(schema, from, to)) {
					editorUi.activeStep = from < to ? to - 1 : to;
					editorUi.expandedFieldKey = null;
					renderEditor(root, schema, fieldTypes);
					syncSchema(schema);
					markDirty();
				}
			});
			tab.addEventListener('click', function () {
				if (stepDragMoved) {
					stepDragMoved = false;
					return;
				}
				focusStep(schema, root, fieldTypes, stepIndex, false);
			});
			tabs.appendChild(tab);
		});
		root.appendChild(tabs);

		var stepIndex = editorUi.activeStep;
		var step = schema.steps[stepIndex];
		if (!step) {
			syncSchema(schema);
			updateSchemaStats(schema);
			return;
		}

		var stepBox = document.createElement('div');
		stepBox.className = 'art-forms-step';
		stepBox.id = stepAnchorId(stepIndex);

		var stepHead = document.createElement('div');
		stepHead.className = 'art-forms-step-head';

		var badge = document.createElement('span');
		badge.className = 'art-forms-step-badge';
		badge.textContent = str('stepBadge', 'Блок') + ' ' + (stepIndex + 1);

		var titleInput = document.createElement('input');
		titleInput.type = 'text';
		titleInput.className = 'art-forms-step-title';
		titleInput.value = step.title || '';
		titleInput.placeholder = str('stepTitle', 'Название блока');
		titleInput.addEventListener('input', function () {
			schema.steps[stepIndex].title = titleInput.value;
			syncSchema(schema);
			markDirty();
			var activeTab = root.querySelector('.art-forms-step-tab.is-active');
			if (activeTab) {
				var fieldCount = (schema.steps[stepIndex].fields || []).length;
				activeTab.textContent =
					str('stepBadge', 'Блок') +
					' ' +
					(stepIndex + 1) +
					(titleInput.value ? ' · ' + titleInput.value : '') +
					(fieldCount ? ' (' + fieldCount + ')' : '');
			}
		});

		var stepActions = document.createElement('div');
		stepActions.className = 'art-forms-step-actions';

		var duplicateStepBtn = document.createElement('button');
		duplicateStepBtn.type = 'button';
		duplicateStepBtn.className =
			'art-forms-field-icon-btn art-forms-step-duplicate';
		duplicateStepBtn.title = str('duplicateStep', 'Дублировать блок');
		duplicateStepBtn.setAttribute(
			'aria-label',
			str('duplicateStep', 'Дублировать блок')
		);
		duplicateStepBtn.innerHTML =
			'<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>';
		duplicateStepBtn.addEventListener('click', function () {
			duplicateStep(schema, stepIndex);
			renderEditor(root, schema, fieldTypes);
			syncSchema(schema);
			markDirty();
		});
		stepActions.appendChild(duplicateStepBtn);

		if (schema.steps.length > 1) {
			var removeStep = document.createElement('button');
			removeStep.type = 'button';
			removeStep.className =
				'art-forms-field-icon-btn art-forms-step-remove';
			removeStep.title = str('removeStep', 'Удалить блок');
			removeStep.setAttribute(
				'aria-label',
				str('removeStep', 'Удалить блок')
			);
			removeStep.innerHTML =
				'<span class="dashicons dashicons-trash" aria-hidden="true"></span>';
			removeStep.addEventListener('click', function () {
				var fieldCount = (schema.steps[stepIndex].fields || []).length;
				if (fieldCount > 0) {
					var msg = str(
						'confirmRemoveStep',
						'Удалить блок вместе с полями? Это нельзя отменить.'
					);
					if (!window.confirm(msg)) {
						return;
					}
				}
				schema.steps.splice(stepIndex, 1);
				if (editorUi.activeStep >= schema.steps.length) {
					editorUi.activeStep = schema.steps.length - 1;
				}
				editorUi.expandedFieldKey = null;
				renderEditor(root, schema, fieldTypes);
				syncSchema(schema);
				markDirty();
			});
			stepActions.appendChild(removeStep);
		}

		stepHead.appendChild(badge);
		stepHead.appendChild(titleInput);
		stepHead.appendChild(stepActions);
		stepBox.appendChild(stepHead);

		var fieldsWrap = document.createElement('div');
		fieldsWrap.className = 'art-forms-fields';

		var fields = step.fields || [];
		if (!fields.length) {
			var empty = document.createElement('p');
			empty.className = 'art-forms-fields-empty';
			empty.textContent = str(
				'emptyFields',
				'Пока нет полей. Добавьте первое поле ниже.'
			);
			fieldsWrap.appendChild(empty);
		}

		fields.forEach(function (field, fieldIndex) {
			fieldsWrap.appendChild(
				renderFieldRow(schema, stepIndex, fieldIndex, fieldTypes, root)
			);
		});

		stepBox.appendChild(fieldsWrap);

		var footer = document.createElement('div');
		footer.className = 'art-forms-step-footer';

		var addField = document.createElement('button');
		addField.type = 'button';
		addField.className = 'button art-forms-add-field';
		addField.textContent = '+ ' + str('addField', 'Добавить поле');
		addField.addEventListener('click', function () {
			schema.steps[stepIndex].fields = schema.steps[stepIndex].fields || [];
			var newKey = nextAutoKey(schema);
			schema.steps[stepIndex].fields.push({
				key: newKey,
				type: 'text',
				label: '',
				required: true,
				locked: false,
				options: []
			});
			editorUi.activeStep = stepIndex;
			editorUi.expandedFieldKey = newKey;
			renderEditor(root, schema, fieldTypes);
			syncSchema(schema);
			markDirty();
		});

		footer.appendChild(addField);
		stepBox.appendChild(footer);
		root.appendChild(stepBox);

		syncSchema(schema);
		updateSchemaStats(schema);
	}

	function renderFieldRow(schema, stepIndex, fieldIndex, fieldTypes, root) {
		var field = schema.steps[stepIndex].fields[fieldIndex];
		if (!field.key) {
			field.key = nextAutoKey(schema);
		}

		var isExpanded = editorUi.expandedFieldKey === field.key;

		var card = document.createElement('div');
		card.className =
			'art-forms-field-card' +
			(isExpanded ? ' is-expanded' : ' is-collapsed');
		card.id = fieldAnchorId(stepIndex, fieldIndex);

		var summary = document.createElement('div');
		summary.className = 'art-forms-field-summary';

		var dragHandle = document.createElement('span');
		dragHandle.className = 'art-forms-field-drag';
		dragHandle.textContent = '⋮⋮';
		dragHandle.setAttribute('aria-hidden', 'true');
		bindFieldDrag(dragHandle, schema, root, fieldTypes, stepIndex, fieldIndex);

		var toggle = document.createElement('button');
		toggle.type = 'button';
		toggle.className = 'art-forms-field-toggle';
		toggle.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
		toggle.addEventListener('click', function () {
			if (editorUi.expandedFieldKey === field.key) {
				editorUi.expandedFieldKey = null;
				renderEditor(root, schema, fieldTypes);
			} else {
				focusField(schema, root, fieldTypes, stepIndex, field.key, false);
			}
		});

		var chevron = document.createElement('span');
		chevron.className = 'art-forms-field-chevron';
		chevron.textContent = isExpanded ? '▾' : '▸';

		var meta = document.createElement('span');
		meta.className = 'art-forms-field-summary-meta';

		var index = document.createElement('span');
		index.className = 'art-forms-field-index';
		index.textContent = str('fieldNumber', 'Поле') + ' ' + (fieldIndex + 1);

		var typeBadge = document.createElement('span');
		typeBadge.className = 'art-forms-field-type-badge';
		typeBadge.textContent = typeLabel(field.type || 'text');

		var labelText = document.createElement('span');
		labelText.className = 'art-forms-field-summary-label';
		labelText.textContent = field.label || str('noLabel', 'Без подписи');

		var keyCode = document.createElement('code');
		keyCode.className = 'art-forms-field-summary-key';
		keyCode.textContent = field.key;

		meta.appendChild(index);
		meta.appendChild(typeBadge);
		meta.appendChild(labelText);
		meta.appendChild(keyCode);

		if (field.required || field.type === 'consent') {
			var req = document.createElement('span');
			req.className = 'art-forms-field-summary-req';
			req.textContent = '*';
			req.title = str('required', 'Обязательное');
			meta.appendChild(req);
		}

		toggle.appendChild(chevron);
		toggle.appendChild(meta);

		var actions = document.createElement('div');
		actions.className = 'art-forms-field-actions';

		var duplicate = document.createElement('button');
		duplicate.type = 'button';
		duplicate.className = 'art-forms-field-icon-btn art-forms-field-duplicate';
		duplicate.title = str('duplicateField', 'Дублировать');
		duplicate.setAttribute('aria-label', str('duplicateField', 'Дублировать'));
		duplicate.innerHTML = '<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>';
		duplicate.addEventListener('click', function (event) {
			event.stopPropagation();
			var fields = schema.steps[stepIndex].fields || [];
			var newKey = nextDuplicateKey(schema, field.key);
			var copy = cloneFieldForDuplicate(field, newKey);
			fields.splice(fieldIndex + 1, 0, copy);
			editorUi.activeStep = stepIndex;
			editorUi.expandedFieldKey = newKey;
			renderEditor(root, schema, fieldTypes);
			syncSchema(schema);
			markDirty();
		});

		var remove = document.createElement('button');
		remove.type = 'button';
		remove.className = 'art-forms-field-icon-btn art-forms-field-remove';
		remove.title = str('removeField', 'Удалить');
		remove.setAttribute('aria-label', str('removeField', 'Удалить'));
		remove.innerHTML = '<span class="dashicons dashicons-trash" aria-hidden="true"></span>';
		remove.addEventListener('click', function (event) {
			event.stopPropagation();
			var removedKey = field.key;
			schema.steps[stepIndex].fields.splice(fieldIndex, 1);
			if (editorUi.expandedFieldKey === removedKey) {
				editorUi.expandedFieldKey = null;
			}
			renderEditor(root, schema, fieldTypes);
			syncSchema(schema);
			markDirty();
		});

		actions.appendChild(duplicate);
		actions.appendChild(remove);

		summary.appendChild(dragHandle);
		summary.appendChild(toggle);
		summary.appendChild(actions);
		card.appendChild(summary);
		bindFieldDropTarget(card, schema, root, fieldTypes, stepIndex, fieldIndex);

		if (!isExpanded) {
			return card;
		}

		var body = document.createElement('div');
		body.className = 'art-forms-field-body';

		var grid = document.createElement('div');
		grid.className = 'art-forms-field-grid art-forms-field-grid-compact';

		var label = document.createElement('input');
		label.type = 'text';
		label.placeholder = str('fieldLabel', 'Подпись');
		label.value = field.label || '';
		label.addEventListener('input', function () {
			field.label = label.value;
			syncSchema(schema);
			markDirty();
			var summaryLabel = card.querySelector('.art-forms-field-summary-label');
			if (summaryLabel) {
				summaryLabel.textContent =
					field.label || str('noLabel', 'Без подписи');
			}
		});

		var typeSelect = document.createElement('select');
		fieldTypes.forEach(function (type) {
			var opt = document.createElement('option');
			opt.value = type;
			opt.textContent = typeLabel(type);
			if (field.type === type) {
				opt.selected = true;
			}
			typeSelect.appendChild(opt);
		});
		typeSelect.addEventListener('change', function () {
			field.type = typeSelect.value;
			if (field.type === 'consent') {
				field.required = true;
			}
			if (field.type === 'hidden') {
				field.required = false;
			}
			editorUi.expandedFieldKey = field.key;
			renderEditor(root, schema, fieldTypes);
			syncSchema(schema);
			markDirty();
		});

		var requiredWrap = document.createElement('div');
		requiredWrap.className = 'art-forms-control art-forms-control-required-wrap';
		var requiredLab = document.createElement('span');
		requiredLab.className = 'art-forms-control-label';
		requiredLab.innerHTML = '&nbsp;';
		var requiredInner = document.createElement('label');
		requiredInner.className = 'art-forms-control-required';
		var required = document.createElement('input');
		required.type = 'checkbox';
		required.checked =
			field.type === 'consent'
				? true
				: field.type === 'hidden'
					? false
					: !!field.required;
		required.disabled = field.type === 'consent' || field.type === 'hidden';
		required.addEventListener('change', function () {
			field.required = required.checked;
			syncSchema(schema);
			markDirty();
			var summaryReq = card.querySelector('.art-forms-field-summary-req');
			if (field.required || field.type === 'consent') {
				if (!summaryReq) {
					var reqMark = document.createElement('span');
					reqMark.className = 'art-forms-field-summary-req';
					reqMark.textContent = '*';
					reqMark.title = str('required', 'Обязательное');
					var summaryMeta = card.querySelector(
						'.art-forms-field-summary-meta'
					);
					if (summaryMeta) {
						summaryMeta.appendChild(reqMark);
					}
				}
			} else if (summaryReq && summaryReq.parentNode) {
				summaryReq.parentNode.removeChild(summaryReq);
			}
		});
		requiredInner.appendChild(required);
		requiredInner.appendChild(
			document.createTextNode(str('required', 'Обязательное'))
		);
		requiredWrap.appendChild(requiredLab);
		requiredWrap.appendChild(requiredInner);

		var keyWrap = document.createElement('div');
		keyWrap.className = 'art-forms-control art-forms-control-key';
		var keyLab = document.createElement('span');
		keyLab.className = 'art-forms-control-label';
		keyLab.textContent = str('fieldKeyShort', 'Ключ');
		keyWrap.appendChild(keyLab);

		if (field.locked) {
			var keyRow = document.createElement('div');
			keyRow.className = 'art-forms-key-row';

			var keyCodeLocked = document.createElement('code');
			keyCodeLocked.className = 'art-forms-key-code';
			keyCodeLocked.textContent = field.key;
			keyRow.appendChild(keyCodeLocked);

			var unlockBtn = document.createElement('button');
			unlockBtn.type = 'button';
			unlockBtn.className = 'art-forms-unlock';
			unlockBtn.textContent = str('unlockKey', 'Изменить');
			unlockBtn.addEventListener('click', function () {
				var msg = str(
					'confirmUnlock',
					'Переименование ключа может сломать код на сайте и интеграции. Продолжить?'
				);
				if (!window.confirm(msg)) {
					return;
				}
				field.locked = false;
				field.unlock_key = true;
				editorUi.expandedFieldKey = field.key;
				renderEditor(root, schema, fieldTypes);
				syncSchema(schema);
				markDirty();
			});
			keyRow.appendChild(unlockBtn);
			keyWrap.appendChild(keyRow);
		} else {
			var keyInput = document.createElement('input');
			keyInput.type = 'text';
			keyInput.className = 'art-forms-key-input';
			keyInput.value = field.key || '';
			keyInput.title = str(
				'fieldKeyHint',
				'Техническое имя поля (name). Обычно достаточно автоключа f1, f2…'
			);
			keyInput.addEventListener('input', function () {
				var prevKey = field.key;
				field.key = transliterateToKey(keyInput.value);
				keyInput.value = field.key;
				if (editorUi.expandedFieldKey === prevKey) {
					editorUi.expandedFieldKey = field.key;
				}
				syncSchema(schema);
				markDirty();
				var summaryKey = card.querySelector('.art-forms-field-summary-key');
				if (summaryKey) {
					summaryKey.textContent = field.key;
				}
			});
			keyWrap.appendChild(keyInput);
		}

		grid.appendChild(control(str('fieldType', 'Тип'), typeSelect));
		grid.appendChild(control(str('fieldLabel', 'Подпись'), label));
		grid.appendChild(keyWrap);
		grid.appendChild(requiredWrap);
		body.appendChild(grid);

		var typeHintText = typeHint(field.type);
		if (typeHintText) {
			var typeHintEl = document.createElement('p');
			typeHintEl.className = 'art-forms-type-hint';
			typeHintEl.textContent = typeHintText;
			body.appendChild(typeHintEl);
		}

		if (['select', 'radio', 'checkbox'].indexOf(field.type) !== -1) {
			var extra = document.createElement('div');
			extra.className = 'art-forms-field-extra art-forms-field-extra-stack';

			var options = document.createElement('textarea');
			options.className = 'art-forms-options-textarea';
			options.rows = 4;
			options.placeholder = str(
				'optionsHint',
				'Каждый вариант с новой строки'
			);
			options.value = (field.options || []).join('\n');

			var defaultSelect = null;
			var rebuildDefaultSelect = function () {};

			if (field.type === 'select' || field.type === 'radio') {
				defaultSelect = document.createElement('select');
				rebuildDefaultSelect = function () {
					defaultSelect.innerHTML = '';
					var emptyOpt = document.createElement('option');
					emptyOpt.value = '';
					emptyOpt.textContent = str('defaultEmpty', '— не выбрано —');
					defaultSelect.appendChild(emptyOpt);
					(field.options || []).forEach(function (option) {
						var opt = document.createElement('option');
						opt.value = option;
						opt.textContent = option;
						if (field.default === option) {
							opt.selected = true;
						}
						defaultSelect.appendChild(opt);
					});
				};
				rebuildDefaultSelect();
				defaultSelect.addEventListener('change', function () {
					field.default = defaultSelect.value;
					syncSchema(schema);
					markDirty();
				});
			}

			options.addEventListener('input', function () {
				field.options = options.value
					.split(/\r?\n/)
					.map(function (s) {
						return s.trim();
					})
					.filter(Boolean);
				if (
					field.default &&
					field.options.indexOf(field.default) === -1
				) {
					field.default = '';
				}
				rebuildDefaultSelect();
				syncSchema(schema);
				markDirty();
			});

			extra.appendChild(
				control(str('optionsLabel', 'Варианты ответа'), options)
			);
			appendHint(
				extra,
				str('optionsHelp', 'Пишите по одному варианту в строке.')
			);

			if (defaultSelect) {
				extra.appendChild(
					control(
						str('defaultValue', 'Значение по умолчанию'),
						defaultSelect
					)
				);
			}

			body.appendChild(extra);
		}

		if (field.type === 'hidden') {
			var def = document.createElement('input');
			def.type = 'text';
			def.placeholder = str('defaultValue', 'Значение');
			def.value = field.default || '';
			def.addEventListener('input', function () {
				field.default = def.value;
				syncSchema(schema);
				markDirty();
			});
			var hiddenExtra = document.createElement('div');
			hiddenExtra.className = 'art-forms-field-extra';
			hiddenExtra.appendChild(
				control(str('hiddenValue', 'Значение скрытого поля'), def)
			);
			appendHint(hiddenExtra, typeHint('hidden'));
			body.appendChild(hiddenExtra);
		}

		if (field.type === 'consent') {
			var consentExtra = document.createElement('div');
			consentExtra.className = 'art-forms-field-extra art-forms-field-extra-stack';

			var privacyUrl = document.createElement('input');
			privacyUrl.type = 'url';
			privacyUrl.placeholder = 'https://';
			privacyUrl.value =
				field.privacy_url ||
				(window.artFormsAdmin && artFormsAdmin.defaultPrivacyUrl) ||
				'';
			privacyUrl.addEventListener('input', function () {
				field.privacy_url = privacyUrl.value.trim();
				syncSchema(schema);
				markDirty();
			});

			var privacyLinkText = document.createElement('input');
			privacyLinkText.type = 'text';
			privacyLinkText.placeholder = str(
				'privacyLinkPlaceholder',
				'политикой конфиденциальности'
			);
			privacyLinkText.value = field.privacy_link_text || '';
			privacyLinkText.addEventListener('input', function () {
				field.privacy_link_text = privacyLinkText.value;
				syncSchema(schema);
				markDirty();
			});

			consentExtra.appendChild(
				control(str('privacyUrl', 'Ссылка на политику'), privacyUrl)
			);
			consentExtra.appendChild(
				control(str('privacyLinkText', 'Текст ссылки'), privacyLinkText)
			);
			appendHint(consentExtra, typeHint('consent'));
			body.appendChild(consentExtra);
		}

		card.appendChild(body);
		return card;
	}

	function initUnsavedGuard(form) {
		window.addEventListener('beforeunload', function (event) {
			if (!dirty || allowLeave) {
				return;
			}
			event.preventDefault();
			event.returnValue = str(
				'unsavedLeave',
				'У вас есть несохранённые изменения. Уйти со страницы?'
			);
			return event.returnValue;
		});

		if (form) {
			form.addEventListener('submit', function () {
				allowLeave = true;
				clearDirty();
			});
		}

		// Track edits on regular form fields outside schema editor.
		if (form) {
			form.addEventListener('input', function (event) {
				if (
					event.target &&
					event.target.id === 'art-forms-schema-json'
				) {
					return;
				}
				markDirty();
			});
			form.addEventListener('change', function () {
				markDirty();
			});
		}
	}

	function initSchemaEditor() {
		var root = document.getElementById('art-forms-schema-editor');
		if (!root) {
			return;
		}

		var schema = parseJson('art-forms-schema-data', {
			version: 1,
			steps: [{ id: 'step_1', title: 'Блок 1', fields: [] }]
		});
		var fieldTypes = parseJson('art-forms-field-types', [
			'text',
			'email',
			'tel',
			'textarea',
			'select',
			'radio',
			'checkbox',
			'hidden',
			'consent'
		]);

		if (!schema.steps || !schema.steps.length) {
			schema.steps = [{ id: 'step_1', title: 'Блок 1', fields: [] }];
		}

		renderEditor(root, schema, fieldTypes);
		clearDirty();

		var addStep = document.getElementById('art-forms-add-step');
		if (addStep) {
			addStep.addEventListener('click', function () {
				schema.steps.push({
					id: 'step_' + (schema.steps.length + 1),
					title: 'Блок ' + (schema.steps.length + 1),
					fields: []
				});
				editorUi.activeStep = schema.steps.length - 1;
				editorUi.expandedFieldKey = null;
				renderEditor(root, schema, fieldTypes);
				syncSchema(schema);
				markDirty();
			});
		}

		var form = document.getElementById('art-forms-edit-form');
		if (form) {
			form.addEventListener('submit', function (event) {
				syncSchema(schema);

				if (form.getAttribute('data-force-save') === '1') {
					form.removeAttribute('data-force-save');
					allowLeave = true;
					clearDirty();
					return;
				}

				var warnings = schemaContactWarnings(schema);
				if (!warnings.length) {
					allowLeave = true;
					clearDirty();
					return;
				}

				event.preventDefault();
				var box = document.getElementById('art-forms-save-warning');
				if (!box) {
					form.setAttribute('data-force-save', '1');
					form.submit();
					return;
				}

				box.hidden = false;
				box.innerHTML =
					'<p><strong>' +
					str('saveWarnTitle', 'Перед сохранением') +
					':</strong> ' +
					warnings.join(', ') +
					'. ' +
					str(
						'saveWarnHint',
						'Форму всё равно можно сохранить — это только напоминание.'
					) +
					' <button type="button" class="button button-small" id="art-forms-save-anyway">' +
					str('saveAnyway', 'Сохранить всё равно') +
					'</button></p>';

				var anyway = document.getElementById('art-forms-save-anyway');
				if (anyway) {
					anyway.addEventListener('click', function () {
						form.setAttribute('data-force-save', '1');
						allowLeave = true;
						clearDirty();
						form.submit();
					});
				}

				box.scrollIntoView({ behavior: 'smooth', block: 'center' });
			});
		}

		initUnsavedGuard(form);
	}

	function initCollapsiblePanels() {
		var storageKey = 'artFormsCollapse';
		var stored = {};
		try {
			stored = JSON.parse(window.localStorage.getItem(storageKey) || '{}') || {};
		} catch (e) {
			stored = {};
		}

		document.querySelectorAll('.art-forms-collapsible[data-collapse-key]').forEach(function (panel) {
			var key = panel.getAttribute('data-collapse-key') || '';
			var toggle = panel.querySelector('.art-forms-collapse-toggle');
			var chevron = panel.querySelector('.art-forms-collapse-chevron');
			if (!toggle || !key) {
				return;
			}

			if (Object.prototype.hasOwnProperty.call(stored, key)) {
				if (stored[key]) {
					panel.classList.add('is-collapsed');
					toggle.setAttribute('aria-expanded', 'false');
					if (chevron) {
						chevron.textContent = '▸';
					}
				} else {
					panel.classList.remove('is-collapsed');
					toggle.setAttribute('aria-expanded', 'true');
					if (chevron) {
						chevron.textContent = '▾';
					}
				}
			}
		});

		document.addEventListener('click', function (event) {
			var toggle = event.target.closest('.art-forms-collapse-toggle');
			if (!toggle || event.target.closest('.art-forms-action-remove')) {
				return;
			}

			var panel = toggle.closest('.art-forms-collapsible');
			if (!panel) {
				return;
			}

			var chevron = panel.querySelector('.art-forms-collapse-chevron');
			var key = panel.getAttribute('data-collapse-key') || '';
			var collapsed = panel.classList.toggle('is-collapsed');
			toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
			if (chevron) {
				chevron.textContent = collapsed ? '▸' : '▾';
			}
			if (key) {
				stored[key] = collapsed;
				try {
					window.localStorage.setItem(storageKey, JSON.stringify(stored));
				} catch (err) {
					// Ignore quota / private mode.
				}
			}
		});
	}

	function initFormActions() {
		var list = document.getElementById('art-forms-actions-list');
		var select = document.getElementById('art-forms-action-type');
		var addBtn = document.getElementById('art-forms-add-action');
		if (!list || !select || !addBtn) {
			return;
		}

		function usedTypes() {
			return Array.prototype.map.call(
				list.querySelectorAll('.art-forms-action-card[data-action-type]'),
				function (card) {
					return card.getAttribute('data-action-type');
				}
			);
		}

		function refreshSelect() {
			var used = usedTypes();
			Array.prototype.forEach.call(select.options, function (opt) {
				if (!opt.value) {
					opt.disabled = false;
					return;
				}
				opt.disabled = used.indexOf(opt.value) !== -1;
			});
			if (select.value && select.options[select.selectedIndex] && select.options[select.selectedIndex].disabled) {
				select.value = '';
			}
		}

		function reindexActions() {
			Array.prototype.forEach.call(list.querySelectorAll('.art-forms-action-card'), function (card, index) {
				card.querySelectorAll('[name^="actions["]').forEach(function (el) {
					el.name = el.name.replace(/actions\[[^\]]+\]/, 'actions[' + index + ']');
				});
				card.querySelectorAll('[id]').forEach(function (el) {
					el.id = el.id.replace(/-(\d+|__i__)$/, '-' + index);
				});
				card.querySelectorAll('label[for]').forEach(function (label) {
					label.htmlFor = label.htmlFor.replace(/-(\d+|__i__)$/, '-' + index);
				});
			});
		}

		function addAction(type) {
			if (!type) {
				return;
			}
			if (usedTypes().indexOf(type) !== -1) {
				return;
			}
			var tpl = document.getElementById('art-forms-action-tpl-' + type);
			if (!tpl) {
				return;
			}
			var next = list.querySelectorAll('.art-forms-action-card').length;
			var html = tpl.innerHTML.replace(/__i__/g, String(next));
			var wrap = document.createElement('div');
			wrap.innerHTML = html.trim();
			var card = wrap.firstElementChild;
			if (!card) {
				return;
			}
			list.appendChild(card);
			reindexActions();
			refreshSelect();
			select.value = '';
			markDirty();
		}

		addBtn.addEventListener('click', function () {
			addAction(select.value);
		});

		list.addEventListener('click', function (event) {
			var removeBtn = event.target.closest('.art-forms-action-remove');
			if (!removeBtn) {
				return;
			}
			event.preventDefault();
			var card = removeBtn.closest('.art-forms-action-card');
			if (card) {
				card.remove();
				reindexActions();
				refreshSelect();
				markDirty();
			}
		});

		refreshSelect();
	}

	function initSaveStatus() {
		var status = document.getElementById('art-forms-save-status');
		if (!status) {
			return;
		}
		if (status.getAttribute('data-just-saved') === '1') {
			status.textContent = str('savedStatus', 'Сохранено');
			status.classList.add('is-saved');
			window.setTimeout(function () {
				status.classList.remove('is-saved');
				status.textContent = '';
			}, 4000);
		}
	}

	function initCopyButtons() {
		document.querySelectorAll('.art-forms-copy').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var targetId = btn.getAttribute('data-copy-target');
				var target = document.getElementById(targetId);
				var status = document.querySelector('.art-forms-copy-status');
				if (!target) {
					return;
				}

				var text = target.value || target.textContent || '';
				var done = function (ok) {
					if (!status) {
						return;
					}
					status.textContent = ok
						? str('copied', 'Скопировано')
						: str('copyFailed', 'Не удалось скопировать');
				};

				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(text).then(
						function () {
							done(true);
						},
						function () {
							done(false);
						}
					);
				} else {
					target.removeAttribute('readonly');
					target.select();
					try {
						done(document.execCommand('copy'));
					} catch (e) {
						done(false);
					}
					target.setAttribute('readonly', 'readonly');
				}
			});
		});
	}

	function initChecker() {
		var btn = document.getElementById('art-forms-run-checker');
		var resultBox = document.getElementById('art-forms-checker-result');
		var codeBox = document.getElementById('art-forms-checker-code');
		if (!btn || !resultBox || !codeBox) {
			return;
		}

		btn.addEventListener('click', function () {
			var formData = new FormData();
			formData.append('action', 'art_forms_check_code');
			formData.append('nonce', btn.getAttribute('data-nonce') || '');
			formData.append('form_id', btn.getAttribute('data-form-id') || '');
			formData.append('code', codeBox.value || '');

			resultBox.hidden = false;
			resultBox.textContent = '…';

			fetch((window.artFormsAdmin && artFormsAdmin.ajaxUrl) || ajaxurl, {
				method: 'POST',
				credentials: 'same-origin',
				body: formData
			})
				.then(function (r) {
					return r.json();
				})
				.then(function (json) {
					if (!json || !json.success || !json.data) {
						resultBox.className = 'art-forms-checker-result is-error';
						resultBox.textContent = str('checkError', 'Ошибка проверки');
						return;
					}

					var data = json.data;
					var html = '';
					if (data.ok) {
						resultBox.className = 'art-forms-checker-result is-ok';
						html =
							'<strong>OK</strong> — ' +
							str('checkOk', 'код соответствует схеме.');
					} else {
						resultBox.className = 'art-forms-checker-result is-error';
						html =
							'<strong>' +
							str('checkErrors', 'Ошибки') +
							':</strong><ul>' +
							(data.errors || [])
								.map(function (e) {
									return '<li>' + e + '</li>';
								})
								.join('') +
							'</ul>';
					}

					if (data.warnings && data.warnings.length) {
						html +=
							'<p><strong>' +
							str('checkWarnings', 'Предупреждения') +
							':</strong></p><ul>' +
							data.warnings
								.map(function (w) {
									return '<li>' + w + '</li>';
								})
								.join('') +
							'</ul>';
					}

					resultBox.innerHTML = html;
				})
				.catch(function () {
					resultBox.className = 'art-forms-checker-result is-error';
					resultBox.textContent = str('networkError', 'Ошибка сети');
				});
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		initSchemaEditor();
		initCollapsiblePanels();
		initFormActions();
		initSaveStatus();
		initCopyButtons();
		initChecker();
	});
})();
