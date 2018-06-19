<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

function print_google_calendar_list() {
    $t_current_user_id = auth_get_current_user_id();
    $t_config_list     = plugin_config_get( 'google_calendar_sync_id', "", FALSE, $t_current_user_id );
    $service           = new Google_Service_Calendar( getClient( $t_current_user_id ) );
    $calendar_list     = $service->calendarList->listCalendarList();
    echo '<option value="0">' . plugin_lang_get( 'user_config_google_calendar_list' ) . '</option>';
    foreach( $calendar_list as $calendar ) {
        if( $calendar['id'] === $t_config_list ) {
            echo '<option selected value="' . $calendar['id'] . '">' . $calendar['summary'] . '</option>';
        } else {
            echo '<option value="' . $calendar['id'] . '">' . $calendar['summary'] . '</option>';
        }
    }
}

function get_response_google_url() {
    $client = new Google_Client();

    $client->setAuthConfig( json_decode( plugin_config_get( 'google_client_secret' ), TRUE ) );

    $client->addScope( Google_Service_Calendar::CALENDAR );
    $client->setRedirectUri( config_get_global( 'path' ) . plugin_page( 'user_config_google', TRUE ) );
    $client->setAccessType( 'offline' );
    $client->setApprovalPrompt( 'force' );
    $client->setState( form_security_token( 'calendar_config_edit' ) );
    $auth_url = $client->createAuthUrl();
//    header( 'Location: ' . filter_var( $auth_url, FILTER_SANITIZE_URL ) );
    return filter_var( $auth_url, FILTER_SANITIZE_URL );
}

function getClient( $p_user_id = NULL ) {

    if( $p_user_id == NULL ) {
        $t_user_id = auth_get_current_user_id();
    } else {
        $t_user_id = $p_user_id;
    }

    $t_oauth = plugin_config_get( 'oauth_key', NULL, FALSE, $t_user_id );

    $client = new Google_Client();
    $client->setAuthConfig( json_decode( plugin_config_get( 'google_client_secret' ), TRUE ) );
    $client->addScope( Google_Service_Calendar::CALENDAR );
    $client->setRedirectUri( config_get_global( 'path' ) . plugin_page( 'user_config_google', TRUE ) );

    $client->setAccessType( 'offline' );
    $client->setIncludeGrantedScopes( true );
    $client->setState( form_security_token( 'calendar_config_edit' ) );
    $client->refreshToken( $t_oauth["refresh_token"] );

    if( $client->isAccessTokenExpired() ) {
        try {
            $client->fetchAccessTokenWithRefreshToken( $client->getRefreshToken() );
            plugin_config_set( 'oauth_key', $client->getAccessToken(), $t_user_id );
        } catch( Exception $e ) {
            plugin_config_delete( 'oauth_key', $t_user_id );
        }
    }
    return $client;
}

function event_google_add( $p_event_id, $p_creator_id, $p_members_id ) {
    if( plugin_config_get( 'google_calendar_sync_id', NULL, FALSE, $p_creator_id ) == NULL ) {
        return;
    }
    $t_users_email = array();
    foreach( $p_members_id as $t_member_id ) {
        if( plugin_config_get( 'google_calendar_sync_id', NULL, FALSE, $t_member_id ) == NULL ) {
            continue;
        }
        $t_users_email[] = array( 'email'          => plugin_config_get( 'google_calendar_sync_id', NULL, FALSE, $t_member_id ),
                                  'responseStatus' => 'accepted',
                                  'organizer'      => plugin_config_get( 'google_calendar_sync_id', NULL, FALSE, $p_creator_id ) );
    }
    if( !in_array( $p_creator_id, $p_members_id ) ) {
        $t_users_email[] = array( 'email'          => plugin_config_get( 'google_calendar_sync_id', NULL, FALSE, $p_creator_id ),
                                  'responseStatus' => 'declined',
                                  'organizer'      => plugin_config_get( 'google_calendar_sync_id', NULL, FALSE, $p_creator_id ) );
    }

    try {
        $service = new Google_Service_Calendar( getClient( $p_creator_id ) );

        $event = new Google_Service_Calendar_Event( array(
                                  'summary'     => project_get_field( event_get_field( $p_event_id, 'project_id' ), 'name' ) . ": " . event_get_field( $p_event_id, 'name' ),
//                          'location'    => '800 Howard St., San Francisco, CA 94103',
                                  'description' => string_get_google_description( $p_event_id ),
                                  'start'       => array(
                                                            'dateTime' => date( "c", event_get_field( $p_event_id, 'date_from' ) ),
                                                            'timeZone' => date_default_timezone_get(),
                                  ),
                                  'end'         => array(
                                                            'dateTime' => date( "c", event_get_field( $p_event_id, 'date_to' ) ),
                                                            'timeZone' => date_default_timezone_get(),
                                  ),
                                  'attendees'   => $t_users_email,
//                              'recurrence'  => array(
//                                                        'RRULE:FREQ=DAILY;COUNT=2'
//                              ),
                ) );


        $calendarId = plugin_config_get( 'google_calendar_sync_id', NULL, FALSE, $p_creator_id );
        $event_data = $service->events->insert( $calendarId, $event );
    } catch( Exception $e ) {
        return;
    }

    $c_event_id        = (int) $p_event_id;
    $c_event_google_id = $event_data['id'];

    $t_google_sync_table = plugin_table( 'google_sync' );
    db_param_push();
    $t_query             = "INSERT INTO $t_google_sync_table ( event_id, google_id ) VALUES (" . db_param() . "," . db_param() . ")";
    db_query( $t_query, array( $c_event_id, $c_event_google_id ) );

    return true;
}

function event_google_update( CalendarEventData $p_update_event ) {
    $t_google_event_id    = event_google_get_id( $p_update_event->id );
    $t_creator_id         = $p_update_event->author_id;
    $t_google_calendar_id = plugin_config_get( 'google_calendar_sync_id', NULL, FALSE, $t_creator_id );
    $p_members_id         = event_get_members( $p_update_event->id );

    $t_users_email = array();
    foreach( $p_members_id as $t_member_id ) {
        if( plugin_config_get( 'google_calendar_sync_id', NULL, FALSE, $t_member_id ) == NULL ) {
            continue;
        }
        $t_users_email[] = array( 'email'          => plugin_config_get( 'google_calendar_sync_id', NULL, FALSE, $t_member_id ),
                                  'responseStatus' => 'accepted',
                                  'organizer'      => plugin_config_get( 'google_calendar_sync_id', NULL, FALSE, $t_creator_id ) );
    }
    if( !in_array( $t_creator_id, $p_members_id ) ) {
        $t_users_email[] = array( 'email'          => plugin_config_get( 'google_calendar_sync_id', NULL, FALSE, $t_creator_id ),
                                  'responseStatus' => 'declined',
                                  'organizer'      => plugin_config_get( 'google_calendar_sync_id', NULL, FALSE, $t_creator_id ) );
    }

    if( $t_google_calendar_id == NULL || $t_google_event_id == NULL ) {
        return;
    }

    try {
        $service = new Google_Service_Calendar( getClient( $t_creator_id ) );

        $t_existing_google_event = $service->events->get( plugin_config_get( 'google_calendar_sync_id', NULL, FALSE, $t_creator_id ), $t_google_event_id );

        $t_update_google_event = clone $t_existing_google_event;

        $t_update_google_event->setSummary( project_get_field( $p_update_event->project_id, 'name' ) . ": " . event_get_field( $p_update_event->id, 'name' ) );

        $t_update_google_event->setStart(
                new Google_Service_Calendar_EventDateTime( array(
                                  'dateTime' => date( "c", $p_update_event->date_from ),
                                  'timeZone' => date_default_timezone_get() ) )
        );

        $t_update_google_event->setEnd(
                new Google_Service_Calendar_EventDateTime( array(
                                  'dateTime' => date( "c", $p_update_event->date_to ),
                                  'timeZone' => date_default_timezone_get() ) )
        );

        $t_update_google_event->setDescription(
                string_get_google_description( $p_update_event->id )
        );
        $t_update_google_event->setAttendees( $t_users_email );

        $event_data = $service->events->update( $t_google_calendar_id, $t_google_event_id, $t_update_google_event );
    } catch( Exception $e ) {
        return;
    }
}

function event_google_delete( CalendarEventData $p_event ) {
    $t_google_event_id    = event_google_get_id( $p_event->id );
    $t_creator_id         = $p_event->author_id;
    $t_google_calendar_id = plugin_config_get( 'google_calendar_sync_id', NULL, FALSE, $t_creator_id );
    if( $t_google_event_id != NULL ) {
        try {
            $service = new Google_Service_Calendar( getClient( $t_creator_id ) );

            $service->events->delete( $t_google_calendar_id, $t_google_event_id );

            $t_google_sync_table = plugin_table( 'google_sync' );
            db_param_push();
            $t_query             = "DELETE FROM $t_google_sync_table WHERE google_id=" . db_param();
            db_query( $t_query, array( $t_google_event_id ) );
        } catch( Exception $e ) {
            return;
        }
    }
}

function event_google_get_id( int $p_event_id ) {
    $t_google_sync_table = plugin_table( 'google_sync' );

    db_param_push();

    $t_query = "SELECT google_id FROM $t_google_sync_table
				  WHERE event_id=" . db_param();

    $t_result = db_query( $t_query, array( (int) $p_event_id ) );

    return db_fetch_array( $t_result )['google_id'];
}

function event_is_synchronized_with_google( $p_event_id ) {



    $t_result = event_google_get_id( $p_event_id );

    if( 0 == $t_result ) {
        return false;
    } else {
        return true;
    }
}

function string_get_google_description( $p_event_id ) {
    $t_bugs_id = get_bugs_id_from_event( $p_event_id );

    foreach( $t_bugs_id as $t_bug_id ) {
        $t_description .= '<a href="' . string_get_bug_view_url_with_fqdn( $t_bug_id ) . '" >' . $t_bug_id . ': ' . bug_get_field( $t_bug_id, 'summary' ) . '</a><br><br>';
    }
    return $t_description;
}