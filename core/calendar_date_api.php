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

function days_of_number_week( $p_start_step_days, $p_count_step_days, $t_week_days_name, $p_week = null ) {

    if( $p_week == null ) {
        $t_week = date( "e" );
    }

    $t_week         = $p_week - 1;
    $t_curent_years = date( "Y" );
    $t_days_week    = array();

    for( $i = $p_start_step_days; $i < $p_start_step_days + $p_count_step_days; $i++ ) {

        $t_day_cheks = date( "D", strtotime( 'last monday + ' . $i . ' days 1/1/' . $t_curent_years . ' + ' . $t_week . ' weeks' ) );

        if( $t_week_days_name[$t_day_cheks] == ON ) {
            $t_days_week[$i] = date( strtotime( 'last monday + ' . $i . ' days 1/1/' . $t_curent_years . ' + ' . $t_week . ' weeks' ) );
        }
    }
    return $t_days_week;
}

function times_day( $p_date, $p_full_time = FALSE ) {

    if( $p_full_time == TRUE ) {
        $t_time_day_start  = 0;
        $t_time_day_finish = 86400;
    } else {
        $t_time_day_start  = plugin_config_get( 'time_day_start' );
        $t_time_day_finish = plugin_config_get( 'time_day_finish' );
    }

    $t_time_start  = $p_date + $t_time_day_start;
    $t_time_finish = $p_date + $t_time_day_finish;
    $t_time_count  = 3600 / plugin_config_get( 'stepDayMinutesCount' );
    $t_dates       = range( $t_time_start, $t_time_finish, $t_time_count );

    return $t_dates;
}

function get_dates_event_from_events_id( $p_events_id ) {

    $t_dates = array();

    foreach( $p_events_id as $t_event_id ) {

        if( event_get_field( $t_event_id, "activity" ) == 'Y' ) {
            $t_time_start_day             = strtotime( date( "j.n.Y", event_get_field( $t_event_id, "date_from" ) ) );
            $t_dates[$t_time_start_day][] = $t_event_id;
        }
    }
    ksort( $t_dates );
    return $t_dates;
}

function get_bugs_id_from_event( $event_id ) {
    $p_table_calendar_relationship = plugin_table( "relationship" );

    if( db_table_exists( $p_table_calendar_relationship ) && db_is_connected() ) {
        $query  = "SELECT bug_id
				  FROM $p_table_calendar_relationship
				  WHERE event_id=" . db_param();
        $result = db_query( $query, (array) $event_id );


        $cResult     = array();
        $t_row_count = db_num_rows( $result );
        $pResult     = Array();
        for( $i = 0; $i < $t_row_count; $i++ ) {
            array_push( $cResult, db_fetch_array( $result ) );
            $pResult[$i] = $cResult[$i]["bug_id"];
        }

        return $pResult;
    }
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

function group_events_by_time( $p_events_id ) {
    if( $p_events_id == false || $p_events_id == 0 )
        return 0;

    $arEventsTemp = array();

    foreach( $p_events_id as $t_event_id ) {

        $arEventsTemp [event_get_field( $t_event_id, "date_from" )][] = $t_event_id;
    }

    krsort( $arEventsTemp );

    return $arEventsTemp;
}

function get_events_id_inside_days( $p_ar_all_days, $p_project_id, $p_full_time = FALSE, $p_user_id = ALL_USERS ) {

    $t_table_calendar_events = plugin_table( 'events' );

    $t_project_all = project_hierarchy_get_all_subprojects( $p_project_id );

    if( db_table_exists( $t_table_calendar_events ) && db_is_connected() ) {

        $arDays = array();
        db_param_push();

        $p_query = "SELECT id FROM " . $t_table_calendar_events . " WHERE activity = 'Y' AND date_from BETWEEN " . db_param() . " AND " . db_param();

        foreach( $p_ar_all_days as $t_day ) {

            if( $p_full_time == TRUE ) {
                $t_time_start_day  = $t_day;
                $t_time_finish_day = $t_day + ( (24 * 60) * 59);
            } else {
                $t_time_start_day  = $t_day + plugin_config_get( 'time_day_start' );
                $t_time_finish_day = $t_day + plugin_config_get( 'time_day_finish' );
            }

            $t_result      = db_query( $p_query, array( $t_time_start_day, $t_time_finish_day ) );
            $t_event_count = db_num_rows( $t_result );
            if( $t_event_count > 0 ) {
                for( $i = 0; $i < $t_event_count; $i++ ) {
                    $t_row = db_fetch_array( $t_result );

                    $t_user_is_member = $p_user_id == ALL_USERS ? TRUE : in_array( $p_user_id, event_get_members( $t_row["id"] ) );

                    if( in_array( event_get_field( $t_row["id"], 'project_id' ), $t_project_all ) && $t_user_is_member == TRUE || event_get_field( $t_row["id"], 'project_id' ) == $p_project_id && $t_user_is_member == TRUE ) {

                        $t_time_event_start                    = event_get_field( $t_row["id"], 'date_from' );
                        $arDays[$t_day][$t_time_event_start][] = $t_row["id"];
                    } else {
                        $arDays[$t_day] = [];
                    }
                }
            } else {
                $arDays[$t_day] = [];
            }
        }
        foreach( $arDays as $arDay => $keys ) {
            if( count( $keys ) == 0 ) {
                $t_events_in_days[$arDay] = [];
            }
            ksort( $keys );
            foreach( $keys as $key ) {
                foreach( $key as $k ) {
                    $t_events_in_days[$arDay][] = $k;
                }
            }
        }
        return $t_events_in_days;
    }
    return false;
}
