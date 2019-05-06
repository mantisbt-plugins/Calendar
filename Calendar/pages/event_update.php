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

access_ensure_global_level( plugin_config_get( 'update_event_threshold' ) );

form_security_validate( 'event_update' );

$f_event_id          = gpc_get_int( 'event_id' );
$f_event_time_start  = gpc_get_int( 'event_time_start' );
$f_event_time_finish = gpc_get_int( 'event_time_finish' );
$f_date              = gpc_get_int( 'date' );
$f_freq              = gpc_get_string( 'selected_freq', 'NO_REPEAT' );
$f_interval          = gpc_get_int( 'interval_value' );
$f_until             = strtotime( gpc_get_string( 'date_ending_repetition', NULL ) );
$f_bugs              = gpc_get_int_array( 'bugs_add', array( 0 ) );

event_ensure_exists( $f_event_id );

$t_event_parent_data = event_get( $f_event_id );
$t_event_child_data  = clone $t_event_parent_data;

$t_event_child_data->name            = gpc_get_string( 'name_event' );
$t_event_child_data->activity        = "Y";
$t_event_child_data->changed_user_id = auth_get_current_user_id();
$t_event_child_data->date_from       = strtotime( gpc_get_string( 'date_event' ), NULL ) + $f_event_time_start;
$t_event_child_data->duration        = $f_event_time_finish - $f_event_time_start;

if( event_is_recurrences( $f_event_id ) ) {

    event_occurrence_ensure_exist( $f_event_id, $f_date );
    $t_range = helper_ensure_event_update_confirmed( plugin_lang_get( 'update_event_sure_msg' ) );
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

        $t_rset_child                            = new \RRule\RSet( $t_event_parent_data->recurrence_pattern );
        $t_rset_child->addExDate( $f_date );
        $t_event_parent_data->recurrence_pattern = $t_rset_child->rfcString();

        $t_event_parent_data->update();
        event_google_update( $t_event_parent_data );

        event_attach_issue( $t_event_child_id, $f_bugs );

        $t_event_members_current = event_get_members( $t_event_parent_data->id );
        foreach( $t_event_members_current as $t_event_member ) {
            event_member_add( $t_event_child_id, $t_event_member );
        }
        event_google_add( $t_event_child_id, $t_event_child_data->author_id, $t_event_members_current );

        break;

    case 'THISANDFUTURE':

        $t_event_child_data->author_id = auth_get_current_user_id();
        $t_event_child_data->parent_id = $t_event_parent_data->id;

        switch( $f_freq ) {

            case 'DAILY':
            case 'WEEKLY':
            case 'MONTHLY':
            case 'YEARLY':
                $t_event_child_data->date_to = $f_until == NULL ? strtotime( '01-01-2038' ) + $f_event_time_finish : $f_until + $f_event_time_finish;

                $t_rset_new = new \RRule\RSet();

                $t_rrule = new RRule\RRule( array(
                                          'DTSTART'  => $t_event_child_data->date_from,
                                          'UNTIL'    => $t_event_child_data->date_to,
                                          'FREQ'     => $f_freq,
                                          'INTERVAL' => $f_interval
                        ) );
                $t_rset_new->addRRule( $t_rrule );

                $t_event_child_data->recurrence_pattern = $t_rset_new->rfcString();

                break;

            default :
                $t_event_child_data->date_to = $f_until == NULL ? strtotime( gpc_get_string( 'date_event' ) ) + $f_event_time_finish : $f_until + $f_event_time_finish;
        }

        $t_event_child_id = $t_event_child_data->create();

        event_attach_issue( $t_event_child_id, $f_bugs );

        $t_event_members_current = event_get_members( $t_event_parent_data->id );
        foreach( $t_event_members_current as $t_event_member ) {
            event_member_add( $t_event_child_id, $t_event_member );
        }

        event_google_add( $t_event_child_id, $t_event_child_data->author_id, $t_event_members_current );

        $t_rset_parent_old           = new \RRule\RSet( $t_event_parent_data->recurrence_pattern );
        $t_rrules_parent_old         = $t_rset_parent_old->getRRules();
        $t_rrule_parent_old          = $t_rrules_parent_old[0]->getRule();
        $t_rrule_parent_old['UNTIL'] = strtotime( date( 'Y-m-d', $t_event_child_data->date_from ) );
        $t_rrule_parent_new          = new RRule\RRule( $t_rrule_parent_old );

        $t_rset_parent = new \RRule\RSet();
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

        switch( $f_freq ) {
            case 'DAILY':
            case 'WEEKLY':
            case 'MONTHLY':
            case 'YEARLY':
                $t_event_child_data->date_to = $f_until == NULL ? strtotime( '01-01-2038' ) + $f_event_time_finish : $f_until + $f_event_time_finish;

                $t_rset    = new \RRule\RSet( $t_event_child_data->recurrence_pattern );
                $t_exdates = $t_rset->getExDates();

                $t_rset_new = new \RRule\RSet();


                $t_rrule = new RRule\RRule( array(
                                          'DTSTART'  => $t_event_child_data->date_from,
                                          'UNTIL'    => $t_event_child_data->date_to,
                                          'FREQ'     => $f_freq,
                                          'INTERVAL' => $f_interval
                        ) );
                $t_rset_new->addRRule( $t_rrule );
                if( count( $t_exdates ) != 0 ) {
                    $t_rset_new->addExDate( $t_exdates );
                }
                $t_event_child_data->recurrence_pattern = $t_rset_new->rfcString();

                break;
            default :
                $t_event_child_data->date_to = $f_until == NULL ? strtotime( gpc_get_string( 'date_event' ) ) + $f_event_time_finish : $f_until + $f_event_time_finish;
        }

        if( $t_event_child_data != $t_event_parent_data ) {
            $t_event_child_data->update();
        }

        $t_event_child_id = $t_event_child_data->id;

        sort( $f_bugs );

        $t_current_bugs = event_get_attached_bugs_id( $t_event_child_data->id );

        if( $f_bugs != $t_current_bugs ) {
            
            event_detach_issue( $t_event_child_data->id, array_diff( $t_current_bugs, $f_bugs ));
            event_attach_issue( $t_event_child_data->id, array_diff( $f_bugs, $t_current_bugs ) );
        }
        if( $t_event_child_data != $t_event_parent_data || $f_bugs != $t_current_bugs ) {
            event_google_update( $t_event_child_data );
        }
}

layout_page_header_begin();

layout_page_header_end();

layout_page_begin( plugin_page( 'event_update_page' ) );

html_operation_successful( plugin_page( 'view' ) . "&event_id=" . $t_event_child_id . "&date=" . $t_event_child_data->date_from, plugin_lang_get( 'update_successful_button' ) );

html_meta_redirect( plugin_page( 'view', TRUE ) . "&event_id=" . $t_event_child_id . "&date=" . $t_event_child_data->date_from );

form_security_purge( 'event_update' );

layout_page_end();