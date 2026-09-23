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

function install_date_from_date_to() { //version 0.9 (schema 3)
    $p_table_calendar_events = plugin_table( 'events' );

    if( db_table_exists( $p_table_calendar_events ) && db_is_connected() ) {
        $t_events_id = array();

        $t_query = "SELECT id FROM " . $p_table_calendar_events;
        $arRes   = db_query( $t_query, array(), -1, -1 );

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
        $arRes   = db_query( $t_query, array(), -1, -1 );

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
        $arRes   = db_query( $t_query, array(), -1, -1 );

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
        $arRes   = db_query( $t_query, array(), -1, -1 );

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

function install_recurrence_pattern_tzid() { //version 3.0.0 (schema 16)
    $t_table_calendar_events = plugin_table( 'events' );

    if( db_table_exists( $t_table_calendar_events ) && db_is_connected() ) {

        require_once __DIR__ . '/api/vendor/autoload.php';
        require_once __DIR__ . '/core/classes/RSetExt.class.php';

        # Existing patterns store DTSTART as UTC, so occurrences are frozen at a
        # fixed UTC time and their local time shifts on DST transitions (issue #104).
        # Re-anchor DTSTART to the timezone of the event's author — the wall time
        # the author originally entered — keeping the instant unchanged; fall back
        # to the instance timezone for unknown authors or invalid timezone names.
        $t_default_timezone = config_get_global( 'default_timezone' );
        $t_timezones        = array();
        $t_events           = array();

        $t_query = "SELECT id, name, author_id, recurrence_pattern FROM " . $t_table_calendar_events . " WHERE recurrence_pattern <> '' ORDER BY id";
        $arRes   = db_query( $t_query, array(), -1, -1 );

        foreach( $arRes as $key => $t_event ) {

            $t_timezone_name = $t_default_timezone;
            $t_author_id     = (int)$t_event['author_id'];
            if( $t_author_id > 0 && user_exists( $t_author_id ) ) {
                $t_author_timezone = user_pref_get_pref( $t_author_id, 'timezone' );
                if( !is_blank( $t_author_timezone ) ) {
                    $t_timezone_name = $t_author_timezone;
                }
            }
            if( !isset( $t_timezones[$t_timezone_name] ) ) {
                try {
                    $t_timezones[$t_timezone_name] = new DateTimeZone( $t_timezone_name );
                } catch( Exception $e ) {
                    $t_timezones[$t_timezone_name] = new DateTimeZone( $t_default_timezone );
                }
            }
            $t_event['timezone'] = $t_timezones[$t_timezone_name]->getName();
            # after the fallback the resolved name may differ from the requested one
            $t_timezones[$t_event['timezone']] = $t_timezones[$t_timezone_name];
            $t_events[]          = $t_event;
        }

        # Show the administrator exactly what will be converted before running it,
        # and require an explicit "database backup done" checkbox. Modeled on
        # helper_ensure_confirmed(): the form re-posts the same upgrade request
        # with _confirmed=1 (the form security token is only purged after
        # plugin_upgrade() finishes), so on confirm this function is entered
        # again and falls through. The checkbox is enforced server-side; the CSS
        # gate on the button is a courtesy (the CSP forbids inline JS but allows
        # inline styles).
        if( count( $t_events ) > 0 && php_sapi_name() != 'cli'
                && !( gpc_get_bool( '_confirmed' ) && gpc_get_bool( 'backup_confirmed' ) && gpc_get_bool( 'timezones_confirmed' ) ) ) {

            layout_page_header();
            layout_page_begin();

            echo '<div class="col-md-12 col-xs-12">';
            echo '<div class="space-10"></div>';
            echo '<div class="alert alert-warning center">';
            echo '<ul id="recurrence_migration_warnings" class="bigger-110">';
            echo '<li class="bold"><strong>' . plugin_lang_get( 'recurrence_migration_backup_warning' ) . '</strong></li>';
            echo '<li>' . plugin_lang_get( 'recurrence_migration_timezones_warning' ) . '</li>';
            echo '<li>' . sprintf( plugin_lang_get( 'recurrence_migration_confirm_msg' ), count( $t_events ) ) . '</li>';
            echo '<li>' . plugin_lang_get( 'recurrence_migration_column_msg' ) . '</li>';
            echo '</ul>';

            echo '<table id="recurrence_migration_table" class="table table-bordered table-condensed"><thead><tr>'
                    . '<th>ID</th>'
                    . '<th>' . plugin_lang_get( 'name_event' ) . '</th>'
                    . '<th>' . lang_get( 'username' ) . '</th>'
                    . '<th>' . plugin_lang_get( 'recurrence_migration_timezone_col' ) . '</th>'
                    . '</tr></thead><tbody>';
            foreach( $t_events as $t_event ) {
                echo '<tr>'
                        . '<td>' . (int)$t_event['id'] . '</td>'
                        . '<td>' . string_display_line( $t_event['name'] ) . '</td>'
                        . '<td>' . string_display_line( user_get_name( (int)$t_event['author_id'] ) ) . '</td>'
                        . '<td>' . string_display_line( $t_event['timezone'] ) . '</td>'
                        . '</tr>';
            }
            echo '</tbody></table>';
            echo '<div class="space-10"></div>';

            # the surrounding alert block centers text, which visually misaligns
            # the table body against its header — force one alignment for both
            echo '<style>'
                    . '#recurrence_migration_warnings { display: inline-block; text-align: left; list-style-position: outside; font-weight: normal; }'
                    . '#recurrence_migration_warnings li { margin-bottom: 6px; }'
                    . '#recurrence_migration_warnings li.bold, #recurrence_migration_warnings li.bold strong { font-weight: bold; }'
                    . '#recurrence_migration_table th, #recurrence_migration_table td { text-align: left; }'
                    . '#backup_confirmed:not(:checked) ~ input[type="submit"],'
                    . '#timezones_confirmed:not(:checked) ~ input[type="submit"] { pointer-events: none; opacity: .45; }'
                    . '</style>';

            echo '<form method="post" class="center" action="">' . "\n";
            # CSRF protection not required here - user needs to confirm action
            # before the form is accepted.
            $t_post = $_POST;
            $t_get  = $_GET;
            unset( $t_post['_confirmed'], $t_post['backup_confirmed'], $t_post['timezones_confirmed'],
                    $t_get['_confirmed'], $t_get['backup_confirmed'], $t_get['timezones_confirmed'] );
            print_hidden_inputs( $t_post );
            print_hidden_inputs( $t_get );

            echo '<input type="hidden" name="_confirmed" value="1" />', "\n";
            echo '<input type="checkbox" id="timezones_confirmed" name="timezones_confirmed" value="1" /> ';
            echo '<label for="timezones_confirmed" class="bold">' . plugin_lang_get( 'recurrence_migration_timezones_checkbox' ) . '</label>';
            echo '<br />';
            echo '<input type="checkbox" id="backup_confirmed" name="backup_confirmed" value="1" /> ';
            echo '<label for="backup_confirmed" class="bold">' . plugin_lang_get( 'recurrence_migration_backup_checkbox' ) . '</label>';
            echo '<div class="space-10"></div>';
            echo '<input type="submit" class="btn btn-primary btn-white btn-round" value="' . plugin_lang_get( 'recurrence_migration_confirm_button' ) . '" />';
            echo "\n</form>\n";

            echo '<div class="space-10"></div>';
            echo '</div></div>';

            layout_page_end();
            exit;
        }

        foreach( $t_events as $t_event ) {

            $t_timezone = $t_timezones[$t_event['timezone']];

            $t_rset_old = new \RRule\RSet( $t_event['recurrence_pattern'] );
            $t_rset_new = new CalendarPluginRRuleExt\RSetExt();

            foreach( $t_rset_old->getRRules() as $t_rrule_old ) {
                $t_rule = $t_rrule_old->getRule();
                if( $t_rule['DTSTART'] instanceof DateTimeInterface ) {
                    $t_dtstart = new DateTime( '@' . $t_rule['DTSTART']->getTimestamp() );
                    $t_rule['DTSTART'] = $t_dtstart->setTimezone( $t_timezone );
                }
                $t_rset_new->addRRule( new \RRule\RRule( $t_rule ) );
            }

            # Re-anchoring moves occurrence instants that lie in the opposite DST
            # phase from DTSTART, so each EXDATE must be re-matched to the new
            # rule's occurrence on the same local day, or the exclusion is lost.
            foreach( $t_rset_old->getExDates() as $t_exdate ) {
                $t_exdate_local = new DateTime( '@' . $t_exdate->getTimestamp() );
                $t_exdate_local->setTimezone( $t_timezone );
                $t_day_start = ( clone $t_exdate_local )->setTime( 0, 0, 0 );
                $t_day_end   = ( clone $t_exdate_local )->setTime( 23, 59, 59 );

                $t_occurrences = $t_rset_new->getOccurrencesBetween( $t_day_start, $t_day_end, 1 );
                $t_rset_new->addExDate( count( $t_occurrences ) ? $t_occurrences[0] : $t_exdate );
            }

            $t_pattern_new = $t_rset_new->rfcString();
            if( $t_pattern_new != $t_event['recurrence_pattern'] ) {
                $t_query = "UPDATE $t_table_calendar_events
                                            SET recurrence_pattern=" . db_param();
                $t_query .= " WHERE id=" . db_param();

                db_query( $t_query, Array( $t_pattern_new, $t_event['id'] ) );
            }
        }
    }
    return TRUE;
}

class CalendarPlugin extends MantisPlugin {

    function register() {

        $this->name        = plugin_lang_get( 'name_plugin_description_page' );
        $this->description = plugin_lang_get( 'description' );
        $this->page        = 'config_page';

        $this->version = '3.0.0';

        $this->requires = array(
                                  'MantisCore' => '2.26.0',
        );

        $this->author  = 'Grigoriy Ermolaev';
        $this->contact = 'igflocal@gmail.com';
        $this->url     = 'http://github.com/mantisbt-plugins/calendar';
    }
    
    function isValidDependency() {
        $t_file_path = config_get_global( 'plugin_path' );
	$t_file_path .= $this->basename . DIRECTORY_SEPARATOR;
	$t_file_path .= 'api/vendor/autoload.php';
        
        $t_file_is = file_exists( $t_file_path );
        return $t_file_is;
    }
    
    function isValid() {
        return parent::isValid() && $this->isValidDependency();
    }
    
    function getInvalidPlugin() {
        if(!parent::isValid()) {
            return parent::getInvalidPlugin();
        } else {
            $t_plugin = new MissingClassPlugin( $this->basename );

            $t_plugin->setInvalidPlugin( $this );

            $t_plugin->description = plugin_lang_get( 'ERROR_PLUGIN_DEPENDENCY_INSTALL_DESCRIPTION', $this->basename );
            $t_plugin->status_message = plugin_lang_get( 'ERROR_PLUGIN_DEPENDENCY_INSTALL_STATUS_MESSAGE', $this->basename );

            return $t_plugin;
        }
    }

    function schema() {

        /**
         * Standard table creation options
         * Array key is the ADOdb datadict driver's name
         */
        $t_table_options = array(
                'mysql' => 'DEFAULT CHARSET=utf8',
                'pgsql' => 'WITHOUT OIDS',
        );

        # Special handling for Oracle (oci8):
        # - Field cannot be null with oci because empty string equals NULL
        # - Oci uses a different date literal syntax
        # - Default BLOBs to empty_blob() function
        if( db_is_oracle() ) {
                $t_notnull = '';
//                $t_timestamp = 'timestamp' . installer_db_now();
                $t_blob_default = 'DEFAULT " empty_blob() "';
        } else {
                $t_notnull = 'NOTNULL';
//                $t_timestamp = '\'' . installer_db_now() . '\'';
                $t_blob_default = '';
        }

        return array(
                                  // version 0.0.1(schema 0)
                                  array( "CreateTableSQL", array( plugin_table( "events" ), "
					id I $t_notnull AUTOINCREMENT PRIMARY,
                                        project_id I $t_notnull,
                                        name C(255) $t_notnull,
                                        tasks C(2000) $t_notnull,
                                        date_event I UNSIGNED $t_notnull DEFAULT 1,
                                        hour_start I $t_notnull,
                                        minutes_start I $t_notnull,
                                        hour_finish I $t_notnull,
                                        minutes_finish I $t_notnull,
                                        activity C(1) $t_notnull,
                                        author_id I,
                                        date_changed I,
                                        changed_user_id I
				",
                                      $t_table_options ) ),
                                  //version 0.0.1(schema 1)
                                  array( "CreateTableSQL", array( plugin_table( "relationship" ), "
                                        event_id I $t_notnull,
                                        bug_id I $t_notnull
                                " ,
                                      $t_table_options ) ),
                                  //version 0.9(schema 2)
                                  array( 'AddColumnSQL', array( plugin_table( "events" ), "
                                        date_from I UNSIGNED $t_notnull DEFAULT 1,
                                        date_to I UNSIGNED $t_notnull DEFAULT 1
                                " ,
                                      $t_table_options ) ),
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
                                        user_id I UNSIGNED $t_notnull PRIMARY DEFAULT '0',
                                        event_id I UNSIGNED $t_notnull PRIMARY DEFAULT '0')
                                " ,
                                      $t_table_options ) ),
                                  //version 2.2.0 (schema 6)
                                  array( 'CreateIndexSQL', array( 'idx_event_id', plugin_table( "event_member" ), "
                                      event_id
                                      " ) ),
                                  //version 2.2.0 (schema 7)
                                  array( 'UpdateFunction', 'turn_user_owner_to_user_member' ),
                                  //version 2.3.0 (schema 8)
                                  array( "CreateTableSQL", array( plugin_table( "google_sync" ), "
                                      event_id I UNSIGNED $t_notnull PRIMARY DEFAULT '0',                                        
                                      google_id C(255) $t_notnull
                                " ,
                                      $t_table_options ) ),
                                  //version 2.3.0 (schema 9)
                                  array( 'CreateIndexSQL', array( 'idx_goole_sync_event_id', plugin_table( "google_sync" ), "
                                      event_id
                                      " ) ),
                                  //version 2.3.1 (schema 10)
                                  array( 'AddColumnSQL', array( plugin_table( "google_sync" ), "
                                        last_sync I UNSIGNED $t_notnull DEFAULT 0
                                " ) ),
                                  //version 2.4.0 (schema 11)
                                  array( 'AddColumnSQL', array( plugin_table( "events" ), "
                                        duration I UNSIGNED $t_notnull,
                                        recurrence_pattern C(255) DEFAULT NULL,
                                        parent_id I $t_notnull
                                " ) ),
                                  //version 2.4.0 (schema 12)
                                  array( 'UpdateFunction', 'calculate_duration' ),
                                  //version 2.4.7 (schema 13)
                                  array( 'AlterColumnSQL', array( plugin_table( "events" ), "
                                        recurrence_pattern X
                                " ) ),
                                  //version 2.4.8 (schema 14)
                                  array( 'UpdateFunction', 'recurrence_pattern_set_notnull' ),
                                  //version 2.4.8 (schema 15)
                                  array( 'AlterColumnSQL', array( plugin_table( "events" ), "
                                        recurrence_pattern X $t_notnull
                                " ) ),
                                  //version 3.0.0 (schema 16) — runs first: its confirmation
                                  //page must precede any database change of this upgrade
                                  array( 'UpdateFunction', 'recurrence_pattern_tzid' ),
                                  //version 3.0.0 (schema 17)
                                  array( 'AddColumnSQL', array( plugin_table( "events" ), "
                                        timezone C(64) $t_notnull DEFAULT \" '' \"
                                " ) ),
                                  //version 3.0.0 (schema 18)
                                  array( "CreateTableSQL", array( plugin_table( "event_history" ), "
                                        id I $t_notnull AUTOINCREMENT PRIMARY,
                                        event_id I UNSIGNED $t_notnull DEFAULT '0',
                                        user_id I UNSIGNED $t_notnull DEFAULT '0',
                                        field_name C(64) $t_notnull DEFAULT \" '' \",
                                        old_value X,
                                        new_value X,
                                        type I $t_notnull DEFAULT '0',
                                        date_modified I UNSIGNED $t_notnull DEFAULT '1'
                                " ,
                                      $t_table_options ) ),
                                  //version 3.0.0 (schema 19)
                                  array( 'CreateIndexSQL', array( 'idx_event_history_event_id', plugin_table( "event_history" ), "
                                      event_id
                                      " ) ),
                                  //version 3.0.0 (schema 20)
                                  array( "CreateTableSQL", array( plugin_table( "event_reminder" ), "
                                        id I $t_notnull AUTOINCREMENT PRIMARY,
                                        event_id I UNSIGNED $t_notnull DEFAULT '0',
                                        time_offset I UNSIGNED $t_notnull DEFAULT '0'
                                " ,
                                      $t_table_options ) ),
                                  //version 3.0.0 (schema 21)
                                  array( 'CreateIndexSQL', array( 'idx_event_reminder_event_id', plugin_table( "event_reminder" ), "
                                      event_id
                                      " ) ),
                                  //version 3.0.0 (schema 22)
                                  array( 'AddColumnSQL', array( plugin_table( "events" ), "
                                        description X
                                " ) ),
                                  //version 3.0.0 (schema 23): 0 = the reminders of the event, else the personal set of that recipient
                                  array( 'AddColumnSQL', array( plugin_table( "event_reminder" ), "
                                        user_id I UNSIGNED $t_notnull DEFAULT '0'
                                " ) ),
                                  //version 3.0.0 (schema 24): reply of the member, see core/calendar_rsvp_api.php
                                  array( 'AddColumnSQL', array( plugin_table( "event_member" ), "
                                        status I UNSIGNED $t_notnull DEFAULT '0'
                                " ) ),
                                  //version 3.0.0 (schema 25): a reminder put off to a later moment, see core/calendar_reminder_api.php
                                  array( "CreateTableSQL", array( plugin_table( "event_reminder_snooze" ), "
                                        id I $t_notnull AUTOINCREMENT PRIMARY,
                                        event_id I UNSIGNED $t_notnull DEFAULT '0',
                                        occurrence I UNSIGNED $t_notnull DEFAULT '0',
                                        user_id I UNSIGNED $t_notnull DEFAULT '0',
                                        fire_at I UNSIGNED $t_notnull DEFAULT '0'
                                " ,
                                      $t_table_options ) ),
                                  //version 3.0.0 (schema 26)
                                  array( 'CreateIndexSQL', array( 'idx_event_reminder_snooze_fire_at', plugin_table( "event_reminder_snooze" ), "
                                      fire_at
                                      " ) ),
        );
    }

    function config() {
        return array( //Default settings. Some of the settings are available for override via the plugin configuration page.
                                  //Time settings
                                  'datetime_picker_format'                              => 'DD-MM-Y',
                                  'short_date_format'                                   => 'd-m-Y',
                                  'datetime_picker_month_date_format'                   => 'MM-YYYY',
                                  'month_date_format'                                   => 'm-Y',
                                  'event_time_start_stop_picker_format'                 => 'HH:mm',
                                  'startStepDays'                                       => 0,
//                                  'startStepDays'                        => date( 'w' )-1,
                                  'countStepDays'                                       => 7,
                                  'show_count_future_recurring_events_in_bug_view_page' => 1,
                                  'bug_calendar_block_position'                         => 2, //Where the calendar sits on the issue view page, see the CALENDAR_BUG_BLOCK_* constants (config() runs before init(), so the literal).
                                  'bug_calendar_block_separate'                         => OFF, //Per user choice, only consulted under CALENDAR_BUG_BLOCK_USER_CHOICE.
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
                                  'view_event_history_threshold'                        => DEVELOPER, //The history exposes the members of the event, so it is not lower than show_member_list_threshold by default.
                                  //Member event access rights. 
                                  'show_member_list_threshold'                          => REPORTER,
                                  'member_event_threshold'                              => DEVELOPER, //The level of access necessary to become a member of the event.
                                  'member_add_others_event_threshold'                   => DEVELOPER,
                                  'member_delete_others_event_threshold'                => DEVELOPER, //Access level needed to delete other users from the list of users member a event.
                                  //Replies of the members: whether they will take part.
                                  'rsvp_feature_enabled'                                => OFF, //Master switch of the whole feature, changed by the administrator only. Replies, their mails and links, the pending list, its filter and the fading of unanswered events.
                                  //Reminders about upcoming events.
                                  'reminders_feature_enabled'                           => OFF, //Master switch of the whole feature, changed by the administrator only.
                                  'reminders_enabled'                                   => ON, //Per user opt-out.
                                  'reminders_no_reply'                                  => OFF, //Per user: remind about events the user has not replied to yet (RSVP).
                                  'reminders_default'                                   => array( 900 ), //Per user default offsets in seconds, used by events without their own reminders.
                                  'reminder_max_per_event'                              => 5,
                                  'reminder_max_offset'                                 => 2678400, //31 days; the same value is the look ahead of the dispatcher.
                                  'reminder_max_lateness'                               => 21600, //6 hours; caps the window after a long downtime.
                                  'reminder_web_trigger_interval'                       => 300,
                                  'reminder_last_run'                                   => 0, //Watermark of the dispatcher.
                                  'reminder_last_cron_run'                              => 0, //Last run through EVENT_CRONJOB, shown as a diagnostic on the configuration page.
                                  //Mail notifications about the changes of events.
                                  'notifications_feature_enabled'                       => OFF, //Master switch of the whole feature, changed by the administrator only.
                                  'notify_event_created'                                => ON, //Per user opt-out, also gates the mail about being added to an event.
                                  'notify_event_updated'                                => ON, //Per user opt-out.
                                  'notify_event_deleted'                                => ON, //Per user opt-out, also gates the mail about being removed from an event.
                                  //Who is mailed about which action, set by the administrator globally or per project.
                                  //The defaults spell out the behaviour the feature had before the matrix existed.
                                  'notify_flags'                                        => array(
                                                                                            'created'        => array( 'author' => ON, 'members' => ON, 'actor' => OFF ),
                                                                                            'updated'        => array( 'author' => ON, 'members' => ON, 'actor' => OFF ),
                                                                                            'deleted'        => array( 'author' => ON, 'members' => ON, 'actor' => OFF ),
                                                                                            'member_added'   => array( 'author' => OFF, 'members' => OFF, 'actor' => OFF ),
                                                                                            'member_removed' => array( 'author' => OFF, 'members' => OFF, 'actor' => OFF ),
                                                                                            'rsvp'           => array( 'author' => ON, 'members' => OFF, 'actor' => OFF )
                                                                                            ),
                                  //Outcome of the last manual check for a newer release, see core/calendar_update_api.php.
                                  'update_check_result'                                 => array(),
                                  //Google settings
                                  'oauth_key'                                           => array(),
                                  'google_calendar_sync_id'                             => '',
                                  'google_client_secret'                                => '',
        );
    }

    function init() {
        require_once 'api/vendor/autoload.php';
        require_once 'core/classes/RSetExt.class.php';
        require_once 'core/classes/EventCreateRequest.class.php';
        require_once 'core/calendar_event_data_api.php';
        require_once 'core/calendar_history_api.php';
        require_once 'core/calendar_reminder_api.php';
        require_once 'core/calendar_rsvp_api.php';
        require_once 'core/calendar_notify_api.php';
        require_once 'core/calendar_ical_api.php';
        require_once 'core/calendar_date_api.php';
        require_once 'core/calendar_access_api.php';
        require_once 'core/calendar_print_api.php';
        require_once 'core/calendar_helper_api.php';
        require_once 'core/calendar_columns_api.php';
        require_once 'core/calendar_user_api.php';
        require_once 'core/calendar_form_api.php';
        require_once 'core/calendar_google_api.php';
        require_once 'core/calendar_menu_api.php';
        require_once 'core/calendar_public_api.php';
        require_once 'core/calendar_update_api.php';
        require_once 'core/classes/WeekCalendar.class.php';
        require_once 'core/classes/ViewWeekCalendar.class.php';
        require_once 'core/classes/ViewIssue.class.php';
        require_once 'core/classes/ViewEventReply.class.php';
        require_once 'core/classes/ViewWeekSelect.class.php';
        require_once 'core/classes/ColumnForm.class.php';
        require_once 'core/classes/TimeColumn.class.php';
        require_once 'core/classes/DayColumn.class.php';
        require_once 'core/classes/EventArea.class.php';
        require_once 'core/classes/EventBand.class.php';
        require_once 'core/classes/ColumnViewIssuePage.class.php';
        require_once 'core/classes/ViewMonthCalendar.class.php';

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
                                  'ERROR_REMINDER_INVALID'            => plugin_lang_get( 'ERROR_REMINDER_INVALID' ),
        );
    }

    /**
     * Events raised by the plugin, so that other plugins can react to
     * calendar changes without depending on the Calendar code itself.
     *
     * EVENT_CALENDAR_EVENT_CREATED, EVENT_CALENDAR_EVENT_UPDATED and
     * EVENT_CALENDAR_EVENT_DELETED pass the event identifier as their only
     * parameter. EVENT_CALENDAR_EVENT_CREATED is signalled only after the
     * event is fully assembled - its members, issue links and reminders are
     * already written - so a subscriber may look the event up by id and see
     * it complete (see event_signal_created()). EVENT_CALENDAR_EVENT_UPDATED
     * likewise comes once the change is complete - the row, the reminders and
     * the issue links -, and follows what the user did, the same thing the
     * mail about a change follows: a form submitted without touching anything
     * raises it not, while a series that lost an occurrence or was cut short,
     * or an event that gained an issue link, raises it even though its row
     * may read the same (see event_signal_updated()). A change of the members
     * or of their replies has signals of its own, below.
     * EVENT_CALENDAR_EVENT_DELETED is signalled before anything is removed,
     * the way the core raises EVENT_BUG_DELETED: the handler still finds the
     * event and its members, so calendar_api_event_notify_recipients() works
     * there as well.
     *
     * EVENT_CALENDAR_EVENT_MEMBER_ADDED and EVENT_CALENDAR_EVENT_MEMBER_REMOVED
     * are signalled when a user is added to or removed from the members of an
     * existing event, with array( $p_event_id, $p_user_id, $p_actor_id ): the
     * member and whoever added or removed them. Like the mails about it, they
     * follow what the user did rather than how it is stored: the members
     * written while an event is assembled - at its creation, or when an
     * occurrence is split off its series - are announced by
     * EVENT_CALENDAR_EVENT_CREATED alone, and the members dropped with a
     * deleted event by EVENT_CALENDAR_EVENT_DELETED. Both are raised once the
     * change is stored, and the event still exists in either case.
     *
     * EVENT_CALENDAR_EVENT_REMINDER is signalled once per due reminder, that is
     * once per triple of occurrence, recipient and offset, and its parameters
     * are array( $p_event_id, $p_occurrence_timestamp, $p_user_id,
     * $p_offset_seconds ). It is raised for every allowed recipient even when
     * no mail is sent (empty address, notifications switched off globally), so
     * a subscriber can deliver the reminder through its own channel; a user who
     * opted out of reminders gets neither the mail nor the signal, and neither
     * does a member who declined the event or - unless they asked for it -
     * one who has not replied yet (see calendar_reminder_rsvp_hold()). The
     * reminders of a user can be read and changed on their behalf through
     * calendar_api_event_reminders() and the calls next to it. A reminder
     * that a recipient has put off through calendar_api_event_reminder_snooze()
     * is signalled the same way when its moment comes; its offset is then the
     * distance between that moment and the start of the occurrence, zero or
     * negative once the occurrence has begun.
     *
     * EVENT_CALENDAR_EVENT_RSVP is signalled when a member of an event replies
     * whether they will take part, and its parameters are array( $p_event_id,
     * $p_user_id, $p_status ), the status being one of the CALENDAR_RSVP_*
     * constants. It is raised for a changed reply only, on every path that
     * records one - the event page, the links in the mails and the public
     * calendar_api_event_member_status_set(); the mark of the author as
     * taking part in a freshly created event is not a reply and raises it not.
     *
     * EVENT_CALENDAR_NOTIFY_USER_INCLUDE and EVENT_CALENDAR_NOTIFY_USER_EXCLUDE
     * let a subscriber take part in the choice of the recipients of a
     * notification, the way EVENT_NOTIFY_USER_INCLUDE and
     * EVENT_NOTIFY_USER_EXCLUDE of the core do for the mails about an issue.
     *
     * EVENT_CALENDAR_NOTIFY_USER_INCLUDE( $p_event_id, $p_action ) is signalled
     * once per notification, after the author and the members named by the
     * notification matrix have been collected and before anything is filtered
     * out. It expects an array of user identifiers back, and anything else is
     * ignored. An added user is a candidate like any other: the actor rule of
     * the matrix, the personal notify_event_* settings and the
     * view_event_threshold of the event are applied to them as well, so the
     * signal widens the circle without handing anybody a way past the checks.
     *
     * EVENT_CALENDAR_NOTIFY_USER_EXCLUDE( $p_event_id, $p_action, $p_user_id )
     * is signalled for every candidate that survived all of those checks. Any
     * truthy answer of any subscriber drops the candidate, and no answer at all
     * keeps them.
     *
     * Both are handed the raw action rather than the row of the matrix it maps
     * to, that is one of 'created', 'updated', 'deleted',
     * 'occurrence_cancelled', 'from_date_cancelled', 'member_added',
     * 'member_removed', 'member_added_others', 'member_removed_others' and
     * 'rsvp'. They
     * are raised on every path that computes recipients, the public
     * calendar_api_event_notify_recipients() included.
     */
    function events() {
        return array(
                                  'EVENT_CALENDAR_EVENT_CREATED'        => EVENT_TYPE_EXECUTE,
                                  'EVENT_CALENDAR_EVENT_UPDATED'        => EVENT_TYPE_EXECUTE,
                                  'EVENT_CALENDAR_EVENT_DELETED'        => EVENT_TYPE_EXECUTE,
                                  'EVENT_CALENDAR_EVENT_MEMBER_ADDED'   => EVENT_TYPE_EXECUTE,
                                  'EVENT_CALENDAR_EVENT_MEMBER_REMOVED' => EVENT_TYPE_EXECUTE,
                                  'EVENT_CALENDAR_EVENT_REMINDER'       => EVENT_TYPE_EXECUTE,
                                  'EVENT_CALENDAR_EVENT_RSVP'           => EVENT_TYPE_EXECUTE,
                                  'EVENT_CALENDAR_NOTIFY_USER_INCLUDE'  => EVENT_TYPE_DEFAULT,
                                  'EVENT_CALENDAR_NOTIFY_USER_EXCLUDE'  => EVENT_TYPE_DEFAULT,
        );
    }

    function hooks() {
        return array(
                                  'EVENT_LAYOUT_RESOURCES' => 'resources',
                                  'EVENT_MENU_MAIN_FRONT'  => 'menu_main_front',
                                  'EVENT_VIEW_BUG_DETAILS' => 'html_print_calendar',
                                  'EVENT_VIEW_BUG_EXTRA'   => 'html_print_calendar_extra',
                                  'EVENT_FILTER_COLUMNS'    => 'column_add_in_view_all_bug_page',
                                  'EVENT_DISPLAY_TEXT'      => 'column_title_formating',
                                  'EVENT_CRONJOB'           => 'process_reminders_cron',
                                  'EVENT_CORE_READY'        => 'core_ready',
                                  'EVENT_MENU_ACCOUNT'      => 'menu_account',
        );
    }

    /**
     * Add the personal calendar view, reminder, notification and issue page
     * settings as a tab of the account section. They live there rather than on the plugin's
     * own settings page, because every user who can be a member of an event
     * or open an issue must be able to reach them, while the plugin page is
     * behind manage_calendar_threshold. The tab appears as soon as one of the
     * blocks applies, the page itself shows the blocks that do.
     * @return array of hyperlinks
     */
    function menu_account() {

        if( !calendar_user_view_settings_allowed() && !calendar_reminder_feature_enabled() && !calendar_notify_feature_enabled() && !calendar_bug_block_user_choice() ) {
            return array();
        }

        return array( '<a href="' . plugin_page( 'reminders_page' ) . '">' . plugin_lang_get( 'reminders_account_tab' ) . '</a>' );
    }

    /**
     * Run the reminder dispatcher from the core cron job (scripts/cronjob.php)
     * @return void
     */
    function process_reminders_cron() {

        if( !calendar_reminder_feature_enabled() ) {
            return;
        }

        # the marker proves to the administrator that the core cron job is
        # really scheduled, so it is written even when nothing is due
        plugin_config_set( 'reminder_last_cron_run', time() );

        calendar_reminder_process();
    }

    /**
     * Fallback dispatcher for installations that do not run the core cron job.
     * Throttled, silent and free of output, so that page loads stay unaffected.
     * @return void
     */
    /**
     * Per-request work that has to happen before any output: remember the
     * time range chosen for the issue page block and dispatch web reminders.
     * @return void
     */
    function core_ready() {
        calendar_issue_full_time_get();
        $this->process_reminders_web();
    }

    function process_reminders_web() {

        if( !calendar_reminder_feature_enabled() ) {
            return;
        }

        if( time() - (int)plugin_config_get( 'reminder_last_run' ) < (int)plugin_config_get( 'reminder_web_trigger_interval' ) ) {
            return;
        }

        calendar_reminder_process();
    }

    function resources() {
        return '<link rel="stylesheet" type="text/css" href="' . plugin_file( 'Calendar_1789643015.css' ) . '"></link>'
                . '<script type="text/javascript" src="' . plugin_file( 'calendar_filter.js' ) . '"></script>'
//                . '<script type="text/javascript" src="' . plugin_file( 'calendar_modal.js' ) . '"></script>'
//                . '<script type="text/javascript" src="' . plugin_file( 'calendar_event_create.js' ) . '"></script>'
                . '<script type="text/javascript" src="' . plugin_file( 'calendar_events_1789600000.js' ) . '"></script>'
                . '<script type="text/javascript" src="' . plugin_file( 'calendar_week_select_1789541168.js' ) . '"></script>'
                . '<script type="text/javascript" src="' . plugin_file( 'calendar_reminders_1789541168.js' ) . '"></script>'
                . '<script type="text/javascript" src="' . plugin_file( 'date_time_picker_1789541168.js' ) . '"></script>';
    }

    function menu_main_front() {
        $t_links = array();
        $t_links[] = array(
            'title' => plugin_lang_get( 'menu_main_front' ),
            'url' => plugin_page( 'calendar_user_page' ),
            'icon' => 'fa-calendar'
        );
        return $t_links;
    }

    /**
     * The calendar as a row of the issue details table. Both view page hooks
     * are registered, and calendar_bug_block_is_separate() decides which of
     * the two prints, so the block never shows up twice.
     * @param string $p_event
     * @param integer $p_bug_id
     */
    function html_print_calendar( $p_event, $p_bug_id ) {

        if( calendar_bug_block_is_separate() || !access_has_project_level( plugin_config_get( 'bug_calendar_view_threshold' ) ) ) {
            return;
        }

        echo '<tr class=calendar-area align="center">';
        echo '<td class=calendar-area-in-bugs align="center" colspan="6">';

        $this->print_issue_calendar( $p_bug_id );

        echo '</td>';
        echo '</tr>';
    }

    /**
     * The calendar as a widget of its own below the notes
     * @param string $p_event
     * @param integer $p_bug_id
     */
    function html_print_calendar_extra( $p_event, $p_bug_id ) {

        if( !calendar_bug_block_is_separate() || !access_has_project_level( plugin_config_get( 'bug_calendar_view_threshold' ) ) ) {
            return;
        }

        $this->print_issue_calendar( $p_bug_id );
    }

    private function print_issue_calendar( $p_bug_id ) {
        $t_events_id = get_events_id_from_bug_id( $p_bug_id );
        $t_dates     = calendar_column_objects_get_from_event_ids( $t_events_id );

        $t_calendar_issue_view = new ViewIssue( $t_dates, $p_bug_id, calendar_issue_full_time_get(), count( $t_events_id ) );
        $t_calendar_issue_view->print_html();
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
