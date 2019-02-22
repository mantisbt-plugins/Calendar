<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of IssueView
 *
 * @author ermolaev
 */
class ViewIssue extends ViewWeek {
    private $bug_id;

    //put your code here

    public function __construct( $p_bug_id ) {
        $this->bug_id  = $p_bug_id;
        
        $t_events_id   = get_events_id_from_bug_id( $p_bug_id );
        $this->day_colums = calendar_column_objects_get_from_event_ids( $t_events_id );
    }

    protected function print_spacer() {
        echo '';
    }
    
    protected function print_headline() {
        echo '<div class="widget-header widget-header-small">';
        echo '<h4 class="widget-title lighter">';
        echo '<i class="ace-icon fa fa-list-alt"></i>';

        if( count( $this->day_colums ) == 0 ) {
            echo plugin_lang_get( 'not_assigned_event' );
        } else {
            echo plugin_lang_get( 'assigned_event' );
        }

        echo '</h4>';
        echo '</div>';
    }
    
    protected function print_menu_top() {
        echo '';
    }

    protected function print_menu_bottom() {
        if( access_compare_level( access_get_project_level(), plugin_config_get( 'report_event_threshold' ) ) && !bug_is_readonly( $this->bug_id ) ) {
            echo '<div class="widget-toolbox padding-8 clearfix">';

            echo '<div class="form-inline pull-left padding-2">';
            print_small_button( plugin_page( 'calendar_event_insert_page' ) . "&bug_id=" . $this->bug_id, plugin_lang_get( 'insert_event' ) );
            echo '</div>';
            
            echo '<div class="form-inline pull-left padding-2">';
            print_small_button( plugin_page( 'event_add_page' ) . "&bug_id=" . $this->bug_id, plugin_lang_get( 'add_new_event' ) );
            echo '</div>';
            
            echo '</div>';
        }
    }
}
