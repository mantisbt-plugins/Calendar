/*
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
*/

/*
 * Month view date picker tweaks.
 */

$(document).ready(function () {
    // The month selector exists in the month view only; in the week view the
    // element is absent and the picker instance is undefined.
    var t_picker = $('#view_month_date_select').data('DateTimePicker');

    if (t_picker) {
        t_picker.viewMode('months');
    }
});
