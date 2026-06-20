/* Galerias DOMI — Edit Page */
( function () {
	'use strict';

	/* ── Animaciones de altura ─────────────────────────────────────────
	 *
	 * CSS no puede transicionar height: 0 → auto, así que medimos el
	 * scrollHeight real del elemento y animamos entre valores concretos.
	 * Al terminar la apertura restauramos "auto" para que el panel se
	 * adapte si su contenido crece dinámicamente en el futuro.
	 * ----------------------------------------------------------------- */

	/**
	 * Despliega un panel midiendo su altura real.
	 *
	 * @param {HTMLElement} el
	 */
	function slideDown( el ) {
		// Mostrar pero medir antes de que el navegador pinte.
		el.removeAttribute( 'hidden' );
		el.style.height   = '0';
		el.style.opacity  = '0';
		el.style.overflow = 'hidden';

		// Forzar reflow para que la transición salga desde 0.
		// eslint-disable-next-line no-unused-expressions
		el.getBoundingClientRect();

		const target = el.scrollHeight;

		el.style.transition = 'height .28s cubic-bezier(0.4, 0, 0.2, 1), opacity .28s ease';
		el.style.height     = target + 'px';
		el.style.opacity    = '1';

		el.addEventListener( 'transitionend', function finish( e ) {
			if ( e.propertyName !== 'height' ) {
				return;
			}
			// Restaurar "auto" para que el panel sea flexible.
			el.style.height     = '';
			el.style.overflow   = '';
			el.style.transition = '';
			el.removeEventListener( 'transitionend', finish );
		} );
	}

	/**
	 * Colapsa un panel hasta altura 0 y lo oculta al terminar.
	 *
	 * @param {HTMLElement} el
	 * @param {Function}    [onDone]  Callback opcional al finalizar.
	 */
	function slideUp( el, onDone ) {
		el.style.height   = el.scrollHeight + 'px';
		el.style.overflow = 'hidden';

		// Forzar reflow.
		// eslint-disable-next-line no-unused-expressions
		el.getBoundingClientRect();

		el.style.transition = 'height .2s cubic-bezier(0.4, 0, 1, 1), opacity .2s ease';
		el.style.height     = '0';
		el.style.opacity    = '0';

		el.addEventListener( 'transitionend', function finish( e ) {
			if ( e.propertyName !== 'height' ) {
				return;
			}
			el.setAttribute( 'hidden', '' );
			el.style.height     = '';
			el.style.overflow   = '';
			el.style.opacity    = '';
			el.style.transition = '';
			el.removeEventListener( 'transitionend', finish );
			if ( onDone ) {
				onDone();
			}
		} );
	}

	/* ── Lógica de tabs ───────────────────────────────────────────────── */

	document.addEventListener( 'DOMContentLoaded', function () {
		const tabsWrapper = document.querySelector( '.gd-tabs' );
		if ( ! tabsWrapper ) {
			return;
		}

		const btns   = tabsWrapper.querySelectorAll( '.gd-tab-btn' );
		const panels = tabsWrapper.querySelectorAll( '.gd-tab-panel' );

		/**
		 * Activa el tab indicado animando entrada y salida.
		 *
		 * @param {string} targetId  Valor de data-tab del botón destino.
		 */
		function activateTab( targetId ) {
			btns.forEach( function ( btn ) {
				const isTarget = btn.dataset.tab === targetId;
				btn.classList.toggle( 'is-active', isTarget );
				btn.setAttribute( 'aria-expanded', isTarget ? 'true' : 'false' );
			} );

			panels.forEach( function ( panel ) {
				const isTarget = panel.id === 'gd-tab-' + targetId;

				if ( isTarget && panel.hasAttribute( 'hidden' ) ) {
					slideDown( panel );
				} else if ( ! isTarget && ! panel.hasAttribute( 'hidden' ) ) {
					slideUp( panel );
				}
			} );
		}

		// Click — toggle: tab activo se colapsa al volver a clickearlo.
		btns.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				if ( btn.classList.contains( 'is-active' ) ) {
					btn.classList.remove( 'is-active' );
					btn.setAttribute( 'aria-expanded', 'false' );
					const panel = document.getElementById( 'gd-tab-' + btn.dataset.tab );
					if ( panel && ! panel.hasAttribute( 'hidden' ) ) {
						slideUp( panel );
					}
				} else {
					activateTab( btn.dataset.tab );
				}
			} );
		} );

		// Teclado: ↑ ↓ navegan entre secciones; Home / End van al primero / último.
		tabsWrapper.addEventListener( 'keydown', function ( e ) {
			if ( ! [ 'ArrowUp', 'ArrowDown', 'Home', 'End' ].includes( e.key ) ) {
				return;
			}

			const list       = Array.from( btns );
			const currentIdx = list.findIndex( b => b === document.activeElement );
			if ( currentIdx === -1 ) {
				return;
			}

			let nextIdx;
			if ( e.key === 'ArrowDown' ) {
				nextIdx = ( currentIdx + 1 ) % list.length;
			} else if ( e.key === 'ArrowUp' ) {
				nextIdx = ( currentIdx - 1 + list.length ) % list.length;
			} else if ( e.key === 'Home' ) {
				nextIdx = 0;
			} else {
				nextIdx = list.length - 1;
			}

			e.preventDefault();
			list[ nextIdx ].focus();
		} );

		// Guardar: re-indexar primero, colapsar tab abierto, luego enviar.
		const saveBtn = document.querySelector( '.gd-btn-save' );
		if ( saveBtn ) {
			saveBtn.addEventListener( 'click', function ( e ) {
				// Garantizar índices correctos ANTES de cualquier cambio en el DOM.
				reindex();
				reindexImages();

				const openPanel = tabsWrapper.querySelector( '.gd-tab-panel:not([hidden])' );
				if ( ! openPanel ) {
					return; // Ningún tab abierto → submit normal.
				}

				e.preventDefault();

				btns.forEach( function ( b ) {
					b.classList.remove( 'is-active' );
					b.setAttribute( 'aria-expanded', 'false' );
				} );

				// Clonar los datos de filtros a campos hidden fuera del panel
				// para garantizar que lleguen al servidor aunque el panel quede [hidden].
				const form        = saveBtn.closest( 'form' );
				const filterInputs = openPanel.querySelectorAll(
					'.gd-filter-row__edit-mode input[name^="gd_filters"]'
				);
				filterInputs.forEach( function ( input ) {
					const clone = document.createElement( 'input' );
					clone.type  = 'hidden';
					clone.name  = input.name;
					clone.value = input.value;
					form.appendChild( clone );
					input.name = ''; // Deshabilitar el original para evitar duplicados.
				} );

				slideUp( openPanel, function () {
					form.submit();
				} );
			} );
		}
	} );

	/* ── Filtros: toggle → habilita / deshabilita campos dependientes ── */

	const filtersToggle          = document.getElementById( 'gd-filters-enabled' );
	const filterStyleField       = document.getElementById( 'gd-filter-style-field' );
	const filterStyleSelect      = document.getElementById( 'gd-filter-style' );
	const variantButtonsField    = document.getElementById( 'gd-filter-variant-buttons-field' );
	const variantSelectField     = document.getElementById( 'gd-filter-variant-select-field' );
	const filterShapeField       = document.getElementById( 'gd-filter-shape-field' );
	const filterShapeSelect      = document.getElementById( 'gd-filter-shape' );
	const filtersAvailableField  = document.getElementById( 'gd-filters-available-field' );

	if ( filtersToggle && filterStyleField && filterStyleSelect ) {

		// Tipo de filtro activo ('buttons' | 'select').
		function currentType() {
			return filterStyleSelect.value;
		}

		// Variante seleccionada en el picker de botones.
		function buttonsVariant() {
			const checked = document.querySelector( 'input[name="gd_filter_variant_buttons"]:checked' );
			return checked ? checked.value : 'solid';
		}

		// Habilita/inhabilita los radios de un picker de variante.
		function setVariantField( field, enabled ) {
			if ( ! field ) {
				return;
			}
			field.classList.toggle( 'is-disabled', ! enabled );
			field.setAttribute( 'aria-disabled', enabled ? 'false' : 'true' );
			field.querySelectorAll( 'input[type="radio"]' ).forEach( function ( radio ) {
				radio.setAttribute( 'tabindex', enabled ? '' : '-1' );
			} );
		}

		// Muestra el picker del tipo activo y oculta el otro; coloca la "Forma".
		function syncTypeUI() {
			const enabled = filtersToggle.checked;
			const type    = currentType();

			if ( variantButtonsField ) {
				variantButtonsField.classList.toggle( 'gd-hidden', 'buttons' !== type );
				setVariantField( variantButtonsField, enabled && 'buttons' === type );
			}
			if ( variantSelectField ) {
				variantSelectField.classList.toggle( 'gd-hidden', 'select' !== type );
				setVariantField( variantSelectField, enabled && 'select' === type );
			}

			// La forma solo existe para botones; deshabilitada si la variante es minimal.
			if ( filterShapeField ) {
				const shapeHidden   = 'buttons' !== type;
				const shapeDisabled = ! enabled || shapeHidden || 'minimal' === buttonsVariant();
				filterShapeField.classList.toggle( 'gd-hidden', shapeHidden );
				filterShapeField.classList.toggle( 'is-disabled', shapeDisabled );
				filterShapeField.setAttribute( 'aria-disabled', shapeDisabled ? 'true' : 'false' );
				if ( filterShapeSelect ) {
					filterShapeSelect.setAttribute( 'tabindex', shapeDisabled ? '-1' : '' );
				}
			}
		}

		function syncFilterStyle() {
			const enabled = filtersToggle.checked;

			filterStyleField.classList.toggle( 'is-disabled', ! enabled );
			filterStyleField.setAttribute( 'aria-disabled', enabled ? 'false' : 'true' );
			filterStyleSelect.setAttribute( 'tabindex', enabled ? '' : '-1' );

			if ( filtersAvailableField ) {
				filtersAvailableField.classList.toggle( 'is-disabled', ! enabled );
				filtersAvailableField.setAttribute( 'aria-disabled', enabled ? 'false' : 'true' );
			}

			syncTypeUI();

			// Mostrar/ocultar la columna de filtro en cada fila de imagen.
			document.querySelectorAll( '.gd-image-row__filter' ).forEach( function ( col ) {
				col.classList.toggle( 'gd-hidden', ! enabled );
			} );
		}

		filtersToggle.addEventListener( 'change', syncFilterStyle );

		// Al cambiar de tipo, se muestra el picker correspondiente y la forma.
		filterStyleSelect.addEventListener( 'change', syncTypeUI );

		// La forma se recalcula al cambiar la variante de botones.
		document.querySelectorAll( 'input[name="gd_filter_variant_buttons"]' ).forEach( function ( radio ) {
			radio.addEventListener( 'change', syncTypeUI );
		} );

		// Estado inicial al cargar la página.
		syncFilterStyle();
	}

	/* ── Paginación: toggle → muestra / oculta picker de filas ──────── */

	const paginationToggle    = document.getElementById( 'gd-pagination-enabled' );
	const paginationRowsField = document.getElementById( 'gd-pagination-rows-field' );

	if ( paginationToggle && paginationRowsField ) {
		function syncPagination() {
			const enabled = paginationToggle.checked;
			paginationRowsField.classList.toggle( 'is-disabled', ! enabled );
			paginationRowsField.setAttribute( 'aria-disabled', enabled ? 'false' : 'true' );
		}
		paginationToggle.addEventListener( 'change', syncPagination );
		syncPagination();
	}

	/* ── Filter repeater ─────────────────────────────────────────────── */

	const filtersList  = document.getElementById( 'gd-filters-list' );
	const filterAddBtn = document.getElementById( 'gd-filter-add' );

	// Definidas en scope del IIFE para que sean accesibles desde el handler de guardado.
	function reindex() {
		if ( ! filtersList ) { return; }

		filtersList
			.querySelectorAll( '.gd-filter-row:not(.gd-filter-row--default)' )
			.forEach( function ( row, i ) {
				const nameInput = row.querySelector( '.gd-filter-name' );
				const idInput   = row.querySelector( '.gd-filter-id' );
				if ( nameInput ) { nameInput.name = 'gd_filters[' + i + '][name]'; }
				if ( idInput )   { idInput.name   = 'gd_filters[' + i + '][id]'; }
			} );

		// Guardar la posición de la fila "Todos" (cuántos filtros regulares hay antes).
		const posInput  = document.getElementById( 'gd-todos-position' );
		const todosEl   = filtersList.querySelector( '.gd-filter-row--default' );
		if ( posInput && todosEl ) {
			const allRows = Array.from( filtersList.querySelectorAll( '.gd-filter-row' ) );
			const todosIdx = allRows.indexOf( todosEl );
			const position = allRows
				.slice( 0, todosIdx )
				.filter( function ( r ) { return ! r.classList.contains( 'gd-filter-row--default' ); } )
				.length;
			posInput.value = position;
		}
	}

	function reindexImages() {
		var list = document.getElementById( 'gd-images-list' );
		if ( ! list ) { return; }
		list.querySelectorAll( '.gd-image-row' ).forEach( function ( row, i ) {
			var idInput  = row.querySelector( 'input[type="hidden"]' );
			var filterEl = row.querySelector( '.gd-image-filter-select' );
			if ( idInput )  { idInput.name  = 'gd_images[' + i + '][id]'; }
			if ( filterEl ) { filterEl.name = 'gd_images[' + i + '][filter]'; }
		} );
	}

	if ( filtersList && filterAddBtn ) {

		let rowCounter        = filtersList.querySelectorAll( '.gd-filter-row:not(.gd-filter-row--default)' ).length;
		let currentEditingRow = null;

		// ── Utilidades ─────────────────────────────────────────────────

		function slugify( text ) {
			return text
				.toLowerCase()
				.normalize( 'NFD' )
				.replace( /[̀-ͯ]/g, '' )
				.replace( /[^a-z0-9\s-]/g, '' )
				.trim()
				.replace( /\s+/g, '-' )
				.replace( /-+/g, '-' );
		}

		function setButtonMode( mode ) {
			if ( 'new' === mode ) {
				filterAddBtn.textContent  = 'Generar filtro';
				filterAddBtn.dataset.mode = 'new';
			} else if ( 'edit' === mode ) {
				filterAddBtn.textContent  = 'Guardar filtro';
				filterAddBtn.dataset.mode = 'edit';
			} else {
				filterAddBtn.textContent  = '+ Agregar filtro';
				filterAddBtn.dataset.mode = 'add';
			}
		}

		// ── Confirmar fila ─────────────────────────────────────────────

		function confirmRow( row ) {
			const nameInput = row.querySelector( '.gd-filter-name' );
			const idInput   = row.querySelector( '.gd-filter-id' );
			let valid       = true;

			if ( ! nameInput.value.trim() ) {
				nameInput.classList.add( 'has-error' );
				valid = false;
			} else {
				nameInput.classList.remove( 'has-error' );
			}

			if ( ! idInput.value.trim() ) {
				idInput.classList.add( 'has-error' );
				valid = false;
			} else {
				idInput.classList.remove( 'has-error' );
			}

			if ( ! valid ) {
				( nameInput.value.trim() ? idInput : nameInput ).focus();
				return;
			}

			row.querySelector( '.gd-filter-row__fixed-name' ).textContent = nameInput.value.trim();
			row.querySelector( '.gd-filter-row__fixed-id' ).textContent   = idInput.value.trim();

			row.dataset.state = 'confirmed';
			delete row.dataset.new;
			currentEditingRow = null;
			setButtonMode( 'add' );
			reindex();
		}

		// ── Poner fila en modo edición ─────────────────────────────────

		function editRow( row ) {
			// Si hay una fila nueva vacía pendiente, descartarla.
			if ( currentEditingRow && currentEditingRow !== row && currentEditingRow.dataset.new ) {
				const pendingName = currentEditingRow.querySelector( '.gd-filter-name' ).value.trim();
				const pendingId   = currentEditingRow.querySelector( '.gd-filter-id' ).value.trim();
				if ( ! pendingName && ! pendingId ) {
					currentEditingRow.remove();
					reindex();
				}
			}

			row.dataset.state = 'editing';
			currentEditingRow = row;
			setButtonMode( 'edit' );

			// Defer focus para que el display:flex ya esté activo.
			requestAnimationFrame( function () {
				row.querySelector( '.gd-filter-name' ).focus();
			} );
		}

		// ── Drag-and-drop reordering ──────────────────────────────────

		let dragSrcRow = null;

		function bindDragHandle( row ) {
			row.querySelectorAll( '.gd-filter-handle' ).forEach( function ( handle ) {
				handle.addEventListener( 'mousedown', function () {
					row.setAttribute( 'draggable', 'true' );
				} );
			} );

			row.addEventListener( 'dragstart', function ( e ) {
				dragSrcRow = row;
				e.dataTransfer.effectAllowed = 'move';
				requestAnimationFrame( function () {
					row.classList.add( 'is-dragging' );
				} );
			} );

			row.addEventListener( 'dragend', function () {
				row.removeAttribute( 'draggable' );
				row.classList.remove( 'is-dragging' );
				dragSrcRow = null;
				reindex();
			} );
		}

		filtersList.addEventListener( 'dragover', function ( e ) {
			e.preventDefault();
			if ( ! dragSrcRow ) { return; }
			e.dataTransfer.dropEffect = 'move';

			const targetRow = e.target.closest( '.gd-filter-row' );
			if ( ! targetRow || targetRow === dragSrcRow ) { return; }

			const rect   = targetRow.getBoundingClientRect();
			const before = e.clientY < rect.top + rect.height / 2;

			if ( before ) {
				if ( targetRow.previousElementSibling !== dragSrcRow ) {
					filtersList.insertBefore( dragSrcRow, targetRow );
				}
			} else {
				if ( targetRow.nextElementSibling !== dragSrcRow ) {
					filtersList.insertBefore( dragSrcRow, targetRow.nextElementSibling );
				}
			}
		} );

		// ── Crear nueva fila ───────────────────────────────────────────

		function makeHandle() {
			const h = document.createElement( 'button' );
			h.type      = 'button';
			h.className = 'gd-filter-handle';
			h.setAttribute( 'aria-label', 'Mover filtro' );
			h.setAttribute( 'tabindex', '-1' );
			h.textContent = '⣿';
			return h;
		}

		function createRow() {
			const idx = rowCounter++;
			const row = document.createElement( 'div' );
			row.className     = 'gd-filter-row';
			row.dataset.state = 'editing';
			row.dataset.new   = 'true';

			// Modo edición: handle + inputs
			const editMode = document.createElement( 'div' );
			editMode.className = 'gd-filter-row__edit-mode';

			const fields = document.createElement( 'div' );
			fields.className = 'gd-filter-row__fields';

			const nameInput = document.createElement( 'input' );
			nameInput.type        = 'text';
			nameInput.className   = 'gd-filter-name';
			nameInput.name        = 'gd_filters[' + idx + '][name]';
			nameInput.placeholder = 'Nombre';

			const idInput = document.createElement( 'input' );
			idInput.type         = 'text';
			idInput.className    = 'gd-filter-id';
			idInput.name         = 'gd_filters[' + idx + '][id]';
			idInput.placeholder  = 'id-del-filtro';
			idInput.dataset.auto = 'true';

			fields.appendChild( nameInput );
			fields.appendChild( idInput );
			editMode.appendChild( makeHandle() );
			editMode.appendChild( fields );

			// Modo vista: handle + etiquetas + acciones
			const viewMode = document.createElement( 'div' );
			viewMode.className = 'gd-filter-row__view-mode';

			const nameLabel = document.createElement( 'span' );
			nameLabel.className = 'gd-filter-row__fixed-name';

			const idLabel = document.createElement( 'span' );
			idLabel.className = 'gd-filter-row__fixed-id';

			const viewActions = document.createElement( 'div' );
			viewActions.className = 'gd-filter-row__view-actions';

			const editBtn = document.createElement( 'button' );
			editBtn.type        = 'button';
			editBtn.className   = 'gd-filter-edit';
			editBtn.textContent = 'Editar';

			const deleteBtn = document.createElement( 'button' );
			deleteBtn.type        = 'button';
			deleteBtn.className   = 'gd-filter-delete';
			deleteBtn.textContent = 'Eliminar';

			viewActions.appendChild( editBtn );
			viewActions.appendChild( deleteBtn );

			viewMode.appendChild( makeHandle() );
			viewMode.appendChild( nameLabel );
			viewMode.appendChild( idLabel );
			viewMode.appendChild( viewActions );

			row.appendChild( editMode );
			row.appendChild( viewMode );

			// Eventos inputs
			nameInput.addEventListener( 'input', function () {
				nameInput.classList.remove( 'has-error' );
				if ( 'true' === idInput.dataset.auto ) {
					idInput.value = slugify( nameInput.value );
				}
			} );

			idInput.addEventListener( 'input', function () {
				idInput.classList.remove( 'has-error' );
				idInput.dataset.auto = 'false';
			} );

			idInput.addEventListener( 'blur', function () {
				if ( '' === idInput.value ) {
					idInput.dataset.auto = 'true';
					idInput.value = slugify( nameInput.value );
				}
			} );

			editBtn.addEventListener( 'click', function () { editRow( row ); } );

			deleteBtn.addEventListener( 'click', function () {
				if ( currentEditingRow === row ) { currentEditingRow = null; }
				if ( ! filtersList.querySelector( '.gd-filter-row[data-state="editing"]' ) ) {
					setButtonMode( 'add' );
				}
				row.remove();
				reindex();
			} );

			bindDragHandle( row );
			return row;
		}

		// ── Vincular eventos a filas ya renderizadas por PHP ───────────

		filtersList
			.querySelectorAll( '.gd-filter-row:not(.gd-filter-row--default)' )
			.forEach( function ( row ) {
				const nameInput = row.querySelector( '.gd-filter-name' );
				const idInput   = row.querySelector( '.gd-filter-id' );
				const editBtn   = row.querySelector( '.gd-filter-edit' );
				const deleteBtn = row.querySelector( '.gd-filter-delete' );

				nameInput.addEventListener( 'input', function () {
					nameInput.classList.remove( 'has-error' );
					if ( 'true' === idInput.dataset.auto ) {
						idInput.value = slugify( nameInput.value );
					}
				} );

				idInput.addEventListener( 'input', function () {
					idInput.classList.remove( 'has-error' );
					idInput.dataset.auto = 'false';
				} );

				idInput.addEventListener( 'blur', function () {
					if ( '' === idInput.value ) {
						idInput.dataset.auto = 'true';
						idInput.value = slugify( nameInput.value );
					}
				} );

				editBtn.addEventListener( 'click', function () { editRow( row ); } );

				deleteBtn.addEventListener( 'click', function () {
					if ( currentEditingRow === row ) { currentEditingRow = null; }
					if ( ! filtersList.querySelector( '.gd-filter-row[data-state="editing"]' ) ) {
						setButtonMode( 'add' );
					}
					row.remove();
					reindex();
				} );

				bindDragHandle( row );
			} );

		// ── Drag de la fila "Todos" ────────────────────────────────────

		const todosRow = filtersList.querySelector( '.gd-filter-row--default' );
		if ( todosRow ) { bindDragHandle( todosRow ); }

		// ── Checkbox "Mostrar Todos" ───────────────────────────────────

		const showTodosCheckbox = document.getElementById( 'gd-show-todos' );
		if ( showTodosCheckbox && todosRow ) {
			showTodosCheckbox.addEventListener( 'change', function () {
				todosRow.classList.toggle( 'is-todos-hidden', ! showTodosCheckbox.checked );
			} );
		}

		// ── Botón principal ────────────────────────────────────────────

		filterAddBtn.dataset.mode = 'add';

		filterAddBtn.addEventListener( 'click', function () {
			if ( 'add' === filterAddBtn.dataset.mode ) {
				const row = createRow();
				filtersList.appendChild( row );
				currentEditingRow = row;
				setButtonMode( 'new' );
				row.querySelector( '.gd-filter-name' ).focus();
			} else if ( currentEditingRow ) {
				confirmRow( currentEditingRow );
			}
		} );
	}

	/* ── Column picker: opción personalizada ──────────────────────────── */

	const customRadio = document.getElementById( 'gd-col-custom' );
	const customInput = document.querySelector( '.gd-col-custom-input' );

	if ( customRadio && customInput ) {

		/**
		 * Fuerza el valor dentro del rango [1, 8] y sincroniza el radio.
		 */
		function syncCustomValue() {
			let val = parseInt( customInput.value, 10 );
			if ( isNaN( val ) || val < 1 ) { val = 1; }
			if ( val > 8 )                 { val = 8; }
			customInput.value  = val;
			customRadio.value  = val;
			customRadio.checked = true;
		}

		// Hacer foco en el input → seleccionar el radio custom.
		customInput.addEventListener( 'focus', function () {
			customRadio.checked = true;
		} );

		// Mientras escribe → sincronizar en tiempo real.
		customInput.addEventListener( 'input', syncCustomValue );

		// Al salir → corregir y formatear el valor final.
		customInput.addEventListener( 'blur', syncCustomValue );

		// Evitar que el click sobre el input propague al label dos veces.
		customInput.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			this.focus();
		} );
	}

	/* ── Modal de vista previa ──────────────────────────────────── */

	var modal        = document.getElementById( 'gd-image-modal' );
	var modalImg     = document.getElementById( 'gd-modal-img' );
	var modalTitle   = document.getElementById( 'gd-modal-title' );
	var modalClose   = document.getElementById( 'gd-modal-close' );
	var modalOverlay = document.getElementById( 'gd-modal-overlay' );

	function openModal( src, title ) {
		if ( ! modal ) { return; }
		modalImg.src           = src;
		modalImg.alt           = title;
		modalTitle.textContent = title;
		modal.removeAttribute( 'hidden' );
		requestAnimationFrame( function () {
			requestAnimationFrame( function () {
				modal.classList.add( 'is-open' );
			} );
		} );
	}

	function closeModal() {
		if ( ! modal ) { return; }
		modal.classList.remove( 'is-open' );
		modal.addEventListener( 'transitionend', function onDone() {
			modal.setAttribute( 'hidden', '' );
			modalImg.src = '';
			modal.removeEventListener( 'transitionend', onDone );
		}, { once: true } );
	}

	if ( modalClose )   { modalClose.addEventListener( 'click', closeModal ); }
	if ( modalOverlay ) { modalOverlay.addEventListener( 'click', closeModal ); }
	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key && modal && ! modal.hasAttribute( 'hidden' ) ) {
			closeModal();
		}
	} );

	// Vincular botones renderizados por PHP
	document.querySelectorAll( '.gd-image-preview-btn' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			openModal( btn.dataset.src, btn.dataset.title );
		} );
	} );

	/* ── Imágenes: Media Library + lista ────────────────────────── */

	var addImagesBtn  = document.getElementById( 'gd-btn-add-images' );
	var dropzoneEl    = document.getElementById( 'gd-dropzone' );
	var imagesList    = document.getElementById( 'gd-images-list' );
	var imagesCountEl = document.getElementById( 'gd-images-count' );

	if ( addImagesBtn && imagesList && window.wp && window.wp.media ) {

		var mediaFrame = null;
		var dragSrcImg = null;

		// ── Abrir la biblioteca de medios ──────────────────────

		function openMediaFrame() {
			if ( ! mediaFrame ) {
				mediaFrame = wp.media( {
					title   : 'Seleccionar imágenes para la galería',
					button  : { text: 'Agregar a la galería' },
					multiple: true,
					library : { type: 'image' },
				} );

				mediaFrame.on( 'select', function () {
					var selection = mediaFrame.state().get( 'selection' );
					selection.each( function ( attachment ) {
						var a = attachment.toJSON();
						if ( ! imagesList.querySelector( '[data-id="' + a.id + '"]' ) ) {
							var thumbUrl   = ( a.sizes && a.sizes.thumbnail ) ? a.sizes.thumbnail.url : a.url;
							var previewUrl = ( a.sizes && a.sizes.large )     ? a.sizes.large.url     : a.url;
							var newRow     = createImageRow( a.id, thumbUrl, a.title || a.filename || '' );
							var pBtn       = newRow.querySelector( '.gd-image-preview-btn' );
							if ( pBtn ) { pBtn.dataset.src = previewUrl; }
							imagesList.appendChild( newRow );
						}
					} );
					reindexImages();
					syncImagesUI();
				} );
			}
			mediaFrame.open();
		}

		addImagesBtn.addEventListener( 'click', openMediaFrame );

		if ( dropzoneEl ) {
			dropzoneEl.addEventListener( 'click', openMediaFrame );
			dropzoneEl.addEventListener( 'keydown', function ( e ) {
				if ( 'Enter' === e.key || ' ' === e.key ) {
					e.preventDefault();
					openMediaFrame();
				}
			} );
		}

		// ── Leer filtros confirmados del repeater ──────────────

		function getFilterOptions() {
			var options = [];
			if ( ! filtersList ) { return options; }
			filtersList
				.querySelectorAll( '.gd-filter-row[data-state="confirmed"]' )
				.forEach( function ( row ) {
					var nameEl = row.querySelector( '.gd-filter-row__fixed-name' );
					var idEl   = row.querySelector( '.gd-filter-row__fixed-id' );
					if ( nameEl && idEl && nameEl.textContent.trim() && idEl.textContent.trim() ) {
						options.push( {
							name: nameEl.textContent.trim(),
							id  : idEl.textContent.trim(),
						} );
					}
				} );
			return options;
		}

		// ── Reconstruir los <option> de todos los selects ──────

		function refreshFilterSelects() {
			var options = getFilterOptions();
			document.querySelectorAll( '.gd-image-filter-select' ).forEach( function ( select ) {
				var current = select.value;
				select.innerHTML = '<option value="todos">Todos</option>';
				options.forEach( function ( opt ) {
					var el         = document.createElement( 'option' );
					el.value       = opt.id;
					el.textContent = opt.name;
					select.appendChild( el );
				} );
				select.value = current;
				if ( ! select.value ) { select.value = 'todos'; }
			} );
		}

		// ── Crear el <select> de filtro para una fila ──────────

		function buildFilterSelect() {
			var sel       = document.createElement( 'select' );
			sel.className = 'gd-image-filter-select';
			sel.name      = 'gd_images[0][filter]'; // reindexImages() lo corrige

			var todosOpt         = document.createElement( 'option' );
			todosOpt.value       = 'todos';
			todosOpt.textContent = 'Todos';
			sel.appendChild( todosOpt );

			getFilterOptions().forEach( function ( opt ) {
				var el         = document.createElement( 'option' );
				el.value       = opt.id;
				el.textContent = opt.name;
				sel.appendChild( el );
			} );

			return sel;
		}

		// ── Crear una fila de imagen ───────────────────────────

		function createImageRow( id, thumbUrl, title ) {
			var row        = document.createElement( 'div' );
			row.className  = 'gd-image-row';
			row.dataset.id = String( id );

			// Handle de arrastre
			var handle       = document.createElement( 'button' );
			handle.type      = 'button';
			handle.className = 'gd-filter-handle';
			handle.setAttribute( 'aria-label', 'Mover imagen' );
			handle.setAttribute( 'tabindex', '-1' );
			handle.innerHTML = '&#x2847;';

			// Miniatura
			var thumbWrap       = document.createElement( 'div' );
			thumbWrap.className = 'gd-image-row__thumb';
			var img             = document.createElement( 'img' );
			img.src             = thumbUrl;
			img.alt             = title;
			img.draggable       = false;
			thumbWrap.appendChild( img );

			// Info: nombre + filtro
			var info       = document.createElement( 'div' );
			info.className = 'gd-image-row__info';

			var nameEl         = document.createElement( 'span' );
			nameEl.className   = 'gd-image-row__name';
			nameEl.textContent = title || '(sin título)';

			var filterDiv       = document.createElement( 'div' );
			filterDiv.className = 'gd-image-row__filter';

			// Respetar el estado del toggle de filtros
			var toggle = document.getElementById( 'gd-filters-enabled' );
			if ( toggle && ! toggle.checked ) {
				filterDiv.classList.add( 'gd-hidden' );
			}

			filterDiv.appendChild( buildFilterSelect() );
			info.appendChild( nameEl );
			info.appendChild( filterDiv );

			// Botón Vista previa
			var previewBtn           = document.createElement( 'button' );
			previewBtn.type          = 'button';
			previewBtn.className     = 'gd-image-preview-btn';
			previewBtn.dataset.src   = thumbUrl;
			previewBtn.dataset.title = title;
			previewBtn.textContent   = 'Vista previa';
			previewBtn.addEventListener( 'click', function () {
				openModal( previewBtn.dataset.src, previewBtn.dataset.title );
			} );

			// Botón eliminar
			var removeBtn       = document.createElement( 'button' );
			removeBtn.type      = 'button';
			removeBtn.className = 'gd-image-remove';
			removeBtn.setAttribute( 'aria-label', 'Eliminar imagen' );
			removeBtn.innerHTML = '&#x2715;';
			removeBtn.addEventListener( 'click', function () {
				row.remove();
				reindexImages();
				syncImagesUI();
			} );

			// Input hidden del ID
			var hidden   = document.createElement( 'input' );
			hidden.type  = 'hidden';
			hidden.name  = 'gd_images[0][id]'; // reindexImages() lo corrige
			hidden.value = String( id );

			row.appendChild( handle );
			row.appendChild( thumbWrap );
			row.appendChild( info );
			row.appendChild( previewBtn );
			row.appendChild( removeBtn );
			row.appendChild( hidden );

			bindImageDrag( row );
			return row;
		}

		// ── Vincular eventos a filas renderizadas por PHP ─────

		imagesList.querySelectorAll( '.gd-image-row' ).forEach( function ( row ) {
			var removeBtn = row.querySelector( '.gd-image-remove' );
			if ( removeBtn ) {
				removeBtn.addEventListener( 'click', function () {
					row.remove();
					reindexImages();
					syncImagesUI();
				} );
			}
			bindImageDrag( row );
		} );

		// ── Drag & drop (vertical) ─────────────────────────────

		function bindImageDrag( row ) {
			var handle = row.querySelector( '.gd-filter-handle' );
			if ( handle ) {
				handle.addEventListener( 'mousedown', function () {
					row.setAttribute( 'draggable', 'true' );
				} );
			}

			row.addEventListener( 'dragstart', function ( e ) {
				dragSrcImg = row;
				e.dataTransfer.effectAllowed = 'move';
				requestAnimationFrame( function () {
					row.classList.add( 'is-dragging' );
				} );
			} );

			row.addEventListener( 'dragend', function () {
				row.removeAttribute( 'draggable' );
				row.classList.remove( 'is-dragging' );
				dragSrcImg = null;
				reindexImages();
			} );
		}

		imagesList.addEventListener( 'dragover', function ( e ) {
			e.preventDefault();
			if ( ! dragSrcImg ) { return; }
			e.dataTransfer.dropEffect = 'move';

			var targetRow = e.target.closest( '.gd-image-row' );
			if ( ! targetRow || targetRow === dragSrcImg ) { return; }

			var rect   = targetRow.getBoundingClientRect();
			var before = e.clientY < rect.top + rect.height / 2;

			if ( before ) {
				if ( targetRow.previousElementSibling !== dragSrcImg ) {
					imagesList.insertBefore( dragSrcImg, targetRow );
				}
			} else {
				if ( targetRow.nextElementSibling !== dragSrcImg ) {
					imagesList.insertBefore( dragSrcImg, targetRow.nextElementSibling );
				}
			}
		} );

		// ── Sincronizar UI (dropzone vs lista) ─────────────────

		function syncImagesUI() {
			var count = imagesList.querySelectorAll( '.gd-image-row' ).length;

			if ( dropzoneEl ) {
				dropzoneEl.classList.toggle( 'gd-hidden', count > 0 );
			}
			imagesList.classList.toggle( 'gd-hidden', count === 0 );

			if ( imagesCountEl ) {
				if ( 0 === count ) {
					imagesCountEl.textContent = 'Sin imágenes';
				} else if ( 1 === count ) {
					imagesCountEl.textContent = '1 imagen';
				} else {
					imagesCountEl.textContent = count + ' imágenes';
				}
			}
		}

		// ── MutationObserver: sincronizar selects cuando cambian filtros ──

		if ( filtersList ) {
			var filterObserver = new MutationObserver( function ( mutations ) {
				var shouldRefresh = mutations.some( function ( m ) {
					if ( 'childList' === m.type ) { return true; }
					if ( 'attributes' === m.type && 'data-state' === m.attributeName ) {
						return 'confirmed' === m.target.dataset.state;
					}
					return false;
				} );
				if ( shouldRefresh ) { refreshFilterSelects(); }
			} );

			filterObserver.observe( filtersList, {
				childList      : true,
				subtree        : true,
				attributes     : true,
				attributeFilter: [ 'data-state' ],
			} );
		}
	}

} )();
