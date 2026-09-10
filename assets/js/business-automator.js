(function () {
  'use strict';

  const settings = window.SeoisticAutomator || {};
  const strings = settings.strings || {};

  function text(value, fallback = '') {
    return typeof value === 'string' && value ? value : fallback;
  }

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  }

  function request(path, body = {}) {
    return window.wp.apiFetch({
      path,
      method: 'POST',
      data: Object.assign({ nonce: settings.nonce }, body)
    });
  }

  function statusLabel(status) {
    const labels = {
      running: 'Running',
      completed: 'Completed',
      awaiting_approval: strings.awaiting || 'Awaiting approval',
      failed: 'Failed',
      skipped: 'Skipped',
      approved: strings.approved || 'Approved',
      auto_approved: 'Auto-approved',
      locked: 'Locked'
    };
    return labels[status] || status;
  }

  function render() {
    const root = document.getElementById('seoistic-automator-root');
    if (!root) return;
    root.className = 'seoistic-automator';
    root.innerHTML = root.dataset.tab === 'history' ? renderHistory() : renderRecipes();
    bindEvents(root);
  }

  function renderRecipes() {
    const rows = (settings.recipes || []).map((recipe) => `
      <tr data-recipe="${escapeHtml(recipe.id)}">
        <th scope="row">${escapeHtml(recipe.name)}</th>
        <td>${escapeHtml(recipe.description)}</td>
        <td>${escapeHtml(recipe.trigger)}${recipe.trigger === 'schedule' ? ' · ' + escapeHtml(recipe.schedule) : ''}</td>
        <td>
          <label><input type="checkbox" class="automator-enabled" ${recipe.enabled ? 'checked' : ''}> ${escapeHtml('Enabled')}</label>
          <label><input type="checkbox" class="automator-auto" ${recipe.auto_apply ? 'checked' : ''}> ${escapeHtml('Auto-apply')}</label>
          ${recipe.trigger === 'schedule' ? `<select class="automator-schedule">${['daily', 'weekly'].map((value) => `<option value="${value}" ${recipe.schedule === value ? 'selected' : ''}>${value}</option>`).join('')}</select>` : ''}
        </td>
        <td><button type="button" class="button" data-run="${escapeHtml(recipe.id)}">${escapeHtml(strings.run || 'Run')}</button></td>
      </tr>`).join('');

    return `
      <div class="seoistic-card seoistic-automator-card">
        <div class="seoistic-notice" data-status="idle" hidden></div>
        <label class="automator-email"><strong>${escapeHtml('Notification email')}</strong>
          <input class="regular-text" type="email" data-email value="${escapeHtml((settings.settings || {}).notify_email || '')}" placeholder="${escapeHtml('Site admin email')}">
        </label>
        <div class="table-responsive">
          <table class="widefat striped">
            <thead><tr><th>${escapeHtml('Recipe')}</th><th>${escapeHtml('Purpose')}</th><th>${escapeHtml('Trigger')}</th><th>${escapeHtml('Options')}</th><th>${escapeHtml('Actions')}</th></tr></thead>
            <tbody>${rows}</tbody>
          </table>
        </div>
      </div>`;
  }

  function renderHistory() {
    const runs = settings.history || [];
    if (!runs.length) return `<div class="seoistic-card"><p>${escapeHtml('No automation runs recorded yet.')}</p></div>`;
    return runs.map((run) => `
      <details class="seoistic-card seoistic-run" data-run-id="${escapeHtml(run.run_id)}">
        <summary>
          <strong>${escapeHtml(run.recipe_name)}</strong>
          <span class="seoistic-badge">${escapeHtml(statusLabel(run.status))}</span>
          ${run.auto_applied ? `<span class="seoistic-badge">${escapeHtml('Auto-applied')}</span>` : ''}
          <span>${escapeHtml(run.started_at || '')}</span>
          ${run.status === 'awaiting_approval' ? `<button type="button" class="button button-primary" data-approve="${escapeHtml(run.run_id)}">${escapeHtml(strings.approve || 'Approve & apply')}</button>` : ''}
        </summary>
        <div class="seoistic-run-steps">${(run.steps || []).map((step) => renderStep(step)).join('')}</div>
      </details>`).join('');
  }

  function renderStep(step) {
    const details = step.data || {};
    return `
      <section class="seoistic-step">
        <header><strong>${escapeHtml(step.id)}</strong><span>${escapeHtml(statusLabel(step.status))}</span></header>
        ${details.diff_preview ? `<pre>${Object.entries(details.diff_preview).map(([key, diff]) => escapeHtml(key) + '\n' + escapeHtml(diff)).join('\n\n')}</pre>` : ''}
        ${details.error ? `<p class="error">${escapeHtml(details.error)}</p>` : ''}
        ${step.id === 'apply' ? `<p>${escapeHtml(details.changed ? 'Changes applied.' : 'No changes applied.')}</p>` : ''}
      </section>`;
  }

  function bindEvents(root) {
    root.querySelectorAll('[data-run]').forEach((button) => button.addEventListener('click', () => triggerRun(button)));
    root.querySelectorAll('[data-approve]').forEach((button) => button.addEventListener('click', () => approveRun(button)));
    root.querySelectorAll('[data-recipe]').forEach((row) => {
      row.querySelectorAll('input, select').forEach((control) => control.addEventListener('change', saveRecipe(row)));
    });
  }

  function triggerRun(button) {
    button.disabled = true;
    request('/seoistic/v1/business-automator/run', { recipe_id: button.dataset.run })
      .then((response) => showStatus('Run created: ' + statusLabel((response.data || {}).status), 'success'))
      .catch((error) => showStatus(error.message, 'error'))
      .finally(() => { button.disabled = false; });
  }

  function approveRun(button) {
    if (!window.confirm(strings.confirm || 'Apply this approved automation change?')) return;
    button.disabled = true;
    request(`/seoistic/v1/business-automator/runs/${encodeURIComponent(button.dataset.approve)}/approve`)
      .then(() => showStatus(strings.approved || 'Approved', 'success'))
      .catch((error) => showStatus(error.message, 'error'))
      .finally(() => { button.disabled = false; });
  }

  function saveRecipe(row) {
    const recipe = (settings.recipes || []).find((item) => item.id === row.dataset.recipe) || {};
    request(`/seoistic/v1/business-automator/recipes/${encodeURIComponent(row.dataset.recipe)}`, {
      enabled: row.querySelector('.automator-enabled').checked,
      auto_apply: row.querySelector('.automator-auto').checked,
      schedule: row.querySelector('.automator-schedule') ? row.querySelector('.automator-schedule').value : 'weekly',
      notify_email: document.querySelector('[data-email]') ? document.querySelector('[data-email]').value : ''
    })
      .then(() => showStatus('Recipe saved.', 'success'))
      .catch((error) => showStatus(error.message, 'error'));
  }

  function showStatus(message, type) {
    const notice = document.querySelector('[data-status]');
    if (!notice) return;
    notice.hidden = false;
    notice.className = `seoistic-notice notice notice-${type === 'success' ? 'success' : 'error'}`;
    notice.textContent = type === 'success' ? message : `${strings.error || 'Error'}: ${message}`;
  }

  document.addEventListener('DOMContentLoaded', render);
}());
