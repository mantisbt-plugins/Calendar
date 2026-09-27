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
 * Time range selection in the week view of the calendar
 * and the jump to event creation on a click over the selection.
 *
 * The gesture is recognized by behaviour, not by pointer type: a press
 * released without movement is a tap, a press with movement is a drag.
 * So a mouse selects the range by dragging, a touch screen by two taps
 * (the first one sets the start of the range, the second one the end).
 */
document.addEventListener("DOMContentLoaded", function () {
    var tables = document.querySelectorAll("table.calendar-user.week[data-add-event-url]");

    // Movement in pixels that turns a press into a drag and cancels a tap
    var DRAG_THRESHOLD = 10;

    tables.forEach(function (table) {
        var addEventUrl = table.getAttribute("data-add-event-url");
        var timeStep = parseInt(table.getAttribute("data-time-step"), 10);
        var addEventTitle = table.getAttribute("data-add-event-title") || "";

        if (!addEventUrl || !timeStep) {
            return;
        }

        var selection = null;
        var press = null;
        var suppressClick = false;

        function formatTime(seconds) {
            var hours = Math.floor(seconds / 3600);
            var minutes = Math.floor((seconds % 3600) / 60);
            return (hours < 10 ? "0" : "") + hours + ":" + (minutes < 10 ? "0" : "") + minutes;
        }

        function clearSelection() {
            if (selection && selection.area.parentNode) {
                selection.area.parentNode.removeChild(selection.area);
            }
            selection = null;
        }

        function slotIndexAt(slots, clientY) {
            for (var i = 0; i < slots.length; i++) {
                if (clientY < slots[i].getBoundingClientRect().bottom) {
                    return i;
                }
            }
            return slots.length - 1;
        }

        function slotCell(slot) {
            var cell = slot.closest("td");
            return cell && cell.getAttribute("data-date") ? cell : null;
        }

        function renderSelection() {
            var from = Math.min(selection.anchorIndex, selection.currentIndex);
            var to = Math.max(selection.anchorIndex, selection.currentIndex);

            var cellRect = selection.cell.getBoundingClientRect();
            var firstRect = selection.slots[from].getBoundingClientRect();
            var lastRect = selection.slots[to].getBoundingClientRect();

            selection.timeStart = parseInt(selection.slots[from].getAttribute("data-time"), 10);
            selection.timeEnd = parseInt(selection.slots[to].getAttribute("data-time"), 10) + timeStep;

            selection.area.style.top = (firstRect.top - cellRect.top) + "px";
            selection.area.style.height = (lastRect.bottom - firstRect.top) + "px";
            selection.area.innerHTML = '<span class="week-select-plus">+</span>' +
                    formatTime(selection.timeStart) + " - " + formatTime(selection.timeEnd);
        }

        function startSelection(cell, slots, index) {
            clearSelection();

            var area = document.createElement("a");
            area.className = "week-select-area";
            area.title = addEventTitle;
            cell.appendChild(area);

            selection = {
                cell: cell,
                slots: slots,
                anchorIndex: index,
                currentIndex: index,
                endIsSet: false,
                area: area
            };

            renderSelection();
        }

        function readySelection() {
            selection.area.href = addEventUrl +
                    "&date=" + encodeURIComponent(selection.cell.getAttribute("data-date")) +
                    "&time_start=" + selection.timeStart +
                    "&time_end=" + selection.timeEnd;
            selection.area.classList.add("ready");
        }

        table.addEventListener("pointerdown", function (e) {
            if (e.button !== 0) {
                return;
            }

            var slot = e.target.closest("li.time-slot");
            if (!slot) {
                return;
            }

            var cell = slotCell(slot);
            if (!cell) {
                return;
            }

            var slots = Array.prototype.slice.call(cell.querySelectorAll("li.time-slot"));

            press = {
                cell: cell,
                slots: slots,
                index: slots.indexOf(slot),
                startY: e.clientY,
                isTouch: e.pointerType === "touch",
                dragging: false,
                cancelled: false
            };

            // Text selection is only in the way of a mouse drag, a touch must stay free to scroll
            if (!press.isTouch) {
                e.preventDefault();
            }
        });

        document.addEventListener("pointermove", function (e) {
            if (!press || press.cancelled) {
                return;
            }

            if (!press.dragging) {
                if (Math.abs(e.clientY - press.startY) < DRAG_THRESHOLD) {
                    return;
                }

                // A moving finger scrolls the page, it does not select a range
                if (press.isTouch) {
                    press.cancelled = true;
                    return;
                }

                startSelection(press.cell, press.slots, press.index);
                press.dragging = true;
            }

            var index = slotIndexAt(selection.slots, e.clientY);
            if (index !== selection.currentIndex) {
                selection.currentIndex = index;
                renderSelection();
            }
        });

        document.addEventListener("pointerup", function () {
            if (!press) {
                return;
            }

            if (press.cancelled) {
                press = null;
                return;
            }

            if (press.dragging) {
                // A drag sets both points at once
                selection.endIsSet = true;
                readySelection();
            } else if (selection && selection.cell === press.cell) {
                if (selection.endIsSet && press.index < selection.anchorIndex) {
                    // Once both points are set, a point above the first one
                    // clears the selection
                    clearSelection();
                    press = null;
                    return;
                }

                // A tap or click moves the free end of the range
                selection.currentIndex = press.index;
                selection.endIsSet = true;
                renderSelection();
                readySelection();
            } else {
                startSelection(press.cell, press.slots, press.index);
                readySelection();
            }

            // The browser sends a synthesized click after pointerup, and the
            // selection area is already under the pointer by that moment -
            // swallow that click so the gesture itself does not navigate
            suppressClick = true;

            press = null;
        });

        document.addEventListener("click", function (e) {
            if (!suppressClick) {
                return;
            }

            suppressClick = false;

            if (e.target.closest(".week-select-area")) {
                e.preventDefault();
                e.stopPropagation();
            }
        }, true);

        document.addEventListener("pointercancel", function () {
            if (press && press.dragging) {
                clearSelection();
            }

            press = null;
        });

        // Drop the selection when pressing outside of the calendar grid
        document.addEventListener("pointerdown", function (e) {
            // A new press starts a new gesture, a click stuck from
            // the previous one must not be swallowed anymore
            suppressClick = false;

            if (selection &&
                    !e.target.closest(".week-select-area") &&
                    !e.target.closest("li.time-slot")) {
                clearSelection();
            }
        });
    });
});
