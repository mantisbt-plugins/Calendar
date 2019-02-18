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

$f_event_id = gpc_get_int( 'event_id' );
$f_date     = gpc_get_int( 'date' );

event_ensure_exists( $f_event_id );

access_ensure_event_level( plugin_config_get( 'update_event_threshold' ), $f_event_id );

$t_event_is_rerecurrences = event_is_recurrences( $f_event_id );

if( $t_event_is_rerecurrences ) {
    event_occurrence_ensure_exist( $f_event_id, $f_date );
}

# Get Project Id and set it as current
$t_current_project = helper_get_current_project();
$t_project_id      = gpc_get_int( 'project_id', $t_current_project );

$t_event = event_get( $f_event_id );

if( $t_event_is_rerecurrences ) {
    $t_event->date_from = $f_date;
}

if( $t_event->project_id != $t_current_project ) {
    # in case the current project is not the same project of the bug we are viewing...
    # ... override the current project. This to avoid problems with categories and handlers lists etc.
    $g_project_override = $t_event->project_id;
}

$t_bugs_id = event_get_attached_bugs_id( $f_event_id );

layout_page_header();

layout_page_begin();
?>
<div class="col-md-12 col-xs-12">
    <div id="event-update" class="form-container">
        <form id="update_event_form" method="post" action="<?php echo plugin_page( 'event_update' ) ?>">
            <?php echo form_security_field( 'event_update' ); ?>
            <input type="hidden" name="event_id" value="<?php echo $f_event_id ?>" />
            <input type="hidden" name="last_updated" value="<?php echo $t_event->date_changed ?>" />
            <input type="hidden" name="date" value="<?php echo $f_date ?>" />

            <div class="widget-box widget-color-blue2">
                <div class="widget-header widget-header-small">
                    <h4 class="widget-title lighter">
                        <i class="ace-icon fa fa-comments"></i>
                        <?php echo plugin_lang_get( 'updating_event_advanced_title' ) ?>
                    </h4>
                    <div class="widget-toolbar no-border">
                        <div class="widget-menu">
                            <?php print_small_button( plugin_page( 'view' ) . "&event_id=" . $f_event_id, plugin_lang_get( 'back_to_event_link' ) ); ?>
                        </div>
                    </div>
                </div>

                <div class="widget-body">
                    <div class="widget-main no-padding">
                        <div class="table-responsive">
                            <table class="table table-bordered table-condensed table-striped">

                                <tr>
                                    <th class="category">
                                        <span class="required">*</span><label for="name_event"><?php echo plugin_lang_get( 'name_event' ); ?></label>
                                    </th>
                                    <td>
                                        <input <?php echo helper_get_tab_index() ?> type="text" id="name_event" name="name_event" size="105" maxlength="128" value="<?php echo $t_event->name ?>"required autofocus/>
                                    </td>
                                </tr>

                                <!--#Date-->

                                <?php
                                $t_date_to_display = date( plugin_config_get( 'short_date_format' ), $t_event->date_from );
                                ?>
                                <tr>
                                    <th class="category">
                                        <span class="required">*</span><label for="date_event"><?php echo plugin_lang_get( 'date_event' ) ?></label>
                                    </th>
                                    <td>
                                        <?php
                                        echo '<input ' . helper_get_tab_index() . ' type="text" id="date_event" name="date_event" class="datetimepicker input-sm" ' .
                                        'data-picker-locale="' . lang_get_current_datetime_locale() .
                                        '" data-picker-format="' . plugin_config_get( 'datetime_picker_format' ) . '" ' .
                                        'size="20" maxlength="16" required value="' . $t_date_to_display . '" />'
                                        ?>
                                        <i class="fa fa-calendar fa-xlg datetimepicker"></i>
                                    </td>
                                </tr>

                                <!--#event_time_start-->

                                <tr>
                                    <th class="category">
                                        <span class="required">*</span><label for="event_time_start"><?php echo plugin_lang_get( 'from_time' ) ?></label>
                                    </th>
                                    <td>
                                        <span class="date-event time-event">

                                            <span class="event_time_start-area">
                                                <select tabindex=3 name="event_time_start" id="event_time_start"><?php print_time_select_option( strtotime( date( "H:i", $t_event->date_from ) . " GMT", 0 ), TRUE ); ?></select>
                                            </span>

                                        </span>
                                    </td>
                                </tr>


                                <!--#event_time_finish-->

                                <tr>
                                    <th class="category">
                                        <span class="required">*</span><label for="event_time_finish"><?php echo plugin_lang_get( 'to_time' ) ?></label>
                                    </th>
                                    <td>
                                        <span class="date-event time-event">
                                            <span class="event_time_finish">
                                                <select tabindex=4 name="event_time_finish" id="event_time_finish"><?php print_time_select_option( strtotime( date( "H:i", $t_event->date_from + $t_event->duration ) . " GMT", 0 ), TRUE ); ?></select>
                                            </span>	
                                        </span>
                                    </td>
                                </tr>

                                <!--#recurrence-->

                                <tr>
                                    <th class="category">
                                        <label for="event_is_repeated"><?php echo plugin_lang_get( 'event_is_repeated' ) ?></label>
                                    </th>
                                    <td>
                                        <?php
                                        $t_rrule_string    = event_get_field( $t_event->id, 'recurrence_pattern' );
                                        $t_rule = array();
                                        if( !is_blank( $t_rrule_string ) ) {
                                            $t_rset   = new \RRule\RSet( $t_rrule_string );
                                            $t_rrules = $t_rset->getRRules();
                                            $t_rule   = $t_rrules[0]->getRule();
                                        }

                                        if( array_key_exists('INTERVAL', $t_rule) ) {
                                            ?>
                                            <input style="width: 50px;" type="number" id="interval_value" name="interval_value" min="1" value="<?php echo $t_rule['INTERVAL'] ?>" step="1"/>
                                        <?php } else { ?>
                                            <input style="width: 50px;" type="number" id="interval_value" name="interval_value" min="1" value="1" step="1"/>
                                        <?php } ?>
                                        <select name="selected_freq">

                                            <?php
                                            $t_frequency_list = plugin_config_get( 'frequencies' );



                                            foreach( $t_frequency_list as $key => $t_type_freq ) {
                                                if( array_key_exists('FREQ', $t_rule) && $t_rule['FREQ'] == $t_type_freq ) {
                                                    ?>
                                                    <option selected="selected" value="<?php echo $t_type_freq; ?>"><?php echo plugin_lang_get( $t_type_freq ) ?></option>
                                                    <?php
                                                } else {
                                                    ?>
                                                    <option value="<?php echo $t_type_freq; ?>"><?php echo plugin_lang_get( $t_type_freq ) ?></option>
                                                    <?php
                                                }
                                            }
                                            ?>

                                        </select>

                                        <label for="event_is_repeated"><?php echo plugin_lang_get( 'ending_repetition' ) ?></label>

                                        <?php
                                        if( array_key_exists( 'UNTIL', $t_rule ) ) {
                                            $t_time_until = $t_rule['UNTIL']->format( config_get( 'short_date_format' ) );
                                        } else {
                                            $t_time_until = plugin_lang_get( 'never_ending_repetition' );
                                        }
                                        echo '<input ' . helper_get_tab_index() . ' type="text" id="date_ending_repetition" name="date_ending_repetition" class="datetimepicker input-sm" ' .
                                        'data-picker-locale="' . lang_get_current_datetime_locale() .
                                        '" data-picker-format="' . plugin_config_get( 'datetime_picker_format' ) . '" ' .
                                        'size="10" maxlength="10" value="' . $t_time_until . '"/>'
                                        ?>
                                        <i class="fa fa-calendar fa-xlg datetimepicker"></i>


                                    </td>

                                </tr>

                            </table>
                        </div>


                        <?php
                        $t_per_page   = null;
                        $t_bug_count  = null;
                        $t_page_count = null;

                        $t_custom_filter = filter_get_default();

                        $t_bugs = filter_get_bug_rows( $f_page_number, $t_per_page, $t_page_count, $t_bug_count, $t_custom_filter, NULL, null, true );

                        $t_bugslist       = Array();
                        $t_users_handlers = Array();
                        $t_project_ids    = Array();
                        $t_row_count      = count( $t_bugs );

                        for( $i = 0; $i < $t_row_count; $i++ ) {
                            array_push( $t_bugslist, $t_bugs[$i]->id );
                            $t_users_handlers[] = $t_bugs[$i]->handler_id;
                            $t_project_ids[]    = $t_bugs[$i]->project_id;
                        }
                        $t_unique_users_handlers = array_unique( $t_users_handlers );
                        $t_unique_project_ids    = array_unique( $t_project_ids );
                        user_cache_array_rows( $t_unique_users_handlers );
                        project_cache_array_rows( $t_unique_project_ids );

                        gpc_set_cookie( config_get( 'bug_list_cookie' ), implode( ',', $t_bugslist ) );
                        ?>

                        <div class="col-md-12 col-xs-12">
                            <div class="space-10"></div>

                            <?php
                            $t_collapse_block = is_collapsed( 'relationships_event' );
                            $t_block_css      = $t_collapse_block ? 'collapsed' : '';
                            $t_block_icon     = $t_collapse_block ? 'fa-chevron-down' : 'fa-chevron-up';
                            ?>
                            <div id="relationships" class="widget-box widget-color-blue2 <?php echo $t_block_css ?>">
                                <div class="widget-header widget-header-small">
                                    <h4 class="widget-title lighter">
                                        <i class="ace-icon fa fa-sitemap"></i>
                                        <?php echo plugin_lang_get( 'event_relationships_bugs' ) ?>
                                    </h4>
                                    <div class="widget-toolbar">
                                        <a data-action="collapse" href="#">
                                            <i class="1 ace-icon fa <?php echo $t_block_icon ?> bigger-125"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="widget-body">
                                    <div class="widget-main no-padding">
                                        <div class="table-responsive">
                                            <div class="tasks-list-area">
                                                <?php
                                                foreach( $t_bugs as $bug ) {
                                                    $bug_id           = $bug->id;
                                                    $bug_name         = $bug->summary;
                                                    $bug_status_color = get_status_color( bug_get_field( $bug_id, "status" ) );
                                                    $t_checked        = in_array( $bug_id, $t_bugs_id ) ? "checked" : " ";

                                                    echo '<div class="task-area" style="background-color:' . $bug_status_color . ';">';
                                                    echo '<label for="task_' . $bug_id . '">';
                                                    echo '<input
							type="checkbox"
							name="bugs_add[]"
							id="task_' . $bug_id . '"
							value="' . $bug_id . '"
                                                        ' . $t_checked . '
							data-title="' . $bug_name . '"
							data-options="{background-color:' . $bug_status_color . ';}"
							>';

                                                    echo '<b>' . $bug_id . '</b>: ' . $bug_name;
                                                    echo '</label>';
                                                    echo '</div>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>



                        <div class="widget-toolbox padding-8 clearfix">
                            <input <?php helper_get_tab_index(); ?>
                                type="submit" class="btn btn-primary btn-white btn-round"
                                value="<?php echo plugin_lang_get( 'update_information_button' ); ?>" />
                        </div>
                    </div>
                    </form>
                </div>
            </div>
    </div>
</div>

<?php
layout_page_end();
