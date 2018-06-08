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
    protected $project_id      = null;
    protected $author_id       = 0;
    protected $changed_user_id = 0;
    protected $status          = false;
    protected $name            = '';
    protected $activity        = 'Y';
    protected $date_changed    = '';
    protected $date_from       = '';
    protected $date_to         = '';
    private $loading           = false;

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
                $value = (int) $value;
                break;

            case 'name':
                $p_value = trim( $value );
                break;

            case 'date_changed':
            case 'date_from':
            case 'date_to':
                if( !is_numeric( $value ) ) {
                    $value = strtotime( $value, 0 );
                }
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
            trigger_error( ERROR_DATE, ERROR );
        }


//		if( 0 == $this->category_id && !config_get( 'allow_no_category' ) ) {
//			error_parameters( lang_get( 'category' ) );
//			trigger_error( ERROR_EMPTY_FIELD, ERROR );
//		}
//
//		if( !is_blank( $this->duplicate_id ) && ( $this->duplicate_id != 0 ) && ( $this->id == $this->duplicate_id ) ) {
//			trigger_error( ERROR_BUG_DUPLICATE_SELF, ERROR );
//			# never returns
//		}
    }

    function __construct( $id ) {
        $this->id = $id;
    }

    function create() {

        $this->validate();

        $t_event_table = plugin_table( 'events' );

        # Insert the rest of the data
        $query = "INSERT INTO $t_event_table
                                                ( project_id, name,
                                                  activity, author_id, date_changed,
                                                  changed_user_id, date_from, date_to
                                                )
                                              VALUES
                                                ( " . db_param() . ',' . db_param() . ",
                                                  " . db_param() . ',' . db_param() . ',' . db_param() . ",
                                                  " . db_param() . ',' . db_param() . ',' . db_param() . ')';

        db_query( $query, Array( $this->project_id, $this->name,
                                  $this->activity, $this->author_id, $this->date_changed,
                                  $this->changed_user_id, $this->date_from, $this->date_to ) );

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
                                                date_from=" . db_param() . ", date_to=" . db_param();

        $t_fields = Array(
                                  $this->name,
                                  $this->activity, $this->changed_user_id,
                                  $this->date_from, $this->date_to,
        );

        $query .= " WHERE id=" . db_param();

        $t_fields[] = $this->id;

        db_query( $query, $t_fields );

        event_clear_cache( $this->id );

        # Update the last update date
        event_update_date( $t_event_id );

        return true;
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
        plugin_error( "Event #$p_event_id not found" );
    }
}

/**
 * Check if a event exists
 * @param integer $p_event_id Integer representing bug identifier.
 * @return boolean true if bug exists, false otherwise
 * @access public
 */
function event_exists( $p_event_id ) {
    $c_event_id = (int) $p_event_id;

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

    $c_event_id    = (int) $p_event_id;
    $t_event_table = plugin_table( 'events' );

    $query  = "SELECT *
				  FROM $t_event_table
				  WHERE id=" . db_param();
    $result = db_query( $query, Array( $c_event_id ) );

    if( 0 == db_num_rows( $result ) ) {
        $g_cache_calendar_event[$c_event_id] = false;

        if( $p_trigger_errors ) {
            error_parameters( $p_event_id );
            trigger_error( ERROR_EVENT_NOT_FOUND, ERROR );
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

    $g_cache_calendar_event[(int) $p_event_row['id']] = $p_event_row;

    if( !is_null( $p_stats ) ) {
        $g_cache_calendar_event[(int) $p_event_row['id']]['_stats'] = $p_stats;
    }

    return $g_cache_calendar_event[(int) $p_event_row['id']];
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
        unset( $g_cache_calendar_event[(int) $p_event_id] );
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
    $c_event_id = (int) $p_event_id;

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
function event_member( $p_event_id, $p_user_id ) {
	$c_event_id = (int)$p_event_id;
	$c_user_id = (int)$p_user_id;

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
	$t_query = "INSERT INTO $t_event_member_table ( user_id, event_id ) VALUES (" . db_param() . "," . db_param() . ")";
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
function event_unmember( $p_event_id, $p_user_id ) {

    $t_event_member_table = plugin_table( 'event_member' );
    # Delete monitoring record
    db_param_push();
    $t_query               = "DELETE FROM $t_event_member_table WHERE event_id = " . db_param();
    $t_db_query_params[]   = $p_event_id;

    if( $p_user_id !== null ) {
        $t_query             .= ' AND user_id = ' . db_param();
        $t_db_query_params[] = $p_user_id;
    }

    db_query( $t_query, $t_db_query_params );

    # log new un-monitor action
//	history_log_event_special( $p_bug_id, BUG_UNMONITOR, (int)$p_user_id );
    # updated the last_updated date
    event_update_date( $p_event_id );

    return true;
}

/**
 * Returns the list of users members the specified event
 *
 * @param integer $p_bevent_id Integer representing event identifier.
 * @return array
 */
function event_get_members( $p_event_id ) {
	if( ! access_has_event_level( config_get( 'show_member_list_threshold' ), $p_event_id ) ) {
		return array();
	}

	# get the eventnote data
        $t_event_member_table = plugin_table( 'event_member' );
	db_param_push();
	$t_query = "SELECT user_id, enabled
			FROM $t_event_member_table m, {user} u
			WHERE m.event_id=" . db_param() . " AND m.user_id = u.id
			ORDER BY u.realname, u.username";
	$t_result = db_query( $t_query, array( $p_event_id ) );

	$t_users = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_users[] = $t_row['user_id'];
	}

	user_cache_array_rows( $t_users );

	return $t_users;
}