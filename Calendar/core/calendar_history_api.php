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
 * Change log of the calendar events, modeled on the issue history of the core.
 *
 * A record is either a plain field change - field_name / old_value / new_value
 * are filled in - or an action, in which case the type tells what happened and
 * old_value carries the single identifier the action refers to. Values are
 * stored raw and formatted only on display, so a change of the date format or
 * of the interface language does not rewrite the past.
 */

# a field of the event row was changed, field_name/old_value/new_value are used
define( 'CALENDAR_HISTORY_FIELD_CHANGE', 0 );
# the event row was created
define( 'CALENDAR_HISTORY_EVENT_CREATED', 1 );
# the event was split off a recurring series, old_value = parent event id
define( 'CALENDAR_HISTORY_CREATED_FROM_SERIES', 2 );
# a single occurrence was excluded, old_value = timestamp of the occurrence
define( 'CALENDAR_HISTORY_OCCURRENCE_DELETED', 3 );
# a user joined the event, old_value = user id of the member
define( 'CALENDAR_HISTORY_MEMBER_ADDED', 4 );
# a user left the event, old_value = user id of the member
define( 'CALENDAR_HISTORY_MEMBER_REMOVED', 5 );
# an issue was attached to the event, old_value = bug id
define( 'CALENDAR_HISTORY_BUG_ATTACHED', 6 );
# an issue was detached from the event, old_value = bug id
define( 'CALENDAR_HISTORY_BUG_DETACHED', 7 );
# a reminder was added to the event, old_value = offset in seconds
define( 'CALENDAR_HISTORY_REMINDER_ADDED', 8 );
# a reminder was removed from the event, old_value = offset in seconds
define( 'CALENDAR_HISTORY_REMINDER_REMOVED', 9 );

# a reminder was sent to one recipient, old_value = offset in seconds,
# user_id = the recipient. Written for events that occur once.
define( 'CALENDAR_HISTORY_REMINDER_SENT', 10 );

# a reminder of a recurring event was sent, old_value = offset in seconds,
# new_value = number of recipients. One record per occurrence and offset
# instead of one per recipient, which would swamp the history of a series.
define( 'CALENDAR_HISTORY_REMINDER_SENT_MANY', 11 );

# a member replied whether they will take part, old_value = user id of the
# member, new_value = one of the CALENDAR_RSVP_* constants
define( 'CALENDAR_HISTORY_RSVP', 12 );
# the replies of the members were dropped because the time of the event changed
define( 'CALENDAR_HISTORY_RSVP_RESET', 13 );

# a record written by another plugin through calendar_api_event_history_log(),
# field_name = basename of that plugin plus the name of its own field,
# old_value/new_value are its raw values. The number leaves room for further
# native types, the same way PLUGIN_HISTORY does in the core.
define( 'CALENDAR_HISTORY_PLUGIN', 100 );

# size of the field_name column of the event_history table, see schema()
define( 'CALENDAR_HISTORY_FIELD_NAME_MAXLEN', 64 );

/**
 * Fields of the event row that are tracked by the change log
 * @return array
 * @access public
 */
function event_history_tracked_fields() {
    return array(
                              'name',
                              'description',
                              'activity',
                              'date_from',
                              'date_to',
                              'duration',
                              'recurrence_pattern',
                              'timezone',
                              'parent_id',
    );
}

/**
 * Add one record to the history of the given event
 * @param integer      $p_event_id   Integer representing event identifier.
 * @param integer      $p_type       One of the CALENDAR_HISTORY_* constants.
 * @param string       $p_field_name Name of the changed field, for field changes only.
 * @param string       $p_old_value  Old value, or the identifier an action refers to.
 * @param string       $p_new_value  New value, for field changes only.
 * @param integer|null $p_user_id    Acting user, defaults to the logged in one.
 * @param integer|null $p_timestamp  Time of the change, defaults to now.
 * @return boolean (always true)
 * @access public
 */
function event_history_log( $p_event_id, $p_type, $p_field_name = '', $p_old_value = '', $p_new_value = '', $p_user_id = null, $p_timestamp = null ) {

    if( $p_user_id === null ) {
        # the public API and the command line have no session user
        $p_user_id = auth_is_user_authenticated() ? auth_get_current_user_id() : 0;
    }

    if( $p_timestamp === null ) {
        $p_timestamp = db_now();
    }

    $t_event_history_table = plugin_table( 'event_history' );

    db_param_push();
    $t_query = "INSERT INTO $t_event_history_table
                                                ( event_id, user_id, field_name,
                                                  old_value, new_value, type,
                                                  date_modified
                                                )
                                              VALUES
                                                ( " . db_param() . ',' . db_param() . ',' . db_param() . ",
                                                  " . db_param() . ',' . db_param() . ',' . db_param() . ",
                                                  " . db_param() . ')';

    db_query( $t_query, Array( (int)$p_event_id, (int)$p_user_id, $p_field_name,
                              db_mysql_fix_utf8( $p_old_value ), db_mysql_fix_utf8( $p_new_value ), (int)$p_type,
                              $p_timestamp ) );

    return true;
}

/**
 * Compare two event rows and log a record for every tracked field that differs
 * @param array        $p_old_row Event row as it was before the update.
 * @param array        $p_new_row Event row as it is after the update.
 * @param integer|null $p_user_id Acting user, defaults to the logged in one.
 * @return boolean (always true)
 * @access public
 */
function event_history_log_diff( array $p_old_row, array $p_new_row, $p_user_id = null ) {

    if( isset( $p_new_row['id'] ) ) {
        $t_event_id = (int)$p_new_row['id'];
    } elseif( isset( $p_old_row['id'] ) ) {
        $t_event_id = (int)$p_old_row['id'];
    } else {
        return true;
    }

    # one timestamp for the whole update, so that the records of a single edit
    # stay together whatever the sort order is
    $t_timestamp = db_now();

    foreach( event_history_tracked_fields() as $t_field_name ) {

        if( !array_key_exists( $t_field_name, $p_old_row ) || !array_key_exists( $t_field_name, $p_new_row ) ) {
            continue;
        }

        $t_old_value = (string)$p_old_row[$t_field_name];
        $t_new_value = (string)$p_new_row[$t_field_name];

        if( $t_old_value === $t_new_value ) {
            continue;
        }

        event_history_log( $t_event_id, CALENDAR_HISTORY_FIELD_CHANGE, $t_field_name,
                           $t_old_value, $t_new_value, $p_user_id, $t_timestamp );
    }

    return true;
}

/**
 * Return the history records of the given event
 * @param integer $p_event_id Integer representing event identifier.
 * @return array
 * @access public
 * @uses database_api.php
 */
function event_history_get_events( $p_event_id ) {

    $t_event_history_table = plugin_table( 'event_history' );

    # the direction follows the core preference; it cannot be parameterized,
    # so the value is whitelisted instead
    $t_order = ( config_get( 'history_order' ) == 'DESC' ) ? 'DESC' : 'ASC';

    db_param_push();
    $t_query  = "SELECT *
					  FROM $t_event_history_table
					  WHERE event_id=" . db_param() . "
					  ORDER BY date_modified $t_order, id $t_order";
    $t_result = db_query( $t_query, Array( (int)$p_event_id ) );

    $t_rows = array();
    while( $t_row  = db_fetch_array( $t_result ) ) {
        $t_rows[] = $t_row;
    }

    return $t_rows;
}

/**
 * Drop the whole history of the given event
 * @param integer $p_event_id Integer representing event identifier.
 * @return boolean (always true)
 * @access public
 * @uses database_api.php
 */
function event_history_delete( $p_event_id ) {

    $t_event_history_table = plugin_table( 'event_history' );

    db_param_push();
    $t_query = "DELETE FROM $t_event_history_table WHERE event_id=" . db_param();

    db_query( $t_query, Array( (int)$p_event_id ) );

    return true;
}

/**
 * Turn a raw history row into the strings shown to the user
 * @param array $p_row History row as returned by event_history_get_events().
 * @return array with the keys date, user_id, note, old_value, new_value and change
 * @access public
 */
function event_history_localize_row( array $p_row ) {

    $t_localized = array(
                              'date'      => date( config_get( 'normal_date_format' ), (int)$p_row['date_modified'] ),
                              'user_id'   => (int)$p_row['user_id'],
                              'note'      => '',
                              'old_value' => '',
                              'new_value' => '',
    );

    switch( (int)$p_row['type'] ) {

        case CALENDAR_HISTORY_FIELD_CHANGE:
            $t_localized['note']      = event_history_field_label( $p_row['field_name'] );
            $t_localized['old_value'] = event_history_format_value( $p_row['field_name'], $p_row['old_value'] );
            $t_localized['new_value'] = event_history_format_value( $p_row['field_name'], $p_row['new_value'] );
            break;

        case CALENDAR_HISTORY_EVENT_CREATED:
            $t_localized['note'] = plugin_lang_get( 'event_history_created' );
            break;

        case CALENDAR_HISTORY_CREATED_FROM_SERIES:
            $t_localized['note'] = sprintf( plugin_lang_get( 'event_history_created_from_series' ), bug_format_id( (int)$p_row['old_value'] ) );
            break;

        case CALENDAR_HISTORY_OCCURRENCE_DELETED:
            $t_localized['note']      = plugin_lang_get( 'event_history_occurrence_deleted' );
            $t_localized['old_value'] = date( config_get( 'normal_date_format' ), (int)$p_row['old_value'] );
            break;

        case CALENDAR_HISTORY_MEMBER_ADDED:
            # user_get_name() stays safe once the account is gone
            $t_localized['note']      = plugin_lang_get( 'event_history_member_added' );
            $t_localized['new_value'] = user_get_name( (int)$p_row['old_value'] );
            break;

        case CALENDAR_HISTORY_MEMBER_REMOVED:
            $t_localized['note']      = plugin_lang_get( 'event_history_member_removed' );
            $t_localized['old_value'] = user_get_name( (int)$p_row['old_value'] );
            break;

        case CALENDAR_HISTORY_BUG_ATTACHED:
            # the issue may have been deleted meanwhile, so only its id is shown
            $t_localized['note']      = plugin_lang_get( 'event_history_bug_attached' );
            $t_localized['new_value'] = bug_format_id( (int)$p_row['old_value'] );
            break;

        case CALENDAR_HISTORY_BUG_DETACHED:
            $t_localized['note']      = plugin_lang_get( 'event_history_bug_detached' );
            $t_localized['old_value'] = bug_format_id( (int)$p_row['old_value'] );
            break;

        case CALENDAR_HISTORY_REMINDER_ADDED:
            $t_localized['note']      = plugin_lang_get( 'event_history_reminder_added' );
            $t_localized['new_value'] = calendar_reminder_format_offset( (int)$p_row['old_value'] );
            break;

        case CALENDAR_HISTORY_REMINDER_REMOVED:
            $t_localized['note']      = plugin_lang_get( 'event_history_reminder_removed' );
            $t_localized['old_value'] = calendar_reminder_format_offset( (int)$p_row['old_value'] );
            break;

        case CALENDAR_HISTORY_REMINDER_SENT:
            $t_localized['note']      = plugin_lang_get( 'event_history_reminder_sent' );
            $t_localized['new_value'] = calendar_reminder_format_distance( (int)$p_row['old_value'] );
            break;

        case CALENDAR_HISTORY_REMINDER_SENT_MANY:
            $t_localized['note']      = sprintf( plugin_lang_get( 'event_history_reminder_sent_many' ), (int)$p_row['new_value'] );
            $t_localized['new_value'] = calendar_reminder_format_distance( (int)$p_row['old_value'] );
            break;

        case CALENDAR_HISTORY_RSVP:
            $t_localized['note']      = sprintf( plugin_lang_get( 'event_history_rsvp' ), user_get_name( (int)$p_row['old_value'] ) );
            $t_localized['new_value'] = calendar_rsvp_status_label( (int)$p_row['new_value'] );
            break;

        case CALENDAR_HISTORY_RSVP_RESET:
            $t_localized['note'] = plugin_lang_get( 'event_history_rsvp_reset' );
            break;

        case CALENDAR_HISTORY_PLUGIN:
            # the field name is already prefixed with the basename of the
            # calling plugin, which makes it the language key of that plugin as
            # well - the same lookup the core does for its own plugin history,
            # so a caller localizes its records by shipping the string with it
            $t_label = lang_get_defaulted( 'plugin_' . $p_row['field_name'], $p_row['field_name'] );

            $t_localized['note']      = sprintf( plugin_lang_get( 'event_history_plugin' ), $t_label );
            $t_localized['old_value'] = $p_row['old_value'];
            $t_localized['new_value'] = $p_row['new_value'];
            break;
    }

    # the same single "change" column the core history shows: both values
    # around an arrow for a field change, the only one present otherwise
    if( $t_localized['old_value'] !== '' && $t_localized['new_value'] !== '' ) {
        $t_localized['change'] = $t_localized['old_value'] . ' => ' . $t_localized['new_value'];
    } else {
        $t_localized['change'] = $t_localized['old_value'] . $t_localized['new_value'];
    }

    return $t_localized;
}

/**
 * Return the label of a tracked event field
 * @param string $p_field_name Name of the event field.
 * @return string
 * @access public
 */
function event_history_field_label( $p_field_name ) {

    switch( $p_field_name ) {

        case 'name':
            return plugin_lang_get( 'name_event' );

        case 'description':
            return plugin_lang_get( 'description_event' );

        case 'activity':
            return plugin_lang_get( 'event_activity' );

        case 'date_from':
            return plugin_lang_get( 'date_from' );

        case 'date_to':
            return plugin_lang_get( 'date_to' );

        case 'duration':
            return plugin_lang_get( 'event_duration' );

        case 'recurrence_pattern':
            return plugin_lang_get( 'event_recurrence' );

        case 'timezone':
            return plugin_lang_get( 'event_timezone' );

        case 'parent_id':
            return plugin_lang_get( 'event_parent' );

        default:
            return $p_field_name;
    }
}

/**
 * Format the stored value of a tracked event field for display
 * @param string $p_field_name Name of the event field.
 * @param string $p_value      Raw value as stored in the history row.
 * @return string
 * @access public
 */
function event_history_format_value( $p_field_name, $p_value ) {

    if( is_blank( $p_value ) ) {
        return '';
    }

    switch( $p_field_name ) {

        case 'date_from':
        case 'date_to':
            return date( config_get( 'normal_date_format' ), (int)$p_value );

        case 'duration':
            # a duration is a number of seconds, never a point in time; the
            # days are counted apart since gmdate() wraps at 24 hours
            $t_days = intdiv( (int)$p_value, 86400 );
            return ( $t_days > 0 ? $t_days . plugin_lang_get( 'days_short' ) . ' ' : '' ) . gmdate( 'H:i', (int)$p_value % 86400 );

        case 'recurrence_pattern':
        case 'description':
            # an RFC string and a free text description can both be multi
            # line, the table cell is not
            return implode( '; ', preg_split( "/\r\n|\n|\r/", trim( $p_value ) ) );

        default:
            return $p_value;
    }
}
