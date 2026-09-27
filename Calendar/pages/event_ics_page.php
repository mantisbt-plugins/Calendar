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
 * The page the notification mails link to for the iCalendar file of an event.
 *
 * The file itself is served by event_ics.php, and the mails used to link
 * there directly. A guest is sent through the login page and back, and a
 * link that ends in a file leaves the browser on the login form it has just
 * submitted, with a token that is spent: a second tap or a reload meets
 * ERROR 2800 over a file that has downloaded fine. So the mails link here,
 * to a page that shows a button - the login lands on a page, the file is a
 * click away.
 */

$f_event_id = gpc_get_int( 'event_id' );

event_ensure_exists( $f_event_id );

$t_event = event_get_row( $f_event_id );

# the thresholds are read for the project of the event, not for the current
# one, the way the event page does it
$g_project_override = (int)$t_event['project_id'];

access_ensure_event_level( plugin_config_get( 'view_event_threshold' ), $f_event_id );

layout_page_header( plugin_lang_get( 'event_ics_page_title' ) );
layout_page_begin( plugin_page( 'calendar_user_page' ) );

echo '<div class="col-md-12 col-xs-12">';
echo '<div class="space-10"></div>';
echo '<div class="widget-box widget-color-blue2">';
echo '<div class="widget-header widget-header-small">';
echo '<h4 class="widget-title lighter">';
echo '<i class="ace-icon fa fa-calendar"></i>';
echo string_display_line( project_get_name( (int)$t_event['project_id'] ) . ': ' . $t_event['name'] );
echo '</h4>';
echo '</div>';
echo '<div class="widget-body">';
echo '<div class="widget-main">';
echo '<p>' . string_display_line( plugin_lang_get( 'event_ics_page_hint' ) ) . '</p>';
echo '<div class="space-10"></div>';
print_small_button( plugin_page( 'event_ics' ) . '&event_id=' . $f_event_id, plugin_lang_get( 'event_ics_button' ) );
print_small_button( plugin_page( 'view' ) . '&event_id=' . $f_event_id . '&date=' . (int)$t_event['date_from'], plugin_lang_get( 'rsvp_open_event' ) );
echo '</div>';
echo '</div>';
echo '</div>';
echo '</div>';

layout_page_end();
