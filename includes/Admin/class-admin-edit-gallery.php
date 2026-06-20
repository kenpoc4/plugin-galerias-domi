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
	const RENDER_ACTION  = 'galerias_domi_render_gallery';
	const RENDER_NONCE   = 'galerias_domi_render_gallery_nonce';

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
	 * Registra el hook de renderizado vía admin-post.php.
	 *
	 * @since 1.0.0
	 */
	public static function register_render_hook(): void {
		add_action(
			'admin_post_' . self::RENDER_ACTION,
			function () {
				$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
				( new self( $id ) )->handle_render();
			}
		);
	}

	/**
	 * Renderiza la galería: congela la configuración guardada en un snapshot
	 * y avanza la versión de render a la versión de guardado actual.
	 *
	 * El frontend (shortcode) solo refleja los cambios tras este paso.
	 *
	 * @since 1.0.0
	 */
	public function handle_render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'galerias-domi' ) );
		}

		check_admin_referer( self::RENDER_NONCE );

		if ( null === $this->gallery ) {
			wp_die( esc_html__( 'Galería no encontrada.', 'galerias-domi' ) );
		}

		$id = $this->gallery->ID;

		// Solo se puede renderizar una galería publicada y con cambios guardados.
		$is_published   = (bool) get_post_meta( $id, '_gd_published', true );
		$save_version   = absint( get_post_meta( $id, '_gd_save_version', true ) );
		$render_version = absint( get_post_meta( $id, '_gd_render_version', true ) );

		if ( ! $is_published || $save_version <= $render_version ) {
			wp_die( esc_html__( 'La galería no está lista para renderizarse.', 'galerias-domi' ) );
		}

		// Congelar la configuración guardada actual como snapshot de render.
		$snapshot = $this->build_config_snapshot( $id );
		update_post_meta( $id, '_gd_rendered_data', $snapshot );

		// La versión de render se iguala a la de guardado: la galería queda al día.
		update_post_meta( $id, '_gd_render_version', $save_version );
		update_post_meta( $id, '_gd_rendered_at', time() );

		// Invalidar la caché del frontend de la versión anterior.
		delete_transient( 'gd_gallery_html_' . $id . '_' . $render_version );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'     => Admin_Menu::MENU_SLUG,
					'action'   => 'edit',
					'id'       => $id,
					'rendered' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Construye el snapshot de configuración a partir de los post metas guardados.
	 *
	 * Este array es lo que el shortcode usa para renderizar el frontend; se guarda
	 * en el momento del render para que el sitio público solo cambie al renderizar.
	 *
	 * @since 1.0.0
	 * @param int $id ID de la galería.
	 * @return array<string, mixed>
	 */
	private function build_config_snapshot( int $id ): array {
		$columns = absint( get_post_meta( $id, '_gd_columns', true ) );
		$columns = $columns >= 1 && $columns <= 8 ? $columns : 3;

		$hover_effect = (string) get_post_meta( $id, '_gd_hover_effect', true );
		$hover_effect = $hover_effect ?: 'shadow';

		$filter_style = (string) get_post_meta( $id, '_gd_filter_style', true );
		$filter_style = $filter_style ?: 'buttons';

		// Variante efectiva: la del tipo seleccionado (cada tipo guarda la suya).
		$filter_variant = $this->get_saved_variant( $id, $filter_style );

		$filter_shape = (string) get_post_meta( $id, '_gd_filter_shape', true );
		$filter_shape = in_array( $filter_shape, self::FILTER_SHAPES, true ) ? $filter_shape : self::FILTER_SHAPE_DEFAULT;

		$saved_filters = get_post_meta( $id, '_gd_filters', true );
		$filters       = array();
		if ( is_array( $saved_filters ) ) {
			foreach ( $saved_filters as $f ) {
				$fname = sanitize_text_field( $f['name'] ?? '' );
				$fid   = sanitize_key( $f['id'] ?? '' );
				if ( '' !== $fname && '' !== $fid ) {
					$filters[] = array(
						'name' => $fname,
						'id'   => $fid,
					);
				}
			}
		}

		$saved_images = get_post_meta( $id, '_gd_images', true );
		$images       = array();
		if ( is_array( $saved_images ) ) {
			foreach ( $saved_images as $item ) {
				if ( is_numeric( $item ) ) {
					$images[] = array(
						'id'     => (int) $item,
						'filter' => 'todos',
					);
				} elseif ( is_array( $item ) && ! empty( $item['id'] ) ) {
					$images[] = array(
						'id'     => absint( $item['id'] ),
						'filter' => sanitize_key( $item['filter'] ?? 'todos' ),
					);
				}
			}
		}

		$pagination_rows = absint( get_post_meta( $id, '_gd_pagination_rows', true ) );
		$pagination_rows = in_array( $pagination_rows, array( 3, 6, 9, 12 ), true ) ? $pagination_rows : 6;

		$raw_show_todos = get_post_meta( $id, '_gd_show_todos', true );

		$width = absint( get_post_meta( $id, '_gd_width', true ) );
		$width = in_array( $width, self::WIDTH_VALUES, true ) ? $width : self::WIDTH_DEFAULT;

		return array(
			'columns'            => $columns,
			'width'              => $width,
			'hover_effect'       => $hover_effect,
			'filters_enabled'    => (bool) get_post_meta( $id, '_gd_filters_enabled', true ),
			'filter_style'       => $filter_style,
			'filter_variant'     => $filter_variant,
			'filter_shape'       => $filter_shape,
			'show_todos'         => '' !== $raw_show_todos ? (bool) $raw_show_todos : true,
			'todos_position'     => min( absint( get_post_meta( $id, '_gd_todos_position', true ) ), count( $filters ) ),
			'filters'            => $filters,
			'pagination_enabled' => (bool) get_post_meta( $id, '_gd_pagination_enabled', true ),
			'pagination_rows'    => $pagination_rows,
			'images'             => $images,
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

		// Variante de estilo del filtro (una por tipo; cada una conserva su valor).
		$variant_buttons = sanitize_key( wp_unslash( $_POST['gd_filter_variant_buttons'] ?? '' ) );
		if ( ! in_array( $variant_buttons, self::FILTER_VARIANTS_BUTTONS, true ) ) {
			$variant_buttons = self::FILTER_VARIANT_DEFAULT;
		}
		update_post_meta( $id, '_gd_filter_variant_buttons', $variant_buttons );

		$variant_select = sanitize_key( wp_unslash( $_POST['gd_filter_variant_select'] ?? '' ) );
		if ( ! in_array( $variant_select, self::FILTER_VARIANTS_SELECT, true ) ) {
			$variant_select = self::FILTER_VARIANT_DEFAULT;
		}
		update_post_meta( $id, '_gd_filter_variant_select', $variant_select );

		// Forma del filtro (redondo / cuadrado).
		$filter_shape = sanitize_key( wp_unslash( $_POST['gd_filter_shape'] ?? '' ) );
		if ( ! in_array( $filter_shape, self::FILTER_SHAPES, true ) ) {
			$filter_shape = self::FILTER_SHAPE_DEFAULT;
		}
		update_post_meta( $id, '_gd_filter_shape', $filter_shape );

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

		// Ancho de la galería (porcentaje del ancho disponible).
		$width = absint( $_POST['gd_width'] ?? self::WIDTH_DEFAULT ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! in_array( $width, self::WIDTH_VALUES, true ) ) {
			$width = self::WIDTH_DEFAULT;
		}
		update_post_meta( $id, '_gd_width', $width );

		// Avanzar la versión de guardado y registrar la fecha.
		// Esta versión es la que se compara contra la de render para habilitar
		// el botón "Renderizar".
		$save_version = absint( get_post_meta( $id, '_gd_save_version', true ) ) + 1;
		update_post_meta( $id, '_gd_save_version', $save_version );
		update_post_meta( $id, '_gd_saved_at', time() );

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
			array(
				'id'       => 'espacios',
				'label'    => __( 'Espacios', 'galerias-domi' ),
				'callback' => array( $this, 'render_tab_espacios' ),
			),
		);
	}

	/**
	 * Valores de ancho permitidos (porcentaje del ancho disponible).
	 *
	 * @since 1.0.0
	 * @var int[]
	 */
	private const WIDTH_VALUES = array( 100, 95, 80, 65, 50 );

	/**
	 * Ancho por defecto cuando no hay valor guardado.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private const WIDTH_DEFAULT = 100;

	/**
	 * Variantes de estilo disponibles para el tipo "botones".
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	private const FILTER_VARIANTS_BUTTONS = array( 'solid', 'outline', 'minimal' );

	/**
	 * Variantes de estilo disponibles para el tipo "selector".
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	private const FILTER_VARIANTS_SELECT = array( 'solid', 'minimal' );

	/**
	 * Variante de estilo por defecto.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const FILTER_VARIANT_DEFAULT = 'solid';

	/**
	 * Devuelve las variantes de estilo permitidas para un tipo de filtro.
	 *
	 * @since 1.0.0
	 * @param string $style 'buttons' | 'select'.
	 * @return string[]
	 */
	private function variants_for_style( string $style ): array {
		return 'select' === $style ? self::FILTER_VARIANTS_SELECT : self::FILTER_VARIANTS_BUTTONS;
	}

	/**
	 * Lee la variante de estilo guardada para un tipo concreto.
	 *
	 * Cada tipo recuerda su propia elección en su meta. Si no hay valor (galería
	 * anterior a esta separación) cae al meta unificado `_gd_filter_variant`.
	 *
	 * @since 1.0.0
	 * @param int    $id    ID de la galería.
	 * @param string $style 'buttons' | 'select'.
	 * @return string
	 */
	private function get_saved_variant( int $id, string $style ): string {
		$meta_key = 'select' === $style ? '_gd_filter_variant_select' : '_gd_filter_variant_buttons';
		$value    = (string) get_post_meta( $id, $meta_key, true );

		if ( '' === $value ) {
			// Compatibilidad con el meta unificado anterior.
			$value = (string) get_post_meta( $id, '_gd_filter_variant', true );
		}

		$allowed = $this->variants_for_style( $style );
		return in_array( $value, $allowed, true ) ? $value : self::FILTER_VARIANT_DEFAULT;
	}

	/**
	 * Formas permitidas para los filtros (no aplica a la variante "minimal").
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	private const FILTER_SHAPES = array( 'rounded', 'square' );

	/**
	 * Forma por defecto.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const FILTER_SHAPE_DEFAULT = 'rounded';

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
		$variant_buttons = $this->get_saved_variant( $this->gallery->ID, 'buttons' );
		$variant_select  = $this->get_saved_variant( $this->gallery->ID, 'select' );
		$filter_shape    = get_post_meta( $this->gallery->ID, '_gd_filter_shape', true );
		$filter_shape    = in_array( $filter_shape, self::FILTER_SHAPES, true ) ? $filter_shape : self::FILTER_SHAPE_DEFAULT;
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
				<?php esc_html_e( 'Tipo de Filtro', 'galerias-domi' ); ?>
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

		<!-- Estilo del filtro (un picker por tipo; se muestra el del tipo activo) -->
		<?php
		$this->render_variant_picker( 'buttons', $variant_buttons, $filters_enabled, 'buttons' !== $filter_style );
		$this->render_variant_picker( 'select', $variant_select, $filters_enabled, 'select' !== $filter_style );

		// La forma solo aplica al tipo "botones" y no a la variante "minimal".
		$shape_hidden   = 'buttons' !== $filter_style;
		$shape_disabled = ! $filters_enabled || 'minimal' === $variant_buttons;
		?>
		<!-- Forma del filtro (solo tipo botones; requiere filtros activos y variante ≠ minimal) -->
		<div class="gd-field <?php echo $shape_disabled ? 'is-disabled' : ''; ?><?php echo $shape_hidden ? ' gd-hidden' : ''; ?>"
			id="gd-filter-shape-field"
			aria-disabled="<?php echo $shape_disabled ? 'true' : 'false'; ?>">

			<span class="gd-field__label">
				<?php esc_html_e( 'Forma', 'galerias-domi' ); ?>
			</span>

			<div class="gd-select-wrap">
				<select
					id="gd-filter-shape"
					name="gd_filter_shape"
					<?php echo $shape_disabled ? 'tabindex="-1"' : ''; ?>>
					<option value="rounded" <?php selected( $filter_shape, 'rounded' ); ?>>
						<?php esc_html_e( 'Redondo', 'galerias-domi' ); ?>
					</option>
					<option value="square" <?php selected( $filter_shape, 'square' ); ?>>
						<?php esc_html_e( 'Cuadrado', 'galerias-domi' ); ?>
					</option>
				</select>
			</div>

		</div>

		<?php
		$this->render_field_filters_list();
	}

	/**
	 * Renderiza el picker de "Estilo del filtro" para un tipo concreto.
	 *
	 * Cada tipo (botones/selector) tiene su propio set de variantes y su propio
	 * campo en el formulario, por lo que cada uno conserva su elección de forma
	 * independiente. El picker que no corresponde al tipo activo se oculta.
	 *
	 * @since 1.0.0
	 * @param string $style           'buttons' | 'select'.
	 * @param string $current         Variante actualmente seleccionada.
	 * @param bool   $filters_enabled Si los filtros están activos.
	 * @param bool   $hidden          Si este picker debe ocultarse (tipo no activo).
	 */
	private function render_variant_picker( string $style, string $current, bool $filters_enabled, bool $hidden ): void {
		$labels = array(
			'solid'   => __( 'Sólido', 'galerias-domi' ),
			'outline' => __( 'Contorno', 'galerias-domi' ),
			'minimal' => __( 'Minimal', 'galerias-domi' ),
		);

		$classes = 'gd-field';
		if ( ! $filters_enabled ) {
			$classes .= ' is-disabled';
		}
		if ( $hidden ) {
			$classes .= ' gd-hidden';
		}
		$no_tab = ! $filters_enabled || $hidden;
		?>
		<div class="<?php echo esc_attr( $classes ); ?>"
			id="gd-filter-variant-<?php echo esc_attr( $style ); ?>-field"
			data-variant-field="<?php echo esc_attr( $style ); ?>"
			aria-disabled="<?php echo ! $filters_enabled ? 'true' : 'false'; ?>">

			<span class="gd-field__label">
				<?php esc_html_e( 'Estilo del filtro', 'galerias-domi' ); ?>
			</span>

			<div class="gd-variant-picker"
				role="radiogroup"
				aria-label="<?php esc_attr_e( 'Estilo del filtro', 'galerias-domi' ); ?>">

				<?php foreach ( $this->variants_for_style( $style ) as $value ) : ?>
				<label class="gd-variant-option"
					for="gd-variant-<?php echo esc_attr( $style ); ?>-<?php echo esc_attr( $value ); ?>">
					<input
						type="radio"
						id="gd-variant-<?php echo esc_attr( $style ); ?>-<?php echo esc_attr( $value ); ?>"
						name="gd_filter_variant_<?php echo esc_attr( $style ); ?>"
						value="<?php echo esc_attr( $value ); ?>"
						<?php checked( $value, $current ); ?>
						<?php echo $no_tab ? 'tabindex="-1"' : ''; ?>>
					<span class="gd-variant-option__preview gd-variant-preview--<?php echo esc_attr( $value ); ?>">
						<?php if ( 'select' === $style ) : ?>
							<span class="gd-variant-select-mock">
								<span class="gd-variant-select-mock__text"><?php esc_html_e( 'Todos', 'galerias-domi' ); ?></span>
								<span class="gd-variant-select-mock__arrow" aria-hidden="true">&#9662;</span>
							</span>
						<?php else : ?>
							<span class="gd-variant-chip is-active"><?php esc_html_e( 'Todos', 'galerias-domi' ); ?></span>
							<span class="gd-variant-chip"><?php esc_html_e( 'Paisaje', 'galerias-domi' ); ?></span>
						<?php endif; ?>
					</span>
					<span class="gd-variant-option__label">
						<?php echo esc_html( $labels[ $value ] ); ?>
					</span>
				</label>
				<?php endforeach; ?>

			</div>

		</div>
		<?php
	}

	/**
	 * Renderiza el contenido del tab "Espacios".
	 *
	 * @since 1.0.0
	 */
	private function render_tab_espacios(): void {
		$this->render_field_width();
	}

	/**
	 * Renderiza el selector de ancho de la galería.
	 *
	 * @since 1.0.0
	 */
	private function render_field_width(): void {
		$current = absint( get_post_meta( $this->gallery->ID, '_gd_width', true ) );
		$current = in_array( $current, self::WIDTH_VALUES, true ) ? $current : self::WIDTH_DEFAULT;
		?>
		<div class="gd-field">

			<span class="gd-field__label">
				<?php esc_html_e( 'Ancho de la galería', 'galerias-domi' ); ?>
			</span>
			<span class="gd-field__desc">
				<?php esc_html_e( 'Porcentaje del ancho disponible que ocupa la galería. Se centra automáticamente.', 'galerias-domi' ); ?>
			</span>

			<div class="gd-width-picker"
				role="radiogroup"
				aria-label="<?php esc_attr_e( 'Ancho de la galería', 'galerias-domi' ); ?>">

				<?php foreach ( self::WIDTH_VALUES as $val ) : ?>
				<label class="gd-width-option"
					for="gd-width-<?php echo esc_attr( $val ); ?>">
					<input
						type="radio"
						id="gd-width-<?php echo esc_attr( $val ); ?>"
						name="gd_width"
						value="<?php echo esc_attr( $val ); ?>"
						<?php checked( $val, $current ); ?>>
					<span class="gd-width-option__bar">
						<span class="gd-width-option__fill" style="width: <?php echo esc_attr( $val ); ?>%;"></span>
					</span>
					<span class="gd-width-option__label">
						<?php echo esc_html( $val ); ?>%
					</span>
				</label>
				<?php endforeach; ?>

			</div>

		</div>
		<?php
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

		// Versiones y fechas para la lógica del botón "Renderizar".
		$save_version   = absint( get_post_meta( $this->gallery->ID, '_gd_save_version', true ) );
		$render_version = absint( get_post_meta( $this->gallery->ID, '_gd_render_version', true ) );
		$saved_at       = absint( get_post_meta( $this->gallery->ID, '_gd_saved_at', true ) );
		$rendered_at    = absint( get_post_meta( $this->gallery->ID, '_gd_rendered_at', true ) );

		// Render habilitado solo si está publicada y la versión guardada es
		// mayor que la renderizada (hay cambios pendientes de renderizar).
		$can_render  = $is_published && $save_version > $render_version;
		$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
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

			<?php if ( isset( $_GET['rendered'] ) && '1' === $_GET['rendered'] ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( '¡Galería renderizada! El frontend ya refleja la última versión guardada.', 'galerias-domi' ); ?></p>
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

					<div class="gd-save-bar__meta">
						<?php if ( $saved_at ) : ?>
							<span class="gd-version-info">
								<?php
								printf(
									/* translators: %s: fecha del último guardado */
									esc_html__( 'Último guardado: %s', 'galerias-domi' ),
									esc_html( wp_date( $date_format, $saved_at ) )
								);
								?>
							</span>
						<?php endif; ?>
						<?php if ( $rendered_at ) : ?>
							<span class="gd-version-info">
								<?php
								printf(
									/* translators: %s: fecha del último renderizado */
									esc_html__( 'Último render: %s', 'galerias-domi' ),
									esc_html( wp_date( $date_format, $rendered_at ) )
								);
								?>
							</span>
						<?php endif; ?>
					</div>

					<div class="gd-save-bar__actions">
						<button type="submit" class="gd-btn-save">
							<?php esc_html_e( 'Guardar galería', 'galerias-domi' ); ?>
						</button>

						<button
							type="submit"
							form="gd-render-form"
							class="gd-btn-render"
							<?php disabled( $can_render, false ); ?>
							<?php if ( ! $can_render ) : ?>
								title="<?php echo esc_attr(
									$is_published
										? __( 'No hay cambios nuevos que renderizar.', 'galerias-domi' )
										: __( 'Publica y guarda la galería antes de renderizar.', 'galerias-domi' )
								); ?>"
							<?php endif; ?>>
							<?php esc_html_e( 'Renderizar', 'galerias-domi' ); ?>
						</button>
					</div>

				</div>

			</form>

			<!-- Formulario aislado del botón "Renderizar" (no envía la edición). -->
			<form id="gd-render-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( self::RENDER_NONCE ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::RENDER_ACTION ); ?>">
				<input type="hidden" name="id" value="<?php echo esc_attr( $this->gallery->ID ); ?>">
			</form>

		</div><!-- .wrap -->
		<?php
	}
}
