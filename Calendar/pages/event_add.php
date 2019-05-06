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

access_ensure_global_level( plugin_config_get( 'report_event_threshold' ) );

form_security_validate( 'event_add' );

$f_bugs     = gpc_get_int_array( 'bugs_add', array() );
$f_from_bug = gpc_get_int( 'from_bug_id', 0 );

if($f_from_bug != 0) {
    bug_ensure_exists($f_from_bug);
}

$f_event_time_start       = gpc_get_int( 'event_time_start' );
$f_event_time_finish      = gpc_get_int( 'event_time_finish' );
$f_date_ending_repetition = strtotime( gpc_get_string( 'date_ending_repetition', NULL ) );
$f_selected_freq          = gpc_get_string( 'selected_freq', 'NO_REPEAT' );

$t_event_data = new CalendarEventData();

$t_event_data->project_id = gpc_get_int( 'project_id', helper_get_current_project() );
$t_event_data->name       = gpc_get_string( 'name_event' );
$t_event_data->activity   = "Y";
$t_event_data->author_id  = auth_get_current_user_id();
$t_event_data->date_from  = strtotime( gpc_get_string( 'date_event' ) ) + $f_event_time_start;
$t_event_data->duration   = $f_event_time_finish - $f_event_time_start;

switch( $f_selected_freq ) {
    case 'DAILY':
    case 'WEEKLY':
    case 'MONTHLY':
    case 'YEARLY':
        $t_event_data->date_to            = $f_date_ending_repetition == NULL ? strtotime( '01-01-2038' ) + $f_event_time_finish : $f_date_ending_repetition + $f_event_time_finish;
        $t_rrule                          = new RRule\RRule( array(
                                  'DTSTART'  => $t_event_data->date_from,
                                  'UNTIL'    => $t_event_data->date_to,
                                  'FREQ'     => $f_selected_freq,
                                  'INTERVAL' => gpc_get_int( 'interval_value' )
                ) );
        $t_event_data->recurrence_pattern = $t_rrule->rfcString();

        break;
    default :
        $t_event_data->date_to = $f_date_ending_repetition == NULL ? strtotime( gpc_get_string( 'date_event' ) ) + $f_event_time_finish : $f_date_ending_repetition + $f_event_time_finish;
}

$t_event_id = $t_event_data->create();

if( count( $f_bugs ) > 0 ) {
    event_attach_issue( $t_event_id, $f_bugs );
}

$f_owner_is_members = gpc_get_bool( 'owner_is_members' );
$f_member_user_list = gpc_get_int_array( 'user_ids', array() );

if( $f_owner_is_members || count( $f_member_user_list ) == 0 ) {
    $f_member_user_list[] = $t_event_data->author_id;
}

foreach( $f_member_user_list as $t_member ) {
    event_member_add( $t_event_id, $t_member );
}

event_google_add( $t_event_id, $t_event_data->author_id, $f_member_user_list );

form_security_purge( 'event_add' );

layout_page_header_begin();

layout_page_header_end();

layout_page_begin( plugin_page( 'event_add_page' ) );


if( $f_from_bug != 0 ) {
    print_successful_redirect_to_bug( $f_from_bug );
} else {
    $t_buttons = array(
                              array( plugin_page( 'calendar_user_page' ), plugin_lang_get( 'menu_main_front' ) ),
                              array( plugin_page( 'view' ) . "&event_id=" . $t_event_id . "&date=" . $t_event_data->date_from, sprintf( plugin_lang_get( 'view_submitted_event_link' ), $t_event_id ) ),
    );
    html_meta_redirect( plugin_page( 'calendar_user_page', TRUE ) );
    html_operation_confirmation( $t_buttons, '', CONFIRMATION_TYPE_SUCCESS );

    layout_page_end();
}