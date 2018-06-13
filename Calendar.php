<?php
# Copyright (c) 2018 Grigoriy Ermolaev (igflocal@gmail.com)
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

class CalendarPlugin extends MantisPlugin {

    function register() {

        $this->name        = plugin_lang_get( 'name_plugin_description_page' );
        $this->description = plugin_lang_get( 'description' );
        $this->page        = 'config';

        $this->version  = '2.3.0-dev';

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
        );
    }

    function config() {
        return array(
                                  'datetime_picker_format'               => 'DD-MM-Y',
                                  'short_date_format'                    => 'd-m-Y',
                                  'event_time_start_stop_picker_format'  => 'HH:mm',
                                  'startStepDays'                        => 0,
                                  'countStepDays'                        => 7,
                                  'arWeekdaysName'                       => array( 'Mon' => ON,
                                                            'Tue' => ON,
                                                            'Wed' => ON,
                                                            'Thu' => ON,
                                                            'Fri' => ON,
                                                            'Sat' => ON,
                                                            'Sun' => ON ),
                                  'time_day_start'                       => 32400,
                                  'time_day_finish'                      => 64800,
                                  'stepDayMinutesCount'                  => 2,
                                  'manage_calendar_threshold'            => DEVELOPER,
                                  'calendar_view_threshold'              => DEVELOPER,
                                  'calendar_edit_threshold'              => DEVELOPER,
                                  'report_event_threshold'               => DEVELOPER,
                                  'update_event_threshold'               => DEVELOPER,
                                  'show_member_list_threshold'           => REPORTER,
                                  'member_add_others_event_threshold'    => DEVELOPER,
                                  'member_event_threshold'               => DEVELOPER, //The level of access necessary to become a member of the event.
                                  'member_delete_others_event_threshold' => MANAGER, //Access level needed to delete other users from the list of users member a event.
                                  'oauth_key'                            => '',
        );
    }

    function init() {
        require_once 'core/event_data_api.php';
        require_once 'core/calendar_date_api.php';
        require_once 'core/calendar_access_api.php';
        require_once 'core/calendar_print_api.php';
        require_once 'core/Columns_api.php';
        require_once 'core/calendar_user_api.php';
        require_once 'api/google-api-php-client-2.2.1/vendor/autoload.php';
        require_once 'core/calendar_form_api.php';

        define( 'ERROR_EVENT_NOT_FOUND', 'ERROR_EVENT_NOT_FOUND' );
        define( 'ERROR_DATE', 'ERROR_DATE' );
        define( 'ERROR_RANGE_TIME', 'ERROR_RANGE_TIME' );
        define( 'ERROR_MIN_MEMBERS', 'ERROR_MIN_MEMBERS' );
    }

    function hooks() {
        $hooks = array(
                                  "EVENT_LAYOUT_RESOURCES" => "resources",
                                  'EVENT_MENU_MAIN_FRONT'  => 'menu_main_front',
                                  'EVENT_VIEW_BUG_DETAILS' => 'html_print_calendar',
        );
        return $hooks;
    }

    function resources() {
        return '<link rel="stylesheet" type="text/css" href="' . plugin_file( 'Calendar.css' ) . '"></link>'
                . '<script type="text/javascript" src="' . plugin_file( 'calendar_filter.js' ) . '"></script>';
    }

    function menu_main_front() {
        if( access_has_project_level( plugin_config_get( 'manage_calendar_threshold' ) ) ) {
            return array(
                                      array(
                                                                'url'          => plugin_page( 'calendar_user_page' ),
                                                                'title'        => plugin_lang_get( 'menu_main_front' ),
                                                                'access_level' => DEVELOPER,
                                                                'icon'         => 'fa-random'
                                      ),
            );
        }
    }

    function html_print_calendar( $p_first_option, $p_bug_id ) {
        $t_events_id      = get_events_id_from_bug_id( $p_bug_id );
        $t_days_events    = get_dates_event_from_events_id( $t_events_id );
        ?>


        <tr class=calendar-area align="center">
            <td class=calendar-area-in-bugs align="center" colspan="6">

                <div class="col-md-12 col-xs-12">
                    <?php
                    $t_collapse_block = count( $t_days_events ) == 0 ? TRUE : FALSE;
                    $t_block_css      = $t_collapse_block ? 'collapsed' : '';
//                    $t_block_icon     = $t_collapse_block ? 'fa-chevron-down' : 'fa-chevron-up';
                    ?>
                    <div class="widget-box widget-color-blue2 <?php echo $t_block_css ?>">
                        <div class="widget-header widget-header-small">
                            <h4 class="widget-title lighter">
                                <i class="ace-icon fa fa-list-alt"></i>
                                <?php
                                if( $t_collapse_block ) {
                                    echo plugin_lang_get( 'not_assigned_event' );
                                } else {
                                    echo plugin_lang_get( 'assigned_event' );
                                }
                                ?>
                            </h4>
                        </div>
                        <div class="widget-body">


                            <div class="widget-main no-padding">

                                <div class="table-responsive">
                                    <table class="calendar-user week">
                                        <tr class="row-day">
                                            <?php print_column_time(); ?>
                                            <?php
                                            foreach( $t_days_events as $t_day_and_events => $t_events ) {
                                                print_column_this_day( $t_day_and_events, $t_events, count( $t_days_events ) );
                                            }
                                            ?>
                                    </table>
                                </div>

                            </div>
                        </div>
                    </div>
                    <?php
                    $t_access_level_current_user = access_get_global_level( auth_get_current_user_id() );

                    if( access_compare_level( $t_access_level_current_user, plugin_config_get( 'calendar_edit_threshold' ) ) ) {
                        ?>
                        <div class="widget-toolbox padding-8 clearfix">
                            <div class="form-inline pull-left">
                                <?php
//                                print_small_button( plugin_page( 'calendar_event_insert_page' ) . "&bug_id=" . $p_bug_id, plugin_lang_get( 'insert_event' ) );
                                print_small_button( plugin_page( 'event_add_page' ) . "&bug_id=" . $p_bug_id, plugin_lang_get( 'add_new_event' ) );
                                ?>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </td>
        </tr>

        <?php
    }

}
