<?php
# Copyright (c) 2025 Grigoriy Ermolaev (igflocal@gmail.com)
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

access_ensure_global_level( plugin_config_get( 'update_event_threshold' ) );

form_security_validate( 'event_insert' );

$f_event_id = gpc_get_int( 'event_id' );
$f_date     = gpc_get_int( 'date' );
$t_freq     = gpc_get_string( 'selected_freq', 'NO_REPEAT' );
$f_bug      = gpc_get_int( 'bug_id' );

bug_ensure_exists( $f_bug );

event_ensure_exists( $f_event_id );

$t_event_parent_data = event_get( $f_event_id );
$t_event_child_data  = clone $t_event_parent_data;

$t_event_child_data->changed_user_id = auth_get_current_user_id();
$t_event_child_data->date_from       = $f_date;

if( event_is_recurrences( $f_event_id ) ) {

    event_occurrence_ensure_exist( $f_event_id, $f_date );
    $t_range = helper_ensure_event_update_confirmed( plugin_lang_get( 'update_event_sure_msg' ) );

    $t_rset_parent_old   = new \RRule\RSet( $t_event_parent_data->recurrence_pattern );
    $t_rrules_parent_old = $t_rset_parent_old->getRRules();
    $t_rrule_parent_old  = $t_rrules_parent_old[0]->getRule();

    $t_freq     = $t_rrule_parent_old['FREQ'];
    $t_interval = $t_rrule_parent_old['INTERVAL'];
} else {
    $t_range = 'ALL';
}
switch( $t_range ) {

    case 'THIS':

        $t_event_child_data->parent_id          = $t_event_parent_data->id;
        $t_event_child_data->recurrence_pattern = '';
        $t_event_child_data->author_id          = auth_get_current_user_id();
        $t_event_child_data->date_to            = $t_event_child_data->date_from + $t_event_child_data->duration;

        $t_event_child_id = $t_event_child_data->create();

        $t_rset_child                            = new CalendarPluginRRuleExt\RSetExt( $t_event_parent_data->recurrence_pattern );
        $t_rset_child->addExDate( $f_date );
        $t_event_parent_data->recurrence_pattern = $t_rset_child->rfcString();

        $t_event_parent_data->update();
        event_google_update( $t_event_parent_data );

        $t_bugs   = event_get_attached_bugs_id( $t_event_parent_data->id );
        $t_bugs[] = $f_bug;
        event_attach_issue( $t_event_child_id, $t_bugs );

        $t_event_members_current = event_get_members( $t_event_parent_data->id );
        foreach( $t_event_members_current as $t_event_member ) {
            event_member_add( $t_event_child_id, $t_event_member );
        }
        event_google_add( $t_event_child_id, $t_event_child_data->author_id, $t_event_members_current );

        break;

    case 'THISANDFUTURE':

        $t_event_child_data->author_id = auth_get_current_user_id();
        $t_event_child_data->parent_id = $t_event_parent_data->id;



        switch( $t_freq ) {

            case 'DAILY':
            case 'WEEKLY':
            case 'MONTHLY':
            case 'YEARLY':
                $t_rset_new = new CalendarPluginRRuleExt\RSetExt();

                $t_rrule = new RRule\RRule( array(
                                          'DTSTART'  => $t_event_child_data->date_from,
                                          'UNTIL'    => $t_event_child_data->date_to,
                                          'FREQ'     => $t_freq,
                                          'INTERVAL' => $t_interval
                        ) );
                $t_rset_new->addRRule( $t_rrule );

                $t_event_child_data->recurrence_pattern = $t_rset_new->rfcString();

                break;

            default :
                $t_event_child_data->date_to = $f_until == NULL ? strtotime( gpc_get_string( 'date_event' ) ) + $f_event_time_finish : $f_until + $f_event_time_finish;
        }

        $t_event_child_id = $t_event_child_data->create();

        $t_bugs   = event_get_attached_bugs_id( $t_event_parent_data->id );
        $t_bugs[] = $f_bug;

        event_attach_issue( $t_event_child_id, $t_bugs );

        $t_event_members_current = event_get_members( $t_event_parent_data->id );
        foreach( $t_event_members_current as $t_event_member ) {
            event_member_add( $t_event_child_id, $t_event_member );
        }

        event_google_add( $t_event_child_id, $t_event_child_data->author_id, $t_event_members_current );

        $t_rrule_parent_old['UNTIL'] = strtotime( date( 'Y-m-d', $t_event_child_data->date_from ) );
        $t_rrule_parent_new          = new RRule\RRule( $t_rrule_parent_old );

        $t_rset_parent = new CalendarPluginRRuleExt\RSetExt();
        $t_rset_parent->addRRule( $t_rrule_parent_new );

        $t_exdates_parent_old = $t_rset_parent_old->getExDates();
        foreach( $t_exdates_parent_old as $t_exdate_parent_old ) {
            $t_rset_parent->addExDate( $t_exdate_parent_old );
        }
        $t_event_parent_data->recurrence_pattern = $t_rset_parent->rfcString();

        $t_event_parent_data->update();

        event_google_update( $t_event_parent_data );

        break;

    case 'ALL':
    default :
        event_attach_issue( $t_event_parent_data->id, array( 0 => $f_bug ) );

        $t_event_parent_data->update();

        event_google_update( $t_event_child_data );

}

form_security_purge( 'event_insert' );

print_header_redirect_view( $f_bug );