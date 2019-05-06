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

function calendar_print_manage_config_menu( $p_page = '' ) {
	if( !access_has_project_level( plugin_config_get( 'manage_calendar_threshold' ) ) ) {
		return;
	}

	$t_pages = array();

	$t_pages['config_page.php'] = array( 'url'   => plugin_page( 'config_page' ),
	                                     'label' => 'config_title' );

	$t_pages['config_work_threshold_page.php'] = array( 'url'   => plugin_page( 'config_work_threshold_page' ), 
	                                                    'label' => 'manage_threshold_config' );

	echo '<div class="space-10"></div>' . "\n";
	echo '<div class="center">' . "\n";
	echo '<div class="btn-toolbar inline">' . "\n";
	echo '<div class="btn-group">' . "\n";

	foreach ( $t_pages as $t_page ) {
		$t_active =  $t_page['url'] == $p_page ? 'active' : '';
		echo '<a class="btn btn-sm btn-white btn-primary ' . $t_active . '" href="'. $t_page['url'] .'">' . "\n";
		echo plugin_lang_get( $t_page['label'] );
		echo '</a>' . "\n";
	}


	echo '</div>' . "\n";
	echo '</div>' . "\n";
	echo '</div>' . "\n";
}