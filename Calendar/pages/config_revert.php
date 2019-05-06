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

form_security_validate( 'config_revert' );

auth_reauthenticate();

access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

$f_project_id = gpc_get_int( 'project', 0 );
$f_revert = gpc_get_string( 'revert', '' );
$f_return = gpc_get_string( 'return' );

$t_access = true;
$t_revert_vars = explode( ',', $f_revert );
array_walk( $t_revert_vars, 'trim' );

if( '' != $f_revert ) {
	# Confirm with the user
	helper_ensure_confirmed( lang_get( 'config_delete_sure' ) . lang_get( 'word_separator' ) .
		string_html_specialchars( implode( ', ', $t_revert_vars ) ) . lang_get( 'word_separator' ) . lang_get( 'in_project' ) . lang_get( 'word_separator' ) . project_get_name( $f_project_id ),
		lang_get( 'delete_config_button' ) );

	foreach ( $t_revert_vars as $t_revert ) {
		plugin_config_delete( $t_revert, ALL_USERS, $f_project_id );
	}
}

form_security_purge( 'config_revert' );

$t_redirect_url = $f_return;

layout_page_header( null, $t_redirect_url );

layout_page_begin();

html_operation_successful( $t_redirect_url );

layout_page_end();
