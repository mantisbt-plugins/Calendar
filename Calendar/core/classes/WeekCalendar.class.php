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
 * Description of Calendar
 *
 * @author ermolaev
 */
abstract class WeekCalendar {
    public static $full_time_is = false;
    public static $link_options = '';
    # the one event a grid is about; every other one is drawn faded, see focus_style()
    public static $focus_event_id = NULL;
    protected $day_colums       = array();
    protected $project_ids      = array();

    public function __construct( $p_days_events, $p_link_options, $p_is_full_time = false ) {
        self::$full_time_is = $p_is_full_time;
        self::$link_options = $p_link_options;

        # an occurrence that runs past midnight leaves the hour grid: it is
        # drawn once as a band over the columns of its days, the other rows
        # stay in their day columns
        $t_day_rows = array();
        $t_bands    = array();
        $t_column   = 0;

        foreach( $p_days_events as $t_day => $t_events_row ) {
            $t_day_rows[$t_day] = array();

            foreach( $t_events_row as $t_event_row ) {
                $this->project_ids[] = event_get_field( $t_event_row['id'], 'project_id' );

                if( !calendar_event_is_multiday( $t_event_row['date_from'], $t_event_row['duration'] ) ) {
                    $t_day_rows[$t_day][] = $t_event_row;
                    continue;
                }

                $t_key = $t_event_row['id'] . '_' . $t_event_row['date_from'];
                if( !isset( $t_bands[$t_key] ) ) {
                    $t_bands[$t_key] = array( 'row' => $t_event_row, 'first' => $t_column, 'last' => $t_column, 'first_day' => $t_day );
                }
                $t_bands[$t_key]['last']     = $t_column;
                $t_bands[$t_key]['last_day'] = $t_day;
            }
            $t_column++;
        }

        $t_lanes = $this->assign_band_lanes( $t_bands );

        ColumnForm::$band_lanes = count( array_unique( $t_lanes ) );

        $t_column_bands = array();
        foreach( $t_bands as $t_key => $t_band ) {
            # the occurrence may reach past the shown columns: into another
            # week, or into a weekday the user has switched off
            $t_row = $t_band['row'];
            $t_column_bands[$t_band['first']][] = new EventBand( $t_row, $t_lanes[$t_key], $t_band['last'] - $t_band['first'] + 1,
                                                                 $t_row['date_from'] < $t_band['first_day'],
                                                                 $t_row['date_from'] + $t_row['duration'] > strtotime( '+1 day', $t_band['last_day'] ) );
        }

        $t_column = 0;
        foreach( $t_day_rows as $t_day => $t_events_row ) {
            $this->day_colums[] = new DayColumn( $t_day, $t_events_row, $p_is_full_time ? NULL : $this->full_time_url(),
                                                 isset( $t_column_bands[$t_column] ) ? $t_column_bands[$t_column] : array() );
            $t_column++;
        }
    }

    /**
     * Give every band a lane so that bands sharing a column never overlap:
     * bands are taken in order of their first column, each goes to the first
     * lane that is free from that column on.
     *
     * @param array $p_bands Key => array( 'first' => column, 'last' => column )
     * @return array Key => lane number, from 0
     */
    private function assign_band_lanes( array $p_bands ) {
        uasort( $p_bands, function( $p_a, $p_b ) {
            if( $p_a['first'] != $p_b['first'] ) {
                return $p_a['first'] - $p_b['first'];
            }
            return $p_b['last'] - $p_a['last'];
        } );

        $t_lane_last_column = array();
        $t_lanes            = array();

        foreach( $p_bands as $t_key => $t_band ) {
            $t_lane = 0;
            while( isset( $t_lane_last_column[$t_lane] ) && $t_lane_last_column[$t_lane] >= $t_band['first'] ) {
                $t_lane++;
            }
            $t_lane_last_column[$t_lane] = $t_band['last'];
            $t_lanes[$t_key]             = $t_lane;
        }

        return $t_lanes;
    }

    public function __destruct() {
        ColumnForm::$is_initialized = FALSE;
        ColumnForm::$band_lanes     = 0;
        self::$focus_event_id       = NULL;
    }

    /**
     * Inline style that fades an event out when the grid is about another
     * one; empty when the grid is about none or about this one
     * @param integer $p_event_id Event drawn.
     * @return string
     */
    public static function focus_style( $p_event_id ) {
        if( self::$focus_event_id === NULL || (int)$p_event_id == self::$focus_event_id ) {
            return '';
        }

        return 'opacity:0.35;';
    }

    protected function print_spacer_top() {
        echo '';
    }

    abstract protected function print_headline();

    /**
     * Name the collapse state of the widget is stored under (see is_collapsed());
     * NULL keeps the widget always open
     */
    protected function collapse_name() {
        return NULL;
    }

    /**
     * The collapse toggle of the widget header; prints nothing without a collapse name
     */
    protected function print_collapse_toolbar() {
        $t_name = $this->collapse_name();
        if( $t_name === NULL ) {
            return;
        }

        $t_icon = is_collapsed( $t_name ) ? 'fa-chevron-down' : 'fa-chevron-up';
        echo '<div class="widget-toolbar">';
        echo '<a data-action="collapse" href="#"><i class="ace-icon fa ' . $t_icon . ' bigger-125"></i></a>';
        echo '</div>';
    }

    protected function print_menu_top() {
        echo '';
    }

    /**
     * URL of this view with the 0-24 time range; NULL when the view cannot switch.
     * Called from the constructor, so subclasses must set the fields it needs
     * before calling parent::__construct().
     */
    protected function full_time_url() {
        return NULL;
    }

    /**
     * URL of this view with the working hours range; NULL when the view cannot switch.
     */
    protected function day_range_url() {
        return NULL;
    }

    /**
     * The time range toggle of the time column title: array( 'url', 'text' )
     * with the link to the other range and the shown one; NULL for a plain title
     */
    protected function time_range_toggle() {
        $t_url = self::$full_time_is ? $this->day_range_url() : $this->full_time_url();
        if( $t_url === NULL ) {
            return NULL;
        }

        if( self::$full_time_is ) {
            return array( 'url' => $t_url, 'text' => '0-24' );
        }

        $t_user_id = auth_get_current_user_id();
        $t_start   = plugin_config_get( 'time_day_start', NULL, FALSE, $t_user_id );
        $t_finish  = plugin_config_get( 'time_day_finish', NULL, FALSE, $t_user_id );

        return array( 'url' => $t_url, 'text' => gmdate( "H", $t_start ) . "-" . gmdate( "H", $t_finish ) );
    }

    /**
     * URL of the event creation page used by the time range selection.
     * NULL disables the selection in the calendar.
     */
    protected function add_event_url() {
        return NULL;
    }

    protected function print_body() {
        $t_css_collapsed = count( $this->day_colums ) == 0 ? 'style="display: none"' : '';
        echo '<div class="widget-main no-padding"' . $t_css_collapsed . '>';
        echo '<div class="table-responsive" style="overflow-y: hidden;">';

        $t_add_event_url  = $this->add_event_url();
        $t_select_options = '';
        if( $t_add_event_url !== NULL ) {
            $t_select_options = ' data-add-event-url="' . string_attribute( $t_add_event_url ) . '"'
                    . ' data-time-step="' . ColumnForm::$min_segment_time_in_hour . '"'
                    . ' data-add-event-title="' . string_attribute( plugin_lang_get( 'add_new_event' ) ) . '"';
        }

        echo '<table class="calendar-user week"' . $t_select_options . '>';
        echo '<tr class="row-day">';

        $t_range_toggle = $this->time_range_toggle();
        echo (new TimeColumn( $t_range_toggle['url'] ?? NULL, $t_range_toggle['text'] ?? '' ) )->html();

        foreach( $this->day_colums as $t_column ) {
            echo $t_column->html();
        }

        echo '</tr>';
        echo '</table>';
        echo '</div>';
        echo '</div>';
    }

    protected function print_menu_bottom() {
        echo '';
    }

    protected function print_spacer_bottom() {
        echo '';
    }

    public final function print_html() {
        global $g_calendar_show_menu_bottom;

        echo '<div class="col-md-12 col-xs-12">';
        $this->print_spacer_top();
        echo '<a id="calendar_event_attachments"></a>';

        # the id lets the core script remember the collapse state in the cookie
        $t_name = $this->collapse_name();
        if( $t_name === NULL ) {
            echo '<div class="widget-box widget-color-blue2">';
        } else {
            echo '<div id="' . $t_name . '" class="widget-box widget-color-blue2' . ( is_collapsed( $t_name ) ? ' collapsed' : '' ) . '">';
        }

        echo $this->print_headline();

        echo '<div class="widget-body">';

        $this->print_menu_top();
        $this->print_body();
        if( $g_calendar_show_menu_bottom ) {
            $this->print_menu_bottom();
        }

        echo '</div>';

        echo '</div>';
        $this->print_spacer_bottom();
        echo '</div>';

        DayColumn::$total_days_counter = 0;
    }

}
