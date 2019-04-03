<?php

# Copyright (c) 2018 Grigoriy Ermolaev (igflocal@gmail.com)
# Calendar for MantisBT is free software: 
# you can redistribute it and/or modify it under the terms of the GNU
# General Public License as published by the Free Software Foundation, 
# either version 2 of the License, or (at your option) any later version.
#
# Calendar plugin for for MantisBT is distributed in the hope 
# that it will be useful, but WITHOUT ANY WARRANTY; without even the 
# implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
# See the GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Customer management plugin for MantisBT.  
# If not, see <http://www.gnu.org/licenses/>.

class CalendarEventData {

    protected $id;
    protected $parent_id          = 0;
    protected $project_id         = null;
    protected $author_id          = 0;
    protected $changed_user_id    = 0;
    protected $status             = false;
    protected $name               = '';
    protected $activity           = 'Y';
    protected $date_changed       = NULL;
    protected $date_from          = 1;
    protected $date_to            = 1;
    protected $duration           = 0;
    protected $recurrence_pattern = '';
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
                $value = trim( $value );
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
                                                  activity, author_id, date_changed,
                                                  changed_user_id, date_from, date_to,
                                                  duration, recurrence_pattern, parent_id
                                                )
                                              VALUES
                                                ( " . db_param() . ',' . db_param() . ",
                                                  " . db_param() . ',' . db_param() . ',' . db_param() . ",
                                                  " . db_param() . ',' . db_param() . ',' . db_param() . ",
                                                  " . db_param() . ',' . db_param() . ',' . db_param() . ')';

        db_query( $query, Array( $this->project_id, $this->name,
                                  $this->activity, $this->author_id, $this->date_changed,
                                  $this->changed_user_id, $this->date_from, $this->date_to,
                                  $this->duration, $this->recurrence_pattern, $this->parent_id ) );

        $this->id = db_insert_id( $t_event_table );
//
//                    # log new bug
//                    history_log_event_special( $this->id, NEW_BUG );
//
//                    # log changes, if any (compare happens in history_log_event_direct)
//                    history_log_event_direct( $this->id, 'status', $t_original_status, $t_status );
//                    history_log_event_direct( $this->id, 'handler_id', 0, $this->handler_id );

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

//		$t_old_data = event_get( $this->id );

        $t_calendar_event_table = plugin_table( 'events' );

        # Update all fields
        # Ignore date_submitted and last_updated since they are pulled out
        #  as unix timestamps which could confuse the history log and they
        #  shouldn't get updated like this anyway.  If you really need to change
        #  them use bug_set_field()
        $query = "UPDATE $t_calendar_event_table
                                            SET name=" . db_param() . ",
						activity=" . db_param() . ", changed_user_id=" . db_param() . ",
                                                date_from=" . db_param() . ", date_to=" . db_param() . ", duration=" . db_param() . ",
                                                recurrence_pattern=" . db_param() . ", parent_id=" . db_param();

        $t_fields = Array(
                                  $this->name,
                                  $this->activity, $this->changed_user_id,
                                  $this->date_from, $this->date_to, $this->duration,
                                  $this->recurrence_pattern, $this->parent_id,
        );

        $query .= " WHERE id=" . db_param();

        $t_fields[] = $this->id;

        db_query( $query, $t_fields );

        event_clear_cache( $this->id );

        # Update the last update date
        event_update_date( $t_event_id );

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

        $t_calendar_event_table = plugin_table( 'events' );

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

function event_occurrence_exists( $p_event_id, $p_date ) {

    $t_event = event_get( $p_event_id );

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
 * @param integer $p_event_id  Integer representing event identifier.
 * @param integer $p_user_id Integer representing user identifier.
 * @return boolean true if successful, false if unsuccessful
 * @access public
 */
function event_member_add( $p_event_id, $p_user_id ) {
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
//	history_log_event_special( $c_event_id, BUG_MONITOR, $c_user_id );
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

    # log new un-monitor action
//	history_log_event_special( $p_bug_id, BUG_UNMONITOR, (int)$p_user_id );
    # updated the last_updated date
//    event_update_date( $p_event_id );

    return true;
}

/**
 * Returns the list of users members the specified event
 *
 * @param integer $p_bevent_id Integer representing event identifier.
 * @return array
 */
function event_get_members( $p_event_id ) {
    if( !access_has_event_level( plugin_config_get( 'show_member_list_threshold' ), $p_event_id ) ) {
        return array();
    }

    # get the eventnote data
    $t_event_member_table = plugin_table( 'event_member' );
    db_param_push();
    $t_query              = "SELECT user_id
			FROM $t_event_member_table 
			WHERE event_id=" . db_param();
    $t_result             = db_query( $t_query, array( $p_event_id ) );

    $t_users = array();
    while( $t_row   = db_fetch_array( $t_result ) ) {
        $t_users[] = $t_row['user_id'];
    }

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
                $t_bug_id, plugin_lang_get( "event" ), "", plugin_lang_get( "event_hystory_bug_detach" ) . ": " . event_get_field( $p_event_id, 'name' )
        );
        bug_update_date( $t_bug_id );
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

        plugin_history_log(
                $t_bug_id, plugin_lang_get( "event" ), "", plugin_lang_get( "event_hystory_create" ) . ": " . event_get_field( $p_event_id, 'name' )
        );
        bug_update_date( $t_bug_id );
    }
    return TRUE;
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
 * Fills the cache with the attachment count from a list of bugs
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

	$t_query = 'SELECT E.bug_id AS bug_id, COUNT(E.event_id) AS attachments'
			. ' FROM ' . plugin_table( 'relationship' ) . ' E'
			. ' WHERE E.bug_id IN (' . implode( ',', $t_in_values ) . ')'
			. ' GROUP BY E.bug_id';

	$t_result = db_query( $t_query, $t_params );
	while( $t_row = db_fetch_array( $t_result ) ) {
		$c_bug_id = (int)$t_row['bug_id'];
		$g_cache_calendar_event_count[$c_bug_id] = (int)$t_row['attachments'];
		unset( $t_ids_to_search[$c_bug_id] );
	}

	# set bugs without result to 0
	foreach( $t_ids_to_search as $t_id ) {
		$g_cache_calendar_event_count[$t_id] = 0;
	}
}