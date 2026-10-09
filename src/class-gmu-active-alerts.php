<?php

class GMUActiveAlerts{
    private $feed;
    private $alertTitle;
    private $alertDescription;
    private $alertLink;
    private $alertPubDate;
    private $showActiveAlert;
    private $cacheDuration;
    private $cachedAlert;

    /**
     * Sets initial property values from plugin settings and loads alert feed data.
     * 
     * @return void
     */
    public function initialize() {
        $settings = $this->gmu_was_emergencyalerts_get_settings();
        $this->feed = $settings[ 'feed_url' ];
        error_log($this->feed);
        $this->showActiveAlert = TRUE;
        $this->cacheDuration = $settings[ 'cache_duration' ];
        $this->loadAlerts();
    }

    private function setAlertTitle( $title ){
        $this->alertTitle = $title;
    }
    private function setAlertDescription( $description ){
        $this->alertDescription = $description;
    }
    private function setAlertLink( $url ){
        $this->alertLink = $url;
    }
    private function setAlertPubDate( $date ){
        $this->alertPubDate = $this->convert_alert_datetime( $date );
    }

    /**
     * Generates html needed for desired alert banner display.
     * Will return an empty string if showActiveAlert is false.
     * 
     * @return string       A string of html
     */
    public function getAlertMarkup() {
        $data_cached_attr = ( $this->cachedAlert ) ? 'yes' : 'no'; 

        $more_info_link = '';
        if ($this->alertLink !== '') {
            $more_info_link = sprintf(
                '<a aria-label="Additional information about the alert" href="%s">More information</a>',
                esc_url( $this->alertLink )
            );
        }

        $displayString = '';
        if ( $this->showActiveAlert ) {
            $displayString = sprintf(
                '<details data-cached="%s" class="gmu-emergency-alert-details"><summary>Mason Alert</summary><div class="gmu-emergency-alert-content"><span id="gmu-emergency-alert-description">%s</span> %s<span class="gmu-emergency-alert-datetime">Last updated: %s</span></div></details>', 
                $data_cached_attr,
                esc_html( $this->alertDescription ),
                $more_info_link,
                $this->alertPubDate
            );
        }

        return $displayString;       
    }

    /**
     * 
     */
    /*
    public function displayAlert() {
        print $this->getAlertMarkup();
    }
    */

    /**
     * Prints an empty html div container that will be replaced with alert.
     * 
     * @return void
     */
    public function displayAlertPlaceholder() {
        print '<div id="gmu-was-emergencyalerts-placeholder" aria-live="polite" aria-atomic="true" hidden></div>';
    }

    /**
     * Passes data to admin-ajax.php that we can use in JavaScript
     * 
     * @return void
     */
    public function ajaxDisplayAlert() {
        $this->initialize();

        if ( !$this->showActiveAlert ) {
            wp_send_json_success(
                [
                    'active'    => false,
                    'html'      => '',
                ]
            );
        }

        wp_send_json_success( 
            [
                'active'    => true,
                'html'      => $this->getAlertMarkup(),
            ] 
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
    public function loadAlerts() {

        $alert_title = '';
        $alert_description = '';
        $alert_link = '';

        $transient_key = 'gmu_wos_emergencyalerts_feed';
        $lock_key      = 'gmu_wos_emergencyalerts_feed_refresh';
        $lock_group    = 'gmu_emergency_alerts';
        $lock_duration = 15;

        // First, check for existing transient.
        $cached_data = get_transient($transient_key);

        // Transient data found; use that to populate banner.
        if ($cached_data !== false) { 
            if ( is_array($cached_data) ){
                $this->cachedAlert = TRUE;
                $this->setAlertTitle($cached_data['title']);
                $this->setAlertDescription($cached_data['description']);
                $this->setAlertLink($cached_data['url']);
                $this->setAlertPubDate($cached_data['pubdate']);
                $this->gmu_alerts_show_hide($cached_data['description']);
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

        if ( !$lock_acquired ) {
            //A request is already refreshing the feed; Do not make another remote request.
            $this->showActiveAlert = false;
            return;
        }

        try {
            $this->cachedAlert = FALSE;
            $feed_response = wp_remote_get(
                $this->feed, 
                array('timeout' => 5)
            );

            // Hide the banner if we get an error while retrieving it.
            if ( is_wp_error( $feed_response ) ) {
                $this->showActiveAlert = false;
                return;
            }

            // Hide the banner if we get a status other than 200.
            $response_code = wp_remote_retrieve_response_code( $feed_response );
            if ( $response_code !== 200 ) {
                $this->showActiveAlert = false;
                return;
            }
            
            $feed_body = wp_remote_retrieve_body( $feed_response );

            $xml_data = simplexml_load_string( $feed_body );

            if( $xml_data === false || !isset( $xml_data->channel->item[0] ) ) {
                $this->showActiveAlert = FALSE;
                return;
            }

            $feed_item = $xml_data->channel->item[0];

            $alert = [
                'title'       => (string) $feed_item->title,
                'description' => (string) $feed_item->description,
                'url'         => (string) $feed_item->link,
                'pubdate'     => (string) $feed_item->pubDate,
            ];

            $this->setAlertTitle( $alert['title'] );
            $this->setAlertDescription( $alert['description'] );
            $this->setAlertLink( $alert['url'] );
            $this->setAlertPubDate( $alert['pubdate'] );

            // Show/hide alert based on description value.
            $this->gmu_alerts_show_hide((string) $feed_item->description);

            // "cache" the alert.
            set_transient($transient_key, $alert, $this->cacheDuration);

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
    private function gmu_alerts_show_hide($string_to_compare){
        if( trim( strtolower($string_to_compare) ) === 'allclear'){
            $this->showActiveAlert = FALSE;
        } else {
            $this->showActiveAlert = TRUE;
        }
     }

     /**
      * Convert feed date (GMT) to current time zone and reformat.
      * @param string $datetime_string      A date in string format (from RSS).
      *
      * @return string                      The date/time in a format we choose.
      */
     private function convert_alert_datetime($datetime_string){
        $datetime = new DateTime( $datetime_string, new DateTimeZone('UTC') );
        $datetime->setTimezone( wp_timezone() );
        $datetime_formatted = $this->format_am_pm( $datetime->format('m/d/Y g:i a') );
        return $datetime_formatted;
     }

    /**
     * Add periods to am/pm in time output.
     * 
     * @param string $date  The formatted date string.
     * 
     * @return string       The formatted date with periods in am/pm.
     */
    private function format_am_pm($date){
        $date = str_replace('am', 'a.m.', $date);
        $date = str_replace('pm', 'p.m.', $date);
        return $date;
    }

    /**
     * Appends time-based querystring to feed url.
     * (Use only if we find that a cached feed is being served)
     * 
     * @param string $url
     * 
     * @return string   The url with a querystring parameter.
     */
    private function gmu_alerts_uncached_feed($url) {
        $uncached_url = $url . ( str_contains($url, '?') ? '&' : '?' ) . 't=' . time();
        return $uncached_url;
    }

    /**
     * Loads the CSS and JS assets and passes PHP data to JavaScript.
     * 
     * @return void
     */
    public function enqueueAssets() {
        wp_enqueue_style( 'emergency-alerts', plugin_dir_url(__DIR__). 'assets/css/emergencyAlerts.css', array(), '1.0' );
        wp_enqueue_script( 'emergency-alerts', plugin_dir_url(__DIR__). 'assets/js/emergencyAlerts.js', array(), '1.0.1', TRUE );
        wp_localize_script( 
            'emergency-alerts', 
            'gmuEmergencyAlerts', 
            array(
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'action'    => 'gmu_get_active_alert',
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
            get_option( 'gmu_was_emergencyalerts_settings', [] ),
            [
                'feed_url' => '',
                'cache_duration'   => 30,
            ]
        );
    }

}

?>