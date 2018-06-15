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

function event_google_add( $p_event_id, $p_member ) {
    if( plugin_config_get( 'google_calendar_sync_id', NULL, FALSE, $p_member ) == NULL ) {
        return;
    }
    try {
        $service = new Google_Service_Calendar( getClient( $p_member ) );

        $event = new Google_Service_Calendar_Event( array(
                                  'summary' => project_get_field( event_get_field( $p_event_id, 'project_id' ), 'name' ) . ": " . event_get_field( $p_event_id, 'name' ),
//                          'location'    => '800 Howard St., San Francisco, CA 94103',
//                          'description' => 'A chance to hear more about Google\'s developer products.',
                                  'start'   => array(
                                                            'dateTime' => date( "c", event_get_field( $p_event_id, 'date_from' ) ),
                                                            'timeZone' => date_default_timezone_get(),
                                  ),
                                  'end'     => array(
                                                            'dateTime' => date( "c", event_get_field( $p_event_id, 'date_to' ) ),
                                                            'timeZone' => date_default_timezone_get(),
                                  ),
//                              'recurrence'  => array(
//                                                        'RRULE:FREQ=DAILY;COUNT=2'
//                              ),
                ) );

        $calendarId = plugin_config_get( 'google_calendar_sync_id', NULL, FALSE, $p_member );
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
