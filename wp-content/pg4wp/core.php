<?php
/**
 * @package PostgreSQL_For_Wordpress
 * @version $Id$
 * @author	Hawk__, www.hawkix.net
 */

/**
* This file does all the initialisation tasks
*/

// Logs are put in the pg4wp directory
define( 'PG4WP_LOG', PG4WP_ROOT.'/logs/');
// Check if the logs directory is needed and exists or create it if possible
if( (PG4WP_DEBUG || PG4WP_LOG_ERRORS) &&
	!file_exists( PG4WP_LOG) &&
	is_writable(dirname( PG4WP_LOG)))
	mkdir( PG4WP_LOG);

// Load the driver defined in 'db.php'
require_once( PG4WP_ROOT.'/driver_'.DB_DRIVER.'.php');

// This loads up the wpdb class applying appropriate changes to it
$replaces = array(
	'define( '	=> '// define( ',
	'class wpdb'	=> 'class wpdb2',
	'new wpdb'	=> 'new wpdb2',
	'mysql_'	=> 'wpsql_',
	'<?php'		=> '',
	'?>'		=> '',
);
// Ensure class uses the replaced mysql_ functions rather than mysqli_
define( 'WP_USE_EXT_MYSQL', true);
$code = str_replace( array_keys($replaces), array_values($replaces), file_get_contents(ABSPATH.'/wp-includes/class-wpdb.php'));
eval($code);

// Create wpdb object if not already done
if (! isset($wpdb) && defined('DB_USER'))
	$wpdb = new wpdb2( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
