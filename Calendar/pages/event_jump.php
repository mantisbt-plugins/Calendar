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

# CSRF protection not required here - the page does not result in modifications

auth_ensure_user_authenticated();

# the number is taken as the view shows it: "#0000012", "12"
$f_event_id = (int)ltrim( trim( gpc_get_string( 'event_id' ) ), '#' );

event_ensure_exists( $f_event_id );

$t_event = event_get( $f_event_id );

$g_project_override = $t_event->project_id;

access_ensure_event_level( plugin_config_get( 'view_event_threshold' ), $f_event_id );

$t_url = plugin_page( 'view', true ) . '&event_id=' . $f_event_id;

# a series is opened at the occurrence coming up, the last one when it is over
if( event_is_recurrences( $f_event_id ) ) {
    $t_rset       = new \RRule\RSet( $t_event->recurrence_pattern );
    $t_now        = new DateTime();
    $t_occurrence = $t_rset->getOccurrencesAfter( $t_now, true, 1 );

    if( count( $t_occurrence ) == 0 ) {
        $t_occurrence = $t_rset->getOccurrencesBefore( $t_now, false, 1 );
    }

    $t_url .= '&date=' . ( count( $t_occurrence ) > 0 ? $t_occurrence[0]->getTimestamp() : $t_event->date_from );
}

print_header_redirect( $t_url );
