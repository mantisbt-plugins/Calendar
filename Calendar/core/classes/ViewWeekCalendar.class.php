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
 * Description of WeekView
 *
 * @author ermolaev
 */
class ViewWeekCalendar extends WeekCalendar {
    protected $week;
    protected $for_user;
    protected $year;
    protected $date_selected;

    //put your code here
    public function __construct( $p_week, $p_user, $p_is_full_time, $p_days_events, $p_link_options, $p_year, $p_date_selected = false ) {
        $this->week          = $p_week;
        $this->for_user      = (int)$p_user;
        $this->year          = $p_year;
        $this->date_selected = $p_date_selected;

        parent::__construct($p_days_events, $p_link_options, $p_is_full_time);
    }

    protected function date_to_display() {
        if( !is_bool( $this->date_selected ) ) {
            return date( plugin_config_get( 'short_date_format' ), $this->date_selected );
        }

        return date( plugin_config_get( 'short_date_format' ) );
    }

    protected function full_time_url() {
        return plugin_page( 'calendar_user_page' ) . "&for_user=" . $this->for_user . "&week=" . $this->week . "&year=" . $this->year . "&full_time=TRUE" . "&date_select=" . $this->date_to_display();
    }

    protected function day_range_url() {
        return plugin_page( 'calendar_user_page' ) . "&for_user=" . $this->for_user . "&week=" . $this->week . "&year=" . $this->year . "&full_time=FALSE" . "&date_select=" . $this->date_to_display();
    }

    protected function print_spacer_top() {
        echo '<div class="space-10">';
        echo '</div>';
    }

    protected function add_event_url() {
        if( !access_compare_level( access_get_project_level(), plugin_config_get( 'report_event_threshold' ) ) ) {
            return NULL;
        }

        return plugin_page( 'event_add_page' );
    }

    protected function print_headline() {

        echo '<div class="widget-header widget-header-small">';

        echo '<h4 class="widget-title lighter">';
        echo '<i class="ace-icon fa fa-calendar"></i>';
        echo "GMT " . date( "P" );
        echo '</h4>';

        if( access_has_project_level( plugin_config_get( 'manage_calendar_threshold' ) ) ) {
            echo '<div class="widget-toolbar no-border">';
            echo '<div class="widget-menu">';
            print_small_button( plugin_page( 'user_config_page' ), plugin_lang_get( 'config_title' ) );
            echo '</div>';
            echo '</div>';
        }

        echo '</div>';
    }

    protected function print_menu_top() {
        echo '<div class="widget-toolbox padding-8 clearfix">';      
        echo '<div class="btn-toolbar">';
        echo '<form id="select_date_form" method="post" action="' . plugin_page( 'calendar_user_page' ) . '" class="btn-toolbar">';        
        
        # Switch to the month view button
        echo '<div class="btn-group">';
        $t_first_day_of_week = strtotime($this->year . 'W' . str_pad($this->week, 2, '0', STR_PAD_LEFT));
        $t_url = plugin_page('calendar_user_page') . 
                '&view=month' . 
                '&month=' . date('n', $t_first_day_of_week) . 
                '&year=' . $this->year;
        if (!is_bool($this->date_selected)) {
            $t_url .= '&date_select=' . date(plugin_config_get('short_date_format'), $this->date_selected);
        }
        if (self::$full_time_is) {
            $t_url .= '&full_time=TRUE';
        }
        if ($this->for_user != auth_get_current_user_id()) {
            $t_url .= '&for_user=' . $this->for_user;
        }
        print_small_button($t_url, plugin_lang_get('month_view'));
        echo '</div>';
        
        # the time range toggle is the title of the time column
        print_hidden_inputs( array( 'full_time' => self::$full_time_is ? 'TRUE' : 'FALSE' ) );
        $t_date_to_display = $this->date_to_display();

        # Navigation buttons
        echo '<div id="nav-button" class="btn-group pull-right">';
        echo '<div class="btn-group">';
        echo '<input type="text" id="date_select" name="date_select" class="datetimepicker input-sm" ' .
                                'data-picker-locale="' . lang_get_current_datetime_locale() .
                                '" data-picker-format="' . plugin_config_get( 'datetime_picker_format' ) . '" ' .
                                'size="10" maxlength="10" autocomplete="off" value="' . $t_date_to_display . '" />';
        echo '</div>';
        echo '<div id="nav-button" class="btn-group pull-right calendar-nav-period">';
        if( self::$full_time_is == FALSE ) {
            print_small_button( plugin_page( 'calendar_user_page' ) . "&for_user=" . $this->for_user . "&week=" . date( "W", timestamp_previous_week_get( $this->week, $this->year ) ) . "&year=" . date( "o", timestamp_previous_week_get( $this->week, $this->year ) ), '<<' );
            print_link_button( plugin_page( 'calendar_user_page' ) . "&for_user=" . $this->for_user . "&week=" . (int)date( "W" ), plugin_lang_get( 'current_period' ), 'btn-sm calendar-nav-current' );
            print_small_button( plugin_page( 'calendar_user_page' ) . "&for_user=" . $this->for_user . "&week=" . date( "W", timestamp_next_week_get( $this->week, $this->year ) ) . "&year=" . date( "o", timestamp_next_week_get( $this->week, $this->year ) ), '>>' );
        } else {
            print_small_button( plugin_page( 'calendar_user_page' ) . "&for_user=" . $this->for_user . "&week=" . date( "W", timestamp_previous_week_get( $this->week, $this->year ) ) . "&year=" . date( "o", timestamp_previous_week_get( $this->week, $this->year ) ) . "&full_time=TRUE", '<<' );
            print_link_button( plugin_page( 'calendar_user_page' ) . "&for_user=" . $this->for_user . "&week=" . (int)date( "W" ) . "&full_time=TRUE", plugin_lang_get( 'current_period' ), 'btn-sm calendar-nav-current' );
            print_small_button( plugin_page( 'calendar_user_page' ) . "&for_user=" . $this->for_user . "&week=" . date( "W", timestamp_next_week_get( $this->week, $this->year ) ) . "&year=" . date( "o", timestamp_next_week_get( $this->week, $this->year ) ) . "&full_time=TRUE", '>>' );
        }
        echo '</div>';
        echo '</div>';
        
        echo '</form>';
        echo '</div>';
        echo '</div>';        
    }

    protected function print_menu_bottom() {

        echo '<div class="widget-toolbox padding-8 clearfix">';
        echo '<div class="btn-toolbar calendar-toolbar-bottom">';

        echo '<div class="btn-group pull-left">';
        if( access_compare_level( access_get_project_level(), plugin_config_get( 'report_event_threshold' ) ) ) {
            if( self::$full_time_is == TRUE ) {
                print_small_button( plugin_page( 'event_add_page' ) . "&full_time=TRUE", plugin_lang_get( 'add_new_event' ) );
            } else {
                print_small_button( plugin_page( 'event_add_page' ), plugin_lang_get( 'add_new_event' ) );
            }
        }
        echo '</div>';

        print_project_legend( $this->project_ids );

        echo '<div id="nav-button" class="btn-group pull-right">';

        echo '<form id="filter-queries-form" class="btn-toolbar"  method="get" name="list_queries" action="' . plugin_page( 'calendar_user_page' ) . '">';
        # CSRF protection not required here - form does not result in modifications
        echo '<input type="hidden" name="page" value="Calendar/calendar_user_page" />';
        echo '<input type="hidden" name="week" value="' . $this->week . '" />';
        echo '<input type="hidden" name="year" value="' . $this->year . '" />';
        echo '<input type="hidden" name="full_time" value="' . (int)self::$full_time_is . '" />';

        echo '<label class="inline"></label>';
        echo '<select name="for_user">';
        print_for_user_option_list( $this->for_user );

        echo '</select>';
        echo '</form>';

        echo '</div>';

        echo '</div>';
        echo '</div>';
    }

}
