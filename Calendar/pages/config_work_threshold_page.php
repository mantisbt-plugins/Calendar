<?php
# Copyright (c) 2019 Grigoriy Ermolaev (igflocal@gmail.com)
# 
# Calendar for MantisBT is free software: 
# you can redistribute it and/or modify it under the terms of the GNU
# General Public License as published by the Free Software Foundation, 
# either version 2 of the License, or (at your option) any later version.
#
# Calendar plugin for for MantisBT is distributed in the hope 
# that it will be useful, but WITHOUT ANY WARRANTY; without even the 
# implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
# See the GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Customer management plugin for MantisBT.  
# If not, see <http://www.gnu.org/licenses/>.

auth_reauthenticate();

layout_page_header( lang_get( 'manage_threshold_config' ) );

access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

layout_page_begin( 'manage_overview_page.php' );

print_manage_menu( 'manage_plugin_page.php' );
calendar_print_manage_config_menu( plugin_page( 'config_work_threshold_page' ) );

$g_user        = auth_get_current_user_id();
$g_project_id  = helper_get_current_project();
$t_show_submit = false;

$g_access_levels = MantisEnum::getAssocArrayIndexedByValues( config_get( 'access_levels_enum_string' ) );
$g_overrides     = array();

/**
 * Set overrides
 * @param string $p_config Configuration value.
 * @return void
 */
function set_overrides( $p_config ) {
    global $g_overrides;
    if( !in_array( $p_config, $g_overrides ) ) {
        $g_overrides[] = $p_config;
    }
}

/**
 * Section header
 * @param string $p_section_name Section name.
 * @return void
 */
function get_section_begin_mcwt( $p_section_name ) {
    global $g_access_levels;

    echo '<div class="space-10"></div>';
    echo '<div class="widget-box widget-color-blue2">';
    echo '   <div class="widget-header widget-header-small">';
    echo '        <h4 class="widget-title lighter uppercase">';
    echo '            <i class="ace-icon fa fa-sliders"></i>';
    echo $p_section_name;
    echo '       </h4>';
    echo '   </div>';
    echo '   <div class="widget-body">';
    echo '   <div class="widget-main no-padding">';
    echo '       <div class="table-responsive">';
    echo '<table class="table table-striped table-bordered table-condensed">';
    echo '<thead>';
    echo '<tr>';
    echo '<th class="bold" width="40%" rowspan="2">' . lang_get( 'perm_rpt_capability' ) . '</th>';
    echo '<th class="bold" style="text-align:center"  width="40%" colspan="' . count( $g_access_levels ) . '">' . lang_get( 'allowed_access_levels' ) . '</th>';
//	echo '<th class="bold" style="text-align:center" rowspan="2">&#160;' . lang_get( 'alter_level' ) . '&#160;</th>';
    echo '</tr><tr>';
    foreach( $g_access_levels as $t_access_level => $t_access_label ) {
        echo '<th class="bold" style="text-align:center">&#160;' . MantisEnum::getLabel( lang_get( 'access_levels_enum_string' ), $t_access_level ) . '&#160;</th>';
    }
    echo '</tr>' . "\n";
    echo '</thead>';
    echo '<tbody>';
}

/**
 * Defines the cell's background color and sets the overrides
 * @param string  $p_threshold    Configuration option.
 * @param string  $p_file         System default value.
 * @param string  $p_global       All projects value.
 * @param string  $p_project      Current project value.
 * @param boolean $p_set_override If true, will define an override if needed.
 * @return string HTML tag attribute for background color override
 */
function set_color( $p_threshold, $p_file, $p_global, $p_project, $p_set_override ) {
    global $g_project_id;

    $t_color = '';

    # all projects override
    if( $p_global != $p_file ) {
        $t_color = 'color-global';
        if( $p_set_override && ALL_PROJECTS == $g_project_id ) {
            set_overrides( $p_threshold );
        }
    }

    # project overrides
    if( $p_project != $p_global ) {
        $t_color = 'color-project';
        if( $p_set_override && ALL_PROJECTS != $g_project_id ) {
            set_overrides( $p_threshold );
        }
    }

    return $t_color;
}

/**
 * Get row
 * @param string  $p_caption           Caption.
 * @param string  $p_threshold         Threshold.
 * @param boolean $p_all_projects_only All projects only.
 * @return void
 */
function get_capability_row( $p_caption, $p_threshold, $p_all_projects_only = false ) {
    global $g_user, $g_project_id, $g_access_levels;

    $t_file = plugin_config_get( $p_threshold, NULL, TRUE );
    if( !is_array( $t_file ) ) {
        $t_file_exp = array();
        foreach( $g_access_levels as $t_access_level => $t_label ) {
            if( $t_access_level >= $t_file ) {
                $t_file_exp[] = $t_access_level;
            }
        }
    } else {
        $t_file_exp = $t_file;
    }

    $t_global = plugin_config_get( $p_threshold, null, FALSE, ALL_USERS, ALL_PROJECTS );
    if( !is_array( $t_global ) ) {
        $t_global_exp = array();
        foreach( $g_access_levels as $t_access_level => $t_label ) {
            if( $t_access_level >= $t_global ) {
                $t_global_exp[] = $t_access_level;
            }
        }
    } else {
        $t_global_exp = $t_global;
    }

    $t_project = plugin_config_get( $p_threshold, NULL, FALSE, $g_user, $g_project_id );
    if( !is_array( $t_project ) ) {
        $t_project_exp = array();
        foreach( $g_access_levels as $t_access_level => $t_label ) {
            if( $t_access_level >= $t_project ) {
                $t_project_exp[] = $t_access_level;
            }
        }
    } else {
        $t_project_exp = $t_project;
    }

//	$t_can_change = true;

    echo "<tr>\n";

    # Access levels
    echo '  <td>' . string_display( $p_caption ) . "</td>\n";

    foreach( $g_access_levels as $t_access_level => $t_access_label ) {
        $t_file    = in_array( $t_access_level, $t_file_exp );
        $t_global  = in_array( $t_access_level, $t_global_exp );
        $t_project = in_array( $t_access_level, $t_project_exp );

        $t_color = set_color( $p_threshold, $t_file, $t_global, $t_project, TRUE );

        $t_checked     = $t_project ? 'checked="checked"' : '';
        $t_value       = '<label><input type="checkbox" class="ace" name="flag_thres_' . $p_threshold .
                '[]" value="' . $t_access_level . '" ' . $t_checked . ' /><span class="lbl"></span></label>';

        echo '  <td class="center ' . $t_color . '">' . $t_value . "</td>\n";
    }

//	print_who_can_change( $p_threshold, $t_can_change );

    echo "</tr>\n";
}

/**
 * Print section end
 * @return void
 */
function get_section_end() {
    echo '</tbody></table></div>' . "\n";
    echo '</div></div></div> ' . "\n";
    echo '<div class="space-10"></div>';
}

echo '<br />' . "\n";

if( ALL_PROJECTS == $g_project_id ) {
    $t_project_title = lang_get( 'config_all_projects' );
} else {
    $t_project_title = sprintf( lang_get( 'config_project' ), string_display_line( project_get_name( $g_project_id ) ) );
}

echo '<div class="col-md-12 col-xs-12">' . "\n";
echo '<div class="well">' . "\n";
echo '<p class="bold"><i class="fa fa-info-circle"></i> ' . $t_project_title . '</p>' . "\n";
echo '<p>' . lang_get( 'colour_coding' ) . '<br />';
if( ALL_PROJECTS <> $g_project_id ) {
    echo '<span class="color-project">' . lang_get( 'colour_project' ) . '</span><br />';
}
echo '<span class="color-global">' . lang_get( 'colour_global' ) . '</span></p>';
echo '</div>' . "\n";

echo '<form id="mail_config_action" method="post" action="' . plugin_page( 'config_work_threshold_set') . '">' . "\n";
echo form_security_field( 'config_work_threshold_set' );

# Events
get_section_begin_mcwt( plugin_lang_get( 'all_event' ) );
get_capability_row( plugin_lang_get( 'config_view_event_threshold' ), 'view_event_threshold' );
get_capability_row( plugin_lang_get( 'config_report_event_threshold' ), 'report_event_threshold' );
get_capability_row( plugin_lang_get( 'config_update_event_threshold' ), 'update_event_threshold' );
get_capability_row( plugin_lang_get( 'config_show_member_list_threshold' ), 'show_member_list_threshold' );
get_capability_row( plugin_lang_get( 'config_member_event_threshold' ), 'member_event_threshold' );
get_capability_row( plugin_lang_get( 'config_member_add_others_event_threshold' ), 'member_add_others_event_threshold' );
get_capability_row( plugin_lang_get( 'config_member_delete_others_event_threshold' ), 'member_delete_others_event_threshold' );
get_section_end();

# Calendar
get_section_begin_mcwt( plugin_lang_get( 'name_plugin_description_page' ) );
get_capability_row( plugin_lang_get( 'config_calendar_view_threshold' ), 'calendar_view_threshold' );
get_capability_row( plugin_lang_get( 'config_manage_calendar_threshold' ), 'manage_calendar_threshold' );
get_section_end();

# Issues
get_section_begin_mcwt( lang_get( 'issues' ) );
get_capability_row( plugin_lang_get( 'config_bug_calendar_view_threshold' ), 'bug_calendar_view_threshold' );
get_section_end();

echo '<input type="submit" class="btn btn-primary btn-white btn-round" value="' . lang_get( 'change_configuration' ) . '" />' . "\n";

echo '</form>' . "\n";

if(  0 < count( $g_overrides ) ) {
    echo '<div class="pull-right"><form id="threshold_config_action" method="post" action=' . plugin_page( 'config_revert') . '>' . "\n";
    echo form_security_field( 'config_revert' );
    echo '<input name="revert" type="hidden" value="' . implode( ',', $g_overrides ) . '"></input>';
    echo '<input name="project" type="hidden" value="' . $g_project_id . '"></input>';
    echo '<input name="return" type="hidden" value="' . plugin_page( 'config_work_threshold_page' ) . '"></input>';
    echo '<input type="submit" class="btn btn-primary btn-sm btn-white btn-round" value="';
    if( ALL_PROJECTS == $g_project_id ) {
        echo lang_get( 'revert_to_system' );
    } else {
        echo lang_get( 'revert_to_all_project' );
    }
    echo '" />' . "\n";
    echo '</form></div>' . "\n";
}
echo '</div>';
layout_page_end();
