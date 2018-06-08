<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

form_security_validate( 'event_member_add' );

$f_event_id  = gpc_get_int( 'event_id' );
$f_usernames = gpc_get_int_array( 'user_ids', array( 0 ) );

event_ensure_exists($f_event_id);

foreach($f_usernames as $t_user_id) {
    event_member($f_event_id, $t_user_id);
}

form_security_purge( 'event_member_add' );

layout_page_header_begin();

layout_page_header_end();

layout_page_begin( plugin_page( 'event_add_page' ) );


html_operation_successful( plugin_page( 'view' ) . "&event_id=" . $f_event_id, plugin_lang_get( 'update_successful_button' ) );

html_meta_redirect( plugin_page( 'view' ) . "&event_id=" . $f_event_id );

layout_page_end();
