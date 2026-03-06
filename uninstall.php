<?php
/**
 * Se ejecuta cuando el usuario elimina el plugin desde el panel de WordPress.
 *
 * @package GaleriasDomi
 * @since   1.0.0
 */

// Bloquear acceso directo; solo WordPress puede invocar este archivo.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Eliminar opciones guardadas por el plugin.
delete_option( 'galerias_domi_version' );
delete_option( 'galerias_domi_settings' );

// Si es multisite, limpiar también en cada sitio de la red.
if ( is_multisite() ) {
	$sites = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $sites as $site_id ) {
		switch_to_blog( $site_id );
		delete_option( 'galerias_domi_version' );
		delete_option( 'galerias_domi_settings' );
		restore_current_blog();
	}
}
