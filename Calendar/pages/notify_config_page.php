<?php
# Copyright (c) 2026 Grigoriy Ermolaev (igflocal@gmail.com)
# Calendar plugin for MantisBT is free software:
# you can redistribute it and/or modify it under the terms of the GNU
# General Public License as published by the Free Software Foundation,
# either version 2 of the License, or (at your option) any later version.
#
# Calendar plugin for MantisBT is distributed in the hope
# that it will be useful, but WITHOUT ANY WARRANTY; without even the
# implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# See the GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Calendar plugin for MantisBT.
# If not, see <http://www.gnu.org/licenses/>.

# Recipients of the mail notifications about events: a matrix of the actions
# against the groups of recipients, the counterpart of manage_config_email_page
# of the core.
#
# The whole matrix lives in one configuration option, so a project either
# keeps its own copy of it or follows the global one - which is what the
# colours say. The page is not behind the master switch of the feature: the
# recipients may well be decided before the mails are turned on, so a switched
# off feature is reported rather than hidden.
#
# The scope is the project of the selector in the header, the single point
# where a project is chosen in MantisBT - the page adds no selector of its own.

auth_reauthenticate();

access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

$t_project_id = helper_get_current_project();

# the three levels the colours compare: what the plugin ships with, what the
# administrator set for all projects, and what applies to the current one
$t_notify_default    = plugin_config_get( 'notify_flags', array(), TRUE );
$g_notify_file       = calendar_notify_flags_normalize( $t_notify_default, $t_notify_default );
$g_notify_global     = calendar_notify_flags( ALL_PROJECTS );
$g_notify_project    = calendar_notify_flags( $t_project_id );
$g_notify_project_id = $t_project_id;

# a scope that differs from the one it inherits from has something to revert
if( ALL_PROJECTS == $t_project_id ) {
    $t_has_override = $g_notify_global != $g_notify_file;
} else {
    $t_has_override = $g_notify_project != $g_notify_global;
}

/**
 * Colour of one cell, saying which level decided its value
 * @param string $p_action Row of the matrix.
 * @param string $p_target Column of the matrix.
 * @return string CSS classes of the cell
 */
function calendar_notify_config_cell_class( $p_action, $p_target ) {
    global $g_notify_file, $g_notify_global, $g_notify_project, $g_notify_project_id;

    $t_class = 'center';

    if( $g_notify_global[$p_action][$p_target] != $g_notify_file[$p_action][$p_target] ) {
        $t_class = 'center color-global';
    }

    if( ALL_PROJECTS != $g_notify_project_id
            && $g_notify_project[$p_action][$p_target] != $g_notify_global[$p_action][$p_target] ) {
        $t_class = 'center color-project';
    }

    return $t_class;
}

/**
 * One checkbox of the matrix, named after the cell it stands for
 * @param string $p_action Row of the matrix.
 * @param string $p_target Column of the matrix.
 * @return string HTML of the checkbox
 */
function calendar_notify_config_cell( $p_action, $p_target ) {
    global $g_notify_project;

    $t_checked = $g_notify_project[$p_action][$p_target] == ON ? ' checked="checked"' : '';

    return '<label><input type="checkbox" class="ace" name="flag[]" value="'
            . string_attribute( $p_action . ':' . $p_target ) . '"' . $t_checked
            . ' /><span class="lbl"></span></label>';
}

layout_page_header( plugin_lang_get( 'notify_config_title' ) );

layout_page_begin( 'manage_overview_page.php' );

print_manage_menu( 'manage_plugin_page.php' );
calendar_print_manage_config_menu( plugin_page( 'notify_config_page' ) );

echo '<div class="col-md-12 col-xs-12">' . "\n";
echo '<div class="space-10"></div>' . "\n";

if( !calendar_notify_feature_enabled() ) {
    echo '<div class="alert alert-warning">' . plugin_lang_get( 'notify_config_feature_disabled' ) . '</div>' . "\n";
}

# the note tells the scope of the page in the words of the core, the colours
# below and the revert button tell where the values on it come from
if( ALL_PROJECTS == $t_project_id ) {
    $t_project_title = lang_get( 'config_all_projects' );
} else {
    $t_project_title = sprintf( lang_get( 'config_project' ), string_display_line( project_get_name( $t_project_id ) ) );
}

echo '<div class="well">' . "\n";
echo '<p class="bold"><i class="fa fa-info-circle"></i> ' . $t_project_title . '</p>' . "\n";

echo '<p>' . lang_get( 'colour_coding' ) . '<br />';
if( ALL_PROJECTS != $t_project_id ) {
    echo '<span class="color-project">' . lang_get( 'colour_project' ) . '</span><br />';
}
echo '<span class="color-global">' . lang_get( 'colour_global' ) . '</span></p>' . "\n";
echo '<p class="small">' . plugin_lang_get( 'notify_config_hint' ) . '</p>' . "\n";
echo '</div>' . "\n";

echo '<form id="calendar_notify_config_action" method="post" action="' . plugin_page( 'notify_config' ) . '">' . "\n";
echo form_security_field( 'notify_config' );

echo '<div class="widget-box widget-color-blue2">';
echo '   <div class="widget-header widget-header-small">';
echo '        <h4 class="widget-title lighter uppercase">';
echo '            <i class="ace-icon fa fa-envelope"></i>';
echo plugin_lang_get( 'notify_config_title' );
echo '       </h4>';
echo '   </div>';
echo '   <div class="widget-body">';
echo '   <div class="widget-main no-padding">';
echo '       <div class="table-responsive">';
echo '<table class="table table-striped table-bordered table-condensed checkbox-range-selection">' . "\n";
echo '<thead>' . "\n";
echo '<tr>' . "\n";
echo '<th class="bold" width="40%" rowspan="2">' . plugin_lang_get( 'notify_config_action_column' ) . '</th>' . "\n";
echo '<th class="bold" style="text-align:center" colspan="' . count( calendar_notify_targets() ) . '">'
        . plugin_lang_get( 'notify_config_recipients_column' ) . '</th>' . "\n";
echo '</tr><tr>' . "\n";
foreach( calendar_notify_targets() as $t_target ) {
    echo '<th class="bold" style="text-align:center">&#160;' . plugin_lang_get( 'notify_config_target_' . $t_target ) . '&#160;</th>' . "\n";
}
echo '</tr>' . "\n";
echo '</thead>' . "\n";
echo '<tbody>' . "\n";

foreach( calendar_notify_actions() as $t_action ) {

    echo '<tr>' . "\n";
    echo '  <td>' . plugin_lang_get( 'notify_config_row_' . $t_action ) . '</td>' . "\n";

    foreach( calendar_notify_targets() as $t_target ) {
        echo '  <td class="' . calendar_notify_config_cell_class( $t_action, $t_target ) . '">'
                . calendar_notify_config_cell( $t_action, $t_target ) . '</td>' . "\n";
    }

    echo '</tr>' . "\n";
}

echo '</tbody></table></div>' . "\n";
echo '</div></div></div>' . "\n";
echo '<div class="space-10"></div>' . "\n";

echo '<input type="submit" class="btn btn-primary btn-white btn-round" value="' . plugin_lang_get( 'notify_config_save_button' ) . '" />' . "\n";
echo '</form>' . "\n";

if( $t_has_override ) {

    $t_return_url = plugin_page( 'notify_config_page' );

    echo '<div class="pull-right"><form id="calendar_notify_config_revert" method="post" action="' . plugin_page( 'config_revert' ) . '">' . "\n";
    echo form_security_field( 'config_revert' );
    echo '<input type="hidden" name="revert" value="notify_flags" />' . "\n";
    echo '<input type="hidden" name="project" value="' . $t_project_id . '" />' . "\n";
    echo '<input type="hidden" name="return" value="' . string_attribute( $t_return_url ) . '" />' . "\n";
    echo '<input type="submit" class="btn btn-primary btn-sm btn-white btn-round" value="';
    if( ALL_PROJECTS == $t_project_id ) {
        echo plugin_lang_get( 'notify_config_revert_to_default' );
    } else {
        echo plugin_lang_get( 'notify_config_revert_to_global' );
    }
    echo '" />' . "\n";
    echo '</form></div>' . "\n";
}

echo '</div>' . "\n";

layout_page_end();
