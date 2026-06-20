/* Galerias DOMI — Frontend (filtros + paginación) */
( function () {
	'use strict';

	/**
	 * Inicializa una galería: filtros y paginación client-side.
	 *
	 * @param {HTMLElement} gallery
	 */
	function initGallery( gallery ) {
		var grid = gallery.querySelector( '.gd-gallery__grid' );
		if ( ! grid ) {
			return;
		}

		var items      = Array.prototype.slice.call( grid.querySelectorAll( '.gd-gallery__item' ) );
		var pagination = gallery.querySelector( '.gd-gallery__pagination' );
		var perPage    = parseInt( gallery.getAttribute( 'data-per-page' ), 10 ) || 0;

		var state = {
			filter: getInitialFilter( gallery ),
			page  : 1,
		};

		/**
		 * Devuelve los items que coinciden con el filtro activo.
		 *
		 * @return {HTMLElement[]}
		 */
		function getFiltered() {
			if ( 'todos' === state.filter ) {
				return items;
			}
			return items.filter( function ( item ) {
				return item.getAttribute( 'data-filter' ) === state.filter;
			} );
		}

		/**
		 * Aplica filtro + paginación al DOM y reconstruye los controles de página.
		 */
		function update() {
			var filtered = getFiltered();
			var pages    = perPage > 0 ? Math.max( 1, Math.ceil( filtered.length / perPage ) ) : 1;

			if ( state.page > pages ) {
				state.page = pages;
			}

			var start = perPage > 0 ? ( state.page - 1 ) * perPage : 0;
			var end   = perPage > 0 ? start + perPage : filtered.length;

			// Ocultar todo y mostrar solo el subconjunto visible.
			items.forEach( function ( item ) {
				item.classList.add( 'gd-is-hidden' );
			} );
			filtered.slice( start, end ).forEach( function ( item ) {
				item.classList.remove( 'gd-is-hidden' );
			} );

			renderPagination( pages );
		}

		/**
		 * Reconstruye los botones de paginación.
		 *
		 * @param {number} pages
		 */
		function renderPagination( pages ) {
			if ( ! pagination ) {
				return;
			}

			pagination.innerHTML = '';

			if ( perPage <= 0 || pages <= 1 ) {
				return;
			}

			pagination.appendChild(
				makePageButton( '‹', state.page - 1, false, state.page === 1 )
			);

			for ( var p = 1; p <= pages; p++ ) {
				pagination.appendChild(
					makePageButton( String( p ), p, p === state.page, false )
				);
			}

			pagination.appendChild(
				makePageButton( '›', state.page + 1, false, state.page === pages )
			);
		}

		/**
		 * Crea un botón de paginación.
		 *
		 * @param {string}  label
		 * @param {number}  targetPage
		 * @param {boolean} isActive
		 * @param {boolean} isDisabled
		 * @return {HTMLButtonElement}
		 */
		function makePageButton( label, targetPage, isActive, isDisabled ) {
			var btn         = document.createElement( 'button' );
			btn.type        = 'button';
			btn.className   = 'gd-page-btn' + ( isActive ? ' is-active' : '' );
			btn.textContent = label;
			btn.disabled    = isDisabled || isActive;

			if ( ! isDisabled && ! isActive ) {
				btn.addEventListener( 'click', function () {
					state.page = targetPage;
					update();
					gallery.scrollIntoView( { behavior: 'smooth', block: 'start' } );
				} );
			}

			return btn;
		}

		// ── Filtros: botones ──────────────────────────────────────────
		gallery.querySelectorAll( '.gd-gallery__filter-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				gallery.querySelectorAll( '.gd-gallery__filter-btn' ).forEach( function ( b ) {
					b.classList.remove( 'is-active' );
				} );
				btn.classList.add( 'is-active' );
				state.filter = btn.getAttribute( 'data-filter' );
				state.page   = 1;
				update();
			} );
		} );

		// ── Filtros: selector ─────────────────────────────────────────
		var select = gallery.querySelector( '.gd-gallery__filter-select' );
		if ( select ) {
			select.addEventListener( 'change', function () {
				state.filter = select.value;
				state.page   = 1;
				update();
			} );
		}

		// ── Lightbox: abrir al hacer clic en una imagen ───────────────
		grid.addEventListener( 'click', function ( e ) {
			var trigger = e.target.closest ? e.target.closest( '.gd-gallery__open' ) : null;
			if ( ! trigger || ! grid.contains( trigger ) ) {
				return;
			}

			// El set navegable es el conjunto filtrado actual (no solo la página).
			var slides       = [];
			var clickedIndex = 0;
			getFiltered().forEach( function ( item ) {
				var t = item.querySelector( '.gd-gallery__open' );
				if ( ! t || ! t.getAttribute( 'data-full' ) ) {
					return;
				}
				if ( t === trigger ) {
					clickedIndex = slides.length;
				}
				slides.push( {
					full   : t.getAttribute( 'data-full' ),
					caption: t.getAttribute( 'data-caption' ) || '',
				} );
			} );

			if ( slides.length ) {
				Lightbox.open( slides, clickedIndex, trigger );
			}
		} );

		update();
	}

	/**
	 * Determina el filtro activo inicial a partir del marcado renderizado.
	 *
	 * @param {HTMLElement} gallery
	 * @return {string}
	 */
	function getInitialFilter( gallery ) {
		var activeBtn = gallery.querySelector( '.gd-gallery__filter-btn.is-active' );
		if ( activeBtn ) {
			return activeBtn.getAttribute( 'data-filter' );
		}
		var select = gallery.querySelector( '.gd-gallery__filter-select' );
		if ( select ) {
			return select.value;
		}
		return 'todos';
	}

	/**
	 * Lightbox compartido por toda la página. Se construye de forma diferida en
	 * la primera apertura para no añadir DOM ni listeners si nunca se usa.
	 */
	var Lightbox = ( function () {
		var STR = {
			dialog: 'Imagen ampliada',
			close : 'Cerrar (Esc)',
			prev  : 'Imagen anterior',
			next  : 'Imagen siguiente',
		};

		var el, imgEl, captionEl, counterEl, prevBtn, nextBtn, closeBtn;
		var slides    = [];
		var index     = 0;
		var lastFocus = null;
		var built     = false;

		/**
		 * Construye el DOM del lightbox una sola vez.
		 */
		function build() {
			if ( built ) {
				return;
			}

			el = document.createElement( 'div' );
			el.className = 'gd-lightbox';
			el.setAttribute( 'role', 'dialog' );
			el.setAttribute( 'aria-modal', 'true' );
			el.setAttribute( 'aria-label', STR.dialog );
			el.innerHTML =
				'<span class="gd-lightbox__counter" aria-hidden="true" hidden></span>' +
				'<button type="button" class="gd-lightbox__btn gd-lightbox__close" aria-label="' + STR.close + '">✕</button>' +
				'<button type="button" class="gd-lightbox__btn gd-lightbox__nav gd-lightbox__nav--prev" aria-label="' + STR.prev + '">‹</button>' +
				'<figure class="gd-lightbox__figure">' +
					'<img class="gd-lightbox__img" src="" alt="" decoding="async">' +
					'<figcaption class="gd-lightbox__caption"></figcaption>' +
				'</figure>' +
				'<button type="button" class="gd-lightbox__btn gd-lightbox__nav gd-lightbox__nav--next" aria-label="' + STR.next + '">›</button>';

			document.body.appendChild( el );

			imgEl     = el.querySelector( '.gd-lightbox__img' );
			captionEl = el.querySelector( '.gd-lightbox__caption' );
			counterEl = el.querySelector( '.gd-lightbox__counter' );
			prevBtn   = el.querySelector( '.gd-lightbox__nav--prev' );
			nextBtn   = el.querySelector( '.gd-lightbox__nav--next' );
			closeBtn  = el.querySelector( '.gd-lightbox__close' );

			closeBtn.addEventListener( 'click', close );
			prevBtn.addEventListener( 'click', function () { go( -1 ); } );
			nextBtn.addEventListener( 'click', function () { go( 1 ); } );

			// Cerrar al hacer clic fuera de la imagen (backdrop o zona de la figura).
			el.addEventListener( 'click', function ( e ) {
				if ( e.target === el || e.target.classList.contains( 'gd-lightbox__figure' ) ) {
					close();
				}
			} );

			imgEl.addEventListener( 'load', function () {
				el.classList.remove( 'is-loading' );
			} );

			document.addEventListener( 'keydown', onKey );

			built = true;
		}

		/**
		 * Gestión de teclado: Esc cierra, flechas navegan, Tab queda atrapado.
		 *
		 * @param {KeyboardEvent} e
		 */
		function onKey( e ) {
			if ( ! el || ! el.classList.contains( 'is-open' ) ) {
				return;
			}
			if ( 'Escape' === e.key ) {
				close();
			} else if ( 'ArrowLeft' === e.key ) {
				go( -1 );
			} else if ( 'ArrowRight' === e.key ) {
				go( 1 );
			} else if ( 'Tab' === e.key ) {
				trapFocus( e );
			}
		}

		/**
		 * Devuelve los botones enfocables actualmente visibles.
		 *
		 * @return {HTMLElement[]}
		 */
		function focusable() {
			return Array.prototype.filter.call(
				el.querySelectorAll( 'button' ),
				function ( b ) {
					return ! b.hidden && null !== b.offsetParent;
				}
			);
		}

		/**
		 * Mantiene el foco dentro del lightbox mientras está abierto.
		 *
		 * @param {KeyboardEvent} e
		 */
		function trapFocus( e ) {
			var f = focusable();
			if ( ! f.length ) {
				return;
			}
			var first = f[ 0 ];
			var last  = f[ f.length - 1 ];
			if ( e.shiftKey && document.activeElement === first ) {
				e.preventDefault();
				last.focus();
			} else if ( ! e.shiftKey && document.activeElement === last ) {
				e.preventDefault();
				first.focus();
			}
		}

		/**
		 * Precarga una imagen vecina para que la navegación sea instantánea.
		 *
		 * @param {number} i
		 */
		function preload( i ) {
			if ( i < 0 || i >= slides.length ) {
				return;
			}
			var im = new Image();
			im.src = slides[ i ].full;
		}

		/**
		 * Pinta el slide actual y actualiza controles.
		 */
		function render() {
			var slide = slides[ index ];
			if ( ! slide ) {
				return;
			}

			el.classList.add( 'is-loading' );
			imgEl.src = slide.full;
			imgEl.alt = slide.caption;

			captionEl.textContent   = slide.caption;
			captionEl.style.display = slide.caption ? '' : 'none';

			var multi = slides.length > 1;
			prevBtn.hidden    = ! multi;
			nextBtn.hidden    = ! multi;
			counterEl.hidden  = ! multi;
			if ( multi ) {
				counterEl.textContent = ( index + 1 ) + ' / ' + slides.length;
			}

			preload( index + 1 );
			preload( index - 1 );
		}

		/**
		 * Navega de forma circular dentro del set.
		 *
		 * @param {number} dir -1 (anterior) | 1 (siguiente)
		 */
		function go( dir ) {
			if ( slides.length < 2 ) {
				return;
			}
			index = ( index + dir + slides.length ) % slides.length;
			render();
		}

		/**
		 * Abre el lightbox.
		 *
		 * @param {Array<{full: string, caption: string}>} items
		 * @param {number}      start
		 * @param {HTMLElement} trigger Elemento que abrió el lightbox (para devolver el foco).
		 */
		function open( items, start, trigger ) {
			build();
			slides    = items;
			index     = start;
			lastFocus = trigger || document.activeElement;

			document.body.classList.add( 'gd-no-scroll' );
			el.classList.add( 'is-open' );
			render();
			closeBtn.focus();
		}

		/**
		 * Cierra el lightbox y devuelve el foco al disparador.
		 */
		function close() {
			el.classList.remove( 'is-open' );
			document.body.classList.remove( 'gd-no-scroll' );
			imgEl.removeAttribute( 'src' );
			if ( lastFocus && 'function' === typeof lastFocus.focus ) {
				lastFocus.focus();
			}
		}

		return { open: open };
	} )();

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.gd-gallery' ).forEach( initGallery );
	} );
} )();
