<?php
# Copyright (c) 2019 Grigoriy Ermolaev (igflocal@gmail.com)
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

auth_ensure_user_authenticated();

access_ensure_project_level( plugin_config_get( 'calendar_view_threshold' ) );

layout_page_header( plugin_lang_get( 'week' ) );

layout_page_begin( plugin_page( 'calendar_user_page' ) );

$f_bug_id      = gpc_get_int( 'bug_id' );

bug_ensure_exists($f_bug_id);

# Get Project Id and set it as current
$t_project_id = bug_get_field( $f_bug_id, 'project_id' );

if( ( ALL_PROJECTS == $t_project_id || project_exists( $t_project_id ) ) && $t_project_id != helper_get_current_project()
 ) {
    helper_set_current_project( $t_project_id );
    # Reloading the page is required so that the project browser
    # reflects the new current project
    print_header_redirect( $_SERVER['REQUEST_URI'], true, false, true );
}

compress_enable();

# don't index view issues pages
html_robots_noindex();



$f_week                             = gpc_get_int( "week", date( "W" ) );
$f_is_fulltime                      = gpc_get_bool( "full_time" );
$f_for_user                         = gpc_get_int( "for_user", auth_get_current_user_id() );

$t_calendar_week = new ViewWeek( $f_week, $f_for_user, $f_is_fulltime, $f_bug_id );

$t_calendar_week->print_html();

layout_page_end();
