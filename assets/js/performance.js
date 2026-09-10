( function () {
	'use strict';

	var strings = window.SeoisticPerformance && window.SeoisticPerformance.i18n ? window.SeoisticPerformance.i18n : {};
	var previewTokens = {};

	document.addEventListener( 'DOMContentLoaded', function () {
		initPsi();
		initQuickWins();
	} );

	function initPsi() {
		document.querySelectorAll( '[data-seoistic-psi-run]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var url = document.getElementById( 'seoistic-psi-url' );
				var strategy = document.getElementById( 'seoistic-psi-strategy' );
				runRest( button, '/performance/psi', {
					url: url ? url.value.trim() : '',
					strategy: strategy ? strategy.value : 'mobile',
				} );
			} );
		} );
	}

	function initQuickWins() {
		document.querySelectorAll( '[data-seoistic-quick-win-preview]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				runRest( button, '/performance/quick-wins/preview', { action: getAction( button, 'data-seoistic-quick-win-preview' ) }, true );
			} );
		} );
		document.querySelectorAll( '[data-seoistic-quick-win-apply]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				runRest( button, '/performance/quick-wins/apply', {
					action: getAction( button, 'data-seoistic-quick-win-apply' ),
					confirmed: true,
					preview_token: getPreviewToken( button ),
				}, true );
			} );
		} );
		document.querySelectorAll( '[data-seoistic-quick-win-disable]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				runRest( button, '/performance/quick-wins/disable', { action: getAction( button, 'data-seoistic-quick-win-disable' ), confirmed: true }, true );
			} );
		} );
	}

	function runRest( button, path, body, reload ) {
		var result = button.closest( '.seoistic-tool-card' ).querySelector( '[data-seoistic-psi-result], [data-seoistic-quick-win-result]' );
		var original = button.textContent;
		button.disabled = true;
		button.textContent = path.indexOf( '/preview' ) > -1 ? strings.previewing || 'Building preview…' : strings.running || 'Checking…';
		if ( result ) {
			result.hidden = false;
			result.className = 'seoistic-tool-result';
			result.textContent = strings.running || 'Checking…';
		}
		window.seoisticRestPost( path, body ).then( function ( json ) {
			var data = json.data || {};
			if ( data.preview_token ) {
				previewTokens[ body.action ] = data.preview_token;
			}
			if ( result ) {
				result.className = 'seoistic-tool-result is-success';
				result.textContent = data.message || 'Completed.';
			}
			if ( reload ) {
				window.location.reload();
			}
		} ).catch( function ( error ) {
			if ( result ) {
				result.className = 'seoistic-tool-result is-error';
				result.textContent = error.message || 'Request failed.';
			}
		} ).finally( function () {
			button.disabled = false;
			button.textContent = original;
		} );
	}

	function getPreviewToken( button ) {
		return previewTokens[ getAction( button, 'data-seoistic-quick-win-apply' ) ] || '';
	}

	function getAction( button, attribute ) {
		return button.getAttribute( attribute ) || '';
	}
} )();
