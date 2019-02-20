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
abstract class Calendar {
    static public $full_time_is = false;
    protected $day_colums       = array();

    abstract protected function print_headline();

    protected function print_menu_top() {
        echo '';
    }

    protected function print_spacer() {
        echo '';
    }

    private function print_columns() {
        echo '<div class="widget-main no-padding">';
        echo '<div class="table-responsive" style="overflow-y: hidden;">';
        echo '<table class="calendar-user week">';
        echo '<tr class="row-day">';

        $t_time_column = new TimeColumn();
        echo $t_time_column->html();

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

    public final function print_html() {

        echo '<div class="col-md-12 col-xs-12">';
        $this->print_spacer();


        $t_css_collapsed = count( $this->day_colums ) == 0 ? ' collapsed' : '';
        echo '<div class="widget-box widget-color-blue2' . $t_css_collapsed . '">';

        echo $this->print_headline();

        echo '<div class="widget-body">';

        $this->print_menu_top();
        $this->print_columns();
        $this->print_menu_bottom();

        echo '</div>';

        echo '</div>';
        echo '</div>';
    }

}
