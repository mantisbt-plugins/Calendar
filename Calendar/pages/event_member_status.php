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

/**
 * Record the reply of the viewer to an event: whether they will take part.
 * The counterpart of event_rsvp for a user who is logged in, reached from
 * the buttons of the event page and guarded by the form security token.
 */

form_security_validate( 'event_member_status' );

$f_event_id = gpc_get_int( 'event_id' );
$f_date     = gpc_get_int( 'date' );
$f_status   = gpc_get_int( 'status' );

event_ensure_exists( $f_event_id );

$t_event = event_get( $f_event_id );

$g_project_override = $t_event->project_id;

access_ensure_event_level( plugin_config_get( 'view_event_threshold' ), $f_event_id );

$t_user_id = auth_get_current_user_id();

if( !calendar_rsvp_feature_enabled() || !user_is_member_event( $t_user_id, $f_event_id ) ) {
    access_denied();
}

if( !in_array( $f_status, calendar_rsvp_replies(), true ) ) {
    error_parameters( 'status' );
    trigger_error( ERROR_INVALID_FIELD_VALUE, ERROR );
}

event_member_set_status( $f_event_id, $t_user_id, $f_status );

form_security_purge( 'event_member_status' );

layout_page_header_begin();

layout_page_header_end();

layout_page_begin( plugin_page( 'view' ) );

html_operation_successful( plugin_page( 'view' ) . "&event_id=" . $f_event_id . "&date=" . $f_date . "#members", plugin_lang_get( 'update_successful_button' ) );

html_meta_redirect( plugin_page( 'view', TRUE ) . "&event_id=" . $f_event_id . "&date=" . $f_date . "#members" );

layout_page_end();
