<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

form_security_validate( 'event_google_sync' );

$f_event_id = gpc_get_int( 'event_id' );

event_ensure_exists( $f_event_id );

if( event_is_synchronized_with_google( $f_event_id ) || event_google_get_id( $f_event_id ) != NULL ) {
    event_google_update( event_get( $f_event_id ) );
} else {
    event_google_add( $f_event_id, event_get_field( $f_event_id, 'author_id' ), event_get_members( $f_event_id ) );
}

form_security_purge( 'event_google_sync' );

layout_page_header_begin();

layout_page_header_end();

layout_page_begin( plugin_page( 'event_add_page' ) );


html_operation_successful( plugin_page( 'view', TRUE ) . "&event_id=" . $f_event_id, plugin_lang_get( 'update_successful_button' ) );

html_meta_redirect( plugin_page( 'view', TRUE ) . "&event_id=" . $f_event_id );

layout_page_end();
