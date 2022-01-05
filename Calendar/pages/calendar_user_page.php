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

auth_ensure_user_authenticated();

access_ensure_project_level( plugin_config_get( 'calendar_view_threshold' ) );

layout_page_header( plugin_lang_get( 'week' ) );

layout_page_begin( plugin_page( 'calendar_user_page' ) );


# Get Project Id and set it as current
$t_project_id = gpc_get_int( 'project_id', helper_get_current_project() );
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

$t_current_week = date( "W" );
$t_current_year = date( "Y" );
$t_year_start_of_current_week = date( "Y", strtotime( 'this week' ) );

if( $t_current_year != $t_year_start_of_current_week ) {
    $t_current_year = $t_year_start_of_current_week;
}

$f_week        = gpc_get_int( "week", $t_current_week );
$f_year        = gpc_get_int( "year", $t_current_year );
$f_is_fulltime = gpc_get_bool( "full_time" );
$f_for_user    = gpc_get_int( "for_user", auth_get_current_user_id() );
//$t_access_level_current_user        = access_get_project_level();
//$t_access_level_global_current_user = access_get_global_level();

if( strtotime( $f_year . 'W' . str_pad( $f_week, 2, 0, STR_PAD_LEFT ) ) == false ) {
    error_parameters( plugin_lang_get( 'date_event' ) );
    plugin_error( 'ERROR_RANGE_TIME', ERROR );
}

$t_start_day_of_the_week = plugin_config_get( "startStepDays" );
$t_step_days_count       = plugin_config_get( "countStepDays" );
$t_arWeekdaysName        = plugin_config_get( "arWeekdaysName" );

$t_days        = days_of_number_week( $t_start_day_of_the_week, $t_step_days_count, $t_arWeekdaysName, $f_week, $f_year );
$t_days_events = get_days_object( $t_days, helper_get_current_project(), $f_for_user );

$t_calendar_week = new ViewWeekCalendar( $f_week, $f_for_user, $f_is_fulltime, $t_days_events, plugin_page( 'view' ), $f_year );

$t_calendar_week->print_html();

layout_page_end();
