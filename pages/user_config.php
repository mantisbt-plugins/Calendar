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

form_security_validate( 'calendar_user_config_edit' );

$t_current_user_id = auth_get_current_user_id();

$t_days_week_config = plugin_config_get( 'arWeekdaysName', plugin_config_get( 'arWeekdaysName' ), FALSE, $t_current_user_id );
$f_days_week_cheked = gpc_get_string_array( 'days_week' );

$f_time_start  = gpc_get_int( 'time_day_start' );
$f_time_finish = gpc_get_int( 'time_day_finish' );

$f_step_day_minutes_count = gpc_get_int( 'step_day_minutes_count' );
$f_start_step_days        = gpc_get_int( 'start_step_days' );
$f_count_step_days        = gpc_get_int( 'count_step_days' );

$f_google_calendar_list = gpc_get_string( 'google_calendar_list', NULL );

foreach( $t_days_week_config as $t_name_day => $t_status ) {
    if( in_array( $t_name_day, $f_days_week_cheked ) ) {
        $t_days_week_config_set[$t_name_day] = ON;
    } else {
        $t_days_week_config_set[$t_name_day] = OFF;
    }
}

if( $f_time_start >= $f_time_finish ) {
    error_parameters( plugin_lang_get( 'date_event' ) );
    plugin_error( 'ERROR_RANGE_TIME', ERROR );
}

if( $f_step_day_minutes_count < 1 || $f_step_day_minutes_count > 6 || $f_start_step_days < 0 || $f_count_step_days < 1 ) {
    error_parameters( plugin_lang_get( 'date_event' ) );
    plugin_error( 'ERROR_RANGE_TIME', ERROR );
}


if( $t_days_week_config_set != $t_days_week_config ) {
    plugin_config_set( 'arWeekdaysName', $t_days_week_config_set, $t_current_user_id );
}

if( plugin_config_get( 'time_day_start', plugin_config_get( 'time_day_start' ), FALSE, $t_current_user_id ) != $f_time_start ) {
    plugin_config_set( 'time_day_start', $f_time_start, $t_current_user_id );
}

if( plugin_config_get( 'time_day_finish', plugin_config_get( 'time_day_finish' ), FALSE, $t_current_user_id ) != $f_time_finish ) {
    plugin_config_set( 'time_day_finish', $f_time_finish, $t_current_user_id );
}

plugin_config_set( 'stepDayMinutesCount', $f_step_day_minutes_count, $t_current_user_id );
plugin_config_set( 'startStepDays', $f_start_step_days, $t_current_user_id );
plugin_config_set( 'countStepDays', $f_count_step_days, $t_current_user_id );


$t_google_calendar_sync_id = plugin_config_get( 'google_calendar_sync_id', "0", FALSE, $t_current_user_id );

if( $t_google_calendar_sync_id !== $f_google_calendar_list ) {
    if( $f_google_calendar_list === "0" ) {
        plugin_config_delete( 'google_calendar_sync_id', $t_current_user_id );
    } else {
        plugin_config_set( 'google_calendar_sync_id', $f_google_calendar_list, $t_current_user_id );
    }
}

form_security_purge( 'calendar_user_config_edit' );

$t_redirect_url = plugin_page( 'calendar_user_page', TRUE );

layout_page_header( null, $t_redirect_url );

layout_page_begin( $t_redirect_url );

html_operation_successful( $t_redirect_url );

layout_page_end();
