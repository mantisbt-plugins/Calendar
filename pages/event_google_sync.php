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

form_security_validate( 'event_google_sync' );

$f_event_id = gpc_get_int( 'event_id' );
$f_date     = gpc_get_int( 'date' );

event_ensure_exists( $f_event_id );

if( event_is_synchronized_with_google( $f_event_id ) || event_google_get_id( $f_event_id ) != NULL ) {
    event_google_update( event_get( $f_event_id ) );
} else {
    event_google_add( $f_event_id, event_get_field( $f_event_id, 'author_id' ), event_get_members( $f_event_id ) );
}

form_security_purge( 'event_google_sync' );

layout_page_header_begin();

layout_page_header_end();

layout_page_begin( plugin_page( 'event_add_page' ) );


html_operation_successful( plugin_page( 'view', TRUE ) . "&event_id=" . $f_event_id . "&date=" . $f_date, plugin_lang_get( 'update_successful_button' ) );

html_meta_redirect( plugin_page( 'view', TRUE ) . "&event_id=" . $f_event_id . "&date=" . $f_date );

layout_page_end();
