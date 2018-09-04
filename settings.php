<?php
/**
 * Function to be hooked to the pre_option_$option_name filter.
 *
 * @param mixed  $value  Original option value.
 * @param string $option Name of option.
 *
 * @return mixed The value of the option.
 */
function geolocation_settings_pre_option( $value, $option ) {
	$market_id = geolocation_get_current_market_id();

	if ( $market_id ) {
		$value = apply_filters( 'geolocation_settings_pre_option_' . $option, geolocation_settings_get_option( $option, $market_id ), $market_id, $option );
	}

	return $value;
}

/**
 * Returns the option value for an specific market.
 *
 * @param string $option The option name.
 * @param int	 $market_id The market ID.
 *
 * @return mixed The option value. FALSE if it is not found.
 */
function geolocation_settings_get_option( $option, $market_id ) {
	$meta_key = 'market_' . $option;

	if ( metadata_exists( 'term', $market_id, $meta_key ) ) {
		$value = get_term_meta( $market_id, $meta_key, true );
	} else {
		remove_filter( 'pre_option_' . $option, 'geolocation_settings_pre_option', 10, 3 );

		$value = get_option( 'market_' . $option . '_' . $market_id );

		if ( false === $value ) {
			$value = get_option( 'market_' . $market_id . '_' . $option );

			if ( false === $value ) {
				$value = get_option( $option . '_' . $market_id );
			}
		}

		add_filter( 'pre_option_' . $option, 'geolocation_settings_pre_option', 10, 3 );
	}

	return $value;
}

/**
 * Deletes the option value for an specific market.
 *
 * @param string $option The option name.
 * @param int	 $market_id The market ID.
 *
 * @return boolean TRUE on success, FALSE otherwise.
 */
function geolocation_settings_delete_option( $option, $market_id ) {
	$meta_key = 'market_' . $option;
	$response = false;

	if ( metadata_exists( 'term', $market_id, $meta_key ) ) {
		$response = delete_term_meta( $market_id, $meta_key );
	} else {
		remove_filter( 'pre_option_' . $option, 'geolocation_settings_pre_option', 10, 3 );

		$option = 'market_' . $option . '_' . $market_id;

		$value = get_option( $option );

		if ( false === $value ) {
			$option = 'market_' . $market_id . '_' . $option;

			$value = get_option( $option );

			if ( false === $value ) {
				$option = $option . '_' . $market_id;
			}
		}

		$response = delete_option( $option );

		add_filter( 'pre_option_' . $option, 'geolocation_settings_pre_option', 10, 3 );
	}

	return $response;
}

/**
 * In order to avoid searching for every option, only saved options through
 * this page will be filtered based on the market. To do that, we use an
 * special option called "geolocation_market_settings" which contains all the
 * options that were overriden.
 *
 * For backward compatibility, in case the market settings were already
 * defined by other plugin, users should go to each page and save values if
 * options are not being showed when navigating the website.
 */
if ( get_option( 'geolocation_extend_settings' ) ) {
	/**
	 * We add the market selector to the admin bar.
	 */
	add_action( 'admin_bar_menu', function( $wp_admin_bar ) {
		if ( is_admin() &&
			 current_user_can( 'manage_options' ) ) {
			$current_market_name = geolocation_get_current_market_name();

			$markets = geolocation_get_markets();

			if ( ! empty( $markets ) ) {
				$node = array(
					'id'		=> 'geolocation_market',
					'parent'	=> false,
					'title'		=> sprintf( __( 'Current Market: %s', 'geolocation' ), $current_market_name ),
				);

				$wp_admin_bar->add_node( $node );

				$node = array(
					'href'		=> remove_query_arg( 'geolocation_market' ),
					'id'		=> 'geolocation_market_default',
					'parent'	=> 'geolocation_market',
					'title'		=> __( 'Default', 'geolocation' ),
				);

				$wp_admin_bar->add_node( $node );

				foreach ( $markets as $market ) {
					$node = array(
						'href'		=> add_query_arg( 'geolocation_market', $market->term_id ),
						'id'		=> 'geolocation_market_' . $market->term_id,
						'parent'	=> 'geolocation_market',
						'title'		=> $market->name,
					);

					$wp_admin_bar->add_node( $node );
				}
			}
		}
	}, 999 );

	add_action( 'admin_init', function() {
		global $wp_registered_settings;

		$geolocation_market_settings = array();

		$market_id = geolocation_get_current_market_id( 'administrator' );

		foreach ( $wp_registered_settings as $option_name => $args ) {
			if ( false === strpos( $option_name, 'geolocation_' ) ) {
				$geolocation_market_settings[ $option_name ] = true;

				/**
				 * We only allow this for 'manage_options'-capable users and if
				 * they are working over a valid market rather than the default
				 * one.
				 */
				if ( $market_id && current_user_can( 'manage_options' ) ) {
					add_filter( 'pre_update_option_' . $option_name, function( $value, $old_value, $option ) use ( $market_id ) {
						$current_value = geolocation_settings_get_option( $option, $market_id );

						if ( $current_value !== $value ) {
							update_term_meta( $market_id, 'market_' . $option, $value );
						}

						/**
						 * Returning the old value we make sure the original
						 * option is not updated.
						 *
						 * @link https://codex.wordpress.org/Plugin_API/Filter_Reference/pre_update_option_(option_name)
						 */
						return $old_value;
					}, 10, 3 );
				}
			}
		}

		/**
		 * We only save the geolocation settings if they were modified.
		 */
		if ( $geolocation_market_settings !== get_option( 'geolocation_market_settings' ) ) {
			update_option( 'geolocation_market_settings', $geolocation_market_settings );
		}
	}, 999 );

	/**
	 * Here we enqueue a script and a style files that allow us to identify
	 * visually which options are being altered by market.
	 */
	add_action( 'admin_enqueue_scripts', function() {
		if ( current_user_can( 'manage_options' ) ) {	
			wp_enqueue_style( 'geolocation_settings', WP_CONTENT_URL . '/themes/vip/entravision-plugins/geolocation/admin/css/settings.css' );

			wp_enqueue_style( 'wp-jquery-ui-dialog' );

			wp_enqueue_script( 'geolocation_settings', WP_CONTENT_URL . '/themes/vip/entravision-plugins/geolocation/admin/js/settings.js' );

			wp_enqueue_script( 'jquery-ui-dialog' );

			wp_localize_script( 'geolocation_settings', 'geolocation_settings', array(
				'options' => get_option( 'geolocation_market_settings' ),
				'nonces'  => array(
					'load'   => wp_create_nonce( 'geolocation_settings_load' ),
					'delete' => wp_create_nonce( 'geolocation_settings_delete' ),
				),
				'i18n'    => array(
					'delete'         => __( 'Delete', 'geolocation' ),
					'reload'         => __( 'Reload', 'geolocation' ),
					'confirm_delete' => __( 'Are you sure to delete this value?', 'geolocation' ),
					'modal_loading'  => __( 'Loading...', 'geolocation' ),
					'modal_title'    => __( 'Viewing values for settings "%s"...', 'geolocation' ),
					'error_delete'   => __( 'Cannot delete setting, maybe it does not exist.', 'geolocation' ),
					'error_unknown'  => __( 'Unknown error. Please, try again later.', 'geolocation' ),
					'error_setting'  => __( 'Unknown setting.', 'geolocation' ),
				),
			) );
		}
	} );

	/**
	 * Added dialog for visualization of other markets settings.
	 */
	add_action( 'admin_footer', function() {
		if ( current_user_can( 'manage_options' ) ) {
			echo '<div id="geolocation-settings-modal"></div>';
		}
	} );

	/**
	 * AJAX handler for deleting setting.
	 */
	add_action( 'wp_ajax_geolocation_settings_delete', function() {
		if ( current_user_can( 'manage_options' ) ) {
			check_ajax_referer( 'geolocation_settings_delete' );

			if ( isset( $_POST['geolocation_settings_setting'] ) ) {
				$setting = sanitize_text_field( wp_unslash( $_POST['geolocation_settings_setting'] ) );
			} else {
				$setting = null;
			}

			if ( isset( $_POST['geolocation_settings_market_id'] ) ) {
				$market_id = absint( wp_unslash( $_POST['geolocation_settings_market_id'] ) );
			} else {
				$market_id = null;
			}

			$response = false;

			if ( $setting && null !== $market_id ) {
				if ( $market_id ) {
					$response = geolocation_settings_delete_option( $setting, $market_id );
				} else {
					$response = delete_option( $setting );
				}
			}

			wp_send_json( $response );
		}
	} );

	/**
	 * AJAX handler for loading setting for all markets.
	 */
	add_action( 'wp_ajax_geolocation_settings_load', function() {
		if ( current_user_can( 'manage_options' ) ) {
			check_ajax_referer( 'geolocation_settings_load' );

			if ( isset( $_POST['geolocation_settings_setting'] ) ) {
				$setting = sanitize_text_field( wp_unslash( $_POST['geolocation_settings_setting'] ) );
			} else {
				$setting = null;
			}

			$response = null;

			if ( $setting ) {
				$response = array();

				remove_filter( 'pre_option_' . $setting, 'geolocation_settings_pre_option', 10, 3 );

				$response[] = array(
					'id'      => 0,
					'name'    => __( 'Default', 'geolocation' ),
					'setting' => var_export( get_option( $setting ), true ),
				);

				add_filter( 'pre_option_' . $setting, 'geolocation_settings_pre_option', 10, 3 );

				$markets = geolocation_get_markets();

				foreach ( $markets as $market ) {
					$response[] = array(
						'id'      => $market->term_id,
						'name'    => $market->name,
						'setting' => var_export( geolocation_settings_get_option( $setting, $market->term_id ), true ),
					);
				}
			}

			wp_send_json( $response );
		}
	} );

	/**
	 * We add a filter for each option previously registered in
	 * "geolocation_market_settings".
	 */
	$geolocation_market_settings = get_option( 'geolocation_market_settings' );

	if ( $geolocation_market_settings ) {
		foreach ( $geolocation_market_settings as $option => $value ) {
			add_filter( 'pre_option_' . $option, 'geolocation_settings_pre_option', 10, 3 );
		}
	}
}
