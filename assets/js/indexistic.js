/**
 * SEOISTIC → Indexistic console: bulk URL submission to Google's Indexing API
 * and the IndexNow protocol, plus a one-off Google status check. Talks to
 * admin-ajax (not the REST API) via window.SeoisticIndexistic.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		initConsoleSubmit();
		initCheckStatus();
	} );

	function ajaxPost( action, extra ) {
		if ( ! window.SeoisticIndexistic || ! window.SeoisticIndexistic.ajaxUrl ) {
			return Promise.reject( new Error( 'Indexistic config missing' ) );
		}
		var body = new URLSearchParams();
		body.set( 'action', action );
		body.set( 'nonce', window.SeoisticIndexistic.consoleNonce );
		Object.keys( extra || {} ).forEach( function ( key ) {
			var value = extra[ key ];
			if ( Array.isArray( value ) ) {
				value.forEach( function ( item ) {
					body.append( key + '[]', item );
				} );
			} else {
				body.set( key, value );
			}
		} );

		return fetch( window.SeoisticIndexistic.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		} ).then( function ( r ) {
			return r.json();
		} );
	}

	function getUrls() {
		var textarea = document.getElementById( 'seoistic-indexistic-urls' );
		if ( ! textarea ) {
			return [];
		}
		return textarea.value
			.split( '\n' )
			.map( function ( line ) {
				return line.trim();
			} )
			.filter( function ( line ) {
				return '' !== line;
			} );
	}

	function selectedEngine() {
		var checked = document.querySelector( 'input[name="seoistic_indexistic_engine"]:checked' );
		return checked ? checked.value : 'indexnow';
	}

	function showResult( success, message ) {
		var box = document.getElementById( 'seoistic-indexistic-result' );
		if ( ! box ) {
			return;
		}
		box.style.display = 'block';
		box.className = 'seoistic-tool-result ' + ( success ? 'is-success' : 'is-error' );
		box.textContent = message;
	}

	/* ---------------------------------------------------------------- */
	/* Bulk submit                                                        */
	/* ---------------------------------------------------------------- */
	function initConsoleSubmit() {
		var btn = document.getElementById( 'seoistic-indexistic-submit' );
		if ( ! btn ) {
			return;
		}
		btn.addEventListener( 'click', function () {
			var urls = getUrls();
			if ( ! urls.length ) {
				showResult( false, 'Enter at least one URL.' );
				return;
			}

			var progressWrap = document.getElementById( 'seoistic-indexistic-progress' );
			var progressBar = progressWrap ? progressWrap.querySelector( '.seoistic-tool-progress-bar' ) : null;
			btn.disabled = true;
			if ( progressWrap ) {
				progressWrap.style.display = 'block';
			}
			if ( progressBar ) {
				progressBar.style.width = '40%';
			}

			ajaxPost( 'seoistic_indexistic_console_submit', { engine: selectedEngine(), urls: urls } )
				.then( function ( json ) {
					if ( progressBar ) {
						progressBar.style.width = '100%';
					}
					var message = json && json.data && json.data.message
						? json.data.message
						: ( json && json.success ? 'Done.' : 'Submission failed.' );
					showResult( Boolean( json && json.success ), message );
				} )
				.catch( function () {
					showResult( false, 'Submission failed.' );
				} )
				.finally( function () {
					btn.disabled = false;
				} );
		} );
	}

	/* ---------------------------------------------------------------- */
	/* Check Google status (first URL in the textarea)                    */
	/* ---------------------------------------------------------------- */
	function initCheckStatus() {
		var btn = document.getElementById( 'seoistic-indexistic-check-status' );
		if ( ! btn ) {
			return;
		}
		btn.addEventListener( 'click', function () {
			var urls = getUrls();
			if ( ! urls.length ) {
				showResult( false, 'Enter a URL to check.' );
				return;
			}

			btn.disabled = true;
			ajaxPost( 'seoistic_indexistic_check_status', { url: urls[ 0 ] } )
				.then( function ( json ) {
					if ( ! json || ! json.success ) {
						showResult( false, ( json && json.data && json.data.message ) || 'Status check failed.' );
						return;
					}
					showResult( true, formatStatus( urls[ 0 ], json.data && json.data.data ) );
				} )
				.catch( function () {
					showResult( false, 'Status check failed.' );
				} )
				.finally( function () {
					btn.disabled = false;
				} );
		} );
	}

	function formatStatus( url, data ) {
		if ( ! data ) {
			return url + ' — no indexing record found yet.';
		}
		var lines = [ url ];
		if ( data.latestUpdate && data.latestUpdate.notifyTime ) {
			lines.push( 'Last submitted (update): ' + data.latestUpdate.notifyTime );
		}
		if ( data.latestRemove && data.latestRemove.notifyTime ) {
			lines.push( 'Last submitted (removal): ' + data.latestRemove.notifyTime );
		}
		if ( 1 === lines.length ) {
			lines.push( 'No indexing record found yet.' );
		}
		return lines.join( '\n' );
	}
} )();
