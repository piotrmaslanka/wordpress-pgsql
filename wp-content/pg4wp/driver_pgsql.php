<?php
/**
 * @package PostgreSQL_For_Wordpress
 * @version $Id$
 * @author	Hawk__, www.hawkix.net
 */

/**
* Provides a driver for PostgreSQL
*
* This file maps original mysql_* functions with PostgreSQL equivalents
*
* This was originally based on usleepless's original 'mysql2pgsql.php' file, many thanks to him
*/
	// Check pgsql extension is loaded
	if ( !extension_loaded('pgsql') )
		wp_die( 'Your PHP installation appears to be missing the PostgreSQL extension which is required by WordPress with PG4WP.' );

	// Initializing some variables
	$GLOBALS['pg4wp_version'] = '7.0';
	$GLOBALS['pg4wp_result'] = 0;
	$GLOBALS['pg4wp_numrows_query'] = '';
	$GLOBALS['pg4wp_ins_table'] = '';
	$GLOBALS['pg4wp_ins_field'] = '';
	$GLOBALS['pg4wp_last_insert'] = '';
	$GLOBALS['pg4wp_connstr'] = '';
	$GLOBALS['pg4wp_conn'] = false;

	function wpsql_ping($conn)
		{ return pg_ping($conn); }
	function wpsql_num_rows($result)
		{ return pg_num_rows($result); }
	function wpsql_numrows($result)
		{ return pg_num_rows($result); }
	function wpsql_num_fields($result)
		{ return pg_num_fields($result); }
	function wpsql_fetch_field($result)
		{ return 'tablename'; }
	function wpsql_fetch_object($result)
		{ return pg_fetch_object($result); }
	function wpsql_free_result($result)
		{ return pg_free_result($result); }
	function wpsql_affected_rows()
	{
		if( $GLOBALS['pg4wp_result'] === false)
			return 0;
		else
			return pg_affected_rows($GLOBALS['pg4wp_result']);
	}
	function wpsql_fetch_row($result)
		{ return pg_fetch_row($result); }
	function wpsql_data_seek($result, $offset)
		{ return pg_result_seek ( $result, $offset ); }
	function wpsql_error()
		{ if( $GLOBALS['pg4wp_conn']) return pg_last_error(); else return ''; }
	function wpsql_fetch_assoc($result) { return pg_fetch_assoc($result); }
	function wpsql_escape_string($s) { return pg_escape_string($s); }
	function wpsql_real_escape_string($s,$c=NULL) { return pg_escape_string($s); }
	function wpsql_get_server_info() { return '5.0.30'; } // Just want to fool wordpress ...
	
/**** Modified version of wpsql_result() is at the bottom of this file
	function wpsql_result($result, $i, $fieldname)
		{ return pg_fetch_result($result, $i, $fieldname); }
****/

	function wpsql_connect($dbserver, $dbuser, $dbpass)
	{
		$GLOBALS['pg4wp_connstr'] = '';
		$hostport = explode(':', $dbserver);
		if( !empty( $hostport[0]))
			$GLOBALS['pg4wp_connstr'] .= ' host='.$hostport[0];
		if( !empty( $hostport[1]))
			$GLOBALS['pg4wp_connstr'] .= ' port='.$hostport[1];
		if( !empty( $dbuser))
			$GLOBALS['pg4wp_connstr'] .= ' user='.$dbuser;
		if( !empty( $dbpass))
			$GLOBALS['pg4wp_connstr'] .= ' password='.$dbpass;
		elseif( !PG4WP_INSECURE)
			wp_die( 'Connecting to your PostgreSQL database without a password is considered insecure.
					<br />If you want to do it anyway, please set "PG4WP_INSECURE" to true in your "db.php" file.' );

		// PostgreSQL must connect to a specific database (unlike MySQL)
		// Guess at one here and reconnect as required in wpsql_select_db
		$dbname = defined('DB_NAME') && DB_NAME ? DB_NAME : 'template1';
		return pg_connect( $GLOBALS['pg4wp_connstr'].' dbname='.$dbname);
	}
	
	// The effective connection happens here
	function wpsql_select_db($dbname, $connection_id = 0)
	{
		$pg_connstr = $GLOBALS['pg4wp_connstr'].' dbname='.$dbname;

		// Note:  pg_connect returns existing connection for same connstr
		$GLOBALS['pg4wp_conn'] = $conn = pg_connect($pg_connstr);

		if( !$conn)
			return $conn;

		$ver = pg_version($conn);
		if( isset($ver['server']))
			$GLOBALS['pg4wp_version'] = $ver['server'];

		// Now we should be connected, we "forget" about the connection parameters (if this is not a "test" connection)
		if( !defined('WP_INSTALLING') || !WP_INSTALLING)
			$GLOBALS['pg4wp_connstr'] = '';
		
		// Execute early transmitted commands if needed
		if( !empty($GLOBALS['pg4wp_pre_sql']))
			foreach( $GLOBALS['pg4wp_pre_sql'] as $sql2run)
				wpsql_query( $sql2run);
		
		pg4wp_init($conn);

		return $conn;
	}

	function wpsql_fetch_array($result)
	{
		$res = pg_fetch_array($result);
		
		if( is_array($res) )
		foreach($res as $v => $k )
			$res[$v] = trim($k);
		return $res;
	}
	
	function wpsql_query($sql)
	{
		if( !$GLOBALS['pg4wp_conn'])
		{
			// Catch SQL to be executed as soon as connected
			$GLOBALS['pg4wp_pre_sql'][] = $sql;
			return true;
		}
		
		$initial = $sql;
		$sql = pg4wp_rewrite( $sql);
		
		$GLOBALS['pg4wp_result'] = @pg_query($sql);
		if( (PG4WP_DEBUG || PG4WP_LOG_ERRORS) && $GLOBALS['pg4wp_result'] === false && $err = pg_last_error())
		{
			$ignore = false;
			if( defined('WP_INSTALLING') && WP_INSTALLING)
			{
				global $table_prefix;
				$ignore = strpos($err, 'relation "'.$table_prefix);
			}
			if( ! $ignore )
				error_log('['.microtime(true)."] Error running :\n$initial\n---- converted to ----\n$sql\n----> $err\n---------------------\n", 3, PG4WP_LOG.'pg4wp_errors.log');
		}
		return $GLOBALS['pg4wp_result'];
	}
	
	function wpsql_insert_id($lnk = NULL)
	{
		global $wpdb;
		$ins_field = $GLOBALS['pg4wp_ins_field'];
		$table = $GLOBALS['pg4wp_ins_table'];
		$lastq = $GLOBALS['pg4wp_last_insert'];
		
		$seq = $table . '_seq';
		
		// Table 'term_relationships' doesn't have a sequence
		if( $table == $wpdb->term_relationships)
		{
			$sql = 'NO QUERY';
			$data = 0;
		}
		// When using WP_Import plugin, ID is defined in the query
		elseif('post_author' == $ins_field && false !== strpos($lastq,'ID'))
		{
			$sql = 'ID was in query ';
			$pattern = '/.+\'(\d+).+$/';
			preg_match($pattern, $lastq, $matches);
			$data = $matches[1];
			// We should update the sequence on the next non-INSERT query
			$GLOBALS['pg4wp_queued_query'] = "SELECT SETVAL('$seq',(SELECT MAX(\"ID\") FROM $table)+1);";
		}
		else
		{
			$sql = "SELECT CURRVAL('$seq')";
			
			$res = pg_query($sql);
			if( false !== $res)
				$data = pg_fetch_result($res, 0, 0);
			elseif( PG4WP_DEBUG || PG4WP_ERROR_LOG)
			{
				$log = '['.microtime(true)."] wpsql_insert_id() was called with '$table' and '$ins_field'".
						" and returned the error:\n".pg_last_error().
						"\nFor the query:\n".$sql.
						"\nThe latest INSERT query was :\n'$lastq'\n";
				error_log( $log, 3, PG4WP_LOG.'pg4wp_errors.log');
			}
		}
		if( PG4WP_DEBUG && $sql)
			error_log( '['.microtime(true)."] Getting inserted ID for '$table' ('$ins_field') : $sql => $data\n", 3, PG4WP_LOG.'pg4wp_insertid.log');
			
		return $data;
	}
	
	// Convert MySQL FIELD function to CASE statement
	// https://dev.mysql.com/doc/refman/5.7/en/string-functions.html#function_field
	// Other implementations:  https://stackoverflow.com/q/1309624
	function pg4wp_rewrite_field($matches)
	{
		$case = 'CASE ' . trim($matches[1]);
		$comparands = explode(',', $matches[2]);
		foreach($comparands as $i => $comparand) {
			$case .= ' WHEN ' . trim($comparand) . ' THEN ' . ($i + 1);
		}
		$case .= ' ELSE 0 END';
		return $case;
	}

	function pg4wp_rewrite( $sql)
	{
		// Note:  Can be called from constructor before $wpdb is set
		global $wpdb;
		
		$logto = 'queries';
		// The end of the query may be protected against changes
		$end = '';
		
		// Remove unusefull spaces
		$initial = $sql = trim($sql);
		
		if( 0 === strpos($sql, 'SELECT'))
		{
			$logto = 'SELECT';
			// SQL_CALC_FOUND_ROWS doesn't exist in PostgreSQL but it's needed for correct paging
			if( false !== strpos($sql, 'SQL_CALC_FOUND_ROWS'))
			{
				$sql = str_replace('SQL_CALC_FOUND_ROWS', '', $sql);
				$GLOBALS['pg4wp_numrows_query'] = $sql;
				if( PG4WP_DEBUG)
					error_log( '['.microtime(true)."] Number of rows required for :\n$sql\n---------------------\n", 3, PG4WP_LOG.'pg4wp_NUMROWS.log');
			}
			elseif( false !== strpos($sql, 'FOUND_ROWS()'))
			{
				// Here we convert the latest query into a COUNT query
				$sql = $GLOBALS['pg4wp_numrows_query'];
				// Remove any LIMIT ... clause (this is the blocking part)
				$pattern = '/\s+LIMIT.+/';
				$sql = preg_replace( $pattern, '', $sql);
				// Now add the COUNT() statement
				$pattern = '/SELECT\s+([^\s]+)\s+(FROM.+)/';
				$sql = preg_replace( $pattern, 'SELECT COUNT($1) $2', $sql);
			}

			// Ensure that ORDER BY column appears in SELECT DISTINCT fields
			$pattern = '/^SELECT DISTINCT.*ORDER BY\s+(\S+)/';
			if( preg_match( $pattern, $sql, $matches) &&
					strpos( $sql, $matches[1]) > strpos( $sql, 'ORDER BY') &&
					false === strpos( $sql, '*'))
			{
				if( false !== strpos( $sql, 'GROUP BY'))
				{
					$pattern = '/ FROM /';
					$sql = preg_replace( $pattern, ', MIN('.$matches[1].') AS '.$matches[1].' FROM ', $sql, 1);
				}
				else
				{
					$pattern = '/ FROM /';
					$sql = preg_replace( $pattern, ', '.$matches[1].' FROM ', $sql, 1);
				}
			}

			// Convert CONVERT to CAST
			$pattern = '/CONVERT\(([^()]*(\(((?>[^()]+)|(?-2))*\))?[^()]*),\s*([^\s]+)\)/x';
			$sql = preg_replace( $pattern, 'CAST($1 AS $4)', $sql);
			
			// Handle CAST( ... AS CHAR)
			$sql = preg_replace( '/CAST\((.+) AS CHAR\)/', 'CAST($1 AS TEXT)', $sql);

			// Handle CAST( ... AS SIGNED)
			$sql = preg_replace( '/CAST\((.+) AS SIGNED\)/', 'CAST($1 AS INTEGER)', $sql);
			
			// Handle COUNT(*)...ORDER BY...
			$sql = preg_replace( '/COUNT(.+)ORDER BY.+/s', 'COUNT$1', $sql);
			
			// In order for users counting to work...
			$matches = array();
			if( preg_match_all( '/COUNT[^C]+\),/',$sql, $matches))
			{
				foreach( $matches[0] as $num => $one)
				{
					$sub = substr( $one, 0, -1);
					$sql = str_replace( $sub, $sub.' AS count'.$num, $sql);
				}
			}
			
			$pattern = '/LIMIT[ ]+(\d+),[ ]*(\d+)/';
			$sql = preg_replace($pattern, 'LIMIT $2 OFFSET $1', $sql);
			
			$pattern = '/DATE_ADD[ ]*\(([^,]+),([^\)]+)\)/';
			$sql = preg_replace( $pattern, '($1 + $2)', $sql);

			$pattern = '/FIELD[ ]*\(([^\),]+),([^\)]+)\)/';
			$sql = preg_replace_callback( $pattern, 'pg4wp_rewrite_field', $sql);

			$pattern = '/GROUP_CONCAT\(([^()]*(\(((?>[^()]+)|(?-2))*\))?[^()]*)\)/x';
			$sql = preg_replace( $pattern, "string_agg($1, ',')", $sql);

			// Convert MySQL RAND function to PostgreSQL RANDOM function
			$pattern = '/RAND[ ]*\([ ]*\)/';
			$sql = preg_replace( $pattern, 'RANDOM()', $sql);
			
			// UNIX_TIMESTAMP in MYSQL returns an integer
			$pattern = '/UNIX_TIMESTAMP\(([^\)]+)\)/';
			$sql = preg_replace( $pattern, 'ROUND(DATE_PART(\'epoch\',$1))', $sql);
			
			$date_funcs = array(
				'DAYOFMONTH('	=> 'EXTRACT(DAY FROM ',
				'YEAR('			=> 'EXTRACT(YEAR FROM ',
				'MONTH('		=> 'EXTRACT(MONTH FROM ',
				'DAY('			=> 'EXTRACT(DAY FROM ',
			);
			
			$sql = str_replace( 'ORDER BY post_date DESC', 'ORDER BY YEAR(post_date) DESC, MONTH(post_date) DESC', $sql);
			$sql = str_replace( 'ORDER BY post_date ASC', 'ORDER BY YEAR(post_date) ASC, MONTH(post_date) ASC', $sql);
			$sql = str_replace( array_keys($date_funcs), array_values($date_funcs), $sql);
			$curryear = date( 'Y');
			$sql = str_replace( 'FROM \''.$curryear, 'FROM TIMESTAMP \''.$curryear, $sql);
			
			// MySQL 'IF' conversion - Note : NULLIF doesn't need to be corrected
			$pattern = '/ (?<!NULL)IF[ ]*\(([^,]+),([^,]+),([^\)]+)\)/';
			$sql = preg_replace( $pattern, ' CASE WHEN $1 THEN $2 ELSE $3 END', $sql);

			// Act like MySQL default configuration, where sql_mode is ""
			$pattern = '/@@SESSION.sql_mode/';
			$sql = preg_replace( $pattern, "''", $sql);
			
			if( isset($wpdb))
			{
				$sql = str_replace('GROUP BY '.$wpdb->prefix.'posts.ID', '' , $sql);
			}
			$sql = str_replace("!= ''", '<> 0', $sql);
			
			// MySQL 'LIKE' is case insensitive by default, whereas PostgreSQL 'LIKE' is
			$sql = str_replace( ' LIKE ', ' ILIKE ', $sql);
			
			// INDEXES are not yet supported
			if( false !== strpos( $sql, 'USE INDEX (comment_date_gmt)'))
				$sql = str_replace( 'USE INDEX (comment_date_gmt)', '', $sql);
			
			// HB : timestamp fix for permalinks
			$sql = str_replace( 'post_date_gmt > 1970', 'post_date_gmt > to_timestamp (\'1970\')', $sql);
			
			// Akismet sometimes doesn't write 'comment_ID' with 'ID' in capitals where needed ...
			if( isset($wpdb) && false !== strpos( $sql, $wpdb->comments))
				$sql = str_replace(' comment_id ', ' comment_ID ', $sql);

			// MySQL treats a HAVING clause without GROUP BY like WHERE
			if( false !== strpos($sql, 'HAVING') && false === strpos($sql, 'GROUP BY'))
			{
				if( false === strpos($sql, 'WHERE'))
					$sql = str_replace('HAVING', 'WHERE', $sql);
				else
				{
					$pattern = '/WHERE\s+(.*?)\s+HAVING\s+(.*?)(\s*(?:ORDER|LIMIT|PROCEDURE|INTO|FOR|LOCK|$))/';
					$sql = preg_replace( $pattern, 'WHERE ($1) AND ($2) $3', $sql);
				}
			}

			// MySQL allows integers to be used as boolean expressions
			// where 0 is false and all other values are true.
			//
			// Although this could occur anywhere with any number, so far it
			// has only been observed as top-level expressions in the WHERE
			// clause and only with 0.  For performance, limit current
			// replacements to that.
			$pattern_after_where = '(?:\s*$|\s+(GROUP|HAVING|ORDER|LIMIT|PROCEDURE|INTO|FOR|LOCK))';
			$pattern = '/(WHERE\s+)0(\s+AND|\s+OR|' . $pattern_after_where . ')/';
			$sql = preg_replace( $pattern, '$1false$2', $sql);

			$pattern = '/(AND\s+|OR\s+)0(' . $pattern_after_where . ')/';
			$sql = preg_replace( $pattern, '$1false$2', $sql);

			// MySQL supports strings as names, PostgreSQL needs identifiers.
			// Limit to after closing parenthesis to reduce false-positives
			// Currently only an issue for nextgen-gallery plugin
			$pattern = '/\) AS \'([^\']+)\'/';
			$sql = preg_replace( $pattern, ') AS "$1"', $sql);
		} // SELECT
		elseif( 0 === strpos($sql, 'UPDATE'))
		{
			$logto = 'UPDATE';
			$pattern = '/LIMIT[ ]+\d+/';
			$sql = preg_replace($pattern, '', $sql);
			
			// For correct bactick removal
			$pattern = '/[ ]*`([^` ]+)`[ ]*=/';
			$sql = preg_replace( $pattern, ' $1 =', $sql);

			// Those are used when we need to set the date to now() in gmt time
			$sql = str_replace( "'0000-00-00 00:00:00'", 'now() AT TIME ZONE \'gmt\'', $sql);

			// For correct ID quoting
			$pattern = '/(,|\s)[ ]*([^ \']*ID[^ \']*)[ ]*=/';
			$sql = preg_replace( $pattern, '$1 "$2" =', $sql);
			
			// This will avoid modifications to anything following ' SET '
			list($sql,$end) = explode( ' SET ', $sql, 2);
			$end = ' SET '.$end;
		} // UPDATE
		elseif( 0 === strpos($sql, 'INSERT'))
		{
			$logto = 'INSERT';
			$sql = str_replace('(0,',"('0',", $sql);
			$sql = str_replace('(1,',"('1',", $sql);
			
			// Fix inserts into wp_categories
			if( false !== strpos($sql, 'INSERT INTO '.$wpdb->categories))
			{
				$sql = str_replace('"cat_ID",', '', $sql);
				$sql = str_replace("VALUES ('0',", "VALUES(", $sql);
			}
			
			// Those are used when we need to set the date to now() in gmt time
			$sql = str_replace( "'0000-00-00 00:00:00'", 'now() AT TIME ZONE \'gmt\'', $sql);
			
			// Multiple values group when calling INSERT INTO don't always work
			if( false !== strpos( $sql, $wpdb->options) && false !== strpos( $sql, '), ('))
			{
				$pattern = '/INSERT INTO.+VALUES/';
				preg_match($pattern, $sql, $matches);
				$insert = $matches[0];
				$sql = str_replace( '), (', ');'.$insert.'(', $sql);
			}
			
			// Support for "INSERT ... ON DUPLICATE KEY UPDATE ..." is a dirty hack
			// consisting in deleting the row before inserting it
			if( false !== $pos = strpos( $sql, 'ON DUPLICATE KEY'))
			{
				// Get the elements we need (table name, first field, corresponding value)
				$pattern = '/INSERT INTO\s+([^\(]+)\(([^,]+)[^\(]+VALUES\s*\(([^,]+)/';
				preg_match($pattern, $sql, $matches);
				$table = trim( $matches[1], ' `');
				if( !in_array(trim($matches[1],'` '), array($wpdb->posts,$wpdb->comments)))
				{
					// Remove 'ON DUPLICATE KEY UPDATE...' and following
					$sql = substr( $sql, 0, $pos);
					// Add a delete query to handle the maybe existing data
					$sql = 'DELETE FROM '.$table.' WHERE '.$matches[2].' = '.$matches[3].';'.$sql;
				}
			}
			elseif( 0 === strpos($sql, 'INSERT IGNORE'))
			{
				// Note:  Requires PostgreSQL 9.0 and USAGE privilege.
				// Could do query-specific rewrite using SELECT without FROM
				// as in http://stackoverflow.com/a/13342031
				$sql = 'DO $$BEGIN INSERT'.substr($sql, 13).'; EXCEPTION WHEN unique_violation THEN END;$$;';
			}
			
			// To avoid Encoding errors when inserting data coming from outside
			if( preg_match('/^.{1}/us',$sql,$ar) != 1)
				$sql = utf8_encode($sql);
			
			// This will avoid modifications to anything following ' VALUES'
			list($sql,$end) = explode( ' VALUES', $sql, 2);
			$end = ' VALUES'.$end;
			
			// When installing, the sequence for table terms has to be updated
			if( defined('WP_INSTALLING') && WP_INSTALLING && false !== strpos($sql, 'INSERT INTO `'.$wpdb->terms.'`'))
				$end .= ';SELECT setval(\''.$wpdb->terms.'_seq\', (SELECT MAX(term_id) FROM '.$wpdb->terms.')+1);';
			
		} // INSERT
		elseif( 0 === strpos( $sql, 'DELETE' ))
		{
			$logto = 'DELETE';

			// ORDER BY is not supported in DELETE queries, and not required
			// when LIMIT is not present
			if( false !== strpos( $sql, 'ORDER BY') && false === strpos( $sql, 'LIMIT'))
			{
				$pattern = '/ORDER BY \S+ (ASC|DESC)?/';
				$sql = preg_replace( $pattern, '', $sql);
			}

			// LIMIT is not allowed in DELETE queries
			$sql = str_replace( 'LIMIT 1', '', $sql);
			$sql = str_replace( ' REGEXP ', ' ~ ', $sql);
			
			// This handles removal of duplicate entries in table options
			if( false !== strpos( $sql, 'DELETE o1 FROM '))
				$sql = "DELETE FROM $wpdb->options WHERE option_id IN " .
					"(SELECT o1.option_id FROM $wpdb->options AS o1, $wpdb->options AS o2 " .
					"WHERE o1.option_name = o2.option_name " .
					"AND o1.option_id < o2.option_id)";
			// Rewrite _transient_timeout multi-table delete query
			elseif( 0 === strpos( $sql, 'DELETE a, b FROM wp_options a, wp_options b'))
			{
				$where = substr( $sql, strpos($sql, 'WHERE ') + 6);
				$where = rtrim( $where, " \t\n\r;");
				// Fix string/number comparison by adding check and cast
				$where = str_replace( 'AND b.option_value', 'AND b.option_value ~ \'^[0-9]+$\' AND CAST(b.option_value AS BIGINT)', $where);
				// Mirror WHERE clause to delete both sides of self-join.
				$where2 = strtr( $where, array('a.' => 'b.', 'b.' => 'a.'));
				$sql = 'DELETE FROM wp_options a USING wp_options b WHERE '.
					'('.$where.') OR ('.$where2.');';
			}
			
			// Akismet sometimes doesn't write 'comment_ID' with 'ID' in capitals where needed ...
			if( false !== strpos( $sql, $wpdb->comments))
				$sql = str_replace(' comment_id ', ' comment_ID ', $sql);
		}
		// Fix tables listing
		elseif( 0 === strpos($sql, 'SHOW TABLES'))
		{
			$logto = 'SHOWTABLES';
			$sql = 'SELECT tablename FROM pg_tables WHERE schemaname = \'public\';';
		}
		// Rewriting optimize table
		elseif( 0 === strpos($sql, 'OPTIMIZE TABLE'))
		{
			$logto = 'OPTIMIZE';
			$sql = str_replace( 'OPTIMIZE TABLE', 'VACUUM', $sql);
		}
		// Handle 'SET NAMES ... COLLATE ...'
		elseif( 0 === strpos($sql, 'SET NAMES') && false !== strpos($sql, 'COLLATE'))
		{
			$logto = 'SETNAMES';
			$sql = "SET NAMES 'utf8'";
		}
		// Load up upgrade and install functions as required
		$begin = strtoupper( substr( $sql, 0, 3));
		$search = array( 'SHO', 'ALT', 'DES', 'CRE', 'DRO');
		if( in_array($begin, $search))
		{
			require_once( PG4WP_ROOT.'/driver_pgsql_install.php');
			$sql = pg4wp_installing( $sql, $logto);
		}
		
		// WP 2.9.1 uses a comparison where text data is not quoted
		$pattern = '/AND meta_value = (-?\d+)/';
		$sql = preg_replace( $pattern, 'AND meta_value = \'$1\'', $sql);

        // Add type cast for meta_value field when it's compared to number
        $pattern = '/AND meta_value < (\d+)/';
        $sql = preg_replace($pattern, 'AND meta_value::bigint < $1', $sql);
		
		// Generic "INTERVAL xx YEAR|MONTH|DAY|HOUR|MINUTE|SECOND" handler
		$pattern = '/INTERVAL[ ]+(\d+)[ ]+(YEAR|MONTH|DAY|HOUR|MINUTE|SECOND)/';
		$sql = preg_replace( $pattern, "'\$1 \$2'::interval", $sql);
		$pattern = '/DATE_SUB[ ]*\(([^,]+),([^\)]+)\)/';
		$sql = preg_replace( $pattern, '($1::timestamp - $2)', $sql);
		
		// Remove illegal characters
		$sql = str_replace('`', '', $sql);
		
		// Field names with CAPITALS need special handling
		if( false !== strpos($sql, 'ID'))
		{
			$pattern = '/ID([^ ])/';
				$sql = preg_replace($pattern, 'ID $1', $sql);
			$pattern = '/ID$/';
				$sql = preg_replace($pattern, 'ID ', $sql);
			$pattern = '/\(ID/';
				$sql = preg_replace($pattern, '( ID', $sql);
			$pattern = '/,ID/';
				$sql = preg_replace($pattern, ', ID', $sql);
			$pattern = '/[0-9a-zA-Z_]+ID/';
				$sql = preg_replace($pattern, '"$0"', $sql);
			$pattern = '/\.ID/';
				$sql = preg_replace($pattern, '."ID"', $sql);
			$pattern = '/[\s]ID /';
				$sql = preg_replace($pattern, ' "ID" ', $sql);
			$pattern = '/"ID "/';
				$sql = preg_replace($pattern, ' "ID" ', $sql);
		} // CAPITALS
		
		// Empty "IN" statements are erroneous
		$sql = str_replace( 'IN (\'\')', 'IN (NULL)', $sql);
		$sql = str_replace( 'IN ( \'\' )', 'IN (NULL)', $sql);
		$sql = str_replace( 'IN ()', 'IN (NULL)', $sql);
		
		// Put back the end of the query if it was separated
		$sql .= $end;
		
		// For insert ID catching
		if( $logto == 'INSERT')
		{
			$pattern = '/INSERT INTO (\w+)\s+\([ a-zA-Z_"]+/';
			preg_match($pattern, $sql, $matches);
			$GLOBALS['pg4wp_ins_table'] = $matches[1];
			$match_list = explode(' ', $matches[0]);
			if( $GLOBALS['pg4wp_ins_table'])
			{
				$GLOBALS['pg4wp_ins_field'] = trim($match_list[3],' ()	');
				if(! $GLOBALS['pg4wp_ins_field'])
					$GLOBALS['pg4wp_ins_field'] = trim($match_list[4],' ()	');
			}
			$GLOBALS['pg4wp_last_insert'] = $sql;
		}
		elseif( isset($GLOBALS['pg4wp_queued_query']))
		{
			pg_query($GLOBALS['pg4wp_queued_query']);
			unset($GLOBALS['pg4wp_queued_query']);
		}
		
		// Correct quoting for PostgreSQL 9.1+ compatibility
		$sql = str_replace( "\\'", "''", $sql);
		$sql = str_replace( '\"', '"', $sql);
		
		if( PG4WP_DEBUG)
		{
			if( $initial != $sql)
				error_log( '['.microtime(true)."] Converting :\n$initial\n---- to ----\n$sql\n---------------------\n", 3, PG4WP_LOG.'pg4wp_'.$logto.'.log');
			else
				error_log( '['.microtime(true)."] $sql\n---------------------\n", 3, PG4WP_LOG.'pg4wp_unmodified.log');
		}
		return $sql;
	}

	// Database initialization
	function pg4wp_init()
	{
		// Provide (mostly) MySQL-compatible field function
		// Note:  MySQL accepts heterogeneous argument types.  No easy fix.
		//        Can define version with typed first arg to cover some cases.
		// Note:  ROW_NUMBER+unnest doesn't guarantee order, but is simple/fast.
		//        If it breaks, try https://stackoverflow.com/a/8767450
		$result = pg_query(<<<SQL
CREATE OR REPLACE FUNCTION field(anyelement, VARIADIC anyarray)
	RETURNS BIGINT AS
$$
SELECT rownum
FROM (SELECT ROW_NUMBER() OVER () AS rownum, elem
	FROM unnest($2) elem) numbered
WHERE numbered.elem = $1
UNION ALL
SELECT 0
$$
	LANGUAGE SQL IMMUTABLE;
SQL
);
		if( (PG4WP_DEBUG || PG4WP_LOG_ERRORS) && $result === false )
		{
			$err = pg_last_error();
			error_log('['.microtime(true)."] Error creating MySQL-compatible field function: $err\n", 3, PG4WP_LOG.'pg4wp_errors.log');
		}
	}

/*
	Quick fix for wpsql_result() error and missing wpsql_errno() function
	Source : http://vitoriodelage.wordpress.com/2014/06/06/add-missing-wpsql_errno-in-pg4wp-plugin/
*/
	function wpsql_result($result, $i, $fieldname = null) {
		if (is_resource($result)) {
			if ($fieldname) {
				return pg_fetch_result($result, $i, $fieldname);
			} else {
				return pg_fetch_result($result, $i);
			}
		}
	}
	
	function wpsql_errno( $connection) {
		$result = pg_get_result($connection);
		$result_status = pg_result_status($result);
		return pg_result_error_field($result_status, PGSQL_DIAG_SQLSTATE);
	}
