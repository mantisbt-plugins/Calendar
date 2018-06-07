<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

function user_is_monitoring_event( $p_user_id, $p_event_id ) {
    
    $t_event_monitor_table = plugin_table( 'event_monitor' );
    
    db_param_push();
    
    $t_query = "SELECT COUNT(*) FROM $t_event_monitor_table
				  WHERE user_id=" . db_param() . " AND event_id=" . db_param();

    $t_result = db_query( $t_query, array( (int) $p_user_id, (int) $p_event_id ) );

    if( 0 == db_result( $t_result ) ) {
        return false;
    } else {
        return true;
    }
}
