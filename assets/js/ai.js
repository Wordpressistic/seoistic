/**
 * SEOISTIC — post-edit-screen SEO workspace behaviour: tabs, live search
 * preview, character counters, debounced live analysis against the /analyze
 * endpoint (deterministic, server-side, never persists), and AI actions with a
 * before/after suggestion flow (Apply / Dismiss / Undo). Provider keys never
 * reach this file — every call goes through seoistic/v1 with the REST nonce.
 */
( function () {
	'use strict';

	var restPost = window.seoisticRestPost;
	var ANALYZE_DEBOUNCE = 900;
	var CONTENT_POLL_MS = 1500;

	var analyzeTimer = null;
	var analyzeController = null;
	var lastContent = null;

	document.addEventListener( 'DOMContentLoaded', function () {
		var panel = document.getElementById( 'seoistic-panel' );
		if ( ! panel ) {
			return;
		}
		initTabs( panel );
		initCounters( panel );
		initLivePreview( panel );
		initSocialPreview( panel );
		initPreviewDeviceToggle( panel );
		initAiButtons( panel );
		initFixWithAi( panel );
		initRunPostAudit( panel );
		initOpenAuditTab( panel );
		initLiveAnalysis( panel );
	} );

	function i18n( key, fallback ) {
		if ( window.SeoisticEditor && window.SeoisticEditor.i18n && window.SeoisticEditor.i18n[ key ] ) {
			return window.SeoisticEditor.i18n[ key ];
		}
		return fallback;
	}

	/* ---------------------------------------------------------------- */
	function initTabs( panel ) {
		var tabs = panel.querySelectorAll( '.seoistic-panel-tab' );
		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				tabs.forEach( function ( t ) {
					t.classList.remove( 'is-active' );
				} );
				tab.classList.add( 'is-active' );
				var key = tab.getAttribute( 'data-seoistic-tab' );
				panel.querySelectorAll( '.seoistic-panel-pane' ).forEach( function ( pane ) {
					pane.classList.toggle( 'is-active', pane.getAttribute( 'data-seoistic-pane' ) === key );
				} );
			} );
		} );
	}

	/* ---------------------------------------------------------------- */
	function initCounters( panel ) {
		panel.querySelectorAll( '[data-seoistic-counter]' ).forEach( function ( field ) {
			var update = function () {
				updateCounter( field );
			};
			field.addEventListener( 'input', update );
			update();
		} );
	}

	function updateCounter( field ) {
		var name = field.getAttribute( 'name' );
		var min = parseInt( field.getAttribute( 'data-min' ), 10 ) || 0;
		var max = parseInt( field.getAttribute( 'data-max' ), 10 ) || 0;
		var len = field.value.length;
		var countEl = document.querySelector( '[data-seoistic-counter-for="' + name + '"]' );
		var barEl = document.querySelector( '[data-seoistic-bar-for="' + name + '"]' );

		var state = 'is-warn';
		if ( len === 0 ) {
			state = '';
		} else if ( len >= min && len <= max ) {
			state = 'is-good';
		} else if ( len > max ) {
			state = 'is-over';
		}

		if ( countEl ) {
			countEl.textContent = len + ' / ' + min + '–' + max;
			countEl.className = 'seoistic-char-count ' + state;
		}
		if ( barEl ) {
			var pct = max > 0 ? Math.min( 100, ( len / max ) * 100 ) : 0;
			barEl.style.width = pct + '%';
			barEl.style.background = 'is-over' === state ? 'var(--seo-danger)' : ( 'is-good' === state ? 'var(--seo-success)' : 'var(--seo-warning)' );
		}
	}

	/* ---------------------------------------------------------------- */
	function initLivePreview( panel ) {
		var titleField = panel.querySelector( '#seoistic_title' );
		var descField = panel.querySelector( '#seoistic_description' );
		var previewTitle = document.getElementById( 'seoistic-preview-title' );
		var previewDesc = document.getElementById( 'seoistic-preview-desc' );
		var postTitleField = document.getElementById( 'title' );

		if ( titleField && previewTitle ) {
			titleField.addEventListener( 'input', function () {
				previewTitle.textContent = titleField.value || ( postTitleField ? postTitleField.value : '' );
			} );
		}
		if ( descField && previewDesc ) {
			descField.addEventListener( 'input', function () {
				previewDesc.textContent = descField.value;
			} );
		}
	}

	function initPreviewDeviceToggle( panel ) {
		var buttons = panel.querySelectorAll( '[data-seoistic-preview-device]' );
		var preview = document.getElementById( 'seoistic-google-preview' );
		buttons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				buttons.forEach( function ( b ) {
					b.classList.remove( 'is-active' );
				} );
				btn.classList.add( 'is-active' );
				if ( preview ) {
					preview.classList.toggle( 'is-mobile', 'mobile' === btn.getAttribute( 'data-seoistic-preview-device' ) );
				}
			} );
		} );
	}

	function initSocialPreview( panel ) {
		var ogTitle = panel.querySelector( '#seoistic_og_title' );
		var ogDesc = panel.querySelector( '#seoistic_og_description' );
		var ogImage = panel.querySelector( '#seoistic_og_image' );
		var titlePreview = document.getElementById( 'seoistic-social-title-preview' );
		var descPreview = document.getElementById( 'seoistic-social-desc-preview' );
		var imagePreview = document.getElementById( 'seoistic-social-image' );

		if ( ogTitle && titlePreview ) {
			ogTitle.addEventListener( 'input', function () {
				titlePreview.textContent = ogTitle.value;
			} );
		}
		if ( ogDesc && descPreview ) {
			ogDesc.addEventListener( 'input', function () {
				descPreview.textContent = ogDesc.value;
			} );
		}
		if ( ogImage && imagePreview ) {
			ogImage.addEventListener( 'input', function () {
				if ( ogImage.value ) {
					imagePreview.style.backgroundImage = 'url(' + ogImage.value + ')';
					imagePreview.textContent = '';
				} else {
					imagePreview.style.backgroundImage = '';
					imagePreview.textContent = 'No share image set';
				}
			} );
		}
	}

	/* ---------------------------------------------------------------- */
	/* Live analysis: debounced, abortable, deterministic (server-side)   */
	/* ---------------------------------------------------------------- */
	function initLiveAnalysis( panel ) {
		[ '#seoistic_title', '#seoistic_description', '#seoistic_focus_keyword' ].forEach( function ( selector ) {
			var field = panel.querySelector( selector );
			if ( field ) {
				field.addEventListener( 'input', function () {
					scheduleAnalysis( panel );
				} );
			}
		} );

		// Editor content changes (Gutenberg, TinyMCE and plain-textarea alike):
		// a light poll comparing the current content — the debounce absorbs the
		// bursts, stale requests are aborted, nothing runs while the tab is
		// hidden.
		lastContent = getEditorContent();
		window.setInterval( function () {
			if ( document.hidden ) {
				return;
			}
			var content = getEditorContent();
			if ( null !== content && content !== lastContent ) {
				lastContent = content;
				scheduleAnalysis( panel );
			}
		}, CONTENT_POLL_MS );
	}

	function getEditorContent() {
		try {
			if ( window.wp && window.wp.data && window.wp.data.select && window.wp.data.select( 'core/editor' ) ) {
				var value = window.wp.data.select( 'core/editor' ).getEditedPostContent();
				if ( 'string' === typeof value ) {
					return value;
				}
			}
		} catch ( e ) {
			/* Gutenberg store unavailable — fall through. */
		}
		if ( window.tinymce && window.tinymce.get( 'content' ) && ! window.tinymce.get( 'content' ).isHidden() ) {
			return window.tinymce.get( 'content' ).getContent();
		}
		var textarea = document.getElementById( 'content' );
		return textarea ? textarea.value : null;
	}

	function scheduleAnalysis( panel ) {
		setLiveStatus( 'analyzing', i18n( 'analyzing', 'Analyzing…' ) );
		window.clearTimeout( analyzeTimer );
		analyzeTimer = window.setTimeout( function () {
			runAnalysis( panel );
		}, ANALYZE_DEBOUNCE );
	}

	function runAnalysis( panel ) {
		var postId = parseInt( panel.getAttribute( 'data-post-id' ), 10 ) || 0;
		if ( ! postId ) {
			return;
		}
		if ( analyzeController ) {
			analyzeController.abort();
		}
		analyzeController = 'AbortController' in window ? new AbortController() : null;

		var body = { post_id: postId };
		var title = panel.querySelector( '#seoistic_title' );
		var desc = panel.querySelector( '#seoistic_description' );
		var keyword = panel.querySelector( '#seoistic_focus_keyword' );
		if ( title ) {
			body.title = title.value;
		}
		if ( desc ) {
			body.description = desc.value;
		}
		if ( keyword ) {
			body.focus_keyword = keyword.value;
		}
		var content = getEditorContent();
		if ( null !== content ) {
			body.content = content;
		}

		restPost( '/analyze', body, analyzeController ? analyzeController.signal : undefined )
			.then( function ( json ) {
				var data = ( json && json.data ) || {};
				if ( 'number' === typeof data.score ) {
					updateScoreRing( data.score );
				}
				if ( data.checks ) {
					updateCheckLists( data.checks );
				}
				setLiveStatus( '', i18n( 'upToDate', 'Live analysis · up to date' ) );
			} )
			.catch( function ( err ) {
				if ( err && 'AbortError' === err.name ) {
					return;
				}
				setLiveStatus( 'error', i18n( 'analysisError', 'Analysis failed — will retry on next change.' ) );
			} );
	}

	function setLiveStatus( state, text ) {
		var status = document.getElementById( 'seoistic-live-status' );
		if ( ! status ) {
			return;
		}
		status.classList.toggle( 'is-analyzing', 'analyzing' === state );
		status.classList.toggle( 'is-error', 'error' === state );
		var label = status.querySelector( '.seoistic-live-text' );
		if ( label ) {
			label.textContent = text;
		}
	}

	function bandLabel( score ) {
		if ( score >= 90 ) {
			return i18n( 'bandExcellent', 'Excellent' );
		}
		if ( score >= 80 ) {
			return i18n( 'bandGood', 'Good' );
		}
		if ( score >= 50 ) {
			return i18n( 'bandNeedsWork', 'Needs work' );
		}
		return i18n( 'bandCritical', 'Critical' );
	}

	function ringTone( score ) {
		if ( score >= 90 ) {
			return 'is-excellent';
		}
		if ( score >= 80 ) {
			return 'is-good';
		}
		if ( score >= 50 ) {
			return 'is-warn';
		}
		return 'is-bad';
	}

	function updateScoreRing( score ) {
		var wrap = document.getElementById( 'seoistic-workspace-score' );
		if ( ! wrap ) {
			return;
		}
		var ring = wrap.querySelector( '.seoistic-ring' );
		if ( ring ) {
			var fill = ring.querySelector( '.seoistic-ring-fill' );
			if ( fill ) {
				var radius = parseFloat( fill.getAttribute( 'r' ) ) || 22;
				var circumference = 2 * Math.PI * radius;
				fill.style.strokeDashoffset = String( circumference * ( 1 - score / 100 ) );
			}
			ring.classList.remove( 'is-good', 'is-warn', 'is-bad', 'is-excellent' );
			ring.classList.add( ringTone( score ) );
			var label = ring.querySelector( '.seoistic-ring-label' );
			if ( label ) {
				label.textContent = String( score );
			}
		}
		var band = document.getElementById( 'seoistic-score-band' );
		if ( band ) {
			band.textContent = bandLabel( score );
		}
	}

	function updateCheckLists( checks ) {
		var fixes = document.getElementById( 'seoistic-priority-fixes' );
		var passed = document.getElementById( 'seoistic-passed-checks' );
		if ( ! fixes && ! passed ) {
			return;
		}

		function buildItem( check, pass ) {
			var li = document.createElement( 'li' );
			li.className = 'seoistic-check-item ' + ( pass ? 'is-pass' : 'is-fail' );
			var icon = document.createElement( 'span' );
			icon.className = 'dashicons dashicons-' + ( pass ? 'yes-alt' : 'warning' );
			icon.setAttribute( 'aria-hidden', 'true' );
			var text = document.createElement( 'span' );
			text.textContent = check.label || '';
			if ( ! pass && check.message ) {
				var msg = document.createElement( 'span' );
				msg.className = 'seoistic-check-msg';
				msg.textContent = check.message;
				text.appendChild( msg );
			}
			li.appendChild( icon );
			li.appendChild( text );
			return li;
		}

		if ( fixes ) {
			fixes.textContent = '';
			var failing = checks.filter( function ( c ) {
				return ! c.pass;
			} );
			if ( ! failing.length ) {
				var empty = document.createElement( 'li' );
				empty.className = 'seoistic-checks-empty';
				empty.textContent = i18n( 'allPassing', 'Nothing to fix — all checks pass.' );
				fixes.appendChild( empty );
			}
			failing.forEach( function ( c ) {
				fixes.appendChild( buildItem( c, false ) );
			} );
		}
		if ( passed ) {
			passed.textContent = '';
			checks.filter( function ( c ) {
				return !! c.pass;
			} ).forEach( function ( c ) {
				passed.appendChild( buildItem( c, true ) );
			} );
		}
	}

	/* ---------------------------------------------------------------- */
	/* AI actions — before/after suggestion flow                          */
	/* ---------------------------------------------------------------- */
	var AI_ENDPOINTS = {
		generate_title: '/ai/generate-title',
		generate_description: '/ai/generate-description',
		generate_keywords: '/ai/generate-keywords',
		generate_schema: '/ai/generate-schema',
		generate_alt: '/ai/generate-alt',
		internal_links: '/ai/internal-links',
		optimize_content: '/ai/optimize-content',
		full_page_optimization: '/ai/full-page-optimization',
	};

	function initAiButtons( panel ) {
		panel.querySelectorAll( '[data-seoistic-ai-action]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				runAiAction( panel, btn, btn.getAttribute( 'data-seoistic-ai-action' ) );
			} );
		} );
	}

	function runAiAction( panel, btn, action ) {
		var endpoint = AI_ENDPOINTS[ action ];
		if ( ! endpoint ) {
			return;
		}
		var postId = parseInt( panel.getAttribute( 'data-post-id' ), 10 ) || 0;
		var resultBox = document.getElementById( 'seoistic-ai-result' );
		var original = btn.innerHTML;
		btn.disabled = true;
		btn.classList.add( 'is-loading' );

		restPost( endpoint, { post_id: postId } )
			.then( function ( json ) {
				handleAiResult( panel, action, json.data || json, resultBox );
			} )
			.catch( function ( err ) {
				if ( window.seoisticToast ) {
					window.seoisticToast( err.message || i18n( 'aiFailed', 'AI request failed.' ), 'error' );
				}
				if ( resultBox ) {
					resultBox.style.display = 'block';
					resultBox.className = 'seoistic-tool-result is-error';
					resultBox.textContent = err.message || i18n( 'aiFailed', 'AI request failed.' );
				}
			} )
			.finally( function () {
				btn.disabled = false;
				btn.classList.remove( 'is-loading' );
				btn.innerHTML = original;
			} );
	}

	/**
	 * Which field(s) each generator proposes to change. Values are read from
	 * the response data by key.
	 */
	var ACTION_FIELDS = {
		generate_title: [ { selector: '#seoistic_title', key: 'title' } ],
		generate_description: [ { selector: '#seoistic_description', key: 'meta_description' } ],
		generate_keywords: [ { selector: '#seoistic_focus_keyword', key: 'focus_keywords', first: true } ],
		generate_schema: [ { selector: '#seoistic_schema_type', key: 'schema_type' } ],
		full_page_optimization: [
			{ selector: '#seoistic_title', key: 'title' },
			{ selector: '#seoistic_description', key: 'meta_description' },
			{ selector: '#seoistic_focus_keyword', key: 'focus_keywords', first: true },
		],
	};

	function handleAiResult( panel, action, data, resultBox ) {
		var fields = ACTION_FIELDS[ action ];

		if ( fields ) {
			var changes = [];
			fields.forEach( function ( spec ) {
				var el = panel.querySelector( spec.selector );
				if ( ! el ) {
					return;
				}
				var proposed = data[ spec.key ];
				if ( spec.first && Array.isArray( proposed ) ) {
					proposed = proposed[ 0 ];
				}
				if ( 'string' === typeof proposed && '' !== proposed && proposed !== el.value ) {
					changes.push( { element: el, proposed: proposed, previous: el.value } );
				}
			} );
			if ( changes.length ) {
				renderSuggestion( panel, action, changes, data.reason || '' );
			} else if ( window.seoisticToast ) {
				window.seoisticToast( i18n( 'noChange', 'AI returned no new suggestion for this field.' ), 'info' );
			}
			return;
		}

		// Advisory actions (content suggestions, internal links, alt text):
		// render as a plain list — a human decides what to place where.
		if ( resultBox ) {
			resultBox.style.display = 'block';
			resultBox.className = 'seoistic-tool-result is-success';
			resultBox.textContent = '';
			var suggestions = data.suggestions || ( data.alt_text ? [ data.alt_text ] : [] );
			if ( ! suggestions.length && data.reason ) {
				suggestions = [ data.reason ];
			}
			if ( ! suggestions.length ) {
				resultBox.textContent = i18n( 'noChange', 'AI returned no new suggestion for this field.' );
				return;
			}
			var list = document.createElement( 'ul' );
			list.style.margin = '0';
			list.style.paddingLeft = '18px';
			suggestions.forEach( function ( suggestion ) {
				var li = document.createElement( 'li' );
				li.textContent = 'string' === typeof suggestion ? suggestion : JSON.stringify( suggestion );
				list.appendChild( li );
			} );
			resultBox.appendChild( list );
		}
	}

	function fieldLabel( element ) {
		var wrap = element.closest( '.seoistic-field' );
		var label = wrap ? wrap.querySelector( '.seoistic-field-label' ) : null;
		return label ? label.textContent : element.name || '';
	}

	/**
	 * Before/after suggestion card: states what changes, shows both versions,
	 * and only ever writes on an explicit Apply. Undo restores the exact
	 * previous values. All content set via textContent — no raw HTML.
	 */
	function renderSuggestion( panel, action, changes, reason ) {
		// One suggestion card per action at a time.
		var existing = panel.querySelector( '[data-seoistic-suggestion="' + action + '"]' );
		if ( existing ) {
			existing.remove();
		}

		var card = document.createElement( 'div' );
		card.className = 'seoistic-suggestion';
		card.setAttribute( 'data-seoistic-suggestion', action );
		card.setAttribute( 'role', 'region' );
		card.setAttribute( 'aria-label', i18n( 'aiSuggestion', 'AI suggestion' ) );

		var head = document.createElement( 'div' );
		head.className = 'seoistic-suggestion-head';
		var headIcon = document.createElement( 'span' );
		headIcon.className = 'dashicons dashicons-superhero';
		headIcon.setAttribute( 'aria-hidden', 'true' );
		var headText = document.createElement( 'span' );
		headText.textContent = i18n( 'aiSuggestion', 'AI suggestion' );
		head.appendChild( headIcon );
		head.appendChild( headText );
		card.appendChild( head );

		var body = document.createElement( 'div' );
		body.className = 'seoistic-suggestion-body';
		changes.forEach( function ( change ) {
			var label = document.createElement( 'p' );
			label.className = 'seoistic-suggestion-note';
			label.textContent = fieldLabel( change.element );
			body.appendChild( label );

			var cols = document.createElement( 'div' );
			cols.className = 'seoistic-suggestion-cols';
			[ [ 'is-before', i18n( 'before', 'Before' ), change.previous ], [ 'is-after', i18n( 'after', 'After' ), change.proposed ] ].forEach( function ( spec ) {
				var col = document.createElement( 'div' );
				col.className = 'seoistic-suggestion-col ' + spec[ 0 ];
				var colLabel = document.createElement( 'span' );
				colLabel.className = 'seoistic-suggestion-col-label';
				colLabel.textContent = spec[ 1 ];
				var colText = document.createElement( 'span' );
				colText.textContent = spec[ 2 ] || i18n( 'empty', '(empty)' );
				col.appendChild( colLabel );
				col.appendChild( colText );
				cols.appendChild( col );
			} );
			body.appendChild( cols );
		} );
		if ( reason ) {
			var why = document.createElement( 'p' );
			why.className = 'seoistic-suggestion-note';
			why.textContent = reason;
			body.appendChild( why );
		}
		card.appendChild( body );

		var actions = document.createElement( 'div' );
		actions.className = 'seoistic-suggestion-actions';

		var applyBtn = document.createElement( 'button' );
		applyBtn.type = 'button';
		applyBtn.className = 'seoistic-btn seoistic-btn-primary seoistic-btn-sm';
		applyBtn.textContent = i18n( 'apply', 'Apply' );

		var dismissBtn = document.createElement( 'button' );
		dismissBtn.type = 'button';
		dismissBtn.className = 'seoistic-btn seoistic-btn-sm';
		dismissBtn.textContent = i18n( 'dismiss', 'Dismiss' );

		var undoBtn = document.createElement( 'button' );
		undoBtn.type = 'button';
		undoBtn.className = 'seoistic-btn seoistic-btn-sm';
		undoBtn.textContent = i18n( 'undo', 'Undo' );
		undoBtn.hidden = true;

		function setFieldValue( element, value ) {
			element.value = value;
			element.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			if ( 'SELECT' === element.tagName ) {
				element.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
		}

		applyBtn.addEventListener( 'click', function () {
			changes.forEach( function ( change ) {
				setFieldValue( change.element, change.proposed );
			} );
			applyBtn.hidden = true;
			dismissBtn.hidden = true;
			undoBtn.hidden = false;
			undoBtn.focus();
			scheduleAnalysis( panel );
			if ( window.seoisticToast ) {
				window.seoisticToast( i18n( 'applied', 'Suggestion applied.' ), 'success' );
			}
		} );

		undoBtn.addEventListener( 'click', function () {
			changes.forEach( function ( change ) {
				setFieldValue( change.element, change.previous );
			} );
			card.remove();
			scheduleAnalysis( panel );
			if ( window.seoisticToast ) {
				window.seoisticToast( i18n( 'undone', 'Change undone.' ), 'success' );
			}
		} );

		dismissBtn.addEventListener( 'click', function () {
			card.remove();
		} );

		actions.appendChild( applyBtn );
		actions.appendChild( dismissBtn );
		actions.appendChild( undoBtn );
		card.appendChild( actions );

		// Single-field suggestions dock under their field; multi-field ones go
		// at the top of the active pane.
		if ( 1 === changes.length ) {
			var fieldWrap = changes[ 0 ].element.closest( '.seoistic-field' );
			if ( fieldWrap ) {
				fieldWrap.insertAdjacentElement( 'afterend', card );
			} else {
				panel.appendChild( card );
			}
			// Make sure the owning tab is visible when triggered from elsewhere.
			var pane = card.closest( '.seoistic-panel-pane' );
			if ( pane && ! pane.classList.contains( 'is-active' ) ) {
				var tab = panel.querySelector( '[data-seoistic-tab="' + pane.getAttribute( 'data-seoistic-pane' ) + '"]' );
				if ( tab ) {
					tab.click();
				}
			}
		} else {
			var activePane = panel.querySelector( '.seoistic-panel-pane.is-active' );
			( activePane || panel ).insertAdjacentElement( 'afterbegin', card );
		}
		applyBtn.focus();
	}

	/* ---------------------------------------------------------------- */
	/* "Fix with AI" buttons in the Audit checklist                       */
	/* ---------------------------------------------------------------- */
	var FIX_MAP = {
		title: 'generate_title',
		description: 'generate_description',
		keyword: 'generate_keywords',
		og_image: 'generate_alt',
	};

	function initFixWithAi( panel ) {
		panel.querySelectorAll( '[data-seoistic-ai-fix]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var checkId = btn.getAttribute( 'data-seoistic-ai-fix' );
				var action = FIX_MAP[ checkId ] || 'optimize_content';
				var aiTab = panel.querySelector( '[data-seoistic-tab="ai"]' );
				if ( aiTab ) {
					aiTab.click();
				}
				var aiButton = panel.querySelector( '[data-seoistic-ai-action="' + action + '"]' ) || panel.querySelector( '[data-seoistic-ai-action="optimize_content"]' );
				if ( aiButton ) {
					aiButton.click();
				}
			} );
		} );
	}

	/* ---------------------------------------------------------------- */
	/* Sidebar "View full report" — jumps into the main panel's Audit tab */
	/* ---------------------------------------------------------------- */
	function initOpenAuditTab( panel ) {
		document.querySelectorAll( '[data-seoistic-open-audit-tab]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var auditTab = panel.querySelector( '[data-seoistic-tab="audit"]' );
				if ( auditTab ) {
					auditTab.click();
				}
				panel.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			} );
		} );
	}

	/* ---------------------------------------------------------------- */
	/* Manual "Run Audit" — recalculates without saving the post           */
	/* ---------------------------------------------------------------- */
	function initRunPostAudit( panel ) {
		var btn = document.getElementById( 'seoistic-run-post-audit' );
		if ( ! btn ) {
			return;
		}
		btn.addEventListener( 'click', function () {
			var postId = parseInt( btn.getAttribute( 'data-post-id' ), 10 ) || 0;
			btn.disabled = true;
			restPost( '/audit/post', { post_id: postId } )
				.then( function () {
					window.location.reload();
				} )
				.catch( function () {
					btn.disabled = false;
				} );
		} );
	}
} )();
