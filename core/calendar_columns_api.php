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

function print_column_time( $p_full_time = FALSE ) {

    echo '<td class="column-time-td">';
    echo '<ul class="column-header-day">' . plugin_lang_get( 'time_event' ) . '</ul>';

    $t_step_interval = plugin_config_get( 'stepDayMinutesCount' );

    $t_time_count = 3600 / $t_step_interval;

    if( $p_full_time == TRUE ) {
        $t_times_day = range( 0, 86400, $t_time_count );
    } else {
        $t_time_day_start  = plugin_config_get( 'time_day_start', plugin_config_get( 'time_day_start' ), FALSE, auth_get_current_user_id() );
        $t_time_day_finish = plugin_config_get( 'time_day_finish', plugin_config_get( 'time_day_finish' ), FALSE, auth_get_current_user_id() );
        $t_times_day       = range( $t_time_day_start, $t_time_day_finish, $t_time_count );
    }

    $t_count_times_day = count( $t_times_day ) - 1;

    foreach( $t_times_day as $key => $t_time ) {

        if( !($key % $t_step_interval) && $t_count_times_day != $key ) {
            echo "<ul class=\"hour\" id=\"hour_" . $t_time . "\">";
        }
        if( $t_count_times_day == $key ) {
            echo "<ul class=\"hour last-row\" id=\"hour_" . $t_time . "\">";
        }

        echo "<li>";
        echo gmdate( "H:i", $t_time );
        echo "</li>";

        if( $key % $t_step_interval && $t_count_times_day != $key ) {
            echo "</ul>";
        }
        if( $t_count_times_day == $key ) {
            echo "</ul>";
        }
    }

    echo "</td>";
}

function print_column_this_day( $p_day_events, $p_total_number_of_days = 0, $p_full_time = FALSE ) {

    $p_day       = key( $p_day_events );
    $p_times     = key( $p_day_events[$p_day] );
    
    if( array_key_exists( $p_day, $p_day_events ) && array_key_exists( $p_times, $p_day_events[$p_day] )) {
    $t_events_id = $p_day_events[$p_day][$p_times];
    } else {
        $t_events_id = NULL;
    }

    $t_work_times_current_day           = times_day( $p_day, $p_full_time );
    $t_this_day                         = date( "U", strtotime( date( "j.n.Y" ) ) );
    $stepDayMinutesCount                = plugin_config_get( 'stepDayMinutesCount' ); //2
    $STEP_INTERVAL_TIME_HEIGHT          = $stepDayMinutesCount / 1.46;
    $t_step_day_minutes_for_clock_count = plugin_config_get( 'stepDayMinutesCount' ); //2
    $t_events_group_by_time             = $p_day_events[$p_day];
    $t_indent_divisor_counter           = 0;
    $t_events_intersection              = false;
    $t_events_collision_ar              = array();
    $t_events_collision_ar1             = array();
    $t_out_of_range_event               = array();

    foreach( $t_events_group_by_time as $t_time => $t_events ) {
        if( !in_array( $t_time, $t_work_times_current_day ) ) {
            $t_out_of_range_event = array_merge( $t_out_of_range_event, $t_events );
        }
    }

    if( $t_this_day == $p_day ) {
        echo '<td class="column-this-day-td" style="width: calc(100%/' . $p_total_number_of_days . ')">';
    } else {
        echo '<td class="column-day-td" style="width: calc(100%/' . $p_total_number_of_days . ')">';
    }

    echo '<ul class="column-header-day"><span>' . plugin_lang_get( date( "D", $p_day ) ) . ', ' . date( config_get( 'short_date_format' ), $p_day ) . '</span></ul>';

    $t_count_times_day = count( $t_work_times_current_day ) - 1;

    foreach( $t_work_times_current_day as $key => $t_current_time ) {

        if( !($key % $stepDayMinutesCount) && $t_count_times_day != $key ) {
            echo "<ul class=\"hour\" id=\"area_hour_" . $t_current_time . "\">";
        }

        if( $t_count_times_day != $key ) {

            echo "<li id=\"area_min_" . $t_current_time . "\">";

            if( array_key_exists( $t_current_time, $t_events_group_by_time ) ) {
                $t_events_current_time_period = $t_events_group_by_time[$t_current_time];

                for( $q = 0, $qw = count( $t_events_current_time_period ); $q < $qw; $q++ ) {

                    $t_current_event_id = $t_events_current_time_period[$q];

                    $t_events_intersection_counter = 0;
                    $t_width_divisor_counter       = 0;

                    foreach( $t_events_id as $t_event_id ) {

                        if( $t_event_id == $t_current_event_id )
                            continue;

                        $t_time_start_current_event  = event_get_field( $t_event_id, "date_from" );
                        $t_time_finish_current_event = event_get_field( $t_event_id, "date_from" ) + event_get_field( $t_event_id, "duration" );

                        $t_time_start_event  = event_get_field( $t_current_event_id, "date_from" );
                        $t_time_finish_event = event_get_field( $t_current_event_id, "date_from" ) + event_get_field( $t_current_event_id, "duration" );
                        
//                        $t_time_start_current_event  = date( "H:i", event_get_field( $t_event_id, "date_from" ) );
//                        $t_time_finish_current_event = date( "H:i", event_get_field( $t_event_id, "date_from" ) + event_get_field( $t_event_id, "duration" ) );
//
//                        $t_time_start_event  = date( "H:i", event_get_field( $t_current_event_id, "date_from" ) );
//                        $t_time_finish_event = date( "H:i", event_get_field( $t_current_event_id, "date_from" ) + event_get_field( $t_current_event_id, "duration" ) );

                        if( $t_time_start_event < $t_time_finish_current_event && $t_time_start_event >= $t_time_start_current_event ||
                                $t_time_finish_event > $t_time_start_current_event && $t_time_finish_event <= $t_time_finish_current_event ||
                                $t_time_start_event <= $t_time_start_current_event && $t_time_finish_event >= $t_time_finish_current_event ) {

                            $t_width_divisor_counter++;
                            $t_events_intersection_counter++;
                            $t_events_intersection                        = true;
                            $t_events_collision_ar[$t_current_event_id][] = $t_event_id;
                            $t_events_collision_ar1[$t_event_id][]        = $t_current_event_id;
                        }
                    }
                    if( $t_indent_divisor_counter > $t_width_divisor_counter ) {
                        $t_indent_divisor_counter = 0;
                    }

                    $countInterval = ( event_get_field( $t_current_event_id, "duration" ) / 60) / $t_step_day_minutes_for_clock_count;

                    if( strtotime( date( "j.n.Y" ) ) <= $p_day ) {
                        if( $t_indent_divisor_counter == 0 && $t_width_divisor_counter >= 1 || $t_width_divisor_counter == 0 ) {
                            $t_width_divisor_counter++;
                            echo "<a href=" . plugin_page( 'view' ) .
                            "&event_id=" . $t_current_event_id .
                            "&date=" . $t_current_time .
                            " id=\"event_week\" value=" . $t_current_event_id .
                            " class=\"event_week\" style=\"z-index:" . $t_current_event_id . "; "
                            . "height:" . $STEP_INTERVAL_TIME_HEIGHT * $countInterval . "px; "
                            . "width: calc(100%/" . $t_width_divisor_counter . ");\">";
                        } else {
//                            $t_width_divisor_counter1 = $t_width_divisor_counter;
//                            $t_width_divisor_counter++;
                            $t_width_divisor_counter  = count( $t_events_collision_ar[$t_current_event_id] );
                            $t_width_divisor_counter1 = $t_width_divisor_counter;
                            $fopwf                    = $t_width_divisor_counter + 1;
                            echo "<a href=" . plugin_page( 'view' )
                            . "&event_id=" . $t_current_event_id .
                            "&date=" . $t_current_time .
                            " id=\"event_week\" value=" . $t_current_event_id .
                            " class=\"event_week\" "
                            . "style=\"z-index:" . $t_current_event_id . "; "
                            . "height:" . $STEP_INTERVAL_TIME_HEIGHT * $countInterval . "px; "
//                            . "width: calc(100%/" . $t_width_divisor_counter . "); "
//                            . "margin-left: calc(100%/(" . $t_width_divisor_counter . "/" . $t_width_divisor_counter1 . "));\">";
                            . "margin-left: calc(100%/(" . $fopwf . "/" . $t_indent_divisor_counter . "));\">";
                        }
                    } else {    // иначе как событие в прошлом
                        if( $t_indent_divisor_counter == 0 && $t_width_divisor_counter >= 1 || $t_width_divisor_counter == 0 ) {
                            $t_width_divisor_counter++;
                            echo "<a href=" . plugin_page( 'view' ) .
                            "&event_id=" . $t_current_event_id .
                            "&date=" . $t_current_time .
                            " id=\"event_week\" value=" . $t_current_event_id .
                            " class=\"event_week_expired\" "
                            . "style=\"z-index:" . $t_current_event_id . "; "
                            . "height:" . $STEP_INTERVAL_TIME_HEIGHT * $countInterval . "px; "
                            . "width: calc(100%/" . $t_width_divisor_counter++ . ");\">";
                        } else {
//                            $t_width_divisor_counter1 = $t_width_divisor_counter;
//                            $t_width_divisor_counter++;
                            $t_width_divisor_counter  = count( $t_events_collision_ar[$t_current_event_id] );
                            $t_width_divisor_counter1 = $t_width_divisor_counter;
                            $fopwf                    = $t_width_divisor_counter + 1;

                            echo "<a href=" . plugin_page( 'view' ) .
                            "&event_id=" . $t_current_event_id .
                            "&date=" . $t_current_time .
                            " id=\"event_week\" value=" . $t_current_event_id .
                            " class=\"event_week_expired\" "
                            . "style=\"z-index:" . $t_current_event_id . "; "
                            . "height:" . $STEP_INTERVAL_TIME_HEIGHT * $countInterval . "px; "
//                            . "width: calc(100%/" . $fopwf . "); "
//                            . "margin-left: calc(100%/(" . $t_width_divisor_counter . "/" . $t_width_divisor_counter1 . "));\">";
//                            . "margin-left: calc(100%/(2/" . $t_indent_divisor_counter . "));\">";
                            . "margin-left: calc(100%/(" . $fopwf . "/" . $t_indent_divisor_counter . "));\">";
                        }
                    }

                    if( $t_events_intersection == true ) {
                        $t_indent_divisor_counter++;
                    }

                    if( event_get_field( $t_current_event_id, "project_id" ) == helper_get_current_project() ) {
                        echo event_get_field( $t_current_event_id, "name" );
                    } else {
                        echo project_get_field( event_get_field( $t_current_event_id, "project_id" ), "name" ) . ": " . event_get_field( $t_current_event_id, "name" );
                    }
                    echo "</br>";

                    $t_time_start  = date( "H:i", event_get_field( $t_current_event_id, "date_from" ) );
                    $t_time_finish = date( "H:i", event_get_field( $t_current_event_id, "date_from" ) + event_get_field( $t_current_event_id, "duration" ) );
                    echo $t_time_start . " - " . $t_time_finish;


                    echo "</a>";
                }
            }
            echo "</li>";
        }

        if( $key == $t_count_times_day ) {
            echo "</ul>";
            echo "<ul class=\"hour last-row\" id=\"area_hour\">";
            if( count( $t_out_of_range_event ) != 0 ) {
                echo "<li>" . "+" . count( $t_out_of_range_event ) . " " . plugin_lang_get( 'out_of_range' ) . "</li>";
            } else {
                echo "<li></li>";
            }
        } else if( $key % $stepDayMinutesCount || $key == $t_count_times_day ) {
            echo "</ul>";
        }
    }
    echo "</ul>";
    echo "</td>";
}
