<?php
// Set the required paramaters to set the page up
define("CLIENTAREA",true);
//define("FORCESSL",true); // Uncomment to force the page to use https://

require("init.php");
$ca = new WHMCS_ClientArea();
$ca->initPage();

// Load the Lang file
if (file_exists(dirname(__file__) . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . 'solusvmcontrol' . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . strtolower($language) . '.php')) {
    require (dirname(__file__) . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . 'solusvmcontrol' . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . strtolower($language) . '.php');
} else {
    require (dirname(__file__) . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . 'solusvmcontrol' . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . 'english.php');
}

// Check login status
if ($ca->isLoggedIn()) {
    // User is logged in, check product belongs to user
    $user_check = "SELECT count(id) AS count FROM tblhosting WHERE id = '" . (int)$_GET['id'] . "' AND userid = '" . $ca->getUserID() . "' AND packageid = '" . (int)$_GET['pid'] . "' LIMIT 1";
    $result = mysql_query($user_check);
    $row = mysql_fetch_assoc($result);
    if($row["count"] != 1) {
        die("<font style=\"color:red;\"><b>{$_ADDONLANG['client_product_doesnt_belong_to_user']}</b></font>");
    }

    //** All checks out so far **//

    // Require SVMC functions
    require_once('modules/addons/solusvmcontrol/solusvmcontrol.php');

    // Call vps()
    vps();


/**
 * 	// User is logged in
 * 	$result = mysql_query("SELECT firstname FROM tblclients WHERE id=".$ca->getUserID());
 * 	$data = mysql_fetch_array($result);
 * 	$clientname = $data[0];

 * 	echo $clientname;
 */
} else {
	// User is not logged in
	die("<font style=\"color:red;\"><b>{$_ADDONLANG['client_not_logged_in']}</b></font>");
}
