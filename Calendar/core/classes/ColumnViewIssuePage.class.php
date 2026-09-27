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
 * Description of ColumnViewIssuePage
 *
 * @author ermolaev
 */
class ColumnViewIssuePage extends MantisColumn {

    public function __construct() {

        $this->sortable = TRUE;
        $s_text         = OFF;

        if( plugin_is_registered( 'MantisCoreFormatting' ) ) {
            plugin_push_current( 'MantisCoreFormatting' );
            $s_text = plugin_config_get( 'process_text' );
            plugin_pop_current();
        }

        if( ON == $s_text ) {
            $this->title = plugin_lang_get( 'column_view_issue_page_title' );
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
        plugin_push_current( 'Calendar' );

        $t_attachment_count = calendar_event_issue_attachment_count( $p_bug->id );

        if( $t_attachment_count > 0 && access_has_project_level( plugin_config_get( 'bug_calendar_view_threshold' ), $p_bug->project_id ) ) {
            if( $p_columns_target == COLUMNS_TARGET_PRINT_PAGE || $p_columns_target == COLUMNS_TARGET_VIEW_PAGE ) {
                $t_href       = string_get_bug_view_url( $p_bug->id ) . '#calendar_event_attachments';
                $t_href_title = sprintf( lang_get( 'view_attachments_for_issue' ), $t_attachment_count, $p_bug->id );
                echo '<a href="' . $t_href . '" title="' . $t_href_title . '">' . $t_attachment_count . '</a>';
            } else {
                echo $t_attachment_count;
            }
        } else {
            if( $p_columns_target == COLUMNS_TARGET_PRINT_PAGE || $p_columns_target == COLUMNS_TARGET_VIEW_PAGE ) {
                echo ' &#160; ';
            } else {
                echo '';
            }
        }

        plugin_pop_current();
    }

    public function sortquery( $p_direction ) {
        plugin_push_current( 'Calendar' );

        $t_bug_table    = db_get_table( 'mantis_bug_table' );
        $t_relationship_table = plugin_table( 'relationship' );

        plugin_pop_current();

        return array(
                                  'join'  => "LEFT JOIN $t_relationship_table relationship ON $t_bug_table.id=relationship.bug_id",
                                  'order' => "relationship.event_id $p_direction",
        );
    }

}
