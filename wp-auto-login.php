<?php

/**
 * Plugin Name: Auto Login
 * Description: Enables one click login from the Feather Stack admin.
 */

/**
 * Authenticate. Ignore the password.
 */
function al_authenticate($user, $username, $password) {
	return get_user_by('login',$username);
}

/**
 * Login message. This is where we do the magic, so that we have a chance
 * to show an error message to the user.
 */
function al_login_message($message) {
	if (!isset($_REQUEST["loginkey"]))
		return $message;

	$authenticationUrl=get_option("al_authentication_url");
	if (!$authenticationUrl)
		return $message;

	$curl=curl_init();
	curl_setopt($curl,CURLOPT_RETURNTRANSFER,1);	

	$url=add_query_arg("loginkey",$loginKey,$authenticationUrl);
	curl_setopt($curl,CURLOPT_URL,$url);
	$res=curl_exec($curl);

	if (curl_error($curl))
		return curl_error($curl);

	if (curl_getinfo($curl,CURLINFO_HTTP_CODE)!=200)
		return "Response from auth server: ".curl_getinfo($curl,CURLINFO_HTTP_CODE);

	$data=json_decode($res,TRUE);
	if (!$data)
		return "Got no json data from auth server.";

	if (isset($data["error"]) && $data["error"])
		return $data["error"];

	if (!$data["success"])
		return "Got no success from auth server.";

	if (!$data["username"])
		return "Got no username from auth server.";

	$username=$data["username"];
	if (is_user_logged_in())
		wp_logout();

	add_filter("authenticate","al_authenticate",10,3);
    $user=wp_signon(array('user_login'=>$username));
    remove_filter("authenticate","al_authenticate",10,3);

    if (is_a($user,'WP_User'))
        wp_set_current_user($user->ID,$user->user_login);

	if (!is_user_logged_in()) {
		error_log("Auto login failed...");
		return "Auto login failed";
	}

	wp_redirect(user_admin_url());
	exit;
}

/**** Main. ****/
add_filter("login_message","al_login_message");
