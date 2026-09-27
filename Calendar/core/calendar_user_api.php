<?php
# Copyright (c) 2026 Grigoriy Ermolaev (igflocal@gmail.com)
# Calendar plugin for MantisBT is free software:
# you can redistribute it and/or modify it under the terms of the GNU
# General Public License as published by the Free Software Foundation,
# either version 2 of the License, or (at your option) any later version.
#
# Calendar plugin for MantisBT is distributed in the hope
# that it will be useful, but WITHOUT ANY WARRANTY; without even the
# implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# See the GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Calendar plugin for MantisBT.
# If not, see <http://www.gnu.org/licenses/>.

function user_is_member_event( $p_user_id, $p_event_id ) {
    
    $t_event_member_table = plugin_table( 'event_member' );
    
    db_param_push();
    
    $t_query = "SELECT COUNT(*) FROM $t_event_member_table
				  WHERE user_id=" . db_param() . " AND event_id=" . db_param();

    $t_result = db_query( $t_query, array( (int) $p_user_id, (int) $p_event_id ) );

    if( 0 == db_result( $t_result ) ) {
        return false;
    } else {
        return true;
    }
}
