<?php
/**
 * Se ejecuta cuando el usuario elimina el plugin desde el panel de WordPress.
 *
 * Limpia todo lo que crea el plugin: opciones, las galerías (CPT) con sus
 * post metas `_gd_*` y los transients de HTML del frontend.
 *
 * @package GaleriasDomi
 * @since   1.0.0
 */

// Bloquear acceso directo; solo WordPress puede invocar este archivo.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Elimina todos los datos del plugin en el sitio actual.
 *
 * El plugin no está cargado durante la desinstalación, así que el tipo de
 * post se referencia por su slug literal ('galerias-domi').
 *
 * @since 1.0.0
 */
function galerias_domi_uninstall_site(): void {
	global $wpdb;

	// Opciones guardadas por el plugin.
	delete_option( 'galerias_domi_version' );
	delete_option( 'galerias_domi_settings' );

	// Galerías (CPT) y sus post metas. wp_delete_post() borra también el meta.
	$gallery_ids = get_posts(
		array(
			'post_type'        => 'galerias-domi',
			'post_status'      => 'any',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'suppress_filters' => true,
		)
	);

	foreach ( $gallery_ids as $gallery_id ) {
		wp_delete_post( (int) $gallery_id, true );
	}

	// Transients del frontend: gd_gallery_html_{id}_{version} y sus timeouts.
	// Patrón estático sin entrada de usuario; no requiere wp_cache (es desinstalación).
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		"DELETE FROM {$wpdb->options}
		 WHERE option_name LIKE '\_transient\_gd\_gallery\_html\_%'
		    OR option_name LIKE '\_transient\_timeout\_gd\_gallery\_html\_%'"
	);
}

// Ejecutar en cada sitio de la red si es multisite; en uno solo si no lo es.
if ( is_multisite() ) {
	$sites = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $sites as $site_id ) {
		switch_to_blog( $site_id );
		galerias_domi_uninstall_site();
		restore_current_blog();
	}
} else {
	galerias_domi_uninstall_site();
}
