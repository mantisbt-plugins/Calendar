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

$f_project_id = gpc_get_int( 'project_id', helper_get_current_project() );

# the threshold is read for the project the event is created in, which is
# posted along with the form and need not be the current one
access_ensure_project_level( plugin_config_get( 'report_event_threshold', null, false, null, $f_project_id ), $f_project_id );

form_security_validate( 'event_add' );

# attaching an issue must not disclose one, so only the issues the user may
# view are kept, the way the issue selector of the form lists them
$f_bugs     = event_bug_ids_filter_viewable( gpc_get_int_array( 'bugs_add', array() ) );
$f_from_bug = gpc_get_int( 'from_bug_id', 0 );

if($f_from_bug != 0) {
    bug_ensure_exists($f_from_bug);
}

$t_event_timezone         = calendar_timezone_get( gpc_get_string( 'event_timezone', '' ) );
$t_period                 = calendar_event_form_period( $t_event_timezone );
$f_date_ending_repetition = calendar_strtotime_in_timezone( gpc_get_string( 'date_ending_repetition', NULL ), $t_event_timezone );
$f_selected_freq          = gpc_get_string( 'selected_freq', 'NO_REPEAT' );

if( calendar_reminder_feature_enabled() ) {
    $t_reminder_offsets = calendar_reminder_offsets_from_event_form();
}

$t_event_data = new CalendarEventData();

$t_event_data->project_id  = $f_project_id;
$t_event_data->name        = gpc_get_string( 'name_event' );
$t_event_data->description = gpc_get_string( 'description_event', '' );
$t_event_data->activity    = "Y";
$t_event_data->author_id   = auth_get_current_user_id();
$t_event_data->date_from   = $t_period['date_from'];
$t_event_data->duration    = $t_period['duration'];
$t_event_data->timezone    = $t_event_timezone->getName();

switch( $f_selected_freq ) {
    case 'DAILY':
    case 'WEEKLY':
    case 'MONTHLY':
    case 'YEARLY':
        # the rule stops at the end time of the last day; the series itself
        # ends when its last occurrence does, which for an occurrence
        # spanning days is later than that
        $t_until_day                      = $f_date_ending_repetition == NULL ? calendar_strtotime_in_timezone( '01-01-2038', $t_event_timezone ) : $f_date_ending_repetition;
        $t_event_data->date_to            = $t_until_day + $t_period['end_offset'];
        $t_rrule                          = new RRule\RRule( array(
                                  'DTSTART'  => calendar_rrule_datetime( $t_event_data->date_from, $t_event_timezone ),
                                  'UNTIL'    => calendar_rrule_datetime( $t_until_day + $t_period['time_finish'], $t_event_timezone ),
                                  'FREQ'     => $f_selected_freq,
                                  'INTERVAL' => gpc_get_int( 'interval_value' )
                ) );
        $t_event_data->recurrence_pattern = $t_rrule->rfcString();

        break;
    default :
        $t_event_data->date_to = $t_period['date_to'];
}

$f_owner_is_members = gpc_get_bool( 'owner_is_members' );
$f_member_user_list = gpc_get_int_array( 'user_ids', array() );

if( $f_owner_is_members || count( $f_member_user_list ) == 0 ) {
    $f_member_user_list[] = $t_event_data->author_id;
}

# the same member rules as calendar_api_event_create(), checked before the
# event row is written so that a rejected request leaves no orphan behind
event_members_ensure_eligible( $f_project_id, $t_event_data->author_id, $f_member_user_list );

$t_event_id = $t_event_data->create();

if( count( $f_bugs ) > 0 ) {
    event_attach_issue( $t_event_id, $f_bugs );
}

foreach( $f_member_user_list as $t_member ) {
    event_member_add( $t_event_id, $t_member );
}

event_member_accept_author( $t_event_id );

if( calendar_reminder_feature_enabled() ) {
    event_reminder_set_all( $t_event_id, $t_reminder_offsets );
}

event_google_add( $t_event_id, $t_event_data->author_id, $f_member_user_list );

# the event is fully assembled now - announce it to the subscribers
event_signal_created( $t_event_id );

# the author and the members learn about the event by mail, the creator of it
# does not need to be told what they have just done
calendar_notify_event_created( $t_event_id, auth_get_current_user_id() );

form_security_purge( 'event_add' );

layout_page_header_begin();

layout_page_header_end();

layout_page_begin( plugin_page( 'event_add_page' ) );


if( $f_from_bug != 0 ) {
    print_header_redirect_view( $f_from_bug );
} else {
    $t_buttons = array(
                              array( plugin_page( 'calendar_user_page' ), plugin_lang_get( 'menu_main_front' ) ),
                              array( plugin_page( 'view' ) . "&event_id=" . $t_event_id . "&date=" . $t_event_data->date_from, sprintf( plugin_lang_get( 'view_submitted_event_link' ), $t_event_id ) ),
    );
    html_meta_redirect( plugin_page( 'calendar_user_page', TRUE ) );
    html_operation_confirmation( $t_buttons, '', CONFIRMATION_TYPE_SUCCESS );

    layout_page_end();
}