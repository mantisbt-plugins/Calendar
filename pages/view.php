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

event_ensure_exists( $f_event_id );

$g_project_override = event_get_field( $f_event_id, 'project_id' );

$t_event = event_get( $f_event_id );

//access_ensure_bug_level(plugin_config_get( 'view_event_threshold' ), $f_bug_id );

compress_enable();

layout_page_header( bug_format_id( $t_event->id ) . ': ' . $t_event->name );
layout_page_begin( plugin_page( 'calendar_user_page' ) );

$t_event_id   = $f_event_id;
$t_form_title = plugin_lang_get( 'event_view_title' );

$t_formatted_event_id = string_display_line( bug_format_id( $f_event_id ) );
$t_project_name       = string_display_line( project_get_name( $t_event->project_id ) );
$t_date_event         = date( config_get( 'short_date_format' ), $t_event->date_from );
$t_last_updated       = date( config_get( 'normal_date_format' ), $t_event->date_changed );

#
# Start of Template
#

echo '<div class="col-md-12 col-xs-12">';
echo '<div class="space-10"></div>';
echo '<div class="widget-box widget-color-blue2">';
echo '<div class="widget-header widget-header-small">';
echo '<h4 class="widget-title lighter">';
echo '<i class="ace-icon fa fa-bars"></i>';
echo $t_form_title;
echo '</h4>';
echo '</div>';

echo '<div class="widget-body">';
echo '<div class="widget-main no-padding">';
echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-condensed">';



$t_access_level_current_user = access_get_global_level( auth_get_current_user_id() );

if( access_compare_level( $t_access_level_current_user, plugin_config_get( 'calendar_edit_threshold' ) ) ) {
    echo '<tfoot>';
    echo '<tr class="noprint"><td colspan="2">';

    print_small_button( plugin_page( 'event_update_page' ) . "&event_id=" . $f_event_id, lang_get( 'update_bug_button' ) );
    print_small_button( plugin_page( 'event_delete' ) . "&event_id=" . $f_event_id . form_security_param( 'event_delete' ), lang_get( 'delete_bug_button' ) );

    echo '</tr>';
    echo '</tfoot>';
}



echo '<tbody>';

# Labels
#  Event ID
echo '<tr>';
echo '<th class="bug-reporter category" width="100px">', plugin_lang_get( 'id' ), '</th>';
echo '<td class="bug-reporter">';
echo $t_formatted_event_id;
echo '</td>';
echo '</tr>';

# Project
echo '<tr>';
echo '<th class="bug-reporter category">', lang_get( 'email_project' ), '</th>';
echo '<td class="bug-reporter" >';
echo $t_project_name;
echo '</td>';
echo '</tr>';

# Date Updated
echo '<tr>';
echo '<th class="bug-reporter category">', plugin_lang_get( 'last_update' ), '</th>';
echo '<td class="bug-reporter" >';
echo strtotime( $t_last_updated ) == 0 ? plugin_lang_get( 'not_last_update' ) : $t_last_updated;
echo '</td>';
echo '</tr>';
#
# Reporter,
# 

echo '<tr>';
echo '<th class="bug-reporter category">', plugin_lang_get( 'author' ), '</th>';
echo '<td class="bug-reporter" >';
print_user( $t_event->author_id );
echo '</td>';
echo '</tr>';

echo '<tr>';
echo '<th class="bug-reporter category">', plugin_lang_get( 'date_event' ), '</th>';
echo '<td class="bug-reporter">';
echo $t_date_event;
echo '</td>';
echo '</tr>';

echo '<tr>';
echo '<th class="bug-reporter category">', plugin_lang_get( 'time_event' ), '</th>';
echo '<td class="bug-reporter" >';
$t_time_start  = date( "H:i", event_get_field( $t_event_id, "date_from" ) );
$t_time_finish = date( "H:i", event_get_field( $t_event_id, "date_to" ) );
echo $t_time_start . " " . plugin_lang_get( 'to_time' ) . " " . $t_time_finish;
echo '</td>';
echo '</tr>';

echo '<tr>';
echo '<th class="bug-reporter category">', plugin_lang_get( 'name_event' ), '</th>';
echo '<td class="bug-reporter" >';
echo $t_formatted_event_id . ": " . $t_event->name;
echo '</td>';
echo '</tr>';

echo '</tbody></table>';
echo '</div></div></div></div></div>';

//relationship_event_view_box( $t_event->id );
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
                    <?php
// echo $t_relationships_html; 

                    echo "<ul class=\"tasks-list\">";

                    $t_bugs_id = get_bugs_id_from_event( $t_event_id ); // получим массив задач для текущего события
                    if( $t_bugs_id ) {

                        foreach( $t_bugs_id as $t_bug_id ) {
                            if( !access_has_bug_level( config_get( 'view_bug_threshold' ), $t_bug_id ) ) {
                                continue;
                            }

                            $t_bug_summary = bug_get_field( $t_bug_id, "summary" );
                            $t_bug_url     = "/view.php?id=" . $t_bug_id;

                            echo "<li><a href=\"" . $t_bug_url . "\" target=\"_self\" style=\"background-color:" . get_status_color( bug_get_field( $t_bug_id, "status" ) ) . ";\">" . $t_bug_id . ": " . $t_bug_summary . "</a></li>";
                        }
                    }
                    echo "</ul>";
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>


<?php
layout_page_end();
