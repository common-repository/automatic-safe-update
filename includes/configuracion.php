<?php
/**
 * @package   Automatic Safe Update
 * @author    ABCdatos
 * @license   GPLv2
 * @link      https://taller.abcdatos.net/plugin-actualizar-wordpress/
 */

defined( 'ABSPATH' ) || die( 'No se permite el acceso.' );

// Nivel de actualización [mayor|menor|parche|mantener] (3|2|1|0).
function asu_conf_actualizacion_omision() {
	$nivel = get_option( 'asu_actualizacion_omision', 1 );

	/*
	Valor de transición al cambiar de texto a números pasada la v0.50
	$nivel = asu_nivel_actualizacion_tipo( $nivel );
	update_option( 'asu_actualizacion_omision', $nivel );
	return esc_html( $nivel );
	*/

	return $nivel;
}

function asu_conf_admin_email() {
	return esc_html( get_option( 'asu_admin_email', get_bloginfo( 'admin_email' ) ) );
}

function asu_conf_lineas_log() {
	return esc_html( get_option( 'asu_lineas_log', '100' ) );
}

function asu_conf_lock_settings() {
	return esc_html( get_option( 'asu_lock_settings', '0' ) );
}

function asu_conf_actualizacion_traducciones() {
	return esc_html( get_option( 'asu_actualizacion_traducciones', '0' ) );
}

function asu_conf_actualizacion_temas() {
	return esc_html( get_option( 'asu_actualizacion_temas', '0' ) );
}

/*
Valor de transición al cambiar de texto a números pasada la v0.50
*/
function asu_nivel_actualizacion_tipo( $tipo_actualizacion ) {
	switch ( $tipo_actualizacion ) {
		case 'mayor':
			$nivel = 3;
			break;
		case 'menor':
			$nivel = 2;
			break;
		case 'parche':
			$nivel = 1;
			break;
		case 'mantener':
			$nivel = 0;
			break;
		default:
			$nivel = $tipo_actualizacion;
	}
	return $nivel;
}
