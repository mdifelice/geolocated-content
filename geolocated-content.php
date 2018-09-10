<?php
/**
 * Plugin Name: Geolocated Content
 * Description: Allows to deliver different content to visitors from different locations.
 * Plugin URI:  https://github.com/mdifelice/geolocated-content
 * Author:      Martin Di Felice
 * Author URI:  https://github.com/mdifelice
 * Text Domain: geolocated-content
 * Domain Path: /languages
 * Version:     0.1.0
 * License:     GPL2
 *
 * Geolocated Content is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 2 of the License, or any later version.
 *
 * Geolocated Content is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with
 * Geolocated Content. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
 *
 * @package Geolocated_Content
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/classes/class-geolocated-content-location-link-widget.php';
require_once __DIR__ . '/classes/class-geolocated-content-location-list-widget.php';
require_once __DIR__ . '/classes/class-geolocated-content-redirect.php';
require_once __DIR__ . '/classes/class-geolocated-content-walker-location-checklist.php';
require_once __DIR__ . '/jetpack.php';
require_once __DIR__ . '/redirection.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/shortcodes.php';
require_once __DIR__ . '/template.php';
require_once __DIR__ . '/widgets.php';

add_action(
	'admin_menu', function() {
		add_options_page(
			__( 'Geolocated Content', 'geolocated-content' ),
			__( 'Geolocated Content', 'geolocated-content' ),
			'manage_options',
			'geolocated-content',
			function() {
			?>
<div class="wrap">
	<h1><?php esc_html_e( 'Geolocated Content', 'geolocated-content' ); ?></h1>
	<form method="post" action="options.php">
			<?php
			settings_fields( 'geolocated-content' );
			do_settings_sections( 'geolocated-content' );
			submit_button();
			?>
	</form>
</div>
			<?php
			}
		);
	}
);

add_action(
	'admin_init', function() {
		add_settings_section(
			'geolocated-content',
			__( 'Settings', 'geolocated-content' ),
			null,
			'geolocated-content'
		);

		add_settings_field(
			'geolocated_content_default_location_id',
			__( 'Default location', 'geolocated-content' ),
			function() {
				$locations = geolocated_content_get_locations();

				if ( empty( $locations ) ) {
					printf(
						'<p class="description">' .
						wp_kses(
							// translators: locations admin page.
							__( 'There are no locations yet. Click <a href="%s">here</a> to add your first location.', 'geolocated-content' ),
							array(
								'a' => array(
									'href' => true,
								),
							)
						) .
						'</p>',
						esc_attr( admin_url( 'edit-tags.php?taxonomy=location' ) )
					);
				} else {
					geolocated_content_dropdown(
						array(
							'name'     => 'geolocated_content_default_location_id',
							'selected' => get_option( 'geolocated_content_default_location_id' ),
						)
					);

					printf(
						'<p class="description">%s</p>',
						esc_html__( 'Allows to define a default location. If a post is not assigned to a particular location, it will be assigned to this location. When a visitor enters the website and they do not belong to a specific location, the visitor will see content only from the default location.', 'geolocated-content' )
					);
				}
			},
			'geolocated-content',
			'geolocated-content'
		);

		add_settings_field(
			'geolocated_content_rewrite_slug',
			__( 'Location slug', 'geolocated-content' ),
			function() {
				printf(
					'<input type="text" class="widefat" name="geolocated_content_rewrite_slug" value="%s" />',
					esc_attr( get_option( 'geolocated_content_rewrite_slug' ) )
				);

				printf(
					'<p class="description">%s</p>',
					wp_kses(
						sprintf(
							// translators: permalink admin page.
							__( 'You may change the slug that will be used for location archive pages. By default it will be the string <code>location</code>. Note: remember to flush rewrite rules after changing this value, you can do it <a href="%s">here</a>.', 'geolocated-content' ),
							admin_url( 'options-permalink.php' )
						),
						array(
							'a'    => array(
								'href' => true,
							),
							'code' => array(),
						)
					)
				);
			},
			'geolocated-content',
			'geolocated-content'
		);

		register_setting(
			'geolocated-content',
			'geolocated_content_default_location_id',
			function( $value ) {
				$locations = geolocated_content_get_locations( true );
				$value     = absint( $value );

				if ( ! isset( $locations[ $value ] ) ) {
					$value = false;
				}

				return $value;
			}
		);

		register_setting(
			'geolocated-content',
			'geolocated_content_rewrite_slug',
			'sanitize_file_name'
		);
	}
);

/**
 * Checks if a post has no location associated, and if there is a default
 * location, it associates it with the post. On the other hand, it checks that
 * if a post has one location associated, which is not the default location, the
 * default location do not be associated with that post.
 */
add_action(
	'save_post', function( $post_id, $post ) {
		$default_location_id = geolocated_content_get_default_location_id();

		$skip = false;

		if ( ! $default_location_id ) {
			$skip = true;
		} elseif ( wp_is_post_revision( $post_id ) ) {
			$skip = true;
		} elseif ( ! in_array( 'location', get_object_taxonomies( $post->post_type ), true ) ) {
			$skip = true;
		}

		if ( ! $skip ) {
			$locations = wp_get_post_terms(
				$post_id,
				'location',
				array(
					'fields' => 'ids',
				)
			);

			if ( empty( $locations ) ) {
				$locations = array( $default_location_id );
			} else {
				$count_locations = count( $locations );

				for ( $i = 0; $i < $count_locations; $i++ ) {
					if ( $locations[ $i ] === $default_location_id && count( $locations ) > 1 ) {
						array_splice( $locations, $i, 1 );

						break;
					}
				}
			}

			wp_set_post_terms( $post_id, $locations, 'location' );
		}
	}, 10, 2
);

/**
 * Shows location filter in administrator post lists.
 */
add_action(
	'restrict_manage_posts', function() {
		global $typenow, $wp_query;

		if ( in_array( 'location', get_object_taxonomies( $typenow ), true ) ) {
			$location_id = null;

			if ( isset( $_GET['geolocated_content_location_id'] ) ) {
				$location_id = absint( $_GET['geolocated_content_location_id'] ); // WPCS: CSRF ok.
			}

			geolocated_content_dropdown(
				array(
					'selected' => $location_id,
				)
			);
		}
	}
);

/**
 * Cleans the location cache whenever a location is modified or when the default
 * location changes.
 */
add_action( 'created_location', 'geolocated_content_clean_locations_cache' );
add_action( 'edited_location', 'geolocated_content_clean_locations_cache' );
add_action( 'deleted_location', 'geolocated_content_clean_locations_cache' );
add_action( 'update_option_geolocated_content_default_location_id', 'geolocated_content_clean_locations_cache' );
/**
 * It uses a low priority in order to associate the location taxonomy with all
 * the possible registered custom post types.
 */
add_action(
	'init', function() {
		load_theme_textdomain( 'geolocated-content', __DIR__ . '/languages' );

		$labels = array(
			'name'              => __( 'Locations', 'geolocated-content' ),
			'singular_name'     => __( 'Location', 'geolocated-content' ),
			'search_items'      => __( 'Search Locations', 'geolocated-content' ),
			'all_items'         => __( 'All Locations', 'geolocated-content' ),
			'parent_item'       => __( 'Parent Location', 'geolocated-content' ),
			'parent_item_colon' => __( 'Parent Location:', 'geolocated-content' ),
			'edit_item'         => __( 'Edit Location', 'geolocated-content' ),
			'update_item'       => __( 'Update Location', 'geolocated-content' ),
			'add_new_item'      => __( 'Add New Location', 'geolocated-content' ),
			'new_item_name'     => __( 'New Location Name', 'geolocated-content' ),
			'menu_name'         => __( 'Locations', 'geolocated-content' ),
		);

		$args = array(
			'labels'            => $labels,
			'hierarchical'      => true,
			'public'            => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array(
				'slug' => geolocated_content_get_rewrite_slug(),
			),
			'capabilities'      => array(
				'manage_terms' => 'manage_options',
				'edit_terms'   => 'manage_options',
				'delete_terms' => 'manage_options',
				'assign_terms' => 'edit_posts',
			),
		);

		/**
	 * We register the taxonomy for each custom post type and we add the 'post'
	 * post type to that list.
	 *
	 * Pages are not included here because usually they are not part of
	 * archives, but they can be included by using the filter.
	 */
		$post_types = get_post_types(
			array(
				'_builtin' => false,
			)
		);

		$post_types[] = 'post';

		$post_types = apply_filters( 'geolocated_content_post_types', $post_types );

		register_taxonomy( 'location', $post_types, $args );
	}, 999
);

add_action(
	'pre_get_posts', function( $query ) {
		/**
	 * We don't want to execute this filter on XML-RPC nor WP_CLI requests.
	 * We neither want to execute on the location archive page (only for
	 * the main query), because that page is already executing filtering the
	 * content.
	 */
		$skip = false;

		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			$skip = true;
		} elseif ( defined( 'WP_CLI' ) && WP_CLI ) {
			$skip = true;
		} else {
			$post_type = $query->get( 'post_type' );

			if ( ! empty( $post_type ) ) {
				$taxonomies = get_object_taxonomies( $post_type );

				if ( ! in_array( 'location', $taxonomies, true ) ) {
					$skip = true;
				}
			}
		}

		if ( ! $skip ) {
			if ( is_tax( 'location' ) && $query->is_main_query() ) {
				/**
			 * Since the location taxonomy is associated with several post
			 * type, in the location archive main query we want post post types.
			 */
				$query->set( 'post_type', 'post' );
			} else {
				$is_admin         = geolocated_content_is_admin();
				$location_ids     = array();
				$operator         = 'IN';
				$locations_not_in = $query->get( 'location__not_in' );
				$locations_in     = $query->get( 'location__in' );

				/**
			 * If locations are explicity set in the query we will use them.
			 * Locations not included have precedence over locations included.
			 */
				if ( $locations_not_in || $locations_in ) {
					if ( $locations_not_in ) {
						$location_slugs = $locations_not_in;
						$operator       = 'NOT IN';
					} else {
						$location_slugs = $locations_not_in;
					}

					$location_slugs = explode( ',', $location_slugs );

					foreach ( $location_slugs as $location_slug ) {
						$location = get_term_by( 'slug', $location_slug, 'location' );

						if ( $location ) {
							$location_ids[] = $location->term_id;
						}
					}
				} else {
					if ( ! $is_admin ) {
						$location_slug       = geolocated_content_get_visitor_location_slug();
						$default_location_id = geolocated_content_get_default_location_id();

						if ( $default_location_id ) {
							$location_ids[] = $default_location_id;
						}

						if ( ! empty( $location_slug ) ) {
							$location = get_term_by( 'slug', $location_slug, 'location' );

							if ( $location ) {
								$location_ids[] = $location->term_id;
							}
						}
					}
				}

				/**
			 * We apply a filter to $location_ids, in case some script want to
			 * include (or exclude) other locations in the query.
			 */
				$location_ids = apply_filters( 'geolocated_content_pre_get_posts_locations', $location_ids, $query, $operator, $is_admin );

				if ( ! empty( $location_ids ) ) {
					/**
				 * In order to avoid overwriting current tax queries, we check
				 * if there are any before adding our new one.
				 */
					$current_tax_query = $query->get( 'tax_query' );

					if ( ! $current_tax_query ) {
						$current_tax_query = array();
					}

					$current_tax_query[] = array(
						'taxonomy' => 'location',
						'field'    => 'term_id',
						'terms'    => $location_ids,
						'operator' => $operator,
					);

					$query->set( 'tax_query', $current_tax_query );
				}
			}
		}
	}
);

/**
 * When the location archive link is displayed it is replaced with the home page
 * link for that location.
 */
add_filter(
	'term_link', function( $url, $term, $taxonomy ) {
		if ( 'location' === $taxonomy && apply_filters( 'geolocated_content_filter_term_link', true ) ) {
			remove_filter( 'home_url', 'geolocated_content_add_location_to_url' );

			$url = get_home_url();

			$default_location_id = geolocated_content_get_default_location_id();

			if ( ! $default_location_id || $default_location_id !== $term->term_id ) {
				$url .= '/' . $term->slug;
			}

			add_filter( 'home_url', 'geolocated_content_add_location_to_url' );
		}

		return $url;
	}, 10, 3
);

add_filter(
	'body_class', function( $classes ) {
		$location_slug = geolocated_content_get_visitor_location_slug();

		if ( ! $location_slug ) {
			$location_slug = 'default';
		}

		$classes[] = 'geolocated-content-' . sanitize_html_class( $location_slug );

		return $classes;
	}
);

/**
 * Some times, for 404 errors, there was a loop redirection if this filter
 * is not enabled.
 */
add_filter(
	'redirect_canonical', function( $redirect_url ) {
		return is_404() ? false : $redirect_url;
	}
);

add_filter( 'home_url', 'geolocated_content_add_location_to_url' );

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ), function( $links ) {
		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_attr( admin_url( 'options-general.php?page=geolocated-content' ) ),
			esc_html__( 'Settings', 'geolocated-content' )
		);

		return $links;
	}
);

if ( ! geolocated_content_is_admin() ) {
	$geolocated_content_request_uri       = '';
	$geolocated_content_request_uri_path  = '';
	$geolocated_content_request_uri_query = '';

	if ( isset( $_SERVER['REQUEST_URI'] ) ) {
		$geolocated_content_request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );

		$geolocated_content_parsed_request_uri = wp_parse_url( $geolocated_content_request_uri );

		if ( isset( $geolocated_content_parsed_request_uri['path'] ) ) {
			$geolocated_content_request_uri_path = $geolocated_content_parsed_request_uri['path'];
		}

		if ( isset( $geolocated_content_parsed_request_uri['query'] ) ) {
			$geolocated_content_request_uri_query = $geolocated_content_parsed_request_uri['query'];
		}
	}

	/**
	 * Add slash to avoid duplicated URLs. If we don't do this, we will have two
	 * URLs for each request.
	 */
	if ( ! empty( $geolocated_content_request_uri_path ) && ! preg_match( '/(?:(\/wp-json\/|(?:\/|\.php)$))/', $geolocated_content_request_uri_path ) ) {
		header( 'HTTP/1.1 301 Moved permanently' );
		header( 'Location: ' . $geolocated_content_request_uri_path . '/' . ( $geolocated_content_request_uri_query ? '?' . $geolocated_content_request_uri_query : '' ) );

		exit;
	}

	/**
	 * Extract location slug from request to let WordPress parse correctly it.
	 * Right here, full-page cache plugins, like Batcache, are already executed,
	 * so each request will have a different cache.
	 */
	$geolocated_content_location_slug = geolocated_content_extract_location_slug( $geolocated_content_request_uri );

	if ( $geolocated_content_location_slug ) {
		$geolocated_content_modified_request_uri = preg_replace( '/^\/' . preg_quote( $geolocated_content_location_slug, '/' ) . '/', '', $geolocated_content_request_uri, 1 );

		$_SERVER['GEOLOCATION_REQUEST_URI'] = $_SERVER['REQUEST_URI']; // WPCS: sanitization ok.
		$_SERVER['REQUEST_URI']             = $geolocated_content_modified_request_uri;

		do_action( 'geolocated_content_updated_request_uri', $geolocated_content_request_uri, $geolocated_content_modified_request_uri, $geolocated_content_location_slug );
	}

	do_action( 'geolocated_content_init', $geolocated_content_location_slug );
}
