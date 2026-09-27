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

class CalendarEventData {

    protected $id;
    protected $parent_id          = 0;
    protected $project_id         = null;
    protected $author_id          = 0;
    protected $changed_user_id    = 0;
    protected $status             = false;
    protected $name               = '';
    protected $description        = '';
    protected $activity           = 'Y';
    protected $date_changed       = NULL;
    protected $date_from          = 1;
    protected $date_to            = 1;
    protected $duration           = 0;
    protected $recurrence_pattern = '';
    protected $timezone           = '';
    private $loading              = false;

    /**
     * @private
     */
    public function __set( $name, $value ) {
        switch( $name ) {
            // integer types
            case 'id':
            case 'project_id':
            case 'author_id':
            case 'changed_user_id':
                $value = (int)$value;
                break;

            case 'name':
                # a MySQL table in utf8 rejects 4-byte chars such as emoji,
                # the core replaces them the same way for its own fields
                $value = db_mysql_fix_utf8( trim( $value ) );
                break;

            case 'timezone':
                $value = trim( $value );
                break;

            case 'description':
                # NULL from the database (nullable column) becomes ''
                $value = db_mysql_fix_utf8( trim( (string)$value ) );
                break;

            case 'date_changed':
            case 'date_from':
            case 'date_to':
                if( !is_numeric( $value ) ) {
                    $value = strtotime( $value, 0 );
                }
                $value = (int)$value;
                break;
        }
        $this->$name = $value;
    }

    /**
     * @private
     */
    public function __get( $name ) {
        return $this->{$name};
    }

    /**
     * @private
     */
    public function __isset( $name ) {
        return isset( $this->{$name} );
    }

    /**
     * fast-load database row into eventobject
     * @param array $p_row
     */
    public function loadrow( $p_row ) {
        $this->loading = true;

        foreach( $p_row as $var => $val ) {
            $this->__set( $var, $p_row[$var] );
        }
        $this->loading = false;
    }

    /**
     * validate current bug object for database insert/update
     * triggers error on failure
     * @param bool $p_update_extended
     */
    function validate() {

        if( is_blank( $this->name ) ) {
            error_parameters( plugin_lang_get( 'name_event' ) );
            trigger_error( ERROR_EMPTY_FIELD, ERROR );
        }

        if( is_blank( $this->date_from ) ) {
            error_parameters( plugin_lang_get( 'date_event' ) );
            trigger_error( ERROR_EMPTY_FIELD, ERROR );
        }

        if( is_blank( $this->date_to ) ) {
            error_parameters( plugin_lang_get( 'date_event' ) );
            trigger_error( ERROR_EMPTY_FIELD, ERROR );
        }

        if( $this->date_from >= $this->date_to ) {
            error_parameters( plugin_lang_get( 'date_event' ) );
            plugin_error( 'ERROR_DATE', ERROR );
        }
    }

    function create() {

        $this->validate();

        $t_event_table = plugin_table( 'events' );

        # Insert the rest of the data
        $query = "INSERT INTO $t_event_table
                                                ( project_id, name,
                                                  description, activity, author_id,
                                                  date_changed, changed_user_id, date_from,
                                                  date_to, duration, recurrence_pattern,
                                                  parent_id, timezone
                                                )
                                              VALUES
                                                ( " . db_param() . ',' . db_param() . ",
                                                  " . db_param() . ',' . db_param() . ',' . db_param() . ",
                                                  " . db_param() . ',' . db_param() . ',' . db_param() . ",
                                                  " . db_param() . ',' . db_param() . ',' . db_param() . ",
                                                  " . db_param() . ',' . db_param() . ')';

        db_query( $query, Array( $this->project_id, $this->name,
                                  $this->description, $this->activity, $this->author_id,
                                  $this->date_changed, $this->changed_user_id, $this->date_from,
                                  $this->date_to, $this->duration, $this->recurrence_pattern,
                                  $this->parent_id, $this->timezone ) );

        $this->id = db_insert_id( $t_event_table );

        # log the creation; the public API has no session user, so the user the
        # event is created for is used instead
        $t_history_user_id = null;
        if( $this->changed_user_id > 0 ) {
            $t_history_user_id = $this->changed_user_id;
        } elseif( $this->author_id > 0 ) {
            $t_history_user_id = $this->author_id;
        }

        event_history_log( $this->id, CALENDAR_HISTORY_EVENT_CREATED, '', '', '', $t_history_user_id );

        # an event with a parent is a single occurrence split off a series
        if( $this->parent_id != 0 ) {
            event_history_log( $this->id, CALENDAR_HISTORY_CREATED_FROM_SERIES, '', $this->parent_id, '', $t_history_user_id );
        }

        # EVENT_CALENDAR_EVENT_CREATED is deliberately NOT signalled here:
        # members, issue links and reminders are written by the caller after
        # this method returns, and a subscriber that looks the event up on the
        # signal must see it complete. Every creation flow calls
        # event_signal_created() once the event is fully assembled.

        return $this->id;
    }

    /**
     * Update a event from the given data structure
     * @param bool p_update_extended
     * @param bool p_bypass_email Default false, set to true to avoid generating emails (if sending elsewhere)
     * @return bool (always true)
     * @access public
     */
    function update() {
        $this->validate();

        $t_event_id = $this->id;

        # read the stored row before it is overwritten, the history is built by
        # comparing it against the properties of this object
        $t_old_row = event_get_row( $this->id );

        $t_calendar_event_table = plugin_table( 'events' );

        # Update all fields
        # Ignore date_submitted and last_updated since they are pulled out
        #  as unix timestamps which could confuse the history log and they
        #  shouldn't get updated like this anyway.  If you really need to change
        #  them use bug_set_field()
        $query = "UPDATE $t_calendar_event_table
                                            SET name=" . db_param() . ", description=" . db_param() . ",
						activity=" . db_param() . ", changed_user_id=" . db_param() . ",
                                                date_from=" . db_param() . ", date_to=" . db_param() . ", duration=" . db_param() . ",
                                                recurrence_pattern=" . db_param() . ", parent_id=" . db_param() . ", timezone=" . db_param();

        $t_fields = Array(
                                  $this->name, $this->description,
                                  $this->activity, $this->changed_user_id,
                                  $this->date_from, $this->date_to, $this->duration,
                                  $this->recurrence_pattern, $this->parent_id, $this->timezone,
        );

        $query .= " WHERE id=" . db_param();

        $t_fields[] = $this->id;

        db_query( $query, $t_fields );

        event_clear_cache( $this->id );

        $t_new_row = array(
                                  'id'                 => $this->id,
                                  'name'               => $this->name,
                                  'description'        => $this->description,
                                  'activity'           => $this->activity,
                                  'date_from'          => $this->date_from,
                                  'date_to'            => $this->date_to,
                                  'duration'           => $this->duration,
                                  'recurrence_pattern' => $this->recurrence_pattern,
                                  'timezone'           => $this->timezone,
                                  'parent_id'          => $this->parent_id,
        );

        event_history_log_diff( $t_old_row, $t_new_row, $this->changed_user_id > 0 ? $this->changed_user_id : null );

        # Update the last update date
        event_update_date( $t_event_id );

        # EVENT_CALENDAR_EVENT_UPDATED is deliberately NOT signalled here, for
        # the same reason as in create(): the reminders and the issue links of
        # a changed event are written by the caller after this method returns.
        # Every change flow calls event_signal_updated() once the change is
        # complete.

        return true;
    }

    /**
     * Delete a event from the given data structure
     * @param bool p_update_extended
     * @param bool p_bypass_email Default false, set to true to avoid generating emails (if sending elsewhere)
     * @return bool (always true)
     * @access public
     */
    function delete() {

        $t_event_id = $this->id;

        # the subscribers hear about the deletion first, the way the core
        # raises EVENT_BUG_DELETED before bug_delete() touches anything: the
        # event and its members are still there to be read by the handler
        event_signal( 'EVENT_CALENDAR_EVENT_DELETED', array( $t_event_id ) );

        $t_calendar_event_table = plugin_table( 'events' );

        # the history is only kept for events that can still be viewed
        event_history_delete( $t_event_id );

        $query = "DELETE FROM $t_calendar_event_table";

        $query .= " WHERE id=" . db_param();

        $t_fields[] = $this->id;

        db_query( $query, $t_fields );

        event_clear_cache( $this->id );

        # Update the last update date
        event_update_date( $this->id );

        return true;
    }

    function get_hm_start() {
        return strtotime( date( "H:i", $this->date_from ), 0 );
    }

}

$g_cache_calendar_event = array();

/**
 * Announce a newly created event to the subscribers of
 * EVENT_CALENDAR_EVENT_CREATED.
 *
 * Called by every creation flow as its last step, after the members, the
 * issue links and the reminders of the event are written - never from
 * CalendarEventData::create() itself, whose caller is still assembling the
 * event. A subscriber may therefore rely on reading the complete event by id.
 * @param integer $p_event_id Integer representing event identifier.
 * @return void
 * @access public
 */
function event_signal_created( $p_event_id ) {
    event_signal( 'EVENT_CALENDAR_EVENT_CREATED', array( (int)$p_event_id ) );
}

/**
 * Announce a changed event to the subscribers of EVENT_CALENDAR_EVENT_UPDATED.
 *
 * Called by every change flow as its last step, after the row, the reminders
 * and the issue links of the event are written - never from
 * CalendarEventData::update() itself, whose caller may still be at it. What
 * counts as a change is what the user did, the same thing the mail about a
 * change follows: a form submitted without touching anything is not news,
 * while a series that lost an occurrence or gained an issue link is, even
 * though its row may read the same.
 * @param integer $p_event_id Integer representing event identifier.
 * @return void
 * @access public
 */
function event_signal_updated( $p_event_id ) {
    event_signal( 'EVENT_CALENDAR_EVENT_UPDATED', array( (int)$p_event_id ) );
}

/**
 * Check if a event exists. If it doesn't then trigger an error
 * @param integer $p_event_id Integer representing bug identifier.
 * @return void
 * @access public
 */
function event_ensure_exists( $p_event_id ) {
    if( !event_exists( $p_event_id ) ) {
        error_parameters( $p_event_id );
        plugin_error( 'ERROR_EVENT_NOT_FOUND' );
    }
}

/**
 * Check if a event exists
 * @param integer $p_event_id Integer representing bug identifier.
 * @return boolean true if bug exists, false otherwise
 * @access public
 */
function event_exists( $p_event_id ) {
    $c_event_id = (int)$p_event_id;

    # Check for invalid id values
    if( $c_event_id <= 0 || $c_event_id > DB_MAX_INT ) {
        return false;
    }

    # bug exists if bug_cache_row returns any value
    if( event_cache_row( $c_event_id, false ) ) {
        return true;
    } else {
        return false;
    }
}

function event_occurrence_ensure_exist( $p_event_id, $p_date ) {
    if( !event_occurrence_exists( $p_event_id, $p_date ) ) {
        error_parameters( $p_event_id );
        plugin_error( 'ERROR_EVENT_TIME_PERIOD_NOT_FOUND' );
    }
}

/**
 * Whether the given timestamp is the start of an occurrence of the given
 * event: the only one of a single event, or one its rule yields - a cancelled
 * occurrence is an EXDATE of the stored rule and so is none
 * @param integer $p_event_id Integer representing event identifier.
 * @param integer $p_date     Timestamp the occurrence would start at.
 * @return boolean
 * @access public
 */
function event_occurrence_exists( $p_event_id, $p_date ) {

    $t_event = event_get( $p_event_id );

    if( is_blank( $t_event->recurrence_pattern ) ) {
        return (int)$p_date == (int)$t_event->date_from;
    }

    $t_rset = new \RRule\RSet( $t_event->recurrence_pattern );

    return $t_rset->occursAt( $p_date );
}

function event_is_recurrences( $p_event_id ) {
    $t_rrule_string = event_get_field( $p_event_id, 'recurrence_pattern' );
    return !is_blank( $t_rrule_string );
}

/**
 * return the specified field of the given event
 *  if the field does not exist, display a warning and return ''
 * @param integer $p_event_id     Integer representing bug identifier.
 * @param string  $p_field_name Field name to retrieve.
 * @return string
 * @access public
 */
function event_get_field( $p_event_id, $p_field_name ) {
    $t_row = event_get_row( $p_event_id );

    if( isset( $t_row[$p_field_name] ) ) {
        return $t_row[$p_field_name];
    } else {
        error_parameters( $p_field_name );
        trigger_error( ERROR_DB_FIELD_NOT_FOUND, WARNING );
        return '';
    }
}

/**
 * Returns an object representing the specified event
 * @param int $p_event_id integer representing event id
 * @return object CalendarEventData Object
 * @access public
 */
function event_get( $p_event_id ) {

    $row = event_get_row( $p_event_id );

    $t_event_data = new CalendarEventData();
    $t_event_data->loadrow( $row );
    return $t_event_data;
}

/**
 * Returns the record of the specified event
 * @param int p_event_id integer representing event id
 * @return array
 * @access public
 */
function event_get_row( $p_event_id ) {
    return event_cache_row( $p_event_id );
}

/**
 * Cache a event row if necessary and return the cached copy
 * @param array p_bug_id id of bug to cache from mantis_bug_table
 * @param array p_trigger_errors set to true to trigger an error if the bug does not exist.
 * @return bool|array returns an array representing the bug row if bug exists or false if bug does not exist
 * @access public
 * @uses database_api.php
 */
function event_cache_row( $p_event_id, $p_trigger_errors = true ) {
    global $g_cache_calendar_event;

    if( isset( $g_cache_calendar_event[$p_event_id] ) ) {
        return $g_cache_calendar_event[$p_event_id];
    }

    $c_event_id    = (int)$p_event_id;
    $t_event_table = plugin_table( 'events' );

    $query  = "SELECT *
				  FROM $t_event_table
				  WHERE id=" . db_param();
    $result = db_query( $query, Array( $c_event_id ) );

    if( 0 == db_num_rows( $result ) ) {
        $g_cache_calendar_event[$c_event_id] = false;

        if( $p_trigger_errors ) {
            error_parameters( $p_event_id );
            plugin_error( 'ERROR_EVENT_NOT_FOUND' );
        } else {
            return false;
        }
    }

    $row = db_fetch_array( $result );

    return event_add_to_cache( $row );
}

/**
 * Cache the rows of the given events with one query, for a list that is
 * about to look them up one by one; an event that does not exist is cached
 * as missing, the way event_cache_row() does it
 * @param array $p_event_ids List of event identifiers.
 * @return void
 * @access public
 * @uses database_api.php
 */
function event_cache_array_rows( array $p_event_ids ) {
    global $g_cache_calendar_event;

    $t_ids_to_fetch = array();
    foreach( $p_event_ids as $t_event_id ) {
        $c_event_id = (int)$t_event_id;
        if( $c_event_id > 0 && !isset( $g_cache_calendar_event[$c_event_id] ) ) {
            $t_ids_to_fetch[$c_event_id] = $c_event_id;
        }
    }

    if( count( $t_ids_to_fetch ) == 0 ) {
        return;
    }

    db_param_push();
    $t_params    = array();
    $t_in_values = array();
    foreach( $t_ids_to_fetch as $c_event_id ) {
        $t_params[]    = $c_event_id;
        $t_in_values[] = db_param();
    }

    $t_query  = 'SELECT * FROM ' . plugin_table( 'events' ) . ' WHERE id IN (' . implode( ',', $t_in_values ) . ')';
    $t_result = db_query( $t_query, $t_params );

    while( $t_row = db_fetch_array( $t_result ) ) {
        event_add_to_cache( $t_row );
        unset( $t_ids_to_fetch[(int)$t_row['id']] );
    }

    foreach( $t_ids_to_fetch as $c_event_id ) {
        $g_cache_calendar_event[$c_event_id] = false;
    }
}

/**
 * Inject a event into the event cache
 * @param array p_event_row event row to cache
 * @param array p_stats bugnote stats to cache
 * @return null
 * @access private
 */
function event_add_to_cache( $p_event_row, $p_stats = null ) {
    global $g_cache_calendar_event;

    $g_cache_calendar_event[(int)$p_event_row['id']] = $p_event_row;

    if( !is_null( $p_stats ) ) {
        $g_cache_calendar_event[(int)$p_event_row['id']]['_stats'] = $p_stats;
    }

    return $g_cache_calendar_event[(int)$p_event_row['id']];
}

/**
 * Clear a event from the cache or all events if no event id specified.
 * @param int event id to clear (optional)
 * @return null
 * @access public
 */
function event_clear_cache( $p_event_id = null ) {
    global $g_cache_calendar_event;

    if( null === $p_event_id ) {
        $g_cache_calendar_event = array();
    } else {
        unset( $g_cache_calendar_event[(int)$p_event_id] );
    }

    return true;
}

/**
 * updates the last_updated field
 * @param int p_bug_id integer representing bug ids
 * @return bool (always true)
 * @access public
 * @uses database_api.php
 */
function event_update_date( $p_event_id ) {
    $c_event_id = (int)$p_event_id;

    $t_event_table = plugin_table( 'events' );

    $query = "UPDATE $t_event_table
				  SET date_changed= " . db_param() . "
				  WHERE id=" . db_param();
    db_query( $query, Array( db_now(), $c_event_id ) );

    event_clear_cache( $c_event_id );

    return true;
}

/**
 * check if the given user is the reporter of the event
 * @param integer $p_event_id  Integer representing bug identifier.
 * @param integer $p_user_id Integer representing a user identifier.
 * @return boolean return true if the user is the reporter, false otherwise
 * @access public
 */
function event_is_user_reporter( $p_event_id, $p_user_id ) {
    if( event_get_field( $p_event_id, 'author_id' ) == $p_user_id ) {
        return true;
    } else {
        return false;
    }
}

/**
 * enable monitoring of this event for the user
 * @param integer      $p_event_id       Integer representing event identifier.
 * @param integer      $p_user_id        Integer representing user identifier.
 * @param integer|null $p_acting_user_id User the history is logged for, defaults to the logged in one.
 * @return boolean true if successful, false if unsuccessful
 * @access public
 */
function event_member_add( $p_event_id, $p_user_id, $p_acting_user_id = null ) {
    $c_event_id = (int)$p_event_id;
    $c_user_id  = (int)$p_user_id;

    # Make sure we aren't already monitoring this event
    if( user_is_member_event( $c_user_id, $c_event_id ) ) {
        return true;
    }

    # Don't let the anonymous user monitor events
    if( user_is_anonymous( $c_user_id ) ) {
        return false;
    }

    # Insert monitoring record
    $t_event_member_table = plugin_table( 'event_member' );
    db_param_push();
    $t_query              = "INSERT INTO $t_event_member_table ( user_id, event_id ) VALUES (" . db_param() . "," . db_param() . ")";
    db_query( $t_query, array( $c_user_id, $c_event_id ) );

    # log new monitoring action
    event_history_log( $c_event_id, CALENDAR_HISTORY_MEMBER_ADDED, '', $c_user_id, '', $p_acting_user_id );

    # updated the last_updated date
    event_update_date( $p_event_id );

//	email_monitor_added( $p_event_id, $p_user_id );

    return true;
}

/**
 * disable the membership in this event for the user
 * if $p_user_id = null, then event is unmonitored for all users.
 * @param integer $p_event_id  Integer representing event identifier.
 * @param integer $p_user_id Integer representing user identifier.
 * @return boolean (always true)
 * @access public
 * @uses database_api.php
 */
function event_member_delete( $p_event_id, $p_user_id = NULL ) {

    $t_event_member_table = plugin_table( 'event_member' );
    # Delete monitoring record
    db_param_push();
    $t_query              = "DELETE FROM $t_event_member_table WHERE event_id = " . db_param();
    $t_db_query_params[]  = $p_event_id;

    if( $p_user_id !== null ) {
        $t_query             .= ' AND user_id = ' . db_param();
        $t_db_query_params[] = $p_user_id;
    }

    db_query( $t_query, $t_db_query_params );

    # log new un-monitor action; a missing user means every member is dropped,
    # which only happens when the event itself is being deleted
    if( $p_user_id !== null && event_exists( $p_event_id ) ) {
        event_history_log( $p_event_id, CALENDAR_HISTORY_MEMBER_REMOVED, '', (int)$p_user_id );
    }

    # updated the last_updated date
//    event_update_date( $p_event_id );

    return true;
}

/**
 * Returns the members of the specified event as they are stored, without any
 * access check: the raw list for the code that chooses recipients or answers
 * another plugin, where no session user is involved. Use event_get_members()
 * wherever the list is shown to somebody.
 *
 * @param integer $p_event_id Integer representing event identifier.
 * @return array List of user identifiers, may be empty.
 * @access public
 * @uses database_api.php
 */
function event_get_member_ids( $p_event_id ) {
    $t_event_member_table = plugin_table( 'event_member' );

    db_param_push();
    $t_query  = "SELECT user_id
			FROM $t_event_member_table
			WHERE event_id=" . db_param();
    $t_result = db_query( $t_query, array( (int)$p_event_id ) );

    $t_user_ids = array();
    while( $t_row = db_fetch_array( $t_result ) ) {
        $t_user_ids[] = (int)$t_row['user_id'];
    }

    return $t_user_ids;
}

/**
 * Returns the list of users members the specified event
 *
 * @param integer      $p_event_id Integer representing event identifier.
 * @param integer|null $p_user_id  User the list is read for, defaults to null to use current user.
 * @return array
 */
function event_get_members( $p_event_id, $p_user_id = null ) {
    # read for the project of the event, the list is asked for outside of its page too
    if( !access_has_event_level( plugin_config_get( 'show_member_list_threshold', null, false, $p_user_id, (int)event_get_field( $p_event_id, 'project_id' ) ), $p_event_id, $p_user_id ) ) {
        return array();
    }

    $t_users = event_get_member_ids( $p_event_id );

    user_cache_array_rows( $t_users );

    return $t_users;
}

function event_get_attached_bugs_id( $p_event_id ) {
    $p_table_calendar_relationship = plugin_table( "relationship" );

    $pResult = Array();
    if( db_table_exists( $p_table_calendar_relationship ) && db_is_connected() ) {
        $query  = "SELECT bug_id
				  FROM $p_table_calendar_relationship
				  WHERE event_id=" . db_param();
        $result = db_query( $query, (array)$p_event_id );


        $cResult     = array();
        $t_row_count = db_num_rows( $result );

        for( $i = 0; $i < $t_row_count; $i++ ) {
            array_push( $cResult, db_fetch_array( $result ) );
            $pResult[$i] = $cResult[$i]["bug_id"];
        }

        sort( $pResult );
    }
    return $pResult;
}

function get_events_id_from_bug_id( $p_bug_id ) {

    $p_table_calendar_relationship = plugin_table( "relationship" );

    if( db_table_exists( $p_table_calendar_relationship ) && db_is_connected() ) {
        $query = "SELECT event_id
				  FROM $p_table_calendar_relationship
				  WHERE bug_id=" . db_param();

        $result = db_query( $query, array( $p_bug_id ) );


        $cResult = array();

        $t_row_count = db_num_rows( $result );
        $pResult     = Array();

        for( $i = 0; $i < $t_row_count; $i++ ) {
            array_push( $cResult, db_fetch_array( $result ) );
            $pResult[$i] = $cResult[$i]["event_id"];
        }

        return $pResult;
    }
}

/**
 * The history of an issue is read by everybody who may view the issue, the
 * event may be in a project they cannot see: the records written into it by
 * event_detach_issue() and event_attach_issue() name the event by its number,
 * never by its name.
 */
function event_detach_issue( $p_event_id, $p_bugs_id ) {

    $t_table_calendar_relationship = plugin_table( "relationship" );

    $query = "DELETE FROM $t_table_calendar_relationship
			          WHERE event_id=" . db_param() . ' AND bug_id=' . db_param();

    foreach( $p_bugs_id as $t_bug_id ) {
        if( !bug_exists( $t_bug_id ) ) {
            continue;
        }
        db_query( $query, array( $p_event_id, $t_bug_id ) );

        plugin_history_log(
                $t_bug_id, plugin_lang_get( "event" ), "", plugin_lang_get( "event_hystory_bug_detach" ) . ": " . bug_format_id( $p_event_id )
        );
        bug_update_date( $t_bug_id );

        # the event may already be gone when its relationships are cleaned up
        if( event_exists( $p_event_id ) ) {
            event_history_log( $p_event_id, CALENDAR_HISTORY_BUG_DETACHED, '', $t_bug_id );
        }
    }

    return TRUE;
}

function event_attach_issue( $p_event_id, array $p_bugs_id ) {
    $t_table_calendar_relationship = plugin_table( "relationship" );

    $query = "INSERT
                                              INTO $t_table_calendar_relationship
                                                  ( event_id, bug_id )
                                              VALUES
                                                  ( " . db_param() . ', ' . db_param() . ')';

    foreach( $p_bugs_id as $t_bug_id ) {
        if( !bug_exists( $t_bug_id ) ) {
            continue;
        }
        db_query( $query, Array( $p_event_id, $t_bug_id ) );

        event_history_log( $p_event_id, CALENDAR_HISTORY_BUG_ATTACHED, '', $t_bug_id );

        plugin_history_log(
                $t_bug_id, plugin_lang_get( "event" ), "", plugin_lang_get( "event_hystory_create" ) . ": " . bug_format_id( $p_event_id )
        );
        bug_update_date( $t_bug_id );
    }
    return TRUE;
}

/**
 * Keep the issues the given user may view, in the project each issue lives in;
 * an issue that does not exist is dropped as well. Attaching an event to an
 * issue, or listing the issues of an event, must never disclose an issue.
 * @param array        $p_bug_ids List of issue identifiers.
 * @param integer|null $p_user_id User the issues are checked for, defaults to the logged in one.
 * @return array issue identifiers, in the given order
 * @access public
 */
function event_bug_ids_filter_viewable( array $p_bug_ids, $p_user_id = null ) {

    if( $p_user_id === null ) {
        $p_user_id = auth_get_current_user_id();
    }

    $t_bug_ids = array();

    foreach( $p_bug_ids as $t_bug_id ) {
        $c_bug_id = (int)$t_bug_id;

        if( !bug_exists( $c_bug_id ) ) {
            continue;
        }

        $t_view_threshold = config_get( 'view_bug_threshold', null, $p_user_id, bug_get_field( $c_bug_id, 'project_id' ) );

        if( access_has_bug_level( $t_view_threshold, $c_bug_id, $p_user_id ) ) {
            $t_bug_ids[] = $c_bug_id;
        }
    }

    return $t_bug_ids;
}

/**
 * Check that the given users may become the members of an event of the given
 * project, created by the given author, and halt with the usual error
 * otherwise: every member exists, is not the anonymous account and reaches
 * the project at the view_event_threshold level, and the author passes
 * member_add_others_event_threshold as soon as the list names somebody else.
 * @param integer $p_project_id Project the event belongs to.
 * @param integer $p_author_id  User the event is created by.
 * @param array   $p_member_ids List of user identifiers.
 * @return void
 * @access public
 */
function event_members_ensure_eligible( $p_project_id, $p_author_id, array $p_member_ids ) {

    $t_adds_other_members = FALSE;

    foreach( $p_member_ids as $t_member_id ) {
        $c_member_id = (int)$t_member_id;

        user_ensure_exists( $c_member_id );

        $t_member_threshold = plugin_config_get( 'view_event_threshold', NULL, FALSE, $c_member_id, $p_project_id );

        if( user_is_anonymous( $c_member_id )
                || !access_has_project_level( $t_member_threshold, $p_project_id, $c_member_id ) ) {
            error_parameters( 'members' );
            trigger_error( ERROR_INVALID_FIELD_VALUE, ERROR );
        }

        if( $c_member_id != $p_author_id ) {
            $t_adds_other_members = TRUE;
        }
    }

    # signing somebody else up for an event is a separate permission
    if( $t_adds_other_members ) {
        $t_add_others_threshold = plugin_config_get( 'member_add_others_event_threshold', NULL, FALSE, $p_author_id, $p_project_id );

        if( !access_has_project_level( $t_add_others_threshold, $p_project_id, $p_author_id ) ) {
            access_denied();
        }
    }
}

/**
 * Check the number of attachments a bug has (if any)
 * @param integer $p_bug_id A bug identifier.
 * @return integer
 */
function calendar_event_issue_attachment_count( $p_bug_id ) {
	global $g_cache_calendar_event_count;

	# If it's not in cache, load the value
	if( !isset( $g_cache_calendar_event_count[$p_bug_id] ) ) {
		calendar_event_issue_attachment_count_cache( array( (int)$p_bug_id ) );
	}

	return $g_cache_calendar_event_count[$p_bug_id];
}

/**
 * Fills the cache with the attachment count from a list of bugs, counting the
 * events the logged in user may see only, see calendar_issue_event_ids_visible().
 * If the bug doesn't have attachments, cache its value as 0.
 * @global array $g_cache_calendar_event_count
 * @param array $p_bug_ids Array of bug ids
 * @return void
 */
function calendar_event_issue_attachment_count_cache( array $p_bug_ids ) {
	global $g_cache_calendar_event_count;

	if( empty( $p_bug_ids ) ) {
		return;
	}

	$t_ids_to_search = array();
	foreach( $p_bug_ids as $t_id ) {
		$c_id = (int)$t_id;
		$t_ids_to_search[$c_id] = $c_id;
	}

	db_param_push();
	$t_params = array();
	$t_in_values = array();
	foreach( $t_ids_to_search as $t_id ) {
		$t_params[] = (int)$t_id;
		$t_in_values[] = db_param();
	}

	$t_query = 'SELECT E.bug_id AS bug_id, E.event_id AS event_id'
			. ' FROM ' . plugin_table( 'relationship' ) . ' E'
			. ' WHERE E.bug_id IN (' . implode( ',', $t_in_values ) . ')';

	$t_result = db_query( $t_query, $t_params );
	$t_bug_events = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_bug_events[(int)$t_row['bug_id']][] = (int)$t_row['event_id'];
	}

	# only the events the user may see are counted, the way the calendar block
	# of the issue page counts them; every event is checked once for the list
	$t_all_event_ids = array();
	foreach( $t_bug_events as $t_event_ids ) {
		$t_all_event_ids = array_merge( $t_all_event_ids, $t_event_ids );
	}
	$t_all_event_ids = array_values( array_unique( $t_all_event_ids ) );
	event_cache_array_rows( $t_all_event_ids );
	$t_visible = array_flip( calendar_issue_event_ids_visible( $t_all_event_ids ) );

	foreach( $t_bug_events as $c_bug_id => $t_event_ids ) {
		$t_count = 0;
		foreach( $t_event_ids as $t_event_id ) {
			if( isset( $t_visible[$t_event_id] ) ) {
				$t_count++;
			}
		}
		$g_cache_calendar_event_count[$c_bug_id] = $t_count;
		unset( $t_ids_to_search[$c_bug_id] );
	}

	# set bugs without result to 0
	foreach( $t_ids_to_search as $t_id ) {
		$g_cache_calendar_event_count[$t_id] = 0;
	}
}