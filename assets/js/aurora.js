/**
 * Aurora interaction layer: animated scores, workflow trackers, skeletons,
 * tabs, and toast orchestration. Dependencies are optional for addon scripts.
 */
( function () {
	'use strict';

	var motionQuery = window.matchMedia ? window.matchMedia( '(prefers-reduced-motion: reduce)' ) : null;
	var reducedMotion = motionQuery ? motionQuery.matches : false;

	function safeNumber( value, fallback ) {
		var number = Number( value );
		return isNaN( number ) ? fallback : number;
	}

	function clampScore( score ) {
		return Math.max( 0, Math.min( 100, safeNumber( score, 0 ) ) );
	}

	function easeOutCubic( progress ) {
		return 1 - Math.pow( 1 - progress, 3 );
	}

	function addMarkup( parent, className, html ) {
		var element = document.createElement( 'div' );
		element.className = className;
		element.innerHTML = html;
		parent.appendChild( element );
		return element;
	}

	function animateNumber( element, from, to, duration ) {
		var start = null;
		var finish = function () {
			element.textContent = String( Math.round( to ) );
		};

		if ( reducedMotion || duration <= 0 ) {
			finish();
			return;
		}

		function step( timestamp ) {
			if ( null === start ) {
				start = timestamp;
			}
			var progress = Math.min( 1, ( timestamp - start ) / duration );
			element.textContent = String( Math.round( from + ( to - from ) * easeOutCubic( progress ) ) );
			if ( progress < 1 ) {
				window.requestAnimationFrame( step );
			} else {
				finish();
			}
		}
		window.requestAnimationFrame( step );
	}

	window.auroraAnimateScore = function( ring, newScore, options ) {
		if ( ! ring ) {
			return;
		}

		options = options || {};
		var oldScore = clampScore( ring.getAttribute( 'data-aurora-score' ) || options.from );
		var target = clampScore( newScore );
		var fill = ring.querySelector( '.seoistic-ring-fill' );
		var label = ring.querySelector( '.seoistic-ring-label' );
		var oldLabel = ring.querySelector( '.aurora-score-old' );
		var circumference = safeNumber( fill && fill.getAttribute( 'r' ), 0 ) * 2 * Math.PI;

		if ( circumference ) {
			if ( reducedMotion ) {
				fill.style.strokeDashoffset = String( circumference * ( 1 - target / 100 ) );
			} else {
				fill.style.strokeDashoffset = String( circumference * ( 1 - oldScore / 100 ) );
				window.requestAnimationFrame( function() {
					fill.style.strokeDashoffset = String( circumference * ( 1 - target / 100 ) );
				} );
			}
		}

		if ( ! oldLabel && oldScore !== target ) {
			oldLabel = document.createElement( 'span' );
			oldLabel.className = 'aurora-score-old';
			ring.appendChild( oldLabel );
		}
		if ( oldLabel ) {
			oldLabel.textContent = oldScore !== target ? String( oldScore ) : '';
		}

		ring.classList.toggle( 'is-animating', ! reducedMotion );
		if ( label ) {
			animateNumber( label, oldScore, target, reducedMotion ? 0 : 380 );
		}
		window.setTimeout( function() {
			ring.classList.remove( 'is-animating' );
			if ( oldLabel ) {
				oldLabel.textContent = '';
			}
		}, reducedMotion ? 0 : 400 );
		ring.setAttribute( 'data-aurora-score', String( target ) );
	};

	window.auroraInitScores = function() {
		document.querySelectorAll( '.seoistic-ring' ).forEach( function( ring ) {
			var fill = ring.querySelector( '.seoistic-ring-fill' );
			var label = ring.querySelector( '.seoistic-ring-label' );
			if ( ! fill || ! label ) {
				return;
			}
			var circumference = safeNumber( fill.getAttribute( 'r' ), 0 ) * 2 * Math.PI;
			var score = clampScore( parseFloat( label.textContent ) );
			if ( circumference && ! reducedMotion ) {
				fill.style.strokeDashoffset = String( circumference );
				window.requestAnimationFrame( function() {
					fill.style.strokeDashoffset = String( circumference * ( 1 - score / 100 ) );
				} );
				animateNumber( label, 0, score, 380 );
			} else {
				fill.style.strokeDashoffset = String( circumference * ( 1 - score / 100 ) );
			}
			ring.setAttribute( 'data-aurora-score', String( score ) );
		} );
	};

	function stepMarkup( key, label ) {
		return '<span class="aurora-step" data-step="' + key + '"><span class="dashicons dashicons-clock" aria-hidden="true"></span><span>' + label + '</span></span>';
	}

	window.auroraTracker = function( container, options ) {
		options = options || {};
		if ( ! container || container.auroraTracker ) {
			return container && container.auroraTracker;
		}

		var queued = options.queued || 'Queued';
		var progress = options.progress || 'Processing';
		var done = options.done || 'Done';
		var tracker = document.createElement( 'div' );
		tracker.className = 'aurora-tracker';
		tracker.setAttribute( 'role', 'status' );
		tracker.setAttribute( 'aria-live', 'polite' );
		tracker.innerHTML = stepMarkup( 'queued', queued ) + stepMarkup( 'progress', progress ) + stepMarkup( 'done', done ) +
			'<p class="aurora-step-detail" aria-hidden="true"></p><div class="aurora-mini-progress"><div class="aurora-mini-progress-bar"></div></div><p class="aurora-tracker-summary"></p>';
		container.appendChild( tracker );

		var api = {
			start: function() {
				tracker.className = 'aurora-tracker is-active';
				tracker.querySelector( '.aurora-tracker-summary' ).textContent = '';
				this.state( 'queued' );
				tracker.querySelector( '.aurora-mini-progress-bar' ).style.width = '0%';
				tracker.querySelector( '.aurora-step-detail' ).textContent = options.queuedMessage || queued;
				return this;
			},
			progress: function( current, total, detail ) {
				var value = total > 0 ? Math.max( 0, Math.min( 100, Math.round( current / total * 100 ) ) ) : 0;
				tracker.querySelector( '.aurora-mini-progress-bar' ).style.width = value + '%';
				tracker.querySelector( '.aurora-step-detail' ).textContent = detail || ( current + ' / ' + total );
				return this;
			},
			state: function( state ) {
				tracker.querySelectorAll( '.aurora-step' ).forEach( function( step ) {
					var key = step.getAttribute( 'data-step' );
					var isActive = key === state;
					var isDone = ( 'progress' === key && 'done' === state ) || ( 'queued' === key && 'progress' === state ) || ( 'queued' === key && 'done' === state );
					step.classList.toggle( 'is-active', isActive );
					step.classList.toggle( 'is-done', isDone );
					step.querySelector( '.dashicons' ).className = 'dashicons ' + ( isDone ? 'dashicons-yes-alt' : ( isActive ? 'dashicons-update' : 'dashicons-clock' ) );
				} );
				return this;
			},
			finish: function( summary ) {
				this.state( 'done' );
				tracker.classList.add( 'is-done' );
				tracker.querySelector( '.aurora-mini-progress-bar' ).style.width = '100%';
				tracker.querySelector( '.aurora-tracker-summary' ).textContent = summary || done;
				return this;
			},
			fail: function( message ) {
				tracker.classList.remove( 'is-active' );
				if ( message && window.seoisticToast ) {
					window.seoisticToast( message, 'error' );
				}
				return this;
			},
			element: tracker
		};

		container.auroraTracker = api;
		return api;
	};

	window.auroraSkeletons = function( container, show ) {
		if ( ! container ) {
			return;
		}
		var skeleton = container.querySelector( '.aurora-skeleton-card' );
		container.setAttribute( 'data-aurora-loading', show ? 'true' : 'false' );
		if ( show && ! skeleton ) {
			addMarkup( container, 'aurora-skeleton-card', '<div class="aurora-skeleton-line is-heading"></div><div class="aurora-skeleton-line"></div><div class="aurora-skeleton-line"></div>' ).setAttribute( 'aria-hidden', 'true' );
		} else if ( skeleton ) {
			skeleton.hidden = ! show;
		}
	};

	window.auroraInitChecklist = function() {
		document.querySelectorAll( '.seoistic-audit-item' ).forEach( function( item ) {
			if ( reducedMotion ) {
				item.classList.add( 'is-aurora-visible' );
				return;
			}
			if ( item.dataset.auroraTimer ) {
				return;
			}
			item.dataset.auroraTimer = 'true';
			window.setTimeout( function() {
				item.classList.add( 'is-aurora-visible' );
			}, 40 );
		} );
	};

	window.auroraInitTabs = function() {
		document.querySelectorAll( '[data-seoistic-tab]' ).forEach( function( button ) {
			if ( button.dataset.auroraReady ) {
				return;
			}
			button.dataset.auroraReady = 'true';
			button.addEventListener( 'click', function() {
				var scope = button.closest( '.seoistic-panel' ) || document;
				var key = button.getAttribute( 'data-seoistic-tab' );
				scope.querySelectorAll( '[data-seoistic-tab]' ).forEach( function( tab ) {
					tab.classList.toggle( 'is-active', tab === button );
					tab.setAttribute( 'aria-selected', tab === button ? 'true' : 'false' );
				} );
				scope.querySelectorAll( '.seoistic-panel-pane' ).forEach( function( pane ) {
					pane.classList.toggle( 'is-active', pane.getAttribute( 'data-seoistic-pane' ) === key );
				} );
			} );
		} );
	};

	function initTheme() {
		var body = document.body;
		if ( ! body.classList.contains( 'wp-admin' ) ) {
			return;
		}
		function apply() {
			var style = window.getComputedStyle( body );
			var background = style.backgroundColor;
			var luminance = 255;
			var match = background.match( /rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([.\d]+))?\)/ );
			if ( match ) {
				luminance = 0.2126 * Number( match[ 1 ] ) + 0.7152 * Number( match[ 2 ] ) + 0.0722 * Number( match[ 3 ] );
				if ( match[ 4 ] !== undefined && Number( match[ 4 ] ) < 0.5 ) {
					luminance = 255;
				}
			}
			body.classList.toggle( 'seoistic-admin-scheme-dark', luminance < 128 );
		}
		apply();
	}

	function initLegacyNotices() {
		document.querySelectorAll( '[data-aurora-toast]' ).forEach( function( notice ) {
			if ( notice.dataset.auroraReady ) {
				return;
			}
			notice.dataset.auroraReady = 'true';
			if ( window.seoisticToast ) {
				window.seoisticToast( notice.getAttribute( 'data-aurora-toast-message' ) || notice.textContent.trim(), notice.getAttribute( 'data-aurora-toast' ) === 'error' ? 'error' : 'success' );
			}
			notice.hidden = true;
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function() {
		window.auroraInitScores();
		window.auroraInitChecklist();
		window.auroraInitTabs();
		initLegacyNotices();
		initTheme();
	} );

	if ( motionQuery && motionQuery.addEventListener ) {
		motionQuery.addEventListener( 'change', function( event ) {
			reducedMotion = event.matches;
			if ( reducedMotion ) {
				window.auroraInitChecklist();
			}
		} );
	}
}() );
