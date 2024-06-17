# MantisBT Calendar Plugin

[![Join the chat at https://gitter.im/mantisbt-plugins/Calendar](https://badges.gitter.im/mantisbt-plugins/Calendar.svg)](https://gitter.im/mantisbt-plugins/Calendar?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

Overview
--------
Adds the task scheduling function in MantisBT based on the calendar of events with the possibility of one-way synchronization with Google Calendar.

Screenshots
-----------

![alt text](doc/main_view_with_filter_list.png)
![alt text](doc/view_event_layers_in_bug_view.png)
![alt text](doc/add_event_view.png)
![alt text](doc/plugin_config_view.png)
![alt text](doc/workflow_thresholds_page.png)

Features
--------
- The ability to create event.
- Binding any number of bugs to event.
- Bug can be related to any number of events.
- Visual display of events in bugs view page.
- One-way synchronization with Google Calendar (v. >= 2.3.0)
- Support for different time zones.
- Recurring events(v. >= 2.4.0-dev).

Supported Versions
------------------
- MantisBT 2.14 to 2.25.x - supported in release up to 2.6.x
- MantisBT 2.26.0 and higher - supported in release 2.7.0 and higher

Download
--------
Please download the stable version.
(https://github.com/mantisbt-plugins/Calendar/releases/latest)


How to install
--------------

1. Copy Calendar folder into plugins folder.
2. Open Mantis with browser.
3. Log in as administrator.
4. Go to Manage -> Manage Plugins.
5. Find Calendar in the list.
6. Click Install.


How to enabled Google Calendar Sync (for Calendar version >= 2.3.0 )
----------------------------------------------------------------

1. Go to [Google Developers Console](https://console.developers.google.com/) and create the new project.
2. Download JSON file.
3. Upload the JSON file on the Calendar settings page.
4. Click the save button.
5. Go to the calendar settings for a specific user and click "Enable sync with Google Calendar"
6. Give permission to manage your calendars Google.
7. Select a calendar for one-way synchronization with Google Calendar.

Detailed instructions are provided in the project wiki.
https://github.com/mantisbt-plugins/Calendar/wiki#how-to-enabled-google-calendar-sync

Donate
--------------
All work on this plugin consists of many hours of coding during our free time, to provide you with a TelegramBot 
that is easy to use. If you enjoy using this plugin and would like to say thank you, donations are a great 
way to show your support.

Donations are invested back into the project 👍

Thank you for keeping this project alive 🙏

Available methods:
1. TGFFBC28Wo27aQ24L4ku6y3Egbe12Jhv1k (USDT TRC20)
2. 1PxyVPeYhRUtt5Mg1t3xSmFtHSYf2CabLR (BTC)