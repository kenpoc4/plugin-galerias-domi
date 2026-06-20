<?php
/**
 * Shortcode y renderizado del frontend de la galería.
 *
 * @package GaleriasDomi\Frontend
 * @since   1.0.0
 */

namespace GaleriasDomi\Frontend;

use GaleriasDomi\Post_Types\Gallery_Post_Type;

// Prevenir acceso directo.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clase Gallery_Shortcode.
 *
 * Registra el shortcode [galeria_domi] y construye el HTML del frontend a partir
 * del snapshot congelado en el paso de "Renderizar". El resultado se cachea en un
 * transient cuya llave incluye la versión de render, por lo que se invalida solo
 * cuando la galería se vuelve a renderizar.
 *
 * @since 1.0.0
 */
class Gallery_Shortcode {

	/**
	 * Etiqueta del shortcode.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const TAG = 'galeria_domi';

	/**
	 * Registra el shortcode y los assets del frontend.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		add_shortcode( self::TAG, array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Registra (sin encolar) los assets del frontend.
	 *
	 * El encolado real se hace dentro del shortcode, solo en las páginas que
	 * realmente muestran una galería.
	 *
	 * @since 1.0.0
	 */
	public function register_assets(): void {
		wp_register_style(
			'gd-frontend',
			GALERIAS_DOMI_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			GALERIAS_DOMI_VERSION
		);

		wp_register_script(
			'gd-frontend',
			GALERIAS_DOMI_PLUGIN_URL . 'assets/js/frontend.js',
			array(),
			GALERIAS_DOMI_VERSION,
			true
		);
	}

	/**
	 * Callback del shortcode.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed>|string $atts Atributos del shortcode.
	 * @return string HTML de la galería.
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, self::TAG );
		$id   = absint( $atts['id'] );

		if ( $id <= 0 ) {
			return '';
		}

		$post = get_post( $id );
		if ( ! $post || Gallery_Post_Type::POST_TYPE !== $post->post_type ) {
			return '';
		}

		// Solo se muestran galerías publicadas y ya renderizadas al menos una vez.
		$is_published = (bool) get_post_meta( $id, '_gd_published', true );
		$config       = get_post_meta( $id, '_gd_rendered_data', true );

		if ( ! $is_published || ! is_array( $config ) || empty( $config['images'] ) ) {
			return '';
		}

		// Encolar assets solo cuando hay una galería en la página.
		wp_enqueue_style( 'gd-frontend' );
		wp_enqueue_script( 'gd-frontend' );

		// Servir desde caché si existe para esta versión de render.
		$render_version = absint( get_post_meta( $id, '_gd_render_version', true ) );
		$cache_key      = 'gd_gallery_html_' . $id . '_' . $render_version;

		$html = get_transient( $cache_key );
		if ( false === $html ) {
			$html = $this->build_html( $id, $config );
			set_transient( $cache_key, $html, WEEK_IN_SECONDS );
		}

		return $html;
	}

	/**
	 * Construye el HTML completo de la galería a partir del snapshot.
	 *
	 * @since 1.0.0
	 * @param int                  $id     ID de la galería.
	 * @param array<string, mixed> $config Snapshot de configuración.
	 * @return string
	 */
	private function build_html( int $id, array $config ): string {
		$columns         = max( 1, min( 8, absint( $config['columns'] ?? 3 ) ) );
		$width           = absint( $config['width'] ?? 100 );
		$width           = in_array( $width, array( 100, 95, 80, 65, 50 ), true ) ? $width : 100;
		$hover           = sanitize_key( $config['hover_effect'] ?? 'shadow' );
		$filters_enabled = ! empty( $config['filters_enabled'] );
		$filter_style    = ( 'select' === ( $config['filter_style'] ?? 'buttons' ) ) ? 'select' : 'buttons';
		$filter_variant  = sanitize_key( $config['filter_variant'] ?? 'solid' );
		$filter_variant  = in_array( $filter_variant, array( 'solid', 'outline', 'minimal' ), true ) ? $filter_variant : 'solid';
		$filter_shape    = sanitize_key( $config['filter_shape'] ?? 'rounded' );
		$filter_shape    = in_array( $filter_shape, array( 'rounded', 'square' ), true ) ? $filter_shape : 'rounded';
		$filters         = is_array( $config['filters'] ?? null ) ? $config['filters'] : array();
		$show_todos      = ! empty( $config['show_todos'] );
		$todos_position  = absint( $config['todos_position'] ?? 0 );
		$images          = is_array( $config['images'] ?? null ) ? $config['images'] : array();

		$pagination_enabled = ! empty( $config['pagination_enabled'] );
		$pagination_rows    = absint( $config['pagination_rows'] ?? 6 );
		$per_page           = $pagination_enabled ? ( $pagination_rows * $columns ) : 0;

		$has_filters = $filters_enabled && ! empty( $filters );

		ob_start();
		?>
		<div class="gd-gallery gd-gallery--hover-<?php echo esc_attr( $hover ); ?>"
			id="gd-gallery-<?php echo esc_attr( $id ); ?>"
			style="--gd-cols: <?php echo esc_attr( $columns ); ?>; --gd-width: <?php echo esc_attr( $width ); ?>%;"
			data-per-page="<?php echo esc_attr( $per_page ); ?>">

			<?php if ( $has_filters ) : ?>
				<?php $this->render_filters( $filters, $show_todos, $todos_position, $filter_style, $filter_variant, $filter_shape ); ?>
			<?php endif; ?>

			<div class="gd-gallery__grid">
				<?php
				foreach ( $images as $image ) :
					$aid    = absint( $image['id'] ?? 0 );
					$filter = sanitize_key( $image['filter'] ?? 'todos' );
					if ( $aid <= 0 ) {
						continue;
					}

					$img = wp_get_attachment_image(
						$aid,
						'large',
						false,
						array(
							'class'   => 'gd-gallery__img',
							'loading' => 'lazy',
						)
					);
					if ( '' === $img ) {
						continue;
					}

					$caption = get_the_title( $aid );

					// Imagen a tamaño completo para el lightbox (con fallbacks).
					$full = wp_get_attachment_image_url( $aid, 'full' );
					if ( ! $full ) {
						$full = wp_get_attachment_image_url( $aid, 'large' );
					}
					if ( ! $full ) {
						$full = (string) wp_get_attachment_url( $aid );
					}

					/* translators: %s: título de la imagen */
					$open_label = '' !== $caption
						? sprintf( __( 'Ampliar imagen: %s', 'galerias-domi' ), $caption )
						: __( 'Ampliar imagen', 'galerias-domi' );
					?>
					<figure class="gd-gallery__item" data-filter="<?php echo esc_attr( $filter ); ?>">
						<button type="button" class="gd-gallery__open"
							data-full="<?php echo esc_url( $full ); ?>"
							data-caption="<?php echo esc_attr( $caption ); ?>"
							aria-label="<?php echo esc_attr( $open_label ); ?>">
							<?php echo $img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</button>
						<?php if ( 'blur' === $hover && '' !== $caption ) : ?>
							<figcaption class="gd-gallery__overlay">
								<span><?php echo esc_html( $caption ); ?></span>
							</figcaption>
						<?php endif; ?>
					</figure>
					<?php
				endforeach;
				?>
			</div><!-- .gd-gallery__grid -->

			<?php if ( $per_page > 0 ) : ?>
				<nav class="gd-gallery__pagination" aria-label="<?php esc_attr_e( 'Paginación de la galería', 'galerias-domi' ); ?>"></nav>
			<?php endif; ?>

		</div><!-- .gd-gallery -->
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Renderiza los controles de filtro (botones o selector).
	 *
	 * @since 1.0.0
	 * @param array<int, array{name: string, id: string}> $filters        Filtros.
	 * @param bool                                         $show_todos     Mostrar "Todos".
	 * @param int                                          $todos_position Posición de "Todos".
	 * @param string                                       $style          'buttons' | 'select'.
	 * @param string                                       $variant        'solid' | 'outline' | 'minimal'.
	 * @param string                                       $shape          'rounded' | 'square'.
	 */
	private function render_filters( array $filters, bool $show_todos, int $todos_position, string $style, string $variant = 'solid', string $shape = 'rounded' ): void {
		$variant_class = 'gd-gallery__filters--' . $variant;

		// La forma solo aplica al tipo "botones".
		if ( 'buttons' === $style ) {
			$variant_class .= ' gd-gallery__filters--shape-' . $shape;
		}
		// Construir la lista ordenada de controles, insertando "Todos" en su posición.
		$todos    = array(
			'name' => __( 'Todos', 'galerias-domi' ),
			'id'   => 'todos',
		);
		$controls = array();

		foreach ( $filters as $index => $filter ) {
			if ( $show_todos && $index === $todos_position ) {
				$controls[] = $todos;
			}
			$controls[] = array(
				'name' => (string) ( $filter['name'] ?? '' ),
				'id'   => sanitize_key( $filter['id'] ?? '' ),
			);
		}
		if ( $show_todos && $todos_position >= count( $filters ) ) {
			$controls[] = $todos;
		}

		// El primer control queda activo por defecto.
		$active_id = $controls[0]['id'] ?? 'todos';

		if ( 'select' === $style ) :
			?>
			<div class="gd-gallery__filters gd-gallery__filters--select <?php echo esc_attr( $variant_class ); ?>">
				<select class="gd-gallery__filter-select" aria-label="<?php esc_attr_e( 'Filtrar galería', 'galerias-domi' ); ?>">
					<?php foreach ( $controls as $control ) : ?>
						<option value="<?php echo esc_attr( $control['id'] ); ?>"
							<?php selected( $control['id'], $active_id ); ?>>
							<?php echo esc_html( $control['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php
		else :
			?>
			<div class="gd-gallery__filters gd-gallery__filters--buttons <?php echo esc_attr( $variant_class ); ?>" role="group"
				aria-label="<?php esc_attr_e( 'Filtrar galería', 'galerias-domi' ); ?>">
				<?php foreach ( $controls as $control ) : ?>
					<button type="button"
						class="gd-gallery__filter-btn <?php echo $control['id'] === $active_id ? 'is-active' : ''; ?>"
						data-filter="<?php echo esc_attr( $control['id'] ); ?>">
						<?php echo esc_html( $control['name'] ); ?>
					</button>
				<?php endforeach; ?>
			</div>
			<?php
		endif;
	}
}
