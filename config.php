<?php
// Project-Specific
$install_path = "~/Sites/XYZ-WordPress-Site/wordpress";

$new_database = array(
	"username" => "xyz",
	"password" => "xyz1234.$",
	"name" => "xyz_wordpress",
	"host" => "localhost",
	"prefix" => "wp_"
);

$wordpress_options = array(
	"title" => "Default Title",
	"allow_search_engines" => 0,
	"url" => "http://composertest.com"
);

$wordpress_admin_user = array(
	"email" => "lawson.kurtz@gmail.com",
	"username" => "admin",
	"password" => "this-is-a-password"
);

// System-Specific
$mysql = array(
	"username" => "master",
	"password" => "master",
	"host" => "localhost"
);

$pages = array(
	array(
		"post_title" => "Page 1",
		"post_content" => "[lipsum]"
		"post_status" => "publish",
	),
  array(
    "post_title" => "Page 2",
    "post_content" => "[lipsum]"
    "post_status" => "publish",
  ),
  array(
    "post_title" => "Page 3",
    "post_content" => "[lipsum]"
    "post_status" => "publish",
  ),
  array(
    "post_title" => "Page 4",
    "post_content" => "[lipsum]"
    "post_status" => "publish",
  ),
  array(
    "post_title" => "Page 5",
    "post_content" => "[lipsum]"
    "post_status" => "publish",
  ),

);