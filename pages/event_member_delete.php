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

form_security_validate( 'event_member_delete' );

$f_event_id = gpc_get_int( 'event_id' );
$t_event    = event_get( $f_event_id );
$f_user_id  = gpc_get_int( 'user_id', NO_USER );
$f_date     = gpc_get_int( 'date' );

$t_logged_in_user_id = auth_get_current_user_id();

if( $f_user_id === NO_USER ) {
    $t_user_id = $t_logged_in_user_id;
} else {
    user_ensure_exists( $f_user_id );
    $t_user_id = $f_user_id;
}

if( user_is_anonymous( $t_user_id ) ) {
    trigger_error( ERROR_PROTECTED_ACCOUNT, E_USER_ERROR );
}

event_ensure_exists( $f_event_id );

if( $t_event->project_id != helper_get_current_project() ) {
    # in case the current project is not the same project of the bug we are viewing...
    # ... override the current project. This to avoid problems with categories and handlers lists etc.
    $g_project_override = $t_event->project_id;
}

if( $t_logged_in_user_id == $t_user_id || $t_event->author_id == $t_logged_in_user_id ) {
    access_ensure_event_level( plugin_config_get( 'member_event_threshold' ), $f_event_id );
} else {
    access_ensure_event_level( plugin_config_get( 'member_delete_others_event_threshold' ), $f_event_id );
}

if( count( event_get_members( $f_event_id ) ) <= 1 ) {
	plugin_error( 'ERROR_MIN_MEMBERS' );
}

event_member_delete( $f_event_id, $t_user_id );

event_google_update( event_get( $f_event_id ) );

form_security_purge( 'event_member_delete' );

layout_page_header_begin();

layout_page_header_end();

layout_page_begin( plugin_page( 'view' ) );

html_operation_successful( plugin_page( 'view' ) . "&event_id=" . $f_event_id . "&date=" . $f_date, plugin_lang_get( 'update_successful_button' ) );

html_meta_redirect( plugin_page( 'view', TRUE ) . "&event_id=" . $f_event_id . "&date=" . $f_date );

layout_page_end();
