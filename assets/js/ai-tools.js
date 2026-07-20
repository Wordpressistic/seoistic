/**
 * SEOISTIC → AI Tools page: generator cards (robots/.htaccess/llms.txt), bulk AI
 * actions (meta/alt/internal-links/AEO), sitemap ping, and the audit report. Uses
 * the shared window.seoisticRestPost helper defined in admin.js.
 */
( function () {
	'use strict';

	var GENERATE_ENDPOINTS = {
		robots: { path: '/ai/generate-robots', field: 'robots_txt', apply: '/tools/apply-robots' },
		htaccess: { path: '/ai/generate-htaccess', field: 'htaccess', apply: '/tools/apply-htaccess', warn: true },
		llms: { path: '/ai/generate-llms', field: 'llms_txt', apply: '/tools/apply-llms' },
	};

	document.addEventListener( 'DOMContentLoaded', function () {
		initGenerators();
		initBulkActions();
		initSitemapPing();
		initAuditReport();
	} );

	function restPost( path, body ) {
		return window.seoisticRestPost( path, body || {} );
	}

	/* ---------------------------------------------------------------- */
	/* Modal helpers                                                      */
	/* ---------------------------------------------------------------- */
	function openModal( title, content, reason, warnings ) {
		document.getElementById( 'seoistic-tools-modal-title' ).textContent = title;
		document.getElementById( 'seoistic-tools-modal-content' ).value = content;
		document.getElementById( 'seoistic-tools-modal-reason' ).textContent = reason || '';

		var warningBox = document.getElementById( 'seoistic-tools-modal-warning' );
		if ( warnings && warnings.length ) {
			warningBox.style.display = 'block';
			warningBox.textContent = warnings.join( ' ' );
		} else {
			warningBox.style.display = 'none';
		}
		window.seoisticOpenModal( 'seoistic-tools-modal' );
	}

	/* ---------------------------------------------------------------- */
	/* Generator cards: robots.txt / .htaccess / llms.txt                 */
	/* ---------------------------------------------------------------- */
	function initGenerators() {
		document.querySelectorAll( '[data-seoistic-generate]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var type = btn.getAttribute( 'data-seoistic-generate' );
				var config = GENERATE_ENDPOINTS[ type ];
				if ( ! config ) {
					return;
				}
				var original = btn.innerHTML;
				btn.disabled = true;
				btn.innerHTML = '<span class="dashicons dashicons-update"></span> ' + 'Generating…';

				restPost( config.path, {} )
					.then( function ( json ) {
						var data = json.data || {};
						openModal( btn.textContent.trim(), data[ config.field ] || '', data.reason || '', data.warnings || [] );
						bindApply( config.apply, type );
					} )
					.catch( function ( err ) {
						window.alert( err.message || 'Generation failed.' ); // eslint-disable-line no-alert
					} )
					.finally( function () {
						btn.disabled = false;
						btn.innerHTML = original;
					} );
			} );
		} );
	}

	function bindApply( applyPath, type ) {
		var applyBtn = document.getElementById( 'seoistic-tools-modal-apply' );
		var downloadBtn = document.getElementById( 'seoistic-tools-modal-download' );
		var textarea = document.getElementById( 'seoistic-tools-modal-content' );

		applyBtn.style.display = '';
		applyBtn.onclick = function () {
			var content = textarea.value;
			if ( 'htaccess' === type && ! window.confirm( 'This will modify your .htaccess file. A backup will be created first. Continue?' ) ) { // eslint-disable-line no-alert
				return;
			}
			applyBtn.disabled = true;
			restPost( applyPath, { content: content } )
				.then( function ( json ) {
					var msg = 'Applied.';
					if ( json.data && json.data.backup ) {
						msg = 'Applied — backup saved as ' + json.data.backup;
					}
					window.alert( msg ); // eslint-disable-line no-alert
					window.seoisticCloseModal( 'seoistic-tools-modal' );
				} )
				.catch( function ( err ) {
					window.alert( err.message || 'Apply failed.' ); // eslint-disable-line no-alert
				} )
				.finally( function () {
					applyBtn.disabled = false;
				} );
		};

		downloadBtn.onclick = function () {
			downloadText( type + '.txt', textarea.value );
		};
	}

	function downloadText( filename, content ) {
		var blob = new Blob( [ content ], { type: 'text/plain' } );
		var url = URL.createObjectURL( blob );
		var a = document.createElement( 'a' );
		a.href = url;
		a.download = filename;
		document.body.appendChild( a );
		a.click();
		document.body.removeChild( a );
		URL.revokeObjectURL( url );
	}

	/* ---------------------------------------------------------------- */
	/* Bulk AI actions                                                    */
	/* ---------------------------------------------------------------- */
	function initBulkActions() {
		document.querySelectorAll( '[data-seoistic-bulk]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				runBulk( btn, btn.getAttribute( 'data-seoistic-bulk' ), 0 );
			} );
		} );
	}

	function runBulk( btn, tool, offset ) {
		var card = btn.closest( '.seoistic-tool-card' );
		var progressWrap = card ? card.querySelector( '.seoistic-tool-progress' ) : null;
		var progressBar = card ? card.querySelector( '.seoistic-tool-progress-bar' ) : null;
		var resultBox = card ? card.querySelector( '.seoistic-tool-result' ) : null;

		btn.disabled = true;
		if ( progressWrap ) {
			progressWrap.style.display = 'block';
		}

		restPost( '/tools/' + tool, { offset: offset } )
			.then( function ( json ) {
				if ( progressBar ) {
					progressBar.style.width = Math.round( json.percent || 0 ) + '%';
				}
				if ( json.done ) {
					btn.disabled = false;
					showResult( resultBox, true, formatBulkDone( json ) );
					if ( json.report && json.report.length ) {
						showReportModal( json.report );
					}
				} else {
					runBulk( btn, tool, json.next_offset );
				}
			} )
			.catch( function ( err ) {
				btn.disabled = false;
				showResult( resultBox, false, err.message || 'Failed.' );
			} );
	}

	function formatBulkDone( json ) {
		if ( json.report && json.report.length ) {
			return json.report.length + ' pages analyzed — see the report.';
		}
		if ( 'undefined' !== typeof json.updated ) {
			return json.processed + ' processed, ' + json.updated + ' updated.';
		}
		return json.processed + ' processed.';
	}

	function showReportModal( report ) {
		var lines = [];
		report.forEach( function ( item ) {
			lines.push( '# ' + item.title + ' (post #' + item.post_id + ')' );
			( item.suggestions || [] ).forEach( function ( suggestion ) {
				lines.push( '- ' + ( 'string' === typeof suggestion ? suggestion : ( suggestion.anchor_text || '' ) + ( suggestion.reason ? ': ' + suggestion.reason : '' ) ) );
			} );
			lines.push( '' );
		} );
		openModal( 'AI Suggestions', lines.join( '\n' ), report.length + ' pages analyzed.', [] );
		document.getElementById( 'seoistic-tools-modal-apply' ).style.display = 'none';
		document.getElementById( 'seoistic-tools-modal-download' ).onclick = function () {
			downloadText( 'seoistic-suggestions.txt', lines.join( '\n' ) );
		};
	}

	function showResult( box, success, message ) {
		if ( ! box ) {
			return;
		}
		box.style.display = 'block';
		box.className = 'seoistic-tool-result ' + ( success ? 'is-success' : 'is-error' );
		box.textContent = message;
	}

	/* ---------------------------------------------------------------- */
	/* Sitemap ping                                                       */
	/* ---------------------------------------------------------------- */
	function initSitemapPing() {
		var btn = document.querySelector( '[data-seoistic-ping-sitemap]' );
		if ( ! btn ) {
			return;
		}
		btn.addEventListener( 'click', function () {
			var card = btn.closest( '.seoistic-tool-card' );
			var resultBox = card ? card.querySelector( '.seoistic-tool-result' ) : null;
			btn.disabled = true;
			restPost( '/tools/ping-sitemap', {} )
				.then( function ( json ) {
					showResult( resultBox, json.success, json.success ? 'Sitemap pinged successfully.' : ( json.error || 'Ping failed.' ) );
				} )
				.catch( function ( err ) {
					showResult( resultBox, false, err.message || 'Ping failed.' );
				} )
				.finally( function () {
					btn.disabled = false;
				} );
		} );
	}

	/* ---------------------------------------------------------------- */
	/* SEO audit report                                                   */
	/* ---------------------------------------------------------------- */
	function initAuditReport() {
		var btn = document.querySelector( '[data-seoistic-audit-report]' );
		if ( ! btn ) {
			return;
		}
		btn.addEventListener( 'click', function () {
			btn.disabled = true;
			restPost( '/tools/audit-report', {} )
				.then( function ( json ) {
					var data = json.data || {};
					var lines = [ 'SEOISTIC audit report — ' + data.generated_at, '' ];
					( data.worst_pages || [] ).forEach( function ( page ) {
						lines.push( page.score + '/100 — ' + page.title + ' (post #' + page.id + ')' );
					} );
					openModal( 'SEO Audit Report', lines.join( '\n' ), 'Your lowest-scoring published pages.', [] );
					document.getElementById( 'seoistic-tools-modal-apply' ).style.display = 'none';
					document.getElementById( 'seoistic-tools-modal-download' ).onclick = function () {
						downloadText( 'seoistic-audit-report.txt', lines.join( '\n' ) );
					};
				} )
				.catch( function ( err ) {
					window.alert( err.message || 'Report failed.' ); // eslint-disable-line no-alert
				} )
				.finally( function () {
					btn.disabled = false;
				} );
		} );
	}
} )();
