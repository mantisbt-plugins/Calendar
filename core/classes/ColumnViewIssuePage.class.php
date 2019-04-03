<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ColumnViewIssuePage
 *
 * @author ermolaev
 */
class ColumnViewIssuePage extends MantisColumn {

    public function __construct() {

        $s_text = OFF;

        if( plugin_is_registered( 'MantisCoreFormatting' ) ) {
            plugin_push_current( 'MantisCoreFormatting' );
            $s_text = plugin_config_get( 'process_text' );
            plugin_pop_current();
        }

        if( ON == $s_text ) {
            $this->title = 'callback_calendar_event_title_format';
        } else {
            $t_event_count_text = plugin_lang_get( 'column_view_issue_page_title' );
            $this->title        = '<i class="fa fa-calendar blue" title="' . $t_event_count_text . '"></i>';
        }

        $this->column = 'events_count';
    }

    public function cache( array $p_bugs ) {
        plugin_push_current( 'Calendar' );
        $t_bug_ids = array();
        foreach( $p_bugs as $t_bug ) {
		$t_bug_ids[] = (int)$t_bug->id;
	}
        calendar_event_issue_attachment_count_cache( $t_bug_ids );
        plugin_pop_current();
    }

    public function display( \BugData $p_bug, $p_columns_target ) {
        plugin_push_current('Calendar');

        $t_attachment_count = calendar_event_issue_attachment_count( $p_bug->id );

        if( $t_attachment_count > 0 && access_has_project_level( plugin_config_get( 'bug_calendar_view_threshold' ), $p_bug->project_id ) ) {
            $t_href       = string_get_bug_view_url( $p_bug->id ) . '#calendar_event_attachments';
            $t_href_title = sprintf( lang_get( 'view_attachments_for_issue' ), $t_attachment_count, $p_bug->id );
            echo '<a href="' . $t_href . '" title="' . $t_href_title . '">' . $t_attachment_count . '</a>';
        } else {
            echo ' &#160; ';
        }
        
        plugin_pop_current();
    }

}
