(function () {
	'use strict';

	var config = window.SeoisticAeo || {};
	var strings = config.i18n || {};
	var blueprint = config.llms || { title: '', description: '', sections: [] };
	var pendingChecklist = {};

	document.addEventListener('DOMContentLoaded', init);

	function init() {
		initBuilder();
		initCrawlerAnalytics();
		initAudit();
		initChecklist();
	}

	function initBuilder() {
		var form = document.querySelector('[data-seoistic-llms-form]');
		var preview = document.querySelector('[data-seoistic-llms-preview]');
		if (!form || !preview) {
			return;
		}
		renderBuilder(form);
		updatePreview(preview);
		form.addEventListener('input', debounce(function () {
			blueprint = readBlueprint(form);
			updatePreview(preview);
		}, 150));
		form.addEventListener('click', function (event) {
			var target = event.target;
			blueprint = readBlueprint(form);
			if (target.matches('[data-add-section]')) {
				blueprint.sections.push({ id: uuid(), title: '', description: '', links: [] });
				rerenderBuilder(form, preview);
			}
			if (target.matches('[data-remove-section]')) {
				blueprint.sections.splice(index(target, 'section'), 1);
				rerenderBuilder(form, preview);
			}
			if (target.matches('[data-add-link]')) {
				var sectionIndex = index(target, 'section');
				blueprint.sections[sectionIndex].links.push({ text: '', url: '' });
				rerenderBuilder(form, preview);
			}
			if (target.matches('[data-remove-link]')) {
				var indexes = indexesFor(target);
				blueprint.sections[indexes.section].links.splice(indexes.link, 1);
				rerenderBuilder(form, preview);
			}
		});
		bindRest('[data-seoistic-llms-save]', function () {
			return restPost('/aeo/llms', { blueprint: readBlueprint(form), apply: false });
		}, function (json, box) {
			showResult(box, true, json.data && json.data.applied ? 'Applied.' : 'Blueprint saved.');
		});
		bindRest('[data-seoistic-llms-apply]', function () {
			blueprint = readBlueprint(form);
			var invalid = findInvalidLink(blueprint);
			if (invalid) {
				return Promise.reject(new Error(invalid));
			}
			return restPost('/aeo/llms', { blueprint: blueprint, apply: true });
		}, function (json, box) {
			showResult(box, true, 'Blueprint saved and applied to llms.txt.');
		});
		var resetButton = document.querySelector('[data-seoistic-llms-reset]');
		if (!resetButton) {
			return;
		}
		resetButton.addEventListener('click', function () {
			if (!window.confirm(strings.confirmReset || 'Reset the visual builder?')) {
				return;
			}
			restPost('/aeo/llms/reset', {}).then(function (json) {
				blueprint = (json.data && json.data.blueprint) || { sections: [] };
				rerenderBuilder(form, preview);
			}).catch(restError);
		});
	}

	function renderBuilder(form) {
		var html = '';
		html += field('title', 'Site title', blueprint.title);
		html += textarea('description', 'Site entity description', blueprint.description);
		blueprint.sections.forEach(function (section, sectionIndex) {
			html += '<details class="seoistic-aeo-section"' + (0 === sectionIndex ? ' open' : '') + ' data-section="' + sectionIndex + '">';
			html += '<summary><span>' + escapeHtml(section.title || 'Untitled section') + '</span><button type="button" class="button-link" data-remove-section="' + sectionIndex + '">' + t('removeSection', 'Remove section') + '</button></summary>';
			html += field('section-title-' + sectionIndex, 'Section title', section.title);
			html += textarea('section-description-' + sectionIndex, 'Entity / topic description', section.description);
			html += '<div class="seoistic-aeo-links">';
			section.links.forEach(function (link, linkIndex) {
				var suffix = sectionIndex + '-' + linkIndex;
				html += '<div class="seoistic-aeo-link" data-section="' + sectionIndex + '" data-link="' + linkIndex + '">';
				html += field('link-text-' + suffix, 'Link text', link.text);
				html += field('link-url-' + suffix, 'Link URL', link.url, 'https://');
				html += '<button type="button" class="button" data-remove-link="' + sectionIndex + '-' + linkIndex + '">' + t('removeLink', 'Remove link') + '</button>';
				html += '</div>';
			});
			html += '</div><button type="button" class="button" data-add-link="' + sectionIndex + '">' + t('addLink', 'Add link') + '</button></details>';
		});
		html += '<button type="button" class="button button-primary" data-add-section>' + t('addSection', 'Add section') + '</button>';
		form.innerHTML = html;
	}

	function readBlueprint(form) {
		var value = {
			title: inputValue(form, '[data-field="title"]'),
			description: inputValue(form, '[data-field="description"]'),
			sections: []
		};
		form.querySelectorAll('.seoistic-aeo-section').forEach(function (section) {
			var sectionIndex = section.getAttribute('data-section');
			var links = [];
			section.querySelectorAll('.seoistic-aeo-link').forEach(function (link) {
				var suffix = sectionIndex + '-' + link.getAttribute('data-link');
				links.push({
					text: inputValue(link, '[data-field="link-text-' + suffix + '"]'),
					url: inputValue(link, '[data-field="link-url-' + suffix + '"]')
				});
			});
			value.sections.push({
				id: (blueprint.sections[sectionIndex] || {}).id || uuid(),
				title: inputValue(section, '[data-field="section-title-' + sectionIndex + '"]'),
				description: inputValue(section, '[data-field="section-description-' + sectionIndex + '"]'),
				links: links
			});
		});
		return value;
	}

	function updatePreview(preview) {
		var lines = ['# ' + (blueprint.title || '')];
		if (blueprint.description) {
			lines.push('> ' + blueprint.description);
		}
		lines.push('');
		blueprint.sections.forEach(function (section) {
			if (!section.title && !section.description && !section.links.length) {
				return;
			}
			lines.push('## ' + (section.title || ''));
			if (section.description) {
				lines.push(section.description);
			}
			section.links.forEach(function (link) {
				lines.push('- [' + (link.text || 'Untitled') + '](' + link.url + ')');
			});
			lines.push('');
		});
		lines.push('## Sitemap', '- ' + window.location.origin + '/wp-sitemap.xml');
		preview.textContent = lines.join('\n');
	}

	function initCrawlerAnalytics() {
		var stats = config.crawler || {};
		var chart = document.querySelector('[data-seoistic-crawler-chart]');
		var body = document.querySelector('[data-seoistic-crawler-urls]');
		if (!chart || !body) {
			return;
		}
		renderChart(chart, stats.days || []);
		if ((stats.urls || []).length) {
			body.innerHTML = (stats.urls || []).map(function (row) {
				return '<tr><td><a href="' + escapeAttr(row.url) + '">' + escapeHtml(row.url) + '</a></td><td>' + escapeHtml(String(row.visits)) + '</td><td>' + escapeHtml(row.last_visit || '') + '</td></tr>';
			}).join('');
		}
	}

	function renderChart(chart, rows) {
		var byDate = {};
		rows.forEach(function (row) {
			byDate[row.date] = (byDate[row.date] || 0) + Number(row.visits || 0);
		});
		var dates = Object.keys(byDate).sort();
		var max = dates.reduce(function (carry, date) {
			return Math.max(carry, byDate[date]);
		}, 1);
		chart.innerHTML = dates.map(function (date) {
			var height = Math.max(4, Math.round((byDate[date] / max) * 100));
			return '<span class="seoistic-aeo-bar" style="--height:' + height + '%" title="' + escapeAttr(date + ': ' + byDate[date]) + '"></span>';
		}).join('') || '<p class="description">No visits recorded yet.</p>';
	}

	function initAudit() {
		var button = document.querySelector('[data-seoistic-audit-run]');
		if (!button) {
			return;
		}
		var card = button.closest('[data-seoistic-audit-card]');
		var tracker = window.auroraTracker && card ? window.auroraTracker(card, {
			queued: strings.queued || 'Queued',
			progress: strings.auditing || 'Auditing…',
			done: strings.done || 'Done',
			queuedMessage: strings.auditing || 'Auditing…'
		}) : null;
		button.addEventListener('click', function () {
			var postId = Number((document.querySelector('[data-seoistic-audit-post]') || {}).value || 0);
			var box = document.querySelector('[data-seoistic-audit-result]');
			if (!postId) {
				showResult(box, false, strings.emptyPost || 'Choose content.');
				return;
			}
			setBusy(button, true, strings.audit || 'Run audit');
			if (tracker) {
				tracker.start();
				tracker.state('progress');
				tracker.progress(0, 1, strings.auditing || 'Auditing…');
			}
			restPost('/aeo/audit', { post_id: postId }).then(function (json) {
				renderAudit(box, json.data || {});
				if (tracker) {
					tracker.finish((json.data && Number(json.data.score || 0)) + '/100');
				}
			}).catch(function (error) {
				showResult(box, false, friendlyError(error));
				if (tracker) {
					tracker.fail(friendlyError(error));
				}
			}).finally(function () {
				setBusy(button, false, strings.audit || 'Run audit');
			});
		});
	}

	function renderAudit(box, report) {
		var checks = report.checks || {};
		var suggestions = report.suggestions || [];
		var html = '<div class="seoistic-aeo-audit"><strong>AEO score: ' + Number(report.score || 0) + '/100</strong><ul>';
		Object.keys(checks).forEach(function (key) {
			html += '<li class="seoistic-audit-item ' + (checks[key] ? 'is-complete' : 'is-incomplete') + '">' + escapeHtml(label(key)) + ': ' + (checks[key] ? 'Pass' : 'Needs work') + '</li>';
		});
		html += '</ul>';
		if (suggestions.length) {
			html += '<h4>Fix suggestions</h4><ul>';
			suggestions.forEach(function (suggestion) {
				html += '<li>' + escapeHtml(suggestion) + '</li>';
			});
			html += '</ul>';
		}
		html += '</div>';
		showResult(box, true, html);
		if (window.auroraInitChecklist) {
			window.auroraInitChecklist();
		}
	}

	function initChecklist() {
		var select = document.querySelector('[data-seoistic-checklist-post]');
		var list = document.querySelector('[data-seoistic-checklist-list]');
		var button = document.querySelector('[data-seoistic-checklist-save]');
		if (!select || !list || !button) {
			return;
		}
		renderChecklist(list, {});
		select.addEventListener('change', function () {
			restGet('/aeo/checklist?post_id=' + Number(select.value)).then(function (json) {
				pendingChecklist = json.data || {};
				renderChecklist(list, json.data || {});
			}).catch(function () {
				pendingChecklist = {};
				renderChecklist(list, {});
			});
		});
		list.addEventListener('change', function (event) {
			if (event.target.matches('[data-checklist-key]')) {
				pendingChecklist[event.target.getAttribute('data-checklist-key')] = event.target.checked ? 1 : 0;
			}
		});
		button.addEventListener('click', function () {
			var box = document.querySelector('[data-seoistic-checklist-result]');
			setBusy(button, true, strings.saveChecklist || 'Save');
			restPost('/aeo/checklist', { post_id: Number(select.value), values: pendingChecklist }).then(function () {
				showResult(box, true, 'Checklist saved.');
			}).catch(function (error) {
				showResult(box, false, friendlyError(error));
			}).finally(function () {
				setBusy(button, false, strings.saveChecklist || 'Save');
			});
		});
	}

	function renderChecklist(list, values) {
		var items = config.checklist || {};
		list.innerHTML = Object.keys(items).map(function (key) {
			var item = items[key];
			var checked = values[key] && values[key].completed;
			return '<label class="seoistic-aeo-check"><input type="checkbox" data-checklist-key="' + escapeAttr(key) + '"' + (checked ? ' checked' : '') + '> <span><strong>' + escapeHtml(item.label) + '</strong><small>' + escapeHtml(item.help) + '</small></span></label>';
		}).join('');
	}

	function restGet(path) {
		return window.seoisticRestGet ? window.seoisticRestGet(path) : fetch(window.SeoisticAdmin.restUrl + path, {
			headers: { 'X-WP-Nonce': window.SeoisticAdmin.restNonce }
		}).then(parseRest);
	}

	function restPost(path, body) {
		return window.seoisticRestPost ? window.seoisticRestPost(path, body) : fetch(window.SeoisticAdmin.restUrl + path, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': window.SeoisticAdmin.restNonce
			},
			body: JSON.stringify(body || {})
		}).then(parseRest);
	}

	function parseRest(response) {
		return response.json().then(function (json) {
			if (!response.ok) {
				throw new Error(json.message || 'Request failed.');
			}
			return json;
		});
	}

	function bindRest(selector, request, success) {
		var button = document.querySelector(selector);
		if (!button) {
			return;
		}
		button.addEventListener('click', function () {
			var box = button.closest('.seoistic-tool-card').querySelector('.seoistic-tool-result');
			var original = button.textContent;
			setBusy(button, true);
			request().then(function (json) {
				success(json, box);
			}).catch(function (error) {
				showResult(box, false, friendlyError(error));
			}).finally(function () {
				setBusy(button, false, original);
			});
		});
	}

	function findInvalidLink(value) {
		var invalid = '';
		value.sections.forEach(function (section) {
			section.links.forEach(function (link) {
				if (link.text && !link.url) {
					invalid = strings.emptyUrl || 'Add a link URL.';
				}
			});
		});
		return invalid;
	}

	function rerenderBuilder(form, preview) {
		renderBuilder(form);
		updatePreview(preview);
	}

	function field(name, labelText, value, placeholder) {
		return '<label class="seoistic-field"><span>' + escapeHtml(labelText) + '</span><input type="text" data-field="' + escapeAttr(name) + '" value="' + escapeAttr(value || '') + '" placeholder="' + escapeAttr(placeholder || '') + '"></label>';
	}

	function textarea(name, labelText, value) {
		return '<label class="seoistic-field"><span>' + escapeHtml(labelText) + '</span><textarea rows="3" data-field="' + escapeAttr(name) + '">' + escapeHtml(value || '') + '</textarea></label>';
	}

	function inputValue(scope, selector) {
		return ((scope || document).querySelector(selector) || {}).value || '';
	}

	function indexesFor(target) {
		var parts = (target.getAttribute('data-remove-link') || '').split('-');
		return { section: Number(parts[0]), link: Number(parts[1]) };
	}

	function index(target, type) {
		return Number(target.getAttribute('data-' + (type === 'section' ? 'remove-section' : 'remove-link')).split('-')[0]);
	}

	function label(key) {
		var labels = {
			answer_first: 'Answer-first structure',
			faq_presence: 'FAQ presence',
			entity_coverage: 'Entity coverage',
			heading_clarity: 'Heading clarity',
			freshness: 'Freshness'
		};
		return labels[key] || key;
	}

	function setBusy(button, busy, label) {
		button.disabled = busy;
		if (busy) {
			button.dataset.originalText = button.textContent;
			button.textContent = label || 'Working…';
		} else if (button.dataset.originalText) {
			button.textContent = label || button.dataset.originalText;
		}
	}

	function showResult(box, success, message) {
		if (!box) {
			return;
		}
		box.hidden = false;
		box.className = 'seoistic-tool-result ' + (success ? 'is-success' : 'is-error');
		box.innerHTML = message;
		if (window.seoisticToast) {
			window.seoisticToast(success ? (typeof message === 'string' ? message.replace(/<[^>]*>/g, ' ') : 'Completed.') : message, success ? 'success' : 'error');
		}
	}

	function friendlyError(error) {
		return window.seoisticAiError ? window.seoisticAiError(error).message : (error.message || 'Request failed.');
	}

	function restError(error) {
		window.console && window.console.error(error);
	}

	function debounce(fn, wait) {
		var timer = 0;
		return function () {
			window.clearTimeout(timer);
			timer = window.setTimeout(fn, wait);
		};
	}

	function uuid() {
		return 'id-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
	}

	function t(key, fallback) {
		return strings[key] || fallback;
	}

	function escapeHtml(value) {
		return String(value).replace(/[&<>"']/g, function (char) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char];
		});
	}

	function escapeAttr(value) {
		return escapeHtml(value);
	}
})();
