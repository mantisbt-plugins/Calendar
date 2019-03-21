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

auth_reauthenticate();

access_ensure_global_level( plugin_config_get( 'manage_calendar_threshold' ) );

layout_page_header( plugin_lang_get( 'name_plugin_description_page' ) );

layout_page_begin( 'manage_overview_page.php' );

$t_name_days_week = plugin_config_get( 'arWeekdaysName' );
?>

<div class="col-md-12 col-xs-12">
    <div class="space-10"></div>
    <div class="form-container">
        <form action="<?php echo plugin_page( 'user_config' ) ?>" method="post">
            <?php echo form_security_field( 'calendar_user_config_edit' ) ?>
            <div class="widget-box widget-color-blue2">
                <div class="widget-header widget-header-small">
                    <h4 class="widget-title lighter">
                        <i class="ace-icon fa fa-cubes"></i>
                        <?php echo plugin_lang_get( 'name_plugin_description_page' ) . ': ' . plugin_lang_get( 'config_title' ) ?>
                    </h4>
                </div>

                <div class="widget-body">
                    <div class="widget-main no-padding">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-condensed table-hover">
                                <colgroup>
                                    <col style="width:25%" />
                                    <col style="width:25%" />
                                    <col style="width:25%" />
                                </colgroup>

                                <tr>
                                    <td class="category" width="50%">
                                        <?php echo plugin_lang_get( 'config_days_week_display' ) ?>

                                    </td>

                                    <td colspan="3" width="50%">
                                        <?php
                                        foreach( $t_name_days_week as $t_name_day => $t_status ) {
                                            if( $t_status == ON ) {
                                                echo '<label><input type="checkbox" name="days_week[]" value="' . $t_name_day . '" checked="checked">' . plugin_lang_get( $t_name_day ) . '</input></label><br>';
                                            } else {
                                                echo '<label><input type="checkbox" name="days_week[]" value="' . $t_name_day . '">' . plugin_lang_get( $t_name_day ) . '</input></label><br>';
                                            }
                                        }
                                        ?>
                                    </td>
                                </tr>

                                <tr>
                                    <td class="category" width="50%">
                                        <?php echo plugin_lang_get( 'config_time_day_range' ) ?>

                                    </td>

                                    <td class="center" width="25%">
                                        <select name="time_day_start">
                                            <?php
                                            $t_time_day_start = plugin_config_get( 'time_day_start' );

                                            print_time_select_option( $t_time_day_start, TRUE );
                                            ?>
                                        </select>
                                    </td>
                                    <td class="center" width="25%">
                                        <select name="time_day_finish">
                                            <?php
                                            $t_time_day_finish = plugin_config_get( 'time_day_finish' );

                                            print_time_select_option( $t_time_day_finish, TRUE );
                                            ?>
                                        </select>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <td class="category" width="50%">
                                        <?php echo plugin_lang_get( 'config_step_day_minutes_count' ) ?>
                                    </td>

                                    <td class="center" width="25%" colspan="2">
                                        <input style="width: 50px;" type="number" id="step_day_minutes_count" name="step_day_minutes_count" min="1" max="6" value="<?php echo plugin_config_get( 'stepDayMinutesCount' ) ?>" step="1"/>
                                    </td>
                                </tr>
                                
                                 <tr>
                                    <td class="category" width="50%">
                                        <?php echo plugin_lang_get( 'config_start_step_days' ) ?>
                                    </td>

                                    <td class="center" width="25%" colspan="2">
                                        <input style="width: 50px;" type="number" id="start_step_days" name="start_step_days" min="0" value="<?php echo plugin_config_get( 'startStepDays' ) ?>" step="1"/>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <td class="category" width="50%">
                                        <?php echo plugin_lang_get( 'config_count_step_days' ) ?>
                                    </td>

                                    <td class="center" width="25%" colspan="2">
                                        <input style="width: 50px;" type="number" id="count_step_days" name="count_step_days" min="1" value="<?php echo plugin_config_get( 'countStepDays' ) ?>" step="1"/>
                                    </td>
                                </tr>
                                
                                <?php if( plugin_config_get( 'google_client_secret' ) ) { ?>
                                    <tr>
                                        <td class="category" width="50%">
                                            <?php echo plugin_lang_get( 'user_config_enable_google_calendar' ) ?>

                                        </td>

                                        <td class="center" colspan="3">
                                            <?php
                                            $t_oauth = plugin_config_get( 'oauth_key', array(), FALSE, auth_get_current_user_id() );
                                            if( count( $t_oauth ) == 0 || array_key_exists('error', $t_oauth) && $t_oauth['error']  ) {
                                                print_small_button( get_response_google_url(), plugin_lang_get( 'user_config_enable_google_calendar_button' ) );
                                            } else {
                                                echo '<select name="google_calendar_list">';
                                                print_google_calendar_list();
                                                echo '</select>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                        <?php } ?>


                                <tr>
                                    <td class="center" colspan="3">
                                        <input type="submit" class="button" value="<?php echo lang_get( 'change_configuration' ) ?>" />
                                    </td>
                                </tr>

                            </table>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
if( !function_exists( 'html_page_bottom' ) ) {
    layout_page_end();
} else {
    html_page_bottom();
}
