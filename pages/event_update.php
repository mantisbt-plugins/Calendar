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

layout_page_header_begin();

layout_page_header_end();

layout_page_begin( plugin_page( 'event_update_page' ) );

access_ensure_global_level( plugin_config_get( 'update_event_threshold' ) );

form_security_validate( 'event_update' );

$f_event_id = gpc_get_int( 'event_id' );

$t_event_data = event_get( $f_event_id );

$f_event_time_start  = gpc_get_int( 'event_time_start' );
$f_event_time_finish = gpc_get_int( 'event_time_finish' );

$t_event_data->name            = gpc_get_string( 'name_event' );
$t_event_data->activity        = "Y";
$t_event_data->changed_user_id = auth_get_current_user_id();
$t_event_data->date_from       = strtotime( gpc_get_string( 'date_event' ), NULL ) + $f_event_time_start;
$t_event_data->date_to         = strtotime( gpc_get_string( 'date_event' ), NULL ) + $f_event_time_finish;

$t_event_data->update();


$t_table_calendar_relationship = plugin_table( "relationship" );

$query = "DELETE FROM $t_table_calendar_relationship
			          WHERE event_id=" . db_param();

db_query( $query, array( $f_event_id ) );

$f_bugs = gpc_get_int_array( 'bugs_add', array( 0 ) );

if( $f_bugs[0] !== 0 ) {

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
        db_query( $query, Array( $t_event_data->id, $t_bug_id ) );
        plugin_history_log( $t_bug_id, plugin_lang_get( "event" ), "", plugin_lang_get( "event_hystory_create" ) . ": " . $t_event_data->name );
    }
}

form_security_purge( 'event_update' );

html_operation_successful( plugin_page( 'view' ) . "&event_id=" . $t_event_data->id, plugin_lang_get( 'update_successful_button' ) );

html_meta_redirect( plugin_page( 'view' ) . "&event_id=" . $t_event_data->id );

layout_page_end();
