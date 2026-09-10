( function () {
	'use strict';

	var settings = window.SeoisticSchemaBuilder || {};
	var typeSelect = document.getElementById( 'seoistic-schema-type' );
	var fieldsRoot = document.getElementById( 'seoistic-schema-fields' );
	var form = document.querySelector( '.seoistic-schema-editor' );
	var preview = document.getElementById( 'seoistic-schema-preview' );
	var issuesRoot = document.getElementById( 'seoistic-schema-issues' );
	var hiddenRules = document.getElementById( 'seoistic-schema-rules-json' );
	var patterns = document.getElementById( 'seoistic-schema-url-patterns' );
	var idInput = form && form.querySelector( 'input[name="id"]' );
	var hiddenMapping = document.createElement( 'input' );
	var variableSelect = document.createElement( 'select' );
	var lastInput = null;
	var hasInvalidJson = false;
	hiddenMapping.type = 'hidden';
	hiddenMapping.name = 'mapping';
	if ( form ) {
		form.appendChild( hiddenMapping );
	}

	function label( type ) {
		return ( settings.typeLabels && settings.typeLabels[ type ] ) || type;
	}

	function requirement( type, field ) {
		var requirements = settings.requirements || {};
		return ( requirements[ type ] || [] ).indexOf( field ) !== -1;
	}

	function currentType() {
		return typeSelect ? typeSelect.value : 'Article';
	}

	function fieldsFor( type ) {
		return ( settings.typeFields && settings.typeFields[ type ] ) || [];
	}

	function structured( field ) {
		var structuredFields = settings.fieldTypes || {};
		return Boolean( structuredFields[ field ] );
	}

	function renderFields() {
		if ( ! typeSelect || ! fieldsRoot ) {
			return;
		}
		var type = currentType();
		fieldsRoot.innerHTML = '';
		if ( type === 'custom' ) {
			var label = document.createElement( 'label' );
			label.className = 'seoistic-field-label';
			label.textContent = 'JSON-LD';
			var textarea = document.createElement( 'textarea' );
			textarea.id = 'seoistic-schema-custom-json';
			textarea.rows = 12;
			textarea.placeholder = '{ "@type": "Thing", "name": "{post_title}" }';
			textarea.addEventListener( 'input', update );
			fieldsRoot.className = 'seoistic-field';
			fieldsRoot.appendChild( label );
			fieldsRoot.appendChild( textarea );
			update();
			return;
		}
		fieldsRoot.className = 'seoistic-schema-fields';
		fieldsFor( type ).forEach( function ( field ) {
			var wrap = document.createElement( 'div' );
			wrap.className = 'seoistic-field';
			var fieldLabel = document.createElement( 'label' );
			fieldLabel.className = 'seoistic-field-label';
			fieldLabel.textContent = field;
			if ( requirement( type, field ) ) {
				var badge = document.createElement( 'span' );
				badge.className = 'seoistic-schema-badge';
				badge.textContent = ( settings.i18n && settings.i18n.required ) || '*';
				fieldLabel.appendChild( badge );
			}
			var control;
			if ( structured( field ) ) {
				control = document.createElement( 'textarea' );
				control.rows = 3;
				control.placeholder = field === 'mainEntity' ? '[{ "@type": "Question", "name": "{post_title}", "acceptedAnswer": { "@type": "Answer", "text": "{seo_description}" } }]' : '{ "@type": "Thing", "name": "{post_title}" }';
			} else {
				control = document.createElement( 'input' );
				control.type = 'text';
				control.placeholder = '{' + field + '} or a static value';
			}
			control.dataset.schemaField = field;
			control.addEventListener( 'input', update );
			wrap.appendChild( fieldLabel );
			wrap.appendChild( control );
			fieldsRoot.appendChild( wrap );
		} );
		update();
	}

	function parsedValue( raw ) {
		var trimmed = ( raw || '' ).trim();
		if ( '' === trimmed ) {
			return '';
		}
		if ( trimmed.charAt( 0 ) === '{' || trimmed.charAt( 0 ) === '[' ) {
			try {
				return JSON.parse( trimmed );
			} catch ( error ) {
				hasInvalidJson = true;
				return undefined;
			}
		}
		return raw;
	}

	function currentMapping() {
		hasInvalidJson = false;
		if ( currentType() === 'custom' ) {
			var custom = document.getElementById( 'seoistic-schema-custom-json' );
			return custom ? parsedValue( custom.value ) : {};
		}
		var mapping = {};
		( fieldsRoot.querySelectorAll( '[data-schema-field]' ) || [] ).forEach( function ( input ) {
			var value = parsedValue( input.value );
			if ( value !== '' && typeof value !== 'undefined' ) {
				mapping[ input.dataset.schemaField ] = value;
			}
		} );
		return mapping;
	}

	function validationIssues( node ) {
		var issues = [];
		var i18n = settings.i18n || {};
		if ( ! node || ! node[ '@type' ] ) {
			issues.push( i18n.missingType || 'Missing @type' );
		}
		( ( settings.requirements || {} )[ currentType() ] || [] ).forEach( function ( field ) {
			var value = node ? node[ field ] : '';
			if ( ! value || ( Array.isArray( value ) && ! value.length ) ) {
				issues.push( field + ': ' + ( i18n.required || 'Required' ) );
			}
		} );
		return issues;
	}

	function update() {
		if ( ! preview || ! form ) {
			return;
		}
		var type = currentType();
		var mapping = currentMapping();
		var node = type === 'custom' ? mapping : { '@type': type };
		Object.keys( mapping ).forEach( function ( field ) {
			node[ field ] = mapping[ field ];
		} );
		if ( hasInvalidJson ) {
			if ( issuesRoot ) {
				issuesRoot.innerHTML = '';
				issuesRoot.className = 'seoistic-schema-issues has-issues';
				var error = document.createElement( 'div' );
				error.textContent = ( settings.i18n && settings.i18n.invalidJson ) || 'Invalid JSON';
				issuesRoot.appendChild( error );
			}
			preview.textContent = '';
			hiddenMapping.value = '';
			return;
		}
		var issues = validationIssues( node );
		if ( type === 'custom' && ! hasInvalidJson && ! node[ '@type' ] ) {
			if ( issuesRoot ) {
				issuesRoot.innerHTML = '';
				issuesRoot.className = 'seoistic-schema-issues has-issues';
				var issue = document.createElement( 'div' );
				issue.textContent = ( settings.i18n && settings.i18n.missingType ) || 'Missing @type';
				issuesRoot.appendChild( issue );
			}
			preview.textContent = JSON.stringify( mapping, null, 2 );
			hiddenMapping.value = JSON.stringify( mapping );
			return;
		}
		try {
			preview.textContent = JSON.stringify( node, null, 2 );
			hiddenMapping.value = JSON.stringify( mapping );
		} catch ( error ) {
			issues = [ ( settings.i18n && settings.i18n.invalidJson ) || 'Invalid JSON' ];
			hiddenMapping.value = '';
		}
		if ( issuesRoot ) {
			issuesRoot.innerHTML = '';
			issuesRoot.className = issues.length ? 'seoistic-schema-issues has-issues' : 'seoistic-schema-issues';
			issues.forEach( function ( issue ) {
				var item = document.createElement( 'div' );
				item.textContent = issue;
				issuesRoot.appendChild( item );
			} );
		}
	}

	function serializeRules() {
		if ( ! hiddenRules || ! form ) {
			return;
		}
		var rules = {};
		[ 'post_types', 'taxonomies', 'templates' ].forEach( function ( group ) {
			rules[ group ] = Array.prototype.map.call( form.querySelectorAll( '[name="rules[' + group + '][]"]:checked' ), function ( input ) {
				return input.value;
			} );
		} );
		rules.url_patterns = ( patterns && patterns.value ? patterns.value : '' ).split( /\r?\n/ ).map( function ( line ) {
			return line.trim();
		} ).filter( Boolean );
		hiddenRules.value = JSON.stringify( rules );
	}

	function initTabs() {
		document.querySelectorAll( '[data-seoistic-preview-tab]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				document.querySelectorAll( '[data-seoistic-preview-tab]' ).forEach( function ( item ) {
					item.classList.toggle( 'is-active', item === button );
				} );
				if ( preview ) {
					preview.hidden = 'preview' !== button.dataset.seoisticPreviewTab;
				}
				if ( issuesRoot ) {
					issuesRoot.hidden = 'issues' !== button.dataset.seoisticPreviewTab;
				}
			} );
		} );
		issuesRoot.hidden = true;
	}

	function initTypes() {
		if ( ! typeSelect ) {
			return;
		}
		Object.keys( settings.typeLabels || {} ).forEach( function ( type ) {
			var option = document.createElement( 'option' );
			option.value = type;
			option.textContent = label( type );
			typeSelect.appendChild( option );
		} );
		typeSelect.addEventListener( 'change', renderFields );
	}

	function initVariables() {
		variableSelect.className = 'seoistic-variable-select';
		var placeholder = document.createElement( 'option' );
		placeholder.value = '';
		placeholder.textContent = ( settings.i18n && settings.i18n.insertVariable ) || 'Insert variable';
		variableSelect.appendChild( placeholder );
		( settings.variables || [] ).forEach( function ( name ) {
			var option = document.createElement( 'option' );
			option.value = name;
			option.textContent = '{' + name + '}';
			variableSelect.appendChild( option );
		} );
		variableSelect.addEventListener( 'change', function () {
			if ( ! variableSelect.value ) {
				return;
			}
			var input = lastInput || fieldsRoot.querySelector( 'input, textarea' );
			if ( input ) {
				var token = '{' + variableSelect.value + '}';
				var start = input.selectionStart || input.value.length;
				var end = input.selectionEnd || input.value.length;
				input.value = input.value.slice( 0, start ) + token + input.value.slice( end );
				input.focus();
				input.selectionStart = input.selectionEnd = start + token.length;
				update();
			}
			variableSelect.value = '';
		} );
		fieldsRoot.parentNode.insertBefore( variableSelect, fieldsRoot );
		fieldsRoot.addEventListener( 'focusin', function ( event ) {
			if ( event.target.matches( 'input, textarea' ) ) {
				lastInput = event.target;
			}
		} );
	}

	function loadBlock( block ) {
		if ( ! form || ! block ) {
			return;
		}
		idInput.value = block.id || '';
		form.title.value = block.title || '';
		typeSelect.value = block.type || 'Article';
		var rules = block.rules || {};
		[ 'post_types', 'taxonomies', 'templates' ].forEach( function ( group ) {
			var values = rules[ group ] || [];
			Array.prototype.forEach.call( form.querySelectorAll( '[name="rules[' + group + '][]"]' ), function ( input ) {
				input.checked = values.indexOf( input.value ) !== -1;
			} );
		} );
		if ( patterns ) {
			patterns.value = ( rules.url_patterns || [] ).join( '\n' );
		}
		form.active.checked = Boolean( block.active );
		renderFields();
		var mapping = block.mapping || {};
		if ( typeSelect.value === 'custom' ) {
			document.getElementById( 'seoistic-schema-custom-json' ).value = JSON.stringify( mapping, null, 2 );
		} else {
			Object.keys( mapping ).forEach( function ( field ) {
				var input = fieldsRoot.querySelector( '[data-schema-field="' + field + '"]' );
				if ( input ) {
					input.value = typeof mapping[ field ] === 'string' ? mapping[ field ] : JSON.stringify( mapping[ field ] );
				}
			} );
		}
		update();
		window.scrollTo( { top: 0, behavior: 'auto' } );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! form ) {
			return;
		}
		initTypes();
		renderFields();
		initVariables();
		initTabs();
		serializeRules();
		form.addEventListener( 'submit', function ( event ) {
			update();
			serializeRules();
			if ( ! hiddenMapping.value || ( issuesRoot && issuesRoot.classList.contains( 'has-issues' ) ) ) {
				event.preventDefault();
				window.alert( ( settings.i18n && settings.i18n.validationFailed ) || 'Validation failed' );
				issuesRoot.hidden = false;
			}
		} );
		patterns && patterns.addEventListener( 'input', serializeRules );
		form.addEventListener( 'change', function ( event ) {
			if ( event.target.name && event.target.name.indexOf( 'rules[' ) === 0 ) {
				serializeRules();
			}
		} );
		document.querySelectorAll( '[data-seoistic-edit]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				try {
					loadBlock( JSON.parse( button.dataset.seoisticEdit ) );
				} catch ( error ) {
					window.alert( ( settings.i18n && settings.i18n.invalidJson ) || 'Invalid JSON' );
				}
			} );
		} );
	} );
} )();
