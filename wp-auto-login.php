<?php

/**
 * Plugin Name: Auto Login
 * Description: Enables one click login from the Feather Stack admin.
 * Version: 1.0.2
 */

/**
 * Show login message, if any.
 */
function al_login_message($message) {
	global $al_error_message;

	if ($al_error_message)
		$message.="<p class='error message'>".esc_html($al_error_message)."</p>";

	return $message;
}

/**
 * Authenticate. Ignore the password. This function is used temporarily
 * to respond to the authenticate filter.
 */
function al_authenticate($user, $username, $password) {
	return get_user_by('login',$username);
}

/**
 * Handle the init action. 
 * If the script is wp-login.php, if we have an external authentication
 * script configured and we have a loginkey, log in the user.
 */
function al_init() {
	global $al_error_message;

	if (basename($_SERVER["SCRIPT_NAME"])!="wp-login.php")
		return;

	if (!isset($_REQUEST["loginkey"]))
		return;

	$url=get_option("al_authentication_url");
	if (!$url)
		return;

	try {
		$curl=curl_init();
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,1);	

		foreach ($_REQUEST as $k=>$v)
			$url=add_query_arg($k,$v,$url);

		curl_setopt($curl,CURLOPT_URL,$url);
		$res=curl_exec($curl);

		if (curl_error($curl))
			throw new Exception(curl_error($curl));

		if (curl_getinfo($curl,CURLINFO_HTTP_CODE)!=200)
			throw new Exception("Response from auth server: ".
				curl_getinfo($curl,CURLINFO_HTTP_CODE));

		$data=json_decode($res,TRUE);
		if (!$data)
			throw new Exception("Got no parsable json data from auth server.");

		if (isset($data["error"]) && $data["error"])
			throw new Exception($data["error"]);

		if (!$data["success"])
			throw new Exception("Got no success from auth server.");

		if (!$data["username"])
			throw new Exception("Got no username from auth server.");

		$username=$data["username"];
		if (is_user_logged_in())
			wp_logout();

		add_filter("authenticate","al_authenticate",10,3);
	    $user=wp_signon(array('user_login'=>$username));
	    remove_filter("authenticate","al_authenticate",10,3);

	    if (is_a($user,'WP_User'))
	        wp_set_current_user($user->ID,$user->user_login);

		if (!is_user_logged_in())
			throw new Exception("Auto login failed");

		wp_redirect(user_admin_url());
		exit;
	}

	catch (Exception $e) {
		$al_error_message=$e->getMessage();
	}
}

/**** Main. ****/
global $al_error_message;
$al_error_message=NULL;

add_filter("login_message","al_login_message");
add_action("init","al_init");
