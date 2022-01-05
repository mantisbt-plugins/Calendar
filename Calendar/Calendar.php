<?php
# Copyright (c) 2019 Grigoriy Ermolaev (igflocal@gmail.com)
# 
# Calendar for MantisBT is free software: 
# you can redistribute it and/or modify it under the terms of the GNU
# General Public License as published by the Free Software Foundation, 
# either version 2 of the License, or (at your option) any later version.
#
# Calendar plugin for for MantisBT is distributed in the hope 
# that it will be useful, but WITHOUT ANY WARRANTY; without even the 
# implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
# See the GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Customer management plugin for MantisBT.  
# If not, see <http://www.gnu.org/licenses/>.

function install_date_from_date_to() { //version 0.9 (schema 3)
    $p_table_calendar_events = plugin_table( 'events' );

    if( db_table_exists( $p_table_calendar_events ) && db_is_connected() ) {
        $t_events_id = array();

        $t_query = "SELECT id FROM " . $p_table_calendar_events;
        $arRes   = db_query( $t_query, NULL, -1, -1 );

        foreach( $arRes as $key => $t_event_id ) {

            $t_query      = "SELECT date_event FROM " . $p_table_calendar_events . " WHERE id=" . db_param();
            $t_result     = db_query( $t_query, array( $t_event_id[id] ) );
            $t_date_event = db_fetch_array( $t_result );

            $t_query = "SELECT date_event, hour_start, minutes_start, hour_finish, minutes_finish FROM " . $p_table_calendar_events . " WHERE id=" . db_param();
            $arRes   = db_query( $t_query, array( $t_event_id[id] ) );

            $t_date_and_time_event = db_fetch_array( $arRes );

            $t_time_start  = strtotime( $t_date_and_time_event['hour_start'] . ":" . $t_date_and_time_event['minutes_start'], 0 );
            $t_time_finish = strtotime( $t_date_and_time_event['hour_finish'] . ":" . $t_date_and_time_event['minutes_finish'], 0 );

            $t_date_from = $t_date_and_time_event['date_event'] + $t_time_start + 25200;
            $t_date_to   = $t_date_and_time_event['date_event'] + $t_time_finish + 25200;

            $query = "UPDATE $p_table_calendar_events
                                                SET date_from=" . db_param() . ', date_to=' . db_param() . " WHERE id=" . db_param();


            db_query( $query, Array( $t_date_from, $t_date_to, $t_event_id[id] ) );
        }
    }
    return TRUE;
}

function install_turn_user_owner_to_user_member() { //version 2.2.0 (schema 7)
    $p_table_calendar_events       = plugin_table( 'events' );
    $p_table_calendar_event_member = plugin_table( 'event_member' );

    if( db_table_exists( $p_table_calendar_events ) && db_table_exists( $p_table_calendar_event_member ) && db_is_connected() ) {
        $t_events_id = array();

        $t_query = "SELECT id, author_id FROM " . $p_table_calendar_events;
        $arRes   = db_query( $t_query, NULL, -1, -1 );

        foreach( $arRes as $key => $t_event_id ) {

            $query = "INSERT INTO $p_table_calendar_event_member
                                                ( user_id, event_id
                                                )
                                              VALUES
                                                ( " . db_param() . ',' . db_param() . ')';

            db_query( $query, Array( $t_event_id['author_id'], $t_event_id['id'] ) );
        }
    }
    return TRUE;
}

function install_calculate_duration() { //version 2.4.0 (schema 12)
    $t_table_calendar_events = plugin_table( 'events' );

    if( db_table_exists( $t_table_calendar_events ) && db_is_connected() ) {

        $t_query = "SELECT id, date_from, date_to FROM " . $t_table_calendar_events;
        $arRes   = db_query( $t_query, NULL, -1, -1 );

        foreach( $arRes as $key => $t_event ) {

            $t_calcalate_duration = $t_event['date_to'] - $t_event['date_from'];

            $query = "UPDATE $t_table_calendar_events
                                            SET duration=" . db_param();

            $t_fields = Array(
                                      $t_calcalate_duration,
            );

            $query .= " WHERE id=" . db_param();

            $t_fields[] = $t_event['id'];

            db_query( $query, $t_fields );
        }
    }
    return TRUE;
}

function install_recurrence_pattern_set_notnull() { //version 2.4.8 (schema 14)
    $t_table_calendar_events = plugin_table( 'events' );

    if( db_table_exists( $t_table_calendar_events ) && db_is_connected() ) {

        $t_query = "SELECT id, recurrence_pattern FROM " . $t_table_calendar_events;
        $arRes   = db_query( $t_query, NULL, -1, -1 );

        foreach( $arRes as $key => $t_event ) {

            if( $t_event['recurrence_pattern'] !== NULL ) {
                continue;
            }

            $query = "UPDATE $t_table_calendar_events
                                            SET recurrence_pattern=''";
            $query .= " WHERE id=" . db_param();

            $t_fields = Array( $t_event['id'] );

            db_query( $query, $t_fields );
        }
    }
    return TRUE;
}

class CalendarPlugin extends MantisPlugin {

    function register() {

        $this->name        = plugin_lang_get( 'name_plugin_description_page' );
        $this->description = plugin_lang_get( 'description' );
        $this->page        = 'config_page';

        $this->version = '2.6.1';

        $this->requires = array(
                                  'MantisCore' => '2.14.0',
        );

        $this->author  = 'Grigoriy Ermolaev';
        $this->contact = 'igflocal@gmail.com';
        $this->url     = 'http://github.com/mantisbt-plugins/calendar';
    }

    function schema() {

        return array(
                                  // version 0.0.1(schema 0)
                                  array( "CreateTableSQL", array( plugin_table( "events" ), "
					id INT(10) NOTNULL AUTOINCREMENT PRIMARY,
                                        project_id INT(10) NOTNULL,
                                        name VARCHAR(255) NOTNULL,
                                        tasks VARCHAR(2000) NOTNULL,
                                        date_event INT(10) UNSIGNED NOTNULL DEFAULT 1,
                                        hour_start INT(2) NOTNULL,
                                        minutes_start INT(2) NOTNULL,
                                        hour_finish INT(2) NOTNULL,
                                        minutes_finish INT(2) NOTNULL,
                                        activity CHAR(1) NOTNULL,
                                        author_id INT(10),
                                        date_changed INT(2),
                                        changed_user_id INT(10)
				" ) ),
                                  //version 0.0.1(schema 1)
                                  array( "CreateTableSQL", array( plugin_table( "relationship" ), "
                                        event_id INT(10) NOTNULL,
                                        bug_id INT(10) NOTNULL)
                                " ) ),
                                  //version 0.9(schema 2)
                                  array( 'AddColumnSQL', array( plugin_table( "events" ), "
                                        date_from INT(10) UNSIGNED NOTNULL DEFAULT 1,
                                        date_to INT(10) UNSIGNED NOTNULL DEFAULT 1
                                " ) ),
                                  //version 0.9(schema 3)
                                  array( 'UpdateFunction', 'date_from_date_to' ),
                                  //version 0.9(schema 4)
                                  array( 'DropColumnSQL', array( plugin_table( "events" ), "
                                        tasks,
                                        date_event,
                                        hour_start,
                                        minutes_start,
                                        hour_finish,
                                        minutes_finish
                                " ) ),
                                  //version 2.2.0 (schema 5)
                                  array( "CreateTableSQL", array( plugin_table( "event_member" ), "
                                        user_id INT(10) UNSIGNED NOTNULL PRIMARY DEFAULT '0',
                                        event_id INT(10) UNSIGNED NOTNULL PRIMARY DEFAULT '0')
                                " ) ),
                                  //version 2.2.0 (schema 6)
                                  array( 'CreateIndexSQL', array( 'idx_event_id', plugin_table( "event_member" ), "
                                      event_id
                                      " ) ),
                                  //version 2.2.0 (schema 7)
                                  array( 'UpdateFunction', 'turn_user_owner_to_user_member' ),
                                  //version 2.3.0 (schema 8)
                                  array( "CreateTableSQL", array( plugin_table( "google_sync" ), "
                                      event_id INT(10) UNSIGNED NOTNULL PRIMARY DEFAULT '0',                                        
                                      google_id VARCHAR(255) NOTNULL
                                " ) ),
                                  //version 2.3.0 (schema 9)
                                  array( 'CreateIndexSQL', array( 'idx_event_id', plugin_table( "google_sync" ), "
                                      event_id
                                      " ) ),
                                  //version 2.3.1 (schema 10)
                                  array( 'AddColumnSQL', array( plugin_table( "google_sync" ), "
                                        last_sync INT(10) UNSIGNED NOTNULL DEFAULT 0
                                " ) ),
                                  //version 2.4.0 (schema 11)
                                  array( 'AddColumnSQL', array( plugin_table( "events" ), "
                                        duration INT(10) UNSIGNED NOTNULL,
                                        recurrence_pattern VARCHAR(255) DEFAULT NULL,
                                        parent_id INT(10) NOTNULL
                                " ) ),
                                  //version 2.4.0 (schema 12)
                                  array( 'UpdateFunction', 'calculate_duration' ),
                                  //version 2.4.7 (schema 13)
                                  array( 'ChangeTableSQL', array( plugin_table( "events" ), "
                                        recurrence_pattern MEDIUMTEXT
                                " ) ),
                                  //version 2.4.8 (schema 14)
                                  array( 'UpdateFunction', 'recurrence_pattern_set_notnull' ),
                                  //version 2.4.8 (schema 15)
                                  array( 'ChangeTableSQL', array( plugin_table( "events" ), "
                                        recurrence_pattern MEDIUMTEXT NOTNULL
                                " ) ),
        );
    }

    function config() {
        return array( //Default settings. Some of the settings are available for override via the plugin configuration page.
                                  //Time settings
                                  'datetime_picker_format'                              => 'DD-MM-Y',
                                  'short_date_format'                                   => 'd-m-Y',
                                  'event_time_start_stop_picker_format'                 => 'HH:mm',
                                  'startStepDays'                                       => 0,
//                                  'startStepDays'                        => date( 'w' )-1,
                                  'countStepDays'                                       => 7,
                                  'show_count_future_recurring_events_in_bug_view_page' => 1,
                                  'arWeekdaysName'                                      => array(
                                                                                            'Mon' => ON,
                                                                                            'Tue' => ON,
                                                                                            'Wed' => ON,
                                                                                            'Thu' => ON,
                                                                                            'Fri' => ON,
                                                                                            'Sat' => ON,
                                                                                            'Sun' => ON 
                                                                                            ),
                                  'time_day_start'                                      => 32400,
                                  'time_day_finish'                                     => 64800,
                                  'stepDayMinutesCount'                                 => 2,
                                  'frequencies'                                         => array(
                                                                                            'NO_REPEAT',
                                                                                            'DAILY',
                                                                                            'WEEKLY',
                                                                                            'MONTHLY',
                                                                                            'YEARLY'
                                                                                            ),
                                  //Calendar access rights.
                                  'manage_calendar_threshold'                           => DEVELOPER,
                                  'calendar_view_threshold'                             => DEVELOPER,
                                  'bug_calendar_view_threshold'                         => REPORTER,
//                                  'calendar_edit_threshold'              => DEVELOPER,
                                  //Event access rights.
                                  'view_event_threshold'                                => REPORTER,
                                  'report_event_threshold'                              => DEVELOPER,
                                  'update_event_threshold'                              => DEVELOPER,
                                  //Member event access rights. 
                                  'show_member_list_threshold'                          => REPORTER,
                                  'member_event_threshold'                              => DEVELOPER, //The level of access necessary to become a member of the event.
                                  'member_add_others_event_threshold'                   => DEVELOPER,
                                  'member_delete_others_event_threshold'                => DEVELOPER, //Access level needed to delete other users from the list of users member a event.
                                  //Google settings
                                  'oauth_key'                                           => array(),
                                  'google_calendar_sync_id'                             => '',
                                  'google_client_secret'                                => '',
        );
    }

    function init() {
        require_once 'api/php-rrule-1.6.1/src/RRuleInterface.php';
        require_once 'api/php-rrule-1.6.1/src/RRule.php';
        require_once 'api/php-rrule-1.6.1/src/RSet.php';
        require_once 'api/php-rrule-1.6.1/src/RfcParser.php';
        require_once 'core/calendar_event_data_api.php';
        require_once 'core/calendar_date_api.php';
        require_once 'core/calendar_access_api.php';
        require_once 'core/calendar_print_api.php';
        require_once 'core/calendar_helper_api.php';
        require_once 'core/calendar_columns_api.php';
        require_once 'core/calendar_user_api.php';
        require_once 'api/google-api-php-client-2.2.1/vendor/autoload.php';
        require_once 'core/calendar_form_api.php';
        require_once 'core/calendar_google_api.php';
        require_once 'core/calendar_menu_api.php';
        require_once 'core/classes/WeekCalendar.class.php';
        require_once 'core/classes/ViewWeekCalendar.class.php';
        require_once 'core/classes/ViewIssue.class.php';
        require_once 'core/classes/ViewWeekSelect.class.php';
        require_once 'core/classes/ColumnForm.class.php';
        require_once 'core/classes/TimeColumn.class.php';
        require_once 'core/classes/DayColumn.class.php';
        require_once 'core/classes/EventArea.class.php';
        require_once 'core/classes/ColumnViewIssuePage.class.php';

        global $g_calendar_show_menu_bottom;
        $g_calendar_show_menu_bottom = TRUE;
    }

    function errors() {
        return array(
                                  'ERROR_EVENT_NOT_FOUND'             => plugin_lang_get( 'ERROR_EVENT_NOT_FOUND' ),
                                  'ERROR_DATE'                        => plugin_lang_get( 'ERROR_DATE' ),
                                  'ERROR_RANGE_TIME'                  => plugin_lang_get( 'ERROR_RANGE_TIME' ),
                                  'ERROR_MIN_MEMBERS'                 => plugin_lang_get( 'ERROR_MIN_MEMBERS' ),
                                  'ERROR_EVENT_TIME_PERIOD_NOT_FOUND' => plugin_lang_get( 'ERROR_EVENT_TIME_PERIOD_NOT_FOUND' ),
        );
    }

    function hooks() {
        return array(
                                  'EVENT_LAYOUT_RESOURCES' => 'resources',
                                  'EVENT_MENU_MAIN_FRONT'  => 'menu_main_front',
                                  'EVENT_VIEW_BUG_DETAILS' => 'html_print_calendar',
                                  'EVENT_FILTER_COLUMNS'    => 'column_add_in_view_all_bug_page',
                                  'EVENT_DISPLAY_TEXT'      => 'column_title_formating',
        );
    }

    function resources() {
        return '<link rel="stylesheet" type="text/css" href="' . plugin_file( 'Calendar_1553843497.css' ) . '"></link>'
                . '<script type="text/javascript" src="' . plugin_file( 'calendar_filter.js' ) . '"></script>';
    }

    function menu_main_front() {
        return array(
                                  array(
                                                            'url'          => plugin_page( 'calendar_user_page' ),
                                                            'title'        => plugin_lang_get( 'menu_main_front' ),
                                                            'access_level' => plugin_config_get( 'calendar_view_threshold' ),
                                                            'icon'         => 'fa-random'
                                  ),
        );
    }

    function html_print_calendar( $p_first_option, $p_bug_id ) {

        if( access_has_project_level( plugin_config_get( 'bug_calendar_view_threshold' ) ) ) {

            echo '<tr class=calendar-area align="center">';
            echo '<td class=calendar-area-in-bugs align="center" colspan="6">';

            $t_events_id = get_events_id_from_bug_id( $p_bug_id );
            $t_dates     = calendar_column_objects_get_from_event_ids( $t_events_id );

            $t_calendar_issue_view = new ViewIssue( $t_dates, $p_bug_id );
            $t_calendar_issue_view->print_html();

            echo '</td>';
            echo '</tr>';
        }
    }
    
    function column_add_in_view_all_bug_page( $p_type_event, $p_param ){
      
        $t_column = new ColumnViewIssuePage();
        
        return array( $t_column );
    }
    
    function column_title_formating( $p_type_event, $p_param ) {

        if( $p_param == plugin_lang_get( 'column_view_issue_page_title' ) ) {
            $t_event_count_text      = plugin_lang_get( 'column_view_issue_page_title' );
            return '<i class="fa fa-calendar blue" title="' . $t_event_count_text . '"></i>';
        }
        
        return $p_param;
    }

}
