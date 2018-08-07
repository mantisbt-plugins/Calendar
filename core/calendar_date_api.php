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
        $t_time_day_start  = plugin_config_get( 'time_day_start', plugin_config_get( 'time_day_start' ), FALSE, auth_get_current_user_id() );
        $t_time_day_finish = plugin_config_get( 'time_day_finish', plugin_config_get( 'time_day_finish' ), FALSE, auth_get_current_user_id() );
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

        if( event_get_field( $t_event_id, 'recurrence_pattern' ) != NULL ) {
            $t_rset          = new RRule\RSet( event_get_field( $t_event_id, 'recurrence_pattern' ) );
            $t_previous_days = $t_rset->getOccurrencesBetween( (int) event_get_field( $t_event_id, 'date_from' ), strtotime( 'tomorrow' ) );
            $t_next_days     = $t_rset->getOccurrencesBetween( strtotime( 'tomorrow' ), (int) event_get_field( $t_event_id, 'date_to' ), 1 );
            foreach( $t_previous_days as $t_previous_day ) {
                $t_time_start_day                                              = strtotime( date( "j.n.Y", $t_previous_day->getTimestamp() ) );
                $t_dates[$t_time_start_day][$t_previous_day->getTimestamp()][] = $t_event_id;
            }
            foreach( $t_next_days as $t_next_day ) {
                $t_time_start_day                                          = strtotime( date( "j.n.Y", $t_next_day->getTimestamp() ) );
                $t_dates[$t_time_start_day][$t_next_day->getTimestamp()][] = $t_event_id;
            }
        } else {
            $t_time_start_day                                                          = strtotime( date( "j.n.Y", event_get_field( $t_event_id, "date_from" ) ) );
            $t_dates[$t_time_start_day][event_get_field( $t_event_id, "date_from" )][] = $t_event_id;
        }
    }
    ksort( $t_dates );
    return $t_dates;
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

function get_events_id_inside_days( $p_ar_all_days, $p_project_id, $p_user_id = ALL_USERS ) {

    $t_table_calendar_events = plugin_table( 'events' );

    $t_project_all = project_hierarchy_get_all_subprojects( $p_project_id );

    if( db_table_exists( $t_table_calendar_events ) && db_is_connected() ) {

        $arDays = array();
        db_param_push();

        $p_query = "SELECT id FROM " . $t_table_calendar_events .
                " WHERE activity = 'Y' "
                . "AND date_from BETWEEN " . db_param() . " AND " . db_param() . " "
                . "OR ( activity = 'Y' AND date_from < " . db_param() . " AND date_to > " . db_param() . " AND recurrence_pattern IS NOT NULL )";

        foreach( $p_ar_all_days as $t_day ) {

            $t_time_start_day  = (int) $t_day;
            $t_time_finish_day = $t_day + 86399;

            $t_result      = db_query( $p_query, array( $t_time_start_day, $t_time_finish_day, $t_time_finish_day, $t_time_start_day ) );
            $t_event_count = db_num_rows( $t_result );
            if( $t_event_count > 0 ) {
                $arDays[$t_day] = [];
                for( $i = 0; $i < $t_event_count; $i++ ) {
                    $t_row = db_fetch_array( $t_result );

                    $t_user_is_member = $p_user_id == ALL_USERS ? TRUE : in_array( $p_user_id, event_get_members( $t_row["id"] ) );

                    if( in_array( event_get_field( $t_row["id"], 'project_id' ), $t_project_all ) && $t_user_is_member == TRUE || event_get_field( $t_row["id"], 'project_id' ) == $p_project_id && $t_user_is_member == TRUE ) {

                        $t_time_event_start = event_get_field( $t_row["id"], 'date_from' );
                        $t_rrule_raw        = event_get_field( $t_row['id'], 'recurrence_pattern' );
                        if( $t_time_event_start < $t_day || $t_rrule_raw != NULL ) {
//                            $t_rrule_raw       = event_get_field( $t_row['id'], 'recurrence_pattern' );
                            $t_recurrenci_rule = RRule\RRule::createFromRfcString( $t_rrule_raw );
                            $t_is              = $t_recurrenci_rule->getOccurrencesBetween( $t_time_start_day, $t_time_finish_day );
                            if( $t_is != NULL ) {
                                $arDays[$t_day][date_timestamp_get( $t_is[0] )][] = $t_row["id"];
                            }
                        } else {
                            $arDays[$t_day][$t_time_event_start][] = $t_row["id"];
                        }
                    }
                }
            } else {
                $arDays[$t_day] = [];
            }
        }
//        foreach( $arDays as $arDay => $keys ) {
//            if( count( $keys ) == 0 ) {
//                $t_events_in_days[$arDay] = [];
//            } else {
//                ksort( $keys );
//                $t_events_in_days[$arDay] = $keys;
//            }
//        }
//        return $t_events_in_days;
//        foreach( $arDays as $arDay => $keys ) {
//            if( count( $keys ) == 0 ) {
//                $t_events_in_days[$arDay] = [];
//            }
//            ksort( $keys );
//            foreach( $keys as $key ) {
//                foreach( $key as $k ) {
//                    $t_events_in_days[$arDay][] = $k;
//                }
//            }
//        }
//        return $t_events_in_days;
//        
//        foreach( $arDays as $arDay => $keys ) {
//            if( count( $keys ) == 0 ) {
//                $t_events_in_days[$arDay] = [];
//            }
//            ksort( $keys );
//            foreach( $keys as $key ) {
//                foreach( $key as $k ) {
//                    $t_events_in_days[$arDay][] = $k;
//                }
//            }
//        }
//        return $t_events_in_days;
        return $arDays;
    }
    return false;
}
