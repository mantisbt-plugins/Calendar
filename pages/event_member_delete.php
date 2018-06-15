<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

form_security_validate( 'event_member_delete' );

$f_event_id = gpc_get_int( 'event_id' );
$t_event    = event_get( $f_event_id );
$f_user_id  = gpc_get_int( 'user_id', NO_USER );

$t_logged_in_user_id = auth_get_current_user_id();

if( $f_user_id === NO_USER ) {
    $t_user_id = $t_logged_in_user_id;
} else {
    user_ensure_exists( $f_user_id );
    $t_user_id = $f_user_id;
}

if( user_is_anonymous( $t_user_id ) ) {
    trigger_error( ERROR_PROTECTED_ACCOUNT, E_USER_ERROR );
}

event_ensure_exists( $f_event_id );

if( $t_event->project_id != helper_get_current_project() ) {
    # in case the current project is not the same project of the bug we are viewing...
    # ... override the current project. This to avoid problems with categories and handlers lists etc.
    $g_project_override = $t_event->project_id;
}

if( $t_logged_in_user_id == $t_user_id ) {
    access_ensure_event_level( plugin_config_get( 'member_event_threshold' ), $f_event_id );
} else {
    access_ensure_event_level( plugin_config_get( 'member_delete_others_event_threshold' ), $f_event_id );
}

if( count( event_get_members( $f_event_id ) ) <= 1 ) {
    trigger_error( 'ERROR_MIN_MEMBERS', ERROR );
}

event_unmember( $f_event_id, $t_user_id );

form_security_purge( 'event_member_delete' );

layout_page_header_begin();

layout_page_header_end();

layout_page_begin( plugin_page( 'view' ) );

html_operation_successful( plugin_page( 'view' ) . "&event_id=" . $f_event_id, plugin_lang_get( 'update_successful_button' ) );

html_meta_redirect( plugin_page( 'view', TRUE ) . "&event_id=" . $f_event_id );

layout_page_end();
