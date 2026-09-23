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

# for_user value of the calendar filter that keeps the events the current
# user has created, whoever their members are; ALL_USERS (0) and a real
# user id are the other two modes
define( 'CALENDAR_FILTER_AUTHOR', -1 );
# for_user value that keeps the events waiting for a reply of the current
# user, see calendar_rsvp_pending_event_ids()
define( 'CALENDAR_FILTER_PENDING', -2 );

# Where the calendar of the issue view page is placed, see bug_calendar_block_position.
# A row of the issue details table, the way it has always been.
define( 'CALENDAR_BUG_BLOCK_DETAILS', 0 );
# A widget of its own below the notes, for everyone.
define( 'CALENDAR_BUG_BLOCK_EXTRA', 1 );
# Every user picks one of the two on the account tab.
define( 'CALENDAR_BUG_BLOCK_USER_CHOICE', 2 );

/**
 * Whether the administrator leaves the placement of the issue page calendar
 * to every user, which puts the choice on the account tab
 * @return boolean
 */
function calendar_bug_block_user_choice() {
    return (int)plugin_config_get( 'bug_calendar_block_position' ) == CALENDAR_BUG_BLOCK_USER_CHOICE;
}

/**
 * Whether the calendar of the issue view page is shown as a widget of its
 * own below the notes (EVENT_VIEW_BUG_EXTRA) rather than as a row of the
 * issue details table (EVENT_VIEW_BUG_DETAILS). Resolves the administrator's
 * bug_calendar_block_position and, when the choice is left to the users,
 * the per-user bug_calendar_block_separate.
 *
 * @param integer|null $p_user_id defaults to the current user
 * @return boolean
 */
function calendar_bug_block_is_separate( $p_user_id = NULL ) {
    if( calendar_bug_block_user_choice() ) {
        return plugin_config_get( 'bug_calendar_block_separate', OFF, FALSE, $p_user_id ) == ON;
    }

    return (int)plugin_config_get( 'bug_calendar_block_position' ) == CALENDAR_BUG_BLOCK_EXTRA;
}

/**
 * Convert a Unix timestamp to a DateTime carrying a timezone, for use as
 * DTSTART/UNTIL of an RRULE. An integer timestamp would be treated as UTC
 * by php-rrule, freezing the UTC time of occurrences and shifting their
 * local time whenever DST starts or ends (issue #104); a DateTime with a
 * named timezone keeps the local time constant instead.
 *
 * @param integer $p_timestamp
 * @param DateTimeZone|null $p_timezone defaults to the current user's timezone
 * @return DateTime
 */
function calendar_rrule_datetime( $p_timestamp, ?DateTimeZone $p_timezone = NULL ) {
    $t_datetime = new DateTime( '@' . (int)$p_timestamp );
    return $t_datetime->setTimezone( $p_timezone === NULL ? new DateTimeZone( date_default_timezone_get() ) : $p_timezone );
}

/**
 * Timezone object for a user-supplied timezone name; falls back to the
 * current user's timezone on a blank or invalid name.
 *
 * @param string $p_timezone_name
 * @return DateTimeZone
 */
function calendar_timezone_get( $p_timezone_name ) {
    if( !is_blank( $p_timezone_name ) ) {
        try {
            return new DateTimeZone( $p_timezone_name );
        } catch( Exception $e ) {
            # fall through to the default
        }
    }
    return new DateTimeZone( date_default_timezone_get() );
}

/**
 * Timestamp of a date string interpreted in the given timezone
 * (midnight for date-only strings), FALSE when unparsable — the same
 * contract as strtotime(), which always uses the current timezone.
 *
 * @param string $p_date_string
 * @param DateTimeZone $p_timezone
 * @return integer|false
 */
function calendar_strtotime_in_timezone( $p_date_string, DateTimeZone $p_timezone ) {
    if( is_blank( $p_date_string ) ) {
        return FALSE;
    }
    try {
        $t_date = new DateTime( $p_date_string, $p_timezone );
    } catch( Exception $e ) {
        return FALSE;
    }
    return $t_date->getTimestamp();
}

/**
 * Period of the event described by the date and time fields of the event
 * form (date_event, date_event_to, event_time_start, event_time_finish),
 * interpreted in the given timezone. A blank end date means the event ends
 * on the day it starts.
 *
 * @param DateTimeZone $p_timezone Timezone the dates of the form are in.
 * @return array date_from   - start of the event,
 *               date_to     - end of the event,
 *               duration    - length of the event in seconds,
 *               time_finish - end time as seconds since midnight, what the
 *                             UNTIL of a series adds to its last day,
 *               end_offset  - date_to relative to the start of the first day,
 *                             what the end of a series adds to its last day.
 */
function calendar_event_form_period( DateTimeZone $p_timezone ) {
    $t_time_start  = gpc_get_int( 'event_time_start' );
    $t_time_finish = gpc_get_int( 'event_time_finish' );
    $t_day_from    = calendar_strtotime_in_timezone( gpc_get_string( 'date_event' ), $p_timezone );
    $t_day_to      = calendar_strtotime_in_timezone( gpc_get_string( 'date_event_to', '' ), $p_timezone );

    if( $t_day_to === FALSE ) {
        $t_day_to = $t_day_from;
    }

    $t_date_from = $t_day_from + $t_time_start;
    $t_date_to   = $t_day_to + $t_time_finish;

    return array(
                              'date_from'   => $t_date_from,
                              'date_to'     => $t_date_to,
                              'duration'    => $t_date_to - $t_date_from,
                              'time_finish' => $t_time_finish,
                              'end_offset'  => $t_date_to - $t_day_from,
    );
}

/**
 * Store a calendar state value in a cookie, using the lifetime
 * defined by the core cookie_time_length setting.
 *
 * @param string $p_name  Cookie name.
 * @param string $p_value Cookie value.
 * @return void
 */
function calendar_state_cookie_set( $p_name, $p_value ) {
    if( !headers_sent() ) {
        gpc_set_cookie( $p_name, $p_value, TRUE );
    }

    # make the value available within the current request as well
    $_COOKIE[$p_name] = $p_value;
}

/**
 * Get the calendar view type.
 * A value coming from the request wins and is remembered in a cookie,
 * otherwise the previously stored one is used.
 *
 * @return string 'week' or 'month'
 */
function calendar_view_type_get() {
    $t_allowed   = array( 'week', 'month' );
    $t_view_type = gpc_get_string( 'view', '' );

    if( in_array( $t_view_type, $t_allowed, TRUE ) ) {
        calendar_state_cookie_set( 'calendar_view_type', $t_view_type );
        return $t_view_type;
    }

    $t_view_type = gpc_get_cookie( 'calendar_view_type', 'week' );

    return in_array( $t_view_type, $t_allowed, TRUE ) ? $t_view_type : 'week';
}

/**
 * Get a boolean display flag.
 * A value coming from the request wins and is remembered in a cookie,
 * otherwise the previously stored one is used.
 *
 * @param string $p_param  Request parameter name
 * @param string $p_cookie Cookie name
 * @return boolean
 */
function calendar_state_flag_get( $p_param, $p_cookie ) {
    if( !gpc_isset( $p_param ) ) {
        return gpc_get_cookie( $p_cookie, '0' ) == '1';
    }

    $t_flag = gpc_get_bool( $p_param );
    calendar_state_cookie_set( $p_cookie, $t_flag ? '1' : '0' );

    return $t_flag;
}

/**
 * Get the full day (0-24) display flag of the calendar pages.
 *
 * @return boolean
 */
function calendar_full_time_get() {
    return calendar_state_flag_get( 'full_time', 'calendar_full_time' );
}

/**
 * Get the full day (0-24) display flag of the calendar block on the issue
 * page. Kept apart from the one of the calendar pages, so that the compact
 * block and the week view are switched independently. The block is printed
 * after the page headers are sent, so the cookie is stored from
 * EVENT_CORE_READY (see CalendarPlugin::core_ready()).
 *
 * @return boolean
 */
function calendar_issue_full_time_get() {
    return calendar_state_flag_get( 'issue_full_time', 'calendar_issue_full_time' );
}

/**
 * The colour a project is drawn with in the calendar. Hues are spread by the
 * golden angle so that neighbouring project ids never look alike, and the
 * colour is derived from the id alone, so it is the same on every page.
 *
 * @param integer $p_project_id
 * @return string Inline CSS custom properties: --project-color (border) and --project-bg (fill)
 */
function calendar_project_color_style( $p_project_id ) {
    $t_hue = (int)fmod( (int)$p_project_id * 137.508, 360 );

    return '--project-color:hsl(' . $t_hue . ',55%,50%);--project-bg:hsl(' . $t_hue . ',65%,86%);';
}

function helper_ensure_event_update_confirmed( $p_message ) {
    if( true == gpc_get_string( '_confirmed', FALSE ) ) {
        return gpc_get_string( '_confirmed' );
    }

    layout_page_header();
    layout_page_begin();

    echo '<div class="col-md-12 col-xs-12">';
    echo '<div class="space-10"></div>';
    echo '<div class="alert alert-warning center">';
    echo '<p class="bigger-110">';
    echo "\n" . $p_message . "\n";
    echo '</p>';
    echo '<div class="space-10"></div>';

    echo '<form method="post" class="center" action="">' . "\n";
    # CSRF protection not required here - user needs to confirm action
    # before the form is accepted.
    print_hidden_inputs( $_POST );
    print_hidden_inputs( $_GET );

    echo '<input type="hidden" name="_confirmed" value="THIS" />', "\n";
    echo '<input type="submit" class="btn btn-primary btn-white btn-round" value="' . plugin_lang_get( 'this_event' ) . '" />';
    echo "\n</form>";

    echo '<form method="post" class="center" action="">' . "\n";
    # CSRF protection not required here - user needs to confirm action
    # before the form is accepted.
    print_hidden_inputs( $_POST );
    print_hidden_inputs( $_GET );

    echo '<input type="hidden" name="_confirmed" value="THISANDFUTURE" />', "\n";
    echo '<input type="submit" class="btn btn-primary btn-white btn-round" value="' . plugin_lang_get( 'this_and_future_event' ) . '" />';
    echo "\n</form>";

    echo '<form method="post" class="center" action="">' . "\n";
    # CSRF protection not required here - user needs to confirm action
    # before the form is accepted.
    print_hidden_inputs( $_POST );
    print_hidden_inputs( $_GET );

    echo '<input type="hidden" name="_confirmed" value="ALL" />', "\n";
    echo '<input type="submit" class="btn btn-primary btn-white btn-round" value="' . plugin_lang_get( 'all_event' ) . '" />';
    echo "\n</form>\n";

    echo '<div class="space-10"></div>';
    echo '</div></div>';

    layout_page_end();
    exit;
}
