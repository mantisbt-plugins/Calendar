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
 * Description of IssueView
 *
 * @author ermolaev
 */
class ViewIssue extends WeekCalendar {
    private $bug_id;
    private $events_count;

    /**
     * @param array   $p_days_events
     * @param integer $p_bug_id
     * @param boolean $p_is_full_time
     * @param integer $p_events_count events attached to the issue, shown in the title
     */
    public function __construct( $p_days_events, $p_bug_id, $p_is_full_time = FALSE, $p_events_count = 0 ) {
        $this->bug_id       = $p_bug_id;
        $this->events_count = $p_events_count;
        parent::__construct( $p_days_events, plugin_page( 'view' ), $p_is_full_time );
    }

    /**
     * The issue page itself; when the block sits below the notes the page
     * is scrolled to it, inside the details table it is near the top anyway
     * @param boolean $p_full_time
     * @return string
     */
    private function issue_url( $p_full_time ) {
        $t_url = string_get_bug_view_url( $this->bug_id ) . '&issue_full_time=' . ( $p_full_time ? '1' : '0' );

        return calendar_bug_block_is_separate() ? $t_url . '#calendar_event_attachments' : $t_url;
    }

    protected function full_time_url() {
        return $this->issue_url( TRUE );
    }

    protected function collapse_name() {
        return 'calendar_issue';
    }

    protected function print_spacer_top() {
        # a widget of its own keeps the same distance from the notes as the
        # other blocks of the page; inside the details table the cell pads
        if( calendar_bug_block_is_separate() ) {
            echo '<div class="space-10">';
            echo '</div>';
        }
    }

    protected function day_range_url() {
        return $this->issue_url( FALSE );
    }

    protected function print_headline() {
        echo '<div class="widget-header widget-header-small">';

        $this->print_collapse_toolbar();

        # the account tab where the user picks the placement of this block
        if( calendar_bug_block_user_choice() ) {
            echo '<div class="widget-toolbar no-border">';
            echo '<a class="btn btn-primary btn-white btn-round btn-xs calendar-issue-settings" href="' . plugin_page( 'reminders_page' ) . '#bug_calendar_block" title="' . string_attribute( plugin_lang_get( 'issue_block_settings' ) ) . '">';
            echo '<i class="fa fa-cog"></i>';
            echo '</a>';
            echo '</div>';
        }

        echo '<h4 class="widget-title lighter">';
        echo '<i class="ace-icon fa fa-list-alt"></i>';

        # the count keeps the events in sight while the widget is collapsed
        if( count( $this->day_colums ) == 0 ) {
            echo plugin_lang_get( 'not_assigned_event' );
        } else {
            echo sprintf( plugin_lang_get( 'assigned_event_count' ), $this->events_count );
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
            print_small_button( plugin_page( 'event_insert_page' ) . "&id=" . $this->bug_id, plugin_lang_get( 'insert_event' ) );
            echo '</div>';

//            echo '<div class="form-inline pull-left padding-2">';
//            print_small_button( plugin_page( 'event_add_page' ) . "&id=" . $this->bug_id, plugin_lang_get( 'add_new_event' ) );
//            echo '</div>';

            echo '</div>';
        }
    }

}
