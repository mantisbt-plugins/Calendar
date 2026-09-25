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

form_security_validate( 'config' );

auth_reauthenticate();

access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

$t_days_week_config = plugin_config_get( 'arWeekdaysName' );
$f_days_week_cheked = gpc_get_string_array( 'days_week' );

$f_time_start  = gpc_get_int( 'time_day_start' );
$f_time_finish = gpc_get_int( 'time_day_finish' );

$f_reminders_feature_enabled     = gpc_get_bool( 'reminders_feature_enabled' ) ? ON : OFF;
$f_notifications_feature_enabled = gpc_get_bool( 'notifications_feature_enabled' ) ? ON : OFF;
$f_rsvp_mode                     = gpc_get_int( 'rsvp_mode' );
$f_bug_calendar_block_position   = gpc_get_int( 'bug_calendar_block_position' );

$f_file = gpc_get_file( 'ufile' );


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

if( !in_array( $f_bug_calendar_block_position, array( CALENDAR_BUG_BLOCK_DETAILS, CALENDAR_BUG_BLOCK_EXTRA, CALENDAR_BUG_BLOCK_USER_CHOICE ) ) ) {
    error_parameters( 'bug_calendar_block_position' );
    trigger_error( ERROR_INVALID_FIELD_VALUE, ERROR );
}

if( !in_array( $f_rsvp_mode, array( CALENDAR_RSVP_MODE_OFF, CALENDAR_RSVP_MODE_ON, CALENDAR_RSVP_MODE_USER_CHOICE ) ) ) {
    error_parameters( 'rsvp_mode' );
    trigger_error( ERROR_INVALID_FIELD_VALUE, ERROR );
}


if( $t_days_week_config_set != $t_days_week_config ) {
    plugin_config_set( 'arWeekdaysName', $t_days_week_config_set );
}

if( plugin_config_get( 'time_day_start' ) != $f_time_start ) {
    plugin_config_set( 'time_day_start', $f_time_start );
}

if( plugin_config_get( 'time_day_finish' ) != $f_time_finish ) {
    plugin_config_set( 'time_day_finish', $f_time_finish );
}

if( plugin_config_get( 'reminders_feature_enabled' ) != $f_reminders_feature_enabled ) {
    plugin_config_set( 'reminders_feature_enabled', $f_reminders_feature_enabled );
}

if( plugin_config_get( 'notifications_feature_enabled' ) != $f_notifications_feature_enabled ) {
    plugin_config_set( 'notifications_feature_enabled', $f_notifications_feature_enabled );
}

if( calendar_rsvp_mode() != $f_rsvp_mode ) {
    plugin_config_set( 'rsvp_mode', $f_rsvp_mode );
}

if( plugin_config_get( 'bug_calendar_block_position' ) != $f_bug_calendar_block_position ) {
    plugin_config_set( 'bug_calendar_block_position', $f_bug_calendar_block_position );
}

if( !is_blank( $f_file['tmp_name'] ) ) {
    $t_client_secret = file_get_contents( $f_file['tmp_name'] );

    if( plugin_config_get( 'google_client_secret' ) != $t_client_secret && $t_client_secret != FALSE ) {
        plugin_config_set( 'google_client_secret', $t_client_secret );
    }
}

form_security_purge( 'config' );

$t_redirect_url = plugin_page( 'config_page', true );

layout_page_header( null, $t_redirect_url );

layout_page_begin( $t_redirect_url );

html_operation_successful( $t_redirect_url );

layout_page_end();
