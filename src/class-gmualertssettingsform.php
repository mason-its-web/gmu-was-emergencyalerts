<?php
/**
 * Creates a plugin settings form.
 *
 * @package GMU_WAS_EMERGENCYALERTS
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates the plugin settings form and processes form submissions.
 */
class GMUAlertsSettingsForm {

	/**
	 * Add the options page.
	 */
	public function gmu_was_emergencyalerts_add_settings_page() {
		add_options_page(
			'GMU Emergency Alerts Settings',
			'GMU Emergency Alerts Settings',
			'manage_options',
			'gmu-was-emergencyalerts',
			array( $this, 'gmu_was_emergencyalerts_render_settings_page' )
		);
	}

	/**
	 * Register the settings and add settings sections and fields.
	 */
	public function gmu_was_emergencyalerts_register_settings() {

		register_setting(
			'gmu_was_emergencyalerts_settings_group',
			'gmu_was_emergencyalerts_settings',
			array(
				'sanitize_callback' => array( $this, 'gmu_was_emergencyalerts_sanitize_settings' ),
				'default'           => array(
					'feed_url'       => 'https://content.getrave.com/rss/gmu/channel6',
					'cache_duration' => 30,
				),
			)
		);

		add_settings_section(
			'gmu_was_emergencyalerts_general_section',
			'General Settings',
			'__return_false',
			'gmu-was-emergencyalerts'
		);

		add_settings_field(
			'feed_url',
			'Feed URL',
			array( $this, 'gmu_was_emergencyalerts_feed_url_field' ),
			'gmu-was-emergencyalerts',
			'gmu_was_emergencyalerts_general_section'
		);

		add_settings_field(
			'cache_duration',
			'Cache duration',
			array( $this, 'gmu_was_emergencyalerts_cache_duration_field' ),
			'gmu-was-emergencyalerts',
			'gmu_was_emergencyalerts_general_section'
		);
	}

	/**
	 * Generate markup for feed url field.
	 *
	 * @return void
	 */
	public function gmu_was_emergencyalerts_feed_url_field() {
		$options = $this->gmu_was_emergencyalerts_get_settings();
		printf( '<input placeholder="Feed URL" type="url" name="gmu_was_emergencyalerts_settings[feed_url]" value="%s" class="regular-text" />', $options['feed_url'] );
		print( '<p class="description">The feed that will be checked for alerts</p>' );
	}

	/**
	 * Generate markup for cache duration field.
	 *
	 * @return void
	 */
	public function gmu_was_emergencyalerts_cache_duration_field() {
		$options = $this->gmu_was_emergencyalerts_get_settings();
		printf( '<input placeholder="time (in seconds)" type="text" name="gmu_was_emergencyalerts_settings[cache_duration]" value="%s" class="regular-text" />', $options['cache_duration'] );
		print( '<p class="description">Wait time (in seconds) before the cached alert expires and the live feed is checked again.</p>' );
	}

	/**
	 * Retrieve option values.
	 *
	 * @return array An array of values.
	 */
	public function gmu_was_emergencyalerts_get_settings() {
		return wp_parse_args(
			get_option( 'gmu_was_emergencyalerts_settings', array() ),
			array(
				'feed_url'       => 'https://content.getrave.com/rss/gmu/channel6',
				'cache_duration' => 30,
			)
		);
	}

	/**
	 * Sanitize form input.
	 *
	 * @param array $input An array of form values.
	 *
	 * @return array Sanitized settings values.
	 */
	public function gmu_was_emergencyalerts_sanitize_settings( $input ) {

		if ( empty( $input['feed_url'] ) ) {
			add_settings_error(
				'gmu_was_emergencyalerts_settings',
				'missing_feed_url',
				__( 'The URL field is required.', 'my-plugin' )
			);
		} else {
			$url = trim( $input['feed_url'] );
			if (
				! filter_var( $url, FILTER_VALIDATE_URL ) ||
				'https' !== wp_parse_url( $url, PHP_URL_SCHEME )
			) {
				add_settings_error(
					'gmu_was_emergencyalerts_settings',
					'invalid_feed_url',
					__( 'Please enter a valid HTTPS URL including the https:// prefix.', 'gmu-was-emergencyalerts' )
				);
			} else {
				$output['feed_url'] = esc_url_raw( $url );
			}
		}

		$output['cache_duration'] = absint( $input['cache_duration'] ?? 30 );

		return $output;
	}

	/**
	 * Generate the markup for the settings page/form.
	 *
	 * @return void
	 */
	public function gmu_was_emergencyalerts_render_settings_page() {
		?>
		<div class="wrap">
			<h1>GMU Emergency Alerts Settings</h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'gmu_was_emergencyalerts_settings_group' );
				do_settings_sections( 'gmu-was-emergencyalerts' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Adds a settings link to the plugins list page.
	 *
	 * @param array $actions The default list of plugin actions.
	 *
	 * @return array A group of actions below the plugin name in the list.
	 */
	public function gmu_was_emergencyalerts_plugin_action_links( $actions ) {
		$links   = array(
			'<a href="' . admin_url( 'options-general.php?page=gmu-was-emergencyalerts' ) . '">Settings</a>',
		);
		$actions = array_merge( $actions, $links );
		return $actions;
	}
}
