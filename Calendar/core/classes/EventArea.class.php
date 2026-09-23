<?php
# Copyright (c) 2026 Grigoriy Ermolaev (igflocal@gmail.com)
# Calendar plugin for MantisBT is free software:
# you can redistribute it and/or modify it under the terms of the GNU
# General Public License as published by the Free Software Foundation,
# either version 2 of the License, or (at your option) any later version.
#
# Calendar plugin for MantisBT is distributed in the hope
# that it will be useful, but WITHOUT ANY WARRANTY; without even the
# implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# See the GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Calendar plugin for MantisBT.
# If not, see <http://www.gnu.org/licenses/>.

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
        $this->is_in_past = calendar_event_is_in_past( $this->event['date_from'], $this->event['duration'] );

        $this->total_event_in_group    = $p_total_event_in_group;
        $this->current_number_in_group = $p_current_number_in_group;
    }

    public function html() {
        $t_result = '';

        # the block covers the part of the occurrence that falls on this day
        # and into the shown time range; the label names the whole occurrence
        $t_time_ar         = explode( ":", date( "H:i", $this->event['segment_from'] ) );
        $t_event_timestamp = ((int)$t_time_ar[0] * 3600) + ((int)$t_time_ar[1] * 60);
        $t_time_above      = $t_event_timestamp - ColumnForm::$time_period_list[0];
        $t_segment_length  = $this->event['segment_to'] - $this->event['segment_from'];

        $t_top   = (( $t_time_above / 60 ) / (ColumnForm::$intervals_per_hour )) * ColumnForm::$html_interval_height;
        $t_left  = ( 100 / $this->total_event_in_group ) * $this->current_number_in_group;
        $t_hight = ( ( $t_segment_length / 60 ) / (ColumnForm::$intervals_per_hour ) ) * ColumnForm::$html_interval_height;
        $t_width = ( 100 - $t_left - 3 - (4 * ($this->total_event_in_group - ($this->current_number_in_group + 1))));

        $t_name       = event_get_field( $this->event['id'], "name" );
        $t_time_label = calendar_event_time_label( $this->event['date_from'], $this->event['duration'] );

        $t_text_area = $t_name . '</br>' . $t_time_label;

        # the project is told by the colour of the block (see the legend)
        # and named in the tooltip only
        $t_project_id = event_get_field( $this->event['id'], "project_id" );
        $t_title      = $t_name . ' | ' . $t_time_label . ' [ ' . project_get_field( $t_project_id, "name" ) . ' ]';
        if( calendar_rsvp_is_pending_for_current_user( $this->event['id'] ) ) {
            $t_title .= ' - ' . plugin_lang_get( 'rsvp_status_none' );
        }

        $t_id = $this->is_in_past ? 'event_week_expired' : 'event_week';

        $t_result .= '<a href=' . WeekCalendar::$link_options
                . '&event_id=' . $this->event['id']
                . '&date=' . $this->event['date_from']
                . ' id="' . $t_id . '"'
                . ' title="' . string_attribute( $t_title ) . '"'
                . ' style="' . calendar_project_color_style( $t_project_id )
                . WeekCalendar::state_style( $this->event['id'] )
                . 'z-index:' . (100 + $this->current_number_in_group) . ';'
                . ' height:' . $t_hight . 'px;'
                . ' width:' . $t_width . '%;'
                . ' top:' . ($t_top + ColumnForm::HEADER_HEIGHT + ColumnForm::bands_height() + ColumnForm::OUT_OF_RANGE_ROW_HEIGHT) . 'px;'
                . ' left: ' . $t_left . '%;">' . $t_text_area . '</a>';

        return $t_result;
    }

}
