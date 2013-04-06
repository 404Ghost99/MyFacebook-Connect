<?php
/**
 * MyFacebook Connect
 * 
 * Integrates MyBB with Facebook, featuring login and registration.
 *
 * @package MyFacebook Connect
 * @author  Shade <legend_k@live.it>
 * @license http://opensource.org/licenses/mit-license.php MIT license
 * @version beta 4
 */

if (!defined('IN_MYBB')) {
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

if (!defined("PLUGINLIBRARY")) {
	define("PLUGINLIBRARY", MYBB_ROOT . "inc/plugins/pluginlibrary.php");
}

function myfbconnect_info()
{
	return array(
		'name' => 'MyFacebook Connect',
		'description' => 'Integrates MyBB with Facebook, featuring login and registration.',
		'website' => 'https://github.com/Shade-/MyFacebookConnect',
		'author' => 'Shade',
		'authorsite' => 'http://www.idevicelab.net/forum',
		'version' => 'beta 4',
		'compatibility' => '16*',
		'guid' => 'none... yet'
	);
}

function myfbconnect_is_installed()
{
	global $cache;
	
	$info = myfbconnect_info();
	$installed = $cache->read("shade_plugins");
	if ($installed[$info['name']]) {
		return true;
	}
}

function myfbconnect_install()
{
	global $db, $PL, $lang, $mybb, $cache;
	
	if (!$lang->myfbconnect) {
		$lang->load('myfbconnect');
	}
	
	if (!file_exists(PLUGINLIBRARY)) {
		flash_message($lang->myfbconnect_pluginlibrary_missing, "error");
		admin_redirect("index.php?module=config-plugins");
	}
	
	$PL or require_once PLUGINLIBRARY;
	
	$PL->settings('myfbconnect', $lang->myfbconnect_settings, $lang->myfbconnect_settings_desc, array(
		'enabled' => array(
			'title' => $lang->myfbconnect_settings_enable,
			'description' => $lang->myfbconnect_settings_enable_desc,
			'value' => '1'
		),
		'appid' => array(
			'title' => $lang->myfbconnect_settings_appid,
			'description' => $lang->myfbconnect_settings_appid_desc,
			'value' => '',
			'optionscode' => 'text'
		),
		'appsecret' => array(
			'title' => $lang->myfbconnect_settings_appsecret,
			'description' => $lang->myfbconnect_settings_appsecret_desc,
			'value' => '',
			'optionscode' => 'text'
		),
		'fastregistration' => array(
			'title' => $lang->myfbconnect_settings_fastregistration,
			'description' => $lang->myfbconnect_settings_fastregistration_desc,
			'value' => '1'
		),
		'usergroup' => array(
			'title' => $lang->myfbconnect_settings_usergroup,
			'description' => $lang->myfbconnect_settings_usergroup_desc,
			'value' => '2',
			'optionscode' => 'text'
		),
		'passwordpm' => array(
			'title' => $lang->myfbconnect_settings_passwordpm,
			'description' => $lang->myfbconnect_settings_passwordpm_desc,
			'value' => '1'
		),
		'requestpublishingperms' => array(
			'title' => $lang->myfbconnect_settings_requestpublishingperms,
			'description' => $lang->myfbconnect_settings_requestpublishingperms_desc,
			'value' => '1'
		)
	));
	
	// insert our Facebook columns into the database
	$db->query("ALTER TABLE " . TABLE_PREFIX . "users ADD (
		`fbdetails` int(1) NOT NULL DEFAULT 1,
		`fbbday` int(1) NOT NULL DEFAULT 1,
		`fblocation` int(1) NOT NULL DEFAULT 1,
		`myfb_uid` bigint(50) NOT NULL DEFAULT 0
		)");
	
	// Euantor's templating system	   
	$dir = new DirectoryIterator(dirname(__FILE__) . '/MyFacebookConnect/templates');
	$templates = array();
	foreach ($dir as $file) {
		if (!$file->isDot() AND !$file->isDir() AND pathinfo($file->getFilename(), PATHINFO_EXTENSION) == 'html') {
			$templates[$file->getBasename('.html')] = file_get_contents($file->getPathName());
		}
	}
	
	$PL->templates('myfbconnect', 'MyFacebook Connect', $templates);
	
	// create cache
	$info = myfbconnect_info();
	$shadePlugins = $cache->read('shade_plugins');
	$shadePlugins[$info['name']] = array(
		'title' => $info['name'],
		'version' => $info['version']
	);
	$cache->update('shade_plugins', $shadePlugins);
	
	require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';
	
	find_replace_templatesets('header_welcomeblock_guest', '#' . preg_quote('{$lang->welcome_register}</a>') . '#i', '{$lang->welcome_register}</a> &mdash; <a href="{$mybb->settings[\'bburl\']}/myfbconnect.php?action=fblogin">{$lang->myfbconnect_login}</a>');
	
	rebuild_settings();
	
}

function myfbconnect_uninstall()
{
	global $db, $PL, $cache, $lang;
	
	if (!$lang->myfbconnect) {
		$lang->load('myfbconnect');
	}
	
	if (!file_exists(PLUGINLIBRARY)) {
		flash_message($lang->myfbconnect_pluginlibrary_missing, "error");
		admin_redirect("index.php?module=config-plugins");
	}
	
	$PL or require_once PLUGINLIBRARY;
	
	$PL->settings_delete('myfbconnect');
	
	// delete our Facebook columns
	$db->query("ALTER TABLE " . TABLE_PREFIX . "users DROP `fbdetails`, DROP `fbbday`, DROP `fblocation`, DROP `myfb_uid`");
	
	$info = myfbconnect_info();
	// delete the plugin from cache
	$shadePlugins = $cache->read('shade_plugins');
	unset($shadePlugins[$info['name']]);
	$cache->update('shade_plugins', $shadePlugins);
	
	$PL->templates_delete('myfbconnect');
	
	require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';
	
	find_replace_templatesets('header_welcomeblock_guest', '#' . preg_quote('&mdash; <a href="{$mybb->settings[\'bburl\']}/myfbconnect.php?action=fblogin">{$lang->myfbconnect_login}</a>') . '#i', '');
	
	// rebuild settings
	rebuild_settings();
}

global $mybb, $settings;

if ($settings['myfbconnect_enabled']) {
	$plugins->add_hook('global_start', 'myfbconnect_global');
	$plugins->add_hook('usercp_menu', 'myfbconnect_usercp_menu', 40);
	$plugins->add_hook('usercp_start', 'myfbconnect_usercp');
}

function myfbconnect_global()
{
	
	global $mybb, $lang, $templatelist;
	
	if (!$lang->myfbconnect) {
		$lang->load("myfbconnect");
	}
	
	if (isset($templatelist)) {
		$templatelist .= ',';
	}
	
	if (THIS_SCRIPT == "myfbconnect.php") {
		$templatelist .= 'myfbconnect_register';
	}
	
	if (THIS_SCRIPT == "usercp.php") {
		$templatelist .= 'myfbconnect_usercp_menu';
	}
	
	if (THIS_SCRIPT == "usercp.php" AND $mybb->input['action'] == "myfbconnect") {
		$templatelist .= ',myfbconnect_usercp_settings,myfbconnect_usercp_settings_linkprofile,myfbconnect_usercp_showsettings,myfbconnect_usercp_settings_setting';
	}
}

function myfbconnect_usercp_menu()
{
	
	global $mybb, $templates, $theme, $usercpmenu, $lang, $collapsed, $collapsedimg;
	
	if (!$lang->myfbconnect) {
		$lang->load("myfbconnect");
	}
	
	eval("\$usercpmenu .= \"".$templates->get('myfbconnect_usercp_menu')."\";");
}

function myfbconnect_usercp()
{
	
	global $mybb, $lang;
	
	if (!$lang->myfbconnect) {
		$lang->load('myfbconnect');
	}
	
	/* API LOAD */
	try {
		include_once MYBB_ROOT . "myfbconnect/src/facebook.php";
	}
	catch (Exception $e) {
		error_log($e);
	}
		
	$appID = $mybb->settings['myfbconnect_appid'];
	$appSecret = $mybb->settings['myfbconnect_appsecret'];
		
	// empty configuration
	if (empty($appID) OR empty($appSecret)) {
		error($lang->myfbconnect_error_noconfigfound);
	}
		
	// Create our application instance
	$facebook = new Facebook(array(
		'appId' => $appID,
		'secret' => $appSecret
	));
	/* END API LOAD */
	
	// linking accounts
	if ($mybb->input['action'] == "fblink") {		
		$loginUrl = "/usercp.php?action=do_fblink";
		myfbconnect_login($loginUrl);
	}
	
	// truly link accounts
	if ($mybb->input['action'] == "do_fblink") {
		// get the user
		$user = $facebook->getUser();
		if ($user) {
			$userdata['id'] = $user;
			// true means only link
			myfbconnect_run($userdata, true);
			// inline success support
			if (function_exists(inline_success)) {
				$inlinesuccess = inline_success($lang->myfbconnect_success_linked);
				$mybb->input['action'] = "myfbconnect";
			} else {
				redirect("usercp.php?action=myfbconnect", $lang->myfbconnect_success_linked);
			}
		} else {
			error($lang->myfbconnect_error_noauth);
		}
	}
	
	// settings page
	if ($mybb->input['action'] == 'myfbconnect') {
		global $db, $lang, $theme, $templates, $headerinclude, $header, $footer, $plugins, $usercpnav;
		
		add_breadcrumb($lang->nav_usercp, 'usercp.php');
		add_breadcrumb($lang->myfbconnect_page_title, 'usercp.php?action=myfbconnect');
		
		if ($mybb->request_method == 'post' OR ($facebook->getAccessToken() && $mybb->input['code'])) {
			
			if($mybb->request_method == 'post') {
				verify_post_check($mybb->input['my_post_key']);
			}
			
			$settings = array();
			$settingsToCheck = array(
				"fbdetails",
				"fbbday",
				"fblocation"
			);
			
			// having some fun with variable variables
			foreach ($settingsToCheck as $setting) {
				if ($mybb->input[$setting] == 1) {
					$settings[$setting] = 1;
				} else {
					$settings[$setting] = 0;
				}
			}
			
			if(!$facebook->getUser()) {
				$loginUrl = "/usercp.php?action=myfbconnect&fbdetails={$mybb->input['fbdetails']}&fbbday={$mybb->input['fbbday']}&fblocation={$mybb->input['fblocation']}";
				myfbconnect_login($loginUrl);
			}
			
			if ($db->update_query('users', $settings, 'uid = ' . (int) $mybb->user['uid'])) {
				// inline update that array of data dude!
				$newUser = array_merge($mybb->user, $settings);
				// oh yeah, let's sync!
				myfbconnect_sync($newUser);
				// inline success support
				if (function_exists(inline_success)) {
					$inlinesuccess = inline_success($lang->myfbconnect_success_settingsupdated);
				} else {
					redirect('usercp.php?action=myfbconnect', $lang->myfbconnect_success_settingsupdated, $lang->myfbconnect_success_settingsupdated_title);
				}
			}
		}
		
		$query = $db->simple_select("users", "myfb_uid", "uid = " . $mybb->user['uid']);
		$alreadyThere = $db->fetch_field($query, "myfb_uid");
		
		if ($alreadyThere) {
			$query = $db->simple_select("users", "fbdetails, fbbday, fblocation", "uid = " . $mybb->user['uid']);
			$userSettings = $db->fetch_array($query);
			$settings = "";
			foreach ($userSettings as $setting => $value) {
				// variable variables. Yay!
				$tempKey = 'myfbconnect_settings_' . $setting;
				if ($value == 1) {
					$checked = " checked=\"checked\"";
				} else {
					$checked = "";
				}
				$label = $lang->$tempKey;
				eval("\$settings .= \"".$templates->get('myfbconnect_usercp_settings_setting')."\";");
			}
			eval("\$options = \"".$templates->get('myfbconnect_usercp_settings_showsettings')."\";");
		} else {
			eval("\$options = \"".$templates->get('myfbconnect_usercp_settings_linkprofile')."\";");
		}
		
		eval("\$content = \"".$templates->get('myfbconnect_usercp_settings')."\";");
		output_page($content);
	}
}

/**
 * Main function which logins or registers any kind of Facebook user, provided a valid ID.
 * 
 * @param array The user data containing all the information which are parsed and inserted into the database.
 * @param boolean (optional) Whether to simply link the profile to FB or not. Default to false.
 * @return boolean True if successful, false if unsuccessful.
 **/

function myfbconnect_run($userdata, $justlink = false)
{
	
	global $mybb, $db, $session, $lang;
	
	$user = $userdata;
	
	// See if this user is already present in our database
	if (!$justlink) {
		$query = $db->simple_select("users", "*", "myfb_uid = {$user['id']}");
		$facebookID = $db->fetch_array($query);
	}
	
	// this user hasn't a linked-to-facebook account yet
	if (!$facebookID OR $justlink) {
		// link the Facebook ID to our user if found, searching for the same email
		if ($user['email']) {
			$query = $db->simple_select("users", "*", "email='{$user['email']}'");
			$registered = $db->fetch_array($query);
		}
		// this user is already registered with us, just link its account with his facebook and log him in
		if ($registered OR $justlink) {
			if ($justlink) {
				$db->update_query("users", array(
					"myfb_uid" => $user['id']
				), "uid = {$mybb->user['uid']}");
				return;
			}
			$db->query("users", array(
				"myfb_uid" => $user['id']
			), "email = '{$user['email']}'");
			$db->delete_query("sessions", "ip='" . $db->escape_string($session->ipaddress) . "' AND sid != '" . $session->sid . "'");
			$newsession = array(
				"uid" => $registered['uid']
			);
			$db->update_query("sessions", $newsession, "sid='" . $session->sid . "'");
			
			// let it sync, let it sync
			myfbconnect_sync($registered, $user);
			
			my_setcookie("mybbuser", $registered['uid'] . "_" . $registered['loginkey'], null, true);
			my_setcookie("sid", $session->sid, -1, true);
			
			// redirect
			if ($_SERVER['HTTP_REFERER'] AND strpos($_SERVER['HTTP_REFERER'], "action=fblogin") === false) {
				$redirect_url = htmlentities($_SERVER['HTTP_REFERER']);
			} else {
				$redirect_url = "index.php";
			}
			redirect($redirect_url, $lang->myfbconnect_redirect_loggedin, $lang->sprintf($lang->myfbconnect_redirect_title, $registered['username']));
		}
		// this user isn't registered with us, so we have to register it
		else {
			
			// if we want to let the user choose some infos, then pass the ball to our custom page			
			if (!$mybb->settings['myfbconnect_fastregistration']) {
				header("Location: myfbconnect.php?action=fbregister");
				return;
			}
			
			$newUserData = myfbconnect_register($user);
			if ($newUserData) {
				myfbconnect_sync($newUserData, $user);
				// after registration we have to log this new user in
				my_setcookie("mybbuser", $newUserData['uid'] . "_" . $newUserData['loginkey'], null, true);
				
				if ($_SERVER['HTTP_REFERER'] AND strpos($_SERVER['HTTP_REFERER'], "action=fblogin") === false AND strpos($_SERVER['HTTP_REFERER'], "action=do_fblogin") === false) {
					$redirect_url = htmlentities($_SERVER['HTTP_REFERER']);
				} else {
					$redirect_url = "index.php";
				}
				
				redirect($redirect_url, $lang->myfbconnect_redirect_registered, $lang->sprintf($lang->myfbconnect_redirect_title, $user['name']));
			} else {
				error($lang->myfbconnect_error_unknown);
			}
		}
	}
	// this user has already a linked-to-facebook account, just log him in and update session
	else {
		$db->delete_query("sessions", "ip='" . $db->escape_string($session->ipaddress) . "' AND sid != '" . $session->sid . "'");
		$newsession = array(
			"uid" => $facebookID['uid']
		);
		$db->update_query("sessions", $newsession, "sid='" . $session->sid . "'");
		
		// eventually sync data
		myfbconnect_sync($facebookID, $user);
		
		// finally log the user in
		my_setcookie("mybbuser", $facebookID['uid'] . "_" . $facebookID['loginkey'], null, true);
		my_setcookie("sid", $session->sid, -1, true);
		// redirect the user to where he came from
		if ($_SERVER['HTTP_REFERER'] AND strpos($_SERVER['HTTP_REFERER'], "action=fblogin") === false) {
			$redirect_url = htmlentities($_SERVER['HTTP_REFERER']);
		} else {
			$redirect_url = "index.php";
		}
		redirect($redirect_url, $lang->myfbconnect_redirect_loggedin, $lang->sprintf($lang->myfbconnect_redirect_title, $facebookID['username']));
	}
	
}

/**
 * Registers an user, provided an array with valid data.
 * 
 * @param array The data of the user to register. name and email keys must be present.
 * @return boolean True if successful, false if unsuccessful.
 **/

function myfbconnect_register($user = array())
{
	
	global $mybb, $session, $plugins;
	
	require_once MYBB_ROOT . "inc/datahandlers/user.php";
	$userhandler = new UserDataHandler("insert");
	
	$password = random_str(8);
	
	$newUser = array(
		"username" => $user['name'],
		"password" => $password,
		"password2" => $password,
		"email" => $user['email'],
		"email2" => $user['email'],
		"usergroup" => $mybb->settings['myfbconnect_usergroup'],
		"regip" => $session->ipaddress,
		"longregip" => my_ip2long($session->ipaddress)
	);
	
	$userhandler->set_data($newUser);
	if ($userhandler->validate_user()) {
		$newUserData = $userhandler->insert_user();
		return $newUserData;
	}
	// the username is already in use, let the user choose one from scratch
	else {
		$error = $userhandler->get_errors();
		error($lang->sprintf($lang->myfbconnect_error_usernametaken, $error['username_exists']['data']['0']));
	}
	
}

/**
 * Syncronizes any Facebook account with any MyBB account, importing all the infos.
 * 
 * @param array The existing user data. UID is required.
 * @param array The Facebook user data to sync.
 * @param int Whether to bypass any existing user settings or not. Disabled by default.
 * @return boolean True if successful, false if unsuccessful.
 **/

function myfbconnect_sync($user, $fbdata = array(), $bypass = false)
{
	
	global $mybb, $db, $session, $lang, $plugins;
	
	$userData = array();
	$userfieldsData = array();
	
	// ouch! empty facebook data, we need to help this poor guy!
	if (empty($fbdata)) {
		
		$appID = $mybb->settings['myfbconnect_appid'];
		$appSecret = $mybb->settings['myfbconnect_appsecret'];
		
		// include our API
		try {
			include_once MYBB_ROOT . "myfbconnect/src/facebook.php";
		}
		catch (Exception $e) {
			error_log($e);
		}
		
		// Create our application instance
		$facebook = new Facebook(array(
			'appId' => $appID,
			'secret' => $appSecret
		));
		
		$fbuser = $facebook->getUser();
		if(!$fbuser) {
			myfbconnect_debug($mybb);
		}
		else {
			$fbdata = $facebook->api("/me?fields=id,name,email,cover,birthday,website,gender,bio,location");
		}
	}
	
	$query = $db->simple_select("userfields", "*", "ufid = {$user['uid']}");
	$userfields = $db->fetch_array($query);
	if (empty($userfields)) {
		$userfieldsData['uid'] = $user['uid'];
	}
	
	// facebook id, if empty we need to sync it
	if (empty($user["myfb_uid"])) {
		$userData["myfb_uid"] = $fbdata["id"];
	}
	
	// begin our checkes comparing mybb with facebook stuff, syntax:
	// $usersettings AND !empty(FACEBOOK VALUE) OR $bypass
	
	// avatar
	if (($user['fbdetails'] AND !empty($fbdata['id'])) OR $bypass) {
		$userData["avatar"] = $db->escape_string("http://graph.facebook.com/{$fbdata['id']}/picture?type=large");
		$userData["avatartype"] = "remote";
		
		// Copy the avatar to the local server (work around remote URL access disabled for getimagesize)
		$file = fetch_remote_file($userData["avatar"]);
		$tmp_name = $mybb->settings['avataruploadpath'] . "/remote_" . md5(random_str());
		$fp = @fopen($tmp_name, "wb");
		if ($fp) {
			fwrite($fp, $file);
			fclose($fp);
			list($width, $height, $type) = @getimagesize($tmp_name);
			@unlink($tmp_name);
			if (!$type) {
				$avatar_error = true;
			}
		}
		
		list($maxwidth, $maxheight) = explode("x", my_strtolower($mybb->settings['maxavatardims']));
		
		if (empty($avatar_error)) {
			if ($width AND $height AND $mybb->settings['maxavatardims'] != "") {
				if (($maxwidth AND $width > $maxwidth) OR ($maxheight AND $height > $maxheight)) {
					$avatardims = $maxheight . "|" . $maxwidth;
				}
			}
			if ($width > 0 AND $height > 0 AND !$avatardims) {
				$avatardims = $width . "|" . $height;
			}
			$userData["avatardimensions"] = $avatardims;
		} else {
			$userData["avatardimensions"] = $maxheight . "|" . $maxwidth;
		}
	}
	// birthday
	if (($user['fbbday'] AND !empty($fbdata['birthday'])) OR $bypass) {
		$birthday = explode("/", $fbdata['birthday']);
		$birthday['0'] = ltrim($birthday['0'], '0');
		$userData["birthday"] = $birthday['1'] . "-" . $birthday['0'] . "-" . $birthday['2'];
	}
	// cover, if Profile Picture plugin is installed
	if ((($user['fbdetails'] AND !empty($fbdata['cover']['source'])) OR $bypass) AND $db->field_exists("profilepic", "users")) {
		$cover = $fbdata['cover']['source'];
		$userData["profilepic"] = str_replace('/s720x720/', '/p851x315/', $cover);
		$userData["profilepictype"] = "remote";
		if ($mybb->usergroup['profilepicmaxdimensions']) {
			list($maxwidth, $maxheight) = explode("x", my_strtolower($mybb->usergroup['profilepicmaxdimensions']));
			$userData["profilepicdimensions"] = $maxwidth . "|" . $maxheight;
		} else {
			$userData["profilepicdimensions"] = "851|315";
		}
	}
	
	if(defined(IDEVICELAB)) {
		// sex - iDeviceLAB only
		if (($user['fbdetails'] AND !empty($fbdata['gender'])) OR (!empty($fbdata['gender']) AND empty($userfields['fid3'])) OR $bypass) {
			if ($fbdata['gender'] == "male") {
				$userfieldsData['fid3'] = "Uomo";
			} elseif ($fbdata['gender'] == "female") {
				$userfieldsData['fid3'] = "Donna";
			}
		}
		// name and last name - iDeviceLAB only
		if (($user['fbdetails'] AND !empty($fbdata['name'])) OR (!empty($fbdata['name']) AND empty($userfields['fid10'])) OR $bypass) {
			$userfieldsData['fid10'] = $fbdata['name'];
		}
		// bio - iDeviceLAB only
		if (($user['fbdetails'] AND !empty($fbdata['bio'])) OR (!empty($fbdata['bio']) AND empty($userfields['fid11'])) OR $bypass) {
			$userfieldsData['fid11'] = my_substr($fbdata['bio'], 0, 400, true);
		}
		// location - iDeviceLAB only
		if (($user['fbdetails'] AND !empty($fbdata['location']['name'])) OR (!empty($fbdata['location']['name']) AND empty($userfields['fid1'])) OR $bypass) {
			$userfieldsData['fid1'] = $fbdata['location']['name'];
		}
	}
	
	$plugins->run_hooks("myfbconnect_sync_end", $userData);
	
	// let's do it!
	if (!empty($userData) AND !empty($user['uid'])) {
		$db->update_query("users", $userData, "uid = {$user['uid']}");
	}
	if (!empty($userfieldsData) AND !empty($user['uid'])) {
		if (isset($userfieldsData['uid'])) {
			$db->insert_query("userfields", $userfieldsData);
		} else {
			$db->update_query("userfields", $userfieldsData, "ufid = {$user['uid']}");
		}
	}
	
	return true;
	
}

/**
 * Logins any Facebook user, prompting a permission page.
 * 
 * @param mixed The URL to redirect at the end of the process. Relative URL.
 * @return redirect Redirects with an header() call to the specified URL.
 **/

function myfbconnect_login($url)
{
	global $mybb, $lang;
	
	$appID = $mybb->settings['myfbconnect_appid'];
	$appSecret = $mybb->settings['myfbconnect_appsecret'];
	
	// include our API
	try {
		include_once MYBB_ROOT . "myfbconnect/src/facebook.php";
	}
	catch (Exception $e) {
		error_log($e);
	}
	
	// Create our application instance
	$facebook = new Facebook(array(
		'appId' => $appID,
		'secret' => $appSecret
	));
	
	// empty configuration
	if (empty($appID) OR empty($appSecret)) {
		error($lang->myfbconnect_error_noconfigfound);
	}
	
	if ($mybb->settings['myfbconnect_requestpublishingperms']) {
		$extraPermissions = ", publish_stream";
	}
	
	// get the true login url
	$_loginUrl = $facebook->getLoginUrl(array(
		'scope' => 'user_birthday, user_location, email'.$extraPermissions,
		'redirect_uri' => $mybb->settings['bburl'].$url
	));
	
	// redirect to ask for permissions or to login if the user already granted them
	header("Location: " . $_loginUrl);
}

/**
 * Debugs any type of data.
 * 
 * @param mixed The data to debug.
 * @return mixed The debugged data.
 **/

function myfbconnect_debug($data)
{
	echo "<pre>";
	echo var_dump($data);
	echo "</pre>";
	exit;
}