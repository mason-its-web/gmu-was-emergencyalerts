<?php
/**
 * Emergency Alert RSS feed handler.
 *
 * @package GMU_WAS_EMERGENCYALERTS
 * @author GMU ITS Web Services
 */

/**
 * Manages and displays emergency alerts.
 *
 * Loads alert data from the provided RSS feed, caches results to reduce
 * load on feed host, and generates markup for AJAX front-end output
 * (to bypass edge cache)
 */
class GMUActiveAlerts {

	/**
	 * The RSS feed url.
	 *
	 * @var string
	 */
	private $feed;

	/**
	 * The alert title to display.
	 *
	 * @var string
	 */
	private $alert_title;

	/**
	 * The alert description to display.
	 *
	 * @var string
	 */
	private $alert_description;

	/**
	 * The alert link to display.
	 *
	 * @var string
	 */
	private $alert_link;

	/**
	 * The alert publish date to display.
	 *
	 * @var string
	 */
	private $alert_pub_date;

	/**
	 * Determines whether or not the banner should be displayed.
	 *
	 * @var bool
	 */
	private $show_active_alert;

	/**
	 * Length of time in seconds to "cache" the feed data.
	 *
	 * @var int
	 */
	private $cache_duration;

	/**
	 * Indicates whether cached or live data is being used.
	 *
	 * @var bool
	 */
	private $cached_alert;

	/**
	 * Sets initial property values from plugin settings and loads alert feed data.
	 *
	 * @return void
	 */
	public function initialize() {
		$settings                = $this->gmu_was_emergencyalerts_get_settings();
		$this->feed              = $settings['feed_url'];
		$this->cache_duration    = $settings['cache_duration'];
		$this->show_active_alert = true;
		$this->load_alerts();
	}

	/**
	 * Assigns a value to the class $alert_title property.
	 *
	 * @param string $title An alert title value.
	 *
	 * @return void
	 */
	private function set_alert_title( $title ) {
		$this->alert_title = $title;
	}

	/**
	 * Assigns a value to the class $alert_description property.
	 *
	 * @param string $description An alert description value.
	 *
	 * @return void
	 */
	private function set_alert_description( $description ) {
		$this->alert_description = $description;
	}

	/**
	 * Assigns a value to the class $alert_link property.
	 *
	 * @param string $url A url string.
	 *
	 * @return void
	 */
	private function set_alert_link( $url ) {
		$this->alert_link = $url;
	}

	/**
	 * Assigns a value to the class $alert_pub_date property.
	 *
	 * @param string $date A date value in string format.
	 *
	 * @return void
	 */
	private function set_alert_pub_date( $date ) {
		$this->alert_pub_date = $this->convert_alert_datetime( $date );
	}

	/**
	 * Generates html needed for desired alert banner display.
	 * Will return an empty string if show_active_alert is false.
	 *
	 * @return string       A string of html
	 */
	public function get_alert_markup() {
		$data_cached_attr = ( $this->cached_alert ) ? 'yes' : 'no';

		$more_info_link = '';
		if ( '' !== $this->alert_link ) {
			$more_info_link = sprintf(
				'<a aria-label="Additional information about the alert" href="%s">More information</a>',
				esc_url( $this->alert_link )
			);
		}

		$display_string = '';
		if ( $this->show_active_alert ) {
			$display_string = sprintf(
				'<details data-cached="%s" class="gmu-emergency-alert-details"><summary>Mason Alert</summary><div class="gmu-emergency-alert-content"><span id="gmu-emergency-alert-description">%s</span> %s<span class="gmu-emergency-alert-datetime">Last updated: %s</span></div></details>',
				$data_cached_attr,
				esc_html( $this->alert_description ),
				$more_info_link,
				$this->alert_pub_date
			);
		}

		return $display_string;
	}

	/**
	 * Prints an empty html div container that will be replaced with alert.
	 *
	 * @return void
	 */
	public function display_alert_placeholder() {
		print '<div id="gmu-was-emergencyalerts-placeholder" aria-live="polite" aria-atomic="true" hidden></div>';
	}

	/**
	 * Passes data to admin-ajax.php that we can use in JavaScript
	 *
	 * @return void
	 */
	public function ajax_display_alert() {
		$this->initialize();

		if ( ! $this->show_active_alert ) {
			wp_send_json_success(
				array(
					'active' => false,
					'html'   => '',
				)
			);
		}

		wp_send_json_success(
			array(
				'active' => true,
				'html'   => $this->get_alert_markup(),
			)
		);
	}

	/**
	 * Loads and caches the current emergency alert.
	 *
	 * Uses cached alert data when available. On a cache miss, retrieves the
	 * configured feed after acquiring a refresh lock and updates the alert
	 * properties and display status.
	 *
	 * @return void
	 */
	public function load_alerts() {

		$alert_title       = '';
		$alert_description = '';
		$alert_link        = '';

		$transient_key = 'gmu_wos_emergencyalerts_feed';
		$lock_key      = 'gmu_wos_emergencyalerts_feed_refresh';
		$lock_group    = 'gmu_emergency_alerts';
		$lock_duration = 15;

		// First, check for existing transient.
		$cached_data = get_transient( $transient_key );

		// Transient data found; use that to populate banner.
		if ( false !== $cached_data ) {
			if ( is_array( $cached_data ) ) {
				$this->cached_alert = true;
				$this->set_alert_title( $cached_data['title'] );
				$this->set_alert_description( $cached_data['description'] );
				$this->set_alert_link( $cached_data['url'] );
				$this->set_alert_pub_date( $cached_data['pubdate'] );
				$this->gmu_alerts_show_hide( $cached_data['description'] );
			}
			return;
		}

		// Transient not found; fetch live feed data.

		// Create a brief temporary "lock" to prevent overloading the live feed.
		$lock_acquired = wp_cache_add(
			$lock_key,
			1,
			$lock_group,
			$lock_duration
		);

		if ( ! $lock_acquired ) {
			// A request is already refreshing the feed; Do not make another remote request.
			$this->show_active_alert = false;
			return;
		}

		try {
			$this->cached_alert = false;
			$feed_response      = wp_remote_get(
				$this->feed,
				array( 'timeout' => 5 )
			);

			// Hide the banner if we get an error while retrieving it.
			if ( is_wp_error( $feed_response ) ) {
				$this->show_active_alert = false;
				return;
			}

			// Hide the banner if we get a status other than 200.
			$response_code = wp_remote_retrieve_response_code( $feed_response );
			if ( 200 !== $response_code ) {
				$this->show_active_alert = false;
				return;
			}

			$feed_body = wp_remote_retrieve_body( $feed_response );

			$xml_data = simplexml_load_string( $feed_body );

			if ( false === $xml_data || ! isset( $xml_data->channel->item[0] ) ) {
				$this->show_active_alert = false;
				return;
			}

			$feed_item = $xml_data->channel->item[0];

			$alert = array(
				'title'       => (string) $feed_item->title,
				'description' => (string) $feed_item->description,
				'url'         => (string) $feed_item->link,
				'pubdate'     => (string) $feed_item->pubDate,
			);

			$this->set_alert_title( $alert['title'] );
			$this->set_alert_description( $alert['description'] );
			$this->set_alert_link( $alert['url'] );
			$this->set_alert_pub_date( $alert['pubdate'] );

			// Show/hide alert based on description value.
			$this->gmu_alerts_show_hide( (string) $feed_item->description );

			// "cache" the alert.
			set_transient( $transient_key, $alert, $this->cache_duration );

		} finally {
			// Release the lock.
			wp_cache_delete(
				$lock_key,
				$lock_group
			);
		}
	}

	/**
	 * Sets display property to true or false based on alert description value.
	 *
	 * @param string $string_to_compare     The alert description.
	 *
	 * @return void
	 */
	private function gmu_alerts_show_hide( $string_to_compare ) {
		if ( trim( strtolower( $string_to_compare ) ) === 'allclear' ) {
			$this->show_active_alert = false;
		} else {
			$this->show_active_alert = true;
		}
	}

	/**
	 * Convert feed date (GMT) to current time zone and reformat.
	 *
	 * @param string $datetime_string      A date in string format (from RSS).
	 *
	 * @return string                      The date/time in a format we choose.
	 */
	private function convert_alert_datetime( $datetime_string ) {
		$datetime = new DateTime( $datetime_string, new DateTimeZone( 'UTC' ) );
		$datetime->setTimezone( wp_timezone() );
		$datetime_formatted = $this->format_am_pm( $datetime->format( 'm/d/Y g:i a' ) );
		return $datetime_formatted;
	}

	/**
	 * Add periods to am/pm in time output.
	 *
	 * @param string $date  The formatted date string.
	 *
	 * @return string       The formatted date with periods in am/pm.
	 */
	private function format_am_pm( $date ) {
		$date = str_replace( 'am', 'a.m.', $date );
		$date = str_replace( 'pm', 'p.m.', $date );
		return $date;
	}

	/**
	 * Appends time-based querystring to feed url.
	 * (Use only if we find that a cached feed is being served)
	 *
	 * @param string $url A url in string format.
	 *
	 * @return string   The url with a querystring parameter.
	 */
	private function gmu_alerts_uncached_feed( $url ) {
		$uncached_url = $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . 't=' . time();
		return $uncached_url;
	}

	/**
	 * Loads the CSS and JS assets and passes PHP data to JavaScript.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		wp_enqueue_style( 'emergency-alerts', plugin_dir_url( __DIR__ ) . 'assets/css/emergencyAlerts.css', array(), '1.0' );
		wp_enqueue_script( 'emergency-alerts', plugin_dir_url( __DIR__ ) . 'assets/js/emergencyAlerts.js', array(), '1.0.1', true );
		wp_localize_script(
			'emergency-alerts',
			'gmuEmergencyAlerts',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => 'gmu_get_active_alert',
			)
		);
	}

	/**
	 * Retrieves the plugin settings.
	 *
	 * @return array        An array of plugin options.
	 */
	public function gmu_was_emergencyalerts_get_settings() {
		return wp_parse_args(
			get_option( 'gmu_was_emergencyalerts_settings', array() ),
			array(
				'feed_url'       => '',
				'cache_duration' => 30,
			)
		);
	}
}
