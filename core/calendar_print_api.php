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

function print_time_select_option( $p_selected_time = NULL ) {

    $t_time_day_start  = plugin_config_get( 'timeDayStart' );
    $t_time_day_finish = plugin_config_get( 'timeDayFinish' );

    $t_time_day_start_timestamp  = ($t_time_day_start * 60) * 60;
    $t_time_day_finish_timestamp = ($t_time_day_finish * 60) * 60;

    $stepDaySeconds        = (60 / plugin_config_get( 'stepDayMinutesCount' )) * 60;
    $t_select_time_options = array();

    for( $i = $t_time_day_start_timestamp; $i <= $t_time_day_finish_timestamp; $i = $i + $stepDaySeconds ) {
        $t_select_time_options[] = $i;
    }

    echo '<option value="--:--">--:--</option>';
    foreach( $t_select_time_options as $key => $t_current_time ) {
        if( $p_selected_time != NULL ) {
            $fdf                         = date( "H", $p_selected_time );
            $fdf1                        = date( "i", $p_selected_time );
            $t_time_format_selected_time = gmmktime( $fdf, $fdf1, 0, 1, 1, 1970 );
        } else {
            $t_time_format_selected_time = NULL;
        }

        if( $t_time_format_selected_time !== $t_current_time ) {
            echo '<option value="' . $t_current_time . '">' . gmdate( "H:i", $t_current_time ) . '</option>';
        } else {
            echo '<option selected value="' . $t_current_time . '">' . gmdate( "H:i", $t_current_time ) . '</option>';
        }
    }
}
