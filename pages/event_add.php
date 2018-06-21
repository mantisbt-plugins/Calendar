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

access_ensure_global_level( plugin_config_get( 'manage_calendar_threshold' ) );

form_security_validate( 'event_add' );

$f_bugs = gpc_get_int_array( 'bugs_add', array( 0 ) );

$t_event_data = new CalendarEventData;

$f_event_time_start  = gpc_get_int( 'event_time_start' );
$f_event_time_finish = gpc_get_int( 'event_time_finish' );

$t_event_data->project_id = gpc_get_int( 'project_id', helper_get_current_project() );
$t_event_data->name       = gpc_get_string( 'name_event' );
$t_event_data->activity   = "Y";
$t_event_data->author_id  = auth_get_current_user_id();
$t_event_data->date_from  = strtotime( gpc_get_string( 'date_event' ) ) + $f_event_time_start;
$t_event_data->date_to    = strtotime( gpc_get_string( 'date_event' ) ) + $f_event_time_finish;

$t_event_id = $t_event_data->create();

$f_bugs = gpc_get_int_array( 'bugs_add', array( 0 ) );


if( $f_bugs[0] !== 0 && !is_blank( $t_event_id ) ) {

    $t_table_calendar_relationship = plugin_table( "relationship" );

    $query = "INSERT
                                              INTO $t_table_calendar_relationship
                                                  ( event_id, bug_id )
                                              VALUES
                                                  ( " . db_param() . ', ' . db_param() . ')';

    foreach( $f_bugs as $t_bug_id ) {
        if( !bug_exists( $t_bug_id ) ) {
            continue;
        }
        db_query( $query, Array( $t_event_id, $t_bug_id ) );
        plugin_history_log( $t_bug_id, plugin_lang_get( "event" ), "", plugin_lang_get( "event_hystory_create" ) . ": " . $t_event_data->name );
    }
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


if( $f_bugs[0] == 0 ) {
    $t_buttons = array(
                              array( plugin_page( 'view' ) . "&event_id=" . $t_event_id, sprintf( plugin_lang_get( 'view_submitted_event_link' ), $t_event_id ) ),
                              array( plugin_page( 'calendar_user_page' ), plugin_lang_get( 'menu_main_front' ) ),
    );
    html_meta_redirect( plugin_page( 'calendar_user_page', TRUE ) );
} else if( $f_bugs[0] != 0 || count( $f_bugs ) == 1 ) {
    $t_buttons = array(
                              array( plugin_page( 'view' ) . "&event_id=" . $t_event_id, sprintf( plugin_lang_get( 'view_submitted_event_link' ), $t_event_id ) ),
                              array( plugin_page( 'calendar_user_page' ), plugin_lang_get( 'menu_main_front' ) ),
    );
    html_meta_redirect( string_get_bug_view_url( $f_bugs[0] ) );
} else {
    $t_buttons = array(
                              array( plugin_page( 'view' ) . "&event_id=" . $t_event_id, sprintf( plugin_lang_get( 'view_submitted_event_link' ), $t_event_id ) ),
                              array( plugin_page( 'calendar_user_page' ), plugin_lang_get( 'menu_main_front' ) ),
    );
    html_meta_redirect( plugin_page( 'view', TRUE ) . "&event_id=" . $t_event_id );
}

html_operation_confirmation( $t_buttons, '', CONFIRMATION_TYPE_SUCCESS );

layout_page_end();
