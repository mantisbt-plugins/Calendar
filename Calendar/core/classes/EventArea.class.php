<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of EventArea
 *
 * @author ermolaev
 */
class EventArea {
    private $is_in_past;
    private $event;
    private $total_event_in_group    = 0;
    private $current_number_in_group = 0;

    function __construct( $p_event_row, $p_total_event_in_group, $p_current_number_in_group ) {

        $this->event      = $p_event_row;
        $this->is_in_past = ( $this->event['date_from'] + $this->event['duration'] ) < date( "U", strtotime( date( "j.n.Y" ) ) ) ? TRUE : FALSE;

        $this->total_event_in_group    = $p_total_event_in_group;
        $this->current_number_in_group = $p_current_number_in_group;
    }

    public function html() {
        $t_result = '';

        $t_time_ar         = explode( ":", date( "H:i", $this->event['date_from'] ) );
        $t_event_timestamp = ((int)$t_time_ar[0] * 3600) + ((int)$t_time_ar[1] * 60);
        $t_time_above      = $t_event_timestamp - ColumnForm::$time_period_list[0];

        $t_top   = (( $t_time_above / 60 ) / (ColumnForm::$intervals_per_hour )) * ColumnForm::$html_interval_height;
        $t_left  = ( 100 / $this->total_event_in_group ) * $this->current_number_in_group;
        $t_hight = ( ( $this->event['duration'] / 60 ) / (ColumnForm::$intervals_per_hour ) ) * ColumnForm::$html_interval_height;
        $t_width = ( 100 - $t_left - 3 - (4 * ($this->total_event_in_group - ($this->current_number_in_group + 1))));

        $t_text_area = event_get_field( $this->event['id'], "name" );
        $t_text_area .= '</br>';

        $t_time_start  = date( "H:i", $this->event['date_from'] );
        $t_time_finish = date( "H:i", $this->event['date_from'] + $this->event['duration'] );

        $t_text_area .= $t_time_start . " - " . $t_time_finish;
        $t_text_area .= '</br>';

        $t_text_area .= '[ ' . project_get_field( event_get_field( $this->event['id'], "project_id" ), "name" ) . ' ]';

        $t_id = $this->is_in_past ? 'event_week_expired' : 'event_week';

        $t_result .= '<a href=' . WeekCalendar::$link_options
                . '&event_id=' . $this->event['id']
                . '&date=' . $this->event['date_from']
                . ' id="' . $t_id . '"'
                . ' style="z-index:' . (100 + $this->current_number_in_group) . ';'
                . ' height:' . $t_hight . 'px;'
                . ' width:' . $t_width . '%;'
                . ' top:' . ($t_top + 38) . 'px;'
                . ' left: ' . $t_left . '%;">' . $t_text_area . '</a>';

        return $t_result;
    }

}
