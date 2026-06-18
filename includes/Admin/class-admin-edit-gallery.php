<?php
/**
 * Página de edición de una galería individual.
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
 * Clase Admin_Edit_Gallery.
 *
 * Renderiza la página de edición de una galería existente.
 *
 * @since 1.0.0
 */
class Admin_Edit_Gallery {

	const ACTION         = 'galerias_domi_save_gallery';
	const NONCE          = 'galerias_domi_save_gallery_nonce';
	const PUBLISH_ACTION = 'galerias_domi_publish_gallery';
	const PUBLISH_NONCE  = 'galerias_domi_publish_gallery_nonce';

	/**
	 * Post de la galería que se está editando.
	 *
	 * @since 1.0.0
	 * @var \WP_Post|null
	 */
	private ?\WP_Post $gallery;

	/**
	 * Registra el hook de guardado vía admin-post.php.
	 *
	 * @since 1.0.0
	 */
	public static function register_save_hook(): void {
		add_action(
			'admin_post_' . self::ACTION,
			function () {
				$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
				( new self( $id ) )->handle_save();
			}
		);
	}

	/**
	 * Registra el hook de publicación vía admin-post.php.
	 *
	 * @since 1.0.0
	 */
	public static function register_publish_hook(): void {
		add_action(
			'admin_post_' . self::PUBLISH_ACTION,
			function () {
				$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
				( new self( $id ) )->handle_publish();
			}
		);
	}

	/**
	 * Activa la galería para que su shortcode quede disponible.
	 *
	 * @since 1.0.0
	 */
	public function handle_publish(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'galerias-domi' ) );
		}

		check_admin_referer( self::PUBLISH_NONCE );

		if ( null === $this->gallery ) {
			wp_die( esc_html__( 'Galería no encontrada.', 'galerias-domi' ) );
		}

		update_post_meta( $this->gallery->ID, '_gd_published', 1 );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => Admin_Menu::MENU_SLUG,
					'action'    => 'edit',
					'id'        => $this->gallery->ID,
					'published' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Procesa el formulario de edición y guarda los post metas.
	 *
	 * @since 1.0.0
	 */
	public function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'galerias-domi' ) );
		}

		check_admin_referer( self::NONCE );

		if ( null === $this->gallery ) {
			wp_die( esc_html__( 'Galería no encontrada.', 'galerias-domi' ) );
		}

		$id = $this->gallery->ID;

		// Columnas (rango 1-8, defecto 3).
		$columns = absint( $_POST['gd_columns'] ?? 3 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$columns = max( 1, min( 8, $columns ) );
		update_post_meta( $id, '_gd_columns', $columns );

		// Efecto hover.
		$valid_effects = array( 'shadow', 'border', 'zoom-in', 'zoom-out', 'blur' );
		$hover_effect  = sanitize_key( wp_unslash( $_POST['gd_hover_effect'] ?? '' ) );
		if ( ! in_array( $hover_effect, $valid_effects, true ) ) {
			$hover_effect = 'shadow';
		}
		update_post_meta( $id, '_gd_hover_effect', $hover_effect );

		// Filtros habilitados.
		update_post_meta( $id, '_gd_filters_enabled', isset( $_POST['gd_filters_enabled'] ) ? 1 : 0 );

		// Estilo del filtro.
		$valid_styles = array( 'buttons', 'select' );
		$filter_style = sanitize_key( wp_unslash( $_POST['gd_filter_style'] ?? '' ) );
		if ( ! in_array( $filter_style, $valid_styles, true ) ) {
			$filter_style = 'buttons';
		}
		update_post_meta( $id, '_gd_filter_style', $filter_style );

		// Mostrar filtro "Todos".
		update_post_meta( $id, '_gd_show_todos', isset( $_POST['gd_show_todos'] ) ? 1 : 0 );

		// Filtros disponibles.
		$raw_filters = isset( $_POST['gd_filters'] ) && is_array( $_POST['gd_filters'] )
			? $_POST['gd_filters'] // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			: array();

		$filters = array();
		foreach ( $raw_filters as $item ) {
			$filter_name = sanitize_text_field( wp_unslash( $item['name'] ?? '' ) );
			$filter_id   = sanitize_key( wp_unslash( $item['id'] ?? '' ) );
			if ( '' !== $filter_name && '' !== $filter_id ) {
				$filters[] = array(
					'name' => $filter_name,
					'id'   => $filter_id,
				);
			}
		}
		update_post_meta( $id, '_gd_filters', $filters );

		// Posición del filtro "Todos" dentro del listado.
		$todos_position = absint( $_POST['gd_todos_position'] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$todos_position = min( $todos_position, count( $filters ) );
		update_post_meta( $id, '_gd_todos_position', $todos_position );

		// Imágenes (array de {id, filter}).
		$raw_images = isset( $_POST['gd_images'] ) && is_array( $_POST['gd_images'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			? $_POST['gd_images'] // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			: array();

		$images = array();
		foreach ( $raw_images as $img_item ) {
			$attachment_id = absint( $img_item['id'] ?? 0 );
			$img_filter    = sanitize_key( $img_item['filter'] ?? 'todos' );
			if ( $attachment_id > 0 ) {
				$images[] = array(
					'id'     => $attachment_id,
					'filter' => $img_filter,
				);
			}
		}
		update_post_meta( $id, '_gd_images', $images );

		// Paginación.
		update_post_meta( $id, '_gd_pagination_enabled', isset( $_POST['gd_pagination_enabled'] ) ? 1 : 0 );

		$valid_rows      = array( 3, 6, 9, 12 );
		$pagination_rows = absint( $_POST['gd_pagination_rows'] ?? 6 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! in_array( $pagination_rows, $valid_rows, true ) ) {
			$pagination_rows = 6;
		}
		update_post_meta( $id, '_gd_pagination_rows', $pagination_rows );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'   => Admin_Menu::MENU_SLUG,
					'action' => 'edit',
					'id'     => $id,
					'saved'  => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param int $id ID de la galería.
	 */
	public function __construct( int $id ) {
		$post          = get_post( $id );
		$this->gallery = ( $post instanceof \WP_Post ) ? $post : null;
	}

	/* =========================================================
	 * DEFINICIÓN DE TABS
	 * ======================================================= */

	/**
	 * Retorna los tabs con su id, etiqueta y método de renderizado.
	 *
	 * @since 1.0.0
	 * @return array<int, array{id: string, label: string, callback: callable}>
	 */
	private function get_tabs(): array {
		return array(
			array(
				'id'       => 'general',
				'label'    => __( 'Opciones generales', 'galerias-domi' ),
				'callback' => array( $this, 'render_tab_general' ),
			),
			array(
				'id'       => 'filters',
				'label'    => __( 'Filtros', 'galerias-domi' ),
				'callback' => array( $this, 'render_tab_filters' ),
			),
		);
	}

	/* =========================================================
	 * CONTENIDO DE TABS
	 * ======================================================= */

	/**
	 * Renderiza el contenido del tab "Opciones generales".
	 *
	 * @since 1.0.0
	 */
	private function render_tab_general(): void {
		$this->render_field_columns();
		$this->render_field_hover_effect();
		$this->render_field_pagination();
	}

	/**
	 * Renderiza el contenido del tab "Filtros".
	 *
	 * @since 1.0.0
	 */
	private function render_tab_filters(): void {
		$filters_enabled = (bool) get_post_meta( $this->gallery->ID, '_gd_filters_enabled', true );
		$filter_style    = get_post_meta( $this->gallery->ID, '_gd_filter_style', true ) ?: 'buttons';
		?>

		<!-- Activar filtros -->
		<div class="gd-field gd-field--row">
			<div class="gd-field__info">
				<span class="gd-field__label gd-field__label--inline">
					<?php esc_html_e( 'Activar filtros', 'galerias-domi' ); ?>
				</span>
				<span class="gd-field__desc">
					<?php esc_html_e( 'Muestra controles para filtrar las imágenes de la galería.', 'galerias-domi' ); ?>
				</span>
			</div>

			<label class="gd-toggle" for="gd-filters-enabled">
				<input
					type="checkbox"
					id="gd-filters-enabled"
					name="gd_filters_enabled"
					value="1"
					<?php checked( $filters_enabled, true ); ?>>
				<span class="gd-toggle__track">
					<span class="gd-toggle__thumb"></span>
				</span>
				<span class="screen-reader-text">
					<?php esc_html_e( 'Activar filtros', 'galerias-domi' ); ?>
				</span>
			</label>
		</div>

		<!-- Estilo del filtro (condicional) -->
		<div class="gd-field <?php echo ! $filters_enabled ? 'is-disabled' : ''; ?>"
			id="gd-filter-style-field"
			aria-disabled="<?php echo ! $filters_enabled ? 'true' : 'false'; ?>">

			<span class="gd-field__label">
				<?php esc_html_e( 'Estilo del filtro', 'galerias-domi' ); ?>
			</span>

			<div class="gd-select-wrap">
				<select
					id="gd-filter-style"
					name="gd_filter_style"
					<?php echo ! $filters_enabled ? 'tabindex="-1"' : ''; ?>>

					<option value="buttons" <?php selected( $filter_style, 'buttons' ); ?>>
						<?php esc_html_e( 'Botones', 'galerias-domi' ); ?>
					</option>
					<option value="select" <?php selected( $filter_style, 'select' ); ?>>
						<?php esc_html_e( 'Selector', 'galerias-domi' ); ?>
					</option>

				</select>
			</div>

		</div>

		<?php
		$this->render_field_filters_list();
	}

	/**
	 * Renderiza la fila especial "Todos" dentro del listado de filtros.
	 *
	 * @since 1.0.0
	 * @param bool $show_todos Si la fila debe mostrarse activa.
	 */
	private function render_todos_row( bool $show_todos ): void {
		?>
		<div class="gd-filter-row gd-filter-row--default <?php echo ! $show_todos ? 'is-todos-hidden' : ''; ?>">
			<button type="button" class="gd-filter-handle" aria-label="<?php esc_attr_e( 'Mover filtro', 'galerias-domi' ); ?>" tabindex="-1">⣿</button>
			<span class="gd-filter-row__fixed-name">
				<?php esc_html_e( 'Todos', 'galerias-domi' ); ?>
			</span>
			<span class="gd-filter-row__fixed-id">todos</span>
		</div>
		<?php
	}

	/**
	 * Renderiza el repeater de filtros disponibles.
	 *
	 * @since 1.0.0
	 */
	private function render_field_filters_list(): void {
		$saved          = get_post_meta( $this->gallery->ID, '_gd_filters', true );
		$filters        = is_array( $saved ) ? $saved : array();
		$raw_show_todos = get_post_meta( $this->gallery->ID, '_gd_show_todos', true );
		$show_todos     = '' !== $raw_show_todos ? (bool) $raw_show_todos : true;
		$todos_position = absint( get_post_meta( $this->gallery->ID, '_gd_todos_position', true ) );
		$todos_position = min( $todos_position, count( $filters ) );
		?>

		<!-- Filtros disponibles -->
		<div class="gd-field" id="gd-filters-available-field">

			<span class="gd-field__label">
				<?php esc_html_e( 'Filtros disponibles', 'galerias-domi' ); ?>
			</span>

			<label class="gd-todos-option" for="gd-show-todos">
				<input
					type="checkbox"
					id="gd-show-todos"
					name="gd_show_todos"
					value="1"
					<?php checked( $show_todos, true ); ?>>
				<?php esc_html_e( 'Mostrar "Todos"', 'galerias-domi' ); ?>
			</label>

			<input type="hidden" id="gd-todos-position" name="gd_todos_position" value="<?php echo esc_attr( $todos_position ); ?>">

			<div class="gd-filters-list" id="gd-filters-list">

				<?php
				foreach ( $filters as $index => $filter ) :
					// Insertar "Todos" antes del filtro en cuya posición debe aparecer.
					if ( (int) $index === $todos_position ) :
						$this->render_todos_row( $show_todos );
					endif;

					$name = sanitize_text_field( $filter['name'] ?? '' );
					$fid  = sanitize_key( $filter['id'] ?? '' );
				?>
				<div class="gd-filter-row" data-state="confirmed">

					<div class="gd-filter-row__edit-mode">
						<button type="button" class="gd-filter-handle" aria-label="<?php esc_attr_e( 'Mover filtro', 'galerias-domi' ); ?>" tabindex="-1">⣿</button>
						<div class="gd-filter-row__fields">
							<input
								type="text"
								class="gd-filter-name"
								name="gd_filters[<?php echo esc_attr( $index ); ?>][name]"
								value="<?php echo esc_attr( $name ); ?>"
								placeholder="<?php esc_attr_e( 'Nombre', 'galerias-domi' ); ?>">
							<input
								type="text"
								class="gd-filter-id"
								name="gd_filters[<?php echo esc_attr( $index ); ?>][id]"
								value="<?php echo esc_attr( $fid ); ?>"
								placeholder="id-del-filtro"
								data-auto="false">
						</div>
					</div>

					<div class="gd-filter-row__view-mode">
						<button type="button" class="gd-filter-handle" aria-label="<?php esc_attr_e( 'Mover filtro', 'galerias-domi' ); ?>" tabindex="-1">⣿</button>
						<span class="gd-filter-row__fixed-name"><?php echo esc_html( $name ); ?></span>
						<span class="gd-filter-row__fixed-id"><?php echo esc_html( $fid ); ?></span>
						<div class="gd-filter-row__view-actions">
							<button type="button" class="gd-filter-edit">
								<?php esc_html_e( 'Editar', 'galerias-domi' ); ?>
							</button>
							<button type="button" class="gd-filter-delete">
								<?php esc_html_e( 'Eliminar', 'galerias-domi' ); ?>
							</button>
						</div>
					</div>

				</div>
				<?php endforeach; ?>

				<?php
				// Si "Todos" debe ir al final (posición ≥ número de filtros regulares).
				if ( $todos_position >= count( $filters ) ) :
					$this->render_todos_row( $show_todos );
				endif;
				?>

			</div><!-- .gd-filters-list -->

			<button type="button" class="gd-filter-add" id="gd-filter-add">
				<?php esc_html_e( '+ Agregar filtro', 'galerias-domi' ); ?>
			</button>

		</div><!-- .gd-field -->

		<?php
	}

	/* =========================================================
	 * CAMPOS
	 * ======================================================= */

	/**
	 * Renderiza el campo de paginación.
	 *
	 * @since 1.0.0
	 */
	private function render_field_pagination(): void {
		$enabled = (bool) get_post_meta( $this->gallery->ID, '_gd_pagination_enabled', true );
		$saved   = absint( get_post_meta( $this->gallery->ID, '_gd_pagination_rows', true ) );
		$rows    = in_array( $saved, array( 3, 6, 9, 12 ), true ) ? $saved : 6;
		?>

		<!-- Toggle paginación -->
		<div class="gd-field gd-field--row">
			<div class="gd-field__info">
				<span class="gd-field__label gd-field__label--inline">
					<?php esc_html_e( 'Paginación', 'galerias-domi' ); ?>
				</span>
				<span class="gd-field__desc">
					<?php esc_html_e( 'Divide las imágenes en páginas para no mostrarlas todas a la vez.', 'galerias-domi' ); ?>
				</span>
			</div>
			<label class="gd-toggle" for="gd-pagination-enabled">
				<input
					type="checkbox"
					id="gd-pagination-enabled"
					name="gd_pagination_enabled"
					value="1"
					<?php checked( $enabled, true ); ?>>
				<span class="gd-toggle__track">
					<span class="gd-toggle__thumb"></span>
				</span>
				<span class="screen-reader-text">
					<?php esc_html_e( 'Activar paginación', 'galerias-domi' ); ?>
				</span>
			</label>
		</div>

		<!-- Filas por página (condicional) -->
		<div class="gd-field <?php echo ! $enabled ? 'is-disabled' : ''; ?>"
			id="gd-pagination-rows-field"
			aria-disabled="<?php echo ! $enabled ? 'true' : 'false'; ?>">

			<span class="gd-field__label">
				<?php esc_html_e( 'Filas por página', 'galerias-domi' ); ?>
			</span>

			<div class="gd-rows-picker"
				role="radiogroup"
				aria-label="<?php esc_attr_e( 'Filas por página', 'galerias-domi' ); ?>">

				<?php foreach ( array( 3, 6, 9, 12 ) as $val ) : ?>
				<label class="gd-row-option"
					for="gd-rows-<?php echo esc_attr( $val ); ?>">
					<input
						type="radio"
						id="gd-rows-<?php echo esc_attr( $val ); ?>"
						name="gd_pagination_rows"
						value="<?php echo esc_attr( $val ); ?>"
						<?php checked( $val, $rows ); ?>>
					<span class="gd-row-option__box">
						<?php echo esc_html( $val ); ?>
					</span>
					<span class="gd-row-option__label">
						<?php esc_html_e( 'filas', 'galerias-domi' ); ?>
					</span>
				</label>
				<?php endforeach; ?>

			</div>

		</div>
		<?php
	}

	/**
	 * Renderiza el campo visual de selección de columnas.
	 *
	 * @since 1.0.0
	 */
	private function render_field_columns(): void {
		$preset_cols = array( 3, 4, 5 );

		// Valor guardado (post meta). Defecto: 3.
		$current   = absint( get_post_meta( $this->gallery->ID, '_gd_columns', true ) );
		$current   = $current >= 1 && $current <= 8 ? $current : 3;
		$is_custom = ! in_array( $current, $preset_cols, true );
		?>
		<div class="gd-field">

			<span class="gd-field__label">
				<?php esc_html_e( 'Columnas', 'galerias-domi' ); ?>
			</span>

			<div class="gd-columns-picker"
				role="radiogroup"
				aria-label="<?php esc_attr_e( 'Número de columnas', 'galerias-domi' ); ?>">

				<?php foreach ( $preset_cols as $cols ) : ?>
					<label
						class="gd-col-option"
						for="gd-col-<?php echo esc_attr( $cols ); ?>"
						title="<?php echo esc_attr( sprintf( _n( '%d columna', '%d columnas', $cols, 'galerias-domi' ), $cols ) ); ?>">

						<input
							type="radio"
							id="gd-col-<?php echo esc_attr( $cols ); ?>"
							name="gd_columns"
							value="<?php echo esc_attr( $cols ); ?>"
							<?php checked( $cols, $current ); ?>>

						<span class="gd-col-option__preview">
							<?php for ( $i = 0; $i < $cols; $i++ ) : ?>
								<span></span>
							<?php endfor; ?>
						</span>

						<span class="gd-col-option__num">
							<?php echo esc_html( $cols ); ?>
						</span>

					</label>
				<?php endforeach; ?>

				<!-- Opción personalizada -->
				<label
					class="gd-col-option gd-col-option--custom"
					for="gd-col-custom"
					title="<?php esc_attr_e( 'Número personalizado', 'galerias-domi' ); ?>">

					<input
						type="radio"
						id="gd-col-custom"
						name="gd_columns"
						value="<?php echo esc_attr( $is_custom ? $current : 6 ); ?>"
						<?php checked( $is_custom, true ); ?>>

					<span class="gd-col-option__preview">
						<input
							type="number"
							class="gd-col-custom-input"
							min="1"
							max="8"
							value="<?php echo esc_attr( $is_custom ? $current : 6 ); ?>"
							aria-label="<?php esc_attr_e( 'Número personalizado de columnas (máximo 8)', 'galerias-domi' ); ?>">
					</span>

					<span class="gd-col-option__num">
						<?php esc_html_e( 'otro', 'galerias-domi' ); ?>
					</span>

				</label>

			</div><!-- .gd-columns-picker -->

		</div><!-- .gd-field -->
		<?php
	}

	/**
	 * Renderiza el campo de selección de efecto hover.
	 *
	 * @since 1.0.0
	 */
	private function render_field_hover_effect(): void {
		$current = get_post_meta( $this->gallery->ID, '_gd_hover_effect', true );
		$current = $current ?: 'shadow';

		$effects = array(
			array(
				'value' => 'shadow',
				'label' => __( 'Sombra', 'galerias-domi' ),
				'title' => __( 'Sombra sobre la imagen', 'galerias-domi' ),
			),
			array(
				'value' => 'border',
				'label' => __( 'Borde', 'galerias-domi' ),
				'title' => __( 'Borde sobre la imagen', 'galerias-domi' ),
			),
			array(
				'value' => 'zoom-in',
				'label' => __( 'Zoom-in', 'galerias-domi' ),
				'title' => __( 'Zoom de aumento', 'galerias-domi' ),
			),
			array(
				'value' => 'zoom-out',
				'label' => __( 'Zoom-out', 'galerias-domi' ),
				'title' => __( 'Zoom de reducción', 'galerias-domi' ),
			),
			array(
				'value' => 'blur',
				'label' => __( 'Blur', 'galerias-domi' ),
				'title' => __( 'Desenfoque con texto', 'galerias-domi' ),
			),
		);
		?>
		<div class="gd-field">

			<span class="gd-field__label">
				<?php esc_html_e( 'Efecto hover', 'galerias-domi' ); ?>
			</span>

			<div class="gd-hover-picker"
				role="radiogroup"
				aria-label="<?php esc_attr_e( 'Efecto al pasar el cursor', 'galerias-domi' ); ?>">

				<?php foreach ( $effects as $effect ) : ?>
					<label
						class="gd-hover-option"
						for="gd-hover-<?php echo esc_attr( $effect['value'] ); ?>"
						title="<?php echo esc_attr( $effect['title'] ); ?>">

						<input
							type="radio"
							id="gd-hover-<?php echo esc_attr( $effect['value'] ); ?>"
							name="gd_hover_effect"
							value="<?php echo esc_attr( $effect['value'] ); ?>"
							<?php checked( $effect['value'], $current ); ?>>

						<span class="gd-hover-option__preview gd-hover-preview--<?php echo esc_attr( $effect['value'] ); ?>">
							<span class="gd-hover-preview__img">
								<?php if ( 'blur' === $effect['value'] ) : ?>
									<span class="gd-hover-preview__overlay">Ver</span>
								<?php endif; ?>
							</span>
						</span>

						<span class="gd-hover-option__label">
							<?php echo esc_html( $effect['label'] ); ?>
						</span>

					</label>
				<?php endforeach; ?>

			</div><!-- .gd-hover-picker -->

		</div><!-- .gd-field -->
		<?php
	}

	/* =========================================================
	 * PANEL DE IMÁGENES (columna derecha)
	 * ======================================================= */

	/**
	 * Renderiza el panel de carga y gestión de imágenes.
	 *
	 * @since 1.0.0
	 */
	private function render_images_panel(): void {
		$saved  = get_post_meta( $this->gallery->ID, '_gd_images', true );
		$images = array();

		if ( is_array( $saved ) ) {
			foreach ( $saved as $item ) {
				if ( is_numeric( $item ) ) {
					$images[] = array( 'id' => (int) $item, 'filter' => 'todos' );
				} elseif ( is_array( $item ) && ! empty( $item['id'] ) ) {
					$images[] = array(
						'id'     => absint( $item['id'] ),
						'filter' => sanitize_key( $item['filter'] ?? 'todos' ),
					);
				}
			}
		}

		$filters_enabled = (bool) get_post_meta( $this->gallery->ID, '_gd_filters_enabled', true );
		$saved_filters   = get_post_meta( $this->gallery->ID, '_gd_filters', true );
		$filter_list     = is_array( $saved_filters ) ? $saved_filters : array();

		$count = count( $images );
		if ( 0 === $count ) {
			$count_text = esc_html__( 'Sin imágenes', 'galerias-domi' );
		} elseif ( 1 === $count ) {
			$count_text = esc_html__( '1 imagen', 'galerias-domi' );
		} else {
			/* translators: %d: número de imágenes */
			$count_text = esc_html( sprintf( __( '%d imágenes', 'galerias-domi' ), $count ) );
		}

		$dropzone_class    = ! empty( $images ) ? ' gd-hidden' : '';
		$list_class        = empty( $images )   ? ' gd-hidden' : '';
		$filter_col_hidden = $filters_enabled ? '' : ' gd-hidden';
		?>
		<div class="gd-card gd-images-card">

			<div class="gd-card__header">
				<h2 class="gd-card__title">
					<?php esc_html_e( 'Imágenes de la galería', 'galerias-domi' ); ?>
				</h2>
			</div>

			<div class="gd-images-card__body">

				<!-- Estado vacío -->
				<div class="gd-dropzone<?php echo esc_attr( $dropzone_class ); ?>"
					id="gd-dropzone" role="button" tabindex="0"
					aria-label="<?php esc_attr_e( 'Abrir biblioteca de medios', 'galerias-domi' ); ?>">
					<span class="gd-dropzone__icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24"
							fill="none" stroke="currentColor" stroke-width="1.4"
							stroke-linecap="round" stroke-linejoin="round">
							<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
							<circle cx="8.5" cy="8.5" r="1.5"/>
							<polyline points="21 15 16 10 5 21"/>
						</svg>
					</span>
					<p class="gd-dropzone__title">
						<?php esc_html_e( 'Todavía no hay imágenes', 'galerias-domi' ); ?>
					</p>
					<p class="gd-dropzone__desc">
						<?php esc_html_e( 'Haz clic en «Agregar imágenes» o aquí para seleccionar desde la biblioteca de medios.', 'galerias-domi' ); ?>
					</p>
				</div>

				<!-- Lista de imágenes -->
				<div class="gd-images-list<?php echo esc_attr( $list_class ); ?>" id="gd-images-list">

					<?php foreach ( $images as $idx => $image ) :
						$aid   = $image['id'];
						$thumb = wp_get_attachment_image_url( $aid, 'thumbnail' );
						$preview = wp_get_attachment_image_url( $aid, 'large' ) ?: (string) wp_get_attachment_url( $aid );
						if ( ! $thumb ) { continue; }
						$title  = get_the_title( $aid ) ?: esc_html__( '(sin título)', 'galerias-domi' );
						$filter = $image['filter'];
					?>
					<div class="gd-image-row" data-id="<?php echo esc_attr( $aid ); ?>">

						<button type="button" class="gd-filter-handle"
							aria-label="<?php esc_attr_e( 'Mover imagen', 'galerias-domi' ); ?>"
							tabindex="-1">&#x2847;</button>

						<div class="gd-image-row__thumb">
							<img src="<?php echo esc_url( $thumb ); ?>"
								alt="<?php echo esc_attr( $title ); ?>"
								draggable="false">
						</div>

						<div class="gd-image-row__info">
							<span class="gd-image-row__name">
								<?php echo esc_html( $title ); ?>
							</span>
							<div class="gd-image-row__filter<?php echo esc_attr( $filter_col_hidden ); ?>">
								<select
									name="gd_images[<?php echo esc_attr( $idx ); ?>][filter]"
									class="gd-image-filter-select">
									<option value="todos" <?php selected( $filter, 'todos' ); ?>>
										<?php esc_html_e( 'Todos', 'galerias-domi' ); ?>
									</option>
									<?php foreach ( $filter_list as $fitem ) :
										$fname = sanitize_text_field( $fitem['name'] ?? '' );
										$fid   = sanitize_key( $fitem['id'] ?? '' );
										if ( '' === $fname || '' === $fid ) { continue; }
									?>
									<option value="<?php echo esc_attr( $fid ); ?>" <?php selected( $filter, $fid ); ?>>
										<?php echo esc_html( $fname ); ?>
									</option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>

						<button type="button" class="gd-image-preview-btn"
							data-src="<?php echo esc_url( $preview ); ?>"
							data-title="<?php echo esc_attr( $title ); ?>">
							<?php esc_html_e( 'Vista previa', 'galerias-domi' ); ?>
						</button>

						<button type="button" class="gd-image-remove"
							aria-label="<?php esc_attr_e( 'Eliminar imagen', 'galerias-domi' ); ?>">
							&#x2715;
						</button>

						<input type="hidden"
							name="gd_images[<?php echo esc_attr( $idx ); ?>][id]"
							value="<?php echo esc_attr( $aid ); ?>">

					</div><!-- .gd-image-row -->
					<?php endforeach; ?>

				</div><!-- .gd-images-list -->

			</div><!-- .gd-images-card__body -->

			<div class="gd-card__footer gd-images-card__footer">
				<span class="gd-images-count" id="gd-images-count">
					<?php echo $count_text; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</span>
				<button type="button" class="gd-btn-add-images" id="gd-btn-add-images">
					<?php esc_html_e( '+ Agregar imágenes', 'galerias-domi' ); ?>
				</button>
			</div>

		</div><!-- .gd-images-card -->

		<div class="gd-modal" id="gd-image-modal" role="dialog" aria-modal="true"
			aria-labelledby="gd-modal-title" hidden>
			<div class="gd-modal__overlay" id="gd-modal-overlay"></div>
			<div class="gd-modal__box">
				<div class="gd-modal__header">
					<span class="gd-modal__title" id="gd-modal-title"></span>
					<button type="button" class="gd-modal__close" id="gd-modal-close"
						aria-label="<?php esc_attr_e( 'Cerrar', 'galerias-domi' ); ?>">&#x2715;</button>
				</div>
				<div class="gd-modal__body">
					<img src="" alt="" id="gd-modal-img" class="gd-modal__img">
				</div>
			</div>
		</div><!-- .gd-modal -->
		<?php
	}

	/* =========================================================
	 * RENDER PRINCIPAL
	 * ======================================================= */

	/**
	 * Renderiza la página de edición completa.
	 *
	 * @since 1.0.0
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para acceder a esta página.', 'galerias-domi' ) );
		}

		if ( null === $this->gallery ) {
			wp_die( esc_html__( 'Galería no encontrada.', 'galerias-domi' ) );
		}

		$back_url     = admin_url( 'admin.php?page=' . Admin_Menu::MENU_SLUG );
		$tabs         = $this->get_tabs();
		$is_new       = '' === get_post_meta( $this->gallery->ID, '_gd_columns', true );
		$is_published = (bool) get_post_meta( $this->gallery->ID, '_gd_published', true );
		?>
		<div class="wrap">

			<h1 class="wp-heading-inline">
				<?php echo esc_html( $this->gallery->post_title ); ?>
			</h1>

			<a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action">
				&larr; <?php esc_html_e( 'Volver al listado', 'galerias-domi' ); ?>
			</a>

			<?php if ( ! $is_published ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gd-publish-form">
					<?php wp_nonce_field( self::PUBLISH_NONCE ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( self::PUBLISH_ACTION ); ?>">
					<input type="hidden" name="id" value="<?php echo esc_attr( $this->gallery->ID ); ?>">
					<button type="submit" class="gd-btn-publish">
						<?php esc_html_e( 'Publicar galería', 'galerias-domi' ); ?>
					</button>
				</form>
			<?php endif; ?>

			<hr class="wp-header-end">

			<?php if ( isset( $_GET['saved'] ) && '1' === $_GET['saved'] ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Configuración guardada correctamente.', 'galerias-domi' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['published'] ) && '1' === $_GET['published'] ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( '¡Galería publicada! El shortcode ya está disponible.', 'galerias-domi' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( self::NONCE ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<input type="hidden" name="id" value="<?php echo esc_attr( $this->gallery->ID ); ?>">

				<div class="gd-edit-layout">

					<!-- 40 % — Opciones -->
					<div class="gd-option-part">
						<div class="gd-card">

							<div class="gd-card__header">
								<h2 class="gd-card__title">
									<?php esc_html_e( 'Opciones de tu galería DOMI', 'galerias-domi' ); ?>
								</h2>
							</div>

							<div class="gd-tabs">

								<?php foreach ( $tabs as $index => $tab ) : ?>

									<button
										type="button"
										class="gd-tab-btn <?php echo ( $is_new && 0 === $index ) ? 'is-active' : ''; ?>"
										id="gd-tab-btn-<?php echo esc_attr( $tab['id'] ); ?>"
										data-tab="<?php echo esc_attr( $tab['id'] ); ?>"
										aria-expanded="<?php echo ( $is_new && 0 === $index ) ? 'true' : 'false'; ?>"
										aria-controls="gd-tab-<?php echo esc_attr( $tab['id'] ); ?>">
										<?php echo esc_html( $tab['label'] ); ?>
									</button>

									<div
										class="gd-tab-panel"
										id="gd-tab-<?php echo esc_attr( $tab['id'] ); ?>"
										role="region"
										aria-labelledby="gd-tab-btn-<?php echo esc_attr( $tab['id'] ); ?>"
										<?php echo ( ! $is_new || 0 !== $index ) ? 'hidden' : ''; ?>>

										<div class="gd-tab-panel__body">
											<?php call_user_func( $tab['callback'] ); ?>
										</div>

									</div>

								<?php endforeach; ?>

							</div><!-- .gd-tabs -->

						</div><!-- .gd-card -->
					</div><!-- .gd-option-part -->

					<!-- 60 % — Imágenes -->
					<div class="gd-preview-part">
						<?php $this->render_images_panel(); ?>
					</div>

				</div><!-- .gd-edit-layout -->

				<div class="gd-save-bar">
					<button type="submit" class="gd-btn-save">
						<?php esc_html_e( 'Guardar galería', 'galerias-domi' ); ?>
					</button>
				</div>

			</form>

		</div><!-- .wrap -->
		<?php
	}
}
