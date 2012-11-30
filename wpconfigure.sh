#!/bin/php
<?php
parse_str(implode('&', array_slice($argv, 1)), $_GET);
$wp_dir = $_GET['path']. "/wordpress";
$config_file_path = $_GET['path'];

fwrite(STDOUT, "Firing up the ole PHP script...\n");


//Project DB Access Details
$user = "sixteen";
$pass = "test-password-1";
$dbase = "this-is-a-test";
$host = "localhost";
$prefix = "wp_";

function setup_mysql_db($database_name) {
	global $user,$pass,$dbase,$host,$prefix;
	$mysql_user = "master";
	$mysql_password = "master";
	$mysql_host = "localhost";

	$con = mysql_connect($mysql_host,$mysql_user,$mysql_password);
	if (!$con)
	  {
	  fwrite(STDOUT, "Can't connect to MySQL.\n");
	  # die('Could not connect: ' . mysql_error());
	  }
	 else {
	 	fwrite(STDOUT, "Connected to MySQL.\n");
	 }

	if (mysql_query("CREATE DATABASE `$database_name`;",$con))
	  {
	  fwrite(STDOUT, "Database created.\n");
	  }
	else
	  {
	  fwrite(STDOUT, "Error creating database: " . mysql_error() . "\n");
	  }

  if (mysql_query("CREATE USER '$user'@'$host' IDENTIFIED BY 'password';",$con))
    {
    fwrite(STDOUT, "User created.\n");
    }
  else
    {
    fwrite(STDOUT, "Error creating user: " . mysql_error() . "\n");
    }

    if (mysql_query("GRANT ALL ON `$database_name`.* TO '$user'@'$host';",$con))
      {
      fwrite(STDOUT, "User privledges granted.\n");
      }
    else
      {
      fwrite(STDOUT, "Error granting user privledges: " . mysql_error() . "\n");
      }

	mysql_close($con);
	fwrite(STDOUT, "MySQL setup function ending...\n");
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
		fwrite(STDOUT, "wp-config.php successfully configured. Woo hoo.\n");
	} else {
		fwrite(STDOUT, "You already have a wp-config.php\nWe're gonna stick with your existing configuration.\n");
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
		fwrite(STDOUT, "Environment loader created.\n");
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
		fwrite(STDOUT, "Development environment created.\n");
	}
}

function run( $wp_dir ) {
	global $dbase;
	modify_wp_config( $wp_dir );
	create_env_loader( $wp_dir );
	create_dev_env( $wp_dir );
	setup_mysql_db( $dbase );
}

run( $wp_dir );


?>
