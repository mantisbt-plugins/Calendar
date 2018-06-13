<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Validate the security token for the given form name based on tokens
 * stored in the user's session.  While checking stored tokens, any that
 * are more than 3 days old will be purged.
 * @param string $p_form_name Form name.
 * @return boolean Form is valid
 */
function form_security_validate_google( $p_form_name ) {
	if( PHP_CLI == php_mode() || OFF == config_get_global( 'form_security_validation' ) ) {
		return true;
	}

	$t_tokens = session_get( 'form_security_tokens', array() );

	# Short-circuit if we don't have any tokens for the given form name
	if( !isset( $t_tokens[$p_form_name] ) || !is_array( $t_tokens[$p_form_name] ) || count( $t_tokens[$p_form_name] ) < 1 ) {

		trigger_error( ERROR_FORM_TOKEN_INVALID, ERROR );
		return false;
	}

	# Get the form input
	$t_form_token = 'state';
	$t_input = gpc_get_string( $t_form_token, '' );

	# No form input
	if( '' == $t_input ) {
		trigger_error( ERROR_FORM_TOKEN_INVALID, ERROR );
		return false;
	}

	# Get the date claimed by the token
	$t_date = mb_substr( $t_input, 0, 8 );

	# Check if the token exists
	if( isset( $t_tokens[$p_form_name][$t_date][$t_input] ) ) {
		return true;
	}

	# Token does not exist
	trigger_error( ERROR_FORM_TOKEN_INVALID, ERROR );
	return false;
}