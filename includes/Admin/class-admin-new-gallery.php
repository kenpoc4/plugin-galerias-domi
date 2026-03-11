<?php
/**
 * Página y procesamiento para crear una nueva galería.
 *
 * @package GaleriasDomi\Admin
 * @since   1.0.0
 */

namespace GaleriasDomi\Admin;

use GaleriasDomi\Post_Types\Gallery_Post_Type;

// Prevenir acceso directo.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clase Admin_New_Gallery.
 *
 * Muestra el formulario de creación de galería y procesa el guardado.
 *
 * @since 1.0.0
 */
class Admin_New_Gallery {

	/**
	 * Nombre de la acción del formulario.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const ACTION = 'galerias_domi_create';

	/**
	 * Nombre del nonce.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const NONCE = 'galerias_domi_create_nonce';

	/**
	 * Registra los hooks necesarios.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_save' ) );
	}

	/**
	 * Renderiza el formulario de nueva galería.
	 *
	 * @since 1.0.0
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para acceder a esta página.', 'galerias-domi' ) );
		}

		// Recuperar error desde la URL si fue redirigido de vuelta.
		$error = isset( $_GET['gd_error'] ) ? sanitize_key( $_GET['gd_error'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<div class="wrap">

			<h1><?php esc_html_e( 'Agregar Galeria DOMI', 'galerias-domi' ); ?></h1>

			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Admin_Menu::MENU_SLUG ) ); ?>" class="page-title-action">
				&larr; <?php esc_html_e( 'Volver al listado', 'galerias-domi' ); ?>
			</a>

			<hr class="wp-header-end">

			<?php if ( 'empty_title' === $error ) : ?>
				<div class="notice notice-error is-dismissible">
					<p><?php esc_html_e( 'El nombre de la galería no puede estar vacío.', 'galerias-domi' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="gd-new-gallery-form-wrap" style="max-width:480px;margin-top:24px;">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
					<?php wp_nonce_field( self::ACTION, self::NONCE ); ?>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="gd_gallery_title">
									<?php esc_html_e( 'Nombre de la galería', 'galerias-domi' ); ?>
									<span style="color:#d63638;">*</span>
								</label>
							</th>
							<td>
								<input
									type="text"
									id="gd_gallery_title"
									name="gd_gallery_title"
									class="regular-text"
									placeholder="<?php esc_attr_e( 'Ej: Galería de verano', 'galerias-domi' ); ?>"
									value="<?php echo esc_attr( isset( $_GET['gd_title'] ) ? sanitize_text_field( wp_unslash( $_GET['gd_title'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification ?>"
									autofocus
									required
								>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Crear galería', 'galerias-domi' ), 'primary', 'submit', false ); ?>

				</form>
			</div>

		</div>
		<?php
	}

	/**
	 * Procesa el formulario de creación de galería.
	 *
	 * Se ejecuta vía admin_post_{action}. Valida, guarda y redirige.
	 *
	 * @since 1.0.0
	 */
	public function handle_save(): void {
		// Verificar permisos.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para realizar esta acción.', 'galerias-domi' ) );
		}

		// Verificar nonce.
		check_admin_referer( self::ACTION, self::NONCE );

		// Obtener y sanear el título.
		$title = isset( $_POST['gd_gallery_title'] )
			? sanitize_text_field( wp_unslash( $_POST['gd_gallery_title'] ) )
			: '';

		$back_url = admin_url( 'admin.php?page=' . Admin_Menu::MENU_SLUG . '-new' );

		// Validar que no esté vacío.
		if ( '' === $title ) {
			wp_safe_redirect(
				add_query_arg( 'gd_error', 'empty_title', $back_url )
			);
			exit;
		}

		// Guardar la galería como CPT.
		$post_id = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_type'   => Gallery_Post_Type::POST_TYPE,
				'post_status' => 'publish',
				'post_author' => get_current_user_id(),
			),
			true // Retorna WP_Error en caso de fallo.
		);

		// Manejar error de inserción.
		if ( is_wp_error( $post_id ) ) {
			wp_safe_redirect(
				add_query_arg( 'gd_error', 'insert_failed', $back_url )
			);
			exit;
		}

		// Éxito: redirigir al listado con mensaje de confirmación.
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => Admin_Menu::MENU_SLUG,
					'gd_created' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
