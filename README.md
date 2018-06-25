# MantisBT Calendar Plugin
Overview
--------
Adds the task scheduling function in MantisBT based on the calendar of events with the possibility of one-way synchronization with Google Calendar.

Features
--------
- The ability to create a single event.
- Binding of any number of tasks for the event.
- Visual display of events in tasks.
- One-way synchronization with Google Calendar (v. >= 2.3.0)
- Support for different time zones.

Download
--------
Please download the stable version.
(https://github.com/brlumen/Calendar/releases)


How to install
--------------

1. Copy Calendar folder into plugins folder.
2. Open Mantis with browser.
3. Log in as administrator.
4. Go to Manage -> Manage Plugins.
5. Find Calendar in the list.
6. Click Install.

How to use Google Calendar Sync (for Calendar version >= 2.3.0 )
----------------------------------------------------------------

1. Go to [Google Developers Console](https://console.developers.google.com/) and create the new project.
2. Download JSON file.
3. Upload the JSON file on the Calendar settings page.
4. Click the save button.
5. Go to the calendar settings for a specific user and click "Enable sync with Google Calendar"
6. Give permission to manage your calendars Google.
7. Select a calendar for one-way synchronization with Google Calendar.

Supported Versions
------------------

- MantisBT 2.14 and higher - supported
