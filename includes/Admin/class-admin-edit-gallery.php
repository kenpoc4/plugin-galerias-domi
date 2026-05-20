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

	/**
	 * Post de la galería que se está editando.
	 *
	 * @since 1.0.0
	 * @var \WP_Post|null
	 */
	private ?\WP_Post $gallery;

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
	 * Renderiza el repeater de filtros disponibles.
	 *
	 * @since 1.0.0
	 */
	private function render_field_filters_list(): void {
		$saved   = get_post_meta( $this->gallery->ID, '_gd_filters', true );
		$filters = is_array( $saved ) ? $saved : array();
		?>

		<!-- Filtros disponibles -->
		<div class="gd-field">

			<span class="gd-field__label">
				<?php esc_html_e( 'Filtros disponibles', 'galerias-domi' ); ?>
			</span>

			<div class="gd-filters-list" id="gd-filters-list">

				<!-- Fila "Todos" — fija, no eliminable -->
				<div class="gd-filter-row gd-filter-row--default">
					<div class="gd-filter-row__fields">
						<span class="gd-filter-row__fixed-name">
							<?php esc_html_e( 'Todos', 'galerias-domi' ); ?>
						</span>
						<span class="gd-filter-row__fixed-id">todos</span>
					</div>
					<span class="gd-filter-row__badge">
						<?php esc_html_e( 'Por defecto', 'galerias-domi' ); ?>
					</span>
				</div>

				<?php foreach ( $filters as $index => $filter ) :
					$name = sanitize_text_field( $filter['name'] ?? '' );
					$id   = sanitize_key( $filter['id'] ?? '' );
				?>
				<div class="gd-filter-row">
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
							value="<?php echo esc_attr( $id ); ?>"
							placeholder="id-del-filtro"
							data-auto="false">
					</div>
					<button
						type="button"
						class="gd-filter-remove"
						aria-label="<?php esc_attr_e( 'Eliminar filtro', 'galerias-domi' ); ?>">
						&times;
					</button>
				</div>
				<?php endforeach; ?>

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

		$back_url = admin_url( 'admin.php?page=' . Admin_Menu::MENU_SLUG );
		$tabs     = $this->get_tabs();
		?>
		<div class="wrap">

			<h1 class="wp-heading-inline">
				<?php echo esc_html( $this->gallery->post_title ); ?>
			</h1>

			<a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action">
				&larr; <?php esc_html_e( 'Volver al listado', 'galerias-domi' ); ?>
			</a>

			<hr class="wp-header-end">

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
									class="gd-tab-btn <?php echo 0 === $index ? 'is-active' : ''; ?>"
									id="gd-tab-btn-<?php echo esc_attr( $tab['id'] ); ?>"
									data-tab="<?php echo esc_attr( $tab['id'] ); ?>"
									aria-expanded="<?php echo 0 === $index ? 'true' : 'false'; ?>"
									aria-controls="gd-tab-<?php echo esc_attr( $tab['id'] ); ?>">
									<?php echo esc_html( $tab['label'] ); ?>
								</button>

								<div
									class="gd-tab-panel"
									id="gd-tab-<?php echo esc_attr( $tab['id'] ); ?>"
									role="region"
									aria-labelledby="gd-tab-btn-<?php echo esc_attr( $tab['id'] ); ?>"
									<?php echo 0 !== $index ? 'hidden' : ''; ?>>

									<div class="gd-tab-panel__body">
										<?php call_user_func( $tab['callback'] ); ?>
									</div>

								</div>

							<?php endforeach; ?>

						</div><!-- .gd-tabs -->

					</div><!-- .gd-card -->
				</div><!-- .gd-option-part -->

				<!-- 60 % — Vista previa (por implementar) -->
				<div class="gd-preview-part"></div>

			</div><!-- .gd-edit-layout -->

		</div><!-- .wrap -->
		<?php
	}
}
