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

# Store the personal reminder, notification and issue page settings submitted from
# reminders_page.php. A block that the page did not render must not be stored,
# otherwise switching a feature off would silently reset the settings of every
# user who saves the page meanwhile.

auth_ensure_user_authenticated();

current_user_ensure_unprotected();

form_security_validate( 'calendar_reminders_edit' );

$t_reminders_enabled     = calendar_reminder_feature_enabled();
$t_notifications_enabled = calendar_notify_feature_enabled();
$t_bug_block_user_choice = calendar_bug_block_user_choice();

if( !$t_reminders_enabled && !$t_notifications_enabled && !$t_bug_block_user_choice ) {
    access_denied();
}

$t_current_user_id = auth_get_current_user_id();

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
