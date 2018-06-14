<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

$client = new Google_Client();

$client->setAuthConfig( plugin_file_path( 'client.json', 'Calendar' ) );

$client->addScope( Google_Service_Calendar::CALENDAR );
$client->setRedirectUri( "https://sd.sibprofi.ru/sdtest/plugin.php?page=Calendar/user_config" );
$client->setAccessType( 'offline' );
$client->setApprovalPrompt('force');
$client->setState( form_security_token( 'calendar_config_edit' ) );
$auth_url = $client->createAuthUrl();
header( 'Location: ' . filter_var( $auth_url, FILTER_SANITIZE_URL ) );


//
//$service = new Google_Service_Calendar_CalendarList($client);
//
//$optParams = array('filter' => 'free-ebooks');
//$results = $service->;
//
//echo $results;