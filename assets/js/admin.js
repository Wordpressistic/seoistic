/**
 * SEOISTIC admin — shared vanilla-JS behaviours: app shell (sidebar drawer,
 * command palette, toasts), dashboard (counters, roadmap, batched site audit),
 * addons, import and tools screens. No build step, no framework, no jQuery.
 */
( function () {
	'use strict';

	var reducedMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	/**
	 * Shared REST helper — used by ai.js (post-edit panel) and ai-tools.js (AI
	 * Tools page). Always sends the WP REST nonce; never touches provider keys.
	 */
	window.seoisticRestPost = function ( path, body, signal ) {
		if ( ! window.SeoisticAdmin || ! window.SeoisticAdmin.restUrl ) {
			return Promise.reject( new Error( 'REST config missing' ) );
		}
		return fetch( window.SeoisticAdmin.restUrl + path, {
			method: 'POST',
			credentials: 'same-origin',
			signal: signal || undefined,
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': window.SeoisticAdmin.restNonce,
			},
			body: JSON.stringify( body ),
		} ).then( function ( r ) {
			return r.json().then( function ( json ) {
				if ( ! r.ok ) {
					throw new Error( ( json && json.message ) || 'Request failed' );
				}
				return json;
			} );
		} );
	};

	window.seoisticRestGet = function ( path, signal ) {
		if ( ! window.SeoisticAdmin || ! window.SeoisticAdmin.restUrl ) {
			return Promise.reject( new Error( 'REST config missing' ) );
		}
		return fetch( window.SeoisticAdmin.restUrl + path, {
			method: 'GET',
			credentials: 'same-origin',
			signal: signal || undefined,
			headers: { 'X-WP-Nonce': window.SeoisticAdmin.restNonce },
		} ).then( function ( r ) {
			return r.json().then( function ( json ) {
				if ( ! r.ok ) {
					throw new Error( ( json && json.message ) || 'Request failed' );
				}
				return json;
			} );
		} );
	};

	/* ---------------------------------------------------------------- */
	/* Toasts                                                            */
	/* ---------------------------------------------------------------- */
	var TOAST_ICONS = { success: 'yes-alt', error: 'warning', info: 'info-outline' };

	window.seoisticToast = function ( message, type ) {
		var region = document.getElementById( 'seoistic-toasts' );
		if ( ! region ) {
			// Post-edit screens don't render the shell footer — create the
			// live region on demand.
			region = document.createElement( 'div' );
			region.id = 'seoistic-toasts';
			region.className = 'seoistic-toasts';
			region.setAttribute( 'role', 'status' );
			region.setAttribute( 'aria-live', 'polite' );
			document.body.appendChild( region );
		}
		type = TOAST_ICONS[ type ] ? type : 'info';
		var toast = document.createElement( 'div' );
		toast.className = 'seoistic-toast is-' + type;
		var icon = document.createElement( 'span' );
		icon.className = 'dashicons dashicons-' + TOAST_ICONS[ type ];
		icon.setAttribute( 'aria-hidden', 'true' );
		var text = document.createElement( 'span' );
		text.textContent = message;
		toast.appendChild( icon );
		toast.appendChild( text );
		region.appendChild( toast );

		window.setTimeout( function () {
			toast.classList.add( 'is-leaving' );
			window.setTimeout( function () {
				toast.remove();
			}, reducedMotion ? 0 : 220 );
		}, 3500 );
	};

	document.addEventListener( 'DOMContentLoaded', function () {
		animateCounters();
		initSidebar();
		initRoadmap();
		initCommandPalette();
		initAddonFilters();
		initAddonSearch();
		initAddonToggles();
		initModals();
		initClipboard();
		initConfirmForms();
		initImportCards();
		initSiteAudit();
	} );

	/* ---------------------------------------------------------------- */
	/* Animated counters on dashboard/metric cards                       */
	/* ---------------------------------------------------------------- */
	function animateCounters() {
		var els = document.querySelectorAll( '[data-seoistic-count]' );
		els.forEach( function ( el ) {
			var raw = el.getAttribute( 'data-seoistic-count' );
			var target = parseInt( raw, 10 );
			if ( isNaN( target ) ) {
				return; // Non-numeric (e.g. a status glyph) — leave as-is.
			}
			if ( reducedMotion ) {
				el.textContent = target;
				return;
			}
			var duration = 700;
			var start = null;
			var from = 0;

			function step( timestamp ) {
				if ( ! start ) {
					start = timestamp;
				}
				var progress = Math.min( ( timestamp - start ) / duration, 1 );
				var eased = 1 - Math.pow( 1 - progress, 3 );
				el.textContent = Math.round( from + ( target - from ) * eased );
				if ( progress < 1 ) {
					window.requestAnimationFrame( step );
				} else {
					el.textContent = target;
				}
			}
			window.requestAnimationFrame( step );
		} );
	}

	/* ---------------------------------------------------------------- */
	/* App shell: sidebar drawer (narrow viewports)                      */
	/* ---------------------------------------------------------------- */
	function initSidebar() {
		var sidebar = document.getElementById( 'seoistic-sidebar' );
		if ( ! sidebar ) {
			return;
		}
		var overlay = document.querySelector( '.seoistic-sidebar-overlay' );

		function setOpen( open ) {
			sidebar.classList.toggle( 'is-open', open );
			if ( overlay ) {
				overlay.classList.toggle( 'is-open', open );
			}
			document.querySelectorAll( '[data-seoistic-sidebar-toggle]' ).forEach( function ( btn ) {
				btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			} );
		}

		document.querySelectorAll( '[data-seoistic-sidebar-toggle]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				setOpen( ! sidebar.classList.contains( 'is-open' ) );
			} );
		} );
		document.querySelectorAll( '[data-seoistic-sidebar-close]' ).forEach( function ( el ) {
			el.addEventListener( 'click', function () {
				setOpen( false );
			} );
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && sidebar.classList.contains( 'is-open' ) ) {
				setOpen( false );
			}
		} );
	}

	/* ---------------------------------------------------------------- */
	/* Dashboard roadmap: collapsible severity groups                    */
	/* ---------------------------------------------------------------- */
	function initRoadmap() {
		document.querySelectorAll( '[data-seoistic-roadmap-toggle]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var group = btn.closest( '.seoistic-roadmap-group' );
				if ( ! group ) {
					return;
				}
				var open = ! group.classList.contains( 'is-open' );
				group.classList.toggle( 'is-open', open );
				btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			} );
		} );

		// "Run Site Audit" proxies inside roadmap items delegate to the hero button.
		document.querySelectorAll( '[data-seoistic-run-audit]' ).forEach( function ( proxy ) {
			proxy.addEventListener( 'click', function () {
				var main = document.getElementById( 'seoistic-run-audit' );
				if ( main ) {
					main.scrollIntoView( { behavior: reducedMotion ? 'auto' : 'smooth', block: 'center' } );
					main.click();
				}
			} );
		} );
	}

	/* ---------------------------------------------------------------- */
	/* Command palette (Ctrl/Cmd+K)                                      */
	/* ---------------------------------------------------------------- */
	function initCommandPalette() {
		var root = document.getElementById( 'seoistic-cmdk' );
		if ( ! root ) {
			return;
		}
		var input = document.getElementById( 'seoistic-cmdk-input' );
		var list = document.getElementById( 'seoistic-cmdk-list' );
		var commands = ( window.SeoisticAdmin && window.SeoisticAdmin.commands ) || [];
		var items = [];
		var selected = 0;
		var opener = null;
		var searchTimer = null;
		var searchController = null;
		var lastQuery = '';

		function i18n( key, fallback ) {
			return ( window.SeoisticAdmin && window.SeoisticAdmin.i18n && window.SeoisticAdmin.i18n[ key ] ) || fallback;
		}

		function open() {
			opener = document.activeElement;
			root.hidden = false;
			root.classList.add( 'is-open' );
			input.value = '';
			lastQuery = '';
			renderResults( staticMatches( '' ), null );
			input.focus();
		}

		function close() {
			root.classList.remove( 'is-open' );
			root.hidden = true;
			if ( searchController ) {
				searchController.abort();
				searchController = null;
			}
			if ( opener && opener.focus ) {
				opener.focus();
			}
		}

		function staticMatches( q ) {
			q = q.toLowerCase();
			return commands.filter( function ( cmd ) {
				return ! q || ( cmd.label || '' ).toLowerCase().indexOf( q ) !== -1 || ( cmd.group || '' ).toLowerCase().indexOf( q ) !== -1;
			} );
		}

		/**
		 * Renders palette entries. `posts` is null while no content search ran,
		 * an array once results arrived, or the string 'loading' / 'error'.
		 */
		function renderResults( screens, posts ) {
			items = [];
			list.textContent = '';
			var index = 0;

			function addGroupLabel( label ) {
				var li = document.createElement( 'li' );
				li.className = 'seoistic-cmdk-group-label';
				li.textContent = label;
				li.setAttribute( 'role', 'presentation' );
				list.appendChild( li );
			}

			function addItem( entry ) {
				var li = document.createElement( 'li' );
				li.className = 'seoistic-cmdk-item';
				li.id = 'seoistic-cmdk-item-' + index;
				li.setAttribute( 'role', 'option' );
				li.setAttribute( 'aria-selected', 'false' );
				var icon = document.createElement( 'span' );
				icon.className = 'dashicons dashicons-' + ( entry.icon || 'arrow-right-alt2' );
				icon.setAttribute( 'aria-hidden', 'true' );
				var label = document.createElement( 'span' );
				label.textContent = entry.label;
				li.appendChild( icon );
				li.appendChild( label );
				if ( entry.meta ) {
					var meta = document.createElement( 'span' );
					meta.className = 'seoistic-cmdk-item-meta';
					meta.textContent = entry.meta;
					li.appendChild( meta );
				}
				li.addEventListener( 'click', function () {
					window.location.href = entry.url;
				} );
				li.addEventListener( 'mousemove', function () {
					select( items.indexOf( entry ) );
				} );
				list.appendChild( li );
				entry.element = li;
				items.push( entry );
				index++;
			}

			if ( screens.length ) {
				addGroupLabel( i18n( 'screens', 'Pages & actions' ) );
				screens.forEach( function ( cmd ) {
					addItem( { label: cmd.label, icon: cmd.icon, url: cmd.url, meta: cmd.group } );
				} );
			}

			if ( 'loading' === posts ) {
				var loading = document.createElement( 'li' );
				loading.className = 'seoistic-cmdk-loading';
				loading.textContent = i18n( 'searching', 'Searching…' );
				list.appendChild( loading );
			} else if ( 'error' === posts ) {
				var failed = document.createElement( 'li' );
				failed.className = 'seoistic-cmdk-empty';
				failed.textContent = i18n( 'searchFailed', 'Search failed — check your connection.' );
				list.appendChild( failed );
			} else if ( posts && posts.length ) {
				addGroupLabel( i18n( 'content', 'Content' ) );
				posts.forEach( function ( post ) {
					addItem( {
						label: post.title,
						icon: 'admin-page',
						url: post.edit_url,
						meta: post.type_label + ( post.score >= 0 ? ' · ' + post.score + '/100' : ' · ' + i18n( 'notScored', 'Not scored' ) ),
					} );
				} );
			}

			if ( ! items.length && 'loading' !== posts && 'error' !== posts ) {
				var empty = document.createElement( 'li' );
				empty.className = 'seoistic-cmdk-empty';
				empty.textContent = i18n( 'noResults', 'No results found.' );
				list.appendChild( empty );
			}

			select( 0 );
		}

		function select( idx ) {
			if ( ! items.length ) {
				input.removeAttribute( 'aria-activedescendant' );
				return;
			}
			selected = Math.max( 0, Math.min( idx, items.length - 1 ) );
			items.forEach( function ( entry, i ) {
				entry.element.classList.toggle( 'is-selected', i === selected );
				entry.element.setAttribute( 'aria-selected', i === selected ? 'true' : 'false' );
			} );
			input.setAttribute( 'aria-activedescendant', items[ selected ].element.id );
			items[ selected ].element.scrollIntoView( { block: 'nearest' } );
		}

		function onQuery() {
			var q = input.value.trim();
			lastQuery = q;
			var screens = staticMatches( q );

			if ( q.length < 2 ) {
				if ( searchController ) {
					searchController.abort();
					searchController = null;
				}
				renderResults( screens, null );
				return;
			}

			renderResults( screens, 'loading' );
			window.clearTimeout( searchTimer );
			searchTimer = window.setTimeout( function () {
				if ( searchController ) {
					searchController.abort();
				}
				searchController = 'AbortController' in window ? new AbortController() : null;
				window
					.seoisticRestGet( '/search?q=' + encodeURIComponent( q ), searchController ? searchController.signal : undefined )
					.then( function ( json ) {
						if ( q !== lastQuery ) {
							return; // Stale response.
						}
						var results = ( json && json.data && json.data.results ) || [];
						renderResults( staticMatches( q ), results );
					} )
					.catch( function ( err ) {
						if ( err && 'AbortError' === err.name ) {
							return;
						}
						if ( q === lastQuery ) {
							renderResults( staticMatches( q ), 'error' );
						}
					} );
			}, 300 );
		}

		input.addEventListener( 'input', onQuery );
		input.addEventListener( 'keydown', function ( e ) {
			if ( 'ArrowDown' === e.key ) {
				e.preventDefault();
				select( selected + 1 );
			} else if ( 'ArrowUp' === e.key ) {
				e.preventDefault();
				select( selected - 1 );
			} else if ( 'Home' === e.key && items.length ) {
				e.preventDefault();
				select( 0 );
			} else if ( 'End' === e.key && items.length ) {
				e.preventDefault();
				select( items.length - 1 );
			} else if ( 'Enter' === e.key ) {
				e.preventDefault();
				if ( items[ selected ] ) {
					window.location.href = items[ selected ].url;
				}
			}
		} );

		document.querySelectorAll( '[data-seoistic-cmdk-open]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', open );
		} );
		document.querySelectorAll( '[data-seoistic-cmdk-close]' ).forEach( function ( el ) {
			el.addEventListener( 'click', close );
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( ( e.ctrlKey || e.metaKey ) && 'k' === e.key.toLowerCase() ) {
				e.preventDefault();
				if ( root.classList.contains( 'is-open' ) ) {
					close();
				} else {
					open();
				}
			} else if ( 'Escape' === e.key && root.classList.contains( 'is-open' ) ) {
				close();
			} else if ( 'Tab' === e.key && root.classList.contains( 'is-open' ) ) {
				e.preventDefault(); // Single-control dialog: keep focus on the input.
				input.focus();
			}
		} );
	}

	/* ---------------------------------------------------------------- */
	/* Addons page: category filters + free-text search                  */
	/* ---------------------------------------------------------------- */
	function initAddonFilters() {
		var filters = document.querySelectorAll( '.seoistic-filter' );
		if ( ! filters.length ) {
			return;
		}
		filters.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				filters.forEach( function ( b ) {
					b.classList.remove( 'is-active' );
				} );
				btn.classList.add( 'is-active' );
				applyAddonFilter( btn.getAttribute( 'data-filter' ) || 'all' );
			} );
		} );
	}

	function applyAddonFilter( category ) {
		var cards = document.querySelectorAll( '.seoistic-addon' );
		var search = document.getElementById( 'seoistic-addon-search' );
		var term = search ? search.value.toLowerCase() : '';
		cards.forEach( function ( card ) {
			var cats = ( card.getAttribute( 'data-categories' ) || '' ).split( ',' );
			var matchesCategory = 'all' === category || cats.indexOf( category ) !== -1;
			var matchesSearch = ! term || ( card.getAttribute( 'data-search' ) || '' ).indexOf( term ) !== -1;
			card.style.display = matchesCategory && matchesSearch ? '' : 'none';
		} );
	}

	function initAddonSearch() {
		var search = document.getElementById( 'seoistic-addon-search' );
		if ( ! search ) {
			return;
		}
		search.addEventListener( 'input', function () {
			var active = document.querySelector( '.seoistic-filter.is-active' );
			applyAddonFilter( active ? active.getAttribute( 'data-filter' ) : 'all' );
		} );
	}

	/* ---------------------------------------------------------------- */
	/* Addon enable/disable toggle — submits its own tiny form via fetch */
	/* ---------------------------------------------------------------- */
	function initAddonToggles() {
		document.querySelectorAll( '.seoistic-switch input[data-module]' ).forEach( function ( input ) {
			input.addEventListener( 'change', function () {
				var form = input.closest( 'form' );
				if ( form ) {
					form.submit();
				}
			} );
		} );
	}

	/* ---------------------------------------------------------------- */
	/* Generic modal open/close (used by AI tools + fix-with-AI)          */
	/* ---------------------------------------------------------------- */
	function initModals() {
		document.querySelectorAll( '[data-seoistic-modal-close]' ).forEach( function ( el ) {
			el.addEventListener( 'click', function () {
				var overlay = el.closest( '.seoistic-modal-overlay' );
				if ( overlay ) {
					overlay.classList.remove( 'is-open' );
				}
			} );
		} );
		document.querySelectorAll( '.seoistic-modal-overlay' ).forEach( function ( overlay ) {
			overlay.addEventListener( 'click', function ( e ) {
				if ( e.target === overlay ) {
					overlay.classList.remove( 'is-open' );
				}
			} );
		} );
	}

	window.seoisticOpenModal = function ( id ) {
		var overlay = document.getElementById( id );
		if ( overlay ) {
			overlay.classList.add( 'is-open' );
		}
	};
	window.seoisticCloseModal = function ( id ) {
		var overlay = document.getElementById( id );
		if ( overlay ) {
			overlay.classList.remove( 'is-open' );
		}
	};

	/* ---------------------------------------------------------------- */
	/* Copy-to-clipboard buttons                                         */
	/* ---------------------------------------------------------------- */
	function initClipboard() {
		document.querySelectorAll( '[data-seoistic-copy]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var targetSel = btn.getAttribute( 'data-seoistic-copy' );
				var target = document.querySelector( targetSel );
				if ( ! target ) {
					return;
				}
				var text = 'value' in target ? target.value : target.textContent;
				var done = function () {
					var original = btn.textContent;
					btn.textContent = seoisticI18n( 'copied', 'Copied!' );
					setTimeout( function () {
						btn.textContent = original;
					}, 1500 );
				};
				if ( navigator.clipboard && window.isSecureContext ) {
					navigator.clipboard.writeText( text ).then( done );
				} else {
					var ta = document.createElement( 'textarea' );
					ta.value = text;
					ta.style.position = 'fixed';
					ta.style.opacity = '0';
					document.body.appendChild( ta );
					ta.select();
					try {
						document.execCommand( 'copy' );
					} catch ( err ) {
						/* clipboard unsupported — silently ignore */
					}
					document.body.removeChild( ta );
					done();
				}
			} );
		} );
	}

	function seoisticI18n( key, fallback ) {
		if ( window.SeoisticAdmin && window.SeoisticAdmin.i18n && window.SeoisticAdmin.i18n[ key ] ) {
			return window.SeoisticAdmin.i18n[ key ];
		}
		return fallback;
	}

	/* ---------------------------------------------------------------- */
	/* Confirmation dialogs for destructive/irreversible actions          */
	/* ---------------------------------------------------------------- */
	function initConfirmForms() {
		document.querySelectorAll( '[data-seoistic-confirm]' ).forEach( function ( el ) {
			el.addEventListener( 'click', function ( e ) {
				var message = el.getAttribute( 'data-seoistic-confirm' );
				if ( message && ! window.confirm( message ) ) {
					e.preventDefault();
					e.stopPropagation();
				}
			} );
		} );
	}

	/* ---------------------------------------------------------------- */
	/* Import page: batched AJAX import with a progress bar              */
	/* ---------------------------------------------------------------- */
	function initImportCards() {
		document.querySelectorAll( '[data-seoistic-import]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				runImportBatch( btn, 0, 0 );
			} );
		} );
	}

	function runImportBatch( btn, offset, importedSoFar ) {
		if ( ! window.SeoisticAdmin || ! window.SeoisticAdmin.ajaxUrl ) {
			return;
		}
		var source = btn.getAttribute( 'data-seoistic-import' );
		var card = btn.closest( '.seoistic-tool-card' );
		var progressWrap = card ? card.querySelector( '.seoistic-tool-progress' ) : null;
		var progressBar = card ? card.querySelector( '.seoistic-tool-progress-bar' ) : null;
		var resultBox = card ? card.querySelector( '.seoistic-tool-result' ) : null;

		btn.disabled = true;
		if ( progressWrap ) {
			progressWrap.style.display = 'block';
		}

		var body = new URLSearchParams();
		body.set( 'action', 'seoistic_import_batch' );
		body.set( 'nonce', window.SeoisticAdmin.importNonce );
		body.set( 'source', source );
		body.set( 'offset', String( offset ) );

		fetch( window.SeoisticAdmin.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		} )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					showResult( resultBox, false, ( json && json.data && json.data.message ) || seoisticI18n( 'importFailed', 'Import failed.' ) );
					btn.disabled = false;
					return;
				}
				var data = json.data;
				importedSoFar += data.imported;
				if ( progressBar ) {
					progressBar.style.width = Math.round( data.percent ) + '%';
				}
				if ( data.done ) {
					showResult( resultBox, true, data.message || ( importedSoFar + ' items imported.' ) );
					btn.disabled = false;
				} else {
					runImportBatch( btn, data.next_offset, importedSoFar );
				}
			} )
			.catch( function () {
				showResult( resultBox, false, seoisticI18n( 'importFailed', 'Import failed.' ) );
				btn.disabled = false;
			} );
	}

	function showResult( box, success, message ) {
		if ( ! box ) {
			return;
		}
		box.style.display = 'block';
		box.classList.toggle( 'is-success', success );
		box.classList.toggle( 'is-error', ! success );
		box.textContent = message;
	}

	/* ---------------------------------------------------------------- */
	/* Dashboard: "Run Site Audit" — batched score recalculation          */
	/* ---------------------------------------------------------------- */
	function initSiteAudit() {
		var btn = document.getElementById( 'seoistic-run-audit' );
		if ( ! btn ) {
			return;
		}
		btn.addEventListener( 'click', function () {
			if ( btn.disabled ) {
				return;
			}
			var progressWrap = document.getElementById( 'seoistic-audit-progress' );
			var progressBar = progressWrap ? progressWrap.querySelector( '.seoistic-tool-progress-bar' ) : null;
			var resultBox = document.getElementById( 'seoistic-audit-result' );
			btn.disabled = true;
			if ( progressWrap ) {
				progressWrap.style.display = 'block';
			}
			if ( resultBox ) {
				resultBox.style.display = 'none';
			}
			runAuditBatch( btn, 0, progressBar, resultBox );
		} );
	}

	function runAuditBatch( btn, offset, progressBar, resultBox ) {
		if ( ! window.SeoisticAdmin || ! window.SeoisticAdmin.ajaxUrl ) {
			return;
		}
		var body = new URLSearchParams();
		body.set( 'action', 'seoistic_audit_batch' );
		body.set( 'nonce', window.SeoisticAdmin.auditNonce );
		body.set( 'offset', String( offset ) );

		fetch( window.SeoisticAdmin.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		} )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					showResult( resultBox, false, ( json && json.data && json.data.message ) || 'Audit failed.' );
					btn.disabled = false;
					return;
				}
				var data = json.data;
				if ( progressBar ) {
					progressBar.style.width = Math.round( data.percent ) + '%';
				}
				if ( data.done ) {
					showResult( resultBox, true, data.message );
					btn.disabled = false;
					setTimeout( function () {
						window.location.reload();
					}, 900 );
				} else {
					runAuditBatch( btn, data.next_offset, progressBar, resultBox );
				}
			} )
			.catch( function () {
				showResult( resultBox, false, 'Audit failed.' );
				btn.disabled = false;
			} );
	}
} )();
