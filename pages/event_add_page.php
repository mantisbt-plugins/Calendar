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

$f_bug_id      = gpc_get_int( 'bug_id', 0 );
$f_is_fulltime = gpc_get_bool( "full_time" );

if( $f_bug_id == 0 ) {

    # Get Project Id and set it as current
    $t_current_project = helper_get_current_project();
    $t_project_id      = gpc_get_int( 'project_id', $t_current_project );

# If all projects, use default project if set
    $t_default_project = user_pref_get_pref( auth_get_current_user_id(), 'default_project' );
    if( ALL_PROJECTS == $t_project_id && ALL_PROJECTS != $t_default_project ) {
        $t_project_id = $t_default_project;
    }

    if( ( ALL_PROJECTS == $t_project_id || project_exists( $t_project_id ) ) && $t_project_id != $t_current_project && project_enabled( $t_project_id ) ) {
        helper_set_current_project( $t_project_id );
        # Reloading the page is required so that the project browser
        # reflects the new current project
        print_header_redirect( $_SERVER['REQUEST_URI'], true, false, true );
    }

# New issues cannot be reported for the 'All Project' selection
    if( ALL_PROJECTS == $t_current_project ) {
        print_header_redirect( 'login_select_proj_page.php?ref=' . plugin_page( 'event_add_page', TRUE ) );
    }
# Check for event report threshold
    if( !access_has_project_level( plugin_config_get( 'report_event_threshold' ) ) ) {
        # If can't report on current project, show project selector if there is any other allowed project
        access_ensure_any_project_level( plugin_config_get( 'report_event_threshold' ) );
        print_header_redirect( 'login_select_proj_page.php?ref=' . plugin_page( 'event_add_page', TRUE ) );
    }
    access_ensure_project_level( plugin_config_get( 'report_event_threshold' ) );
} else {
    bug_ensure_exists( $f_bug_id );

    $t_project_id = bug_get_field( $f_bug_id, 'project_id' );

    $g_project_override = bug_get_field( $f_bug_id, 'project_id' );
}



# don't index bug report page
html_robots_noindex();

layout_page_header( plugin_lang_get( 'add_new_event' ) );

layout_page_begin();

$t_form_encoding   = '';
?>
<div class="col-md-12 col-xs-12">
    <form id="add_event_form"
          method="post" <?php echo $t_form_encoding; ?>
          action="<?php echo plugin_page( 'event_add' ); ?>">
              <?php echo form_security_field( 'event_add' ) ?>
        <input type="hidden" name="project_id" value="<?php echo $t_project_id ?>" />
        <input type="hidden" name="from_bug_id" value="<?php echo $f_bug_id ?>" />
        <div class="widget-box widget-color-blue2">
            <div class="widget-header widget-header-small">
                <h4 class="widget-title lighter">
                    <i class="ace-icon fa fa-edit"></i>
                    <?php echo plugin_lang_get( 'enter_report_details_title' ) ?>
                </h4>
            </div>
            <div class="widget-body dz-clickable">
                <div class="widget-main no-padding">
                    <div class="table-responsive">
                        <table class="table table-bordered table-condensed">

                            <tr>
                                <th class="category">
                                    <span class="required">*</span><label for="name_event"><?php echo plugin_lang_get( 'name_event' ); ?></label>
                                </th>
                                <td>
                                    <input <?php echo helper_get_tab_index() ?> type="text" id="name_event" name="name_event" size="105" maxlength="128" required autofocus/>
                                </td>
                            </tr>

                            <!--#Date-->

                            <?php
                            $t_date_to_display = '';
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
                                    'size="10" maxlength="10" required />'
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
                                            <select tabindex=3 name="event_time_start" id="event_time_start"><?php print_time_select_option( NULL, $f_is_fulltime ); ?></select>
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
                                            <select  <?php helper_get_tab_index() ?> name="event_time_finish" id="event_time_finish"><?php print_time_select_option( NULL, $f_is_fulltime ); ?></select>
                                        </span>	
                                    </span>
                                </td>
                            </tr>

                            <!--#owner_is_members-->

                            <tr>
                                <th class="category">
                                    <label for="owner_is_members"><?php echo plugin_lang_get( 'owner_is_members' ) ?></label>
                                </th>
                                <td>

                                    <label class="inline">
                                        <input type="checkbox" name="owner_is_members" class="ace input-sm" id="owner_is_members" checked="checked">
                                        <span class="lbl"></span>
                                    </label>

                                </td>
                            </tr>

                            <!--#recurrence-->

                            <tr>
                                <th class="category">
                                    <label for="event_is_repeated"><?php echo plugin_lang_get( 'event_is_repeated' ) ?></label>
                                </th>
                                <td>
                                    <input style="width: 50px;" type="number" id="interval_value" name="interval_value" min="1" value="1" step="1"/>

                                    <select name="selected_freq">

                                        <?php
                                        $t_frequency_list  = plugin_config_get( 'frequencies' );

                                        foreach( $t_frequency_list as $key => $t_type_freq ) {
                                            ?>
                                            <option value="<?php echo $t_type_freq; ?>"><?php echo plugin_lang_get( $t_type_freq ) ?></option>
                                            <?php
                                        }
                                        ?>

                                    </select>

                                    <label for="event_is_repeated"><?php echo plugin_lang_get( 'ending_repetition' ) ?></label>

                                    <?php
                                    echo '<input ' . helper_get_tab_index() . ' type="text" id="date_ending_repetition" name="date_ending_repetition" class="datetimepicker input-sm" ' .
                                    'data-picker-locale="' . lang_get_current_datetime_locale() .
                                    '" data-picker-format="' . plugin_config_get( 'datetime_picker_format' ) . '" ' .
                                    'size="10" maxlength="10" value="' . plugin_lang_get( 'never_ending_repetition' ) . '"/>'
                                    ?>
                                    <i class="fa fa-calendar fa-xlg datetimepicker"></i>


                                </td>

                            </tr>

                        </table>
                    </div>    


                    <?php #members_list_add   ?>
                    <div class="col-md-12 col-xs-12">
                        <div class="space-10"></div>

                        <?php
//                        $t_collapse_block = 'collapsed';
                        $t_collapse_block = '';
                        $t_block_css      = $t_collapse_block ? 'collapsed' : '';
                        $t_block_icon     = $t_collapse_block ? 'fa-chevron-down' : 'fa-chevron-up';
                        ?>
                        <div id="members_list_add" class="widget-box widget-color-blue2 <?php echo $t_block_css ?>">
                            <div class="widget-header widget-header-small">
                                <h4 class="widget-title lighter">
                                    <i class="ace-icon fa fa-users"></i>
                                    <?php echo plugin_lang_get( 'members' ) ?>
                                </h4>
                                <div class="widget-toolbar">
                                    <a data-action="collapse" href="#">
                                        <i class="1 ace-icon fa <?php echo $t_block_icon ?> bigger-125"></i>
                                    </a>
                                </div>
                            </div>

                            <div class="widget-body">
                                <div class="widget-main no-padding">
                                    <table class="table table-bordered table-condensed table-striped">
                                        <div class="table-responsive">
                                            <tr>
                                                <th class="category" width="15%">
                                                    <?php echo lang_get( 'monitoring_user_list' ); ?>
                                                </th>
                                                <td>
                                                    <?php
                                                    $t_project_users    = project_get_all_user_rows( $t_project_id );
                                                    ?>

                                                    <?php if( is_array( $t_project_users ) && count( $t_project_users ) > 0 ): ?>			
                                                        <select size="8" multiple name="user_ids[]">
                                                            <?php foreach( $t_project_users as $project_user ): ?>
                                                                <?php if( !empty( $project_user['id'] ) && !empty( $project_user['realname'] ) ): ?>
                                                                    <option value="<?php echo $project_user['id']; ?>"><?php echo $project_user['realname']; ?></option>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    <?php endif; ?>

                                                </td>
                                            </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


                <?php
                $t_per_page   = null;
                $t_bug_count  = null;
                $t_page_count = null;

                $t_custom_filter = filter_get_default();

                $t_bugs = filter_get_bug_rows( $f_page_number, $t_per_page, $t_page_count, $t_bug_count, $t_custom_filter, $t_project_id, null, true );

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
                                            $t_checked        = $bug_id == $f_bug_id ? "checked" : " ";

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

            </div>
            <div class="widget-toolbox padding-8 clearfix">
                <span class="required pull-right"> * <?php echo lang_get( 'required' ) ?></span>
                <input <?php echo helper_get_tab_index() ?> type="submit" class="btn btn-primary btn-white btn-round" value="<?php echo plugin_lang_get( 'add_button' ) ?>" />
            </div>
        </div>
    </form>
</div>


<?php
layout_page_end();
