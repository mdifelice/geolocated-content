<?php
/**
 * Prints a list of locations.
 *
 * @package Geolocated_Content
 */

/**
 * Class definition.
 */
class Geolocated_Content_Location_List_Widget extends WP_Widget {
	/**
	 * Widget constructor.
	 */
	public function __construct() {
		parent::__construct( false, __( 'Geolocated Content - Location List', 'geolocated-content' ) );
	}

	/**
	 * Prints widget.
	 *
	 * @param array $args     Sidebar options.
	 * @param array $instance Widget options.
	 */
	public function widget( $args, $instance ) {
		echo $args['before_widget']; // WPCS: XSS ok.

		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title']; // WPCS: XSS ok.
		}

		geolocated_content_template_location_list( array(
			'home' => ! empty( $instance['home'] ),
		) );

		echo $args['after_widget']; // WPCS: XSS ok.
	}

	/**
	 * Prints widget form.
	 *
	 * @param array $instance Widget data.
	 */
	public function form( $instance ) {
		$instance = wp_parse_args(
			$instance,
			array(
				'title' => '',
				'home'  => 'yes',
			)
		);
		?>
<p>
	<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'geolocated-content' ); ?></label>
	<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>" />
</p>
<p>
	<label for="<?php echo esc_attr( $this->get_field_id( 'home' ) ); ?>"><?php esc_html_e( 'Link to location home?:', 'geolocated-content' ); ?></label>
	<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'home' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'home' ) ); ?>" type="checkbox" value="yes"<?php checked( 'yes', $instance['home'] ); ?> />
</p>
		<?php
	}
}
