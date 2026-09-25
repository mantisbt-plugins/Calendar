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
 * The invitations that wait for a reply of the logged in member, reached
 * from the button in the header of the calendar.
 *
 * Every row can be answered in place: the buttons post back here under the
 * form security token, the reply is recorded and the answered event leaves
 * the list, which is shown again with the outcome above it. The name of an
 * event leads to its reply page, where the grid of the day shows what the
 * invitation collides with. The token is not purged, for the reason given
 * in event_rsvp.php.
 */

if( !auth_is_user_authenticated() || current_user_is_anonymous() || !calendar_rsvp_user_enabled( auth_get_current_user_id() ) ) {
    access_denied();
}

$t_user_id = auth_get_current_user_id();
$t_alert   = null;

if( gpc_isset( 'reply' ) ) {
    form_security_validate( 'rsvp_pending' );

    $f_event_id = gpc_get_int( 'event_id' );
    $f_status   = gpc_get_int( 'reply' );

    if( in_array( $f_status, calendar_rsvp_replies(), true ) && event_exists( $f_event_id )
            && user_is_member_event( $t_user_id, $f_event_id )
            && access_has_event_level( plugin_config_get( 'view_event_threshold', null, false, $t_user_id, (int)event_get_field( $f_event_id, 'project_id' ) ), $f_event_id, $t_user_id ) ) {

        event_member_set_status( $f_event_id, $t_user_id, $f_status, $t_user_id );

        $t_alert = array( 'success', sprintf( plugin_lang_get( 'rsvp_recorded' ), event_get_field( $f_event_id, 'name' ), calendar_rsvp_status_label( $f_status ) ) );
    } else {
        $t_alert = array( 'warning', plugin_lang_get( 'rsvp_link_invalid' ) );
    }
}

$t_events = calendar_rsvp_pending_events( $t_user_id );

layout_page_header( plugin_lang_get( 'rsvp_pending_title' ) );
layout_page_begin( plugin_page( 'calendar_user_page' ) );

echo '<div class="col-md-12 col-xs-12">';
echo '<div class="space-10"></div>';

if( $t_alert !== null ) {
    echo '<div class="alert alert-' . $t_alert[0] . '">' . string_display_line( $t_alert[1] ) . '</div>';
}

echo '<div class="widget-box widget-color-blue2">';
echo '<div class="widget-header widget-header-small">';
echo '<h4 class="widget-title lighter"><i class="ace-icon fa fa-envelope-o"></i> '
        . plugin_lang_get( 'rsvp_pending_title' ) . ' <span class="badge">' . count( $t_events ) . '</span></h4>';
echo '</div>';
echo '<div class="widget-body"><div class="widget-main no-padding">';

if( count( $t_events ) == 0 ) {
    echo '<div class="padding-8">' . plugin_lang_get( 'rsvp_pending_empty' ) . '</div>';
} else {
    echo '<div class="table-responsive"><table class="table table-bordered table-condensed table-striped">';
    echo '<thead><tr>';
    echo '<th>' . plugin_lang_get( 'name_event' ) . '</th>';
    echo '<th>' . lang_get( 'email_project' ) . '</th>';
    echo '<th>' . plugin_lang_get( 'date_event' ) . '</th>';
    echo '<th>' . plugin_lang_get( 'rsvp_your_reply' ) . '</th>';
    echo '</tr></thead><tbody>';

    foreach( $t_events as $t_event ) {
        list( $t_start, $t_duration ) = $t_event['occurrence'];

        echo '<tr>';
        echo '<td><a href="' . string_attribute( calendar_rsvp_url( (int)$t_event['id'], $t_user_id ) ) . '">'
                . string_display_line( $t_event['name'] ) . '</a></td>';
        echo '<td>' . string_display_line( project_get_name( (int)$t_event['project_id'] ) ) . '</td>';
        echo '<td>' . string_display_line( calendar_rsvp_when_label( $t_start, $t_duration ) );
        if( !is_blank( $t_event['recurrence_pattern'] ) ) {
            echo '<br><small>' . plugin_lang_get( 'rsvp_page_series_hint' ) . '</small>';
        }
        echo '</td>';
        echo '<td class="nowrap">';
        echo '<form method="post" action="' . plugin_page( 'rsvp_pending_page' ) . '" class="form-inline">';
        echo form_security_field( 'rsvp_pending' );
        echo '<input type="hidden" name="event_id" value="' . (int)$t_event['id'] . '" />';
        foreach( calendar_rsvp_replies() as $t_reply ) {
            echo '<button type="submit" name="reply" value="' . (int)$t_reply . '" class="btn btn-xs btn-primary btn-white btn-round">'
                    . plugin_lang_get( 'rsvp_reply_' . calendar_rsvp_status_name( $t_reply ) ) . '</button> ';
        }
        echo '</form>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

echo '</div></div>';
echo '</div>';
echo '</div>';

layout_page_end();
