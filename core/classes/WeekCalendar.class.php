<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Calendar
 *
 * @author ermolaev
 */
abstract class WeekCalendar {
    public static $full_time_is = false;
    public static $link_options = '';
    protected $day_colums       = array();

    public function __construct( $p_days_events, $p_link_options, $p_is_full_time = false ) {
        self::$full_time_is = $p_is_full_time;
        self::$link_options = $p_link_options;

        foreach( $p_days_events as $t_day => $t_events_row ) {
            $this->day_colums[] = new DayColumn( $t_day, $t_events_row );
        }
    }
    
    public function __destruct() {
        ColumnForm::$is_initialized = FALSE;
    }
    protected function print_spacer_top() {
        echo '';
    }

    abstract protected function print_headline();

    protected function print_menu_top() {
        echo '';
    }

    protected function print_body() {
        $t_css_collapsed = count( $this->day_colums ) == 0 ? 'style="display: none"' : '';
        echo '<div class="widget-main no-padding"' . $t_css_collapsed . '>';
        echo '<div class="table-responsive" style="overflow-y: hidden;">';
        echo '<table class="calendar-user week">';
        echo '<tr class="row-day">';

        echo (new TimeColumn() )->html();

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
        echo '<a id="calendar_event_attachments"></a>';
        $this->print_spacer_top();

        echo '<div class="widget-box widget-color-blue2">';

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
