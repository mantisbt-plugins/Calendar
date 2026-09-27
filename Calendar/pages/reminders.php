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

# Store the personal calendar view, reminder, notification and issue page
# settings submitted from reminders_page.php. A block that the page did not
# render must not be stored, otherwise switching a feature off would silently
# reset the settings of every user who saves the page meanwhile.

auth_ensure_user_authenticated();

current_user_ensure_unprotected();

form_security_validate( 'calendar_reminders_edit' );

$t_view_settings         = calendar_user_view_settings_allowed();
$t_rsvp_user_choice      = calendar_rsvp_user_choice();
$t_reminders_enabled     = calendar_reminder_feature_enabled();
$t_notifications_enabled = calendar_notify_feature_enabled();
$t_bug_block_user_choice = calendar_bug_block_user_choice();

if( !$t_view_settings && !$t_rsvp_user_choice && !$t_reminders_enabled && !$t_notifications_enabled && !$t_bug_block_user_choice ) {
    access_denied();
}

$t_current_user_id = auth_get_current_user_id();

if( $t_view_settings ) {

    $f_days_week              = gpc_get_string_array( 'days_week', array() );
    $f_time_start             = gpc_get_int( 'time_day_start' );
    $f_time_finish            = gpc_get_int( 'time_day_finish' );
    $f_step_day_minutes_count = gpc_get_int( 'step_day_minutes_count' );
    $f_start_step_days        = gpc_get_int( 'start_step_days' );
    $f_count_step_days        = gpc_get_int( 'count_step_days' );
    $f_google_calendar        = gpc_get_string( 'google_calendar_list', NULL );

    # the ranges the form offers: a day of 0-24 hours, one to six rows an
    # hour, a start within the week and a grid of at most a month
    if( $f_time_start < 0 || $f_time_finish > 86400 || $f_time_start >= $f_time_finish
            || $f_step_day_minutes_count < 1 || $f_step_day_minutes_count > 6
            || $f_start_step_days < 0 || $f_start_step_days > 6
            || $f_count_step_days < 1 || $f_count_step_days > 31 ) {
        error_parameters( plugin_lang_get( 'date_event' ) );
        plugin_error( 'ERROR_RANGE_TIME', ERROR );
    }

    $t_days_week = array();
    foreach( plugin_config_get( 'arWeekdaysName' ) as $t_name_day => $t_status ) {
        $t_days_week[$t_name_day] = in_array( $t_name_day, $f_days_week ) ? ON : OFF;
    }

    plugin_config_set( 'arWeekdaysName', $t_days_week, $t_current_user_id );
    plugin_config_set( 'time_day_start', $f_time_start, $t_current_user_id );
    plugin_config_set( 'time_day_finish', $f_time_finish, $t_current_user_id );
    plugin_config_set( 'stepDayMinutesCount', $f_step_day_minutes_count, $t_current_user_id );
    plugin_config_set( 'startStepDays', $f_start_step_days, $t_current_user_id );
    plugin_config_set( 'countStepDays', $f_count_step_days, $t_current_user_id );

    # the list is only on the page once the user has granted access to Google;
    # "0" is its "do not sync" entry, anything else has to be one of the
    # calendars of that account - an unknown id keeps the stored choice
    if( $f_google_calendar !== NULL && $f_google_calendar !== '0'
            && !in_array( $f_google_calendar, google_calendar_list_ids( $t_current_user_id ), true ) ) {
        $f_google_calendar = NULL;
    }

    if( $f_google_calendar !== NULL && plugin_config_get( 'google_calendar_sync_id', '0', FALSE, $t_current_user_id ) !== $f_google_calendar ) {
        if( $f_google_calendar === '0' ) {
            plugin_config_delete( 'google_calendar_sync_id', $t_current_user_id );
        } else {
            plugin_config_set( 'google_calendar_sync_id', $f_google_calendar, $t_current_user_id );
        }
    }
}

if( $t_rsvp_user_choice ) {
    plugin_config_set( 'rsvp_enabled', gpc_get_bool( 'rsvp_enabled' ) ? ON : OFF, $t_current_user_id );
}

if( $t_reminders_enabled ) {

    $f_reminders_enabled = gpc_get_bool( 'reminders_enabled' ) ? ON : OFF;

    # an empty list is legal and means "nothing, unless the event asks for it"
    $t_reminder_offsets = calendar_reminder_offsets_from_input( gpc_get_int_array( 'reminder_value', array() ),
                                                                gpc_get_string_array( 'reminder_unit', array() ) );

    plugin_config_set( 'reminders_enabled', $f_reminders_enabled, $t_current_user_id );

    # the checkbox is only on the page while replies are switched on; without
    # it the stored choice is kept for the day they are switched on again
    if( calendar_rsvp_feature_enabled() ) {
        plugin_config_set( 'reminders_no_reply', gpc_get_bool( 'reminders_no_reply' ) ? ON : OFF, $t_current_user_id );
    }
    plugin_config_set( 'reminders_default', $t_reminder_offsets, $t_current_user_id );
}

if( $t_notifications_enabled ) {

    foreach( array( 'created', 'updated', 'deleted' ) as $t_notify_action ) {

        $t_notify_enabled = gpc_get_bool( 'notify_event_' . $t_notify_action ) ? ON : OFF;

        plugin_config_set( 'notify_event_' . $t_notify_action, $t_notify_enabled, $t_current_user_id );
    }
}

if( $t_bug_block_user_choice ) {
    plugin_config_set( 'bug_calendar_block_separate', gpc_get_bool( 'bug_calendar_block_separate' ) ? ON : OFF, $t_current_user_id );
}

form_security_purge( 'calendar_reminders_edit' );

$t_redirect_url = plugin_page( 'reminders_page', TRUE );

layout_page_header( null, $t_redirect_url );

layout_page_begin( $t_redirect_url );

html_operation_successful( $t_redirect_url );

layout_page_end();
