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
 * Save the reminders of the event itself from the event page, the way the
 * event form does it, without the rest of the form: the row the page shows
 * gets the new set - a whole series when the page shows one of its
 * occurrences. The personal sets of the recipients are left alone.
 */

form_security_validate( 'event_reminder_update' );

$f_event_id = gpc_get_int( 'event_id' );
$f_date     = gpc_get_int( 'date' );

event_ensure_exists( $f_event_id );

$t_event = event_get( $f_event_id );

# the thresholds are read for the project of the event, the way the event
# page does it
$g_project_override = $t_event->project_id;

access_ensure_event_level( plugin_config_get( 'update_event_threshold' ), $f_event_id );

if( !calendar_reminder_feature_enabled() ) {
    access_denied();
}

event_reminder_set_all( $f_event_id, calendar_reminder_offsets_from_event_form() );

form_security_purge( 'event_reminder_update' );

layout_page_header_begin();

layout_page_header_end();

layout_page_begin( plugin_page( 'view' ) );

html_operation_successful( plugin_page( 'view' ) . "&event_id=" . $f_event_id . "&date=" . $f_date . "#reminders", plugin_lang_get( 'update_successful_button' ) );

html_meta_redirect( plugin_page( 'view', TRUE ) . "&event_id=" . $f_event_id . "&date=" . $f_date . "#reminders" );

layout_page_end();
