<?php
/**
 * Plugin Name: Automatic Safe Update
 * Plugin URI:  https://taller.abcdatos.net/plugin-actualizar-wordpress/
 * Description: Update plugins and themes in a smart safe way.
 * Version:     1.1.12
 * Author:      ABCdatos
 * Author URI:  https://taller.abcdatos.net/
 * License:     GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: automatic-safe-update
 * Domain Path: /languages
 *
 * @package automatic-safe-update
 */

defined( 'ABSPATH' ) || die( 'No se permite el acceso.' );

add_action( 'plugins_loaded', 'asu_load_plugin_textdomain' );
/** Requerido o se obtiene error Plugin is not compatible with language packs: Missing load_plugin_textdomain(). en el canal de Slack #meta-language-packs. */
function asu_load_plugin_textdomain() {
	load_plugin_textdomain( 'automatic-safe-update', false, basename( __DIR__ ) . '/languages' );
}

require_once plugin_dir_path( __FILE__ ) . 'includes/functions.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/configuracion.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/mail.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-asu-actualizacion.php';
// Lista de variables usadas en tabla options.
require_once plugin_dir_path( __FILE__ ) . 'lista-opciones.php';
// Administration features (settings).
if ( is_admin() ) {
	if ( ! asu_conf_lock_settings() ) {
		include_once 'admin/options.php';
	}
}

add_filter( 'auto_update_plugin', 'asu_auto_update_plugin_filtro', 10, 2 );
/**
 * Decide si aplicar una actualización a un plugin específico.
 *
 * @param bool  $update Determina si se realizará la actualización automática.
 * @param mixed $item   Los datos del plugin que se está actualizando.
 *
 * @return bool Determina si se realizará la actualización automática.
 */
function asu_auto_update_plugin_filtro( $update, $item ) {
	if ( ! property_exists( $item, 'slug' ) ) {
		// Existe alguna condición en que no hay acceso a la propiedad slug "PHP Warning:  Undefined property: stdClass::$slug ".
		asu_anota_log( __FUNCTION__ . ' Consulta para actualizar plugin sin acceso a su slug' );
		$actualizar = false;
	} elseif ( 'automatic-safe-update' === $item->slug ) {
		// El propio plugin siempre se acepta actualizar, por si algún fallo en el código bloquease actualizaciones como en la v1.1.9 y llega hasta aquí.
		asu_anota_log( __FUNCTION__ . ' Autorización de actualizacion del propio plugin ' . $item->slug );
		$actualizar = true;
	} elseif ( '' === $item->slug ) {
		// Cuando un plugin no tiene un slug oficial asignado, como los que estoy desarrollando, llega sin slug.
		asu_anota_log( __FUNCTION__ . ' Consulta para actualizar plugin sin slug en path: ' . $item->plugin );
		$actualizar = false;
	} elseif ( 'a-fake-plugin' === $item->slug ) {
		// El plugin de control de Site Health se autoriza a actualizar, sin mayor proceso.
		asu_anota_log( __FUNCTION__ . ' Comprobación de actualizacion de plugins desde Site Health con el falso ' . $item->slug );
		$actualizar = true;
	} else {
		asu_anota_log( __FUNCTION__ . ' Consulta de autoactualizacion de plugin ' . $item->slug );
		$version_instalada = asu_plugin_version( $item->slug );
		asu_anota_log( __FUNCTION__ . " -Versión instalada: $version_instalada" );
		// 10/04/2023 Evita warnings en ocasiones
		if ( isset( $item->new_version ) ) {
			asu_anota_log( __FUNCTION__ . ' -Versión nueva....: ' . $item->new_version );
			// Hay que descartar cuando la versión no es un aumento, sucede el comparar versiones iguales, sorprendente.
			$requiere_actualizar = asu_requiere_actualizar_plugin( $item->slug, $item->new_version );
			if ( $requiere_actualizar ) {
				$actualizar = asu_corresponde_actualizar_plugin( $item->slug, $item->new_version );
				if ( $actualizar ) {
					asu_anota_log( __FUNCTION__ . ' Autorizada actualización de plugin: ' . $item->slug . " $version_instalada a " . $item->new_version );
					$mensaje_actualizaciones_aplicadas = get_option( 'asu_actualizaciones_aplicadas' );
					$nueva_actualizacion               = asu_texto_actualizacion( $item->slug, $version_instalada, $item->new_version, true );
					// Cualquien actualización aplicada se agrega a la lista, no importan las repeticiones que eventualmente surgieran.
					$mensaje_actualizaciones_aplicadas .= $nueva_actualizacion . "\n";
					update_option( 'asu_actualizaciones_aplicadas', $mensaje_actualizaciones_aplicadas );
					asu_retira_manual( $item->slug );  // Lo retira de la lista de actualizaciones manuales por haberlo actualizado.
				} else {
					asu_anota_log( __FUNCTION__ . ' Denegada actualización de plugin: ' . $item->slug . " $version_instalada a " . $item->new_version );
					$mensaje_actualizaciones_denegadas = get_option( 'asu_actualizaciones_manuales' );
					// Agregar o actualizar en la lista.
					$nueva_actualizacion               = asu_texto_actualizacion( $item->slug, $version_instalada, $item->new_version, false );
					$mensaje_actualizaciones_denegadas = asu_actualiza_lista( $mensaje_actualizaciones_denegadas, $nueva_actualizacion );
					update_option( 'asu_actualizaciones_manuales', $mensaje_actualizaciones_denegadas );
				}
			} else {
				asu_anota_log( __FUNCTION__ . ' No requiere actualización el plugin: ' . $item->slug . " de $version_instalada a " . $item->new_version );
				$actualizar = false;
			}
		} else {
			asu_anota_log( __FUNCTION__ . ' No consta ahora la nueva versión del plugin: ' . $item->slug . ", se mantiene la versión $version_instalada" );
			$actualizar = false;
		}
	}
	return $actualizar;
}

add_filter( 'pre_set_site_transient_update_plugins', 'asu_filter_set_transient_update_plugins' );
/**
 * Este es llamado cada vez que se recupera el transient. Momento ideal para obtener la foto del sistema y advertir al usuario si hay novedades.
 *
 * @param mixed $transient El valor del transient antes de ser configurado.
 *
 * @return mixed El valor modificado del transient.
 */
function asu_filter_set_transient_update_plugins( $transient ) {
	// De una copia del transitorio, eliminamos la marca de tiempo de última comprobación para comparar solo contenido.
	$muestra = $transient;
	unset( $muestra->{'last_checked'} );

	// Compararemos un hash MD5 para saber si hay novedades.
	// Para obtener los MD5, lo más rápido en lugar de serialize es JSON encode.
	$muestra_json_actual = wp_json_encode( $muestra );
	$hash_md5_actual     = md5( $muestra_json_actual );

	$muestra_json_anterior = get_option( 'asu_json' );
	$hash_md5_anterior     = get_option( 'asu_hash' );

	$actualizaciones_aplicadas  = get_option( 'asu_actualizaciones_aplicadas' ); // Actualizaciones aplicadas automáticamente no informadas todavía.
	$actualizaciones_manuales   = get_option( 'asu_actualizaciones_manuales' ); // Actualizaciones que se rechazó aplicar automáticamente.
	$actualizaciones_informadas = get_option( 'asu_actualizaciones_informadas' ); // Actualizaciones previamente pendientes de aplicar ya informadas.
	// $actualizaciones_manuales_informadas = get_option ( 'asu_manual_informada'           ); // Actualizaciones manuales previamente pendientes de aplicar ya informadas.

	// Eliminar opciones erróneas y obsoletas (temporal).
	delete_site_option( 'asu_actualizaciones_rechazas' );
	delete_site_option( 'asu_actualizaciones_rechazas_anterior' );
	delete_site_option( 'asu_actualizaciones_rechazadas' );
	delete_site_option( 'asu_actualizaciones_rechazadas_anterior' );
	delete_site_option( 'asu_manual_pendiente' );
	delete_site_option( 'asu_hash_anterior' );
	delete_site_option( 'asu_json_anterior' );
	delete_site_option( 'asu_actualizaciones_aplicadas_anterior' );
	delete_site_option( 'actualizaciones_aplicadas_anterior' );
	delete_site_option( 'asu_actualizaciones_denegadas' );
	delete_site_option( 'asu_manual_informada' );
	delete_site_option( 'asu_admin_hide_menu' );

	if ( ! $hash_md5_anterior ) {
		// Primera ejecución.
		asu_anota_log( __FUNCTION__ . ' Primera comprobación, solo se almacenan datos' );
		update_option( 'asu_hash', $hash_md5_actual );
		update_option( 'asu_json', $muestra_json_actual );
		update_option( 'asu_actualizaciones_informadas', '' ); // Inicialmente no se informó de nada.
	} elseif ( asu_json_transitorio_vacio( $muestra_json_actual ) ) {
		asu_anota_log( __FUNCTION__ . ' Descartada comprobación de novedades con valores vacíos' );
	} elseif ( $hash_md5_anterior && $hash_md5_anterior !== $hash_md5_actual ) {
		// Hay novedades en la lista de pendientes.

		// //////////////////// Contar si hay más pendientes de actualizar automáticos o solo ese además de la fecha/hora, quizás eso permita decidir si enviar o no el mail, porque cada vez que se actualiza o no, salta.
		// También ver si es ejecución desde cron puede tener información
		// asu_anota_log( "Nuevo hash $hash_md5_actual, longitud json: " . strlen ( $muestra_json_actual ) . ", corresponde enviar mail" );
		// if ( asu_json_transitorio_vacio ( $muestra_json_actual ) ) {
		// asu_anota_log ( __FUNCTION__ . ' Descartada comprobación de novedades con valores vacíos BIS' . "\n" . $muestra_json_actual );
		// }
		// asu_anota_log ( __FUNCTION__ . ' Previo a comprobar asu_mensaje_actualizaciones_pendientes con:' . "\n" . $muestra_json_actual );

		// Se decide si hay que enviar e-mail.
		if ( $actualizaciones_aplicadas ) {
			// A partr de la versión 5.5, el propio WordPress advierte de los intentos de actualización realizados.
			// Los desactivo por el filtro auto_plugin_update_send_email pues considera un error los plugins que se ha configurado que no se actualicen.
			// Si hay automáticas por informar, según los logs parece el momento adecuado de de hacerlo.
			asu_anota_log( __FUNCTION__ . ' Hay actualizaciones en la lista de aplicadas, se enviará mail' );
			asu_envia_email_actualizaciones();
		} elseif ( $actualizaciones_manuales ) {
			// La comprobación de si es un aviso duplicado ya se hará en la siguiente función.
			asu_anota_log( __FUNCTION__ . ' Hay novedades en la lista de actualizaciones manuales pendientes, se enviará mail' );
			asu_envia_email_actualizaciones();
		} elseif ( asu_actualizaciones_informar( asu_mensaje_actualizaciones_pendientes( $muestra, false ) ) ) {
			// Las automáticas pendientes también se consideran. ////////////////// Podría ser opcional, pero eso aumenta el riesgo de que una fall y no nos enteremos..

			// Está opción tal vez la anule.
			// Puede suceder cuando instalan manualmente una actualización, ahí no corresponde avisar.
			// Hay que hacer una función que indique si una actualización fue advertida o no y recorrer las pendientes una a una para ver si todas están avisadas.

			// Estamos tratando de enviar el mail probablemente sin tenertoda la info,omitiendo la última actualización agregada al sistema.
			// Quizás enviando el $transient ($muestra) lo resuelva.
			asu_anota_log( __FUNCTION__ . ' Hay novedades en la lista de actualizaciones pendientes se envía mail utilizando el transitorio como fuente de información' );
			asu_envia_email_actualizaciones( $muestra );
		} else {
			// Esto es lo que se ejecuta tras el filtrado de todos los plugins pendientes de actualizar por asu_auto_update_plugin_filter.
			asu_anota_log( __FUNCTION__ . ' No hay novedades en las listas de actualizaciones aplicada ni manuales, ¿debe haber una automática nueva pendiente? Se enviará mail. Si es correcto, se integra en la condición anterior todo el else' );

			asu_envia_email_actualizaciones();
		}

		// Hace la rotación de respuestas, evitando así además entrar en un bucle sin fin.
		update_option( 'asu_hash', $hash_md5_actual );
		update_option( 'asu_json', $muestra_json_actual );
	} else {
		asu_anota_log( __FUNCTION__ . ' No hay nuevas actualizaciones pendientes' );
	}
	return $transient;
}

// Evita el mail post-actualizaciones al considerar fallos las que se ha configurado que no se actualicen.
add_filter( 'auto_plugin_update_send_email', '__return_false' );

add_filter( 'auto_update_translation', 'asu_auto_update_traduccion_filtro', 10, 2 );
/**
 * Gestión de las actualizaciones de traducciones.
 *
 * @param bool  $update Determina si se realizará la actualización automática de la traducción.
 * @param mixed $item   Los datos de la traducción que se está actualizando.
 *
 * @return bool Determina si se realizará la actualización automática de la traducción.
 */
function asu_auto_update_traduccion_filtro( $update, $item ) {
	$actualizar = get_option( 'asu_actualizacion_traducciones', $update );
	return $actualizar;
}

add_filter( 'auto_update_theme', 'asu_auto_update_tema_filtro', 10, 2 );
/**
 * Gestión de las actualizaciones de temas.
 *
 * @param bool  $update Determina si se realizará la actualización automática del tema.
 * @param mixed $item   Los datos del tema que se está actualizando.
 *
 * @return bool Determina si se realizará la actualización automática del tema.
 */
function asu_auto_update_tema_filtro( $update, $item ) {
	$actualizar = get_option( 'asu_actualizacion_temas', $update );
	return $actualizar;
}

/**
 * Función genérica para decidir si actualizar un elemento.
 *
 * @param string $type   El tipo de elemento que se está actualizando (puede ser 'plugin', 'theme', 'translation').
 * @param bool   $update Determina si se realizará la actualización automática.
 * @param mixed  $item   Los datos del elemento que se está actualizando.
 *
 * @return bool Determina si se realizará la actualización automática.
 */
function asu_auto_update_filtro( $type, $update, $item ) {
	// Decide si aplicar una actualización.
	asu_anota_log( __FUNCTION__ . " Consulta de autoactualizacion de $type " . $item->slug );
	$version_instalada = call_user_func( "asu_{$type}_version", $item->slug );
	asu_anota_log( __FUNCTION__ . " -Versión instalada: $version_instalada" );
	asu_anota_log( __FUNCTION__ . ' -Versión nueva....: ' . $item->new_version );
	// Hay que descartar cuando la versión no es un aumento, sucede el comparar versiones iguales, sorprendente.
	$requiere_actualizar = call_user_func( "asu_requiere_actualizar_$type", $item->slug, $item->new_version );
	if ( $requiere_actualizar ) {
		$actualizar = call_user_func( "asu_corresponde_actualizar_$type", $item->slug, $item->new_version );
		if ( $actualizar ) {
			asu_anota_log( __FUNCTION__ . " Autorizada actualización de $type: " . $item->slug . " $version_instalada a " . $item->new_version );
			$mensaje_actualizaciones_aplicadas = get_option( 'asu_actualizaciones_aplicadas' );
			$nueva_actualizacion               = asu_texto_actualizacion( $item->slug, $version_instalada, $item->new_version, true );
			// Cualquien actualización aplicada se agrega a la lista, no importan las repeticiones que eventualmente surgieran.
			$mensaje_actualizaciones_aplicadas .= $nueva_actualizacion . "\n";
			update_option( 'asu_actualizaciones_aplicadas', $mensaje_actualizaciones_aplicadas );
			asu_retira_manual( $item->slug );  // Lo retira de la lista de actualizaciones manuales por haberlo actualizado.
		} else {
			asu_anota_log( __FUNCTION__ . " Denegada actualización de $type: " . $item->slug . " $version_instalada a " . $item->new_version );
			$mensaje_actualizaciones_denegadas = get_option( 'asu_actualizaciones_manuales' );
			// Agregar o actualizar en la lista.
			$nueva_actualizacion               = asu_texto_actualizacion( $item->slug, $version_instalada, $item->new_version, false );
			$mensaje_actualizaciones_denegadas = asu_actualiza_lista( $mensaje_actualizaciones_denegadas, $nueva_actualizacion );
			update_option( 'asu_actualizaciones_manuales', $mensaje_actualizaciones_denegadas );
		}
	} else {
		asu_anota_log( __FUNCTION__ . " No requiere actualizar $type: " . $item->slug . " $version_instalada a " . $item->new_version );
	}
	return $actualizar;
}

add_filter( 'plugin_action_links', 'asu_plugin_action_links', 10, 2 );
/**
 * Enlace a los ajustes en la página de plugins del administrador.
 * Basado en https://www.smashingmagazine.com/2011/03/ten-things-every-wordpress-plugin-developer-should-know/
 *
 * @param array  $links Array de enlaces que se mostrarán junto al plugin.
 * @param string $file  Ruta al archivo del plugin.
 *
 * @return array El array de enlaces modificados.
 */
function asu_plugin_action_links( $links, $file ) {
	static $this_plugin;
	if ( ! $this_plugin ) {
		$this_plugin = plugin_basename( __FILE__ );
	}
	if ( $file === $this_plugin ) {
		if ( asu_conf_lock_settings() ) {
			$settings_link = '<b title="' . __( 'Settings has been locked by administrator.', 'automatic-safe-update' ) . '" style="color:green">' . __( 'Settings locked', 'automatic-safe-update' ) . '</b>';
			array_push( $links, $settings_link );
		} else {
			// The "page" query string value must be equal to the slug of the Settings admin page.
			$settings_link = '<a href="' . get_bloginfo( 'wpurl' ) . '/wp-admin/admin.php?page=automatic-safe-update" title="' . __( 'Plugin settings', 'automatic-safe-update' ) . '">' . __( 'Settings' ) . '</a>';
			array_unshift( $links, $settings_link );
		}
	}
	return $links;
}

/** Plugin version for options page header. */
function asu_get_version() {
	// En otro directorio la ruta obtenida no es la adecuada y no hay resultado.
	$plugin_data    = get_plugin_data( __FILE__ );
	$plugin_version = $plugin_data['Version'];
	return $plugin_version;
}
