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

# Stores the matrix of the notification recipients, either globally or for one
# project. The matrix is written as a whole, because a project follows the
# global one until it has a copy of its own; deleting that copy is what the
# revert button of the page does, through config_revert.
#
# The scope is the project of the selector in the header, the same one the
# page read - it is never posted along with the form.

form_security_validate( 'notify_config' );

auth_reauthenticate();

access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

$t_project_id = helper_get_current_project();

$f_flags = gpc_get_string_array( 'flag', array() );

# an unchecked box is not posted at all, so the matrix is built from the cells
# the plugin knows and the request only says which of them are on
$t_checked = array();
foreach( $f_flags as $t_flag ) {
    $t_checked[$t_flag] = TRUE;
}

$t_flags = array();
foreach( calendar_notify_actions() as $t_action ) {
    foreach( calendar_notify_targets() as $t_target ) {
        $t_flags[$t_action][$t_target] = isset( $t_checked[$t_action . ':' . $t_target] ) ? ON : OFF;
    }
}

plugin_config_set( 'notify_flags', $t_flags, NO_USER, $t_project_id );

form_security_purge( 'notify_config' );

$t_redirect_url = plugin_page( 'notify_config_page', TRUE );

layout_page_header( null, $t_redirect_url );

layout_page_begin( $t_redirect_url );

html_operation_successful( $t_redirect_url );

layout_page_end();
