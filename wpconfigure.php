<?php
parse_str(implode('&', array_slice($argv, 1)), $_GET);
$config_file_path = $_GET['path'];

require( $config_file_path );

write_to_command_line("Firing up the ole PHP script...");

run( $wp_dir );

function run( $install_path, $new_database, $mysql ) {
  modify_wp_config( $wp_dir );
  create_env_loader( $wp_dir );
  create_dev_env( $wp_dir );
  setup_mysql_db( $new_database, $mysql );
}

function write_to_command_line( $message ) {
  fwrite(STDOUT, $message . "\n");
}

function connect_to_mysql( $host, $username, $password ) {
  $connection = mysql_connect( $host, $username, $password );

  if( $connection ) {
    write_to_command_line("Connected to MySQL.");
    return $connection;
  } else {
    write_to_command_line("Couldn't connect to MySQL. Check your configuration details.");
    return false;
  }
}

function create_mysql_database( $database_name, $connection ) {
  $query = "CREATE DATABASE `$database_name`;"
  $query_result = mysql_query( $query, $connection );
  
  if( $query_result ) {
    write_to_command_line("Database created.");
    return true;
  } else {
    write_to_command_line("Error creating database: " . mysql_error());
    return false;
  }
}

function create_mysql_user( $username, $password, $connection ) {
  $query = "CREATE USER '$username'@'localhost' IDENTIFIED BY '$password';"
  $query_result = mysql_query( $query, $connection );
  
  if( $query_result ) {
    write_to_command_line("User created.");
    return true;
  } else {
    write_to_command_line("Error creating user: " . mysql_error());
    return false;
  }
}

function grant_db_privileges_to_user( $database_name, $username ) {
  $query = "GRANT ALL ON `$database_name`.* TO '$username'@'localhost';"
  $query_result = mysql_query( $query, $connection );
  
  if( $query_result ) {
    write_to_command_line("User privileges granted.");
    return true;
  } else {
    write_to_command_line("Error granting user privileges: " . mysql_error());
    return false;
  }
}

function setup_mysql_db( $new_database, $mysql ) {
  $connection = connect_to_mysql( $mysql['host'], $mysql['username'], $mysql['password'] );
	
  if( $connection ) {
    $database_created = create_mysql_database( $new_databse['name'], $connection );
    $user_created = create_mysql_user( $new_databse['username'], $new_databse['password'], $connection );

    if( $database_created && $user_created ) {
      grant_db_privileges_to_user( $new_databse['name'], $new_databse['username'] );
    }

    mysql_close( $connection );
  } 

	write_to_command_line("MySQL setup complete.");
}

function curl_get_file_contents($URL) {
	$c = curl_init();
	curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($c, CURLOPT_URL, $URL);
	$contents = curl_exec($c);
	curl_close($c);
	if ($contents) return $contents;
	else return false;
}

function replace_keys_and_salts( $wpconfig ) {
	
	$keys = curl_get_file_contents("https://api.wordpress.org/secret-key/1.1/salt/");
	$start = strpos($wpconfig, "define('AUTH_KEY'");
	$search = substr($wpconfig, $start, 479);
	$wpconfig = str_replace($search, $keys, $wpconfig);

	return $wpconfig;
}

function replace_default_db_info( $wpconfig ) {
	global $user,$pass,$dbase,$host,$prefix;
	if (!empty($dbase)) $wpconfig = str_replace("define('DB_NAME', 'database_name_here');", "require_once('environment/load_environment.php');\ndefine('DB_NAME', \$_ENV[\"DB_NAME\"]);", $wpconfig);
	if (!empty($user)) $wpconfig = str_replace("define('DB_USER', 'username_here');", "define('DB_USER', \$_ENV[\"DB_USER\"]);", $wpconfig);
	if (!empty($pass)) $wpconfig = str_replace("define('DB_PASSWORD', 'password_here');", "define('DB_PASSWORD', \$_ENV[\"DB_PASSWORD\"]);", $wpconfig);
	if (!empty($host)) $wpconfig = str_replace("define('DB_HOST', 'localhost');", "define('DB_HOST', \$_ENV[\"DB_HOST\"]);", $wpconfig);
	if (!empty($prefix)) $wpconfig = str_replace("\$table_prefix  = 'wp_';", "\$table_prefix  = '$prefix';", $wpconfig);

	return $wpconfig;
}

function modify_wp_config( $wp_dir ) {
	if ( file_exists( "$wp_dir/wp-config-sample.php" ) && !file_exists( "$wp_dir/wp-config.php" ) ) {
		$wpconfig = file_get_contents("$wp_dir/wp-config-sample.php");
		$wpconfig = replace_keys_and_salts( replace_default_db_info( $wpconfig ) );

		$file = fopen("$wp_dir/wp-config.php", "wb");
		fwrite($file, $wpconfig);
		fclose($file);
		write_to_command_line("wp-config.php successfully configured. Woo hoo.");
	} else {
		write_to_command_line("You already have a wp-config.php\nWe're gonna stick with your existing configuration.");
	}
}

function create_env_loader( $wp_dir ) {
	if ( ! file_exists( "$wp_dir/environment/load_environment.php" ) ) {
		$file_handle = fopen( "$wp_dir/environment/load_environment.php", "wb" );
		$env_loader = '<?php
if(file_exists(__DIR__ . "/production_env.php")) {
	require_once(__DIR__ . "/production_env.php");
} elseif(file_exists(__DIR__ . "/staging_env.php")) {
	require_once(__DIR__ . "/staging_env.php");
} elseif(is_file(__DIR__ . "/development_env.php")) {
	require_once(__DIR__ . "/development_env.php");
} elseif(is_file("../../config/wordpress/production_env.php")) {
	require_once("../../config/wordpress/production_env.php");
} elseif(is_file("../../config/wordpress/staging_env.php")) {
	require_once("../../config/wordpress/staging_env.php");
} elseif(is_file("../../config/wordpress/development_env.php")) {
	require_once("../../config/wordpress/development_env.php");
} else {
	die("Sorry! We\'re busy making this site better. We\'ll be back online shortly. (No environment could be loaded.)");
}	
?>';
		fwrite($file_handle, $env_loader);
		fclose($file_handle);
		write_to_command_line("Environment loader created.");
	}
}

function create_dev_env( $wp_dir ) {
	global $user,$pass,$dbase,$host,$prefix;
	if ( ! file_exists( "$wp_dir/environment/development_env.php" ) ) {
		$file_handle = fopen( "$wp_dir/environment/development_env.php", "wb" );
		$dev_env = '<?php
// Local DB
$_ENV["DB_USER"] = "' . $user . '";
$_ENV["DB_PASSWORD"] = "' . $pass . '";
$_ENV["DB_NAME"] = "' . $dbase . '";
$_ENV["TABLE_PREFIX"] = "' . $prefix . '";
$_ENV["DB_HOST"] = "' . $host . '";
$_ENV["WP_DEBUG"] = true;
?>';
		fwrite($file_handle, $dev_env);
		fclose($file_handle);
		write_to_command_line("Development environment created.");
	}
}




?>
