<?php
/**
 *
 *  Copyright 2014 PrimeXeon Limited
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 *
 */

function solusvmcontrol_hook_clientarea($vars)
{
    global $smarty;

    require_once('solusvmcontrol.php');

    // If user NOT loggedin OR server type != server or SVMC.php isn't there, end now.
    if (!$smarty->_tpl_vars["loggedin"] || $smarty->_tpl_vars["type"] != 'server' || !file_exists(dirname(__file__) . DIRECTORY_SEPARATOR . 'solusvmcontrol.php') ) {
        $smarty->_tpl_vars["SVMC"]["enabled"] = false;
        return;
    }

    // Set enabled flag
    $smarty->_tpl_vars["SVMC"]["enabled"] = true;

    // Check if the product id has been activated in SVMC
    require_once(realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'configuration.php'));
    $conn = mysql_connect($db_host, $db_username, $db_password);
    mysql_select_db($db_name, $conn);
    $result = mysql_query("SELECT count(*) AS count FROM tblcustomfields WHERE relid = ".$vars['pid']." AND (fieldname = 'solusvm_server' OR  fieldname = 'solusvm_api_key' OR fieldname = 'solusvm_api_hash')");
    $real_result = mysql_fetch_assoc($result);
    if($real_result['count'] != 3) {
        $smarty->_tpl_vars["SVMC"]["enabled"] = false;
    }

    // Get the language file
    if (file_exists(dirname(__file__) . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . strtolower($smarty->_tpl_vars["language"]) . '.php')) {
        require_once (dirname(__file__) . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . strtolower($smarty->_tpl_vars["language"]) . '.php');
    } else {
        require_once (dirname(__file__) . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . 'english.php');
    }

    // Decide on SSL or non-SSL URL
    if ($_SERVER['HTTPS'] == 'on' || $_SERVER["SERVER_PORT"] == 443) {
        $whmcsurl = $smarty->_tpl_vars["systemsslurl"];
    } else {
        $whmcsurl = $smarty->_tpl_vars["systemurl"];
    }

    // Assign required javascript to smarty var
    $smarty->_tpl_vars["SVMC"]["js"] = <<<JS
        <script type="text/javascript">
        $(document).ready(function(){
        $('button#status').attr("disabled","true");$('button#boot').attr("disabled","true");$('button#reboot').attr("disabled","true");$('button#shutdown').attr("disabled","true");$('#serverstatus').html('<img src="{$_ADDONLANG['client_hooks_loading_image']}" />');$.ajax({url:"{$whmcsurl}vps.php?pid={$smarty->_tpl_vars["pid"]}&id={$smarty->_tpl_vars["id"]}&action=0",context:document.body,success:function(data){
        $('#serverstatus').html(data)},complete:function(){
        $('button#status').removeAttr("disabled");$('button#boot').removeAttr("disabled");$('button#reboot').removeAttr("disabled");$('button#shutdown').removeAttr("disabled")}});$('button#status').click(function(){
        $('button#status').attr("disabled","true");$('button#boot').attr("disabled","true");$('button#reboot').attr("disabled","true");$('button#shutdown').attr("disabled","true");$('#serverstatus').html('<img src="{$_ADDONLANG['client_hooks_loading_image']}" />');$.ajax({url:"{$whmcsurl}vps.php?pid={$smarty->_tpl_vars["pid"]}&id={$smarty->_tpl_vars["id"]}&action=0",context:document.body,success:function(data){
        $('#serverstatus').html(data)},complete:function(){
        $('button#status').removeAttr("disabled");$('button#boot').removeAttr("disabled");$('button#reboot').removeAttr("disabled");$('button#shutdown').removeAttr("disabled")}})});$('button#boot').click(function(){if(confirm('{$_ADDONLANG['client_hooks_confirm']}')){
        $('button#status').attr("disabled","true");$('button#boot').attr("disabled","true");$('button#reboot').attr("disabled","true");$('button#shutdown').attr("disabled","true");$('#serverstatus').html('<img src="{$_ADDONLANG['client_hooks_loading_image']}" />');$.ajax({url:"{$whmcsurl}vps.php?pid={$smarty->_tpl_vars["pid"]}&id={$smarty->_tpl_vars["id"]}&action=1",context:document.body,success:function(data){
        $('#serverstatus').html(data)},complete:function(){
        $('button#status').removeAttr("disabled");$('button#boot').removeAttr("disabled");$('button#reboot').removeAttr("disabled");$('button#shutdown').removeAttr("disabled")}})}});$('button#shutdown').click(function(){if(confirm('{$_ADDONLANG['client_hooks_confirm']}')){
        $('button#status').attr("disabled","true");$('button#boot').attr("disabled","true");$('button#reboot').attr("disabled","true");$('button#shutdown').attr("disabled","true");$('#serverstatus').html('<img src="{$_ADDONLANG['client_hooks_loading_image']}" />');$.ajax({url:"{$whmcsurl}vps.php?pid={$smarty->_tpl_vars["pid"]}&id={$smarty->_tpl_vars["id"]}&action=2",context:document.body,success:function(data){
        $('#serverstatus').html(data)},complete:function(){
        $('button#status').removeAttr("disabled");$('button#boot').removeAttr("disabled");$('button#reboot').removeAttr("disabled");$('button#shutdown').removeAttr("disabled")}})}});$('button#reboot').click(function(){if(confirm('{$_ADDONLANG['client_hooks_confirm']}')){
        $('button#status').attr("disabled","true");$('button#boot').attr("disabled","true");$('button#reboot').attr("disabled","true");$('button#shutdown').attr("disabled","true");$('#serverstatus').html('<img src="{$_ADDONLANG['client_hooks_loading_image']}" />');$.ajax({url:"{$whmcsurl}vps.php?pid={$smarty->_tpl_vars["pid"]}&id={$smarty->_tpl_vars["id"]}&action=3",context:document.body,success:function(data){
        $('#serverstatus').html(data)},complete:function(){
        $('button#status').removeAttr("disabled");$('button#boot').removeAttr("disabled");$('button#reboot').removeAttr("disabled");$('button#shutdown').removeAttr("disabled")}})}})});
        </script>
JS;

    // Create the smarty buttons & labels
    $smarty->_tpl_vars["SVMC"]["buttonstatus"] = "<button class=\"svmc_b btn info\" id=\"status\">{$_ADDONLANG['client_hooks_button_status']}</button>";
    $smarty->_tpl_vars["SVMC"]["buttonboot"] = "<button class=\"svmc_b btn info\" id=\"boot\">{$_ADDONLANG['client_hooks_button_boot']}</button>";
    $smarty->_tpl_vars["SVMC"]["buttonshutdown"] = "<button class=\"svmc_b btn info\" id=\"shutdown\">{$_ADDONLANG['client_hooks_button_shutdown']}</button>";
    $smarty->_tpl_vars["SVMC"]["buttonreboot"] = "<button class=\"svmc_b btn info\" id=\"reboot\">{$_ADDONLANG['client_hooks_button_reboot']}</button>";

    $smarty->_tpl_vars["SVMC"]["serveractions"] = $_ADDONLANG['client_hooks_actions'];
    $smarty->_tpl_vars["SVMC"]["serverstatus"] = $_ADDONLANG['client_hooks_status'];

}

add_hook("ClientAreaPage", 1, "solusvmcontrol_hook_clientarea");
