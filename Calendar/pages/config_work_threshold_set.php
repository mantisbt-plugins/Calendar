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

/**
 * Workflow Threshold Configuration
 */

form_security_validate( 'config_work_threshold_set' );

auth_reauthenticate();

$t_redirect_url = plugin_page( 'config_work_threshold_page', TRUE );

layout_page_header( lang_get( 'manage_threshold_config' ), $t_redirect_url );

layout_page_begin();

$g_access = current_user_get_access_level();
$g_project = helper_get_current_project();

/**
 * set row
 * @param string  $p_threshold         Threshold.
 * @param boolean $p_all_projects_only All projects only.
 * @return void
 */
function set_capability_row( $p_threshold, $p_all_projects_only = false ) {
	global $g_project;

	if( ALL_PROJECTS == $g_project  || !$p_all_projects_only  ) {
		$f_threshold = gpc_get_int_array( 'flag_thres_' . $p_threshold, array() );
		# @@debug @@ echo "<br />for $p_threshold "; var_dump($f_threshold, $f_access); echo '<br />';
		$t_access_levels = MantisEnum::getAssocArrayIndexedByValues( config_get( 'access_levels_enum_string' ) );
		ksort( $t_access_levels );
		reset( $t_access_levels );

		$t_lower_threshold = NOBODY;
		$t_array_threshold = array();

		foreach( $t_access_levels as $t_access_level => $t_level_name ) {
			if( in_array( $t_access_level, $f_threshold ) ) {
				if( NOBODY == $t_lower_threshold ) {
					$t_lower_threshold = $t_access_level;
				}
				$t_array_threshold[] = $t_access_level;
			} else {
				if( NOBODY <> $t_lower_threshold ) {
					$t_lower_threshold = -1;
				}
			}
		# @@debug @@ var_dump($$t_access_level, $t_lower_threshold, $t_array_threshold); echo '<br />';
		}
		$t_existing_threshold = plugin_config_get( $p_threshold );
		if( -1 == $t_lower_threshold ) {
			if( $t_existing_threshold != $t_array_threshold ) {
				plugin_config_set( $p_threshold, $t_array_threshold, NO_USER, $g_project );
			}
		} else {
			if( $t_existing_threshold != $t_lower_threshold ) {
				plugin_config_set( $p_threshold, $t_lower_threshold, NO_USER, $g_project );
			}
		}
	}
}

# Events
set_capability_row( 'view_event_threshold' );
set_capability_row( 'report_event_threshold' );
set_capability_row( 'update_event_threshold' );
set_capability_row( 'show_member_list_threshold' );
set_capability_row( 'member_event_threshold' );
set_capability_row( 'member_add_others_event_threshold' );
set_capability_row( 'member_delete_others_event_threshold' );

# Calendar
set_capability_row( 'calendar_view_threshold' );
set_capability_row( 'manage_calendar_threshold' );
set_capability_row( 'bug_calendar_view_threshold' );

form_security_purge( 'config_work_threshold_set' );

html_operation_successful( $t_redirect_url );

layout_page_end();
