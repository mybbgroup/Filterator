<?php
/*
 *
 * BETTER Prefix Filter Plugin
 * 
 * All credits go to the original author - Mostafa Shiraali!
 * 
 * Our Changes to the original plugin: 
 * - auto hide prefix filter if no thread prefixes are allowed in the forum
 * - optimise queries, now it does not use one query for one thread prefix
 * - display thread name on mouse hover (title)
 * - small changes in settings
 * 
*/

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

// Plugin info
function PrefixFilter_info() {
    global $mybb, $db, $lang;
    $lang->load("PrefixFilter");
    return ["name" => $lang->pxfr, "description" => $lang->pxfr_inf_des, "website" => "https://mypgr.ir", "author" => "Original author: Mostafa Shiraali (optimised and edited by Supryk and Eldenroot)", "authorsite" => "https://mypgr.ir", "version" => "2.0", "codename" => "", "compatibility" => "18*"];
}

// Plugin activate
function PrefixFilter_activate() {
    global $mybb, $db, $lang;
    $lang->load("PrefixFilter");
    require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';
    find_replace_templatesets("forumdisplay_threadlist", "#^#i", '{\$prefixfilter}');
    $settings_group = ["name" => "PrefixFilter", "title" => $lang->pxfr, "description" => $lang->pxfr_des, "disporder" => "1", "isdefault" => "0"];
    $db->insert_query("settinggroups", $settings_group);
    $gid = $db->insert_id();
    $setting[] = ["name" => "PrefixFilter_enable", "title" => $lang->pxfr_act_opt, "description" => $lang->pxfr_act_opt_des, "optionscode" => "yesno", "value" => "1", "disporder" => 1, "gid" => intval($gid) ];
    $setting[] = ["name" => "PrefixFilter_groups", "title" => $lang->pxfr_grp_opt, "description" => $lang->pxfr_grp_opt_des, "optionscode" => "groupselect", "value" => "4", "disporder" => 2, "gid" => intval($gid) ];
    $setting[] = ["name" => "PrefixFilter_forums", "title" => $lang->pxfr_frm_opt, "description" => $lang->pxfr_frm_opt_des, "optionscode" => "forumselect", "value" => "-1", "disporder" => 3, "gid" => intval($gid) ];
    foreach ($setting as $i) {
        $db->insert_query("settings", $i);
    }
    rebuild_settings();
    
    // Add plugin templates
    $db->insert_query("templates", ["title" => "PrefixFilter", "template" => $db->escape_string('<div style="width: 88%;display: inline-block;margin: 10px 0px 11px 5px;">{$lang->pxfr_fbp} {$prefixes}<a style="margin: 0 3px 0 4px;text-decoration: aliceblue;" href="{$mybb->settings[\'bburl\']}/forumdisplay.php?fid={$fid}">{$lang->pxfr_all}</a></div>'), "sid" => 1]);
    $db->insert_query("templates", ["title" => "PrefixFilter_Prefix", "template" => $db->escape_string('<a title="{$p[\'prefix\']}" style="margin: 0 3px 0 4px;text-decoration: aliceblue;" href="{$mybb->settings[\'bburl\']}/forumdisplay.php?fid={$fid}&prefix={$p[\'pid\']}">{$p[\'displaystyle\']}</a>'), "sid" => 1]);
}

// Plugin deactivate
function PrefixFilter_deactivate() {
    global $mybb, $db;
    require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';
    // Delete template insertions
    find_replace_templatesets("forumdisplay_threadlist", '#' . preg_quote('{$prefixfilter}') . '#i', '', 0);
    // Delete plugin settings in ACP and plugin templates
    $db->query("DELETE FROM " . TABLE_PREFIX . "settinggroups WHERE name='PrefixFilter'");
    $db->delete_query("settings", "name IN ('PrefixFilter_enable','PrefixFilter_groups','PrefixFilter_forums')");
    $db->delete_query("templates", "title IN ('PrefixFilter', 'PrefixFilter_Prefix')");
    rebuild_settings();
}

// Black magic starts - use caching and save one query per one thread prefix
$plugins->add_hook('forumdisplay_threadlist', 'PrefixFilter_forumdisplay_threadlist');
$plugins->add_hook('forumdisplay_thread_end', 'PrefixFilter_forumdisplay_thread_end');
$plugins->add_hook('global_start', 'PrefixFilter_global_start');

function PrefixFilter_forumdisplay_thread_end() {
    global $mybb, $threadprefix, $pr, $fid;
    if ($mybb->settings['PrefixFilter_forums'] == "" || ($mybb->settings['PrefixFilter_forums'] != "-1" && !in_array($fid, explode(",", $mybb->settings['PrefixFilter_forums'])))) {
        return;
    }
    if (!is_member($mybb->settings['PrefixFilter_groups'])) {
        return;
    }
    if (!empty($threadprefix)) {
        if (empty($pr[$threadprefix['pid']])) {
            $pr[$threadprefix['pid']] = array("pid" => $threadprefix['pid'], "prefix" => $threadprefix['prefix'], "displaystyle" => $threadprefix['displaystyle'],);
        }
    }
}

function PrefixFilter_forumdisplay_threadlist() {
    global $lang, $mybb, $fid, $prefixfilter, $pr, $templates;
    $lang->load("PrefixFilter");
    if (!empty($pr)) {
        foreach ($pr as $p) {
            $p['prefix'] = htmlspecialchars_uni($p['prefix']);
            eval("\$prefixes .= \"" . $templates->get("PrefixFilter_Prefix") . "\";");
        }
        if (!empty($prefixes)) {
            eval("\$prefixfilter = \"" . $templates->get("PrefixFilter") . "\";");
        }
    }
}

function PrefixFilter_global_start() {
    global $templatelist;
    if (in_array(THIS_SCRIPT, explode(",", "forumdisplay.php"))) {
        if (isset($templatelist)) {
            $templatelist.= ",";
        }
        $templatelist.= "PrefixFilter, PrefixFilter_Prefix";
    }
}
