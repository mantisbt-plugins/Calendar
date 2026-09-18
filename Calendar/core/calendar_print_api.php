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
 * The <option> rows of the for_user filter of the calendar views: the reset
 * entry, all users, the events the current user has created, and then every
 * user of the project who can report events
 * @param integer $p_for_user The selected value: a user id, ALL_USERS or CALENDAR_FILTER_AUTHOR.
 * @return void
 */
function print_for_user_option_list( $p_for_user ) {
    echo '<option value="' . auth_get_current_user_id() . '">[' . lang_get( 'reset_query' ) . ']</option>';
    foreach( array( ALL_USERS => 'select_all_users', CALENDAR_FILTER_AUTHOR => 'select_author_is_me' ) as $t_value => $t_lang_key ) {
        echo '<option value="' . $t_value . '"' . ( $p_for_user == $t_value ? ' selected="selected"' : '' ) . '>[' . plugin_lang_get( $t_lang_key ) . ']</option>';
    }
    # The core appends a "deleted user" row for an id it cannot find and
    # marks it selected, so the author sentinel must not reach it
    $t_selected_user = $p_for_user == CALENDAR_FILTER_AUTHOR ? NO_USER : $p_for_user;
    print_user_option_list( $t_selected_user, helper_get_current_project(), plugin_config_get( 'report_event_threshold' ) );
}

/**
 * Timezone <option> list grouped by continent, like the core
 * print_timezone_option_list(), but with the UTC offset in effect at
 * $p_timestamp appended to every label, e.g. "Moscow (UTC+03:00)".
 *
 * @param string   $p_selected  timezone identifier to preselect
 * @param int|null $p_timestamp moment to take the offset at, defaults to now
 */
function print_timezone_offset_option_list( $p_selected, $p_timestamp = NULL ) {
    $t_moment    = new DateTime( '@' . ( $p_timestamp === NULL ? time() : (int)$p_timestamp ) );
    $t_locations = array();

    foreach( timezone_identifiers_list( DateTimeZone::ALL ) as $t_identifier ) {
        $t_zone   = explode( '/', $t_identifier, 2 );
        $t_offset = $t_moment->setTimezone( new DateTimeZone( $t_identifier ) )->format( 'P' );
        $t_label  = str_replace( '_', ' ', isset( $t_zone[1] ) ? $t_zone[1] : $t_identifier ) . ' (UTC' . $t_offset . ')';

        $t_locations[$t_zone[0]][$t_identifier] = $t_label;
    }

    foreach( $t_locations as $t_continent => $t_zones ) {
        echo "\t" . '<optgroup label="' . $t_continent . '">' . "\n";
        foreach( $t_zones as $t_identifier => $t_label ) {
            echo "\t\t" . '<option value="' . $t_identifier . '"';
            check_selected( $p_selected, $t_identifier );
            echo '>' . $t_label . '</option>' . "\n";
        }
        echo "\t" . '</optgroup>' . "\n";
    }
}

function print_time_select_option( $p_selected_time = NULL, $p_full_range = FALSE ) {

    if( $p_full_range == FALSE ) {
        $t_time_day_start_timestamp  = plugin_config_get( 'time_day_start', plugin_config_get( 'time_day_start' ), FALSE, auth_get_current_user_id() );
        $t_time_day_finish_timestamp = plugin_config_get( 'time_day_finish', plugin_config_get( 'time_day_finish' ), FALSE, auth_get_current_user_id() );
    } else {
        $t_time_day_start_timestamp  = 0;
        $t_time_day_finish_timestamp = 86400;
    }

    if( $p_selected_time < $t_time_day_start_timestamp && $p_selected_time !== NULL || $p_selected_time > $t_time_day_finish_timestamp && $p_selected_time !== NULL ) {
        $t_time_day_start_timestamp  = 0;
        $t_time_day_finish_timestamp = 86400;
    }

    $t_time_count          = 3600 / plugin_config_get( 'stepDayMinutesCount' );
    $t_select_time_options = range( $t_time_day_start_timestamp, $t_time_day_finish_timestamp, $t_time_count );

    echo '<option value="--:--">--:--</option>';
    foreach( $t_select_time_options as $key => $t_current_time ) {

        if( $p_selected_time !== $t_current_time ) {
            echo '<option value="' . $t_current_time . '">' . gmdate( "H:i", $t_current_time ) . '</option>';
        } else {
            echo '<option selected value="' . $t_current_time . '">' . gmdate( "H:i", $t_current_time ) . '</option>';
        }
    }
}

/**
 * Print the editor of the reminder offsets, shared by the event pages and by
 * the user settings page. Every label is rendered here, so that the script
 * cloning the rows needs no language strings of its own.
 * @param array   $p_offsets   Offsets in seconds to prefill the rows with.
 * @param boolean $p_show_hint Whether to explain what an empty list means.
 * @return void
 * @access public
 */
function print_event_reminder_rows( array $p_offsets, $p_show_hint = true, $p_show_disable = false ) {

    $t_max_rows = (int)plugin_config_get( 'reminder_max_per_event' );
    $t_disabled = calendar_reminder_offsets_disabled( $p_offsets );

    # switching reminders off is a third state next to "own reminders" and
    # "none set": without it an empty editor always falls back to the personal
    # defaults of the members
    if( $p_show_disable ) {
        echo '<div class="calendar-reminder-disable">';
        echo '<label><input type="checkbox" id="calendar-reminder-disabled" name="reminders_disabled" value="1"'
                . ( $t_disabled ? ' checked="checked"' : '' ) . ' /> '
                . plugin_lang_get( 'reminder_disable_label' ) . '</label>';
        echo '</div>';
        echo '<div class="space-4"></div>';
    }

    echo '<div id="calendar-reminder-rows" data-max-rows="' . $t_max_rows . '">';

    foreach( $p_offsets as $t_offset ) {

        if( (int)$t_offset === CALENDAR_REMINDER_DISABLED ) {
            continue;
        }

        print_event_reminder_row( calendar_reminder_offset_to_input( $t_offset ), FALSE );
    }

    # the hidden last row is the template the script clones; its fields are
    # disabled, so the template itself is never submitted
    print_event_reminder_row( array( 'value' => 10, 'unit' => 'minutes' ), TRUE );

    echo '</div>';

    echo '<button type="button" id="calendar-reminder-add" class="btn btn-sm btn-primary btn-white btn-round">'
            . '<i class="ace-icon fa fa-plus"></i> ' . plugin_lang_get( 'reminder_add_row' ) . '</button>';

    if( $p_show_hint ) {
        echo '<div class="space-4"></div>';
        echo '<span class="small">' . plugin_lang_get( 'reminder_hint_defaults' ) . '</span>';
    }
}

/**
 * Print one row of the reminder editor
 * @param array   $p_input       Row as returned by calendar_reminder_offset_to_input().
 * @param boolean $p_is_template Whether this is the hidden row cloned by the script.
 * @return void
 * @access public
 */
function print_event_reminder_row( array $p_input, $p_is_template ) {

    $t_disabled = $p_is_template ? ' disabled="disabled"' : '';
    $t_class    = $p_is_template ? 'calendar-reminder-row calendar-reminder-template' : 'calendar-reminder-row';
    $t_style    = $p_is_template ? ' style="display: none;"' : '';

    echo '<div class="' . $t_class . '"' . $t_style . '>';

    echo '<input style="width: 70px;" type="number" class="input-sm" name="reminder_value[]" min="1" step="1" value="'
            . (int)$p_input['value'] . '"' . $t_disabled . ' /> ';

    echo '<select class="input-sm" name="reminder_unit[]"' . $t_disabled . '>';
    foreach( array_keys( calendar_reminder_units() ) as $t_unit ) {
        echo '<option value="' . $t_unit . '"' . ( $p_input['unit'] == $t_unit ? ' selected="selected"' : '' ) . '>'
                . plugin_lang_get( 'reminder_unit_' . $t_unit ) . '</option>';
    }
    echo '</select> ';

    echo '<button type="button" class="btn btn-xs btn-danger btn-white btn-round calendar-reminder-remove" title="'
            . plugin_lang_get( 'reminder_remove_row' ) . '"><i class="ace-icon fa fa-trash-o"></i></button>';

    echo '</div>';
}

/**
 * Legend of the project colours: one swatch per project, sorted by name.
 * Every entry switches the current project, the way the navbar menu does;
 * with a project selected, a leading entry leads back to all projects.
 *
 * @param array $p_project_ids Projects that have events in the shown period
 * @return void
 */
function print_project_legend( array $p_project_ids ) {
    $t_names = array();
    foreach( array_unique( $p_project_ids ) as $t_project_id ) {
        $t_names[$t_project_id] = project_get_name( $t_project_id );
    }
    if( count( $t_names ) == 0 ) {
        return;
    }
    natcasesort( $t_names );

    $t_current = helper_get_current_project();
    $t_ref     = '&ref=' . string_url( string_sanitize_url( $_SERVER['REQUEST_URI'] ) );

    echo '<div class="calendar-project-legend pull-left">';
    if( $t_current != ALL_PROJECTS ) {
        echo '<a class="calendar-project-legend-item" href="' . helper_mantis_url( 'set_project.php?project_id=' . ALL_PROJECTS . $t_ref ) . '">'
                . lang_get( 'all_projects' ) . '</a>';
    }
    foreach( $t_names as $t_project_id => $t_name ) {
        echo '<a class="calendar-project-legend-item' . ( $t_project_id == $t_current ? ' active' : '' ) . '"'
                . ' style="' . calendar_project_color_style( $t_project_id ) . '"'
                . ' href="' . helper_mantis_url( 'set_project.php?project_id=' . $t_project_id . $t_ref ) . '">'
                . '<i class="calendar-project-swatch"></i>' . string_display_line( $t_name ) . '</a>';
    }
    echo '</div>';
}
