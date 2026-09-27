# Public API for other plugins

Available in Calendar 3.0.0 and newer.

Other MantisBT plugins can create calendar events without declaring a hard
dependency on Calendar. The contract is the request class
`\CalendarPluginApi\EventCreateRequest`: checking that it exists at runtime is
enough to know whether Calendar is installed and loaded.

The [TelegramBot](https://github.com/mantisbt-plugins/TelegramBot) plugin uses this
API since its version 2.0: it creates calendar events from Telegram and
subscribes to the calendar signals to send Telegram notifications about
created and changed events, choosing the recipients by a notification matrix
of its own, so it doubles as a worked example of the integration.

```php
if( class_exists( 'CalendarPluginApi\\EventCreateRequest' ) ) {
    $t_request = new \CalendarPluginApi\EventCreateRequest();

    $t_request->project_id = $t_project_id;   // required
    $t_request->name       = 'Sprint review'; // required
    $t_request->user_id    = $t_user_id;      // required, the event author
    $t_request->date_from  = $t_from;         // required, Unix timestamp
    $t_request->date_to    = $t_to;           // required, Unix timestamp; a later
                                              // day makes a multi-day event

    $t_request->description        = 'Agenda: ...';      // optional
    $t_request->duration           = 3600;               // seconds; optional for a single
                                                         // event, required for a recurring one
    $t_request->bug_ids            = array( $t_bug_id ); // optional, attach issues
    $t_request->members            = array( 15, 22 );    // optional, defaults to the author
    $t_request->recurrence_pattern = 'RRULE:...';        // optional, RFC 5545
    $t_request->timezone           = 'Europe/Moscow';    // optional
    $t_request->reminders          = array( 900, 86400 ); // optional, see below

    $t_event_id = calendar_api_event_create( $t_request );
}
```

For a recurring event `date_to` is the end of the last occurrence and
`duration` is the length of one occurrence, so a recurring request without
`duration` is rejected; for a single event it may be left out and defaults to
`date_to - date_from`. `reminders` mirrors the three
states of the event form: `null` (the default) leaves every recipient with
their personal default reminders, a list of offsets in seconds before the start
of an occurrence (each at least 60) applies to every recipient instead, and an
empty array switches reminders off for this event.

The facade owns all of the calendar rules, so the caller may hand over raw
input: it verifies that everything referenced exists, that the author passes
`report_event_threshold` and may view every attached issue, and that every
member is eligible for the project — all before the event is written, so a
rejected request never leaves a partial event behind. Wrong types fail with a
`TypeError` at the assignment; missing required fields and ineligible values
are thrown as `\Mantis\Exceptions\ClientException` whose code is the MantisBT
error constant, never as an error page. The other calls below that raise
errors do it the same way.

`calendar_api_candidate_members( $p_project_id, $p_user_id )` returns the user
ids the given user may sign up as members — use it to build a member picker;
any subset of the returned list is guaranteed to be accepted.

`calendar_api_candidate_issues( $p_project_id, $p_user_id, $p_page, $p_per_page )`
returns the issues the given user may attach the event to, page by page, from
the same source as the issue selector of the event form — use it to build an
issue picker; any subset of the returned ids is guaranteed to be accepted.

`calendar_api_event_history_log( $p_event_id, $p_field_name, $p_old_value, $p_new_value, $p_user_id = null, $p_basename = null )`
writes a record to the history of an event, the way `plugin_history_log()` of
the core does for an issue. The field name is prefixed with the basename of the
calling plugin, and that prefixed name doubles as the language key of the
label: define `$s_plugin_<Basename>_<field_name>` in your language files, or
the raw field name is shown. Values are stored and displayed as given.

`calendar_api_event_notify_recipients( $p_event_id, $p_action, $p_actor_id = null )`
returns the user ids the calendar itself would notify about the given action —
one of `created`, `updated`, `deleted`, `member_added`, `member_removed`,
`rsvp` —
after the recipient matrix, the personal settings and the access checks have
been applied; for `member_added`, `member_removed` and `rsvp`, which name a
member, only users who pass `show_member_list_threshold` of the event's project
are returned. Use it to deliver the same notification through your own channel;
the master switch of the calendar mails is deliberately not consulted.

`calendar_api_event_members( $p_event_id )` returns the user ids of the
members of an event as they are stored, without the
`show_member_list_threshold` check — the author is in the event row, the
members are nowhere else. Use it when your plugin keeps a notification matrix
of its own and needs the circle the matrix is applied to; every further
check — the access of a member to the event, their personal settings — is
yours to make, the way `calendar_api_event_notify_recipients()` makes them.

## Replies to invitations

A reply is stored per member and event, never per occurrence: a member answers
for a whole series. The statuses are the `CALENDAR_RSVP_*` constants:

| Constant                  | Value | Meaning                   |
|---------------------------|-------|---------------------------|
| `CALENDAR_RSVP_NONE`      | 0     | no reply given yet        |
| `CALENDAR_RSVP_ACCEPTED`  | 1     | the member will take part |
| `CALENDAR_RSVP_DECLINED`  | 2     | the member will not       |
| `CALENDAR_RSVP_TENTATIVE` | 3     | the member is not sure    |

The `rsvp_mode` chosen by the administrator is one of `CALENDAR_RSVP_MODE_OFF`
(0), `CALENDAR_RSVP_MODE_ON` (1, every member replies) and
`CALENDAR_RSVP_MODE_USER_CHOICE` (2, every user switches the replies on or off
for themselves).

`calendar_api_rsvp_enabled( $p_user_id = null )` returns whether the replies
are on: without a user, whether they exist on the instance at all; with one,
whether that user takes part in them. Ask it before offering reply buttons in
your own channel. It never raises an error.

`calendar_api_event_member_statuses( $p_event_id )` returns the replies of the
members as `user id => status`, as they are stored — not gated by
`show_member_list_threshold`, and returned even while `rsvp_mode` is off.
Raises an error for an unknown event.

`calendar_api_event_member_status_set( $p_event_id, $p_user_id, $p_status )`
records the reply of a member on their behalf, the way the event page and the
links in the mails do. It is rejected when the event is unknown, the user does
not take part in the replies (`ERROR_ACCESS_DENIED`), is not a member of the
event or may not view it (`view_event_threshold`), or the status is not
`CALENDAR_RSVP_ACCEPTED`, `CALENDAR_RSVP_TENTATIVE` or `CALENDAR_RSVP_DECLINED`
(`ERROR_INVALID_FIELD_VALUE`). An unchanged reply changes nothing; a changed
one is logged in the history of the event under the name of the member,
signalled through `EVENT_CALENDAR_EVENT_RSVP` and mailed to whoever the
notification matrix names for the `rsvp` action.

## Reminders of a user

`calendar_api_event_reminders( $p_event_id, $p_user_id )` returns the reminders
of the user about the event, as the event page shows them — what you need to
draw buttons under a delivered reminder. Raises an error for an unknown event
or user. The array holds:

- `enabled` — whether `reminders_feature_enabled` is on; the calls below are
  rejected while it is not;
- `is_recipient` — whether the user is the author or a member of the event;
  only such a user may change their reminders;
- `opted_out` — whether the user switched every reminder off in their account;
- `source` — `personal` (a set of their own for this event), `event` (the set
  of the event) or `defaults` (their personal defaults);
- `held` — `null`, or why nothing is sent: `declined` (the user declined the
  event) or `no_reply` (the user has not replied and does not want reminders
  about such events; adding a reminder of their own lifts it);
- `offsets` — the offsets in seconds that apply to the user, ascending, empty
  when held back or switched off; exactly the offsets
  `EVENT_CALENDAR_EVENT_REMINDER` will carry for them (a user who is not a
  recipient gets the set of the event);
- `labels` — offset => its text (`10 min`) in the language of the user;
- `event_offsets` — the set of the event itself, empty when it has none;
- `max_per_event`, `max_offset` — the limits an added offset has to keep.

`calendar_api_event_reminder_add( $p_event_id, $p_user_id, $p_offset )`,
`calendar_api_event_reminder_remove( $p_event_id, $p_user_id, $p_offset )` and
`calendar_api_event_reminder_reset( $p_event_id, $p_user_id )` change the
reminders of one user, for them only: add and remove turn what applied so far
into a set of their own with the offset added or dropped (removing the last
offset leaves the user without reminders about the event), reset drops that set
so the set of the event — or the personal defaults — applies again. The set of
the event stays as its author made it, and nothing is logged in the history of
the event. All three are rejected when the event or the user is unknown, while
`reminders_feature_enabled` is off (`ERROR_ACCESS_DENIED`), when the user is
neither the author nor a member of the event (`ERROR_USER_BY_ID_NOT_FOUND`) or
may not view it (`view_event_threshold`). Add also rejects an offset outside
the limits of the event form — at least 60 seconds, at most
`reminder_max_offset`, no more than `reminder_max_per_event` offsets in all
(`ERROR_REMINDER_INVALID`) — and a user who declined the event
(`ERROR_ACCESS_DENIED`); for a user whose reminders were held back for lack of
a reply the added offset is their only reminder and it goes out. An offset that
applies already, or does not apply for remove, changes nothing.

`calendar_api_event_reminder_snooze( $p_event_id, $p_occurrence, $p_user_id, $p_fire_at )`
puts the reminder of a recipient about one occurrence off to the moment
`$p_fire_at` — the "remind me again later" under a reminder delivered on
`EVENT_CALENDAR_EVENT_REMINDER`, whose occurrence timestamp it takes. The put
off reminder goes out once, the same way as a scheduled one (mail, signal,
history record); a recipient has one per occurrence, and snoozing again moves
it. It is rejected when the event is unknown, while `reminders_feature_enabled`
is off (`ERROR_ACCESS_DENIED`), when the timestamp is not an occurrence of the
event (`ERROR_EVENT_TIME_PERIOD_NOT_FOUND`), the user is not reminded about the
event — the author and the members who have not declined it are
(`ERROR_USER_BY_ID_NOT_FOUND`) — or the moment is not ahead of now and before
the end of the occurrence (`ERROR_INVALID_FIELD_VALUE`). Whatever changes by
then — a cancelled occurrence, a member who left, reminders switched off —
drops it silently.

## iCalendar file

`calendar_api_event_ics_url( $p_event_id )` returns the absolute URL of the
page that offers the `.ics` file of the event — the link the notification
mails carry. It makes no check and raises no error: whoever follows it has to
log in and pass the `view_event_threshold` of the event.

`calendar_api_event_ics( $p_event_id, $p_user_id )` returns the file itself,
built for the user who is going to receive it, as
`array( 'filename' => ..., 'content' => ... )`; the media type is
`text/calendar`. The file holds one event or a whole series, published rather
than sent as an invitation, with a UID that stays the same across the changes
of the event, so importing a newer file updates the copy. It is rejected when
the event or the user is unknown or the user may not view the event
(`view_event_threshold`); of the attached issues it lists only those the user
may view.

## Signals

Calendar also declares events other plugins can hook. The first three receive
the event id as their only parameter; `EVENT_CALENDAR_EVENT_CREATED` is
signalled only after the members, issues and reminders of the event have been
written, and `EVENT_CALENDAR_EVENT_DELETED` before anything is deleted, so the
handler still finds the event and its members:

- `EVENT_CALENDAR_EVENT_CREATED` (`EVENT_TYPE_EXECUTE`)
- `EVENT_CALENDAR_EVENT_UPDATED` (`EVENT_TYPE_EXECUTE`)
- `EVENT_CALENDAR_EVENT_DELETED` (`EVENT_TYPE_EXECUTE`)
- `EVENT_CALENDAR_EVENT_REMINDER` (`EVENT_TYPE_EXECUTE`) — once per due
  reminder with `array( $p_event_id, $p_occurrence_timestamp, $p_user_id,
  $p_offset_seconds )`, raised even when no mail is sent, so a subscriber can
  deliver the reminder through its own channel. A user who opted out of
  reminders gets no signal, and neither does a member whose reminders are held
  back by their reply. A snoozed reminder is signalled the same way when its
  moment comes; its offset is then the distance to the start of the
  occurrence, zero or negative once the occurrence has begun.
- `EVENT_CALENDAR_EVENT_MEMBER_ADDED` and `EVENT_CALENDAR_EVENT_MEMBER_REMOVED`
  (`EVENT_TYPE_EXECUTE`) — with `array( $p_event_id, $p_user_id, $p_actor_id )`
  when a user is added to or removed from the members of an existing event,
  once the change is stored. Members written while an event is created, or
  when an occurrence is split off its series, are announced by
  `EVENT_CALENDAR_EVENT_CREATED` alone, and those dropped with a deleted event
  by `EVENT_CALENDAR_EVENT_DELETED`.
- `EVENT_CALENDAR_EVENT_RSVP` (`EVENT_TYPE_EXECUTE`) — with
  `array( $p_event_id, $p_user_id, $p_status )` when a member changes their
  reply, on every path that records one: the event page, the links in the
  mails and `calendar_api_event_member_status_set()`. The author, marked as
  taking part when the event is created, raises no signal.
- `EVENT_CALENDAR_NOTIFY_USER_INCLUDE( $p_event_id, $p_action )` and
  `EVENT_CALENDAR_NOTIFY_USER_EXCLUDE( $p_event_id, $p_action, $p_user_id )`
  (`EVENT_TYPE_DEFAULT`) — take part in the choice of the recipients of a
  notification, like `EVENT_NOTIFY_USER_INCLUDE` / `EVENT_NOTIFY_USER_EXCLUDE`
  of the core: the first returns extra candidate user ids (they still pass all
  the usual checks), a truthy answer to the second drops the candidate.

## Subscribing without a hard dependency

MantisBT initializes plugins one at a time, so when your plugin's `hooks()`
runs, Calendar may not be initialized yet: its classes do not exist and its
events are not declared. Hooking an undeclared event is silently dropped.
Two reliable patterns:

1. Pre-declare the event in your `hooks()`. All plugins are *registered*
   before any of them is initialized, so `plugin_is_registered( 'Calendar' )`
   is dependable there. Declare the event with the exact type listed above —
   `event_declare()` is a no-op for an already declared event, and the first
   declaration wins the type used by every later signal:

   ```php
   function hooks() {
       $t_hooks = array( /* your other hooks */ );
       if( plugin_is_registered( 'Calendar' ) ) {
           event_declare( 'EVENT_CALENDAR_EVENT_CREATED', EVENT_TYPE_EXECUTE );
           $t_hooks['EVENT_CALENDAR_EVENT_CREATED'] = 'on_calendar_event';
       }
       return $t_hooks;
   }
   ```

2. Or subscribe late: hook the core `EVENT_PLUGIN_INIT` event, which is
   signalled after every plugin has been initialized, and call `event_hook()`
   from its handler — at that point `class_exists` is reliable and the
   Calendar events are declared.

Do not use `$this->uses` for this: a soft dependency keeps your plugin
uninitialized while Calendar is registered but waiting for a schema upgrade.
