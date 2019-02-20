<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of WeekView
 *
 * @author ermolaev
 */
class ViewWeek extends Calendar {
    private $week;
    private $for_user;

    //put your code here
    public function __construct( $p_week, $p_user, $p_is_full_time ) {
        $this->week         = $p_week;
        $this->for_user     = (int)$p_user;
        self::$full_time_is = (bool)$p_is_full_time;

        $t_start_day_of_the_week = plugin_config_get( "startStepDays" );
        $t_step_days_count       = plugin_config_get( "countStepDays" );
        $t_arWeekdaysName        = plugin_config_get( "arWeekdaysName" );

        $t_days = days_of_number_week( $t_start_day_of_the_week, $t_step_days_count, $t_arWeekdaysName, $this->week );

        $this->day_colums = get_days_object( $t_days, helper_get_current_project(), $this->for_user );
    }

    protected function print_spacer() {
        echo '<div class="space-10">';
        echo '</div>';
    }

    protected function print_headline() {

        echo '<div class="widget-header widget-header-small">';

        echo '<h4 class="widget-title lighter">';
        echo '<i class="ace-icon fa fa-list-alt"></i>';
        echo plugin_lang_get( 'menu_main_front' ) . " ( GMT " . date( "P" ) . " )";
        echo '</h4>';

        if( access_compare_level( access_get_global_level(), plugin_config_get( 'manage_calendar_threshold' ) ) ) {
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

        echo '<div class="btn-group pull-left">';
        if( Calendar::$full_time_is == FALSE ) {
            print_small_button( plugin_page( 'calendar_user_page' ) . "&for_user=" . $this->for_user . "&week=" . $this->week . "&full_time=TRUE", "0-24" );
        } else {
            print_small_button( plugin_page( 'calendar_user_page' ) . "&for_user=" . $this->for_user . "&week=" . $this->week, gmdate( "H", plugin_config_get( 'time_day_start' ) ) . "-" . gmdate( "H", plugin_config_get( 'time_day_finish' ) ) );
        }
        echo '</div>';

        echo '<div class="btn-group pull-right">';
        if( Calendar::$full_time_is == FALSE ) {
            print_small_button( plugin_page( 'calendar_user_page' ) . "&for_user=" . $this->for_user . "&week=" . ($this->week - 1), plugin_lang_get( 'previous_period' ) );
            print_small_button( plugin_page( 'calendar_user_page' ) . "&for_user=" . $this->for_user . "&week=" . (int)date( "W" ), plugin_lang_get( 'week' ) );
            print_small_button( plugin_page( 'calendar_user_page' ) . "&for_user=" . $this->for_user . "&week=" . ($this->week + 1), plugin_lang_get( 'next_period' ) );
        } else {
            print_small_button( plugin_page( 'calendar_user_page' ) . "&for_user=" . $this->for_user . "&week=" . ($this->week - 1) . "&full_time=TRUE", plugin_lang_get( 'previous_period' ) );
            print_small_button( plugin_page( 'calendar_user_page' ) . "&for_user=" . $this->for_user . "&week=" . (int)date( "W" ) . "&full_time=TRUE", plugin_lang_get( 'week' ) );
            print_small_button( plugin_page( 'calendar_user_page' ) . "&for_user=" . $this->for_user . "&week=" . ($this->week + 1) . "&full_time=TRUE", plugin_lang_get( 'next_period' ) );
        }
        echo '</div>';

        echo '</div>';
        echo '</div>';
    }

    protected function print_menu_bottom() {

        echo '<div class="widget-toolbox padding-8 clearfix">';
        echo '<div class="btn-toolbar">';

        echo '<div class="btn-group pull-left">';
        if( access_compare_level( access_get_project_level(), plugin_config_get( 'report_event_threshold' ) ) ) {
            if( self::$full_time_is == TRUE ) {
                print_small_button( plugin_page( 'event_add_page' ) . "&full_time=TRUE", plugin_lang_get( 'add_new_event' ) );
            } else {
                print_small_button( plugin_page( 'event_add_page' ), plugin_lang_get( 'add_new_event' ) );
            }
        }
        echo '</div>';

        echo '<div class="btn-group pull-right">';

        echo '<form id="filter-queries-form" class="form-inline pull-left padding-left-8"  method="get" name="list_queries" action="' . plugin_page( 'calendar_user_page' ) . '">';
        # CSRF protection not required here - form does not result in modifications
        echo '<input type="hidden" name="page" value="Calendar/calendar_user_page" />';
        echo '<input type="hidden" name="week" value="' . $this->week . '" />';
        echo '<input type="hidden" name="full_time" value="' . (int)self::$full_time_is . '" />';

        echo '<label class="inline">' . plugin_lang_get( 'filter_text' ) . '</label>';
        echo '<select name="for_user">';
        echo '<option value="' . auth_get_current_user_id() . '">[' . lang_get( 'reset_query' ) . ']</option>';
        if( $this->for_user == 0 ) {
            echo '<option selected="selected" value="0">[' . plugin_lang_get( 'select_all_users' ) . ']</option>';
        } else {
            echo '<option value="0">[' . plugin_lang_get( 'select_all_users' ) . ']</option>';
        }

        print_user_option_list( $this->for_user, helper_get_current_project(), plugin_config_get( 'report_event_threshold' ) );

        echo '</select>';
        echo '</form>';

        echo '</div>';

        echo '</div>';
        echo '</div>';
    }

}
