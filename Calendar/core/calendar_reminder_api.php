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
 * Reminders about upcoming events.
 *
 * A reminder is an offset in seconds before the start of an occurrence. The
 * offsets of an event are stored in plugin_table( 'event_reminder' ) with
 * user_id = 0; if an event has none, every recipient is reminded by its own
 * personal defaults instead. A recipient may replace what applies to them by
 * a set of their own, stored in the same table under their user_id - that set
 * is theirs alone and is consulted before the offsets of the event. Nothing is
 * materialized per occurrence: the dispatcher expands the recurrence rules on
 * the fly and compares the resulting fire times with the window that has
 * passed since its previous run.
 *
 * The one exception is a reminder that a recipient has put off: "remind me
 * again in a quarter of an hour", pressed on a reminder they have just
 * received. That is a one-off reminder about one occurrence, stored with its
 * absolute moment in plugin_table( 'event_reminder_snooze' ), fired once by
 * the same dispatcher and forgotten. Nothing offers it in the pages or the
 * mails of the calendar so far; it exists for the plugins that deliver the
 * reminders through a channel of their own, see calendar_api_event_reminder_snooze().
 *
 * The dispatcher runs from EVENT_CRONJOB, that is from the command line, and
 * from EVENT_CORE_READY as a throttled fallback. Everything below must
 * therefore work without a session user and must not touch the Google client,
 * which is free to redirect the browser.
 */

# Marker offset stored for an event whose reminders are switched off. A real
# offset is at least one minute, so zero cannot collide with one; it lets the
# three states of an event - own reminders, none set, switched off - live in
# the same table and be copied and deleted like any other reminder.
define( 'CALENDAR_REMINDER_DISABLED', 0 );

/**
 * Whether the reminder feature is switched on for the whole instance
 * @return boolean
 * @access public
 */
function calendar_reminder_feature_enabled() {
    return plugin_config_get( 'reminders_feature_enabled' ) == ON;
}

/**
 * Units offered on the reminder rows, mapped to their length in seconds
 * @return array
 * @access public
 */
function calendar_reminder_units() {
    return array(
                              'minutes' => 60,
                              'hours'   => 3600,
                              'days'    => 86400,
    );
}

/**
 * Turn the rows of a reminder editor into a sorted list of offsets in seconds
 * @param array $p_values Numbers as posted by reminder_value[].
 * @param array $p_units  Unit names as posted by reminder_unit[].
 * @return array offsets in seconds, unique and ascending
 * @access public
 */
function calendar_reminder_offsets_from_input( array $p_values, array $p_units ) {

    $t_units      = calendar_reminder_units();
    $t_max_offset = (int)plugin_config_get( 'reminder_max_offset' );
    $t_max_rows   = (int)plugin_config_get( 'reminder_max_per_event' );

    $t_offsets = array();

    foreach( $p_values as $t_index => $t_value ) {

        $t_value = (int)$t_value;

        # an emptied number field arrives as zero and means "row cleared"
        if( $t_value === 0 ) {
            continue;
        }

        if( !isset( $p_units[$t_index] ) || !isset( $t_units[$p_units[$t_index]] ) ) {
            plugin_error( 'ERROR_REMINDER_INVALID', ERROR );
        }

        $t_offset = $t_value * $t_units[$p_units[$t_index]];

        if( $t_value < 1 || $t_offset > $t_max_offset ) {
            plugin_error( 'ERROR_REMINDER_INVALID', ERROR );
        }

        $t_offsets[] = $t_offset;
    }

    $t_offsets = array_values( array_unique( $t_offsets ) );
    sort( $t_offsets );

    if( count( $t_offsets ) > $t_max_rows ) {
        plugin_error( 'ERROR_REMINDER_INVALID', ERROR );
    }

    return $t_offsets;
}

/**
 * Read the reminder part of an event form: either the switched off marker or
 * the rows of the editor.
 * @return array offsets in seconds
 * @access public
 */
function calendar_reminder_offsets_from_event_form() {

    if( gpc_get_bool( 'reminders_disabled' ) ) {
        return array( CALENDAR_REMINDER_DISABLED );
    }

    return calendar_reminder_offsets_from_input( gpc_get_int_array( 'reminder_value', array() ),
                                                 gpc_get_string_array( 'reminder_unit', array() ) );
}

/**
 * Whether the given set of offsets switches reminders off for an event
 * @param array $p_offsets Offsets in seconds.
 * @return boolean
 * @access public
 */
function calendar_reminder_offsets_disabled( array $p_offsets ) {
    return in_array( CALENDAR_REMINDER_DISABLED, $p_offsets, true );
}

/**
 * Validate plain offsets in seconds against the same limits as the event
 * form: at least one minute each, at most reminder_max_offset, at most
 * reminder_max_per_event of them. Used by the public API, where the caller
 * hands over seconds instead of the value/unit rows of the form.
 * @param array $p_offsets Offsets in seconds.
 * @return array offsets in seconds, unique and ascending
 * @access public
 */
function calendar_reminder_offsets_normalize( array $p_offsets ) {

    $t_max_offset = (int)plugin_config_get( 'reminder_max_offset' );
    $t_max_rows   = (int)plugin_config_get( 'reminder_max_per_event' );

    $t_offsets = array();

    foreach( $p_offsets as $t_offset ) {

        $t_offset = (int)$t_offset;

        if( $t_offset < 60 || $t_offset > $t_max_offset ) {
            plugin_error( 'ERROR_REMINDER_INVALID', ERROR );
        }

        $t_offsets[] = $t_offset;
    }

    $t_offsets = array_values( array_unique( $t_offsets ) );
    sort( $t_offsets );

    if( count( $t_offsets ) > $t_max_rows ) {
        plugin_error( 'ERROR_REMINDER_INVALID', ERROR );
    }

    return $t_offsets;
}

/**
 * Split an offset into the largest unit that represents it exactly
 * @param integer $p_offset Offset in seconds.
 * @return array with the keys value and unit
 * @access public
 */
function calendar_reminder_offset_to_input( $p_offset ) {

    $t_offset = (int)$p_offset;
    $t_units  = calendar_reminder_units();

    foreach( array( 'days', 'hours' ) as $t_unit ) {

        if( $t_offset >= $t_units[$t_unit] && $t_offset % $t_units[$t_unit] == 0 ) {
            return array( 'value' => (int)( $t_offset / $t_units[$t_unit] ), 'unit' => $t_unit );
        }
    }

    return array( 'value' => (int)round( $t_offset / 60 ), 'unit' => 'minutes' );
}

/**
 * Format an offset of a reminder set, for the history of the set: the
 * switched off marker reads as such, anything else is a distance to the
 * start
 * @param integer $p_offset Offset in seconds.
 * @return string
 * @access public
 */
function calendar_reminder_format_offset( $p_offset ) {

    if( (int)$p_offset === CALENDAR_REMINDER_DISABLED ) {
        return plugin_lang_get( 'reminder_offset_disabled' );
    }

    return calendar_reminder_format_distance( $p_offset );
}

/**
 * Format the distance between the moment a reminder goes out and the start
 * of its occurrence, for the history of the sent reminders and for the
 * reminder mail. A scheduled reminder goes out before the start; a put off
 * one may go out at the start or after it, which is a distance of zero or
 * less.
 * @param integer $p_distance Seconds before the start, negative after it.
 * @return string
 * @access public
 */
function calendar_reminder_format_distance( $p_distance ) {

    $t_distance = (int)$p_distance;

    if( $t_distance == 0 ) {
        return plugin_lang_get( 'reminder_offset_at_start' );
    }

    $t_input = calendar_reminder_offset_to_input( abs( $t_distance ) );
    $t_text  = sprintf( plugin_lang_get( 'reminder_offset_' . $t_input['unit'] ), $t_input['value'] );

    if( $t_distance < 0 ) {
        return sprintf( plugin_lang_get( 'reminder_offset_after_start' ), $t_text );
    }

    return $t_text;
}

/**
 * Whether the given user wants to be reminded at all
 * @param integer $p_user_id Integer representing user identifier.
 * @return boolean
 * @access public
 */
function calendar_reminder_user_enabled( $p_user_id ) {
    return plugin_config_get( 'reminders_enabled', ON, FALSE, (int)$p_user_id ) == ON;
}

/**
 * Personal default offsets of the given user
 * @param integer $p_user_id Integer representing user identifier.
 * @return array offsets in seconds, unique and ascending
 * @access public
 */
function calendar_reminder_user_offsets( $p_user_id ) {

    $t_offsets = plugin_config_get( 'reminders_default', array(), FALSE, (int)$p_user_id );

    if( !is_array( $t_offsets ) ) {
        return array();
    }

    $t_offsets = array_values( array_unique( array_map( 'intval', $t_offsets ) ) );
    sort( $t_offsets );

    return $t_offsets;
}

/**
 * Return the reminder offsets of the given event: those of the event itself,
 * or the personal set of one of its recipients
 * @param integer $p_event_id Integer representing event identifier.
 * @param integer $p_user_id  Owner of the set, 0 for the event itself.
 * @return array offsets in seconds, ascending
 * @access public
 * @uses database_api.php
 */
function event_reminder_get_offsets( $p_event_id, $p_user_id = 0 ) {

    $t_event_reminder_table = plugin_table( 'event_reminder' );

    db_param_push();
    $t_query  = "SELECT time_offset
						  FROM $t_event_reminder_table
						  WHERE event_id=" . db_param() . " AND user_id=" . db_param() . "
						  ORDER BY time_offset ASC";
    $t_result = db_query( $t_query, Array( (int)$p_event_id, (int)$p_user_id ) );

    $t_offsets = array();
    while( $t_row      = db_fetch_array( $t_result ) ) {
        $t_offsets[] = (int)$t_row['time_offset'];
    }

    return $t_offsets;
}

/**
 * Every reminder set stored for the given event, keyed by its owner: 0 for
 * the offsets of the event itself, a user id for a personal set. One query
 * for the dispatcher instead of one per recipient.
 * @param integer $p_event_id Integer representing event identifier.
 * @return array user id => offsets in seconds, ascending
 * @access public
 * @uses database_api.php
 */
function event_reminder_get_all( $p_event_id ) {

    $t_event_reminder_table = plugin_table( 'event_reminder' );

    db_param_push();
    $t_query  = "SELECT user_id, time_offset
						  FROM $t_event_reminder_table
						  WHERE event_id=" . db_param() . "
						  ORDER BY user_id ASC, time_offset ASC";
    $t_result = db_query( $t_query, Array( (int)$p_event_id ) );

    $t_sets = array();
    while( $t_row = db_fetch_array( $t_result ) ) {
        $t_sets[(int)$t_row['user_id']][] = (int)$t_row['time_offset'];
    }

    return $t_sets;
}

/**
 * The reminders that apply to one recipient of an event, and where they come
 * from: the personal set of the recipient for this event, else the offsets of
 * the event, else the personal defaults of the recipient. A set that is
 * switched off yields no offsets.
 *
 * The reply of a member may hold the reminders back, see
 * calendar_reminder_rsvp_hold(); held reminders yield no offsets either, and
 * 'held' tells why.
 * @param integer    $p_event_id Integer representing event identifier.
 * @param integer    $p_user_id  Integer representing user identifier.
 * @param array|null $p_sets     Sets as returned by event_reminder_get_all(), read here when omitted.
 * @param array|null $p_statuses Replies as returned by event_member_get_statuses(), read here when omitted.
 * @return array 'source' => 'personal' | 'event' | 'defaults', 'offsets' => offsets in seconds, ascending,
 *               'held' => null | 'declined' | 'no_reply'
 * @access public
 */
function calendar_reminder_effective( $p_event_id, $p_user_id, ?array $p_sets = null, ?array $p_statuses = null ) {

    if( $p_sets === null ) {
        $p_sets = event_reminder_get_all( $p_event_id );
    }

    $c_user_id = (int)$p_user_id;

    if( $c_user_id > 0 && isset( $p_sets[$c_user_id] ) ) {
        $t_source  = 'personal';
        $t_offsets = $p_sets[$c_user_id];
    } elseif( isset( $p_sets[0] ) ) {
        $t_source  = 'event';
        $t_offsets = $p_sets[0];
    } else {
        $t_source  = 'defaults';
        $t_offsets = $c_user_id > 0 ? calendar_reminder_user_offsets( $c_user_id ) : array();
    }

    if( calendar_reminder_offsets_disabled( $t_offsets ) ) {
        $t_offsets = array();
    }

    $t_held = calendar_reminder_rsvp_hold( $p_event_id, $c_user_id, $t_source, $p_statuses );

    if( $t_held !== null ) {
        $t_offsets = array();
    }

    return array( 'source' => $t_source, 'offsets' => $t_offsets, 'held' => $t_held );
}

/**
 * Whether the reply of a member holds their reminders back. A member who
 * declined is not reminded at all. A member who has not replied yet is not
 * reminded either, unless they asked for it in their settings
 * ('reminders_no_reply') or set reminders of their own for this very event.
 * The author is never held back, and nobody is while replies are switched off.
 * @param integer    $p_event_id Integer representing event identifier.
 * @param integer    $p_user_id  Integer representing user identifier.
 * @param string     $p_source   Where the reminders of the user come from, see calendar_reminder_effective().
 * @param array|null $p_statuses Replies as returned by event_member_get_statuses(), read here when omitted.
 * @return string|null 'declined', 'no_reply' or null if nothing is held back
 * @access public
 */
function calendar_reminder_rsvp_hold( $p_event_id, $p_user_id, $p_source, ?array $p_statuses = null ) {

    $c_user_id = (int)$p_user_id;

    if( $c_user_id <= 0 || !calendar_rsvp_feature_enabled()
            || (int)event_get_field( $p_event_id, 'author_id' ) == $c_user_id ) {
        return null;
    }

    if( $p_statuses === null ) {
        $p_statuses = event_member_get_statuses( $p_event_id );
    }

    $t_status = isset( $p_statuses[$c_user_id] ) ? (int)$p_statuses[$c_user_id] : CALENDAR_RSVP_NONE;

    if( $t_status == CALENDAR_RSVP_DECLINED ) {
        return 'declined';
    }

    if( $t_status == CALENDAR_RSVP_NONE && $p_source != 'personal' && !calendar_reminder_user_no_reply( $c_user_id ) ) {
        return 'no_reply';
    }

    return null;
}

/**
 * Whether the given user wants to be reminded about events they have not
 * replied to yet
 * @param integer $p_user_id Integer representing user identifier.
 * @return boolean
 * @access public
 */
function calendar_reminder_user_no_reply( $p_user_id ) {
    return plugin_config_get( 'reminders_no_reply', OFF, FALSE, (int)$p_user_id ) == ON;
}

/**
 * Whether the given user is among those reminded about the given event: its
 * author or one of its members. The opt-out of the user is not looked at
 * here, the page tells them about it instead.
 * @param integer $p_event_id Integer representing event identifier.
 * @param integer $p_user_id  Integer representing user identifier.
 * @return boolean
 * @access public
 */
function calendar_reminder_user_is_recipient( $p_event_id, $p_user_id ) {

    $c_user_id = (int)$p_user_id;

    if( $c_user_id <= 0 ) {
        return false;
    }

    return (int)event_get_field( $p_event_id, 'author_id' ) == $c_user_id
            || in_array( $c_user_id, event_get_member_ids( $p_event_id ), true );
}

/**
 * Replace the personal reminder set of one recipient of an event. An empty
 * set is stored as the switched off marker, so that it does not fall back to
 * the offsets of the event; to get back to those, use event_reminder_user_reset().
 * Personal sets are not logged in the history of the event: they are private
 * to the recipient.
 * @param integer $p_event_id Integer representing event identifier.
 * @param integer $p_user_id  Integer representing user identifier.
 * @param array   $p_offsets  Offsets in seconds.
 * @return boolean (always true)
 * @access public
 * @uses database_api.php
 */
function event_reminder_user_set_all( $p_event_id, $p_user_id, array $p_offsets ) {

    $c_event_id             = (int)$p_event_id;
    $c_user_id              = (int)$p_user_id;
    $t_event_reminder_table = plugin_table( 'event_reminder' );

    $t_offsets = array_values( array_unique( array_map( 'intval', $p_offsets ) ) );
    sort( $t_offsets );

    if( count( $t_offsets ) == 0 ) {
        $t_offsets = array( CALENDAR_REMINDER_DISABLED );
    }

    event_reminder_user_reset( $c_event_id, $c_user_id );

    foreach( $t_offsets as $t_offset ) {

        db_param_push();
        $t_query = "INSERT INTO $t_event_reminder_table
                                                ( event_id, user_id, time_offset
                                                )
                                              VALUES
                                                ( " . db_param() . ',' . db_param() . ',' . db_param() . ')';

        db_query( $t_query, Array( $c_event_id, $c_user_id, $t_offset ) );
    }

    return true;
}

/**
 * Drop the personal reminder set of one recipient of an event, so that the
 * offsets of the event (or their personal defaults) apply to them again
 * @param integer $p_event_id Integer representing event identifier.
 * @param integer $p_user_id  Integer representing user identifier.
 * @return boolean (always true)
 * @access public
 * @uses database_api.php
 */
function event_reminder_user_reset( $p_event_id, $p_user_id ) {

    $t_event_reminder_table = plugin_table( 'event_reminder' );

    db_param_push();
    $t_query = "DELETE FROM $t_event_reminder_table WHERE event_id=" . db_param() . " AND user_id=" . db_param();

    db_query( $t_query, Array( (int)$p_event_id, (int)$p_user_id ) );

    return true;
}

/**
 * Copy the personal reminder sets of every recipient from one event to
 * another, used when an occurrence is split off its series: the members are
 * copied the same way, and their choices go with them
 * @param integer $p_source_event_id Event the sets are read from.
 * @param integer $p_target_event_id Event the sets are written to.
 * @return boolean (always true)
 * @access public
 */
function event_reminder_user_copy_all( $p_source_event_id, $p_target_event_id ) {

    foreach( event_reminder_get_all( $p_source_event_id ) as $t_user_id => $t_offsets ) {

        if( $t_user_id == 0 ) {
            continue;
        }

        event_reminder_user_set_all( $p_target_event_id, $t_user_id, $t_offsets );
    }

    return true;
}

/**
 * Replace the reminders of the event itself by the given set of offsets; the
 * personal sets of its recipients are left alone. Only the difference is
 * written, so an unchanged set leaves no history behind.
 * @param integer      $p_event_id       Integer representing event identifier.
 * @param array        $p_offsets        Offsets in seconds.
 * @param integer|null $p_acting_user_id User the history is logged for, defaults to the logged in one.
 * @return boolean (always true)
 * @access public
 * @uses database_api.php
 */
function event_reminder_set_all( $p_event_id, array $p_offsets, $p_acting_user_id = null ) {

    $c_event_id             = (int)$p_event_id;
    $t_event_reminder_table = plugin_table( 'event_reminder' );

    $t_offsets_current = event_reminder_get_offsets( $c_event_id );
    $t_offsets_new     = array_values( array_unique( array_map( 'intval', $p_offsets ) ) );
    sort( $t_offsets_new );

    $t_offsets_added   = array_diff( $t_offsets_new, $t_offsets_current );
    $t_offsets_removed = array_diff( $t_offsets_current, $t_offsets_new );

    if( count( $t_offsets_added ) == 0 && count( $t_offsets_removed ) == 0 ) {
        return true;
    }

    foreach( $t_offsets_removed as $t_offset ) {

        db_param_push();
        $t_query = "DELETE FROM $t_event_reminder_table
                                              WHERE event_id=" . db_param() . " AND user_id=0 AND time_offset=" . db_param();

        db_query( $t_query, Array( $c_event_id, $t_offset ) );

        event_history_log( $c_event_id, CALENDAR_HISTORY_REMINDER_REMOVED, '', $t_offset, '', $p_acting_user_id );
    }

    foreach( $t_offsets_added as $t_offset ) {

        db_param_push();
        $t_query = "INSERT INTO $t_event_reminder_table
                                                ( event_id, user_id, time_offset
                                                )
                                              VALUES
                                                ( " . db_param() . ', 0, ' . db_param() . ')';

        db_query( $t_query, Array( $c_event_id, $t_offset ) );

        event_history_log( $c_event_id, CALENDAR_HISTORY_REMINDER_ADDED, '', $t_offset, '', $p_acting_user_id );
    }

    return true;
}

/**
 * Drop every reminder of the given event, its own, the personal sets of its
 * recipients and the ones they have put off, used when the event itself is
 * deleted
 * @param integer $p_event_id Integer representing event identifier.
 * @return boolean (always true)
 * @access public
 * @uses database_api.php
 */
function event_reminder_delete_all( $p_event_id ) {

    $t_event_reminder_table = plugin_table( 'event_reminder' );

    db_param_push();
    $t_query = "DELETE FROM $t_event_reminder_table WHERE event_id=" . db_param();

    db_query( $t_query, Array( (int)$p_event_id ) );

    event_reminder_snooze_delete_all( $p_event_id );

    return true;
}

/**
 * Put the reminder of one recipient about one occurrence off to the given
 * moment. A recipient has one put off reminder per occurrence at a time, so
 * putting it off again moves it. Nothing is checked here, see
 * calendar_reminder_snooze_ensure_valid(); nothing is logged either, the
 * reminder is logged when it goes out, like any other.
 * @param integer $p_event_id   Integer representing event identifier.
 * @param integer $p_occurrence Timestamp the occurrence starts at.
 * @param integer $p_user_id    Integer representing user identifier.
 * @param integer $p_fire_at    Timestamp the reminder is to go out at.
 * @return boolean (always true)
 * @access public
 * @uses database_api.php
 */
function event_reminder_snooze_set( $p_event_id, $p_occurrence, $p_user_id, $p_fire_at ) {

    $c_event_id   = (int)$p_event_id;
    $c_occurrence = (int)$p_occurrence;
    $c_user_id    = (int)$p_user_id;
    $t_table      = plugin_table( 'event_reminder_snooze' );

    db_param_push();
    $t_query = "DELETE FROM $t_table
                 WHERE event_id=" . db_param() . " AND occurrence=" . db_param() . " AND user_id=" . db_param();
    db_query( $t_query, Array( $c_event_id, $c_occurrence, $c_user_id ) );

    db_param_push();
    $t_query = "INSERT INTO $t_table
                            ( event_id, occurrence, user_id, fire_at
                            )
                          VALUES
                            ( " . db_param() . ',' . db_param() . ',' . db_param() . ',' . db_param() . ')';
    db_query( $t_query, Array( $c_event_id, $c_occurrence, $c_user_id, (int)$p_fire_at ) );

    return true;
}

/**
 * Drop the put off reminders of the given event
 * @param integer $p_event_id Integer representing event identifier.
 * @return boolean (always true)
 * @access public
 * @uses database_api.php
 */
function event_reminder_snooze_delete_all( $p_event_id ) {

    $t_table = plugin_table( 'event_reminder_snooze' );

    db_param_push();
    $t_query = "DELETE FROM $t_table WHERE event_id=" . db_param();

    db_query( $t_query, Array( (int)$p_event_id ) );

    return true;
}

/**
 * Check that the reminder of a recipient may be put off to the given moment,
 * and halt with the usual error otherwise: the occurrence has to be one of
 * the event, the user has to be among those reminded about it, and the
 * moment has to lie ahead but before the occurrence is over - a reminder
 * about a meeting that has ended reminds of nothing. Whether the reminders
 * are switched on at all is for the caller to check.
 * @param integer $p_event_id   Integer representing event identifier.
 * @param integer $p_occurrence Timestamp the occurrence starts at.
 * @param integer $p_user_id    Integer representing user identifier.
 * @param integer $p_fire_at    Timestamp the reminder is to go out at.
 * @return void
 * @access public
 */
function calendar_reminder_snooze_ensure_valid( $p_event_id, $p_occurrence, $p_user_id, $p_fire_at ) {

    $t_event = event_get_row( $p_event_id );

    if( !event_occurrence_exists( $p_event_id, $p_occurrence ) ) {
        error_parameters( $p_event_id );
        plugin_error( 'ERROR_EVENT_TIME_PERIOD_NOT_FOUND', ERROR );
    }

    if( !in_array( (int)$p_user_id, event_reminder_recipients( $p_event_id, (int)$t_event['author_id'] ), true ) ) {
        error_parameters( $p_user_id );
        trigger_error( ERROR_USER_BY_ID_NOT_FOUND, ERROR );
    }

    # a single event stores the end of its only occurrence in date_to, a
    # series the length of one occurrence in duration
    $t_duration = (int)$t_event['duration'];
    $t_end      = $t_duration > 0 ? (int)$p_occurrence + $t_duration : (int)$t_event['date_to'];

    if( (int)$p_fire_at <= time() || (int)$p_fire_at >= $t_end ) {
        error_parameters( 'fire_at' );
        trigger_error( ERROR_INVALID_FIELD_VALUE, ERROR );
    }
}

/**
 * Users to be reminded about the given event: its author and its members,
 * except the members who declined it.
 * The member list is read with event_get_member_ids(), because
 * event_get_members() checks the access level of the session user, which the
 * command line does not have.
 * @param integer $p_event_id  Integer representing event identifier.
 * @param integer $p_author_id Integer representing the author of the event.
 * @return array user identifiers
 * @access public
 * @uses database_api.php
 */
function event_reminder_recipients( $p_event_id, $p_author_id ) {

    $t_user_ids = array_merge( array( (int)$p_author_id ), event_get_member_ids( $p_event_id ) );
    $t_statuses = calendar_rsvp_feature_enabled() ? event_member_get_statuses( $p_event_id ) : array();

    $t_recipients = array();
    foreach( array_unique( $t_user_ids ) as $t_user_id ) {

        if( $t_user_id <= 0 || !user_exists( $t_user_id ) || !user_is_enabled( $t_user_id ) ) {
            continue;
        }

        # a member who will not come is not reminded, a put off reminder of
        # theirs included; the author cannot decline their own event
        if( $t_user_id != (int)$p_author_id && isset( $t_statuses[$t_user_id] ) && $t_statuses[$t_user_id] == CALENDAR_RSVP_DECLINED ) {
            continue;
        }

        if( !calendar_reminder_user_enabled( $t_user_id ) ) {
            continue;
        }

        # the author and the members have access implicitly, this only catches
        # users that were meanwhile removed from the project
        if( !access_has_event_level( plugin_config_get( 'view_event_threshold' ), $p_event_id, $t_user_id ) ) {
            continue;
        }

        $t_recipients[] = $t_user_id;
    }

    return $t_recipients;
}

/**
 * Send the reminder mail of one occurrence to one recipient
 * @param array   $p_event      Event row with id, project_id, name.
 * @param integer $p_occurrence Timestamp the occurrence starts at.
 * @param integer $p_user_id    Integer representing user identifier.
 * @param integer $p_offset     Seconds between the reminder and the start, negative once the occurrence has begun.
 * @return boolean true if the mail was queued
 * @access public
 * @uses email_api.php
 */
function calendar_reminder_send_email( array $p_event, $p_occurrence, $p_user_id, $p_offset ) {

    $t_email = user_get_email( $p_user_id );

    if( is_blank( $t_email ) ) {
        return false;
    }

    # the mail is written in the language and in the timezone of its recipient,
    # not in those of whoever happens to trigger the dispatcher
    lang_push( user_pref_get_language( $p_user_id ) );

    $t_timezone = user_pref_get_pref( $p_user_id, 'timezone' );
    if( is_blank( $t_timezone ) ) {
        $t_timezone = config_get_global( 'default_timezone' );
    }

    date_set_timezone( $t_timezone );
    $t_occurrence_text = date( config_get( 'normal_date_format' ), (int)$p_occurrence );
    date_restore_timezone();

    # the command line has no request to derive a host from, so the link is
    # built from the configured path
    $t_url = config_get_global( 'path' ) . plugin_page( 'view', true )
            . '&event_id=' . (int)$p_event['id'] . '&date=' . (int)$p_occurrence;

    # a put off reminder may go out once the occurrence has begun, and then
    # cannot announce it as upcoming
    $t_body_key = (int)$p_offset > 0 ? 'reminder_email_body' : 'reminder_email_body_started';

    $t_subject = sprintf( plugin_lang_get( 'reminder_email_subject' ), $p_event['name'] );
    $t_body    = sprintf( plugin_lang_get( $t_body_key ),
                          $p_event['name'],
                          project_get_name( (int)$p_event['project_id'], false ),
                          $t_occurrence_text,
                          calendar_reminder_format_distance( $p_offset ),
                          $t_url );

    email_store( $t_email, $t_subject, $t_body );

    lang_pop();

    return true;
}

/**
 * Send every reminder that fell due since the previous run of the dispatcher.
 * Scheduling is plain arithmetic on Unix timestamps; timezones only matter when
 * the occurrence is rendered for a recipient.
 * @return void
 * @access public
 * @uses database_api.php
 */
function calendar_reminder_process() {

    if( !calendar_reminder_feature_enabled() || !db_is_connected() ) {
        return;
    }

    $t_events_table         = plugin_table( 'events' );
    $t_event_reminder_table = plugin_table( 'event_reminder' );

    if( !db_table_exists( $t_events_table ) || !db_table_exists( $t_event_reminder_table ) ) {
        return;
    }

    $t_now      = time();
    $t_last_run = (int)plugin_config_get( 'reminder_last_run' );

    # the window is claimed before it is processed: a dispatcher that dies half
    # way loses the rest of its window instead of replaying it on every request
    plugin_config_set( 'reminder_last_run', $t_now );

    if( $t_last_run == 0 || $t_last_run >= $t_now ) {
        # first run ever, or a concurrent run has already taken this window
        return;
    }

    $t_max_offset   = (int)plugin_config_get( 'reminder_max_offset' );
    $t_max_lateness = (int)plugin_config_get( 'reminder_max_lateness' );

    # fire times are looked for in ( window_start, now ], so that a long
    # downtime does not turn into a flood of overdue reminders
    $t_window_start = max( $t_last_run, $t_now - $t_max_lateness );
    $t_range_end    = $t_now + $t_max_offset;

    # a recurring event stores the end of its series in date_to, a single one
    # the end of its only occurrence - hence the two branches of the predicate
    db_param_push();
    $t_query  = "SELECT id, project_id, author_id, name, date_from, date_to, timezone, recurrence_pattern
						  FROM $t_events_table
						  WHERE activity = 'Y'
						    AND ( ( recurrence_pattern =  '' AND date_from > " . db_param() . " AND date_from <= " . db_param() . " )
						       OR ( recurrence_pattern >  '' AND date_to   > " . db_param() . " AND date_from <= " . db_param() . " ) )";
    $t_result = db_query( $t_query, Array( $t_window_start, $t_range_end, $t_window_start, $t_range_end ) );

    $t_events = array();
    while( $t_row     = db_fetch_array( $t_result ) ) {
        $t_events[] = $t_row;
    }

    foreach( $t_events as $t_event ) {

        $t_event_id = (int)$t_event['id'];

        if( is_blank( $t_event['recurrence_pattern'] ) ) {
            $t_occurrences = array( (int)$t_event['date_from'] );
        } else {
            # EXDATEs are part of the stored rule, so deleted occurrences never
            # show up here
            $t_occurrences = array();
            $t_rule        = RRule\RRule::createFromRfcString( $t_event['recurrence_pattern'] );

            foreach( $t_rule->getOccurrencesBetween( $t_window_start, $t_range_end, 1000 ) as $t_occurrence ) {
                $t_occurrences[] = $t_occurrence->getTimestamp();
            }
        }

        if( count( $t_occurrences ) == 0 ) {
            continue;
        }

        $t_reminder_sets = event_reminder_get_all( $t_event_id );
        $t_statuses      = event_member_get_statuses( $t_event_id );

        $t_recipients  = event_reminder_recipients( $t_event_id, (int)$t_event['author_id'] );
        $t_is_recurring = !is_blank( $t_event['recurrence_pattern'] );

        foreach( $t_occurrences as $t_occurrence ) {

            # recipients are collected per offset, so that a series can be
            # logged as one record per offset instead of one per recipient
            $t_sent = array();

            foreach( $t_recipients as $t_user_id ) {

                # the personal set of the recipient, else the reminders of the
                # event, else the personal defaults of the recipient
                $t_effective = calendar_reminder_effective( $t_event_id, $t_user_id, $t_reminder_sets, $t_statuses );
                $t_offsets   = $t_effective['offsets'];

                foreach( $t_offsets as $t_offset ) {

                    $t_offset    = (int)$t_offset;
                    $t_fire_time = $t_occurrence - $t_offset;

                    if( $t_fire_time <= $t_window_start || $t_fire_time > $t_now ) {
                        continue;
                    }

                    calendar_reminder_send_email( $t_event, $t_occurrence, $t_user_id, $t_offset );

                    # the signal is raised even when no mail could be sent, so
                    # that another plugin can deliver the reminder its own way
                    event_signal( 'EVENT_CALENDAR_EVENT_REMINDER', array( $t_event_id, $t_occurrence, $t_user_id, $t_offset ) );

                    plugin_log_event( sprintf( 'reminder of event #%d at %s sent to user #%d, %d seconds before the start',
                                               $t_event_id, date( 'c', $t_occurrence ), $t_user_id, $t_offset ) );

                    $t_sent[$t_offset][] = $t_user_id;
                }
            }

            calendar_reminder_log_history( $t_event_id, $t_sent, $t_is_recurring );
        }
    }

    calendar_reminder_process_snoozes( $t_window_start, $t_now );
}

/**
 * Send the put off reminders whose moment fell into the given window, each
 * once, and forget them. One is dropped without a word when its world has
 * changed meanwhile - its event is gone or no longer active, its occurrence
 * was cancelled or moved, its recipient is no longer reminded about the event
 * - and when its moment fell before the window: a put off reminder is not
 * replayed after a long downtime any more than a scheduled one.
 * @param integer $p_window_start Start of the window, exclusive.
 * @param integer $p_now          End of the window, inclusive.
 * @return void
 * @access private
 * @uses database_api.php
 */
function calendar_reminder_process_snoozes( $p_window_start, $p_now ) {

    $t_events_table = plugin_table( 'events' );
    $t_snooze_table = plugin_table( 'event_reminder_snooze' );

    if( !db_table_exists( $t_snooze_table ) ) {
        return;
    }

    db_param_push();
    $t_query  = "SELECT s.id, s.event_id, s.occurrence, s.user_id, s.fire_at,
                        e.project_id, e.author_id, e.name, e.activity, e.recurrence_pattern
                   FROM $t_snooze_table s
                   JOIN $t_events_table e ON e.id = s.event_id
                  WHERE s.fire_at <= " . db_param();
    $t_result = db_query( $t_query, Array( (int)$p_now ) );

    $t_due = array();
    while( $t_row = db_fetch_array( $t_result ) ) {
        $t_due[] = $t_row;
    }

    foreach( $t_due as $t_row ) {

        $t_event_id   = (int)$t_row['event_id'];
        $t_occurrence = (int)$t_row['occurrence'];
        $t_user_id    = (int)$t_row['user_id'];
        $t_fire_at    = (int)$t_row['fire_at'];

        if( $t_fire_at > $p_window_start
                && $t_row['activity'] == 'Y'
                && event_occurrence_exists( $t_event_id, $t_occurrence )
                && in_array( $t_user_id, event_reminder_recipients( $t_event_id, (int)$t_row['author_id'] ), true ) ) {

            # the offset of a put off reminder is where it went out relative to
            # the start, zero or negative once the occurrence has begun
            $t_offset = $t_occurrence - $t_fire_at;
            $t_event  = array( 'id' => $t_event_id, 'project_id' => (int)$t_row['project_id'], 'name' => $t_row['name'] );

            calendar_reminder_send_email( $t_event, $t_occurrence, $t_user_id, $t_offset );

            event_signal( 'EVENT_CALENDAR_EVENT_REMINDER', array( $t_event_id, $t_occurrence, $t_user_id, $t_offset ) );

            plugin_log_event( sprintf( 'put off reminder of event #%d at %s sent to user #%d, %d seconds before the start',
                                       $t_event_id, date( 'c', $t_occurrence ), $t_user_id, $t_offset ) );

            # one recipient asked for it, so it is logged for that recipient
            # whether the event is a series or not
            event_history_log( $t_event_id, CALENDAR_HISTORY_REMINDER_SENT, '', $t_offset, '', $t_user_id );
        }

        db_param_push();
        db_query( "DELETE FROM $t_snooze_table WHERE id=" . db_param(), Array( (int)$t_row['id'] ) );
    }
}

/**
 * Record the reminders sent for one occurrence in the history of the event.
 * A single event gets one record per recipient; a series gets one record per
 * offset with the number of recipients, because every occurrence would
 * otherwise add a record per recipient to the same event.
 * @param integer $p_event_id    Integer representing event identifier.
 * @param array   $p_sent        Recipients per offset in seconds.
 * @param boolean $p_is_recurring Whether the event carries a recurrence rule.
 * @return void
 * @access public
 */
function calendar_reminder_log_history( $p_event_id, array $p_sent, $p_is_recurring ) {

    foreach( $p_sent as $t_offset => $t_user_ids ) {

        if( $p_is_recurring ) {
            event_history_log( $p_event_id, CALENDAR_HISTORY_REMINDER_SENT_MANY, '', $t_offset, count( $t_user_ids ), NO_USER );
            continue;
        }

        foreach( $t_user_ids as $t_user_id ) {
            event_history_log( $p_event_id, CALENDAR_HISTORY_REMINDER_SENT, '', $t_offset, '', $t_user_id );
        }
    }
}
