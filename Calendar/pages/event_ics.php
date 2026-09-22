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
 * Serve the iCalendar file of an event to whoever may view the event.
 *
 * This is the button on the event page and on event_ics_page.php, the page
 * the notification mails link to; a guest is sent through the login page
 * and back, the way the event page does it, and lands on a page rather than
 * on the file (see event_ics_page.php for why that matters). The file is
 * built afresh on every request, so it always shows the current state of the
 * event, and it changes nothing - hence no form security token.
 */

$f_event_id = gpc_get_int( 'event_id' );

event_ensure_exists( $f_event_id );

$t_event = event_get_row( $f_event_id );

# the thresholds are read for the project of the event, not for the current
# one, the way the event page does it
$g_project_override = (int)$t_event['project_id'];

access_ensure_event_level( plugin_config_get( 'view_event_threshold' ), $f_event_id );

$t_content = calendar_ical_event( $f_event_id, auth_get_current_user_id() );

http_caching_headers( false );
http_content_disposition_header( calendar_ical_filename( $t_event ) );

header( 'Content-Type: text/calendar; charset=UTF-8' );

echo $t_content;
