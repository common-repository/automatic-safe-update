<?php
/**
 * Runs on Uninstall of Automatic Safe Update
 *
 * @package   Automatic Safe Update
 * @author    ABCdatos
 * @license   GPLv2
 * @link      https://taller.abcdatos.net/plugin-actualizar-wordpress/
 */

// La propiedad modo ha de pasar a ser una función y la propiedad real ser un booleano modo_automatico.

if ( ! class_exists( 'asu_Actualizacion' ) ) {
	class asu_Actualizacion {

		// Propiedades.
		public $slug;
		public $version_actual;
		public $version_nueva;
		public $modo_automatico;
		public $modo;
		public $nombre;

		public function __construct() {
			$this->slug            = '';
			$this->version_actual  = '';
			$this->version_nueva   = '';
			$this->modo            = __( 'Manual', 'automatic-safe-update' );
			$this->version_nueva   = '';
			$this->modo_automatico = false;
			$this->nombre          = '';
		}

		public function interpreta_linea( $actualizacion ) {
			if ( $actualizacion ) {
				$palabra               = explode( ' ', $actualizacion );
				$this->slug            = $palabra[0];
				$this->version_actual  = $palabra[1];
				$this->version_nueva   = $palabra[3];
				$this->modo            = $palabra[4];
				$this->version_nueva   = preg_replace( '/(.*):$/', '$1', $this->version_nueva );
				$this->modo_automatico = ( __( 'Automatic', 'automatic-safe-update' ) === $this->modo );
				$this->nombre          = '';
			}
		}

		public function linea_sin_modo() {
			return $this->slug . ' ' . $this->version_actual . ' -> ' . $this->version_nueva;
		}

		public function url_changelog() {
			if ( 'js_composer' === $this->slug ) {
				// URL para WP Bakery.
				$url = 'https://kb.wpbakery.com/docs/preface/release-notes/';
			} else {
				// Enlace genérico.

				/**
				 * Este tarda en actualizarse.
				// return admin_url ( 'plugin-install.php?tab=plugin-information&section=changelog&plugin=' . $this->slug ) ;
				 */

				$idioma     = get_bloginfo( 'language' );
				$idioma     = explode( '-', $idioma );
				$subdominio = $idioma[0];
				$url        = 'https://';
				if ( 'en' !== $subdominio ) {
					$url .= "$subdominio.";
				}
				$url .= "wordpress.org/plugins/$this->slug/#developers";
			}
			return $url;
		}

		// Devuelve el título del plugin,sea ya conocido o buscándolo.
		public function nombre() {
			if ( ! $this->nombre ) {
				$this->obtiene_nombre();
			}
			return $this->nombre;
		}

		// Obtiene el título del plugin si todavía no consta.
		private function obtiene_nombre() {
			if ( ! $this->nombre ) {
				// Valor por omisión, el slug.
				$this->nombre = $this->slug;
				if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'is_plugin_active' ) ) {
					include_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				$plugins = get_plugins();
				// Recorre los plugins existentes buscando el solicitado.
				foreach ( $plugins as $plugin_file => $plugin_data ) {
					$slug_leido = asu_element_slug( $plugin_file );
					// Cuando coincida, tomamos el título de los metadatos.
					if ( $slug_leido === $this->slug ) {
						$this->nombre = $plugin_data['Name'];
						break;
					}
				}
			}
		}

		// Indica si le versión realmente instalada es la que se pretendía actualizar.
		private function version_correcta() {
			return ( asu_plugin_version( $this->slug ) === $this->version_nueva );
		}

		// Línea en el formato primigenio en que se guardaban y comunicaban las actualizaciones aplicadas, rechazada e informadas..
		public function linea_compacta() {
			return "$this->slug $this->version_actual -> $this->version_nueva: " . $this->texto_modo();
		}

		private function texto_modo() {
			if ( $this->modo_automatico ) {
				$texto = __( 'Automatic', 'automatic-safe-update' );
			} else {
				$texto = __( 'Manual', 'automatic-safe-update' );
			}
			return $texto;
		}

		public function texto_estado() {
			if ( $this->version_correcta() ) {
				$texto = 'OK';
			} else {
				$texto = __( 'Failure', 'automatic-safe-update' );
			}
			return $texto;
		}

		// Línea en el formato primigenio en que se guardaban y comunicaban las actualizaciones aplicadas, rechazadas e informadas.
		public function linea_nueva( $indica_modo = true, $indica_estado = false ) {
			$linea = $this->nombre() . " $this->version_actual -> $this->version_nueva";
			if ( $indica_modo ) {
				$linea .= ': ' . $this->texto_modo();
			}
			if ( $indica_estado ) {
				$linea .= ' [' . $this->texto_estado() . ']';
			}
			$linea .= "\n";
			$linea .= $this->url_changelog();
			$linea .= "\n";
			return $linea;
		}
	}
}
