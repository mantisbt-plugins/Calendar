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
 * The page a member replies on when they come from the link of a mail or of
 * the description of an .ics file.
 *
 * The link opens the page, it records nothing by itself: the member reads
 * what the event is and presses a button, and the page shows the outcome in
 * place, with the buttons still there to change their mind. Below, a week
 * grid of the days the occurrence covers shows every event of the member on
 * those days, so that a collision is seen before the answer. The page is for
 * its member logged in only - a guest is sent through the login page and
 * back, the way the core does it for any page, and a session of somebody
 * else, the anonymous one included, is told whom the link was sent to. So a
 * forwarded mail, a shared calendar or a mail scanner that follows the links
 * cannot answer for the member.
 *
 * The link carries a signature (see calendar_rsvp_url()), which tells a
 * stale link - expired, forged, or sent to somebody who has left the event
 * since - from a current one. The buttons post back here under the form
 * security token, like any form of the core.
 */

if( !auth_is_user_authenticated() || current_user_is_anonymous() ) {
    access_denied();
}

$t_user_id = auth_get_current_user_id();
$t_posted  = gpc_isset( 'reply' );

$f_event_id = gpc_get_int( 'event_id' );

if( $t_posted ) {
    form_security_validate( 'event_rsvp' );

    $f_user_id = $t_user_id;
    $f_status  = gpc_get_int( 'reply' );
    $t_genuine = in_array( $f_status, calendar_rsvp_replies(), true );
} else {
    $f_user_id = gpc_get_int( 'user_id' );
    $t_genuine = calendar_rsvp_token_valid( $f_event_id, $f_user_id, gpc_get_int( 'expires' ), gpc_get_string( 'token', '' ) );
}

# the thresholds are read for the project of the event; the project is not
# made the current one before the event is known to be visible, since the
# page header would name it
$t_event_project_id = event_exists( $f_event_id ) ? (int)event_get_field( $f_event_id, 'project_id' ) : null;

$t_valid = calendar_rsvp_user_enabled( $f_user_id )
        && $t_genuine
        && $t_event_project_id !== null
        && user_exists( $f_user_id ) && user_is_enabled( $f_user_id )
        && user_is_member_event( $f_user_id, $f_event_id )
        && access_has_event_level( plugin_config_get( 'view_event_threshold', null, false, $f_user_id, $t_event_project_id ), $f_event_id, $f_user_id );

$t_own = $t_valid && $t_user_id == $f_user_id;

if( $t_valid ) {
    $t_event            = event_get_row( $f_event_id );
    $g_project_override = (int)$t_event['project_id'];
    $t_event_url        = plugin_page( 'view' ) . '&event_id=' . $f_event_id . '&date=' . (int)$t_event['date_from'] . '#members';
} else {
    # nothing about an event the viewer may not see is disclosed, not even its time
    $t_event_url = $t_event_project_id !== null
            && access_has_event_level( plugin_config_get( 'view_event_threshold', null, false, $t_user_id, $t_event_project_id ), $f_event_id )
            ? plugin_page( 'view' ) . '&event_id=' . $f_event_id . '&date=' . (int)event_get_field( $f_event_id, 'date_from' )
            : plugin_page( 'calendar_user_page' );
}

$t_alert = null;

if( $t_own && $t_posted ) {
    # the token is not purged: a reply is the same however often it is
    # given, and a reload of the outcome must not meet ERROR 2800
    event_member_set_status( $f_event_id, $t_user_id, $f_status, $t_user_id );

    $t_alert = array( 'success', sprintf( plugin_lang_get( 'rsvp_recorded' ), $t_event['name'], calendar_rsvp_status_label( $f_status ) ) );
} elseif( $t_valid && !$t_own ) {
    # a genuine link, opened under another account: it is not theirs to use
    $t_alert = array( 'warning', sprintf( plugin_lang_get( 'rsvp_link_other_account' ), user_get_name( $f_user_id ) ) );
} elseif( !$t_valid ) {
    $t_alert = array( 'warning', plugin_lang_get( 'rsvp_link_invalid' ) );
}

layout_page_header( plugin_lang_get( 'rsvp_your_reply' ) );
layout_page_begin( plugin_page( 'calendar_user_page' ) );

echo '<div class="col-md-12 col-xs-12">';
echo '<div class="space-10"></div>';

if( $t_alert !== null ) {
    echo '<div class="alert alert-' . $t_alert[0] . '">' . string_display_line( $t_alert[1] ) . '</div>';
}

if( $t_own ) {
    $t_status       = event_member_get_status( $f_event_id, $t_user_id );
    $t_is_recurring = !is_blank( $t_event['recurrence_pattern'] );

    # a series without an occurrence left is shown by its first one
    $t_occurrence = calendar_rsvp_occurrence( $t_event );
    if( $t_occurrence === null ) {
        $t_occurrence = array( (int)$t_event['date_from'], (int)$t_event['duration'] );
    }
    list( $t_start, $t_duration ) = $t_occurrence;

    echo '<div class="widget-box widget-color-blue2">';
    echo '<div class="widget-header widget-header-small">';
    echo '<h4 class="widget-title lighter"><i class="ace-icon fa fa-envelope-o"></i> ' . plugin_lang_get( 'rsvp_page_invitation_title' ) . '</h4>';
    echo '</div>';
    echo '<div class="widget-body"><div class="widget-main no-padding">';
    echo '<div class="table-responsive"><table class="table table-bordered table-condensed">';

    echo '<tr><th class="category width-30">' . plugin_lang_get( 'name_event' ) . '</th>';
    echo '<td>' . string_display_line( $t_event['name'] ) . '</td></tr>';

    echo '<tr><th class="category">' . lang_get( 'email_project' ) . '</th>';
    echo '<td>' . string_display_line( project_get_name( (int)$t_event['project_id'] ) ) . '</td></tr>';

    echo '<tr><th class="category">' . plugin_lang_get( 'date_event' ) . '</th>';
    echo '<td>' . string_display_line( calendar_rsvp_when_label( $t_start, $t_duration ) );
    if( $t_is_recurring ) {
        echo '<br><small>' . plugin_lang_get( 'rsvp_page_series_hint' ) . '</small>';
    }
    echo '</td></tr>';

    echo '<tr><th class="category">' . plugin_lang_get( 'rsvp_your_reply' ) . '</th><td>';
    print_rsvp_status_label( $t_status );
    echo '<div class="space-6"></div>';
    echo '<form method="post" action="' . plugin_page( 'event_rsvp' ) . '" class="form-inline">';
    echo form_security_field( 'event_rsvp' );
    echo '<input type="hidden" name="event_id" value="' . (int)$f_event_id . '" />';

    foreach( calendar_rsvp_replies() as $t_reply ) {
        $t_class = $t_status == $t_reply ? 'btn-primary' : 'btn-primary btn-white';
        echo '<button type="submit" name="reply" value="' . (int)$t_reply . '" class="btn btn-sm ' . $t_class . ' btn-round">'
                . plugin_lang_get( 'rsvp_reply_' . calendar_rsvp_status_name( $t_reply ) ) . '</button> ';
    }

    echo '</form>';
    echo '</td></tr>';
    echo '</table></div>';
    echo '</div></div>';
    echo '</div>';
    echo '<div class="space-10"></div>';
}

print_small_button( $t_event_url, plugin_lang_get( 'rsvp_open_event' ) );
echo '</div>';

# what else the member has on the days of the occurrence
if( $t_own ) {
    $t_calendar = new ViewEventReply( $f_event_id, $t_start, $t_duration, $t_user_id );
    $t_calendar->print_html();
}

layout_page_end();
