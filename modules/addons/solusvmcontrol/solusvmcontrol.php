<?php

if (!defined("WHMCS"))
    die("This file cannot be accessed directly");


/*
 * Get URL
 */
function get_url($url,$timeout,$path,$querystring)
{
    // Load the Lang file
    if (file_exists(dirname(__file__) . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . strtolower($language) . '.php')) {
        require (dirname(__file__) . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . strtolower($language) . '.php');
    } else {
        require (dirname(__file__) . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . 'english.php');
    }

    // Let PHP split the URL into parts
    $parse_url = parse_url($url);

    // Make sure the port is set
    if(isset($parse_url['port']) && !empty($parse_url["port"]))
        $parse_url['port'] = ":".$parse_url['port'];

    // Define the path
    $path = $path.$querystring;

    // Init var which contains method used
    $method="";

    if (function_exists("curl_exec")) {
        $method="curl_exec";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $parse_url['scheme']."://".$parse_url['host'].$parse_url["port"].$path);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $data = curl_exec($ch);
        $errstr = curl_error($ch);
        curl_close($ch);
    } else {
        $method="fsockopen";
        $fp = fsockopen($url, $parse_url["port"], $errno, $errstr, $timeout);
        if ($fp) {
            $header = "GET " . $path . " HTTP/1.0\r\n";
            $header .= "Host: " . $parse_url['host'] . "\r\n";
            $header .= "Content-type: text/html\r\n";
            $header .= "Connection: close\r\n\r\n";
            $data = "";
            @stream_set_timeout($fp, $timeout);
            @fputs($fp, $header);
            $status = @socket_get_status($fp);
            while (!@feof($fp) && $status) {
                $data .= @fgets($fp, 1024);
                $status = @socket_get_status($fp);
            }
            @fclose($fp);
        }
    }

    // Return success, and data or error
    if (!$data) {
        return array("success"=>false,"data"=>"{$_ADDONLANG['client_connection_failed']} (".$method.":".$errstr.")");
    } else {
        return array("success"=>true,"data"=>$data);
    }
}

/*
 * Open DB Connection
 */
function db_open()
{
    require_once(realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'configuration.php'));
    $conn = mysql_connect($db_host, $db_username, $db_password);
    mysql_select_db($db_name, $conn);
    return $conn;
}

/*
 * Function that handles Config settings in WHMCS
 */
function solusvmcontrol_config()
{
    $configarray = array(
    "name" => "SolusVM Control",
    "description" => "SolusVMControl allows your users to view status, boot, shutdown and reboot their virtual machines that you have resold, in the case where you only have access to a SolusVM user account (aka no access to the SolusVM Master).",
    "version" => "3.0",
    "author" => "<a href=\"http://www.primexeon.com/\">PrimeXeon</a> on <a href=\"https://github.com/PrimeXeonLtd/SolusVMControl\">GitHub</a>",
    "language" => "english",
    "fields" => array(
        "timeout" => array ("FriendlyName" => "Time Out", "Type" => "text", "Size" => "30", "Description" => "The amount of time the script will try to contact the SolusVM Server before giving up", "Default" => "10", ),
    ));
    return $configarray;
}

/*
 *
 */
function solusvmcontrol_summary_tab()
{
    // Make sure subtab is set
    if ( !isset ( $_GET['subtab'] ) )
    {
        $_GET['subtab'] = "active";
    }

    // Tab styling
    $activestyle = " style=\"background-color: #FFF !important;border-bottom:solid 1px white !important;\"";
    $out_inactive = false;
    $out_active = false;
    $out_all = false;
    switch ($_GET['subtab']) {
        case "inactive":
            $active_in = $activestyle;
            $out_inactive = true;
            break;
        case "all":
            $active_al = $activestyle;
            $out_all = true;
            break;
        case "active":
        default:
            $active_ac = $activestyle;
            $out_active = true;
            break; // This looks a bit confusing, but basically case "active" is the default
    }

    // Init arrays for output
    $active = "";
    $inactive = "";
    $all = "";

    // Init query
    db_open();
    $svmc_servers_query = "SELECT group_concat(DISTINCT relid SEPARATOR ',') AS svmc_servers FROM tblcustomfields WHERE (fieldname = 'solusvm_server' OR  fieldname = 'solusvm_api_key' OR fieldname = 'solusvm_api_hash')";
    $svmc_servers_result = mysql_query($svmc_servers_query);

    // Get the login Credentials into an assoc array
    $svmc_servers = mysql_fetch_assoc($svmc_servers_result);

    if ( empty ( $svmc_servers["svmc_servers"] ) ) {
        $out = "<tr><td colspan=\"3\">SolusVMControl is not currently active on any of your VPS.</td></tr>";
    } else {
        // Get all VPS
        $all_vps_result = mysql_query("SELECT tblhosting.*, tblproducts.name FROM tblhosting, tblproducts WHERE tblhosting.packageid IN ({$svmc_servers["svmc_servers"]}) AND tblhosting.packageid = tblproducts.id");

        $all_vps=null;
        while($row = mysql_fetch_assoc($all_vps_result))
        {
            $all_vps[] = $row;
        }

        // Itterate through $all_vps
        foreach ($all_vps as $vps) {
            // Get credentials for $vps
            $vps_svmc_settings_result = mysql_query("SELECT tblcustomfieldsvalues.relid,fieldname,value FROM tblcustomfields, tblcustomfieldsvalues WHERE tblcustomfields.id = tblcustomfieldsvalues.fieldid AND fieldname LIKE 'solusvm_%' AND tblcustomfieldsvalues.relid = '" .
                $vps['id'] . "'");

            $vps_svmc_settings=null;
            while($row = mysql_fetch_assoc($vps_svmc_settings_result))
            {
                $vps_svmc_settings[] = $row;
            }

            // Pick up VPS with no details logged
            if (sizeof($vps_svmc_settings) < 3) {
                $inactive[$vps['id']]['name'] = $vps['name'];
                $inactive[$vps['id']]['domain'] = rtrim($vps['domain'], '. ');
                $inactive[$vps['id']]['id'] = $vps['id'];
            }

            // Sort the results
            foreach ($vps_svmc_settings as $vps_svmc_settings_item) {
                // Create output arrays
                $active[$vps['id']][$vps_svmc_settings_item['fieldname']] = $vps_svmc_settings_item['value'];
                $active[$vps['id']]['name'] = $vps['name'];
                $active[$vps['id']]['domain'] = rtrim($vps['domain'], '. ');
                $active[$vps['id']]['id'] = $vps['id'];

            }

        }

    }

    // Check through the active ones (as some may not actually be active (empty))
    if ( !empty ( $active ) )
    {
        foreach ($active as $active_item) {
            if (empty($active_item['solusvm_api_key']) || empty($active_item['solusvm_api_hash']) ||
                empty($active_item['solusvm_server'])) {
                $inactive[] = $active_item;
                unset($active[$active_item['id']]);
            }
        }
    }

    if ($out_active)
    {
        $outrows = $active;
    }
    else if ($out_inactive) {
        $outrows = $inactive;
    }
    else if ($out_all)
    {
        $outrows = array_merge($active, $inactive);
    }

    if ( !empty ( $outrows ) )
    {
        $i=0;
        foreach ($outrows as $row) {
            if( $i % 2 )
                $switch = "";
            else
                $switch = "background-color:#F5F5F5";

            $out .= "\n\t\t\t<tr style=\"height:50px;{$switch}\">" . "\n\t\t\t\t<td>{$row['name']}</td>" . "\n\t\t\t\t<td><a href=\"clientshosting.php?id={$row['id']}\">{$row['domain']}</a></td>";

            if (($out_active || $out_all) && !empty($row['solusvm_server'])) {
                $out .= "\n\t\t\t\t<td>Server: {$row['solusvm_server']}<br />API Key: {$row['solusvm_api_key']}<br />API Hash:{$row['solusvm_api_hash']}</td>";
            } else {
                $out .= "\n\t\t\t\t<td>&nbsp;</td>";
            }

            $out .= "<td style=\"text-align:rigtht\"><a href=\"clientshosting.php?id={$row['id']}\" style=\"background-color: #339BB9;background-repeat: repeat-x;
                                background-image: -webkit-linear-gradient(top, #5bc0de, #339bb9);cursor: pointer;padding: 5px 14px 6px;
                                border: 1px solid #CCC;border: 1px solid #CCC;-webkit-transition: 0.1s linear all;-webkit-border-radius: 4px;
                                -moz-border-radius: 4px;border-radius: 4px;text-decoration:none;color:#FFF\">Edit Settings</a></td>\n\t\t\t</tr>";
            $i++;
        }
    }

    if ( empty ( $active_ac ) )
        $active_ac = "";

    if ( empty ( $active_in ) )
        $active_in = "";

    if ( empty ( $active_al ) )
        $active_al = "";

    return "
        <div id=\"clienttabs\">
        	<ul>
        		<li class=\"tab\"><a href=\"addonmodules.php?module=solusvmcontrol&tab=summary&subtab=active\"{$active_ac}>Active</a></li>
        		<li class=\"tab\"><a href=\"addonmodules.php?module=solusvmcontrol&tab=summary&subtab=inactive\"{$active_in}>Inactive</a></li>
        		<li class=\"tab\"><a href=\"addonmodules.php?module=solusvmcontrol&tab=summary&subtab=all\"{$active_al}>All</a></li>
            </ul>
        </div>

        <div id=\"tab0box\" class=\"tabbox\">
            <div id=\"tab_content\" style=\"text-align:left;\">
                <table id=\"box-table-a\" summary=\"SolsuVMControl\" width=\"100%\" cellspacing=\"0\">
                    <thead>
                        <tr style=\"height:50px;\">
                            <th scope=\"col\" width=\"250\" style=\"border-bottom: solid 3px #333\">SolusVM Package</th>
                            <th scope=\"col\" width=\"300\" style=\"border-bottom: solid 3px #333\">Domain</th>
                            <th scope=\"col\" style=\"border-bottom: solid 3px #333\">Details</th>
                            <th scope=\"col\" style=\"border-bottom: solid 3px #333\">Edit Settings</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$out}
                    </tbody>
                </table>
            </div>
        </div>
    ";
}

function solusvmcontrol_enable_tab_update()
{
    // Check if a request has been set to enable/disable SVMC for a specific package
    if(is_numeric($_POST['server']))
    {
        // Check SolusVMControl is not already enabled for the product
        $num_svmc_rows_result = mysql_query("SELECT count(*) AS count FROM tblcustomfields WHERE relid = ? AND (fieldname = 'solusvm_server' OR  fieldname = 'solusvm_api_key' OR fieldname = 'solusvm_api_hash')", array ( $_POST['server'] ) );
        $num_svmc_rows = mysql_fetch_assoc($num_svmc_rows_result);

        if( (int)$num_svmc_rows['count'] == 0 )
        {
            // Check if SVMC is already enabled for the product
            if ( $_POST['action'] == 'enable' )
            {
                // Enable SolusVMControl by adding the fields to the product
                mysql_query( "INSERT INTO tblcustomfields (type,relid,fieldname,fieldtype,description,fieldoptions,regexpr,adminonly,required,showorder,sortorder)
                                VALUES ('product',{$_POST['server']},'solusvm_server'  ,'text','','','','on','','','0'),
                                       ('product',{$_POST['server']},'solusvm_api_key', 'text','','','','on','','','0'),
                                       ('product',{$_POST['server']},'solusvm_api_hash','text','','','','on','','','0')");

            // SVMC is probably activated
            }
            else if ( $_POST['action'] == 'disable' )
            {
                // Disable SolusVMControl for the product
                mysql_query( "DELETE FROM tblcustomfields WHERE type = 'product' AND relid = {$_POST['server']} AND adminonly = 'on' AND
                            ( fieldname = 'solusvm_server' OR
                              fieldname = 'solusvm_api_key' OR
                              fieldname = 'solusvm_api_hash' )");
            }

        }

    }

}

function solusvmcontrol_enable_tab()
{
    // Tab styling
    $activestyle = " style=\"background-color: #FFF !important;border-bottom:solid 1px white !important;\"";
    $out_inactive = false;
    $out_active = false;
    $out_all = false;
    switch ($_GET['subtab']) {
        case 'inactive':
            $active_in = $activestyle;
            $out_inactive = true;
            break;
        case 'all':
            $active_al = $activestyle;
            $out_all = true;
            break;
        case 'active':
        default:
            $active_ac = $activestyle;
            $out_active = true;
            break; // This looks a bit confusing, but basically case "active" is the default
    }

    // Init arrays for output
    $active = "";
    $inactive = "";
    $all = "";

    // Perform updates if required (enable/disable SVMC for selected product)
    solusvmcontrol_enable_tab_update();

    // Get list of products that SolusVMControl is enabled for
    $svmc_products_result = mysql_query( "SELECT DISTINCT(relid) FROM tblcustomfields WHERE (fieldname = 'solusvm_server' OR  fieldname = 'solusvm_api_key' OR fieldname = 'solusvm_api_hash')" );

    $svmc_products=null;
    while($row = mysql_fetch_assoc($svmc_products_result))
    {
        $svmc_products[] = $row;
    }

    foreach ($svmc_products as $svmc_product)
    {
        $ids[$svmc_product['relid']] = true;
    }

    // Get all appropriate products
    $all_products_result = mysql_query( "SELECT * FROM tblproducts WHERE type = 'server' ORDER BY id ASC" );
    $all_products = null;
    while($row = mysql_fetch_assoc($all_products_result))
    {
        $all_products[] = $row;
    }

    foreach ($all_products AS $product)
    {
        if($ids[$product['id']]) // already enabled
        {
            $active[$product['id']]['id'] = $product['id'];
            $active[$product['id']]['name'] = $product['name'];
            $active[$product['id']]['action'] = "disable";
            $active[$product['id']]['enable'] = " disabled";
        } else
        {
            $inactive[$product['id']]['id'] = $product['id'];
            $inactive[$product['id']]['name'] = $product['name'];
            $inactive[$product['id']]['action'] = "enable";
            $inactive[$product['id']]['disable'] = " disabled";
        }
    }

    // Get correct output array
    if ($out_active) {
        $outrows = $active;
    } else
        if ($out_inactive) {
            $outrows = $inactive;
        } else
            if ($out_all) {
                $outrows = array_merge($active, $inactive);
            }

    $i=0;
    foreach ($outrows as $row) {
        if( $i % 2 )
            $switch = "";
        else
            $switch = "background-color:#F5F5F5";

        if ( $row['action'] == "enable" )
        {
            $enable = "submit";
            $disable = "button";
        } else
        {
            $enable = "button";
            $disable = "submit";
        }

        $out .= "\n\t\t\t<tr style=\"{$switch};height:50px;\">" .
                "\n\t\t\t\t<td>{$row['name']}</td>" .
                "\n\t\t\t\t<td>".
				"\n\t\t\t\t\t<form action=\"addonmodules.php?module=solusvmcontrol&tab=enable\" method=\"post\">".
                	"<input type=\"hidden\" name=\"server\" value=\"{$row['id']}\" />".
                	"<input type=\"hidden\" name=\"action\" value=\"{$row['action']}\" />".
					"<input type=\"{$enable}\" value=\"Enable\" {$row['enable']} />".
					"<input type=\"{$disable}\" value=\"Disable\" {$row['disable']} />".
				"</form>".
                "\n\t\t\t\t</td>".
                "\n\t\t\t</tr>";
        $i++;
    }

    return "
        <div id=\"clienttabs\">
        	<ul>
        		<li class=\"tab\"><a href=\"addonmodules.php?module=solusvmcontrol&tab=enable&subtab=active\"{$active_ac}>Active</a></li>
        		<li class=\"tab\"><a href=\"addonmodules.php?module=solusvmcontrol&tab=enable&subtab=inactive\"{$active_in}>Inactive</a></li>
        		<li class=\"tab\"><a href=\"addonmodules.php?module=solusvmcontrol&tab=enable&subtab=all\"{$active_al}>All</a></li>
            </ul>
        </div>

        <div id=\"tab0box\" class=\"tabbox\">
            <div id=\"tab_content\" style=\"text-align:left;\">
                <table id=\"box-table-a\" summary=\"SolsuVMControl\" width=\"100%\" cellspacing=\"0\">
                    <thead>
                        <tr style=\"height:50px;\">
                            <th scope=\"col\" width=\"300\" style=\"border-bottom: solid 3px #333\">SolusVM Package</th>
                            <th scope=\"col\" style=\"border-bottom: solid 3px #333\">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$out}
                    </tbody>
                </table>
            </div>
        </div>
    ";
}


/*
 * Function that handles main output in WHMCS admin
 */
function solusvmcontrol_output($vars)
{
    $modulelink = $vars['modulelink'];
    $version = $vars['version'];
    $timeout = $vars['timeout'];
    $LANG = $vars['_lang'];
    $configpath = realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'configuration.php');

    // Tab styling
    $activestyle = " style=\"background-color: #FFF !important;border-bottom:solid 1px white !important;\"";
    $active_s = "";
    $active_e = "";
    $active_h = "";
    switch ($_GET['tab']) {
        case "enable":
            $active_e = $activestyle;
            $out = solusvmcontrol_enable_tab();
            break;
        case "summary":
        default:
            $active_s = $activestyle;
            $out = solusvmcontrol_summary_tab();
            break; // This looks a bit confusing, but basically case "summary" is the default
    }

    // Tabbed output area
    echo "  <div style=\"display:inline-block;text-align:center\">
                <a href=\"https://www.primexeon.com/clients/submitticket.php?step=2&deptid=2\"><img src=\"http://www.primexeon.com/addons/SVMC/images/support.png\" width=\"100\" height=\"100\" alt=\"Support\"/></a><br />
                <span><a href=\"https://www.primexeon.com/clients/submitticket.php?step=2&deptid=2\">Support</a></span>
            </div>

            <div style=\"display:inline-block;text-align:center\">
                <a href=\"http://www.primexeon.com/clients/knowledgebase/104/FAQ.html\"><img src=\"http://www.primexeon.com/addons/SVMC/images/faq.png\" width=\"100\" height=\"100\" alt=\"FAQ\"/></a><br />
                <span><a href=\"http://www.primexeon.com/clients/knowledgebase/104/FAQ.html\">FAQ's</a></span>
            </div>

            <div style=\"clear:left\"></div>
            <br /><br />

            <div id=\"clienttabs\" stye=\"margin: 0 280px 0 0 !important; margin-right: 280px !important\">
            	<ul stye=\"margin: 0 280px 0 0 !important;\">
            		<li class=\"tab\"><a href=\"{$vars['modulelink']}&tab=summary\"{$active_s}>Summary</a></li>
            		<li class=\"tab\"><a href=\"{$vars['modulelink']}&tab=enable\"{$active_e}>Enable for Product</a></li>
            		<!--<li class=\"tab\"><a href=\"{$vars['modulelink']}&tab=instructions\"{$active_i}>Instructions</a></li>
            		<li class=\"tab\"><a href=\"{$vars['modulelink']}&tab=help\"{$active_h}>Help</a></li>-->
            	</ul>
            </div>

            <div id=\"tab0box\" class=\"tabbox\" stye=\"margin: 0 280px 0 0 !important;\">
              <div id=\"tab_content\" style=\"text-align:left;\">{$out}</div>
            </div>

            <div style=\"margin: 10px 0 0 0;text-align:center\">Copyright &copy; <a href=\"http://www.primexeon.com\" target=\"_blank\">PrimeXeon</a> " . date('Y') . ".</div>";
}

/*
 * Function handles the right hand sidebar in the WHMCS admin
 */
function solusvmcontrol_sidebar($vars)
{
    $modulelink = $vars['modulelink'];
    $version = $vars['version'];
    $LANG = $vars['_lang'];

    $sidebar = '<span class="header" style="background-color:transparent"><h1>SolusVM Control</h1></span>
        GitHub: <br />'.$active_addons.'
	Version: '.$version.'
    ';
    return $sidebar;

}

/*
 * Function that handles all of the ajax requests
 */
function vps(){

    global $_ADDONLANG;

    // Get the language file
    if (file_exists(dirname(__file__) . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . strtolower($language) . '.php')) {
        require_once (dirname(__file__) . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . strtolower($language) . '.php');
    } else {
        require_once (dirname(__file__) . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . 'english.php');
    }

    // Check the required params are set
    if( !is_numeric($_GET['pid']) || !is_numeric($_GET['id']) || !is_numeric($_GET['action']) )
    {
        echo "<font style=\"color:red;\"><b>{$_ADDONLANG['client_params_not_set']}</b></font>";
        return;
    }

    // Check the action is valid
    if( $_GET['action'] < 0 || $_GET['action'] > 3)
    {
        echo "<font style=\"color:red;\"><b>{$_ADDONLANG['client_invalid_action']}</b></font>";
        return;
    }

    // EVERYTHING CHECKS OUT, SO LETS GO //

    // Get API Credentials from DB
    $get_api_credentials = "SELECT tblcustomfields.fieldname,  tblcustomfieldsvalues.value
                                FROM tblcustomfields, tblcustomfieldsvalues
                                WHERE tblcustomfields.relid = ".$_GET['pid']."
                                    AND tblcustomfieldsvalues.relid = ".$_GET['id']."
                                    AND tblcustomfields.id =  tblcustomfieldsvalues.fieldid
				    AND (fieldname = 'solusvm_server' OR  fieldname = 'solusvm_api_key' OR fieldname = 'solusvm_api_hash')";

    unset($result);$result = mysql_query($get_api_credentials);

    if( mysql_num_rows($result) != 3 ) // Check there are the right number
    {
        echo "<font style=\"color:red;\"><b>{$_ADDONLANG['client_service_not_enabled']}</b></font>";
        return;
    }

    // Get the login Credentials into an assoc array
    $api_credentials=null;
    while ($row = mysql_fetch_assoc($result)) {
        $api_credentials[$row["fieldname"]] = $row["value"];
    }

    // Translate the action id to action
    switch($_GET['action']) {
        case 0: // Status
            $action = "status";
            break;
        case 1: // Boot
            $action = "boot";
            break;
        case 2: // Shutdown
            $action = "shutdown";
            break;
        case 3: // Reboot
            $action = "reboot";
            break;
    }

    $get_url = get_url($api_credentials["solusvm_server"],
                       $timeout,
                       "/api/client/command.php?",
                       "key=".$api_credentials["solusvm_api_key"]."&hash=".$api_credentials["solusvm_api_hash"]."&action=".$action
                       );

    // Failed to Connect to Host Node
    if(!$get_url["success"])
    {
        echo "<font style=\"color:red;\"><b>".$get_url["data"]."</b></font>";
        return;
    }

    // IF WE'RE HERE, WE'RE PROBABLY IN BUSINESS //

    // Parse returned data
    preg_match_all('/<(.*?)>([^<]+)<\/\\1>/i', $get_url["data"], $match);
    $result = array();
    foreach ($match[1] as $x => $y)
    {
        $result[$y] = $match[2][$x];
    }

    // Check the server didn't return an error
    if($result["status"] == "error")
    {
        echo "<font style=\"color:red;\"><b>".$result["statusmsg"]." (:statusmsg)</b></font>";
        return;
    }

    // Now give some output
    if ($result["status"] == "success"){
        if ($result["statusmsg"] == "online")
        {
            echo "<font style=\"color:green;\"><b>{$_ADDONLANG['client_status_online']}</b></font>";
            return;
        }
        elseif ($result["statusmsg"] == "offline")
        {
            echo "<font style=\"color:red;\"><b>{$_ADDONLANG['client_status_offline']}</b></font>";
            return;
        }
        elseif ($result["statusmsg"] == "rebooted")
        {
            echo "<font style=\"color:green;\"><b>{$_ADDONLANG['client_status_reboot_init']}</b></font>";
            return;
        }
        elseif ($result["statusmsg"] == "shutdown")
        {
            echo "<font style=\"color:green;\"><b>{$_ADDONLANG['client_status_shutdown_init']}</b></font>";
            return;
        }
        elseif ($result["statusmsg"] == "booted")
        {
            echo "<font style=\"color:green;\"><b>{$_ADDONLANG['client_status_boot_init']}</b></font>";
            return;
        }
        else
        {
            echo "<font style=\"color:orange;\"><b{$_ADDONLANG['client_status_unknown']}></b></font>";
            return;
        }
    }
}
