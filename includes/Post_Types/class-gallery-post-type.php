<?php
/**
 * Registro del Custom Post Type para las galerías.
 *
 * @package GaleriasDomi\Post_Types
 * @since   1.0.0
 */

namespace GaleriasDomi\Post_Types;

// Prevenir acceso directo.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clase Gallery_Post_Type.
 *
 * Registra y configura el CPT 'galerias-domi'.
 *
 * @since 1.0.0
 */
class Gallery_Post_Type {

	/**
	 * Nombre del post type.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const POST_TYPE = 'galerias-domi';

	/**
	 * Registra los hooks necesarios.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	/**
	 * Registra el Custom Post Type.
	 *
	 * @since 1.0.0
	 */
	public function register_post_type(): void {
		$labels = array(
			'name'               => __( 'Galerias DOMI', 'galerias-domi' ),
			'singular_name'      => __( 'Galeria DOMI', 'galerias-domi' ),
			'add_new'            => __( 'Agregar Galeria DOMI', 'galerias-domi' ),
			'add_new_item'       => __( 'Agregar nueva Galeria DOMI', 'galerias-domi' ),
			'edit_item'          => __( 'Editar Galeria DOMI', 'galerias-domi' ),
			'new_item'           => __( 'Nueva Galeria DOMI', 'galerias-domi' ),
			'view_item'          => __( 'Ver Galeria DOMI', 'galerias-domi' ),
			'search_items'       => __( 'Buscar Galerias DOMI', 'galerias-domi' ),
			'not_found'          => __( 'No se encontraron galerías.', 'galerias-domi' ),
			'not_found_in_trash' => __( 'No hay galerías en la papelera.', 'galerias-domi' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_rest'        => false,
			'query_var'           => false,
			'rewrite'             => false,
			'capability_type'     => 'post',
			'has_archive'         => false,
			'hierarchical'        => false,
			'supports'            => array( 'title', 'author' ),
		);

		register_post_type( self::POST_TYPE, $args );
	}
}
