<?php
/**
 * Tabla de listado de galerías usando WP_List_Table.
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

// WP_List_Table no se carga automáticamente fuera de su contexto.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Clase Galleries_List_Table.
 *
 * Extiende WP_List_Table para mostrar las galerías creadas
 * con columnas: Título, Shortcode, Autor y Fecha.
 *
 * @since 1.0.0
 */
class Galleries_List_Table extends \WP_List_Table {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => __( 'Galeria', 'galerias-domi' ),
				'plural'   => __( 'Galerias', 'galerias-domi' ),
				'ajax'     => false,
			)
		);
	}

	/**
	 * Define las columnas de la tabla.
	 *
	 * @since 1.0.0
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return array(
			'title'     => __( 'Título', 'galerias-domi' ),
			'shortcode' => __( 'Shortcode', 'galerias-domi' ),
			'author'    => __( 'Autor', 'galerias-domi' ),
			'date'      => __( 'Fecha', 'galerias-domi' ),
		);
	}

	/**
	 * Columnas que permiten ordenamiento.
	 *
	 * @since 1.0.0
	 * @return array<string, array<int, bool|string>>
	 */
	protected function get_sortable_columns(): array {
		return array(
			'title' => array( 'title', false ),
			'date'  => array( 'date', true ),
		);
	}

	/**
	 * Prepara los elementos para la tabla.
	 *
	 * @since 1.0.0
	 */
	public function prepare_items(): void {
		$per_page     = 20;
		$current_page = $this->get_pagenum();

		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'date'; // phpcs:ignore WordPress.Security.NonceVerification
		$order   = isset( $_GET['order'] ) && 'asc' === $_GET['order'] ? 'ASC' : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification

		$query = new \WP_Query(
			array(
				'post_type'      => Gallery_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => $per_page,
				'paged'          => $current_page,
				'orderby'        => $orderby,
				'order'          => $order,
			)
		);

		$this->set_pagination_args(
			array(
				'total_items' => $query->found_posts,
				'per_page'    => $per_page,
				'total_pages' => $query->max_num_pages,
			)
		);

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);

		$this->items = $query->posts;
	}

	/**
	 * Renderiza la columna Título con enlace de edición.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $item Entrada actual.
	 * @return string
	 */
	protected function column_title( \WP_Post $item ): string {
		$edit_url = add_query_arg(
			array(
				'page'   => Admin_Menu::MENU_SLUG,
				'action' => 'edit',
				'id'     => $item->ID,
			),
			admin_url( 'admin.php' )
		);

		$title = ! empty( $item->post_title )
			? esc_html( $item->post_title )
			: '<em>' . esc_html__( '(sin título)', 'galerias-domi' ) . '</em>';

		$actions = array(
			'edit' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $edit_url ),
				esc_html__( 'Editar', 'galerias-domi' )
			),
		);

		return sprintf( '<strong><a href="%s">%s</a></strong>', esc_url( $edit_url ), $title )
			. $this->row_actions( $actions );
	}

	/**
	 * Renderiza la columna Shortcode.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $item Entrada actual.
	 * @return string
	 */
	protected function column_shortcode( \WP_Post $item ): string {
		$shortcode = sprintf( '[galeria_domi id="%d"]', $item->ID );
		return sprintf(
			'<code style="user-select:all;">%s</code>',
			esc_html( $shortcode )
		);
	}

	/**
	 * Renderiza la columna Autor.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $item Entrada actual.
	 * @return string
	 */
	protected function column_author( \WP_Post $item ): string {
		$author = get_the_author_meta( 'display_name', $item->post_author );
		return esc_html( $author );
	}

	/**
	 * Renderiza la columna Fecha.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $item Entrada actual.
	 * @return string
	 */
	protected function column_date( \WP_Post $item ): string {
		return esc_html(
			get_the_date( get_option( 'date_format' ), $item )
		);
	}

	/**
	 * Renderizado por defecto para columnas sin método específico.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $item        Entrada actual.
	 * @param string   $column_name Nombre de la columna.
	 * @return string
	 */
	protected function column_default( $item, $column_name ): string {
		return '';
	}

	/**
	 * Mensaje cuando no hay galerías.
	 *
	 * @since 1.0.0
	 */
	public function no_items(): void {
		esc_html_e( 'Aún no has creado ninguna galería.', 'galerias-domi' );
	}
}
