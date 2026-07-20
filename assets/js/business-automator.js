/**
 * Business Automator Admin Page
 */

document.addEventListener('DOMContentLoaded', function () {
	// Tab switching
	const tabLinks = document.querySelectorAll('.seoistic-tab-link');
	const tabPanels = document.querySelectorAll('.seoistic-tab-panel');

	tabLinks.forEach(link => {
		link.addEventListener('click', function (e) {
			e.preventDefault();

			// Remove active class from all
			tabLinks.forEach(l => l.classList.remove('active'));
			tabPanels.forEach(p => p.classList.remove('active'));

			// Add active class to clicked tab
			this.classList.add('active');
			const target = this.getAttribute('href').substring(1);
			document.getElementById(target).classList.add('active');
		});
	});

	// Test connection button
	const testBtn = document.getElementById('test-connection-btn');
	if (testBtn) {
		testBtn.addEventListener('click', testConnection);
	}

	// Deploy template buttons
	const deployBtns = document.querySelectorAll('.deploy-template');
	deployBtns.forEach(btn => {
		btn.addEventListener('click', function () {
			const templateId = this.dataset.templateId;
			deployTemplate(templateId);
		});
	});

	// Load automations
	loadAutomations();
});

/**
 * Test connection to Business Automator
 */
function testConnection() {
	const url = document.getElementById('seoistic_automator_url').value;
	const token = document.getElementById('seoistic_automator_api_token').value;

	if (!url || !token) {
		showStatus('Please enter both URL and API token', 'error');
		return;
	}

	const statusDiv = document.getElementById('connection-status');
	statusDiv.style.display = 'block';
	statusDiv.innerHTML = '<p>Testing connection...</p>';

	wp.apiFetch({
		path: '/seoistic/v1/automations/test-connection',
		method: 'POST',
		data: {
			url: url,
			token: token,
		},
	})
		.then(response => {
			if (response.success) {
				showStatus('✓ Connection successful!', 'success');
			} else {
				showStatus('✗ Connection failed: ' + response.message, 'error');
			}
		})
		.catch(error => {
			showStatus('Error: ' + error.message, 'error');
		});
}

/**
 * Load and display automations
 */
function loadAutomations() {
	const list = document.getElementById('automations-list');
	if (!list) return;

	wp.apiFetch({
		path: '/seoistic/v1/automations',
		method: 'GET',
	})
		.then(automations => {
			if (automations.length === 0) {
				list.innerHTML = '<p>No automations created yet. Deploy a template to get started.</p>';
			} else {
				let html = '<table class="widefat"><thead><tr><th>Name</th><th>Type</th><th>Status</th><th>Actions</th></tr></thead><tbody>';

				automations.forEach(automation => {
					const statusClass = automation.enabled ? 'enabled' : 'disabled';
					const statusText = automation.enabled ? 'Active' : 'Inactive';

					html += `
						<tr>
							<td>${escapeHtml(automation.name)}</td>
							<td>${escapeHtml(automation.type)}</td>
							<td><span class="status ${statusClass}">${statusText}</span></td>
							<td>
								<button class="button button-small" onclick="editAutomation('${automation.id}')">Edit</button>
								<button class="button button-small" onclick="deleteAutomation('${automation.id}')">Delete</button>
							</td>
						</tr>
					`;
				});

				html += '</tbody></table>';
				list.innerHTML = html;
			}
		})
		.catch(error => {
			list.innerHTML = '<p style="color: red;">Error loading automations: ' + escapeHtml(error.message) + '</p>';
		});
}

/**
 * Deploy a template
 */
function deployTemplate(templateId) {
	// In a real implementation, this would show a modal to configure the template
	// For now, just create a basic automation from the template
	alert('Deploying template: ' + templateId + '\n\nA configuration dialog will appear here.');
}

/**
 * Edit automation
 */
function editAutomation(automationId) {
	alert('Editing automation: ' + automationId);
}

/**
 * Delete automation
 */
function deleteAutomation(automationId) {
	if (!confirm('Are you sure you want to delete this automation?')) {
		return;
	}

	wp.apiFetch({
		path: '/seoistic/v1/automations/' + automationId,
		method: 'DELETE',
	})
		.then(() => {
			showStatus('Automation deleted', 'success');
			loadAutomations();
		})
		.catch(error => {
			showStatus('Error: ' + error.message, 'error');
		});
}

/**
 * Show status message
 */
function showStatus(message, type) {
	const statusDiv = document.getElementById('connection-status');
	statusDiv.style.display = 'block';

	const className = type === 'success' ? 'notice-success' : 'notice-error';
	statusDiv.innerHTML = `<div class="notice ${className}"><p>${escapeHtml(message)}</p></div>`;

	// Auto-hide after 5 seconds
	setTimeout(() => {
		statusDiv.style.display = 'none';
	}, 5000);
}

/**
 * Escape HTML special characters
 */
function escapeHtml(text) {
	const div = document.createElement('div');
	div.textContent = text;
	return div.innerHTML;
}
