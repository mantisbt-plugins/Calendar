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

# Personal calendar view, reminder, notification and issue page settings, a tab
# of the account section. Anyone who can be a member of an event or open an
# issue has to reach this page, so it is not behind any of the calendar
# thresholds - it only ever touches the settings of the current user. The
# calendar view block alone keeps the manage_calendar_threshold its settings
# button in the calendar header has always had. Each block is shown only
# when its feature is switched on, and the page as a whole is reachable as
# long as one of them is.

auth_ensure_user_authenticated();

current_user_ensure_unprotected();

$t_view_settings         = calendar_user_view_settings_allowed();
$t_rsvp_user_choice      = calendar_rsvp_user_choice();
$t_reminders_enabled     = calendar_reminder_feature_enabled();
$t_notifications_enabled = calendar_notify_feature_enabled();
$t_bug_block_user_choice = calendar_bug_block_user_choice();

if( !$t_view_settings && !$t_rsvp_user_choice && !$t_reminders_enabled && !$t_notifications_enabled && !$t_bug_block_user_choice ) {
    access_denied();
}

$t_current_user_id = auth_get_current_user_id();

layout_page_header( plugin_lang_get( 'reminders_account_tab' ) );

layout_page_begin( 'account_page.php' );

print_account_menu( plugin_page( 'reminders_page', TRUE ) );
?>

<div class="col-md-12 col-xs-12">
    <div class="space-10"></div>
    <div class="form-container">
        <form action="<?php echo plugin_page( 'reminders' ) ?>" method="post">
            <?php echo form_security_field( 'calendar_reminders_edit' ) ?>
            <?php if( $t_view_settings ) { ?>
            <div id="calendar_view" class="widget-box widget-color-blue2">
                <div class="widget-header widget-header-small">
                    <h4 class="widget-title lighter">
                        <i class="ace-icon fa fa-calendar"></i>
                        <?php echo plugin_lang_get( 'user_config_view_title' ) ?>
                    </h4>
                </div>

                <div class="widget-body">
                    <div class="widget-main no-padding">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-condensed table-hover">
                                <colgroup>
                                    <col style="width:50%" />
                                    <col style="width:25%" />
                                    <col style="width:25%" />
                                </colgroup>

                                <tr>
                                    <td class="category">
                                        <?php echo plugin_lang_get( 'config_days_week_display' ) ?>
                                    </td>

                                    <td colspan="2">
                                        <?php
                                        foreach( plugin_config_get( 'arWeekdaysName' ) as $t_name_day => $t_status ) {
                                            echo '<label><input type="checkbox" name="days_week[]" value="' . $t_name_day . '"'
                                                    . ( $t_status == ON ? ' checked="checked"' : '' ) . '> ' . plugin_lang_get( $t_name_day ) . '</label><br>';
                                        }
                                        ?>
                                    </td>
                                </tr>

                                <tr>
                                    <td class="category">
                                        <?php echo plugin_lang_get( 'config_time_day_range' ) ?>
                                    </td>

                                    <td class="center">
                                        <select name="time_day_start">
                                            <?php print_time_select_option( plugin_config_get( 'time_day_start' ), TRUE ) ?>
                                        </select>
                                    </td>
                                    <td class="center">
                                        <select name="time_day_finish">
                                            <?php print_time_select_option( plugin_config_get( 'time_day_finish' ), TRUE ) ?>
                                        </select>
                                    </td>
                                </tr>

                                <tr>
                                    <td class="category">
                                        <?php echo plugin_lang_get( 'config_step_day_minutes_count' ) ?>
                                    </td>

                                    <td colspan="2">
                                        <input style="width: 50px;" type="number" name="step_day_minutes_count" min="1" max="6" value="<?php echo plugin_config_get( 'stepDayMinutesCount' ) ?>" step="1"/>
                                    </td>
                                </tr>

                                <tr>
                                    <td class="category">
                                        <?php echo plugin_lang_get( 'config_start_step_days' ) ?>
                                    </td>

                                    <td colspan="2">
                                        <input style="width: 50px;" type="number" name="start_step_days" min="0" value="<?php echo plugin_config_get( 'startStepDays' ) ?>" step="1"/>
                                    </td>
                                </tr>

                                <tr>
                                    <td class="category">
                                        <?php echo plugin_lang_get( 'config_count_step_days' ) ?>
                                    </td>

                                    <td colspan="2">
                                        <input style="width: 50px;" type="number" name="count_step_days" min="1" value="<?php echo plugin_config_get( 'countStepDays' ) ?>" step="1"/>
                                    </td>
                                </tr>

                                <?php if( plugin_config_get( 'google_client_secret' ) ) { ?>
                                <tr>
                                    <td class="category">
                                        <?php echo plugin_lang_get( 'user_config_enable_google_calendar' ) ?>
                                    </td>

                                    <td colspan="2">
                                        <?php
                                        $t_oauth = plugin_config_get( 'oauth_key', array(), FALSE, $t_current_user_id );
                                        if( count( $t_oauth ) == 0 || array_key_exists( 'error', $t_oauth ) && $t_oauth['error'] ) {
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

                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php } ?>

            <?php if( $t_rsvp_user_choice ) { ?>
            <div id="rsvp" class="widget-box widget-color-blue2">
                <div class="widget-header widget-header-small">
                    <h4 class="widget-title lighter">
                        <i class="ace-icon fa fa-envelope-o"></i>
                        <?php echo plugin_lang_get( 'rsvp_pref_title' ) ?>
                    </h4>
                </div>

                <div class="widget-body">
                    <div class="widget-main no-padding">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-condensed table-hover">
                                <colgroup>
                                    <col style="width:50%" />
                                    <col style="width:50%" />
                                </colgroup>

                                <tr>
                                    <td class="category">
                                        <?php echo plugin_lang_get( 'rsvp_pref_enabled' ) ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo '<label><input type="checkbox" name="rsvp_enabled" value="1"'
                                                . ( calendar_rsvp_user_enabled( $t_current_user_id ) ? ' checked="checked"' : '' ) . '></input></label>';
                                        ?>
                                        <p class="small"><?php echo plugin_lang_get( 'rsvp_pref_hint' ) ?></p>
                                    </td>
                                </tr>

                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php } ?>

            <?php if( $t_reminders_enabled ) { ?>
            <div class="widget-box widget-color-blue2">
                <div class="widget-header widget-header-small">
                    <h4 class="widget-title lighter">
                        <i class="ace-icon fa fa-bell"></i>
                        <?php echo plugin_lang_get( 'reminders_title' ) ?>
                    </h4>
                </div>

                <div class="widget-body">
                    <div class="widget-main no-padding">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-condensed table-hover">
                                <colgroup>
                                    <col style="width:50%" />
                                    <col style="width:50%" />
                                </colgroup>

                                <tr>
                                    <td class="category">
                                        <?php echo plugin_lang_get( 'reminders_pref_enabled' ) ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo '<label><input type="checkbox" name="reminders_enabled" value="1"'
                                                . ( calendar_reminder_user_enabled( $t_current_user_id ) ? ' checked="checked"' : '' ) . '></input></label>';
                                        ?>
                                    </td>
                                </tr>

                                <?php if( calendar_rsvp_feature_enabled() ) { ?>
                                <tr>
                                    <td class="category">
                                        <?php echo plugin_lang_get( 'reminders_pref_no_reply' ) ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo '<label><input type="checkbox" name="reminders_no_reply" value="1"'
                                                . ( calendar_reminder_user_no_reply( $t_current_user_id ) ? ' checked="checked"' : '' ) . '></input></label>';
                                        ?>
                                        <p class="small"><?php echo plugin_lang_get( 'reminders_pref_no_reply_hint' ) ?></p>
                                    </td>
                                </tr>
                                <?php } ?>

                                <tr>
                                    <td class="category">
                                        <?php echo plugin_lang_get( 'reminders_pref_default' ) ?>
                                    </td>

                                    <td>
                                        <?php print_event_reminder_rows( calendar_reminder_user_offsets( $t_current_user_id ), FALSE ) ?>
                                        <p class="small"><?php echo plugin_lang_get( 'reminders_pref_hint' ) ?></p>
                                    </td>
                                </tr>

                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php } ?>

            <?php if( $t_notifications_enabled ) { ?>
            <div class="widget-box widget-color-blue2">
                <div class="widget-header widget-header-small">
                    <h4 class="widget-title lighter">
                        <i class="ace-icon fa fa-envelope"></i>
                        <?php echo plugin_lang_get( 'notifications_title' ) ?>
                    </h4>
                </div>

                <div class="widget-body">
                    <div class="widget-main no-padding">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-condensed table-hover">
                                <colgroup>
                                    <col style="width:50%" />
                                    <col style="width:50%" />
                                </colgroup>

                                <?php
                                # being added to an event and being removed from one are mailed
                                # along with the creation and the deletion, so three settings
                                # cover every mail of the feature
                                foreach( array( 'created', 'updated', 'deleted' ) as $t_notify_action ) {
                                    ?>
                                    <tr>
                                        <td class="category">
                                            <?php echo plugin_lang_get( 'notify_pref_' . $t_notify_action ) ?>
                                        </td>

                                        <td>
                                            <?php
                                            echo '<label><input type="checkbox" name="notify_event_' . $t_notify_action . '" value="1"'
                                                    . ( calendar_notify_user_enabled( $t_current_user_id, $t_notify_action ) ? ' checked="checked"' : '' ) . '></input></label>';
                                            ?>
                                        </td>
                                    </tr>
                                    <?php
                                }
                                ?>

                                <tr>
                                    <td class="category" colspan="2">
                                        <span class="small"><?php echo plugin_lang_get( 'notify_pref_hint' ) ?></span>
                                    </td>
                                </tr>

                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php } ?>

            <?php if( $t_bug_block_user_choice ) { ?>
            <div id="bug_calendar_block" class="widget-box widget-color-blue2">
                <div class="widget-header widget-header-small">
                    <h4 class="widget-title lighter">
                        <i class="ace-icon fa fa-list-alt"></i>
                        <?php echo plugin_lang_get( 'config_bug_calendar_block_position' ) ?>
                    </h4>
                </div>

                <div class="widget-body">
                    <div class="widget-main no-padding">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-condensed table-hover">
                                <colgroup>
                                    <col style="width:50%" />
                                    <col style="width:50%" />
                                </colgroup>

                                <tr>
                                    <td class="category">
                                        <?php echo plugin_lang_get( 'user_config_bug_calendar_block_separate' ) ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo '<label><input type="checkbox" name="bug_calendar_block_separate" value="1"'
                                                . ( calendar_bug_block_is_separate( $t_current_user_id ) ? ' checked="checked"' : '' ) . '></input></label>';
                                        ?>
                                    </td>
                                </tr>

                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php } ?>

            <div class="widget-toolbox padding-8 clearfix">
                <div class="center">
                    <input type="submit" class="button" value="<?php echo lang_get( 'change_configuration' ) ?>" />
                </div>
            </div>
        </form>
    </div>
</div>

<?php
layout_page_end();
