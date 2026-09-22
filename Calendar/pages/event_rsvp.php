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
 * Record a reply given through the link of a notification mail.
 *
 * The link is personal and signed (see calendar_rsvp_url()), so it acts for
 * the member it was sent to without a session: no login, no form security
 * token - a mail could not carry one. A member who is logged in already is
 * taken to the event afterwards; anybody else is told that the reply has
 * been recorded on a page of its own, in the language of the member, and
 * offered the event page, which asks them to log in the usual way. A link
 * that is stale - expired, forged, or sent to somebody who has left the
 * event since - records nothing and says so.
 */

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

if( $t_valid ) {

    $t_event = event_get_row( $f_event_id );

    $g_project_override = (int)$t_event['project_id'];

    event_member_set_status( $f_event_id, $f_user_id, $f_status, $f_user_id );

    $t_event_url = plugin_page( 'view', true ) . '&event_id=' . $f_event_id . '&date=' . (int)$t_event['date_from'] . '#members';

    if( auth_is_user_authenticated() && auth_get_current_user_id() == $f_user_id ) {
        print_header_redirect( $t_event_url );
    }

    lang_push( user_pref_get_language( $f_user_id ) );

    $t_message = sprintf( plugin_lang_get( 'rsvp_recorded' ), $t_event['name'], calendar_rsvp_status_label( $f_status ) );
} else {

    $t_event_url = event_exists( $f_event_id )
            ? plugin_page( 'view', true ) . '&event_id=' . $f_event_id . '&date=' . (int)event_get_field( $f_event_id, 'date_from' )
            : plugin_page( 'calendar_user_page', true );

    $t_message = plugin_lang_get( 'rsvp_link_invalid' );
}

layout_login_page_begin();

echo '<div class="col-md-offset-3 col-md-6 col-sm-10 col-sm-offset-1">';
echo '<div class="login-container">';
echo '<div class="space-12"></div>';
layout_login_page_logo();
echo '<div class="space-24"></div>';
echo '<div class="position-relative">';
echo '<div class="signup-box visible widget-box no-border" id="login-box">';
echo '<div class="widget-body">';
echo '<div class="widget-main">';
echo '<div class="alert ' . ( $t_valid ? 'alert-success' : 'alert-warning' ) . '">' . string_display_line( $t_message ) . '</div>';
echo '<div class="space-10"></div>';
print_small_button( $t_event_url, plugin_lang_get( 'rsvp_open_event' ) );
echo '</div>';
echo '</div>';
echo '</div>';
echo '</div>';
echo '</div>';
echo '</div>';

if( $t_valid ) {
    lang_pop();
}

layout_login_page_end();
