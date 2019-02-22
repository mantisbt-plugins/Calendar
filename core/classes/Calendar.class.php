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

    protected function print_spacer() {
        echo '';
    }

    abstract protected function print_headline();

    protected function print_menu_top() {
        echo '';
    }

    abstract protected function print_body();

    protected function print_menu_bottom() {
        echo '';
    }

    public final function print_html() {

        echo '<div class="col-md-12 col-xs-12">';
        $this->print_spacer();

        echo '<div class="widget-box widget-color-blue2">';

        echo $this->print_headline();

        echo '<div class="widget-body">';

        $this->print_menu_top();
        $this->print_body();
        $this->print_menu_bottom();

        echo '</div>';

        echo '</div>';
        echo '</div>';
    }

}
