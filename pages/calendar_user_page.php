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

auth_ensure_user_authenticated();

access_ensure_project_level( plugin_config_get( 'calendar_view_threshold' ) );

layout_page_header( plugin_lang_get( 'week' ) );

layout_page_begin( plugin_page( 'calendar_user_page' ) );


# Get Project Id and set it as current
$t_project_id = gpc_get_int( 'project_id', helper_get_current_project() );
if( ( ALL_PROJECTS == $t_project_id || project_exists( $t_project_id ) ) && $t_project_id != helper_get_current_project()
 ) {
    helper_set_current_project( $t_project_id );
    # Reloading the page is required so that the project browser
    # reflects the new current project
    print_header_redirect( $_SERVER['REQUEST_URI'], true, false, true );
}

compress_enable();

# don't index view issues pages
html_robots_noindex();


$f_week      = gpc_get_int( "week", date( "W" ) );
$f_full_time = gpc_get_bool( "full_time" );
$f_for_user  = gpc_get_int( "for_user", auth_get_current_user_id() );
?>

<div class="col-md-12 col-xs-12">
    <div class="space-10"></div>
    <div class="widget-box widget-color-blue2">
        <div class="widget-header widget-header-small">
            <h4 class="widget-title lighter">
                <i class="ace-icon fa fa-list-alt"></i>
                <?php echo plugin_lang_get( 'menu_main_front' ) . " ( GMT " . date( "P" ) . " )"; ?>
            </h4>
        </div>
        <div class="widget-body">

            <div class="widget-toolbox padding-8 clearfix">
                <div class="btn-toolbar">
                    <div class="btn-group pull-left">
                        <?php
                        if( $f_full_time == FALSE ) {
                            print_small_button( plugin_page( 'calendar_user_page' ) . "&for_user=" . $f_for_user . "&week=" . $f_week . "&full_time=TRUE", "0-24" );
                        } else {
                            print_small_button( plugin_page( 'calendar_user_page' ) . "&for_user=" . $f_for_user . "&week=" . $f_week, gmdate( "H", plugin_config_get( 'time_day_start' ) ) . "-" . gmdate( "H", plugin_config_get( 'time_day_finish' ) ) );
                        }
                        ?>
                    </div>

                    <div class="btn-group pull-right">
                        <?php
                        if( $f_full_time == FALSE ) {
                            print_small_button( plugin_page( 'calendar_user_page' ) . "&for_user=" . $f_for_user . "&week=" . ($f_week - 1), plugin_lang_get( 'previous_period' ) );
                            print_small_button( plugin_page( 'calendar_user_page' ) . "&for_user=" . $f_for_user . "&week=" . (int) date( "W" ), plugin_lang_get( 'week' ) );
                            print_small_button( plugin_page( 'calendar_user_page' ) . "&for_user=" . $f_for_user . "&week=" . ($f_week + 1), plugin_lang_get( 'next_period' ) );
                        } else {
                            print_small_button( plugin_page( 'calendar_user_page' ) . "&for_user=" . $f_for_user . "&week=" . ($f_week - 1) . "&full_time=TRUE", plugin_lang_get( 'previous_period' ) );
                            print_small_button( plugin_page( 'calendar_user_page' ) . "&for_user=" . $f_for_user . "&week=" . (int) date( "W" ) . "&full_time=TRUE", plugin_lang_get( 'week' ) );
                            print_small_button( plugin_page( 'calendar_user_page' ) . "&for_user=" . $f_for_user . "&week=" . ($f_week + 1) . "&full_time=TRUE", plugin_lang_get( 'next_period' ) );
                        }
                        ?>
                    </div>

                </div>
            </div>

            <div class="widget-main no-padding">
                <div class="table-responsive" style="overflow-y: hidden;">
                    <!--<div class="table-responsive">-->
                    <table class="calendar-user week">
                        <tr class="row-day">
                            <?php
                            print_column_time( $f_full_time );

                            $p_project_id = helper_get_current_project();

                            $t_start_day_of_the_week = plugin_config_get( "startStepDays" );
                            $t_step_days_count       = plugin_config_get( "countStepDays" );
                            $t_arWeekdaysName        = plugin_config_get( "arWeekdaysName" );

                            $t_days = days_of_number_week( $t_start_day_of_the_week, $t_step_days_count, $t_arWeekdaysName, $f_week );

                            $t_days_and_events = get_events_id_inside_days( $t_days, $p_project_id, $f_full_time, $f_for_user );

                            foreach( $t_days_and_events as $t_day_and_events => $t_events_id ) {
                                print_column_this_day( $t_day_and_events, $t_events_id, count( $t_days_and_events ), $f_full_time );
                            }
                            ?>
                    </table>
                </div>
            </div>
            <?php
            $t_access_level_current_user = access_get_global_level( auth_get_current_user_id() );

            if( access_compare_level( $t_access_level_current_user, plugin_config_get( 'calendar_edit_threshold' ) ) ) {
                ?>
                <div class="widget-toolbox padding-8 clearfix">
                    <div class="form-inline pull-left">
                        <?php
                        if( $f_full_time == TRUE ) {
                            print_small_button( plugin_page( 'event_add_page' ) . "&full_time=TRUE", plugin_lang_get( 'add_new_event' ) );
                        } else {
                            print_small_button( plugin_page( 'event_add_page' ), plugin_lang_get( 'add_new_event' ) );
                        }
                        ?>
                    </div>
                    <div class="form-inline pull-right">
                        <form id="filter-queries-form" class="form-inline pull-left padding-left-8"  method="get" name="list_queries" action="<?php echo plugin_page( 'calendar_user_page' ); ?>">
                            <?php # CSRF protection not required here - form does not result in modifications?>
                            <input type="hidden" name="page" value="Calendar/calendar_user_page" />
                            <input type="hidden" name="week" value="<?php echo $f_week; ?>" />
                            <input type="hidden" name="full_time" value="<?php echo $f_full_time; ?>" />

                            
                            <label class="inline"><?php echo plugin_lang_get( 'filter_text' ); ?></label>
                            <select name="for_user">
                                <option value="<?php echo auth_get_current_user_id(); ?>"><?php echo '[' . lang_get( 'reset_query' ) . ']' ?></option>
                                <?php if( $f_for_user == 0 ) { ?>
                                    <option selected="selected" value="0"><?php echo '[' . plugin_lang_get( 'select_all_users' ) . ']' ?></option>
                                <?php } else { ?>
                                    <option value="0"><?php echo '[' . plugin_lang_get( 'select_all_users' ) . ']' ?></option>
                                <?php } ?>
                                <?php
                                print_user_option_list( $f_for_user, $p_project_id, plugin_config_get( 'report_event_threshold' ) );
                                ?>
                            </select>
                        </form>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
    </div>
</div>

<?php
layout_page_end();
