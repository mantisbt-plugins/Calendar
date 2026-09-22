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
 * Drop one offset from the reminders of the viewer for an event - for them
 * only: the reminders of the event stay as its author set them, the viewer
 * gets a personal set without the offset.
 */

form_security_validate( 'event_reminder_delete' );

$f_event_id = gpc_get_int( 'event_id' );
$f_date     = gpc_get_int( 'date' );

event_ensure_exists( $f_event_id );

$t_event = event_get( $f_event_id );

# the thresholds are read for the project of the event, the way the event
# page does it
$g_project_override = $t_event->project_id;

access_ensure_event_level( plugin_config_get( 'view_event_threshold' ), $f_event_id );

$t_user_id = auth_get_current_user_id();

# only those who are reminded may shape what they are reminded by
if( !calendar_reminder_feature_enabled() || !calendar_reminder_user_is_recipient( $f_event_id, $t_user_id ) ) {
    access_denied();
}

$f_offset = gpc_get_int( 'offset' );

$t_effective = calendar_reminder_effective( $f_event_id, $t_user_id );
$t_offsets   = array_values( array_diff( $t_effective['offsets'], array( $f_offset ) ) );

if( $t_offsets != $t_effective['offsets'] ) {
    event_reminder_user_set_all( $f_event_id, $t_user_id, $t_offsets );
}

form_security_purge( 'event_reminder_delete' );

layout_page_header_begin();

layout_page_header_end();

layout_page_begin( plugin_page( 'view' ) );

html_operation_successful( plugin_page( 'view' ) . "&event_id=" . $f_event_id . "&date=" . $f_date . "#reminders", plugin_lang_get( 'update_successful_button' ) );

html_meta_redirect( plugin_page( 'view', TRUE ) . "&event_id=" . $f_event_id . "&date=" . $f_date . "#reminders" );

layout_page_end();
