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

class DayColumn extends ColumnForm {
    protected $timestamp          = 1;
    protected $events_area_group  = array();
    protected $is_today;
    protected $event_above_count = 0;
    protected $event_below_count = 0;
    protected $bands              = array();

    /**
     * @param int         $p_timestamp     Day start
     * @param array       $p_events_row    Events of the day, without the multi-day ones
     * @param string      $p_full_time_url URL of the same view in 0-24 mode; NULL leaves the
     *                                     out-of-range counters as plain text
     * @param EventBand[] $p_bands         Multi-day bands that start in this column
     */
    public function __construct( $p_timestamp, $p_events_row, $p_full_time_url = NULL, array $p_bands = array() ) {
        parent::__construct();

        $this->timestamp = $p_timestamp;
        $this->bands     = $p_bands;

        $t_events_row_to_group = array();

        # the part of the day the column shows; a segment that lies entirely
        # outside of it is only counted, one that crosses its edge is clipped
        $t_window_start = $this->timestamp + self::$time_period_list[0];
        $t_window_end   = $this->timestamp + self::$time_period_list[count( self::$time_period_list ) - 1];

        foreach( $p_events_row as $t_event_row ) {
            if( $t_event_row['segment_to'] <= $t_window_start ) {
                $this->event_above_count++;
            } elseif( $t_event_row['segment_from'] >= $t_window_end ) {
                $this->event_below_count++;
            } else {
                $t_event_row['segment_from'] = max( $t_event_row['segment_from'], $t_window_start );
                $t_event_row['segment_to']   = min( $t_event_row['segment_to'], $t_window_end );
                $t_events_row_to_group[]     = $t_event_row;
            }
        }

        $t_group_number     = 0;
        $t_group_events_row = array();

        foreach( $t_events_row_to_group as $key => &$t_event_row ) {
            unset( $t_events_row_to_group[$key] );
            $this->group_event( $t_event_row, $t_events_row_to_group, $t_group_events_row[$t_group_number] );
            $t_group_number++;
            reset( $t_events_row_to_group );
        }

        foreach( $t_group_events_row as $key => $t_events_row ) {
            # the position in the group is the stacking order: the longest
            # block goes to the back and the left, every shorter one is drawn
            # on top of it and shifted right, so that none is hidden entirely
            usort( $t_events_row, function( $p_a, $p_b ) {
                $t_length_a = $p_a['segment_to'] - $p_a['segment_from'];
                $t_length_b = $p_b['segment_to'] - $p_b['segment_from'];
                if( $t_length_a != $t_length_b ) {
                    return $t_length_b - $t_length_a;
                }
                return $p_a['segment_from'] - $p_b['segment_from'];
            } );

            foreach( $t_events_row as $key_in_group => $t_event_row ) {
                $this->events_area_group[$key][] = new EventArea( $t_event_row, count( $t_events_row ), $key_in_group );
//                $this->events_area_group[$key][] = CalendarServiceLocator::get( 'EventArea', array( $t_event_row, count( $t_events_row ), $key_in_group ));
            }
        }

        $this->title_text = plugin_lang_get( date( "D", $this->timestamp ) ) . ', ' . date( config_get( 'short_date_format' ), $this->timestamp );
        if( $this->event_above_count > 0 ) {
            $this->first_row_text = $this->out_of_range_html( $this->event_above_count, 'out_of_range_above', $p_full_time_url );
        }
        if( $this->event_below_count > 0 ) {
            $this->last_row_text = $this->out_of_range_html( $this->event_below_count, 'out_of_range_below', $p_full_time_url );
        }

        $this->is_today = date( "U", strtotime( date( "j.n.Y" ) ) ) == $this->timestamp ? TRUE : FALSE;
        self::$total_days_counter++;
    }

//    public static function get_event_area( $p_event_row, $p_total_event_in_group, $p_current_number_in_group ) {
//        return new EventArea( $p_event_row, $p_total_event_in_group, $p_current_number_in_group );
//    }

    /**
     * "+N events earlier/later" counter, linked to the 0-24 view when a URL is given
     */
    private function out_of_range_html( $p_count, $p_lang_key, $p_full_time_url ) {
        $t_text = '+' . $p_count . ' ' . plugin_lang_get( $p_lang_key );
        if( $p_full_time_url === NULL ) {
            return $t_text;
        }

        return '<a href="' . string_attribute( $p_full_time_url ) . '">' . $t_text . '</a>';
    }

    protected function html_column_param() {
        $t_class = $this->is_today ? 'column-this-day-td' : 'column-day-td';

        return '<td class="' . $t_class . '"'
                . ' data-date="' . date( 'Y-m-d', $this->timestamp ) . '"'
                . ' style="width: calc(100%/' . self::$total_days_counter . ')">';
    }

    protected function html_hour_li_attr( $p_time ) {
        return ' class="time-slot" data-time="' . (int)$p_time . '"';
    }

    protected function html_body() {
        $t_result = '';

        foreach( $this->bands as $t_band ) {
            $t_result .= $t_band->html();
        }

        foreach( $this->events_area_group as $t_events_area ) {
            foreach( $t_events_area as $t_event_area ) {
                $t_result .= $t_event_area->html();
            }
        }

        return $t_result;
    }

    /**
     * Collect into $p_result every event whose segment of the day overlaps
     * with the given one, directly or through another overlapping event
     */
    protected function group_event( $p_event_first, &$p_events, &$p_result ) {
        foreach( $p_events as $key => &$t_event ) {
            if( $p_event_first['segment_from'] < $t_event['segment_to'] && $t_event['segment_from'] < $p_event_first['segment_to'] ) {
                unset( $p_events[$key] );
                $this->group_event( $t_event, $p_events, $p_result );
            }
        }
        $p_result[] = $p_event_first;
    }

}
