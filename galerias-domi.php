<?php
/**
 * Plugin Name:       Galerias DOMI
 * Plugin URI:        https://github.com/kenpoc4/plugin-galerias-domi
 * Description:       Plugin para la creación profesional de galerías y carruseles.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Kenny Poncio
 * Author URI:        https://github.com/kenpoc4/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       galerias-domi
 * Domain Path:       /languages
 * Network:           false
 *
 * @package GaleriasDomi
 */

// Prevenir acceso directo al archivo.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Definición de constantes del plugin.
define( 'GALERIAS_DOMI_VERSION', '1.0.0' );
define( 'GALERIAS_DOMI_PLUGIN_FILE', __FILE__ );
define( 'GALERIAS_DOMI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GALERIAS_DOMI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GALERIAS_DOMI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader PSR-4 para las clases del plugin.
spl_autoload_register(
	function ( string $class ): void {
		$prefix   = 'GaleriasDomi\\';
		$base_dir = GALERIAS_DOMI_PLUGIN_DIR . 'includes/';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative  = str_replace( $prefix, '', $class );
		$parts     = explode( '\\', $relative );
		$classname = array_pop( $parts );
		$subdir    = implode( '/', $parts );
		$file      = $base_dir . ( $subdir ? $subdir . '/' : '' )
					. 'class-' . strtolower( str_replace( '_', '-', $classname ) ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

/**
 * Clase principal del plugin.
 *
 * @since 1.0.0
 */
final class Galerias_Domi {

	/**
	 * Instancia única de la clase (patrón Singleton).
	 *
	 * @since 1.0.0
	 * @var Galerias_Domi|null
	 */
	private static ?Galerias_Domi $instance = null;

	/**
	 * Retorna la instancia única del plugin.
	 *
	 * @since 1.0.0
	 * @return Galerias_Domi
	 */
	public static function get_instance(): Galerias_Domi {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor privado para forzar el uso de get_instance().
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->define_hooks();
	}

	/**
	 * Registra los hooks principales del plugin.
	 *
	 * @since 1.0.0
	 */
	private function define_hooks(): void {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'init' ) );

		if ( is_admin() ) {
			$this->load_admin();
		}
	}

	/**
	 * Inicializa los módulos de administración.
	 *
	 * @since 1.0.0
	 */
	private function load_admin(): void {
		( new \GaleriasDomi\Admin\Admin_Menu() )->register();
	}

	/**
	 * Carga las traducciones del plugin.
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'galerias-domi',
			false,
			dirname( GALERIAS_DOMI_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Inicialización del plugin tras cargar WordPress.
	 *
	 * @since 1.0.0
	 */
	public function init(): void {
		( new \GaleriasDomi\Post_Types\Gallery_Post_Type() )->register();
		( new \GaleriasDomi\Frontend\Gallery_Shortcode() )->register();
	}

}

/**
 * Retorna la instancia principal del plugin.
 *
 * @since 1.0.0
 * @return Galerias_Domi
 */
function galerias_domi(): Galerias_Domi {
	return Galerias_Domi::get_instance();
}

// Iniciar el plugin.
galerias_domi();

/**
 * Acciones de activación.
 */
register_activation_hook( __FILE__, 'galerias_domi_activate' );

/**
 * Se ejecuta al activar el plugin.
 *
 * El CPT se registra con `rewrite=false`, por lo que no hace falta limpiar
 * las reglas de reescritura de URLs.
 *
 * @since 1.0.0
 */
function galerias_domi_activate(): void {
	// Guardar la versión instalada para futuras migraciones.
	if ( ! get_option( 'galerias_domi_version' ) ) {
		add_option( 'galerias_domi_version', GALERIAS_DOMI_VERSION );
	}
}
