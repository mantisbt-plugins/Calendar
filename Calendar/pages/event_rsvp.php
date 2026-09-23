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
 * Record a reply given through the link of a notification mail or of the
 * description of an .ics file.
 *
 * The link carries the reply and is signed for the member it was sent to
 * (see calendar_rsvp_url()), but it acts only for that member logged in: a
 * guest is sent through the login page and back, the way the core does it
 * for any page, and a session of somebody else - the anonymous account
 * included - records nothing. So a forwarded mail, a shared calendar or a
 * mail scanner that follows the links cannot answer for the member, while
 * the signature keeps a crafted link from making a logged in member answer
 * what they never meant to. No form security token: the signature plays its
 * part, and a mail could not carry one anyway.
 *
 * A link that is stale - expired, forged, or sent to somebody who has left
 * the event since - records nothing and says so.
 */

if( !auth_is_user_authenticated() || current_user_is_anonymous() ) {
    access_denied();
}

$f_event_id = gpc_get_int( 'event_id' );
$f_user_id  = gpc_get_int( 'user_id' );
$f_status   = gpc_get_int( 'status' );
$f_expires  = gpc_get_int( 'expires' );
$f_token    = gpc_get_string( 'token', '' );

$t_valid = calendar_rsvp_feature_enabled()
        && in_array( $f_status, calendar_rsvp_replies(), true )
        && calendar_rsvp_token_valid( $f_event_id, $f_user_id, $f_status, $f_expires, $f_token )
        && event_exists( $f_event_id )
        && user_exists( $f_user_id ) && user_is_enabled( $f_user_id )
        && user_is_member_event( $f_user_id, $f_event_id )
        && access_has_event_level( plugin_config_get( 'view_event_threshold' ), $f_event_id, $f_user_id );

$t_own = $t_valid && auth_get_current_user_id() == $f_user_id;

if( $t_valid ) {
    $t_event            = event_get_row( $f_event_id );
    $g_project_override = (int)$t_event['project_id'];
    $t_event_url        = plugin_page( 'view', true ) . '&event_id=' . $f_event_id . '&date=' . (int)$t_event['date_from'] . '#members';
} else {
    $t_event_url = event_exists( $f_event_id )
            ? plugin_page( 'view', true ) . '&event_id=' . $f_event_id . '&date=' . (int)event_get_field( $f_event_id, 'date_from' )
            : plugin_page( 'calendar_user_page', true );
}

if( $t_own ) {
    event_member_set_status( $f_event_id, $f_user_id, $f_status, $f_user_id );

    $t_message = sprintf( plugin_lang_get( 'rsvp_recorded' ), $t_event['name'], calendar_rsvp_status_label( $f_status ) );
} elseif( $t_valid ) {
    # a genuine link, opened under another account: it is not theirs to use
    $t_message = sprintf( plugin_lang_get( 'rsvp_link_other_account' ), user_get_name( $f_user_id ) );
} else {
    $t_message = plugin_lang_get( 'rsvp_link_invalid' );
}

layout_page_header( plugin_lang_get( 'rsvp_your_reply' ), $t_own ? $t_event_url : null );
layout_page_begin( plugin_page( 'calendar_user_page' ) );

echo '<div class="col-md-12 col-xs-12">';
echo '<div class="space-10"></div>';
echo '<div class="alert ' . ( $t_own ? 'alert-success' : 'alert-warning' ) . '">' . string_display_line( $t_message ) . '</div>';
print_small_button( $t_event_url, plugin_lang_get( 'rsvp_open_event' ) );
echo '</div>';

layout_page_end();
