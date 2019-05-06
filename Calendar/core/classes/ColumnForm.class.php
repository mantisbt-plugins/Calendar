<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

abstract class ColumnForm {
    const HEIGHT_MULTIPLIER = 3;
    const HOUR              = 3600;
    const DAY_MIN_TIME      = 0;
    const DAY_MAX_TIME      = 86400;

    protected $title_text    = '';
    protected $last_row_text = '';
    public static $time_period_list   = array();
    public static $intervals_per_hour = 0;
    public static $is_initialized    = false;
    public static $html_interval_height;
    public static $min_segment_time_in_hour;
    public static $ratio_height;
    public static $total_days_counter;

    function __construct() {
        if( !self::$is_initialized ) {
            self::$total_days_counter = 0;
            self::$intervals_per_hour = plugin_config_get( 'stepDayMinutesCount' );
            self::$ratio_height       = self::HEIGHT_MULTIPLIER / self::$intervals_per_hour;

            self::$html_interval_height = self::$intervals_per_hour / self::$ratio_height;

            self::$min_segment_time_in_hour = self::HOUR / self::$intervals_per_hour;

            if( WeekCalendar::$full_time_is ) {
                self::$time_period_list = range( self::DAY_MIN_TIME, self::DAY_MAX_TIME, self::$min_segment_time_in_hour );
            } else {
                $t_time_day_start       = plugin_config_get( 'time_day_start', plugin_config_get( 'time_day_start' ), FALSE, auth_get_current_user_id() );
                $t_time_day_finish      = plugin_config_get( 'time_day_finish', plugin_config_get( 'time_day_finish' ), FALSE, auth_get_current_user_id() );
                self::$time_period_list = range( $t_time_day_start, $t_time_day_finish, self::$min_segment_time_in_hour );
            }
            self::$is_initialized = TRUE;
        }
    }

//    abstract static function get_event_area($p_event_row, $p_total_event_in_group, $p_current_number_in_group);

    abstract protected function html_column_param();

    protected function html_body() {
        return '';
    }

    protected function html_hour_text( $p_time ) {
        return '';
    }

    final public function html() {
        $t_result = '';

        $t_result .= $this->html_column_param();
        $t_result .= '<ul class="column-header-day"><span>' . $this->title_text . '</span></ul>';

        $t_result .= $this->html_body();

        $t_count_times_day = count( self::$time_period_list ) - 1;

        foreach( self::$time_period_list as $key => $t_time ) {

            if( !($key % 2) && $t_count_times_day != $key ) {
                $t_result .= "<ul class=\"hour\" id=\"hour_" . $t_time . "\">";
            }
            if( $t_count_times_day == $key ) {
                break;
            }

            $t_result .= "<li>";
            $t_result .= $this->html_hour_text( $t_time );
            $t_result .= "</li>";

            if( $key % 2 && $t_count_times_day != $key ) {
                $t_result .= "</ul>";
            }
        }

        $t_result .= "<ul class=\"hour last-row\" id=\"area_hour\">";
        $t_result .= "<li>" . $this->last_row_text . "</li>";
        $t_result .= "</ul>";

        $t_result .= "</td>";

        return $t_result;
    }

}
