<?php
/**
 * Renderizado de la página principal del plugin en el dashboard.
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
 * Clase Admin_Page.
 *
 * Construye y muestra la página principal de Galerias DOMI,
 * siguiendo la estructura visual de plugins como Contact Form 7.
 *
 * @since 1.0.0
 */
class Admin_Page {

	/**
	 * Renderiza la página completa.
	 *
	 * @since 1.0.0
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para acceder a esta página.', 'galerias-domi' ) );
		}

		$add_new_url = admin_url( 'admin.php?page=' . Admin_Menu::MENU_SLUG . '-new' );

		$table = new Galleries_List_Table();
		$table->prepare_items();

		// Mensaje de éxito tras crear una galería.
		$created = isset( $_GET['gd_created'] ) && '1' === $_GET['gd_created']; // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<div class="wrap">

			<h1 class="wp-heading-inline">
				<?php esc_html_e( 'Galerias DOMI', 'galerias-domi' ); ?>
			</h1>

			<a href="<?php echo esc_url( $add_new_url ); ?>" class="page-title-action">
				<?php esc_html_e( 'Agregar Galeria DOMI', 'galerias-domi' ); ?>
			</a>

			<hr class="wp-header-end">

			<?php if ( $created ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( '¡Galería creada correctamente!', 'galerias-domi' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( Admin_Menu::MENU_SLUG ); ?>">
				<?php $table->display(); ?>
			</form>

		</div>
		<?php
	}
}
