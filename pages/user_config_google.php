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


//form_security_validate_google( 'calendar_config_edit' );


//auth_reauthenticate();
//access_ensure_global_level( config_get( 'update_event_threshold' ) );

$f_oauth_key = gpc_get_string( 'code' );

$client      = new Google_Client();
$client->setAuthConfig( plugin_file_path( 'client.json', 'Calendar' ) );
//$client->addScope( Google_Service_Calendar::CALENDAR );
$client->setRedirectUri( "https://sd.sibprofi.ru/sdtest/plugin.php?page=Calendar/user_config_google" );
$client->setAccessType( 'offline' );
//$client->setApprovalPrompt( 'force' );
//$client->setIncludeGrantedScopes( true );
//$auth_url    = $client->createAuthUrl();
//$client->setState( form_security_token( 'calendar_config_edit' ) );
$accessToken = $client->fetchAccessTokenWithAuthCode( $f_oauth_key );

plugin_config_set( 'oauth_key', $accessToken, auth_get_current_user_id() );

form_security_purge( plugin_page( 'config', true ) );

$t_redirect_url = plugin_page( 'user_config_page', true );

layout_page_header( null, $t_redirect_url );

layout_page_begin( $t_redirect_url );

html_operation_successful( $t_redirect_url );

layout_page_end();
