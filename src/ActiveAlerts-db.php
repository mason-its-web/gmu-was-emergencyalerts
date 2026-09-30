<?php

class ActiveAlerts{
    protected $feed;
    private $wpdb;
    private $tablePrefix;
    private $dbTable;
    private $alertTitle;
    private $alertDescription;
    private $showActiveAlert;
    private $cacheDuration;

    public function __construct()
    {
        global $table_prefix, $wpdb;
        $this->wpdb = $wpdb;
        $this->tablePrefix = $table_prefix;
    }

    public function initialize() {
        //print('Initialized.');
        $this->dbTable = 'gmu_wos_emergencyalerts';
        $this->feed = 'http://sandboxgmu.local/wp-content/plugins/gmu-wos-emergency-alerts/channel1.xml';
        $this->showActiveAlert = TRUE;
        $this->cacheDuration = 3600;
        $this->loadAlerts();
    }

    private function setAlertTitle($title){
        $this->alertTitle = $title;
    }
    private function setAlertDescription($description){
        $this->alertDescription = $description;
    }
    public function getAlertTitle(){
        return $this->alertTitle;
    }
    public function getAlertDescription(){
        return $this->alertDescription;
    }

    public function displayAlert(){
        $displayString = ($this->showActiveAlert) ? sprintf('<details class="gmu-emergency-alert-details"><summary>%s</summary><div class="gmu-emergency-alert-content">%s</div></details>', $this->getAlertTitle(), $this->getAlertDescription()) : '';
        print $displayString;       
    }

    public function loadAlerts() {
        $table = $this->dbTable;
        $alert_title = '';
        $alert_description = '';
        $alert_link = '';

        // First, check database and get most recent alert
        $alert_query = $this->wpdb->prepare("SELECT * FROM {$table} ORDER BY alert_date DESC LIMIT %d", 1);
        $alerts = $this->wpdb->get_results($alert_query);
        if( $alerts ) { // An alert was found in db (query should only pull one).
            $latest_alert = $alerts[0];
            $timezone = new DateTimeZone('UTC');
            $dt_now = new DateTime('now');
            $dt_alert = new DateTime($latest_alert->alert_date);
            $alert_title = $latest_alert->alert_title . ' (cached)';
            $alert_description = $latest_alert->alert_description;
            $dt_diff = $dt_now->getTimestamp() - $dt_alert->getTimestamp();
        }

        // If timestamp on most recent alert is more than a minute(2? 5?) old, check the feed.
        // If the feed contains an active alert, display it and insert it into the database
        // If we're inside the time window, display alert from database

        if($dt_diff > $this->cacheDuration){ 
            // Get "live" version from feed.
            $xmlData = simplexml_load_file($this->feed);
            if(! empty($xmlData)) {
                $i=0;
                foreach($xmlData->channel->item as $feedItem) {
                    $i++;
                    if($feedItem->description != 'allclear') {
                        $this->showActiveAlert = TRUE;
                        $alert_title = $feedItem->title;
                        $alert_description = $feedItem->description;
                        // Insert alert into database "cache".
                    } else { 
                        // All clear, hide alert banner.
                        $this->showActiveAlert = FALSE;
                    }
                }
            }
        }

        $this->setAlertTitle($alert_title);
        $this->setAlertDescription($alert_description);

        if(!is_admin() && strpos( $_SERVER['REQUEST_URI'], '/wp-json/' ) === false ) {
            static $executed = false;
            if($executed) {
                return;
            }
            $executed = true;
        }
    }

    public function alerts_admin_menu() {
        $title = __( 'GMU Emergency Alert Settings', 'gmu-wos-emergency-alerts' );
    }

    /**
     * Loads the CSS and JS (if needed) assets.
     */
    public function enqueueAssets() {
        wp_enqueue_style('alert_css', plugin_dir_url(__DIR__). 'assets/css/alerts.css', array(), '1.0');
        wp_enqueue_script('alert_js', plugin_dir_url(__DIR__). 'assets/js/AnimatedDetails.js', array(), '1.0');
    }

    public function activate_alert_plugin() {
        //Activation code in here
        $this->init_alert_db();
    }
    public function db_creation_notice() {
        //admin notice here.
    }
    public function init_alert_db() {
        // Create table if it doesn't exist
        if( $this->wpdb->get_var( "show tables like '$this->dbTable'" ) != $this->dbTable ) {
            $sql = "CREATE TABLE `$this->dbTable` (";
            $sql .= "`timestamp` datetime NOT NULL, ";
            $sql .= "`alert_date` datetime NOT NULL, ";
            $sql .= "`alert_title` varchar(500) NOT NULL, ";
            $sql .= "`alert_description` varchar(2500) NOT NULL, ";
            $sql .= "`alert_link` varchar(255) NOT NULL";
            $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=latin1;";

            require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );

            dbDelta( $sql );
        }
        else {
            add_action( 'admin_notices', array($this, 'db_creation_notice' ));
        }
    }


}

?>