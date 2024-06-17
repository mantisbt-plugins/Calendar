<?php

# Copyright (c) 2018 Grigoriy Ermolaev (igflocal@gmail.com)
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
 * Check if the user has the specified access level for the given bug
 * and deny access to the page if not
 * @see access_has_bug_level
 * @param integer      $p_access_level Integer representing access level.
 * @param integer      $p_event_id       Integer representing bug id to check access against.
 * @param integer|null $p_user_id      Integer representing user id, defaults to null to use current user.
 * @return void
 * @access public
 */
function access_ensure_event_level( $p_access_level, $p_event_id, $p_user_id = null ) {
	if( !access_has_event_level( $p_access_level, $p_event_id, $p_user_id ) ) {
		access_denied();
	}
}

/**
 * Check the current user's access against the given value and return true
 * if the user's access is equal to or higher, false otherwise.
 * This function looks up the bug's project and performs an access check
 * against that project
 * @param integer      $p_access_level Integer representing access level.
 * @param integer      $p_event_id       Integer representing bug id to check access against.
 * @param integer|null $p_user_id      Integer representing user id, defaults to null to use current user.
 * @return boolean whether user has access level specified
 * @access public
 */
function access_has_event_level( $p_access_level, $p_event_id, $p_user_id = null ) {
	if( $p_user_id === null ) {
		$p_user_id = auth_get_current_user_id();
	}

	# Deal with not logged in silently in this case
	# @@@ we may be able to remove this and just error
	#     and once we default to anon login, we can remove it for sure
	if( empty( $p_user_id ) && !auth_is_user_authenticated() ) {
		return false;
	}

	$t_project_id = event_get_field( $p_event_id, 'project_id' );
	$t_event_is_user_reporter = event_is_user_reporter( $p_event_id, $p_user_id );
	$t_access_level = access_get_project_level( $t_project_id, $p_user_id );

	# check limit_Reporter (Issue #4769)
	# reporters can view just issues they reported
	$t_limit_reporters = config_get( 'limit_reporters', null, $p_user_id, $t_project_id );
	if( $t_limit_reporters && !$t_event_is_user_reporter ) {
		# Here we only need to check that the current user has an access level
		# higher than the lowest needed to report issues (report_bug_threshold).
		# To improve performance, esp. when processing for several projects, we
		# build a static array holding that threshold for each project
		static $s_thresholds = array();
		if( !isset( $s_thresholds[$t_project_id] ) ) {
			$t_report_event_threshold = plugin_config_get( 'report_event_threshold', null, $p_user_id, $t_project_id );
			if( empty( $t_report_event_threshold ) ) {
				$s_thresholds[$t_project_id] = NOBODY;
			} else {
				$s_thresholds[$t_project_id] = access_threshold_min_level( $t_report_event_threshold ) + 1;
			}
		}
		if( !access_compare_level( $t_access_level, $s_thresholds[$t_project_id] ) ) {
			return false;
		}
	}

	return access_compare_level( $t_access_level, $p_access_level );
}
