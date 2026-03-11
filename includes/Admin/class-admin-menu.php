<?php
/**
 * Registro del menú principal en el dashboard de WordPress.
 *
 * @package GaleriasDomi\Admin
 * @since   1.0.0
 */

namespace GaleriasDomi\Admin;

// Prevenir acceso directo.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clase Admin_Menu.
 *
 * Gestiona el registro y renderizado del menú principal
 * del plugin en el panel de administración de WordPress.
 *
 * @since 1.0.0
 */
class Admin_Menu {

	/**
	 * Slug principal del menú.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const MENU_SLUG = 'galerias-domi';

	/**
	 * Registra los hooks necesarios.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );

		$new_gallery = new Admin_New_Gallery();
		$new_gallery->register();
	}

	/**
	 * Agrega el menú principal y la subpágina oculta de nueva galería.
	 *
	 * @since 1.0.0
	 */
	public function add_menu(): void {
		add_menu_page(
			__( 'Galerias DOMI', 'galerias-domi' ),  // Título de la página (<title>).
			__( 'Galerias DOMI', 'galerias-domi' ),  // Texto del menú en el sidebar.
			'manage_options',                          // Capacidad requerida.
			self::MENU_SLUG,                           // Slug único del menú.
			array( $this, 'render_main_page' ),        // Callback de renderizado.
			'dashicons-format-gallery',                // Icono (galería de imágenes).
			25                                         // Posición en el sidebar.
		);

		// Subpágina oculta: no aparece en el sidebar pero es accesible por URL.
		add_submenu_page(
			null,                                        // Sin padre → oculta del menú.
			__( 'Agregar Galeria DOMI', 'galerias-domi' ),
			__( 'Agregar Galeria DOMI', 'galerias-domi' ),
			'manage_options',
			self::MENU_SLUG . '-new',
			array( $this, 'render_new_page' )
		);
	}

	/**
	 * Renderiza el contenido de la página principal del plugin.
	 *
	 * @since 1.0.0
	 */
	public function render_main_page(): void {
		( new Admin_Page() )->render();
	}

	/**
	 * Renderiza la página de nueva galería.
	 *
	 * @since 1.0.0
	 */
	public function render_new_page(): void {
		( new Admin_New_Gallery() )->render();
	}
}
