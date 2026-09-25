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

class ViewMonthCalendar {
    private $month;
    private $year;
    private $user_id;
    private $days_events;
    private $link_to_view;
    private $date_selected;

    public function __construct($p_month, $p_user_id, $p_days_events, $p_link_to_view, $p_year, $p_date_selected = false) {
        $this->month = $p_month;
        $this->year = $p_year;
        $this->user_id = $p_user_id;
        $this->days_events = $p_days_events;
        $this->link_to_view = $p_link_to_view;
        $this->date_selected = $p_date_selected;
    }

    public function print_html() {
        $t_days_in_month = date('t', strtotime("$this->year-$this->month-01"));
        // Get the weekday (0 = Sunday, 1 = Monday, ..., 6 = Saturday)
        $t_first_weekday = date('w', strtotime("$this->year-$this->month-01"));
        // Convert to the format where Monday = 0, Tuesday = 1, ..., Sunday = 6
        $t_first_day = ($t_first_weekday == 0) ? 6 : $t_first_weekday - 1;

        echo '<div class="col-md-12 col-xs-12">';
        echo '<div class="space-10"></div>';
        echo '<div class="widget-box widget-color-blue2">';
        echo '<div class="widget-header widget-header-small">';
        echo '<h4 class="widget-title lighter">';
        echo '<i class="ace-icon fa fa-calendar"></i>';
        echo plugin_lang_get(strtolower(date('F', strtotime("$this->year-$this->month-01")))) . ' ' . $this->year;
        echo '</h4>';
        
        $this->print_navigation();
        
        echo '</div>';
        echo '<div class="widget-body">';
        
        $this->print_menu_top();
        
        echo '<div class="widget-main no-padding">';
        echo '<div class="table-responsive">';
        echo '<table class="table table-bordered table-condensed table-striped">';
        
        $this->print_header();
        $this->print_calendar_body($t_days_in_month, $t_first_day);
        
        echo '</table>';
        echo '</div>';
        
        $this->print_menu_bottom();
        
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // Add the modal window used to view events
        echo '<div id="eventModal" class="modal" style="display: none;"
              data-events-for-date-text="' . string_attribute( plugin_lang_get( "events_for_date" ) ) . '"
              data-add-event-text="' . string_attribute( plugin_lang_get( "add_new_event" ) ) . '">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="close">&times;</span>
                    <h2 id="modalTitle"></h2>
                </div>
                <div class="modal-body">
                    <div id="modalEventList"></div>
                    <div class="modal-create-event">
                        <button id="create-event-btn" type="button" class="btn btn-primary btn-white btn-round">
                            <i class="ace-icon fa fa-plus"></i> ' . plugin_lang_get("add_new_event") . '
                        </button>
                    </div>
                    <div id="createEventForm" style="display: none;"
                         data-msg-no-name="' . string_attribute( plugin_lang_get( "month_view_enter_event_name" ) ) . '"
                         data-msg-no-time="' . string_attribute( plugin_lang_get( "month_view_enter_event_time" ) ) . '">
                        <hr>
                        <div class="form-group">
                            <label>' . plugin_lang_get("date_event") . ': <span id="selectedDate" class="form-control-static"></span></label>
                        </div>
                        <div class="form-group">
                            <label for="eventName">' . plugin_lang_get("name_event") . '</label>
                            <input type="text" id="eventName" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="eventTimeStart">' . plugin_lang_get("from_time") . '</label>
                            <select id="eventTimeStart" class="form-control">';
        print_time_select_option( null, true );
        echo '</select>
                        </div>
                        <div class="form-group">
                            <label for="eventTimeEnd">' . plugin_lang_get("to_time") . '</label>
                            <select id="eventTimeEnd" class="form-control">';
        print_time_select_option( null, true );
        echo '</select>
                        </div>
                        <button type="button" id="createEventBtn" class="btn btn-primary btn-white btn-round">' . 
                            plugin_lang_get("add_button") . 
                        '</button>
                    </div>
                </div>
            </div>
        </div>';
    }

    private function print_navigation() {
        echo '<div class="widget-toolbar">';
        echo '</div>';

        print_rsvp_pending_button();
    }

    private function print_header() {
        echo '<thead>';
        echo '<tr>';
        echo '<th style="border: none; background: none;"></th>';
        echo '<th style="text-align: center;">' . plugin_lang_get('Mon') . '</th>';
        echo '<th style="text-align: center;">' . plugin_lang_get('Tue') . '</th>';
        echo '<th style="text-align: center;">' . plugin_lang_get('Wed') . '</th>';
        echo '<th style="text-align: center;">' . plugin_lang_get('Thu') . '</th>';
        echo '<th style="text-align: center;">' . plugin_lang_get('Fri') . '</th>';
        echo '<th style="text-align: center;">' . plugin_lang_get('Sat') . '</th>';
        echo '<th style="text-align: center;">' . plugin_lang_get('Sun') . '</th>';
        echo '</tr>';
        echo '</thead>';
    }

    private function get_event_url($p_event) {
        return $this->link_to_view . '&event_id=' . $p_event['id'] .
               ( isset( $p_event['recurrence_pattern'] ) && !is_blank( $p_event['recurrence_pattern'] ) ? '&date=' . $p_event['date_from'] : '');
    }

    private function print_calendar_body($p_days_in_month, $p_first_day) {
        echo '<tbody>';

        $t_day_count = 1;
        $t_cells = 0;
        $t_total_cells = ceil(($p_first_day + $p_days_in_month) / 7) * 7;

        // Get the dates of the previous month
        $t_prev_month = $this->month == 1 ? 12 : $this->month - 1;
        $t_prev_year = $this->month == 1 ? $this->year - 1 : $this->year;
        $t_days_in_prev_month = date('t', strtotime("$t_prev_year-$t_prev_month-01"));
        $t_start_day_prev_month = $t_days_in_prev_month - $p_first_day + 1;

        // Get the dates of the next month
        $t_next_month = $this->month == 12 ? 1 : $this->month + 1;
        $t_next_year = $this->month == 12 ? $this->year + 1 : $this->year;
        $t_next_day = 1;

        while ($t_cells < $t_total_cells) {
            if ($t_cells < $p_first_day) {
                // Days of the previous month
                $t_date = sprintf('%04d-%02d-%02d', $t_prev_year, $t_prev_month, $t_start_day_prev_month);
                $t_day_number = $t_start_day_prev_month;
                $t_other_month = true;
                $t_start_day_prev_month++;
            } elseif ($t_day_count <= $p_days_in_month) {
                // Days of the current month
                $t_date = sprintf('%04d-%02d-%02d', $this->year, $this->month, $t_day_count);
                $t_day_number = $t_day_count;
                $t_other_month = false;
                $t_day_count++;
            } else {
                // Days of the next month
                $t_date = sprintf('%04d-%02d-%02d', $t_next_year, $t_next_month, $t_next_day);
                $t_day_number = $t_next_day;
                $t_other_month = true;
                $t_next_day++;
            }

            if ($t_cells % 7 == 0) {
                echo '<tr>';
                // Add the week number cell without a leading zero
                echo '<td class="calendar-week-number">' . intval(date('W', strtotime($t_date))) . '</td>';
            }

            $this->print_day_cell($t_date, $t_day_number, $t_other_month);

            $t_cells++;
            if ($t_cells % 7 == 0) {
                echo '</tr>';
            }
        }

        echo '</tbody>';
    }

    private function print_day_cell($p_date, $p_day_number, $p_other_month) {
        $t_slots_per_day = 3;
        $t_is_today = strtotime(date('Y-m-d')) == strtotime($p_date);

        $t_day_events = array();
        if (isset($this->days_events[$p_date])) {
            $t_day_events = $this->days_events[$p_date];
            // an event that started on an earlier day comes first
            usort($t_day_events, function($a, $b) {
                return $a['segment_from'] - $b['segment_from'];
            });
        }

        // Every day header carries its event list (possibly empty) for the modal window.
        // Values stay raw: the script puts them into the DOM as text, never as markup.
        $t_modal_events = array();
        foreach ($t_day_events as $t_event) {
            $t_modal_event = array(
                'time' => calendar_event_segment_time_label($t_event),
                'duration' => calendar_event_duration_label($t_event['date_from'], $t_event['duration']),
                'name' => $t_event['name'],
                'url' => $this->get_event_url($t_event),
                'project_name' => project_get_name($t_event['project_id']),
                'style' => calendar_project_color_style($t_event['project_id']) . calendar_rsvp_pending_style($t_event['id'])
            );
            // Show whose event it is unless the grid is already one user's
            if ($this->user_id == ALL_USERS || $this->user_id == CALENDAR_FILTER_AUTHOR) {
                $t_member_names = array();
                foreach (event_get_members($t_event['id']) as $t_member_id) {
                    $t_member_names[] = user_get_name($t_member_id);
                }
                $t_modal_event['user_name'] = implode(', ', $t_member_names);
            }
            $t_modal_events[] = $t_modal_event;
        }
        // The JSON_HEX_* flags leave no HTML special characters in the JSON, so the
        // single attribute escaping below is the only one the browser has to undo
        $t_events_json = json_encode( $t_modal_events,
                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE );
        $t_events_attr = ' data-events="' . string_attribute( $t_events_json ) . '"';
        // Empty slots open the same modal window, so they carry the event list as well
        $t_empty_slot = '<div class="calendar-event-empty clickable" data-date="' . $p_date . '"' . $t_events_attr . '></div>';

        echo '<td class="calendar-cell' .
             ($p_other_month ? ' other-month' : '') .
             ($t_is_today ? ' calendar-today' : '') .
             '" data-date="' . $p_date . '">';
        echo '<div class="calendar-day-header clickable" data-date="' . $p_date . '"' . $t_events_attr . '>';
        echo '<div class="calendar-day-number">' . $p_day_number . '</div>';
        echo '</div>';

        // Print the first events that fit into the cell
        for ($i = 0; $i < $t_slots_per_day; $i++) {
            if (isset($t_day_events[$i])) {
                $t_event = $t_day_events[$i];

                echo '<div class="calendar-event' .
                     (calendar_event_is_in_past($t_event['date_from'], $t_event['duration']) ? ' calendar-event-expired' : '') .
                     '" style="' . calendar_project_color_style($t_event['project_id']) . calendar_rsvp_pending_style($t_event['id']) . '">';
                echo '<a href="' . $this->get_event_url($t_event) . '">';
                echo '<span class="event-time">' . calendar_event_segment_time_label($t_event) . '</span> ';
                echo '<span class="event-duration">(' . calendar_event_duration_label($t_event['date_from'], $t_event['duration']) . ')</span> ';
                echo string_html_specialchars($t_event['name']);
                echo '</a>';
                echo '</div>';
            } else {
                echo $t_empty_slot;
            }
        }

        // If there are more events, show the indicator for them
        $t_remaining_events = count($t_day_events) - $t_slots_per_day;
        if ($t_remaining_events > 0) {
            echo '<div class="calendar-event calendar-more-events" data-date="' .
                 date('d.m.Y', strtotime($p_date)) . '"' . $t_events_attr . '>';
            echo '<i class="ace-icon fa fa-plus-circle">';
            echo sprintf(plugin_lang_get('more_events'), $t_remaining_events);
            echo '</i>';
            echo '</div>';
        } else {
            echo $t_empty_slot;
        }

        echo '</td>';
    }

    protected function print_menu_top() {
        echo '<div class="widget-toolbox padding-8 clearfix">';      
        echo '<div class="btn-toolbar">';
        
        # Switch to the week view button
        echo '<div class="btn-group">';
        $t_url = plugin_page('calendar_user_page') . 
                '&view=week' . 
                '&week=' . date('W') . 
                '&year=' . date('Y');
        if (!is_bool($this->date_selected)) {
            $t_url .= '&date_select=' . date(plugin_config_get('short_date_format'), $this->date_selected);
        }
        if ($this->user_id != auth_get_current_user_id()) {
            $t_url .= '&for_user=' . $this->user_id;
        }
        print_small_button($t_url, plugin_lang_get('week_view'));
        echo '</div>';
        

        # Navigation block
        echo '<div id="nav-button" class="btn-group pull-right calendar-nav-period">';

        # Date selection block
        echo '<form id="select_date_form" method="post" action="' . plugin_page('calendar_user_page') . '" class="form-inline pull-left padding-left-8">';
        echo '<input type="hidden" name="view" value="month">';
        
//        if (!is_bool($this->date_selected)) {
//            $t_date_to_display = date(plugin_config_get('month_date_format'), $this->date_selected);
//        } else {
//            $t_date_to_display = plugin_lang_get(strtolower(date('F', strtotime("$this->year-$this->month-01")))) . ' ' . $this->year;
        $t_date_to_display = date(plugin_config_get('month_date_format'), strtotime("$this->year-$this->month-01"));
//        }
        
        echo '<input type="text" id="view_month_date_select" name="date_select" class="datetimepicker input-sm" ' .
             'data-picker-locale="' . lang_get_current_datetime_locale() . '" ' .
             'data-picker-format="' . plugin_config_get('datetime_picker_month_date_format') . '" ' .
             'size="10" maxlength="10" autocomplete="off" ' .
             'value="' . $t_date_to_display . '" ' .
             '/>';

        echo '</form>';
        
        # Navigation buttons
        print_small_button( plugin_page('calendar_user_page') . '&view=month&month=' . ($this->month == 1 ? 12 : $this->month - 1) . '&year=' . ($this->month == 1 ? $this->year - 1 : $this->year), '<<' );
        print_link_button( plugin_page('calendar_user_page') . '&view=month&month=' . date('m') . '&year=' . date('Y'), plugin_lang_get( 'current_period' ), 'btn-sm calendar-nav-current' );
        print_small_button( plugin_page('calendar_user_page') . '&view=month&month=' . ($this->month == 12 ? 1 : $this->month + 1) . '&year=' . ($this->month == 12 ? $this->year + 1 : $this->year), '>>' );
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }

    protected function print_menu_bottom() {
        echo '<div class="widget-toolbox padding-8 clearfix">';
        echo '<div class="btn-toolbar calendar-toolbar-bottom">';

        echo '<div class="btn-group pull-left">';
        if( access_compare_level( access_get_project_level(), plugin_config_get( 'report_event_threshold' ) ) ) {
            print_small_button( plugin_page( 'event_add_page' ), plugin_lang_get( 'add_new_event' ) );
        }
        echo '</div>';

        $t_project_ids = array();
        foreach( $this->days_events as $t_day_events ) {
            $t_project_ids = array_merge( $t_project_ids, array_column( $t_day_events, 'project_id' ) );
        }
        print_project_legend( $t_project_ids );

        echo '<div id="nav-button" class="btn-group pull-right">';

        print_event_jump_form();

        echo '<form id="filter-queries-form" class="btn-toolbar"  method="get" name="list_queries" action="' . plugin_page( 'calendar_user_page' ) . '">';
        echo '<input type="hidden" name="page" value="Calendar/calendar_user_page" />';
        echo '<input type="hidden" name="view" value="month" />';
        echo '<input type="hidden" name="month" value="' . $this->month . '" />';
        echo '<input type="hidden" name="year" value="' . $this->year . '" />';

        echo '<label class="inline"></label>';
        echo '<select name="for_user">';
        print_for_user_option_list( $this->user_id );

        echo '</select>';
        echo '</form>';

        echo '</div>';

        echo '</div>';
//        echo '</div>';
    }
} 