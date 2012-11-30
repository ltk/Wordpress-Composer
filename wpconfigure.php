<?php
parse_str(implode('&', array_slice($argv, 1)), $_GET);
$wp_dir = $_GET['path'] . "/wordpress";
// $config_file_path = "~/Projects/Wordpress-Setup/WP-Composer/config.php";

$config_file_path = "config.php";
require( $config_file_path );

write_to_command_line("Firing up the ole PHP script...");

run( $wp_dir, $new_database, $mysql, $wordpress_options, $wordpress_admin_user );

function run( $install_path, $new_database, $mysql, $wordpress_options, $wordpress_admin_user ) {
  modify_wp_config( $install_path, $new_database );
  create_env_loader( $install_path );
  create_dev_env( $install_path, $new_database );
  setup_mysql_db( $new_database, $mysql );

  install_wordpress( $install_path, $wordpress_options, $wordpress_admin_user );
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
  $query = "CREATE DATABASE `$database_name`;";
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
  $query = "CREATE USER '$username'@'localhost' IDENTIFIED BY '$password';";
  $query_result = mysql_query( $query, $connection );
  
  if( $query_result ) {
    write_to_command_line("User created.");
    return true;
  } else {
    write_to_command_line("Error creating user: " . mysql_error());
    return false;
  }
}

function grant_db_privileges_to_user( $database_name, $username, $connection ) {
  $query = "GRANT ALL ON `$database_name`.* TO '$username'@'localhost';";
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
    $database_created = create_mysql_database( $new_database['name'], $connection );
    $user_created = create_mysql_user( $new_database['username'], $new_database['password'], $connection );

    if( $database_created && $user_created ) {
      grant_db_privileges_to_user( $new_database['name'], $new_database['username'], $connection );
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

function replace_default_db_info( $wpconfig, $new_database ) {
	if ( ! empty( $new_database['name'] ) ) $wpconfig = str_replace("define('DB_NAME', 'database_name_here');", "require_once('environment/load_environment.php');\ndefine('DB_NAME', \$_ENV[\"DB_NAME\"]);", $wpconfig);
	if (!empty($new_database['username'])) $wpconfig = str_replace("define('DB_USER', 'username_here');", "define('DB_USER', \$_ENV[\"DB_USER\"]);", $wpconfig);
	if (!empty($new_database['password'])) $wpconfig = str_replace("define('DB_PASSWORD', 'password_here');", "define('DB_PASSWORD', \$_ENV[\"DB_PASSWORD\"]);", $wpconfig);
	if (!empty($new_database['host'])) $wpconfig = str_replace("define('DB_HOST', 'localhost');", "define('DB_HOST', \$_ENV[\"DB_HOST\"]);", $wpconfig);
	if (!empty($new_database['prefix'])) $wpconfig = str_replace("\$table_prefix  = 'wp_';", "\$table_prefix  = '{$new_database['prefix']}';", $wpconfig);

	return $wpconfig;
}

function modify_wp_config( $wp_dir, $new_database ) {
	if ( file_exists( "$wp_dir/wp-config-sample.php" ) && !file_exists( "$wp_dir/wp-config.php" ) ) {
		$wpconfig = file_get_contents("$wp_dir/wp-config-sample.php");
		$wpconfig = replace_keys_and_salts( replace_default_db_info( $wpconfig, $new_database ) );

		$file = fopen("$wp_dir/wp-config.php", "wb");
		fwrite($file, $wpconfig);
		fclose($file);
		write_to_command_line("wp-config.php successfully configured. Woo hoo.");
	} else if( file_exists( "$wp_dir/wp-config.php" ) ) {
		write_to_command_line("You already have a wp-config.php\nWe're gonna stick with your existing configuration.");
	} else {
		write_to_command_line("We can't find your wp-config-sample.php\nAbort the mission! Abort!");
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

function create_dev_env( $wp_dir, $new_database ) {
	if ( ! file_exists( "$wp_dir/environment/development_env.php" ) ) {
		$file_handle = fopen( "$wp_dir/environment/development_env.php", "wb" );
		$dev_env = '<?php
// Local DB
$_ENV["DB_USER"] = "' . $new_database['username'] . '";
$_ENV["DB_PASSWORD"] = "' . $new_database['password'] . '";
$_ENV["DB_NAME"] = "' . $new_database['name'] . '";
$_ENV["TABLE_PREFIX"] = "' . $new_database['prefix'] . '";
$_ENV["DB_HOST"] = "' . $new_database['host'] . '";
$_ENV["WP_DEBUG"] = true;
?>';
		fwrite($file_handle, $dev_env);
		fclose($file_handle);
		write_to_command_line("Development environment created.");
	}
}
function after_wordpress_install_buffer( $buffer ) {
	$success = preg_match('/Success/', $buffer );

	if( $success === 1 ){ 
		write_to_command_line("Install Successful!");

		write_to_command_line("Upgrading WordPress database.");
		$upgraded = wp_upgrade();

		if( $upgraded ) {
			write_to_command_line("WordPress database upgraded.");
		} else {
			write_to_command_line("WordPress database NOT upgraded.");
		}

		// TODO: make the site url customizable
		exec('open http://composertest.com/wp-admin/');
	} else {
		$errors = array();
		preg_match( '/<p class="message"><strong>ERROR<\/strong>:(.*)<\/p>/', $buffer, $errors );
		write_to_command_line("WordPress installation failed with the following error:");
		write_to_command_line( trim( $errors[1] ) );
	}
}

function install_wordpress( $install_path, $wordpress_options, $wordpress_admin_user ) {
	$install_file_path = $install_path . "/wp-admin/install.php";
	$_GET['step'] = 2; // Spoofing form submission

	$_POST = array(
		'weblog_title' => $wordpress_options['title'],
		'blog_public' => $wordpress_options['allow_search_engines'],
		'admin_password' => $wordpress_admin_user['password'],
		'admin_password2' => $wordpress_admin_user['password'],
		'admin_email' => $wordpress_admin_user['email'],
		'user_name' => $wordpress_admin_user['username'],
		);
	define( 'WP_SITEURL', $wordpress_options['url'] );
	write_to_command_line("Installing WordPress...");

	ob_start( "after_wordpress_install_buffer" );
	require( $install_file_path );
	ob_end_flush();
}
?>
