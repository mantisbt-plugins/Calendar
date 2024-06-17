<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ColumnTime
 *
 * @author ermolaev
 */
class TimeColumn extends ColumnForm {

    function __construct() {
        parent::__construct();
        $this->title_text    = plugin_lang_get( 'time_event' );
        $t_date              = self::$time_period_list[count( self::$time_period_list ) - 1];
        $this->last_row_text = gmdate( "H:i", $t_date );
    }

    protected function html_column_param() {
        return '<td class="column-time-td">';
    }

    protected function html_hour_text( $p_time ) {
        $t_result = '';

//        if( $p_time % (Calendar::$min_segment_time_in_hour * 2) == 0 ) {
//            $t_result .= gmdate( "H:i", $p_time );
//        }
        if( self::$intervals_per_hour % 2 == 0 ) {
            if( ( $p_time / self::$min_segment_time_in_hour ) % 2 == 0 ) {
                $t_result .= gmdate( "H:i", $p_time );
            }
        } elseif( ( $p_time / self::$min_segment_time_in_hour ) % 2 != 0 ) {
            $t_result .= gmdate( "H:i", $p_time );
        }
        return $t_result;
    }

}
