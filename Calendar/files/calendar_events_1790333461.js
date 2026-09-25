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

document.addEventListener("DOMContentLoaded", function() {
    // Variable initialization
    var emptySlots = document.querySelectorAll(".calendar-event-empty.clickable");
    var dayHeaders = document.querySelectorAll(".calendar-day-header.clickable");
    var createEventModal = document.getElementById("createEventModal");
    var eventModal = document.getElementById("eventModal");
    var selectedDate = null;
    var activeSlot = null;
    var activeHeader = null;
    
    // Click handling for the "more events" button
    var moreEventButtons = document.querySelectorAll(".calendar-more-events");
    moreEventButtons.forEach(function(button) {
        button.addEventListener("click", function() {
            var date = this.getAttribute("data-date");
            var events = JSON.parse(this.getAttribute("data-events"));
            showDayEvents(date, events);
        });
    });

    // Convert an ISO date (YYYY-MM-DD) to the display format DD.MM.YYYY
    function formatDisplayDate(date) {
        var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(date);
        return m ? m[3] + "." + m[2] + "." + m[1] : date;
    }

    // Append a <div> with the given class and plain text content
    function appendTextDiv(parent, className, text) {
        var div = document.createElement("div");
        div.className = className;
        div.textContent = text;
        parent.appendChild(div);
    }

    // Function that shows the events of a day
    function showDayEvents(date, events) {
        var modalTitle = document.getElementById("modalTitle");
        var modalEventList = document.getElementById("modalEventList");
        var span = document.getElementsByClassName("close")[0];
        
        var eventsForDateText = eventModal.getAttribute("data-events-for-date-text");
        modalTitle.textContent = eventsForDateText.replace("%s", date);
        
        // Event data comes from users, so it goes into the DOM as text only
        while (modalEventList.firstChild) {
            modalEventList.removeChild(modalEventList.firstChild);
        }
        if(events !== null){
            events.forEach(function(event) {
                var link = document.createElement("a");
                link.setAttribute("href", event.url);
                link.className = "modal-event";
                link.setAttribute("style", event.style || "");
                appendTextDiv(link, "event-time", event.time);
                appendTextDiv(link, "event-duration", event.duration);
                appendTextDiv(link, "event-name", event.name);
                if (event.user_name) {
                    appendTextDiv(link, "event-user", event.user_name);
                }
                appendTextDiv(link, "event-project", event.project_name);
                modalEventList.appendChild(link);
            });
        }
    
        eventModal.style.display = "block";
        
        var createEventForm = document.getElementById("createEventForm");
        
        // Attach the handler of the event creation button
        var createEventBtn = eventModal.querySelector('#create-event-btn');
        if (createEventBtn) {
            createEventBtn.date = date;
            createEventBtn.addEventListener('click', showCreateEventForm);
        }
        
        span.onclick = function() {
            eventModal.style.display = "none";
            createEventForm.style.display = "none";
            createEventBtn.removeEventListener('click', showCreateEventForm);
        };
        
        window.onclick = function(event) {
            if (event.target == eventModal) {
                eventModal.style.display = "none";
                createEventForm.style.display = "none";
                createEventBtn.removeEventListener('click', showCreateEventForm);
            }
        };
    }

    // Function that shows the event creation form
    function showCreateEventForm(e) {
        var createEventForm = document.getElementById("createEventForm");
        
        // If the form is already shown, hide it
        if (createEventForm.style.display === "block") {
            createEventForm.style.display = "none";
            return;
        }
        
        selectedDate = e.currentTarget.date;
        
        // Clear the form
        document.getElementById("eventName").value = "";
        
        // Show the selected date
        // The date comes in DD.MM.YYYY format, it is shown as is
        document.getElementById("selectedDate").textContent = selectedDate;
        
        // Set the default time values
        var timeStart = document.getElementById("eventTimeStart");
        var timeEnd = document.getElementById("eventTimeEnd");
        
        // Find the options for 9:00 and 10:00
//        for (var i = 0; i < timeStart.options.length; i++) {
//            if (timeStart.options[i].value === "09:00") {
//                timeStart.selectedIndex = i;
//            }
//            if (timeEnd.options[i].value === "10:00") {
//                timeEnd.selectedIndex = i;
//            }
//        }
        
        // Show the event creation form
        createEventForm.style.display = "block";
    }

    // Click handling for the event creation button
    var createEventBtn = document.getElementById("createEventBtn");
    if (createEventBtn) {
        createEventBtn.addEventListener("click", function() {
            var form = document.getElementById("createEventForm");
            var eventName = document.getElementById("eventName").value;
            var timeStart = document.getElementById("eventTimeStart").value;
            var timeEnd = document.getElementById("eventTimeEnd").value;

            if (!eventName) {
                alert(form.dataset.msgNoName);
                return;
            }

            if (!timeStart || !timeEnd) {
                alert(form.dataset.msgNoTime);
                return;
            }
            
            // Build the URL with the parameters
            var url = "plugin.php?page=Calendar/event_add_page" + 
                     "&name=" + encodeURIComponent(eventName) +
                     "&date=" + selectedDate +
                     "&time_start=" + encodeURIComponent(timeStart) +
                     "&time_end=" + encodeURIComponent(timeEnd);
            
            // Go to the event creation page
            window.location.href = url;
        });
    }
    
    // Click handling for an empty slot
    emptySlots.forEach(function(slot) {
        slot.addEventListener("click", function() {
            // A click on the already active slot opens the modal window
            if (activeSlot === this) {
                var date = this.getAttribute("data-date");
                var events = JSON.parse(this.getAttribute("data-events"));
                showDayEvents(formatDisplayDate(date), events);
                activeSlot.classList.remove("active");
                activeSlot = null;
            } else {
                // Drop the highlight from the previously active slot and header
                if (activeSlot) {
                    activeSlot.classList.remove("active");
                }
                if (activeHeader) {
                    activeHeader.classList.remove("active");
                    activeHeader = null;
                }
                
                // Highlight the current slot
                this.classList.add("active");
                activeSlot = this;
            }
        });
    });
    
    // Click handling for a day header
    dayHeaders.forEach(function(header) {
        header.addEventListener("click", function() {
            // A click on the already active header opens the modal window
            if (activeHeader === this) {
                var date = this.getAttribute("data-date");
                var events = JSON.parse(this.getAttribute("data-events"));
                showDayEvents(formatDisplayDate(date), events);
                activeHeader.classList.remove("active");
                activeHeader = null;
            } else {
                // Drop the highlight from the previously active header and slot
                if (activeHeader) {
                    activeHeader.classList.remove("active");
                }
                if (activeSlot) {
                    activeSlot.classList.remove("active");
                    activeSlot = null;
                }
                
                // Highlight the current header
                this.classList.add("active");
                activeHeader = this;
            }
        });
    });

    // Click handling for events
    let activeEvent = null;

    // Handling of the events inside the calendar
    document.querySelectorAll('.calendar-cell .calendar-event').forEach(event => {
        event.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Check whether the event is a link (used by the modal window)
            if (this.tagName === 'A') {
                window.location.href = this.getAttribute('href');
                return;
            }
            
            // For the events inside the calendar
            const link = this.querySelector('a');
            if (!link) return;
            
            const href = link.getAttribute('href');
            if (activeEvent === this) {
                // The second click follows the link without dropping the highlight
                window.location.href = href;
            } else {
                // The first click highlights the event
                if (activeEvent) {
                    activeEvent.classList.remove('active');
                }
                this.classList.add('active');
                activeEvent = this;
            }
        });
    });

    // Drop the highlight on a click outside of an event
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.calendar-event') && activeEvent) {
            activeEvent.classList.remove('active');
            activeEvent = null;
        }
    });
}); 