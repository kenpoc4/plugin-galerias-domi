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

		// Click
		btns.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				activateTab( btn.dataset.tab );
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
	} );

	/* ── Filtros: toggle → habilita / deshabilita el select ──────────── */

	const filtersToggle     = document.getElementById( 'gd-filters-enabled' );
	const filterStyleField  = document.getElementById( 'gd-filter-style-field' );
	const filterStyleSelect = document.getElementById( 'gd-filter-style' );

	if ( filtersToggle && filterStyleField && filterStyleSelect ) {

		function syncFilterStyle() {
			const enabled = filtersToggle.checked;

			filterStyleField.classList.toggle( 'is-disabled', ! enabled );
			filterStyleField.setAttribute( 'aria-disabled', enabled ? 'false' : 'true' );
			filterStyleSelect.setAttribute( 'tabindex', enabled ? '' : '-1' );
		}

		filtersToggle.addEventListener( 'change', syncFilterStyle );

		// Estado inicial al cargar la página.
		syncFilterStyle();
	}

	/* ── Filter repeater ─────────────────────────────────────────────── */

	const filtersList  = document.getElementById( 'gd-filters-list' );
	const filterAddBtn = document.getElementById( 'gd-filter-add' );

	if ( filtersList && filterAddBtn ) {

		let rowCounter = filtersList.querySelectorAll( '.gd-filter-row:not(.gd-filter-row--default)' ).length;

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

		function reindex() {
			filtersList
				.querySelectorAll( '.gd-filter-row:not(.gd-filter-row--default)' )
				.forEach( function ( row, i ) {
					row.querySelector( '.gd-filter-name' ).name = 'gd_filters[' + i + '][name]';
					row.querySelector( '.gd-filter-id' ).name   = 'gd_filters[' + i + '][id]';
				} );
		}

		function bindRowEvents( row ) {
			const nameInput   = row.querySelector( '.gd-filter-name' );
			const idInput     = row.querySelector( '.gd-filter-id' );
			const removeBtn   = row.querySelector( '.gd-filter-remove' );

			nameInput.addEventListener( 'input', function () {
				if ( idInput.dataset.auto === 'true' ) {
					idInput.value = slugify( nameInput.value );
				}
			} );

			idInput.addEventListener( 'input', function () {
				idInput.dataset.auto = 'false';
			} );

			idInput.addEventListener( 'blur', function () {
				if ( '' === idInput.value ) {
					idInput.dataset.auto = 'true';
					idInput.value = slugify( nameInput.value );
				}
			} );

			removeBtn.addEventListener( 'click', function () {
				row.remove();
				reindex();
			} );
		}

		function createRow() {
			const idx = rowCounter++;

			const row = document.createElement( 'div' );
			row.className = 'gd-filter-row';

			const fields = document.createElement( 'div' );
			fields.className = 'gd-filter-row__fields';

			const nameInput = document.createElement( 'input' );
			nameInput.type        = 'text';
			nameInput.className   = 'gd-filter-name';
			nameInput.name        = 'gd_filters[' + idx + '][name]';
			nameInput.placeholder = 'Nombre';

			const idInput = document.createElement( 'input' );
			idInput.type          = 'text';
			idInput.className     = 'gd-filter-id';
			idInput.name          = 'gd_filters[' + idx + '][id]';
			idInput.placeholder   = 'id-del-filtro';
			idInput.dataset.auto  = 'true';

			const removeBtn = document.createElement( 'button' );
			removeBtn.type      = 'button';
			removeBtn.className = 'gd-filter-remove';
			removeBtn.setAttribute( 'aria-label', 'Eliminar filtro' );
			removeBtn.textContent = '\xD7';

			fields.appendChild( nameInput );
			fields.appendChild( idInput );
			row.appendChild( fields );
			row.appendChild( removeBtn );

			bindRowEvents( row );

			return row;
		}

		// Vincular eventos a filas ya renderizadas por PHP (filtros guardados).
		filtersList
			.querySelectorAll( '.gd-filter-row:not(.gd-filter-row--default)' )
			.forEach( bindRowEvents );

		filterAddBtn.addEventListener( 'click', function () {
			const row = createRow();
			filtersList.appendChild( row );
			row.querySelector( '.gd-filter-name' ).focus();
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

} )();
