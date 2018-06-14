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

function print_time_select_option( $p_selected_time = NULL, $p_full_range = FALSE ) {

    if( $p_full_range == FALSE ) {
        $t_time_day_start_timestamp  = plugin_config_get( 'time_day_start', plugin_config_get( 'time_day_start' ), FALSE, auth_get_current_user_id() );
        $t_time_day_finish_timestamp = plugin_config_get( 'time_day_finish', plugin_config_get( 'time_day_finish' ), FALSE, auth_get_current_user_id() );
    } else {
        $t_time_day_start_timestamp  = 0;
        $t_time_day_finish_timestamp = 86400;
    }

    if( $p_selected_time < $t_time_day_start_timestamp && $p_selected_time !== NULL || $p_selected_time > $t_time_day_finish_timestamp && $p_selected_time !== NULL ) {
        $t_time_day_start_timestamp  = 0;
        $t_time_day_finish_timestamp = 86400;
    }

    $t_time_count          = 3600 / plugin_config_get( 'stepDayMinutesCount' );
    $t_select_time_options = range( $t_time_day_start_timestamp, $t_time_day_finish_timestamp, $t_time_count );

    echo '<option value="--:--">--:--</option>';
    foreach( $t_select_time_options as $key => $t_current_time ) {

        if( $p_selected_time !== $t_current_time ) {
            echo '<option value="' . $t_current_time . '">' . gmdate( "H:i", $t_current_time ) . '</option>';
        } else {
            echo '<option selected value="' . $t_current_time . '">' . gmdate( "H:i", $t_current_time ) . '</option>';
        }
    }
}
