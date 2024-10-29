<?php
/** Página de configuración.
 *
 * @package   Automatic Safe Update
 * @author    ABCdatos
 * @license   GPLv2
 * @link      https://taller.abcdatos.net/plugin-actualizar-wordpress/
 */

defined( 'ABSPATH' ) || die( 'No se permite el acceso.' );

/**
 * Dashboard menu settings.
 */
function asu_add_admin_menu() {
	if ( ! asu_conf_lock_settings() ) {
		if ( asu_errores_config() ) {
			$notificacion_contenido = '!';
			$notificacion_globo     = " <span class=\"awaiting-mod\">$notificacion_contenido</span>";
		} else {
			$notificacion_globo = '';
		}
		add_menu_page(
			__( 'Automatic Safe Update', 'automatic-safe-update' ) . ' - ' . __( 'Settings', 'automatic-safe-update' ), // Page title.
			__( 'Automatic Safe Update', 'automatic-safe-update' ) . $notificacion_globo,
			'manage_options',                                                     // Capability.
			'automatic-safe-update',                                              // Menu slug.
			'asu_admin',                                                          // Function.
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Base64 encoding used for SVG icon; considered safe.
			'data:image/svg+xml;base64,' . base64_encode( '<svg viewBox="50 150 200 200" xmlns="http://www.w3.org/2000/svg"><path fill="#a7aaad" d="M232.5938 262.6875 Q232.5938 285.6094 223.3125 305.4375 Q213.6094 326.25 195.75 339.3281 L211.3594 351.5625 L161.1562 365.0625 L165.7969 315.1406 L179.8594 326.25 Q201.6562 306.9844 201.6562 272.6719 Q201.6562 228.6562 159.75 203.9062 L177.4688 169.4531 Q203.3438 181.9688 218.25 208.125 Q232.5938 233.0156 232.5938 262.6875 ZM125.1562 330.6094 L107.4375 365.0625 Q81.8438 352.6875 66.7969 326.3906 Q52.3125 301.2188 52.3125 271.8281 Q52.3125 249.1875 61.7344 229.0781 Q71.4375 208.2656 89.1562 195.1875 L73.5469 182.8125 L123.75 169.4531 L119.1094 219.375 L105.0469 208.2656 Q83.25 227.5312 83.25 261.8438 Q83.25 305.8594 125.1562 330.6094 Z"/></svg>' )         // Icon.
		);
	}
}
add_action( 'admin_menu', 'asu_add_admin_menu' );

/**
 * Settings page function.
 */
function asu_admin() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'No tienes suficientes permisos para acceder a esta página.' );
	}
	?>
	<div class="wrap">
		<h1>
		<?php
		esc_html_e( 'Automatic Safe Update Settings', 'automatic-safe-update' );
		?>
		<?php
		echo ' <small>v';
		echo esc_html( asu_get_version() );
		?>
		</small></h1>
		<form method="POST" action="options.php">
			<?php
				settings_fields( 'automatic-safe-update-ajustes' );
				do_settings_sections( 'automatic-safe-update-ajustes' );
				submit_button();
			?>
		</form>
	</div>

	<div>
	<!--
	<h4>Recomprobando actualizaciones y enviando e-mail si las hay</h4>
	<p>Es probable que no se estén refrescando las listas de actualizaciones en este punto, no sé si sería oportuno o necesario hacerlo. Actualmente creo que solo se hace cuando se aplica el filtro auto_update_plugin, así que la recomprobación manual no estaría afectando, he de ver si meterlo en el pre_set_site_transient_update_plugins cuando haya datos o qué..</p>
	-->
	<?php
	echo '<h3>';
	esc_html_e( 'System status', 'automatic-safe-update' );
	echo '</h3>';

	// Verifica si está activo AUTOMATIC_UPDATER_DISABLED.

	// Definición temporales para evitar errores de Intelephense en desarrollo.
	if ( ! defined( 'AUTOMATIC_UPDATER_DISABLED' ) ) {
		define( 'AUTOMATIC_UPDATER_DISABLED', false );
	}

	echo '<h4>AUTOMATIC_UPDATER_DISABLED ';
	esc_html_e( 'is set to', 'automatic-safe-update' );
	echo ': <b>';
	if ( true === AUTOMATIC_UPDATER_DISABLED ) {
		esc_html_e( 'true', 'automatic-safe-update' );
	} elseif ( false === AUTOMATIC_UPDATER_DISABLED ) {
		esc_html_e( 'false', 'automatic-safe-update' );
	} else {
		echo esc_html( AUTOMATIC_UPDATER_DISABLED );
	}
	echo '</b>. ';
	if ( false !== AUTOMATIC_UPDATER_DISABLED ) {
		esc_html_e( 'Disable it, typically in wp-config.php if you want to enable plugin updates', 'automatic-safe-update' );
	}
	echo '</h4>';

	// Verifica si está activo WP_AUTO_UPDATE_CORE.

	// Definición temporal para evitar errores de Intelephense en desarrollo.
	if ( ! defined( 'WP_AUTO_UPDATE_CORE' ) ) {
		define( 'WP_AUTO_UPDATE_CORE', 'minor' );
	}

	echo '<h4>WP_AUTO_UPDATE_CORE ';
	esc_html_e( 'is set to', 'automatic-safe-update' );
	echo ': <b>';
	if ( true === WP_AUTO_UPDATE_CORE ) {
		esc_html_e( 'true', 'automatic-safe-update' );
	} elseif ( false === WP_AUTO_UPDATE_CORE ) {
		esc_html_e( 'false', 'automatic-safe-update' );
	} else {
		echo esc_html( WP_AUTO_UPDATE_CORE );
	}
	echo '</b>.</h4>';

	asu_anota_log( __FUNCTION__ . ' ***** Vaciado del transitorio para forzar comprobación manual *****' );
	// Alternativa a insertarle el null con set_site_transient( 'update_plugins', null );
	// Pues llegaba a causar un error crítico difícil de localizar.
	delete_site_transient( 'update_plugins' );
	asu_anota_log( __FUNCTION__ . ' Llamada manual a recomprobación, con envío final de email.' );
	wp_update_plugins();
	asu_anota_log( __FUNCTION__ . ' Carga de lista de actualizaciones' );

	$cantidad_pendientes = asu_cantidad_actualizaciones_pendientes();

	if ( ! $cantidad_pendientes ) {
		asu_anota_log( __FUNCTION__ . ' Todos los plugins están actualizados' );
		echo '<h4>';
		esc_html_e( 'All plugins are up to date.', 'automatic-safe-update' );
		echo '</h4>';
	} else {
		asu_anota_log( __FUNCTION__ . ' ' . mensaje_pendientes_actualizar( $cantidad_pendientes ) );
		echo '<h4>';
		echo esc_html( mensaje_pendientes_actualizar( $cantidad_pendientes ) );
		echo '.</h4>';
		asu_envia_email_actualizaciones();
	}

	asu_imprime_lista_compacta( asu_reprocesa_lista_compacta( asu_lista_actualizaciones_pendientes(), true ) );

	echo '<h3>';
	esc_html_e( 'Log', 'automatic-safe-update' );
	echo '</h3>';
	asu_imprime_log();

	?>
	</div>
	<?php
}

/**
 * Initializes the settings for the plugin.
 */
function asu_settings_init() {
	foreach ( asu_lista_opciones() as $nombre_opcion ) {
		if ( 'asu_actualizacion_omision' === $nombre_opcion ) {
			register_setting(
				'automatic-safe-update-ajustes',
				$nombre_opcion,
				array(
					'sanitize_callback' => 'asu_sanear_omision',
					'type'              => 'integer',
				)
			);
		} elseif ( 'asu_plugins' === $nombre_opcion ) {
			register_setting( 'automatic-safe-update-ajustes', $nombre_opcion, 'asu_sanear_plugins' );
		} else {
			register_setting( 'automatic-safe-update-ajustes', $nombre_opcion );
		}
	}

	add_settings_section(
		'asu_seccion_general',                           // $id (string) (Required) Slug-name to identify the section. Used in the 'id' attribute of tags.
		__( 'Basic settings', 'automatic-safe-update' ), // $title (string) (Required) Formatted title of the section. Shown as the heading for the section.
		'asu_seccion_general_callback',                  // $callback (callable) (Required) Function that echos out any content at the top of the section (between heading and fields).
		'automatic-safe-update-ajustes'                  // $page (string) (Required) The slug-name of the settings page on which to show the section. Built-in pages include 'general', 'reading', 'writing', 'discussion', 'media', etc. Create your own using add_options_page();
	);

	add_settings_field(
		'asu_actualizacion_omision',                                     // $id (string) (Required) Slug-name to identify the field. Used in the 'id' attribute of tags.
		__( 'Defaut plugin update level', 'automatic-safe-update' ),     // $title (string) (Required) Formatted title of the field. Shown as the label for the field during output.
		'asu_actualizacion_omision_callback',                            // $callback (callable) (Required) Function that fills the field with the desired form inputs. The function should echo its output.
		'automatic-safe-update-ajustes',                                 // $page (string) (Required) The slug-name of the settings page on which to show the section (general, reading, writing, ...).
		'asu_seccion_general',                                           // $section (string) (Optional) The slug-name of the section of the settings page in which to show the box.
		array()                                                         // $args (array) (Optional) Extra arguments used when outputting the field.
	);

	add_settings_field(
		'asu_admin_email',                                               // $id (string) (Required) Slug-name to identify the field. Used in the 'id' attribute of tags.
		__( 'Send updates to e-mail address', 'automatic-safe-update' ), // $title (string) (Required) Formatted title of the field. Shown as the label for the field during output.
		'asu_admin_email_callback',                                      // $callback (callable) (Required) Function that fills the field with the desired form inputs. The function should echo its output.
		'automatic-safe-update-ajustes',                                 // $page (string) (Required) The slug-name of the settings page on which to show the section (general, reading, writing, ...).
		'asu_seccion_general',                                           // $section (string) (Optional) The slug-name of the section of the settings page in which to show the box.
		array()                                                         // $args (array) (Optional) Extra arguments used when outputting the field.
	);

	add_settings_field(
		'asu_lineas_log',
		__( 'Log max lines', 'automatic-safe-update' ),
		'asu_lineas_log_callback',
		'automatic-safe-update-ajustes',
		'asu_seccion_general',
		array()
	);

	add_settings_field(
		'asu_plugins',
		__( 'Individual plugin setting', 'automatic-safe-update' ),
		'asu_plugins_individuales_callback',
		'automatic-safe-update-ajustes',
		'asu_seccion_general',
		array()
	);

	add_settings_section(
		'asu_seccion_experimental',
		__( 'Experimental settings', 'automatic-safe-update' ),
		'asu_seccion_experimental_callback',
		'automatic-safe-update-ajustes'
	);

	add_settings_field(
		'asu_actualizacion_traducciones',
		__( 'Update translations', 'automatic-safe-update' ),
		'asu_actualizacion_traducciones_callback',
		'automatic-safe-update-ajustes',
		'asu_seccion_experimental',
		array()
	);

	add_settings_field(
		'asu_actualizacion_temas',
		__( 'Update themes', 'automatic-safe-update' ),
		'asu_actualizacion_temas_callback',
		'automatic-safe-update-ajustes',
		'asu_seccion_experimental',
		array()
	);
}
add_action( 'admin_init', 'asu_settings_init' );

// Callbacks to show options data.
// Callbacks para la presentación de datos de opciones.

/**
 * Callback function for the general section.
 */
function asu_seccion_general_callback() {
	// echo __( 'Email communications data.', 'automatic-safe-update' );
	// Sin contenido a devolver.
}

/**
 * Hidden field to save version number.
 */
function asu_version_callback() {
	echo '<input name="asu_version" type="hidden" id="asu_version" value="' . esc_attr( asu_get_version() ) . '" />';
}

/**
 * Callback function for the default plugin update level field.
 */
function asu_actualizacion_omision_callback() {
	asu_muestra_plugin_linea_configuracion_omision( asu_conf_actualizacion_omision() );
	echo '<p class="description" id="tagline-description">';
	esc_html_e( 'Default max plugin version level to update.', 'automatic-safe-update' );
	echo '</p>';
}

/**
 * Callback function for the admin email field.
 */
function asu_admin_email_callback() {
	echo '<input name="asu_admin_email" type="text" id="asu_admin_email" value="' . esc_attr( asu_conf_admin_email() ) . '" class="regular-text" />';
	// Dirección a la que se notificarán las actualizaciones pendientes y aplicadas.
	echo '<p class="description" id="tagline-description">';
	esc_html_e( 'Notification address for pending and applied updates.', 'automatic-safe-update' );
	echo '</p>';
}

/**
 * Callback function for the log lines field.
 */
function asu_lineas_log_callback() {
	echo '<input name="asu_lineas_log" type="text" id="asu_lineas_log" value="' . esc_attr( asu_conf_lineas_log() ) . '" class="regular-text" />';
	echo '<p class="description" id="tagline-description">';
	esc_html_e( 'Maximum lines amount to keep in log.', 'automatic-safe-update' );
	echo '</p>';
}

/**
 * Callback function for the lock settings field.
 */
function asu_lock_settings_callback() {
	echo "<input type='checkbox' name='asu_lock_settings' ";
	checked( asu_conf_lock_settings(), 1 );
	echo " value='1'> ";
	esc_html_e( 'Lock access to this settings page.', 'automatic-safe-update' );
	echo '<p class="description" id="tagline-description" style="color:red;">';
	esc_html_e( 'Don\'t do it if you aren\'t fully sure what are you doing.', 'automatic-safe-update' ) . ' ';
	esc_html_e( 'To restore it, you will need remove or set the value to zero (0) the option asu_lock_settings in options table from database.', 'automatic-safe-update' );
	echo '</p>';
}

/**
 * Callback function for the experimental section.
 */
function asu_seccion_experimental_callback() {
	esc_html_e( 'These settings are only partially supportted and may experience unexpected results.', 'automatic-safe-update' );
}

/**
 * Callback function for the update translations field.
 */
function asu_actualizacion_traducciones_callback() {
	echo "<input type='checkbox' name='asu_actualizacion_traducciones' ";
	checked( asu_conf_actualizacion_traducciones(), 1 );
	echo " value='1'> ";
	esc_html_e( 'Update translations', 'automatic-safe-update' );
	echo '.';
	echo '<p class="description" id="tagline-description">';
	esc_html_e( 'Update all translations.', 'automatic-safe-update' );
	echo '</p>';
}

/**
 * Callback function for the update themes field.
 */
function asu_actualizacion_temas_callback() {
	echo "<input type='checkbox' name='asu_actualizacion_temas' ";
	checked( asu_conf_actualizacion_temas(), 1 );
	echo " value='1'> ";
	esc_html_e( 'Update themes', 'automatic-safe-update' );
	echo '.';
	echo '<p class="description" id="tagline-description">';
	esc_html_e( 'Update all themes.', 'automatic-safe-update' );
	echo '</p>';
}

/**
 * Callback function for the individual plugin settings field.
 */
function asu_plugins_individuales_callback() {
	// Hay que recorrer los plugins configurados y los instalados NO configurados.
	$lista_plugins = asu_plugins_configurados();

	// Agrega los plugins instalados no configurados a la lista.
	if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'is_plugin_active' ) ) {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$plugins_instalados = get_plugins();
	foreach ( $plugins_instalados as $plugin_file => $plugin_data ) {
		$slug = asu_element_slug( $plugin_file );
		if ( ! plugin_esta_configurado( $slug ) ) {
			$lista_plugins[ $slug ] = '';
		}
	}

	// Ordena por clave (slug) mejor sería ordenar por tìtulo.
	ksort( $lista_plugins );

	// Campos del formulario para cada plugin.
	foreach ( $lista_plugins as $slug => $valor ) {
		// Muestra todos los plugins menos el propio, para poder actualizarlo siempre.
		if ( 'automatic-safe-update' !== $slug ) {
			asu_muestra_plugin_linea_configuracion( $slug, $valor );
		}
	}
}

/**
 * Checks for configuration errors.
 * Para indicar advertencia en la burbuja del menú.
 *
 * @return bool False indicating no errors in this implementation.
 */
function asu_errores_config() {
	return false;
}

/**
 * Displays the configuration line for a plugin.
 *
 * @param string $slug  The slug of the plugin.
 * @param mixed  $valor The value of the plugin configuration.
 */
function asu_muestra_plugin_linea_configuracion( $slug, $valor ) {
	asu_muestra_plugin_html_select_configuracion( 'asu_plugins[' . $slug . ']', $valor, true );
	echo esc_html( asu_plugin_nombre( $slug ) );
	echo "<br />\n";
}

/**
 * Displays the default configuration line for plugins.
 *
 * @param mixed $valor The value of the default plugin configuration.
 */
function asu_muestra_plugin_linea_configuracion_omision( $valor ) {
	asu_muestra_plugin_html_select_configuracion( 'asu_actualizacion_omision', $valor, false );
}

/**
 * Displays the HTML select element for plugin configuration.
 *
 * @param string $nombre_campo   The name attribute for the select element.
 * @param mixed  $valor          The value of the selected option.
 * @param bool   $mostrar_omision Whether to show the default option.
 */
function asu_muestra_plugin_html_select_configuracion( $nombre_campo, $valor, $mostrar_omision = null ) {
	echo "\t<select name='" . esc_attr( $nombre_campo ) . "'>\n";
	echo "\t<option value='3'" . selected( $valor, 3, false ) . '>' . esc_html( __( 'Major', 'automatic-safe-update' ) ) . "</option>\n";
	echo "\t<option value='2'" . selected( $valor, 2, false ) . '>' . esc_html( __( 'Minor', 'automatic-safe-update' ) ) . "</option>\n";
	echo "\t<option value='1'" . selected( $valor, 1, false ) . '>' . esc_html( __( 'Patch', 'automatic-safe-update' ) ) . "</option>\n";
	echo "\t<option value='0'" . selected( $valor, 0, false ) . '>' . esc_html( __( 'Hold', 'automatic-safe-update' ) ) . "</option>\n";
	if ( $mostrar_omision ) {
		echo "\t<option value=''" . selected( $valor, '', false ) . '>' . esc_html( __( 'Default', 'automatic-safe-update' ) ) . "</option>\n";
	}
	echo "</select>\n";
}

/**
 * Función de devolución de llamada para sanear la configuración de los plugins.
 *
 * Esta función recorre el array de plugins y elimina cualquier entrada cuyo valor sea una cadena vacía.
 * Esto se utiliza para limpiar la configuración de los plugins, eliminando aquellos plugins que no tienen
 * una configuración establecida y presumiblemente los no exostentes.
 *
 * @param array $plugins El array de plugins a sanitizar.
 *
 * @return array El array de plugins sanitizado.
 */
function asu_sanear_plugins( $plugins ) {
	foreach ( $plugins as $slug => $valor ) {
		if ( '' === $valor ) {
			unset( $plugins[ $slug ] );
		}
	}
	return $plugins;
}

/**
 *  Sanea el valor del nivel de actualización por omisión recibido del formualrio.
 *
 * @param string $nivel Valor numérico del nivel, llega amentablemente como cadena y por eso requiere la conversión.
 *
 * @return int La configuración de nivel de actualización sanitizada.
 */
function asu_sanear_omision( $nivel ) {
	// Llega como string para saneado desde el form., se pasa a entero.
	$nivel = intval( $nivel );

	if ( 0 !== $nivel && 1 !== $nivel && 2 !== $nivel && 3 !== $nivel ) {
		$nivel = asu_conf_actualizacion_omision();
	}
	return $nivel;
}

/**
 * Muestra los plugins pendientes.
 *
 * En el futuro, usar algo tipo JSON o serializar para manejar esos datos.
 *
 * @param string $lista_compacta Contenido en modo texto compactado de la lista de actualizaciones.
 */
function asu_imprime_lista_compacta( $lista_compacta ) {
	$lista_html = str_replace( "\n", "<br />\n", esc_html( $lista_compacta ) );
	echo wp_kses( $lista_html, array( 'br' => array() ) );
}

/**
 * Muestra el contenido del log
 */
function asu_imprime_log() {
	$log_full = get_option( asu_log_option_name() );
	$log_html = str_replace( "\n", "<br />\n", esc_html( $log_full ) );
	echo wp_kses( $log_html, array( 'br' => array() ) );
}
