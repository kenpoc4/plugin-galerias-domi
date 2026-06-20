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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		$new_gallery = new Admin_New_Gallery();
		$new_gallery->register();

		Admin_Edit_Gallery::register_save_hook();
		Admin_Edit_Gallery::register_publish_hook();
		Admin_Edit_Gallery::register_render_hook();
	}

	/**
	 * Hooks de páginas que pertenecen al plugin.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	private const PLUGIN_HOOKS = array(
		'toplevel_page_galerias-domi',
		'admin_page_galerias-domi-new',
	);

	/**
	 * Encola los assets necesarios según la página y acción actuales.
	 *
	 * @since 1.0.0
	 * @param string $hook Sufijo del hook de la página actual.
	 */
	public function enqueue_assets( string $hook ): void {
		// Fuera de las páginas del plugin, no hacer nada.
		if ( ! in_array( $hook, self::PLUGIN_HOOKS, true ) ) {
			return;
		}

		// ── Assets globales: todas las páginas del plugin ──────────────
		wp_enqueue_style(
			'gd-poppins',
			'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap',
			array(),
			null // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		);

		wp_enqueue_style(
			'gd-admin-global',
			GALERIAS_DOMI_PLUGIN_URL . 'assets/css/admin-global.css',
			array( 'gd-poppins' ),
			GALERIAS_DOMI_VERSION
		);

		// ── Assets de la página de edición ─────────────────────────────
		if ( 'toplevel_page_' . self::MENU_SLUG === $hook ) {
			$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			if ( 'edit' === $action ) {
				wp_enqueue_media();

				wp_enqueue_style(
					'gd-admin-edit',
					GALERIAS_DOMI_PLUGIN_URL . 'assets/css/admin-edit.css',
					array( 'gd-admin-global' ),
					GALERIAS_DOMI_VERSION
				);

				wp_enqueue_script(
					'gd-admin-edit',
					GALERIAS_DOMI_PLUGIN_URL . 'assets/js/admin-edit.js',
					array( 'media-editor' ),
					GALERIAS_DOMI_VERSION,
					true
				);
			}
		}
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
