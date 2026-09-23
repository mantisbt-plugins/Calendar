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
 * Replies of the members of an event: whether they will take part.
 *
 * A reply is a status on the member row, one per member and event, never per
 * occurrence - a member answers for the series as a whole, the way they would
 * to a meeting invitation. The author of an event takes part by definition
 * and is marked so when the event is created; every other member starts
 * without a reply. When the time of the event changes, the replies are
 * dropped and asked for again, since they were given for another time.
 *
 * A member replies on the page of the event, or straight from a notification
 * mail or an .ics file through a personal link to a page with the reply
 * buttons. The page opens for the recipient of the link logged in only - a
 * forwarded mail or a shared calendar must not answer for them -, and the
 * reply is given there, by a button under the form security token. The link
 * is signed with an HMAC over the event, the user and an expiry date, keyed
 * by the master salt of the instance; nothing is stored for it, and a link
 * stops working once the event is over or the user is no longer a member.
 */

# no reply given yet
define( 'CALENDAR_RSVP_NONE', 0 );
# the member will take part
define( 'CALENDAR_RSVP_ACCEPTED', 1 );
# the member will not take part
define( 'CALENDAR_RSVP_DECLINED', 2 );
# the member is not sure yet
define( 'CALENDAR_RSVP_TENTATIVE', 3 );

# Who replies, the rsvp_mode set by the administrator: nobody,
define( 'CALENDAR_RSVP_MODE_OFF', 0 );
# every member,
define( 'CALENDAR_RSVP_MODE_ON', 1 );
# or every member who has switched the replies on for themselves on the
# calendar tab of their account.
define( 'CALENDAR_RSVP_MODE_USER_CHOICE', 2 );

/**
 * The mode the administrator has chosen for the replies
 * @return integer one of the CALENDAR_RSVP_MODE_* constants
 * @access public
 */
function calendar_rsvp_mode() {
    return (int)plugin_config_get( 'rsvp_mode' );
}

/**
 * Whether the replies exist at all on this instance, whoever uses them: what
 * belongs to an event rather than to one user - the author marked as taking
 * part, the replies dropped when the time changes, the summary of the
 * replies - hangs on this. What a user sees and does hangs on
 * calendar_rsvp_user_enabled().
 * @return boolean
 * @access public
 */
function calendar_rsvp_feature_enabled() {
    return calendar_rsvp_mode() != CALENDAR_RSVP_MODE_OFF;
}

/**
 * Whether the administrator leaves the replies to every user, which puts the
 * choice on the calendar tab of the account
 * @return boolean
 * @access public
 */
function calendar_rsvp_user_choice() {
    return calendar_rsvp_mode() == CALENDAR_RSVP_MODE_USER_CHOICE;
}

/**
 * Whether the given user takes part in the replies: they are offered the
 * reply buttons and links, the list of the invitations awaiting them with
 * its button and filter, the faded look of those invitations, their
 * reminders are held back until they reply, and they are mailed about the
 * replies of others
 * @param integer $p_user_id Integer representing user identifier.
 * @return boolean
 * @access public
 */
function calendar_rsvp_user_enabled( $p_user_id ) {

    $t_mode = calendar_rsvp_mode();

    if( $t_mode == CALENDAR_RSVP_MODE_OFF || (int)$p_user_id <= 0 ) {
        return false;
    }

    return $t_mode == CALENDAR_RSVP_MODE_ON
            || plugin_config_get( 'rsvp_enabled', ON, FALSE, (int)$p_user_id ) == ON;
}

/**
 * Every status a member row may hold, keyed by the name used in the language
 * strings and the style sheet
 * @return array name => status
 * @access public
 */
function calendar_rsvp_statuses() {
    return array(
                              'none'      => CALENDAR_RSVP_NONE,
                              'accepted'  => CALENDAR_RSVP_ACCEPTED,
                              'tentative' => CALENDAR_RSVP_TENTATIVE,
                              'declined'  => CALENDAR_RSVP_DECLINED,
    );
}

/**
 * The replies a member may give, in the order they are offered
 * @return array statuses
 * @access public
 */
function calendar_rsvp_replies() {
    return array( CALENDAR_RSVP_ACCEPTED, CALENDAR_RSVP_TENTATIVE, CALENDAR_RSVP_DECLINED );
}

/**
 * Name of a status, the suffix of its language keys
 * @param integer $p_status One of the CALENDAR_RSVP_* constants.
 * @return string
 * @access public
 */
function calendar_rsvp_status_name( $p_status ) {

    $t_name = array_search( (int)$p_status, calendar_rsvp_statuses(), true );

    return $t_name === false ? 'none' : $t_name;
}

/**
 * Label of a status as shown next to a member
 * @param integer $p_status One of the CALENDAR_RSVP_* constants.
 * @return string
 * @access public
 */
function calendar_rsvp_status_label( $p_status ) {
    return plugin_lang_get( 'rsvp_status_' . calendar_rsvp_status_name( $p_status ) );
}

/**
 * The replies of every member of the given event
 * @param integer $p_event_id Integer representing event identifier.
 * @return array user id => status
 * @access public
 * @uses database_api.php
 */
function event_member_get_statuses( $p_event_id ) {

    $t_event_member_table = plugin_table( 'event_member' );

    db_param_push();
    $t_query  = "SELECT user_id, status
			FROM $t_event_member_table
			WHERE event_id=" . db_param();
    $t_result = db_query( $t_query, array( (int)$p_event_id ) );

    $t_statuses = array();
    while( $t_row = db_fetch_array( $t_result ) ) {
        $t_statuses[(int)$t_row['user_id']] = (int)$t_row['status'];
    }

    return $t_statuses;
}

/**
 * The reply of one member of the given event; a user who is not a member has
 * given none
 * @param integer $p_event_id Integer representing event identifier.
 * @param integer $p_user_id  Integer representing user identifier.
 * @return integer one of the CALENDAR_RSVP_* constants
 * @access public
 */
function event_member_get_status( $p_event_id, $p_user_id ) {

    $t_statuses = event_member_get_statuses( $p_event_id );

    return isset( $t_statuses[(int)$p_user_id] ) ? $t_statuses[(int)$p_user_id] : CALENDAR_RSVP_NONE;
}

/**
 * Number of members holding each status
 * @param integer    $p_event_id Integer representing event identifier.
 * @param array|null $p_statuses Replies as returned by event_member_get_statuses(), read here when omitted.
 * @return array status => count, every status present
 * @access public
 */
function calendar_rsvp_summary( $p_event_id, ?array $p_statuses = null ) {

    if( $p_statuses === null ) {
        $p_statuses = event_member_get_statuses( $p_event_id );
    }

    $t_summary = array_fill_keys( calendar_rsvp_statuses(), 0 );

    foreach( $p_statuses as $t_status ) {
        $t_summary[$t_status]++;
    }

    return $t_summary;
}

/**
 * Record the reply of one member of the given event.
 *
 * An unchanged reply leaves no trace; a changed one is logged in the history
 * of the event, signalled to the other plugins and mailed to whoever the
 * notification matrix names for it. A user who is not a member of the event
 * cannot reply, the caller is expected to have checked that.
 * @param integer      $p_event_id       Integer representing event identifier.
 * @param integer      $p_user_id        The member who replies.
 * @param integer      $p_status         One of the CALENDAR_RSVP_* constants.
 * @param integer|null $p_acting_user_id User the history is logged for, defaults to the member.
 * @return boolean true if the reply was recorded, false if the user is not a member
 * @access public
 */
function event_member_set_status( $p_event_id, $p_user_id, $p_status, $p_acting_user_id = null ) {

    $t_changed = event_member_write_status( $p_event_id, $p_user_id, $p_status, $p_acting_user_id );

    if( $t_changed === null ) {
        return false;
    }

    if( $t_changed ) {
        event_signal( 'EVENT_CALENDAR_EVENT_RSVP', array( (int)$p_event_id, (int)$p_user_id, (int)$p_status ) );

        calendar_notify_rsvp( (int)$p_event_id, (int)$p_user_id, (int)$p_status, $p_acting_user_id );
    }

    return true;
}

/**
 * Mark the author of a freshly assembled event as taking part in it, if they
 * are among its members: whoever calls a meeting attends it. The mark is
 * written and logged only - it is nobody's reply to anything, so neither the
 * signal nor the mail of a reply goes out for it.
 * @param integer $p_event_id Integer representing event identifier.
 * @return void
 * @access public
 */
function event_member_accept_author( $p_event_id ) {

    if( !calendar_rsvp_feature_enabled() ) {
        return;
    }

    $t_author_id = (int)event_get_field( $p_event_id, 'author_id' );

    event_member_write_status( $p_event_id, $t_author_id, CALENDAR_RSVP_ACCEPTED, $t_author_id );
}

/**
 * Write the status of one member and log it, the part of a reply that both
 * the reply of a member and the mark of the author share
 * @param integer      $p_event_id       Integer representing event identifier.
 * @param integer      $p_user_id        The member.
 * @param integer      $p_status         One of the CALENDAR_RSVP_* constants.
 * @param integer|null $p_acting_user_id User the history is logged for, defaults to the member.
 * @return boolean|null true if the status changed, false if it was the same already, null if the user is not a member
 * @access private
 * @uses database_api.php
 */
function event_member_write_status( $p_event_id, $p_user_id, $p_status, $p_acting_user_id = null ) {

    $c_event_id = (int)$p_event_id;
    $c_user_id  = (int)$p_user_id;
    $c_status   = (int)$p_status;

    if( !in_array( $c_status, calendar_rsvp_statuses(), true ) ) {
        error_parameters( 'status' );
        trigger_error( ERROR_INVALID_FIELD_VALUE, ERROR );
    }

    $t_statuses = event_member_get_statuses( $c_event_id );

    if( !isset( $t_statuses[$c_user_id] ) ) {
        return null;
    }

    if( $t_statuses[$c_user_id] == $c_status ) {
        return false;
    }

    $t_event_member_table = plugin_table( 'event_member' );

    db_param_push();
    $t_query = "UPDATE $t_event_member_table SET status=" . db_param() . "
			WHERE event_id=" . db_param() . " AND user_id=" . db_param();
    db_query( $t_query, array( $c_status, $c_event_id, $c_user_id ) );

    if( $p_acting_user_id === null ) {
        $p_acting_user_id = $c_user_id;
    }

    event_history_log( $c_event_id, CALENDAR_HISTORY_RSVP, '', $c_user_id, $c_status, $p_acting_user_id );

    return true;
}

/**
 * Drop the replies of the members of the given event, because they were given
 * for a time that has changed. The member who made the change keeps their
 * reply: they know the new time. One history record is written for the lot.
 * @param integer      $p_event_id       Integer representing event identifier.
 * @param integer|null $p_keep_user_id   Member whose reply is kept, none by default.
 * @param integer|null $p_acting_user_id User the history is logged for, defaults to the logged in one.
 * @return void
 * @access public
 * @uses database_api.php
 */
function event_member_reset_statuses( $p_event_id, $p_keep_user_id = null, $p_acting_user_id = null ) {

    $c_event_id           = (int)$p_event_id;
    $t_event_member_table = plugin_table( 'event_member' );

    db_param_push();
    $t_query  = "UPDATE $t_event_member_table SET status=" . db_param() . "
			WHERE event_id=" . db_param() . " AND status<>" . db_param();
    $t_params = array( CALENDAR_RSVP_NONE, $c_event_id, CALENDAR_RSVP_NONE );

    if( $p_keep_user_id !== null ) {
        $t_query   .= ' AND user_id<>' . db_param();
        $t_params[] = (int)$p_keep_user_id;
    }

    db_query( $t_query, $t_params );

    if( db_affected_rows() > 0 ) {
        event_history_log( $c_event_id, CALENDAR_HISTORY_RSVP_RESET, '', '', '', $p_acting_user_id );
    }
}

/**
 * Signature of a reply link: an HMAC over everything the link carries, keyed
 * by the master salt of the instance
 * @param integer $p_event_id Integer representing event identifier.
 * @param integer $p_user_id  The member the link is for.
 * @param integer $p_expires  Timestamp the link stops working at.
 * @return string
 * @access private
 */
function calendar_rsvp_token( $p_event_id, $p_user_id, $p_expires ) {

    $t_data = 'rsvp|' . (int)$p_event_id . '|' . (int)$p_user_id . '|' . (int)$p_expires;

    return hash_hmac( 'sha256', $t_data, config_get_global( 'crypto_master_salt' ) );
}

/**
 * Whether a reply link is genuine and still current
 * @param integer $p_event_id Integer representing event identifier.
 * @param integer $p_user_id  The member the link is for.
 * @param integer $p_expires  Timestamp the link stops working at.
 * @param string  $p_token    Signature carried by the link.
 * @return boolean
 * @access public
 */
function calendar_rsvp_token_valid( $p_event_id, $p_user_id, $p_expires, $p_token ) {

    if( (int)$p_expires < time() ) {
        return false;
    }

    return hash_equals( calendar_rsvp_token( $p_event_id, $p_user_id, $p_expires ), (string)$p_token );
}

/**
 * Link a member follows to the page they reply to an event on.
 *
 * The link lives until the event is over - the end of the last occurrence for
 * a series - and for a day at the least, so that a mail about an event that
 * is about to start can still be answered.
 * @param integer $p_event_id Integer representing event identifier.
 * @param integer $p_user_id  The member the link is for.
 * @return string absolute URL
 * @access public
 */
function calendar_rsvp_url( $p_event_id, $p_user_id ) {

    $t_expires = max( (int)event_get_field( $p_event_id, 'date_to' ), time() + 86400 );

    return config_get_global( 'path' ) . plugin_page( 'event_rsvp', true )
            . '&event_id=' . (int)$p_event_id
            . '&user_id=' . (int)$p_user_id
            . '&expires=' . $t_expires
            . '&token=' . calendar_rsvp_token( $p_event_id, $p_user_id, $t_expires );
}

/**
 * The reply link of one recipient of a mail, as the paragraph the mail
 * bodies embed; empty when there is nothing to reply to - the feature is off
 * or the recipient is not a member of the event. Meant to be called in the
 * language of the recipient.
 * @param integer $p_event_id Integer representing event identifier.
 * @param integer $p_user_id  Recipient of the mail.
 * @return string
 * @access public
 */
function calendar_rsvp_mail_block( $p_event_id, $p_user_id ) {

    if( !calendar_rsvp_user_enabled( $p_user_id ) || !user_is_member_event( $p_user_id, $p_event_id ) ) {
        return '';
    }

    return sprintf( plugin_lang_get( 'notify_rsvp_links' ), calendar_rsvp_url( $p_event_id, $p_user_id ) );
}

/**
 * The occurrence of an event a member is asked about: the only one of a
 * single event, the next one of a series - a series is answered as a whole,
 * and the time that matters is the one coming up
 * @param array $p_event Event row.
 * @return array|null array( start, duration ), null when a series has no occurrence left
 * @access public
 */
function calendar_rsvp_occurrence( array $p_event ) {

    $t_start    = (int)$p_event['date_from'];
    $t_duration = (int)$p_event['duration'] > 0 ? (int)$p_event['duration'] : (int)$p_event['date_to'] - $t_start;

    if( !is_blank( $p_event['recurrence_pattern'] ) ) {
        $t_rset = new \RRule\RSet( $p_event['recurrence_pattern'] );
        $t_next = $t_rset->getOccurrencesAfter( new DateTime(), true, 1 );

        if( count( $t_next ) == 0 ) {
            return null;
        }

        $t_start = $t_next[0]->getTimestamp();
    }

    return array( $t_start, $t_duration );
}

/**
 * Time of an occurrence as the reply pages show it: the day and the times
 * of one within a day, the days and times of a longer one
 * @param integer $p_start    Start of the occurrence.
 * @param integer $p_duration Length of the occurrence in seconds.
 * @return string
 * @access public
 */
function calendar_rsvp_when_label( $p_start, $p_duration ) {

    $t_label = calendar_event_time_label( $p_start, $p_duration );

    if( calendar_event_is_multiday( $p_start, $p_duration ) ) {
        return $t_label;
    }

    return date( plugin_config_get( 'short_date_format' ), $p_start ) . ' ' . $t_label;
}

/**
 * The events that wait for a reply of the given member: they are a member
 * without a reply, they did not call the event themselves, it is not over
 * and they may view it. Soonest first.
 * @param integer $p_user_id Integer representing user identifier.
 * @return array list of event rows, each with 'occurrence' => array( start, duration ) added
 * @access public
 * @uses database_api.php
 */
function calendar_rsvp_pending_events( $p_user_id ) {

    $c_user_id            = (int)$p_user_id;
    $t_events_table       = plugin_table( 'events' );
    $t_event_member_table = plugin_table( 'event_member' );

    db_param_push();
    $t_query  = "SELECT e.id, e.project_id, e.author_id, e.name, e.date_from, e.date_to, e.duration, e.recurrence_pattern
                   FROM $t_events_table e
                   JOIN $t_event_member_table m ON m.event_id = e.id
                  WHERE m.user_id=" . db_param() . " AND m.status=" . db_param() . "
                    AND e.author_id<>" . db_param() . " AND e.activity='Y' AND e.date_to>" . db_param();
    $t_result = db_query( $t_query, array( $c_user_id, CALENDAR_RSVP_NONE, $c_user_id, time() ) );

    $t_events = array();
    while( $t_row = db_fetch_array( $t_result ) ) {

        if( !access_has_event_level( plugin_config_get( 'view_event_threshold' ), (int)$t_row['id'], $c_user_id ) ) {
            continue;
        }

        $t_row['occurrence'] = calendar_rsvp_occurrence( $t_row );

        if( $t_row['occurrence'] !== null ) {
            $t_events[] = $t_row;
        }
    }

    usort( $t_events, function( $p_a, $p_b ) {
        return $p_a['occurrence'][0] - $p_b['occurrence'][0];
    } );

    return $t_events;
}

/**
 * The events the given user is asked about and has not answered: a member
 * without a reply of an event somebody else has called. The answer of one
 * request is kept, the calendar grids ask for every event they draw.
 * @param integer $p_user_id Integer representing user identifier.
 * @return array event id => true
 * @access public
 * @uses database_api.php
 */
function calendar_rsvp_pending_event_ids( $p_user_id ) {

    static $s_cache = array();

    $c_user_id = (int)$p_user_id;

    if( !isset( $s_cache[$c_user_id] ) ) {
        $t_events_table       = plugin_table( 'events' );
        $t_event_member_table = plugin_table( 'event_member' );

        db_param_push();
        $t_query  = "SELECT m.event_id FROM $t_event_member_table m
                       JOIN $t_events_table e ON e.id = m.event_id
                      WHERE m.user_id=" . db_param() . " AND m.status=" . db_param() . " AND e.author_id<>" . db_param();
        $t_result = db_query( $t_query, array( $c_user_id, CALENDAR_RSVP_NONE, $c_user_id ) );

        $s_cache[$c_user_id] = array();
        while( $t_row = db_fetch_array( $t_result ) ) {
            $s_cache[$c_user_id][(int)$t_row['event_id']] = true;
        }
    }

    return $s_cache[$c_user_id];
}

/**
 * Whether the logged in user has yet to answer the given event, the test
 * the calendar grids fade an event out by; always false while the replies
 * are switched off
 * @param integer $p_event_id Integer representing event identifier.
 * @return boolean
 * @access public
 */
function calendar_rsvp_is_pending_for_current_user( $p_event_id ) {

    if( !auth_is_user_authenticated() || current_user_is_anonymous() || !calendar_rsvp_user_enabled( auth_get_current_user_id() ) ) {
        return false;
    }

    return isset( calendar_rsvp_pending_event_ids( auth_get_current_user_id() )[(int)$p_event_id] );
}

/**
 * Inline style that fades out an event the logged in user has yet to answer
 * @param integer $p_event_id Integer representing event identifier.
 * @return string
 * @access public
 */
function calendar_rsvp_pending_style( $p_event_id ) {
    return calendar_rsvp_is_pending_for_current_user( $p_event_id ) ? 'opacity:0.5;' : '';
}
