<?php
/**
 * @package   Automatic Safe Update
 * @author    ABCdatos
 * @license   GPLv2
 * @link      https://taller.abcdatos.net/plugin-actualizar-wordpress/
 */

defined( 'ABSPATH' ) || die( 'No se permite el acceso.' );

function asu_destinatario_email() {
	$destinatario = get_option( 'asu_admin_email' );
	if ( ! is_email( $destinatario ) ) {
		$destinatario = get_option( 'admin_email' );
	}
	return $destinatario;
}

/*
 Versión 1.1.0, con problemas con los semáforos en algunos sitios.
function asu_envia_email_actualizaciones( $transitorio = '' ) {
	// Envía mail con las actualizaciones aplicadas y pendientes si hay alguno nuevo de lo que informar.

	asu_anota_log( __FUNCTION__ . ' Inicio de función' );

	// Utiliza un semáforo para evitar múltiples ejecuciones simultáneas que repiten mails, bien con contenidos completos o parciales.
	// No esperar requiere PHP 5.6.1.
	$adquirido_semaforo = false;
	// Observar en https://www.microteching.com/php/concurrencia-bloqueo-de-codigo-y-semaforos-en-php cómo verifica su existencia.
	// $clave_semaforo = hexdec ( '6173755f' ); # asu_ ascii.
	$proyecto = 'a'; // Project identifier. This must be a one character string.

	// Asegura la existencia de las funciones de semáforo.
	if ( ! function_exists( 'sem_get' ) ) {
		function sem_get( $key ) {
			return fopen( __FILE__ . '.sem.' . $key, 'w+' );
		}
		function sem_acquire( $sem_id ) {
			return flock( $sem_id, LOCK_EX );
		}
		function sem_release( $sem_id ) {
			return flock( $sem_id, LOCK_UN );
		}
	}

	// También requiere ftok, no disponible en Windows.
	if ( ! function_exists( 'ftok' ) ) {
		function ftok( $filename = '', $proj = '' ) {
			if ( file_exists( $filename ) ) {
				$filename          = $filename . (string) $proj;
				$longitud_filename = strlen( $filename );
				for ( $key = array(); sizeof( $key ) < $longitud_filename; $key[] = ord( substr( $filename, sizeof( $key ), 1 ) ) );
				return dechex( array_sum( $key ) );
			} else {
				return -1;
			}
		}
	}

	$clave_semaforo = ftok( __FILE__, $proyecto );

	try {
		$semaforo = sem_get( $clave_semaforo, 1, 0600, 1 );
	} catch ( Exception $e ) {
		// Anota el error.
		asu_anota_log( __FUNCTION__ . ' Error al obtener el semáforo: ' . $e->getMessage() );
		if ( function_exists( 'posix_getpwuid' ) ) {
			$user_info = posix_getpwuid( posix_geteuid() );
			asu_anota_log( __FUNCTION__ . ' Usuario de sistema: ' . $user_info['name'] );
		} else {
			asu_anota_log( __FUNCTION__ . ' No están dsponibles las funciones POSIX para indicar el usuario' );
		}
	}

	if ( $semaforo ) {
		if ( version_compare( PHP_VERSION, '5.6.1' ) >= 0 ) {
			// Por esta vía, o consigue el semáforo o se salta todo el proceso.
			$adquirido_semaforo = sem_acquire( $semaforo, true );
		} else {
			// Espera hasta lograr el semáforo.
			sem_acquire( $semaforo );
			$adquirido_semaforo = true;
		}

		if ( $adquirido_semaforo ) {
			// Sigue con normalidad el proceso.

			// The cron lock: a unix timestamp from when the cron was spawned.
			// No ponerlo
			//$doing_cron_transient = get_transient( 'doing_cron' );
			// Use global $doing_wp_cron lock otherwise use the GET lock. If no lock, trying grabbing a new lock.
			//if ( empty( $doing_wp_cron ) ) {
			//	if ( empty( $_GET[ 'doing_wp_cron' ] ) ) {
			//
			//	}
			//}

			asu_anota_log( __FUNCTION__ . ' empty( $doing_wp_cron ): ' . empty( $doing_wp_cron ) );
			asu_anota_log( __FUNCTION__ . ' empty( $_GET[ \'doing_wp_cron\' ] ): ' . empty( $_GET['doing_wp_cron'] ) );
			if ( array_key_exists( 'REQUEST_URI', $_SERVER ) ) {
				asu_anota_log( __FUNCTION__ . ' $_SERVER[\'REQUEST_URI\']: ' . $_SERVER['REQUEST_URI'] );
			}
			if ( array_key_exists( 'QUERY_STRING', $_SERVER ) ) {
				asu_anota_log( __FUNCTION__ . ' $_SERVER[\'QUERY_STRING\']: ' . $_SERVER['QUERY_STRING'] );
			}

			$actualizaciones_aplicadas  = get_option( 'asu_actualizaciones_aplicadas' ); // Actualizaciones aplicadas automáticamente.
			$actualizaciones_manuales   = get_option( 'asu_actualizaciones_manuales' ); // Actualizaciones que se ha rechazado aplicar automáticamente.
			$actualizaciones_informadas = get_option( 'asu_actualizaciones_informadas' ); // Actualizaciones de las que se ha informado al usuario.

			$actualizaciones_fallidas = asu_extrae_no_aplicadas( $actualizaciones_aplicadas ); // Actualizaciones que fallaron al intentar aplicar automáticamente.

			// Limpia las listas, especialmente quitando líneas vacías que quedasen.
			$actualizaciones_aplicadas  = asu_limpia_lista_compacta( $actualizaciones_aplicadas );
			$actualizaciones_manuales   = asu_limpia_lista_compacta( $actualizaciones_manuales );
			$actualizaciones_informadas = asu_limpia_lista_compacta( $actualizaciones_informadas );
			$actualizaciones_fallidas   = asu_limpia_lista_compacta( $actualizaciones_fallidas );

			// Algunas actualizaciones manuales pueden haber quedado ya aplicadas.
			$actualizaciones_manuales = asu_retira_actualizadas( $actualizaciones_manuales );

			$texto_actualizaciones_aplicadas  = asu_reprocesa_lista_compacta( $actualizaciones_aplicadas, false, true );
			$texto_actualizaciones_manuales   = asu_reprocesa_lista_compacta( $actualizaciones_manuales, false );
			$texto_actualizaciones_informadas = asu_reprocesa_lista_compacta( $actualizaciones_informadas, true );

			$contenido = '';

			// Caso 1: Se aplicaron actualizaciones automáticas, se avisa de todo.
			// Caso 2: Se rechazaron todas las actualizaciones automáticas, si no están todas informadas, se avisa.
			// Caso 3: Se avisa de actualizaciones pendientes no informadas.
			// Caso 4: No hay actualizaciones pendientes de informar.

			// Caso 1:   Se aplicaron actualizaciones automáticas, se avisa de todo.
			if ( $actualizaciones_aplicadas ) {
				asu_anota_log( __FUNCTION__ . ' Preparando e-mail de actualizaciones aplicadas' );
				$asunto     = '[' . get_bloginfo( 'name' ) . '] ' . __( 'Automatic plugin updates', 'automatic-safe-update' ); // Actualización automática de plugins.
				$contenido  = __( 'The following automatic updates have been processed:', 'automatic-safe-update' ) . "\n\n"; // Se ha procesado las siguientes actualizaciones automáticas.
				$contenido .= "$texto_actualizaciones_aplicadas\n";

				if ( $actualizaciones_manuales ) {
					$contenido .= __( 'The following updates remain pending to be applied manually:', 'automatic-safe-update' ) . "\n\n";// Quedan pendientes de aplicar manualmente las siguientes actualizaciones.
					$contenido .= "$texto_actualizaciones_manuales\n";
					$contenido .= __( 'Check version changes and go to', 'automatic-safe-update' ) . ' ' . admin_url( 'plugins.php' ) . ' ' . __( 'for updating', 'automatic-safe-update' ) . ".\n\n";
				}
				$actualizaciones_informadas = $actualizaciones_manuales;

				// Si una actualización aplicada dio Fallo, hay que considerarla informada, igual que las pendientes.
				if ( $actualizaciones_fallidas ) {
					if ( $actualizaciones_informadas ) {
						$actualizaciones_informadas .= "\n$actualizaciones_fallidas";
					} else {
						$actualizaciones_informadas = "$actualizaciones_fallidas";
					}
				}
			} elseif ( $actualizaciones_manuales ) {
				// Caso 2:   Se rechazaron todas las actualizaciones automáticas, si no están todas informadas, se avisa.
				if ( asu_actualizaciones_informar( $actualizaciones_manuales ) ) {
					// Se ha llegado a ejecutar cuando la lista aun no está completa. Problemilla esa race condition 20/01/2019 21:51
					// Quizás sirva de algo https://www.loopeando.com/como-funciona-el-cron-de-wordpress-analizamos-su-codigo-linea-a-linea/ .
					asu_anota_log( __FUNCTION__ . ' Sin actualizaciones aplicadas de las que informar, habrá nuevas actualizaciones pendientes tan solo' );
					$asunto                     = '[' . get_bloginfo( 'name' ) . '] ' . __( 'Pending manual plugin updates', 'automatic-safe-update' ); // Actualizaciones de plugins manuales pendientes.
					$contenido                 .= __( 'The following updates are pending to be applied manually on your site:', 'automatic-safe-update' ) . "\n\n"; // Las siguientes actualizaciones están pendientes de aplicar manualmente en tu sitio.
					$contenido                 .= "$texto_actualizaciones_manuales\n";
					$contenido                 .= __( 'Check version changes and go to', 'automatic-safe-update' ) . ' ' . admin_url( 'plugins.php' ) . ' ' . __( 'for updating', 'automatic-safe-update' ) . ".\n\n";
					$actualizaciones_informadas = $actualizaciones_manuales;
				} else {
					asu_anota_log( __FUNCTION__ . ' Sin actualizaciones aplicadas de las que informar y con las actualizaciones manuales ya informadas previamente' );
				}
			} else {
				$actualizaciones_pendientes = asu_lista_actualizaciones_pendientes( false, $transitorio );
				$actualizaciones_pendientes = asu_limpia_lista_compacta( $actualizaciones_pendientes );
				if ( asu_actualizaciones_informar( $actualizaciones_pendientes ) ) {
					// Caso 3: Se avisa de actualizaciones pendientes no informadas.
					asu_anota_log( __FUNCTION__ . ' actualizaciones_pendientes != $actualizaciones_informadas, se prepara mail' );
					$asunto                           = '[' . get_bloginfo( 'name' ) . '] ' . __( 'Pending plugin updates', 'automatic-safe-update' ); // Actualizaciones de plugins pendientes.
					$cantidad_pendientes              = asu_cantidad_actualizaciones_pendientes( $transitorio );
					$contenido                       .= mensaje_pendientes_actualizar( $cantidad_pendientes );
					$contenido                       .= ' ' . __( 'at', 'automatic-safe-update' ) . ' ' . get_bloginfo( 'name' );
					$contenido                       .= ' ' . get_bloginfo( 'url' ) . "\n\n";
					$texto_actualizaciones_pendientes = asu_reprocesa_lista_compacta( $actualizaciones_pendientes, true );
					$contenido                       .= $texto_actualizaciones_pendientes . "\n";
				} else {
					// Caso 4: No hay actualizaciones pendientes de informar.
					// Se podría aplicar para cuando se llama desde options,php, pero forzar el mail tiene poco sentido.
					asu_anota_log( __FUNCTION__ . ' actualizaciones_pendientes = $actualizaciones_informadas, no se prepara mail' );
				}
				$actualizaciones_informadas = $actualizaciones_pendientes;
			}

				// Envía el mail si hay contenido, lo que significa actualizaciones aplicadas o pendientes de las que no se ha informado.
			if ( $contenido ) {
				$contenido   .= asu_mensaje_informacion_servicios();
				$destinatario = asu_destinatario_email();
				asu_anota_log( __FUNCTION__ . " Enviando e-mail de actualizaciones automáticas a $destinatario" );
				wp_mail( $destinatario, $asunto, $contenido );
				asu_anota_log( __FUNCTION__ . ' Enviado e-mail de actualizaciones automáticas' );
				// Borra lo pendiente de informar y acualiza lo ya informado.
				update_option( 'asu_actualizaciones_aplicadas', '' );
				update_option( 'asu_actualizaciones_manuales', '' );
				update_option( 'asu_actualizaciones_informadas', $actualizaciones_informadas ); // Actualizaciones pendientes ya informadas.
			} else {
				asu_anota_log( __FUNCTION__ . ' No figura nada nuevo a informar...' );
			}
			sem_release( $semaforo );
		} else {
			asu_anota_log( __FUNCTION__ . ' El semáforo impidió el proceso' );
		}
	} else {
		asu_anota_log( __FUNCTION__ . " No se pudo tomar el semáforo $clave_semaforo" );
	}

}
*/

/**
 * Envía un correo electrónico con las actualizaciones aplicadas y pendientes si hay alguna nueva información que informar.
 *
 * Utiliza un bloqueo temporal de WordPress para evitar múltiples ejecuciones simultáneas que repiten correos electrónicos, ya sea con contenidos completos o parciales.
 *
 * @param string $transitorio Transitorio.
 * @throws Exception Si hay un error al obtener el bloqueo.
 */
function asu_envia_email_actualizaciones( $transitorio = '' ) {
	// Envía mail con las actualizaciones aplicadas y pendientes si hay alguno nuevo de lo que informar.

	asu_anota_log( __FUNCTION__ . ' Inicio de función' );

	// Utiliza un bloqueo temporal de WordPress para evitar múltiples ejecuciones simultáneas que repiten mails, bien con contenidos completos o parciales. Existe la posibilidad de race condition, tampoco es grave.
	$lock_key = 'asu_envia_email_actualizaciones_lock';
	$lock     = get_transient( $lock_key );

	// Si no está bloqueado con el lock, sigue.
	if ( ! $lock ) {
		// Establece el lock para 5 minutos.
		set_transient( $lock_key, true, 5 * MINUTE_IN_SECONDS );

		// Sigue con normalidad el proceso.
		asu_anota_log( __FUNCTION__ . ' empty( $doing_wp_cron ): ' . empty( $doing_wp_cron ) );
		asu_anota_log( __FUNCTION__ . ' empty( $_GET[ \'doing_wp_cron\' ] ): ' . empty( $_GET['doing_wp_cron'] ) );
		if ( array_key_exists( 'REQUEST_URI', $_SERVER ) ) {
			asu_anota_log( __FUNCTION__ . ' $_SERVER[\'REQUEST_URI\']: ' . esc_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
		}
		if ( array_key_exists( 'QUERY_STRING', $_SERVER ) ) {
			asu_anota_log( __FUNCTION__ . ' $_SERVER[\'QUERY_STRING\']: ' . sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) ) );
		}

		$actualizaciones_aplicadas  = get_option( 'asu_actualizaciones_aplicadas' ); // Actualizaciones aplicadas automáticamente.
		$actualizaciones_manuales   = get_option( 'asu_actualizaciones_manuales' ); // Actualizaciones que se ha rechazado aplicar automáticamente.
		$actualizaciones_informadas = get_option( 'asu_actualizaciones_informadas' ); // Actualizaciones de las que se ha informado al usuario.

		$actualizaciones_fallidas = asu_extrae_no_aplicadas( $actualizaciones_aplicadas ); // Actualizaciones que fallaron al intentar aplicar automáticamente.

		// Limpia las listas, especialmente quitando líneas vacías que quedasen.
		$actualizaciones_aplicadas  = asu_limpia_lista_compacta( $actualizaciones_aplicadas );
		$actualizaciones_manuales   = asu_limpia_lista_compacta( $actualizaciones_manuales );
		$actualizaciones_informadas = asu_limpia_lista_compacta( $actualizaciones_informadas );
		$actualizaciones_fallidas   = asu_limpia_lista_compacta( $actualizaciones_fallidas );

		// Algunas actualizaciones manuales pueden haber quedado ya aplicadas.
		$actualizaciones_manuales = asu_retira_actualizadas( $actualizaciones_manuales );

		$texto_actualizaciones_aplicadas  = asu_reprocesa_lista_compacta( $actualizaciones_aplicadas, false, true );
		$texto_actualizaciones_manuales   = asu_reprocesa_lista_compacta( $actualizaciones_manuales, false );
		$texto_actualizaciones_informadas = asu_reprocesa_lista_compacta( $actualizaciones_informadas, true );

		$contenido = '';

		// Caso 1: Se aplicaron actualizaciones automáticas, se avisa de todo.
		// Caso 2: Se rechazaron todas las actualizaciones automáticas, si no están todas informadas, se avisa.
		// Caso 3: Se avisa de actualizaciones pendientes no informadas.
		// Caso 4: No hay actualizaciones pendientes de informar.

		// Caso 1:   Se aplicaron actualizaciones automáticas, se avisa de todo.
		if ( $actualizaciones_aplicadas ) {
			asu_anota_log( __FUNCTION__ . ' Preparando e-mail de actualizaciones aplicadas' );
			$asunto     = '[' . get_bloginfo( 'name' ) . '] ' . __( 'Automatic plugin updates', 'automatic-safe-update' ); // Actualización automática de plugins.
			$contenido  = __( 'The following automatic updates have been processed:', 'automatic-safe-update' ) . "\n\n"; // Se ha procesado las siguientes actualizaciones automáticas.
			$contenido .= "$texto_actualizaciones_aplicadas\n";

			if ( $actualizaciones_manuales ) {
				$contenido .= __( 'The following updates remain pending to be applied manually:', 'automatic-safe-update' ) . "\n\n";// Quedan pendientes de aplicar manualmente las siguientes actualizaciones.
				$contenido .= "$texto_actualizaciones_manuales\n";
				$contenido .= __( 'Check version changes and go to', 'automatic-safe-update' ) . ' ' . admin_url( 'plugins.php' ) . ' ' . __( 'for updating', 'automatic-safe-update' ) . ".\n\n";
			}
			$actualizaciones_informadas = $actualizaciones_manuales;

			// Si una actualización aplicada dio Fallo, hay que considerarla informada, igual que las pendientes.
			if ( $actualizaciones_fallidas ) {
				if ( $actualizaciones_informadas ) {
					$actualizaciones_informadas .= "\n$actualizaciones_fallidas";
				} else {
					$actualizaciones_informadas = "$actualizaciones_fallidas";
				}
			}
		} elseif ( $actualizaciones_manuales ) {
			// Caso 2:   Se rechazaron todas las actualizaciones automáticas, si no están todas informadas, se avisa.
			if ( asu_actualizaciones_informar( $actualizaciones_manuales ) ) {
				// Se ha llegado a ejecutar cuando la lista aun no está completa. Problemilla esa race condition 20/01/2019 21:51
				// Quizás sirva de algo https://www.loopeando.com/como-funciona-el-cron-de-wordpress-analizamos-su-codigo-linea-a-linea/ .
				asu_anota_log( __FUNCTION__ . ' Sin actualizaciones aplicadas de las que informar, habrá nuevas actualizaciones pendientes tan solo' );
				$asunto                     = '[' . get_bloginfo( 'name' ) . '] ' . __( 'Pending manual plugin updates', 'automatic-safe-update' ); // Actualizaciones de plugins manuales pendientes.
				$contenido                 .= __( 'The following updates are pending to be applied manually on your site:', 'automatic-safe-update' ) . "\n\n"; // Las siguientes actualizaciones están pendientes de aplicar manualmente en tu sitio.
				$contenido                 .= "$texto_actualizaciones_manuales\n";
				$contenido                 .= __( 'Check version changes and go to', 'automatic-safe-update' ) . ' ' . admin_url( 'plugins.php' ) . ' ' . __( 'for updating', 'automatic-safe-update' ) . ".\n\n";
				$actualizaciones_informadas = $actualizaciones_manuales;
			} else {
				asu_anota_log( __FUNCTION__ . ' Sin actualizaciones aplicadas de las que informar y con las actualizaciones manuales ya informadas previamente' );
			}
		} else {
			$actualizaciones_pendientes = asu_lista_actualizaciones_pendientes( false, $transitorio );
			$actualizaciones_pendientes = asu_limpia_lista_compacta( $actualizaciones_pendientes );
			if ( asu_actualizaciones_informar( $actualizaciones_pendientes ) ) {
				// Caso 3: Se avisa de actualizaciones pendientes no informadas.
				asu_anota_log( __FUNCTION__ . ' actualizaciones_pendientes != $actualizaciones_informadas, se prepara mail' );
				$asunto                           = '[' . get_bloginfo( 'name' ) . '] ' . __( 'Pending plugin updates', 'automatic-safe-update' ); // Actualizaciones de plugins pendientes.
				$cantidad_pendientes              = asu_cantidad_actualizaciones_pendientes( $transitorio );
				$contenido                       .= mensaje_pendientes_actualizar( $cantidad_pendientes );
				$contenido                       .= ' ' . __( 'at', 'automatic-safe-update' ) . ' ' . get_bloginfo( 'name' );
				$contenido                       .= ' ' . get_bloginfo( 'url' ) . "\n\n";
				$texto_actualizaciones_pendientes = asu_reprocesa_lista_compacta( $actualizaciones_pendientes, true );
				$contenido                       .= $texto_actualizaciones_pendientes . "\n";
			} else {
				// Caso 4: No hay actualizaciones pendientes de informar.
				// Se podría aplicar para cuando se llama desde options,php, pero forzar el mail tiene poco sentido.
				asu_anota_log( __FUNCTION__ . ' actualizaciones_pendientes = $actualizaciones_informadas, no se prepara mail' );
			}
			$actualizaciones_informadas = $actualizaciones_pendientes;
		}

			// Envía el mail si hay contenido, lo que significa actualizaciones aplicadas o pendientes de las que no se ha informado.
		if ( $contenido ) {
			$contenido   .= asu_mensaje_informacion_servicios();
			$destinatario = asu_destinatario_email();
			asu_anota_log( __FUNCTION__ . " Enviando e-mail de actualizaciones automáticas a $destinatario" );
			wp_mail( $destinatario, $asunto, $contenido );
			asu_anota_log( __FUNCTION__ . ' Enviado e-mail de actualizaciones automáticas' );
			// Borra lo pendiente de informar y acualiza lo ya informado.
			update_option( 'asu_actualizaciones_aplicadas', '' );
			update_option( 'asu_actualizaciones_manuales', '' );
			update_option( 'asu_actualizaciones_informadas', $actualizaciones_informadas ); // Actualizaciones pendientes ya informadas.
		} else {
			asu_anota_log( __FUNCTION__ . ' No figura nada nuevo a informar...' );
		}

		// Libera el bloqueo al final de la ejecución de la función.
		delete_transient( $lock_key );
	} else {
		// Si no se pudo adquirir el lock, termina la ejecución.
		asu_anota_log( __FUNCTION__ . ' El bloqueo impidió el proceso' );
	}

}

function asu_mensaje_actualizaciones_pendientes( $updates, $solo_manual = false ) {
	$mensaje = '';
	asu_anota_log( __FUNCTION__ . ' Inicio de función' );
	// Muestra el estado de las actualizaciones pendientes.
	if ( ! $updates ) {
		asu_anota_log( __FUNCTION__ . ' No se ha recibido constancia de actualizaciones' );
	} else {
		foreach ( (array) $updates->response as $plugin_file => $plugin_data ) {
			$actualizar = asu_corresponde_actualizar_plugin( $plugin_data->slug, $plugin_data->new_version );
			if ( ! ( $solo_manual && $actualizar ) ) {

				$actualizacion                  = new asu_Actualizacion();
				$actualizacion->slug            = $plugin_data->slug;
				$actualizacion->version_actual  = asu_plugin_version( $plugin_data->slug );
				$actualizacion->version_nueva   = $plugin_data->new_version;
				$actualizacion->modo_automatico = $actualizar;

				asu_anota_log( __FUNCTION__ . ' ' . $actualizacion->linea_nueva() );
				$mensaje .= $actualizacion->linea_nueva() . "\n";
			}
		}
	}
	return $mensaje;
}

function asu_lista_actualizaciones_pendientes( $solo_manual = false, $transitorio = '' ) {
	asu_anota_log( __FUNCTION__ . ' Inicio de función' );
	$mensaje = '';
	// if ( $plugins = current_user_can( 'update_plugins' ) ) {
	if ( $transitorio ) {
		$update_plugins = $transitorio;
	} else {
		$update_plugins = get_site_transient( 'update_plugins' );
	}

		// Al actualzar manualmente con WP-CLI RGPD un plugin se obtiene un transitorio vacío, en ese caso tomo la opción que se almacenó.
	if ( empty( $update_plugins->response ) ) {
		asu_anota_log( __FUNCTION__ . ' Transitorio nulo, tomamos el último almacenado' );
		$update_plugins = json_decode( get_option( 'asu_json' ) ); // Así evitamos trabajar con una lista vacía.
	}

	if ( ! empty( $update_plugins->response ) ) {
		foreach ( $update_plugins->response as $key => $plugin_data ) { // Loop through the plugins that need updating.
			$requiere_actualizar = asu_requiere_actualizar_plugin( $plugin_data->slug, $plugin_data->new_version );
			if ( $requiere_actualizar ) {
				$actualizar = asu_corresponde_actualizar_plugin( $plugin_data->slug, $plugin_data->new_version );
				if ( ! ( $solo_manual && $actualizar ) ) {
					$actualizacion                  = new asu_Actualizacion();
					$actualizacion->slug            = $plugin_data->slug;
					$actualizacion->version_actual  = asu_plugin_version( $plugin_data->slug );
					$actualizacion->version_nueva   = $plugin_data->new_version;
					$actualizacion->modo_automatico = $actualizar;
					$mensaje                       .= $actualizacion->linea_compacta() . "\n";
					asu_anota_log( __FUNCTION__ . ' ' . $actualizacion->linea_compacta() );
				}
			}
		}
	}
	// }
	return $mensaje;
}

/**
 * Calcula la cantidad de actualizaciones de plugins pendientes.
 *
 * Esta función obtiene una lista de plugins que necesitan ser actualizados,
 * ya sea desde una transición del sitio o desde una opción en la base de datos.
 * Luego, para cada plugin que necesita ser actualizado, verifica si realmente
 * necesita ser actualizado y, si es así, incrementa un contador.
 *
 * @param mixed $transitorio Transición del sitio que contiene la información de actualización del plugin.
 *                           Si no se proporciona, se obtiene de la base de datos.
 *
 * @return int Cantidad de actualizaciones de plugins pendientes.
 */
function asu_cantidad_actualizaciones_pendientes( $transitorio = '' ) {
	asu_anota_log( __FUNCTION__ . ' Inicio de función' );
	// echo 'Cantidad de plugins a actualizar: ';
	// if ( $plugins = current_user_can( 'update_plugins' ) ) {
	if ( $transitorio ) {
		$update_plugins = $transitorio;
	} else {
		$update_plugins = get_site_transient( 'update_plugins' );
	}
	if ( empty( $update_plugins->response ) ) {
		asu_anota_log( __FUNCTION__ . ' empty ( $update_plugins->response )' );
		$update_plugins = json_decode( get_option( 'asu_json' ) ); // Así evitamos trabajar con una lista vacía.
	}
	$cantidad = 0;
	if ( ! empty( $update_plugins->response ) ) {
		// No sé por qué con count falla "Warning: count(): Parameter must be an array or an object that implements Countable"
		// $cantidad = count ( $update_plugins->response );
		foreach ( $update_plugins->response as $key => $plugin_data ) {
			$requiere_actualizar = asu_requiere_actualizar_plugin( $plugin_data->slug, $plugin_data->new_version );
			if ( $requiere_actualizar ) {
				$cantidad++;
			}
		}
	}
	// }
	return $cantidad;
}

function asu_mensaje_informacion_servicios() {
	$mensaje  = '';
	$mensaje .= __( 'If you experience any issues or need support, the volunteers in the WordPress.org support forums may be able to help.', 'automatic-safe-update' ) . "\n";
	$mensaje .= __( 'https://wordpress.org/support/' ) . "\n\n";
	// Oferta de servicios profesionales (de pago) limitada a ciertos idiomas.
	$locale = get_locale();
	$locale = substr( $locale, 0, 2 );
	if ( 'es' === $locale ) {
		$mensaje .= 'Si necesitas contratar un servicio de soporte profesional o urgente puedes contactar con Taller ABCdatos.' . "\n";
		$mensaje .= 'https://taller.abcdatos.net/contacto/' . "\n\n";
	} elseif ( 'ca' === $locale ) {
		$mensaje .= 'Si necessites contractar un servei de suport professional o urgent pots contactar amb Taller ABCdatos.' . "\n";
		$mensaje .= 'https://taller.abcdatos.net/contacto/' . "\n\n";
	}
	return $mensaje;
}
