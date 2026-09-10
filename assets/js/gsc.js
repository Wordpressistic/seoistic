/**
 * SEOISTIC → Search Console: URL Inspection and connection health checks.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		initInspection();
		initHealthCheck();
	} );

	function initInspection() {
		var btn = document.getElementById( 'seoistic-gsc-inspect-btn' );
		if ( ! btn ) {
			return;
		}
		btn.addEventListener( 'click', function () {
			var input = document.getElementById( 'seoistic-gsc-inspect-url' );
			var resultBox = document.getElementById( 'seoistic-gsc-inspect-result' );
			var url = input ? input.value.trim() : '';
			if ( '' === url || ! window.SeoisticGsc || ! window.SeoisticGsc.ajaxUrl ) {
				return;
			}

			btn.disabled = true;
			var body = new URLSearchParams();
			body.set( 'action', 'seoistic_gsc_inspect_url' );
			body.set( 'nonce', window.SeoisticGsc.nonce );
			body.set( 'url', url );

			fetch( window.SeoisticGsc.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString(),
			} )
				.then( function ( r ) {
					return r.json();
				} )
				.then( function ( json ) {
					showResult( resultBox, json && json.success, gscI18n( 'inspectionFailed' ), json && json.data && json.data.data );
				} )
				.catch( function () {
					showResult( resultBox, false, gscI18n( 'inspectionFailed' ) );
				} )
				.finally( function () {
					btn.disabled = false;
				} );
		} );
	}

	function initHealthCheck() {
		var btn = document.getElementById( 'seoistic-gsc-health-btn' );
		if ( ! btn ) {
			return;
		}
		btn.addEventListener( 'click', function () {
			var resultBox = document.getElementById( 'seoistic-gsc-health-result' );
			if ( ! window.SeoisticGsc || ! window.SeoisticGsc.ajaxUrl || ! window.SeoisticGsc.healthNonce ) {
				return;
			}

			btn.disabled = true;
			var body = new URLSearchParams();
			body.set( 'action', 'seoistic_gsc_test_connection' );
			body.set( 'nonce', window.SeoisticGsc.healthNonce );

			fetch( window.SeoisticGsc.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString(),
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( json ) {
					if ( json && json.success ) {
						var data = json.data || {};
						showResult( resultBox, true, data.property_match ? gscI18n( 'healthy' ) : gscI18n( 'propertyMismatch' ) );
						return;
					}

					var failure = ( json && json.data ) || {};
					if ( failure.access_denied ) {
						showResult( resultBox, false, gscI18n( 'accessDenied' ) );
						return;
					}
					showResult( resultBox, false, failure.message || gscI18n( 'connectionFailed' ) );
				} )
				.catch( function () {
					showResult( resultBox, false, gscI18n( 'connectionFailed' ) );
				} )
				.finally( function () {
					btn.disabled = false;
				} );
		} );
	}

	function showResult( resultBox, success, message, data ) {
		if ( ! resultBox ) {
			return;
		}
		resultBox.style.display = 'block';
		resultBox.className = 'seoistic-tool-result ' + ( success ? 'is-success' : 'is-error' );
		resultBox.textContent = success && data ? formatInspection( data ) : message;
	}

	function gscI18n( key ) {
		return ( window.SeoisticGsc && window.SeoisticGsc.i18n && window.SeoisticGsc.i18n[ key ] ) || '';
	}

	function formatInspection( data ) {
		if ( ! data || ! data.indexStatusResult ) {
			return gscI18n( 'noInspectionData' );
		}
		var idx = data.indexStatusResult;
		var lines = [
			gscI18n( 'verdict' ) + ' ' + ( idx.verdict || gscI18n( 'unknown' ) ),
			gscI18n( 'coverage' ) + ' ' + ( idx.coverageState || gscI18n( 'unknown' ) ),
		];
		if ( idx.lastCrawlTime ) {
			lines.push( gscI18n( 'lastCrawled' ) + ' ' + idx.lastCrawlTime );
		}
		if ( idx.robotsTxtState ) {
			lines.push( gscI18n( 'robotsTxt' ) + ' ' + idx.robotsTxtState );
		}
		return lines.join( '\n' );
	}
} )();
