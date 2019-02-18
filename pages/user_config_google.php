<?php
# Copyright (c) 2019 Grigoriy Ermolaev (igflocal@gmail.com)
# 
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

$f_oauth_key = gpc_get_string( 'code', NULL );
$f_state     = json_decode( gpc_get_string( 'state', NULL ), TRUE );

try {
    $client = new Google_Client();
    $client->setApplicationName( "MantisBT Calendar plugin" );
    $client->setAuthConfig( json_decode( plugin_config_get( 'google_client_secret' ), TRUE ) );

    $client->setRedirectUri( config_get_global( 'path' ) . plugin_page( 'user_config_google', TRUE ) );
    $client->setAccessType( 'offline' );
    $client->setApprovalPrompt( 'force' );

    $accessToken = $client->fetchAccessTokenWithAuthCode( $f_oauth_key );
    if( array_key_exists('error', $accessToken) ) {
        throw new InvalidArgumentException( $accessToken['error_description'] );
    }
    plugin_config_set( 'oauth_key', $accessToken, auth_get_current_user_id() );

    foreach( $f_state as $key => $t_value ) {

        switch( $key ) {

            case 'user_config_google':
                $t_redirect_url = plugin_page( 'user_config_page', true );
                break;

            case 'event_add':
                $t_event_id     = $t_value;
                event_google_add( $t_event_id, event_get_field( $t_event_id, 'author_id' ), event_get_members( $t_event_id ) );
                $t_redirect_url = plugin_page( 'view', true ) . '&event_id=' . $t_event_id;
                break;

            case 'event_update':
                $t_event        = event_get( $t_value );
                event_google_update( $t_event );
                $t_redirect_url = plugin_page( 'view', true ) . '&event_id=' . $t_event->id;
                break;

            case 'event_delete':
                $t_event        = event_get( $t_value );
                event_google_delete( $t_event );
                $t_redirect_url = plugin_page( 'calendar_user_page', true );
                break;
        }
    }
    layout_page_header( null, $t_redirect_url );
    layout_page_begin( $t_redirect_url );

    html_operation_successful( $t_redirect_url );
} catch( Exception $ex ) {

    $t_redirect_url = plugin_page( 'user_config_page', true );
    layout_page_header( null, $t_redirect_url );
    layout_page_begin( $t_redirect_url );
    html_operation_failure( $t_redirect_url, sprintf( plugin_lang_get( 'config_user_google_access_denie' ), $ex->getMessage() ) );
}

layout_page_end();
