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
 * entry, all users, the events the current user has created, those waiting
 * for their reply while the replies are on, and then every user of the
 * project who can report events
 * @param integer $p_for_user The selected value: a user id, ALL_USERS, CALENDAR_FILTER_AUTHOR or CALENDAR_FILTER_PENDING.
 * @return void
 */
function print_for_user_option_list( $p_for_user ) {
    echo '<option value="' . auth_get_current_user_id() . '">[' . lang_get( 'reset_query' ) . ']</option>';
    $t_modes = array( ALL_USERS => 'select_all_users', CALENDAR_FILTER_AUTHOR => 'select_author_is_me' );
    if( calendar_rsvp_user_enabled( auth_get_current_user_id() ) ) {
        $t_modes[CALENDAR_FILTER_PENDING] = 'select_pending_reply';
    }
    foreach( $t_modes as $t_value => $t_lang_key ) {
        echo '<option value="' . $t_value . '"' . ( $p_for_user == $t_value ? ' selected="selected"' : '' ) . '>[' . plugin_lang_get( $t_lang_key ) . ']</option>';
    }
    # The core appends a "deleted user" row for an id it cannot find and
    # marks it selected, so the sentinels of the modes must not reach it
    $t_selected_user = $p_for_user < 0 ? NO_USER : $p_for_user;
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
 * Print the reminder block of the event page. Two rows: the reminders of the
 * event itself, as an editor for whoever may update the event, and the
 * reminders that apply to the viewer with where they come from. A recipient
 * of the event - its author or a member - can add an offset to the latter,
 * drop one, or go back to the reminders of the event; every such change is
 * theirs alone, the event keeps its own set.
 * @param integer $p_event_id Integer representing event identifier.
 * @param integer $p_date     Occurrence the page shows, carried by the links.
 * @param integer $p_user_id  The viewer.
 * @return void
 * @access public
 */
function print_event_reminder_block( $p_event_id, $p_date, $p_user_id ) {

    $c_event_id = (int)$p_event_id;
    $c_date     = (int)$p_date;

    $t_is_recipient = calendar_reminder_user_is_recipient( $c_event_id, $p_user_id );
    $t_can_update   = access_has_event_level( plugin_config_get( 'update_event_threshold' ), $c_event_id, $p_user_id );
    $t_sets         = event_reminder_get_all( $c_event_id );
    $t_effective    = calendar_reminder_effective( $c_event_id, $t_is_recipient ? $p_user_id : 0, $t_sets );
    $t_max_rows     = (int)plugin_config_get( 'reminder_max_per_event' );

    if( $t_is_recipient ) {
        $t_source_label = plugin_lang_get( 'reminders_source_' . $t_effective['source'] );
    } else {
        $t_source_label = $t_effective['source'] == 'event'
                ? plugin_lang_get( 'reminders_source_event' )
                : plugin_lang_get( 'view_event_reminders_defaults' );
    }

    $t_collapse_block = is_collapsed( 'reminders' );
    $t_block_css      = $t_collapse_block ? 'collapsed' : '';
    $t_block_icon     = $t_collapse_block ? 'fa-chevron-down' : 'fa-chevron-up';

    echo '<div class="col-md-12 col-xs-12">';
    echo '<a id="reminders"></a>';
    echo '<div class="space-10"></div>';
    echo '<div id="reminders" class="widget-box widget-color-blue2 ' . $t_block_css . '">';
    echo '<div class="widget-header widget-header-small">';
    echo '<h4 class="widget-title lighter"><i class="ace-icon fa fa-bell-o"></i>' . plugin_lang_get( 'reminders_title' ) . '</h4>';
    echo '<div class="widget-toolbar"><a data-action="collapse" href="#"><i class="1 ace-icon fa ' . $t_block_icon . ' bigger-125"></i></a></div>';
    echo '</div>';
    echo '<div class="widget-body"><div class="widget-main no-padding"><div class="table-responsive">';
    echo '<table class="table table-bordered table-condensed table-striped">';

    # the reminders of the event are edited right here, so that changing them
    # does not take the whole event form; they are saved for the event row the
    # page shows, that is for a whole series when it shows one of its occurrences
    if( $t_can_update ) {
        echo '<tr>';
        echo '<th class="category" width="15%">' . plugin_lang_get( 'reminders_source_event' ) . '</th>';
        echo '<td>';
        echo '<form method="post" action="' . plugin_page( 'event_reminder_update' ) . '" class="noprint">';
        echo form_security_field( 'event_reminder_update' );
        echo '<input type="hidden" name="event_id" value="' . $c_event_id . '" />';
        echo '<input type="hidden" name="date" value="' . $c_date . '" />';
        print_event_reminder_rows( isset( $t_sets[0] ) ? $t_sets[0] : array(), TRUE, TRUE );
        echo '<div class="space-4"></div>';
        echo '<input type="submit" class="btn btn-primary btn-sm btn-white btn-round" value="' . plugin_lang_get( 'save_button' ) . '" />';
        echo '</form>';
        echo '</td>';
        echo '</tr>';
    }

    # a viewer who may edit the event but is not reminded about it would see
    # the same set twice
    if( $t_is_recipient || !$t_can_update ) {
        print_event_reminder_personal_row( $c_event_id, $c_date, $p_user_id, $t_is_recipient, $t_sets, $t_effective, $t_source_label, $t_max_rows );
    }

    echo '</table>';
    echo '</div></div></div></div></div>';
}

/**
 * Print the row of the reminder block that shows what applies to the viewer,
 * with the means for a recipient to make it their own
 * @param integer $p_event_id     Integer representing event identifier.
 * @param integer $p_date         Occurrence the page shows, carried by the links.
 * @param integer $p_user_id      The viewer.
 * @param boolean $p_is_recipient Whether the viewer is reminded about the event.
 * @param array   $p_sets         Sets as returned by event_reminder_get_all().
 * @param array   $p_effective    As returned by calendar_reminder_effective() for the viewer.
 * @param string  $p_source_label Heading of the row.
 * @param integer $p_max_rows     Limit of offsets per event.
 * @return void
 * @access private
 */
function print_event_reminder_personal_row( $p_event_id, $p_date, $p_user_id, $p_is_recipient, array $p_sets, array $p_effective, $p_source_label, $p_max_rows ) {

    $c_event_id     = (int)$p_event_id;
    $c_date         = (int)$p_date;
    $t_is_recipient = $p_is_recipient;
    $t_sets         = $p_sets;
    $t_effective    = $p_effective;
    $t_source_label = $p_source_label;
    $t_max_rows     = (int)$p_max_rows;

    echo '<tr>';
    echo '<th class="category" width="15%">' . $t_source_label . '</th>';
    echo '<td>';

    if( $t_effective['held'] !== null ) {
        echo plugin_lang_get( 'reminders_held_' . $t_effective['held'] );
    } elseif( count( $t_effective['offsets'] ) == 0 ) {
        echo plugin_lang_get( 'reminder_offset_disabled' );
    }

    $t_first = true;
    foreach( $t_effective['offsets'] as $t_offset ) {
        echo $t_first ? '' : ', ';
        $t_first = false;
        echo string_display_line( calendar_reminder_format_offset( $t_offset ) );

        if( $t_is_recipient ) {
            echo ' <a class="btn btn-xs btn-primary btn-white btn-round" title="' . plugin_lang_get( 'reminder_remove_for_me' ) . '" href="'
                    . plugin_page( 'event_reminder_delete' ) . '&amp;event_id=' . $c_event_id . '&amp;date=' . $c_date . '&amp;offset=' . (int)$t_offset
                    . htmlspecialchars( form_security_param( 'event_reminder_delete' ) ) . '"><i class="fa fa-times"></i></a>';
        }
    }

    if( $t_is_recipient ) {

        # the set of the event is shown next to a personal one, so that the
        # recipient sees what they diverged from and can go back to it
        if( $t_effective['source'] == 'personal' ) {
            $t_event_offsets = isset( $t_sets[0] ) && !calendar_reminder_offsets_disabled( $t_sets[0] ) ? $t_sets[0] : array();
            $t_event_text    = count( $t_event_offsets ) == 0
                    ? plugin_lang_get( 'reminder_offset_disabled' )
                    : implode( ', ', array_map( 'calendar_reminder_format_offset', $t_event_offsets ) );

            echo '<br /><span class="small">' . sprintf( plugin_lang_get( 'reminders_event_set' ), string_display_line( $t_event_text ) ) . '</span> ';
            echo '<a class="btn btn-xs btn-primary btn-white btn-round" href="'
                    . plugin_page( 'event_reminder_reset' ) . '&amp;event_id=' . $c_event_id . '&amp;date=' . $c_date
                    . htmlspecialchars( form_security_param( 'event_reminder_reset' ) ) . '">' . plugin_lang_get( 'reminders_reset' ) . '</a>';
        }

        if( !calendar_reminder_user_enabled( $p_user_id ) ) {
            echo '<br /><span class="small">' . plugin_lang_get( 'reminders_opted_out' ) . '</span>';
        }

        # a member who declined gets nothing whatever they set, so the form
        # would only mislead
        if( $t_effective['held'] != 'declined' && ( $t_max_rows <= 0 || count( $t_effective['offsets'] ) < $t_max_rows ) ) {
            echo '<br /><br />';
            echo '<form method="post" action="' . plugin_page( 'event_reminder_add' ) . '" class="form-inline noprint">';
            echo form_security_field( 'event_reminder_add' );
            echo '<input type="hidden" name="event_id" value="' . $c_event_id . '" />';
            echo '<input type="hidden" name="date" value="' . $c_date . '" />';
            echo '<input style="width: 70px;" type="number" class="input-sm" name="reminder_value" min="1" step="1" value="10" /> ';
            echo '<select class="input-sm" name="reminder_unit">';
            foreach( array_keys( calendar_reminder_units() ) as $t_unit ) {
                echo '<option value="' . $t_unit . '">' . plugin_lang_get( 'reminder_unit_' . $t_unit ) . '</option>';
            }
            echo '</select> ';
            echo '<input type="submit" class="btn btn-primary btn-sm btn-white btn-round" value="' . plugin_lang_get( 'reminder_add_row' ) . '" />';
            echo '</form>';
        }

        echo '<div class="space-4"></div>';
        echo '<span class="small">' . plugin_lang_get( 'reminders_personal_hint' ) . '</span>';
    }

    echo '</td>';
    echo '</tr>';
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

/**
 * Print the reply of a member as a label next to their name
 * @param integer $p_status One of the CALENDAR_RSVP_* constants.
 * @return void
 * @access public
 */
function print_rsvp_status_label( $p_status ) {

    $t_classes = array(
                              CALENDAR_RSVP_NONE      => 'label-default',
                              CALENDAR_RSVP_ACCEPTED  => 'label-success',
                              CALENDAR_RSVP_TENTATIVE => 'label-warning',
                              CALENDAR_RSVP_DECLINED  => 'label-danger',
    );

    $c_status = (int)$p_status;
    $t_class  = isset( $t_classes[$c_status] ) ? $t_classes[$c_status] : 'label-default';

    echo ' <span class="label ' . $t_class . '">' . string_display_line( calendar_rsvp_status_label( $c_status ) ) . '</span>';
}

/**
 * Print the reply rows of the member table of the event page: how the members
 * replied so far, and - for a viewer who is a member - the buttons to reply
 * with, the current reply drawn filled
 * @param integer $p_event_id Integer representing event identifier.
 * @param integer $p_date     Occurrence the page shows, carried by the links.
 * @param integer $p_user_id  The viewer.
 * @return void
 * @access public
 */
function print_rsvp_rows( $p_event_id, $p_date, $p_user_id ) {

    $c_event_id = (int)$p_event_id;
    $c_date     = (int)$p_date;
    $c_user_id  = (int)$p_user_id;

    $t_statuses = event_member_get_statuses( $c_event_id );
    $t_summary  = calendar_rsvp_summary( $c_event_id, $t_statuses );

    echo '<tr>';
    echo '<th class="category">' . plugin_lang_get( 'rsvp_replies' ) . '</th>';
    echo '<td>' . sprintf( plugin_lang_get( 'rsvp_summary' ),
                           $t_summary[CALENDAR_RSVP_ACCEPTED], $t_summary[CALENDAR_RSVP_TENTATIVE],
                           $t_summary[CALENDAR_RSVP_DECLINED], $t_summary[CALENDAR_RSVP_NONE] ) . '</td>';
    echo '</tr>';

    if( !isset( $t_statuses[$c_user_id] ) || !calendar_rsvp_user_enabled( $c_user_id ) ) {
        return;
    }

    echo '<tr class="noprint">';
    echo '<th class="category">' . plugin_lang_get( 'rsvp_your_reply' ) . '</th>';
    echo '<td>';

    foreach( calendar_rsvp_replies() as $t_reply ) {
        $t_class = $t_statuses[$c_user_id] == $t_reply ? 'btn-primary' : 'btn-primary btn-white';

        echo '<a class="btn btn-xs ' . $t_class . ' btn-round" href="'
                . plugin_page( 'event_member_status' ) . '&amp;event_id=' . $c_event_id . '&amp;date=' . $c_date . '&amp;status=' . (int)$t_reply
                . htmlspecialchars( form_security_param( 'event_member_status' ) ) . '">'
                . plugin_lang_get( 'rsvp_reply_' . calendar_rsvp_status_name( $t_reply ) ) . '</a> ';
    }

    echo '</td>';
    echo '</tr>';
}

/**
 * The button of a calendar header that leads to the invitations waiting for
 * a reply of the logged in user, with their number; nothing while the
 * replies are switched off or for the anonymous user
 * @return void
 * @access public
 */
function print_rsvp_pending_button() {

    if( current_user_is_anonymous() || !calendar_rsvp_user_enabled( auth_get_current_user_id() ) ) {
        return;
    }

    $t_count = count( calendar_rsvp_pending_events( auth_get_current_user_id() ) );

    echo '<div class="widget-toolbar no-border">';
    echo '<div class="widget-menu">';
    echo '<a class="btn btn-primary btn-white btn-round btn-sm" href="' . plugin_page( 'rsvp_pending_page' ) . '">';
    # ace-icon and a compact badge keep the button as low as its neighbours
    echo '<i class="ace-icon fa fa-envelope-o"></i>' . plugin_lang_get( 'rsvp_pending_title' );
    echo ' <span class="badge' . ( $t_count > 0 ? ' badge-warning' : '' ) . '" style="font-size:11px; line-height:1; padding:2px 5px; top:0; vertical-align:1px;">' . $t_count . '</span>';
    echo '</a>';
    echo '</div>';
    echo '</div>';
}
