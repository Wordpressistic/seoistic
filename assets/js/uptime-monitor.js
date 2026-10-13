/**
 * SEOISTIC Domain & Uptime Monitor admin.
 *
 * Talks to the /seoistic/v1/uptime REST routes via wp.apiFetch (which attaches
 * the X-WP-Nonce header automatically). No inline data or secrets — the page
 * shell is server-rendered and this script hydrates it.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.apiFetch ) {
		return;
	}

	var apiFetch = wp.apiFetch;
	var __ = wp.i18n && wp.i18n.__ ? wp.i18n.__ : function ( s ) { return s; };

	var form = document.getElementById( 'seoistic-uptime-form' );
	var rows = document.getElementById( 'seoistic-uptime-rows' );
	var message = document.getElementById( 'seoistic-uptime-message' );
	var addBtn = document.getElementById( 'seoistic-uptime-add' );
	var cancelBtn = document.getElementById( 'seoistic-uptime-cancel' );

	if ( ! form || ! rows ) {
		return;
	}

	function notify( text, isError ) {
		message.textContent = text;
		message.className = isError ? 'notice notice-error' : 'notice notice-success';
		if ( text ) {
			message.setAttribute( 'style', 'padding:8px 12px;margin:10px 0;' );
		}
	}

	function escapeHtml( value ) {
		var div = document.createElement( 'div' );
		div.textContent = value === null || value === undefined ? '' : String( value );
		return div.innerHTML;
	}

	function statusBadge( status ) {
		var map = {
			up: { label: __( 'Up', 'seoistic' ), color: '#1a7f37' },
			down: { label: __( 'Down', 'seoistic' ), color: '#b32d2e' },
			unknown: { label: __( 'Pending', 'seoistic' ), color: '#8a8a8a' }
		};
		var m = map[ status ] || map.unknown;
		return '<span style="display:inline-block;padding:2px 8px;border-radius:10px;color:#fff;font-size:11px;background:' + m.color + '">' + escapeHtml( m.label ) + '</span>';
	}

	function fmt( value, suffix ) {
		if ( value === null || value === undefined || value === '' ) {
			return '—';
		}
		return escapeHtml( value ) + ( suffix || '' );
	}

	function renderRow( m ) {
		var uptime = m.uptime_30d === null || m.uptime_30d === undefined ? '—' : escapeHtml( m.uptime_30d ) + '%';
		var response = m.last_response_ms === null ? '—' : escapeHtml( m.last_response_ms ) + ' ms';
		if ( m.last_code ) {
			response += ' · ' + escapeHtml( m.last_code );
		}
		var tr = document.createElement( 'tr' );
		tr.innerHTML =
			'<td>' + statusBadge( m.current_status ) + ( m.enabled ? '' : ' <em>(' + __( 'paused', 'seoistic' ) + ')</em>' ) + '</td>' +
			'<td><strong>' + escapeHtml( m.label ) + '</strong><br /><a href="' + escapeHtml( m.url ) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml( m.url ) + '</a>' +
			( m.last_error ? '<br /><span style="color:#b32d2e;font-size:12px;">' + escapeHtml( m.last_error ) + '</span>' : '' ) + '</td>' +
			'<td>' + uptime + '</td>' +
			'<td>' + response + '</td>' +
			'<td>' + fmt( m.ssl_expires ) + '</td>' +
			'<td>' + fmt( m.last_checked ) + '</td>' +
			'<td></td>';

		var actions = tr.lastChild;
		actions.appendChild( actionButton( __( 'Check now', 'seoistic' ), function () { checkNow( m.id ); } ) );
		actions.appendChild( actionButton( __( 'Edit', 'seoistic' ), function () { editMonitor( m ); } ) );
		actions.appendChild( actionButton( __( 'Delete', 'seoistic' ), function () { deleteMonitor( m ); } ) );
		return tr;
	}

	function actionButton( label, onClick ) {
		var btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = 'button button-small';
		btn.style.marginRight = '4px';
		btn.textContent = label;
		btn.addEventListener( 'click', onClick );
		return btn;
	}

	function load() {
		apiFetch( { path: '/seoistic/v1/uptime/monitors' } ).then( function ( res ) {
			rows.innerHTML = '';
			if ( ! res.monitors || ! res.monitors.length ) {
				rows.innerHTML = '<tr><td colspan="7">' + __( 'No monitors yet. Add one to start tracking uptime.', 'seoistic' ) + '</td></tr>';
				return;
			}
			res.monitors.forEach( function ( m ) { rows.appendChild( renderRow( m ) ); } );
		} ).catch( function ( err ) {
			rows.innerHTML = '<tr><td colspan="7">' + escapeHtml( ( err && err.message ) || __( 'Failed to load monitors.', 'seoistic' ) ) + '</td></tr>';
		} );
	}

	function formData() {
		return {
			id: form.querySelector( '[name=id]' ).value,
			label: form.querySelector( '[name=label]' ).value,
			url: form.querySelector( '[name=url]' ).value,
			method: form.querySelector( '[name=method]' ).value,
			expected_code: parseInt( form.querySelector( '[name=expected_code]' ).value, 10 ) || 0,
			keyword: form.querySelector( '[name=keyword]' ).value,
			interval_minutes: parseInt( form.querySelector( '[name=interval_minutes]' ).value, 10 ) || 5,
			timeout_seconds: parseInt( form.querySelector( '[name=timeout_seconds]' ).value, 10 ) || 10,
			notify_email: form.querySelector( '[name=notify_email]' ).value,
			enabled: form.querySelector( '[name=enabled]' ).checked
		};
	}

	function showForm( show ) {
		form.hidden = ! show;
		if ( show ) {
			form.querySelector( '[name=label]' ).focus();
		}
	}

	function resetForm() {
		form.reset();
		form.querySelector( '[name=id]' ).value = '';
		form.querySelector( '[name=enabled]' ).checked = true;
	}

	function editMonitor( m ) {
		resetForm();
		form.querySelector( '[name=id]' ).value = m.id;
		form.querySelector( '[name=label]' ).value = m.label;
		form.querySelector( '[name=url]' ).value = m.url;
		form.querySelector( '[name=method]' ).value = m.method;
		form.querySelector( '[name=expected_code]' ).value = m.expected_code;
		form.querySelector( '[name=keyword]' ).value = m.keyword || '';
		form.querySelector( '[name=interval_minutes]' ).value = m.interval_minutes;
		form.querySelector( '[name=timeout_seconds]' ).value = m.timeout_seconds;
		form.querySelector( '[name=notify_email]' ).value = m.notify_email || '';
		form.querySelector( '[name=enabled]' ).checked = !! m.enabled;
		showForm( true );
	}

	function checkNow( id ) {
		notify( __( 'Checking…', 'seoistic' ), false );
		apiFetch( { path: '/seoistic/v1/uptime/monitors/' + id + '/check', method: 'POST' } ).then( function () {
			notify( __( 'Check complete.', 'seoistic' ), false );
			load();
		} ).catch( function ( err ) {
			notify( ( err && err.message ) || __( 'Check failed.', 'seoistic' ), true );
		} );
	}

	function deleteMonitor( m ) {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( __( 'Delete this monitor and its history?', 'seoistic' ) + '\n' + m.label ) ) {
			return;
		}
		apiFetch( { path: '/seoistic/v1/uptime/monitors/' + m.id, method: 'DELETE' } ).then( function () {
			notify( __( 'Monitor deleted.', 'seoistic' ), false );
			load();
		} ).catch( function ( err ) {
			notify( ( err && err.message ) || __( 'Delete failed.', 'seoistic' ), true );
		} );
	}

	form.addEventListener( 'submit', function ( e ) {
		e.preventDefault();
		var data = formData();
		var id = data.id;
		var path = id ? '/seoistic/v1/uptime/monitors/' + id : '/seoistic/v1/uptime/monitors';
		var method = id ? 'PUT' : 'POST';
		apiFetch( { path: path, method: method, data: data } ).then( function () {
			notify( __( 'Monitor saved.', 'seoistic' ), false );
			resetForm();
			showForm( false );
			load();
		} ).catch( function ( err ) {
			notify( ( err && err.message ) || __( 'Save failed.', 'seoistic' ), true );
		} );
	} );

	if ( addBtn ) {
		addBtn.addEventListener( 'click', function () { resetForm(); showForm( true ); } );
	}
	if ( cancelBtn ) {
		cancelBtn.addEventListener( 'click', function () { showForm( false ); } );
	}

	load();
}( window.wp ) );
