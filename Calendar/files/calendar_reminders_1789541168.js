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
 * Rows of the reminder editor on the event pages and in the user settings.
 *
 * The markup, including every label, is rendered by the server; this script
 * only clones the hidden template row when the user asks for one more reminder
 * and drops a row again on the remove button. Pages without the container are
 * left alone.
 */
document.addEventListener("DOMContentLoaded", function () {
    var container = document.getElementById("calendar-reminder-rows");
    var addButton = document.getElementById("calendar-reminder-add");

    if (!container || !addButton) {
        return;
    }

    var template = container.querySelector(".calendar-reminder-template");
    var maxRows = parseInt(container.getAttribute("data-max-rows"), 10) || 0;

    if (!template) {
        return;
    }

    function countRows() {
        return container.querySelectorAll(".calendar-reminder-row:not(.calendar-reminder-template)").length;
    }

    var disabledBox = document.getElementById("calendar-reminder-disabled");

    function remindersSwitchedOff() {
        return disabledBox !== null && disabledBox.checked;
    }

    function refreshAddButton() {
        addButton.disabled = remindersSwitchedOff() || (maxRows > 0 && countRows() >= maxRows);
    }

    // the rows stay visible while switched off, but greyed out and out of the
    // submission, so that the previous setting is not lost by a stray click
    function refreshRows() {
        var off = remindersSwitchedOff();
        var rows = container.querySelectorAll(".calendar-reminder-row:not(.calendar-reminder-template)");
        var i, fields, j;

        for (i = 0; i < rows.length; i++) {
            rows[i].style.opacity = off ? "0.5" : "";
            fields = rows[i].querySelectorAll("input, select, button");

            for (j = 0; j < fields.length; j++) {
                fields[j].disabled = off;
            }
        }
    }

    if (disabledBox) {
        disabledBox.addEventListener("change", function () {
            refreshRows();
            refreshAddButton();
        });
    }

    addButton.addEventListener("click", function () {
        if (maxRows > 0 && countRows() >= maxRows) {
            return;
        }

        var row = template.cloneNode(true);
        var fields = row.querySelectorAll("input, select");
        var i;

        row.className = "calendar-reminder-row";
        row.style.display = "";

        // the template is kept out of the submission by disabled fields,
        // a real row has to be enabled again
        for (i = 0; i < fields.length; i++) {
            fields[i].disabled = false;
        }

        container.insertBefore(row, template);
        refreshAddButton();
    });

    container.addEventListener("click", function (event) {
        var button = event.target.closest(".calendar-reminder-remove");
        var row;

        if (!button) {
            return;
        }

        row = button.closest(".calendar-reminder-row");

        if (!row || row.classList.contains("calendar-reminder-template")) {
            return;
        }

        row.parentNode.removeChild(row);
        refreshAddButton();
    });

    refreshRows();
    refreshAddButton();
});
