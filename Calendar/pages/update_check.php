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

# Triggered by the button on the configuration page; the outcome is
# shown there, so this page only runs the check and goes straight back.
auth_reauthenticate();
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );
form_security_validate( 'update_check' );

calendar_update_check();

form_security_purge( 'update_check' );

print_header_redirect( plugin_page( 'config_page', TRUE ) . '#update_check' );
