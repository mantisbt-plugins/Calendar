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

form_security_validate( 'event_delete' );

$f_event_id    = gpc_get_int( 'event_id' );
$f_date_select = gpc_get_int( 'date' );
$f_from_bug_id   = gpc_get_int( 'from_bug_id', 0 );

event_ensure_exists( $f_event_id );

if( event_is_recurrences( $f_event_id ) ) {

    event_occurrence_ensure_exist( $f_event_id, $f_date_select );
    $t_range = helper_ensure_event_update_confirmed( plugin_lang_get( 'delete_event_sure_msg' ) );
} else {
    $t_range = 'ALL';
}

$t_event_data    = event_get( $f_event_id );
$t_bugs_attached = event_get_attached_bugs_id( $t_event_data->id );

switch( $t_range ) {

    case 'THIS':
        $t_rset_current = new \RRule\RSet( $t_event_data->recurrence_pattern );
        if( $t_rset_current->count() == 1 ) {
            $t_event_data->delete();
            event_member_delete( $t_event_data->id );
            event_detach_issue( $t_event_data->id, $t_bugs_attached );
            event_google_delete( $t_event_data );
            break;
        }
        $t_rset_current->addExDate( $f_date_select );
        $t_event_data->recurrence_pattern = $t_rset_current->rfcString();

        $t_event_data->update();
        event_google_update( $t_event_data );
        break;

    case 'THISANDFUTURE':

        if( $t_event_data->date_from == $f_date_select ) {
            $t_event_data->delete();
            event_member_delete( $t_event_data->id );
            event_detach_issue( $t_event_data->id, $t_bugs_attached );
            event_google_delete( $t_event_data );
            break;
        }

        $t_rset_new            = new \RRule\RSet();
        $t_event_data->date_to = $f_date_select - 1;

        $t_rset_current = new \RRule\RSet( $t_event_data->recurrence_pattern );
        $t_rrules       = $t_rset_current->getRRules();

        foreach( $t_rrules as $t_rrule ) {
            $t_rule          = $t_rrule->getRule();
            $t_rule['UNTIL'] = $t_event_data->date_to;
            $t_rrules_new    = new RRule\RRule( $t_rule );
            $t_rset_new->addRRule( $t_rrules_new );
        }

        $t_exdates_current = $t_rset_current->getExDates();
        foreach( $t_exdates_current as $t_exdate ) {
            $t_rset_new->addExDate( $t_exdate );
        }

        if( $t_rset_new->count() == 0 ) {
            $t_event_data->delete();
            event_member_delete( $t_event_data->id );
            event_detach_issue( $t_event_data->id, $t_bugs_attached );
            event_google_delete( $t_event_data );
            break;
        }

        $t_event_data->recurrence_pattern = $t_rset_new->rfcString();
        $t_event_data->update();

        event_update_date( $t_event_id );

        event_google_update( $t_event_data );

        break;

    case 'ALL':
    default:
        helper_ensure_confirmed( plugin_lang_get( 'delete_event_sure_msg' ), plugin_lang_get( 'delete_event_button' ) );

        event_member_delete( $t_event_data->id );

        event_detach_issue( $t_event_data->id, $t_bugs_attached );

        event_google_delete( $t_event_data );
        
        $t_event_data->delete();
}

form_security_purge( 'event_delete' );

if( $f_from_bug_id != 0 && bug_exists( $f_from_bug_id ) ) {
    print_successful_redirect_to_bug( $f_from_bug_id );
} else {
    print_header_redirect( plugin_page( 'calendar_user_page', TRUE ) . "&week=" . date( "W", $f_date_select ) );
}
