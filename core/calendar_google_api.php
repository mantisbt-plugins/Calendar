<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

function print_google_calendar_list() {
    $t_current_user_id = auth_get_current_user_id();
    $t_config_list     = plugin_config_get( 'google_calendar_sync_id', "", FALSE, $t_current_user_id );
    $service           = new Google_Service_Calendar( getClient() );
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

    $client->setAuthConfig( plugin_file_path( 'client.json', 'Calendar' ) );

    $client->addScope( Google_Service_Calendar::CALENDAR );
    $client->setRedirectUri( config_get_global( 'path' ) . plugin_page( 'user_config_google', TRUE ) );
    $client->setAccessType( 'offline' );
    $client->setApprovalPrompt( 'force' );
    $client->setState( form_security_token( 'calendar_config_edit' ) );
    $auth_url = $client->createAuthUrl();
//    header( 'Location: ' . filter_var( $auth_url, FILTER_SANITIZE_URL ) );
    return filter_var( $auth_url, FILTER_SANITIZE_URL );
}

function getClient() {
    $t_oauth = plugin_config_get( 'oauth_key', NULL, FALSE, auth_get_current_user_id() );

    $client = new Google_Client();
    $client->setAuthConfig( plugin_file_path( 'client.json', 'Calendar' ) );
    $client->addScope( Google_Service_Calendar::CALENDAR );
//    $client->setRedirectUri( "https://sd.sibprofi.ru/sdtest/plugin.php?page=Calendar/user_config" );
    $client->setRedirectUri( config_get_global( 'path' ) . plugin_page( 'user_config_google', TRUE ) );

    $client->setAccessType( 'offline' );
    $client->setIncludeGrantedScopes( true );
    $client->setState( form_security_token( 'calendar_config_edit' ) );
    $client->refreshToken( $t_oauth["refresh_token"] );

    if( $client->isAccessTokenExpired() ) {
        $client->fetchAccessTokenWithRefreshToken( $client->getRefreshToken() );
        plugin_config_set( 'oauth_key', $client->getAccessToken(), auth_get_current_user_id() );
    }
    return $client;
}
