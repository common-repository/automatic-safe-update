<?php
/**
 * Lista de opciones guardadas en la base de datos para configuración y desinstalación.
 *
 * @package   Automatic Safe Update
 * @author    ABCdatos
 * @license   GPLv2
 * @link      https://taller.abcdatos.net/plugin-actualizar-wordpress/
 */

defined( 'ABSPATH' ) || die( 'No se permite el acceso.' );

/** Lista de variables usadas en tabla options. */
function asu_lista_opciones() {
	return array(
		'asu_version',
		'asu_admin_email',
		'asu_notificar',
		'asu_admin_menu',
		'asu_json',
		'asu_hash',
		'asu_actualizaciones_aplicadas',
		'asu_actualizaciones_manuales',
		'asu_actualizaciones_informadas',
		'asu_log',
		'asu_lineas_log',
		'asu_actualizacion_omision',
		'asu_plugins',
		'asu_lock_settings',
		'asu_actualizacion_nucleo',
		'asu_actualizacion_temas',
		'asu_actualizacion_traducciones',
	);
}
