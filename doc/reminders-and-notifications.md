# Reminders and Notifications

Available in Calendar 3.0.0 and newer.

Reminders, notifications and replies to invitations are switched off after the
installation. Enable them on the Calendar settings page (Manage -> Manage
Plugins -> Calendar):

- **Reminders** are e-mails sent before the start of an occurrence to the
  author and the members of the event. An event may carry its own reminders,
  fall back to the personal defaults of each recipient, or have reminders
  switched off; every user manages their defaults and opt-out on the
  "Event calendar" tab of "My Account". Reminders are dispatched by the
  MantisBT cron job (`scripts/cronjob.php`, hook `EVENT_CRONJOB`); without a
  cron job they are still sent from page loads, throttled to once in five
  minutes. The settings page shows when the cron job last ran.
- **Notifications** are e-mails about created, changed and deleted events and
  about membership changes. Who gets them is decided by a recipient matrix
  (author, members, the acting user) that can be overridden per project, like
  the e-mail notification settings of MantisBT itself, and every user can turn
  off each kind of notification on the same "My Account" tab. The user who made
  the change never gets a mail about it. A mail that names another member —
  added, removed, or replying to an invitation — goes only to users allowed to
  see the member list of the event (`show_member_list_threshold`).
- **Replies to invitations** let the members say whether they will take part
  (see below).

A reminder goes out on the first run of the cron job after its moment, so the
interval of the job is the delay of the reminder: schedule it **every minute**,
for example in the crontab of the web server user:

```
* * * * * php /path/to/mantisbt/scripts/cronjob.php
```


## Personal reminders of an event

The event page shows the reminders of the event and those that apply to the
viewer, with where they come from. The author or a member can add a reminder
there, remove one ("Remove this reminder for me") or go "Back to the reminders
of the event". Such changes apply to that user only; the reminders of the event
stay as its author set them.


## Replies to invitations

The setting "Members reply whether they will take part" (`rsvp_mode`) has three
values: **Off** (the default), **On for everybody**, and **Every user chooses**
— then every user switches "Reply to invitations" on or off on the "Event
calendar" tab of "My Account".

A member replies **Yes**, **Maybe** or **No**, once for the whole event, even a
recurring one:

- on the event page, which also lists the replies of all members;
- from the reply link of a notification mail or of the `.ics` file. The link is
  personal and opens a page with the reply buttons only for its recipient, logged
  in; it stops working once the event is over or the user is no longer a member;
- from the list "Awaiting your reply", opened by the button in the header of the
  week and month views; the filter "Awaiting my reply" shows the same events in
  the calendar. Events waiting for a reply are drawn faded.

The author of an event takes part by definition. A reply is logged in the
history of the event and mailed to whoever the notification matrix names for
"Member replied whether they will take part". When the time of the event
changes, the replies are dropped and asked for again.

Replies hold reminders back: a member who declined an event is not reminded
about it, and a member who has not replied yet is reminded only after setting a
reminder of their own on the event page, or with "Remind about events I have
not replied to" switched on in their account.


## Calendar file (.ics)

The notification mails carry a link to the iCalendar file of the event, and the
event page has a **Download .ics** button. The file holds one event or a whole
series; importing a newer file updates the copy in the calendar application.
Opening the link requires logging in and access to the event.
