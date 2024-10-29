<?php
/** Funciones comunes varias.
 *
 * @package   Automatic Safe Update
 * @author    ABCdatos
 * @license   GPLv2
 * @link      https://taller.abcdatos.net/plugin-actualizar-wordpress/
 */

defined( 'ABSPATH' ) || die( 'No se permite el acceso.' );

/** Nivel al que está configurado que debe actualizarse un plugin.
 * Según la configuración, el plugin puede ser o no actualizable para cambios de versiones de determinados niveles.
 *
 * @param string $slug Slug del plugin.
 * @return int Nivel al que debe actualizarse el plugin.
 */
function asu_nivel_configurado_plugin( $slug ) {
	// Nivel genérico configurado por omisión.
	$nivel = asu_conf_actualizacion_omision();

	// Posible nivel particular configurado.
	$lista_plugins = asu_plugins_configurados();
	if ( isset( $lista_plugins[ $slug ] ) ) {
		$nivel = $lista_plugins[ $slug ];
	}

	return $nivel;
}

/** Determina si el plugin es más nuevo que el instalado para saber si requiere actualizarse, independientemente de lo configurado.
 *
 * @param string $slug Slug del plugin.
 * @param string $version_nueva Versión nueva pendiente de aplicar.
 * @return boolean true|false segun si es una actualización pendiente.
 */
function asu_requiere_actualizar_plugin( $slug, $version_nueva ) {
	$version_instalada             = asu_plugin_version( $slug );
	$nivel_actualizacion_pendiente = asu_nivel_actualizacion_pendiente( $version_instalada, $version_nueva );
	if ( $nivel_actualizacion_pendiente ) {
		$requiere_actualizar = true;
	} else {
		$requiere_actualizar = false;
	}
	return $requiere_actualizar;
}

/** Determina si el plugin requiere actualizarse conforme a lo configurado.
 *
 * @param string $slug Slug del plugin.
 * @param string $version_nueva Versión nueva pendiente de aplicar.
 * @return boolean true|false según si debe aplicarse la actualización.
 */
function asu_corresponde_actualizar_plugin( $slug, $version_nueva ) {
	asu_anota_log( __FUNCTION__ . " Comprobando el nivel de actualización de $slug a $version_nueva" );
	$version_instalada             = asu_plugin_version( $slug );
	$nivel_actualizacion_pendiente = asu_nivel_actualizacion_pendiente( $version_instalada, $version_nueva );
	$actualizable                  = asu_plugin_actualizable_nivel( $slug, $nivel_actualizacion_pendiente );
	$mensaje                       = __( 'upgradeable', 'automatic-safe-update' ) . " $slug $version_instalada " . __( 'to', 'automatic-safe-update' ) . " $version_nueva";
	if ( ! $actualizable ) {
		$mensaje = "no $mensaje";
	}
	$mensaje = ' ' . ucfirst( $mensaje );
	asu_anota_log( __FUNCTION__ . $mensaje );
	return $actualizable;
}

/** Determina si es aplicable al plugin el nivel de actualización consultado.
 *
 * @param string $slug Slug del plugin.
 * @param int    $nivel_actualizacion .
 * @return boolean true|false según si es deben aplicarse las actualizaciones de ese nivel a ese plugin.
 */
function asu_plugin_actualizable_nivel( $slug, $nivel_actualizacion ) {
	if ( asu_nivel_configurado_plugin( $slug ) >= $nivel_actualizacion ) {
		$actualizable = true;
	} else {
		$actualizable = false;
	}
	return $actualizable;
}

/** Determina la versión actualmente instalada del plugin.
 *
 * @param string $slug Slug del plugin.
 * @return string Versión instalada del plugin.
 */
function asu_plugin_version( $slug ) {
	$local_version = '';

	if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'is_plugin_active' ) ) {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$plugins = get_plugins();
	// Recorre los plugins existentes buscando el solicitado.
	foreach ( $plugins as $plugin_file => $plugin_data ) {
		$slug_leido = asu_element_slug( $plugin_file );
		// En la coincidencia trata la excepción del plugin del core sin directorio propio Hello Dolly.
		if ( $slug_leido === $slug
			|| (
				'hello' === $slug_leido &&
				'hello-dolly' === $slug
			)
		) {
			$local_version = $plugin_data['Version'];
			break;
		}
	}

	if ( ! $local_version ) {
		// Advierte en el log que no se pudo obtener la versión del plugin.
		asu_anota_log( __FUNCTION__ . ' ' . esc_html( __( "Can't get plugin version for" ) ) . " '" . esc_html( $slug ) . "'" );
	}

	return $local_version;
}

function asu_theme_version( $slug ) {
	$type = 'theme';
	return asu_element_version( $type, $slug );
}

function asu_element_version( $type, $slug ) {
	if ( ! function_exists( 'get_' . $type . 's' ) || ! function_exists( 'is_' . $type . '_active' ) ) {
		include_once ABSPATH . 'wp-admin/includes/' . $type . '.php';
	}
	$elements = call_user_func( "get_$types" );
	// Recorre los elementos existentes buscando el solicitado.
	foreach ( $elements as $element_file => $element_data ) {
		$slug_leido = asu_element_slug( $element_file );
		if ( $slug_leido === $slug ) {
			$local_version = $element_data['Version'];
			break;
		}
	}
	return $local_version;
}

/** Indica cuál es el nivel de la actualización pendiente para pasar de la instalada a la nueva.
 * 3: Mayor.
 * 2: Menor.
 * 1: Parche.
 * 0: Ninguna.
 *
 * @param string $instalada La versión actualmente instalada.
 * @param string $nueva La nueva versión pendiente de instalar.
 * @return integer el nivel de actualización a aplicar
 */
function asu_nivel_actualizacion_pendiente( $instalada, $nueva ) {
	if ( asu_version_comparar( asu_version_mayor( $instalada ), asu_version_mayor( $nueva ) ) ) {
		$nivel = 3;
	} elseif ( asu_version_comparar( asu_version_menor( $instalada ), asu_version_menor( $nueva ) ) ) {
		$nivel = 2;
	} elseif ( asu_version_comparar( asu_version_parche( $instalada ), asu_version_parche( $nueva ) ) ) {
			$nivel = 1;
	} else {
			$nivel = 0;
	}
	return $nivel;
}

function asu_version_actualizada_mayor( $instalada, $nueva ) {
	if ( asu_version_comparar( asu_version_mayor( $instalada ), asu_version_mayor( $nueva ) ) ) {
		$actualizada = true;
	} else {
		$actualizada = false;
	}
	return $actualizada;
}

function asu_version_actualizada_menor( $instalada, $nueva ) {
	if ( ! asu_version_actualizada_mayor( $instalada, $nueva ) && asu_version_comparar( asu_version_menor( $instalada ), asu_version_menor( $nueva ) ) ) {
		$actualizada = true;
	} else {
		$actualizada = false;
	}
	return $actualizada;
}

function asu_version_actualizada_parche( $instalada, $nueva ) {
	if ( ! asu_version_actualizada_menor( $instalada, $nueva ) && asu_version_comparar( asu_version_parche( $instalada ), asu_version_parche( $nueva ) ) ) {
		$actualizada = true;
	} else {
		$actualizada = false;
	}
	return $actualizada;
}

function asu_version_comparar( $instalada, $nueva ) {
	// Incompleta.
	return version_compare( $nueva, $instalada, '>' );
}

function asu_version_mayor( $version ) {
	$version       = asu_limpia_version( $version );
	$version       = explode( '.', $version );
	$version_mayor = '';
	if ( isset( $version[0] ) ) {
		$version_mayor = $version[0];
	}
	return $version_mayor;
}

function asu_version_menor( $version ) {
	$version       = asu_limpia_version( $version );
	$version       = explode( '.', $version );
	$version_menor = '';
	if ( isset( $version[1] ) ) {
		$version_menor = $version[1];
	}
	return $version_menor;
}

function asu_version_parche( $version ) {
	$version        = asu_limpia_version( $version );
	$version_partes = explode( '.', $version );
	$version_parche = '';
	if ( isset( $version_partes[1] ) ) {
		$longitud_excluida = strlen( $version_partes[0] . '.' . $version_partes[1] . '.' );
		$version_parche    = substr( $version, $longitud_excluida, strlen( $version ) - $longitud_excluida );
		if ( ! $version_parche ) {
			$version_parche = 0; }
	}
	return $version_parche;
}

function asu_limpia_version( $version ) {
	// Retira ".0" finales.
	$version = strtolower( $version );
	preg_replace( '/\.0$/', '//', $version );
	return $version;
}

/*
 * Versión 1.1.11
function asu_element_slug( $ruta ) {
	if ( WP_PLUGIN_DIR . '/hello.php' === $ruta ) {
		// Plugin del core, sin directorio propio.
		$slug = 'hello-dolly';
	} elseif ( stripos( $ruta, '/' ) ) {
		// Obtiene el slug a partir del archivo.
		// Lo oficial sería obtenerlo del directorio.
		$slug = substr( $ruta, 0, stripos( $ruta, '/' ) );
	} elseif ( stripos( $ruta, '.php' ) ) {
		$slug = substr( $ruta, 0, stripos( $ruta, '.php' ) );
	}
	return $slug;
}
*/

/**
 * Obtiene el slug de un plugin a partir de su ruta.
 *
 * Esta función extrae el slug del plugin de su ruta.
 * Maneja casos especiales como el plugin 'Hello Dolly' que está en el directorio raíz de plugins.
 *
 * @param string $ruta La ruta del archivo del plugin.
 * @return string|null El slug del plugin o null si no puede determinarse.
 */
function asu_element_slug( $ruta ) {
	// Slug del plugin a retornar.
	$slug = null;

	// Comprueba si es el plugin 'Hello Dolly' que está en el directorio raíz.
	if ( WP_PLUGIN_DIR . '/hello.php' === $ruta ) {
		$slug = 'hello-dolly';
	} else {
		// Encuentra la primera posición de '/' en la ruta.
		$pos = stripos( $ruta, '/' );
		if ( false !== $pos ) {
			// La ruta incluye un directorio. El slug se asume que es el nombre de ese directorio.
			$slug = substr( $ruta, 0, $pos );
		} else {
			// Encuentra la primera posición de '.php' en la ruta.
			$pos = stripos( $ruta, '.php' );
			if ( false !== $pos ) {
				// No hay directorio en la ruta, asumimos que el slug es el nombre del archivo (sin la extensión .php).
				$slug = substr( $ruta, 0, $pos );
			}
		}
	}

	return $slug;
}

function asu_json_transitorio_vacio( $json_transitorio ) {
	return strlen( $json_transitorio ) <= 4;
}

function asu_anota_log( $texto ) {
	if ( '' !== $texto ) {
		$log_lines_limit = asu_conf_lineas_log();

		// Solo detiene el log si hay un valor configurado y es cero.
		if ( $log_lines_limit ) {

			// Lee el log y lo convierte en matriz.
			$log_full   = get_option( asu_log_option_name() );
			$fecha_hora = current_time( 'mysql' );

			// Inicialización del log.
			if ( ! $log_full ) {
				$log_full = $fecha_hora . ' *** Inicio del log. ***';
			}

			// Convierte el log en matriz para gestionar la rotación.
			$log_full = explode( "\n", $log_full );

			// Limita la cantidad de líneas almacenadas.
			$log_lines_actual = count( $log_full );
			while ( $log_lines_actual >= $log_lines_limit ) {
				array_shift( $log_full );
				$log_lines_actual = count( $log_full );
			}

			// Agrega la nueva línea y lo guarda todo.
			array_push( $log_full, $fecha_hora . ' ' . $texto );
			$log_full = implode( "\n", $log_full );
			preg_replace( '/\n\n/', '/\n/', $log_full );
			update_option( asu_log_option_name(), $log_full );
		}
	}
}

function asu_get_plugin_updates() {
	$all_plugins     = get_plugins();
	$upgrade_plugins = array();
	$current         = get_site_transient( 'update_plugins' );
	foreach ( (array) $all_plugins as $plugin_file => $plugin_data ) {
		if ( isset( $current->response[ $plugin_file ] ) ) {
			$upgrade_plugins[ $plugin_file ]         = (object) $plugin_data;
			$upgrade_plugins[ $plugin_file ]->update = $current->response[ $plugin_file ];
		}
	}
}

function asu_texto_actualizacion( $slug, $version_actual, $version_nueva, $automatica = false ) {
	$texto = "$slug $version_actual -> $version_nueva: ";
	if ( $automatica ) {
		$texto .= __( 'Automatic', 'automatic-safe-update' );
	} else {
		$texto .= __( 'Manual', 'automatic-safe-update' );
	}
	return $texto;
}

function asu_actualiza_lista( $texto_actualizaciones, $nueva_actualizacion ) {
	$actualizacion_nueva   = asu_actualizacion_linea_a_hash( $nueva_actualizacion );
	$texto_actualizaciones = asu_limpia_lista_compacta( $texto_actualizaciones );
	$lista                 = explode( "\n", $texto_actualizaciones );

	// Evita que se agregue un duplicado con una versión diferente.
	$renovada = false;
	$i        = 0;
	foreach ( $lista as $texto_actualizacion ) {
		if ( $texto_actualizacion ) {
			$actualizacion_lista = asu_actualizacion_linea_a_hash( $texto_actualizacion );
			if ( $actualizacion_lista['slug'] === $actualizacion_nueva['slug'] ) {
				asu_anota_log( __FUNCTION__ . " Coincide el slug de $texto_actualizacion" );
				$lista[ $i ] = $texto_actualizacion; // Renovar datos.
				$renovada    = true;
			} else {
				asu_anota_log( __FUNCTION__ . " No coincide el slug de $texto_actualizacion" );
			}
		}
		$i++;
	}

	// Si no se actualizó sobre una existente, se agrega.
	if ( ! $renovada ) {
		asu_anota_log( __FUNCTION__ . " Nuevo slug en la lista $texto_actualizacion, se agrega" );
		array_push( $lista, $nueva_actualizacion );
	}

	sort( $lista );
	$texto_actualizaciones  = implode( "\n", $lista );
	$texto_actualizaciones .= "\n";
	return $texto_actualizaciones;
}

/** De la lista dada, retira las actualizaciones que ya estén aplicadas. */
function asu_retira_actualizadas( $texto_actualizaciones ) {
	$texto_actualizaciones = asu_limpia_lista_compacta( $texto_actualizaciones );
	$lista                 = explode( "\n", $texto_actualizaciones );
	foreach ( $lista as $texto_actualizacion ) {
		if ( $texto_actualizacion ) {
			$actualizacion_lista = asu_actualizacion_linea_a_hash( $texto_actualizacion );
			if ( asu_plugin_version( $actualizacion_lista['slug'] ) === $actualizacion_lista['version_nueva'] ) {
				asu_anota_log( __FUNCTION__ . ' No se requiere actualizar ' . $actualizacion_lista['slug'] . ' ya lo está' );
				$texto_actualizaciones = asu_retira_lista( $texto_actualizaciones, $actualizacion_lista['slug'] );
			}
		}
	}
	return $texto_actualizaciones;
}

/** Retira de la lista de actualizaciones manuales un slug determinado. */
function asu_retira_manual( $slug ) {
	$mensaje_actualizaciones_denegadas = get_option( 'asu_actualizaciones_manuales' );
	$mensaje_actualizaciones_denegadas = asu_retira_lista( $mensaje_actualizaciones_denegadas, $slug );
	update_option( 'asu_actualizaciones_manuales', $mensaje_actualizaciones_denegadas );
}

function asu_retira_lista( $texto_actualizaciones, $slug ) {
	$texto_actualizaciones = asu_limpia_lista_compacta( $texto_actualizaciones );
	$lista                 = explode( "\n", $texto_actualizaciones );
	asu_anota_log( __FUNCTION__ . " Verificando si figura $slug en la lista" );
	$posicion = 0;
	foreach ( $lista as $texto_actualizacion ) {
		if ( $texto_actualizacion ) {
			$actualizacion_lista = asu_actualizacion_linea_a_hash( $texto_actualizacion );
			if ( $actualizacion_lista['slug'] === $slug ) {
				asu_anota_log( __FUNCTION__ . " Coincide el slug de $texto_actualizacion" );
				// Elimina el elemento coincidente de la matriz.
				array_splice( $lista, $posicion, 1 );
			} else {
				asu_anota_log( __FUNCTION__ . " No coincide el slug con el de $texto_actualizacion" );
			}
		}
		$posicion++;
	}
	$texto_actualizaciones = implode( "\n", $lista );
	$texto_actualizaciones = asu_limpia_lista_compacta( $texto_actualizaciones );
	return $texto_actualizaciones;
}

/** Retira posibles líneas vacías y ordena. */
function asu_limpia_lista_compacta( $texto_actualizaciones ) {
	$lista = explode( "\n", $texto_actualizaciones );
	sort( $lista );
	while ( $lista && ! $lista[0] ) {
		array_shift( $lista ); // Elimina el primer elemento si está vacío.
	}
	$texto_actualizaciones = implode( "\n", $lista );
	if ( $texto_actualizaciones ) {
		$texto_actualizaciones .= "\n";
	}
	return $texto_actualizaciones;
}

/** Pasa el formato compacto a un hash. */
function asu_actualizacion_linea_a_hash( $actualizacion ) {
	$palabra    = explode( ' ', $actualizacion );
	$palabra[3] = preg_replace( '/(.*):$/', '$1', $palabra[3] );
	// El uso de "[" en lugar de "array(" es incompatible con PHP 5.3.
	$hash = array(
		'slug'           => $palabra[0],
		'version_actual' => $palabra[1],
		'version_nueva'  => $palabra[3],
	);
	return $hash;
}

function asu_plugin_url_changelog( $slug ) {
	return self_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $slug . '&section=changelog' );
}

/*
 * Inviable, requiere un nonce.
function asu_actualizacion_url_actualizar ( $slug ) {
return self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . $slug . '%2F' . $slug . '.php' );
}
*/

/** Determina si entre las actualizaciones nuevas hay alguna no informada, para poder informar de ellas. */
function asu_actualizaciones_informar( $actualizaciones_nuevas ) {
	$actualizaciones_informadas = get_option( 'asu_actualizaciones_informadas' );
	$actualizaciones_informadas = asu_limpia_lista_compacta( $actualizaciones_informadas );
	$actualizaciones_nuevas     = asu_limpia_lista_compacta( $actualizaciones_nuevas );
	$lista_informadas           = explode( "\n", $actualizaciones_informadas );
	$lista_nuevas               = explode( "\n", $actualizaciones_nuevas );
	if ( array_diff( $lista_nuevas, $lista_informadas ) ) {
		$informar = true;
		asu_anota_log( __FUNCTION__ . ' No informadas: ' . implode( '|', array_diff( $lista_nuevas, $lista_informadas ) ) );
	} else {
		$informar = false;
	}
	return $informar;
}

function mensaje_pendientes_actualizar( $cantidad_pendientes ) {
	$mensaje  = _n( 'There is', 'There are', $cantidad_pendientes, 'automatic-safe-update' );
	$mensaje .= " $cantidad_pendientes ";
	$mensaje .= _n( 'plugin pending to update', 'plugins pending to update', $cantidad_pendientes, 'automatic-safe-update' );
	return $mensaje;
}

/** De un texto con la lista de plugins actualizados, rechazados o informados, genera el formato completo para el e-mail.  */
function asu_reprocesa_lista_compacta( $texto_actualizaciones_viejo, $indica_modo = true, $indica_estado = false ) {
	$texto_actualizaciones_viejo = asu_limpia_lista_compacta( $texto_actualizaciones_viejo );
	$lista_actualizaciones_vieja = explode( "\n", $texto_actualizaciones_viejo );
	$actualizacion               = new asu_Actualizacion();
	$texto_actualizaciones_email = '';
	foreach ( $lista_actualizaciones_vieja as $linea ) {
		// Solo procesa líneas interpretables.
		if ( $linea ) {
			$actualizacion->interpreta_linea( $linea );
			$texto_actualizaciones_email .= $actualizacion->linea_nueva( $indica_modo, $indica_estado ) . "\n";
		}
	}
	return $texto_actualizaciones_email;
}

/** De un texto con la lista de plugins actualizados, rechazados o informados, genera el formato completo para el e-mail. */
function asu_extrae_no_aplicadas( $texto_actualizaciones_compacto ) {
	$texto_actualizaciones_compacto     = asu_limpia_lista_compacta( $texto_actualizaciones_compacto );
	$lista_actualizaciones_vieja        = explode( "\n", $texto_actualizaciones_compacto );
	$actualizacion                      = new asu_Actualizacion();
	$texto_actualizaciones_no_aplicadas = '';
	foreach ( $lista_actualizaciones_vieja as $linea ) {
		// Solo procesa líneas interpretables.
		if ( $linea ) {
			// Solo toma las que no sean OK (aplicadas).
			$actualizacion->interpreta_linea( $linea );
			if ( $actualizacion->texto_estado() !== 'OK' ) {
				$texto_actualizaciones_no_aplicadas .= $actualizacion->linea_compacta() . "\n";
			}
		}
	}
	return $texto_actualizaciones_no_aplicadas;
}

function asu_plugins_configurados() {
	$lista_plugins = get_option( 'asu_plugins' );
	// Si no hay valores, entrega una matriz vacía para evitar errores en los foreach.
	if ( empty( $lista_plugins ) ) {
		$lista_plugins = array();
	}
	return $lista_plugins;
}

function plugin_esta_configurado( $slug ) {
	$esta_configurado           = false;
	$lista_plugins_configurados = asu_plugins_configurados();
	if ( ! empty( $lista_plugins_configurados ) ) {
		foreach ( $lista_plugins_configurados as $elemento => $valor ) {
			if ( $elemento === $slug ) {
				$esta_configurado = true;
				break;
			}
		}
	}
	return $esta_configurado;
}

function asu_plugin_nombre( $slug ) {
	if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'is_plugin_active' ) ) {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$plugins = get_plugins();
	// Si no lo localiza, mejor entregar el slug que nada.
	$name = $slug;
	// Recorre los plugins existentes buscando el solicitado.
	foreach ( $plugins as $plugin_file => $plugin_data ) {
		$slug_leido = asu_element_slug( $plugin_file );
		if ( $slug_leido === $slug ) {
			$name = $plugin_data['Name'];
			break;
		}
	}
	return $name;
}

function asu_log_option_name() {
	return 'asu_log';
}
