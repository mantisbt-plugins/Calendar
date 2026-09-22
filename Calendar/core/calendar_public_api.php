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

/**
 * Public API of the Calendar plugin, meant to be called from other plugins.
 *
 * The contract is the request class of each call, currently
 * \CalendarPluginApi\EventCreateRequest. A caller does not have to declare a
 * hard dependency on Calendar: checking class_exists( 'CalendarPluginApi\\EventCreateRequest' )
 * at runtime is enough to know whether the plugin is installed and loaded.
 *
 * Every function here is a facade and follows the same two rules:
 * - the whole body runs inside plugin_push_current( 'Calendar' ), otherwise
 *   plugin_table() / plugin_config_get() would resolve against the calling
 *   plugin instead of this one;
 * - all input arrives through the arguments - no gpc_*, no current user and
 *   no form security here, those belong to the pages/ layer.
 *
 * The facades that read - the candidate lists, the recipients of a
 * notification, the iCalendar file of an event - exist so that a plugin with
 * a channel of its own can show the same things the calendar shows in its
 * pages and mails, to the same circle of users, without reimplementing the
 * rules behind them.
 */

/**
 * Create a calendar event on behalf of the user named by the request.
 *
 * The request is validated first, then everything it refers to is checked,
 * so that a caller may hand over raw input without knowing the rules of the
 * calendar. The call guarantees that:
 * - the project, the author, every member and every attached issue exist;
 * - the author passes the 'report_event_threshold' level of the project;
 * - the author is allowed to see every issue the event is attached to, so that
 *   attaching an issue can never disclose one;
 * - every member reaches the project at the 'view_event_threshold' level and
 *   none of them is the anonymous account;
 * - the author passes 'member_add_others_event_threshold' as soon as the
 *   member list names somebody other than the author;
 * - the reminder offsets respect the limits of the event form (see
 *   \CalendarPluginApi\EventCreateRequest::$reminders for the semantics).
 * All of these run before the event row is written, so a rejected request
 * leaves no orphan event behind. Access is always checked for
 * $p_request->user_id, never for the logged in user.
 *
 * Every rejection is thrown as \Mantis\Exceptions\ClientException whose code
 * is the MantisBT error constant, never reported through trigger_error(): a
 * programmatic caller has no error page to fall back to, so the usual
 * APPLICATION ERROR halt would tear its request down. Callers catching
 * MantisException are covered.
 *
 * @param \CalendarPluginApi\EventCreateRequest $p_request Event description.
 * @return int Identifier of the created event.
 * @throws \Mantis\Exceptions\ClientException When the request is rejected.
 * @access public
 */
function calendar_api_event_create( \CalendarPluginApi\EventCreateRequest $p_request ) : int {

    plugin_push_current( 'Calendar' );

    # trigger_error() would render the error page and halt, which is the right
    # behavior for pages/ but not for an API consumer - convert every ERROR
    # raised below (own checks, core ensure-functions, access_denied()) into
    # an exception the caller can catch
    set_error_handler( function( $p_severity, $p_message ) {
        # MantisBT passes the error code as the message of trigger_error():
        # numeric for core errors, "plugin_Calendar_<NAME>" for plugin ones.
        # error_string() resolves both; the numeric exception code degrades
        # to ERROR_GENERIC for the string form.
        $t_code = is_numeric( $p_message ) ? (int)$p_message : ERROR_GENERIC;
        throw new \Mantis\Exceptions\ClientException( error_string( $p_message ), $t_code );
    }, E_USER_ERROR );

    try {
        $p_request->validate();

        $t_project_id = $p_request->project_id;
        $t_user_id    = $p_request->user_id;

        project_ensure_exists( $t_project_id );
        user_ensure_exists( $t_user_id );

        # event_attach_issue() skips an issue that does not exist without a
        # word, so the existence of every id is asserted here instead
        $t_bug_ids = array_values( array_unique( array_map( 'intval', $p_request->bug_ids ) ) );

        foreach( $t_bug_ids as $t_bug_id ) {
            bug_ensure_exists( $t_bug_id );
        }

        # the access level is read per user and per project, so that project
        # specific overrides of the threshold are honoured
        $t_threshold = plugin_config_get( 'report_event_threshold', NULL, FALSE, $t_user_id, $t_project_id );

        if( !access_has_project_level( $t_threshold, $t_project_id, $t_user_id ) ) {
            access_denied();
        }

        # the issue selector of the event form is filled by filter_get_bug_rows(),
        # i.e. it only offers issues the user may read - attaching issues must
        # not become a way around that, so the author has to pass the own view
        # threshold of each issue, read in the project the issue lives in
        foreach( $t_bug_ids as $t_bug_id ) {
            $t_bug_project_id     = bug_get_field( $t_bug_id, 'project_id' );
            $t_view_bug_threshold = config_get( 'view_bug_threshold', NULL, $t_user_id, $t_bug_project_id );

            if( !access_has_bug_level( $t_view_bug_threshold, $t_bug_id, $t_user_id ) ) {
                access_denied();
            }
        }

        # an empty member list means the author attends their own event
        $t_members = $p_request->members;
        if( count( $t_members ) == 0 ) {
            $t_members = array( $t_user_id );
        }

        # the member selector of the event form is filled from
        # project_get_all_user_rows(), so only users who can reach the project of
        # the event are eligible; an ineligible member is a malformed request
        # rather than a permission problem of the author, hence the field error
        $t_adds_other_members = FALSE;

        foreach( $t_members as $t_member_id ) {
            $c_member_id = (int)$t_member_id;

            user_ensure_exists( $c_member_id );

            $t_member_threshold = plugin_config_get( 'view_event_threshold', NULL, FALSE, $c_member_id, $t_project_id );

            if( user_is_anonymous( $c_member_id )
                    || !access_has_project_level( $t_member_threshold, $t_project_id, $c_member_id ) ) {
                error_parameters( 'members' );
                trigger_error( ERROR_INVALID_FIELD_VALUE, ERROR );
            }

            if( $c_member_id != $t_user_id ) {
                $t_adds_other_members = TRUE;
            }
        }

        # signing somebody else up for an event is a separate permission
        if( $t_adds_other_members ) {
            $t_add_others_threshold = plugin_config_get( 'member_add_others_event_threshold', NULL, FALSE, $t_user_id, $t_project_id );

            if( !access_has_project_level( $t_add_others_threshold, $t_project_id, $t_user_id ) ) {
                access_denied();
            }
        }

        $t_recurrence_pattern = trim( $p_request->recurrence_pattern );
        if( !is_blank( $t_recurrence_pattern ) ) {
            try {
                new \RRule\RSet( $t_recurrence_pattern );
            } catch( Exception $e ) {
                error_parameters( 'recurrence_pattern' );
                trigger_error( ERROR_INVALID_FIELD_VALUE, ERROR );
            }
        }

        # normalized before the event row is written, so that a rejected
        # reminder list leaves no orphan event behind; NULL keeps the event
        # without explicit reminders (personal defaults of the recipients),
        # an empty list becomes the switched-off marker
        $t_reminder_offsets = null;
        if( $p_request->reminders !== null ) {
            $t_reminder_offsets = count( $p_request->reminders ) == 0
                    ? array( CALENDAR_REMINDER_DISABLED )
                    : calendar_reminder_offsets_normalize( $p_request->reminders );
        }

        # an unknown or missing name falls back to the instance timezone, the
        # same way the event creation form behaves
        $t_timezone = calendar_timezone_get( $p_request->timezone );

        $t_event_data = new CalendarEventData();

        $t_event_data->project_id         = $t_project_id;
        $t_event_data->name               = $p_request->name;
        $t_event_data->description        = $p_request->description;
        $t_event_data->activity           = 'Y';
        $t_event_data->author_id          = $t_user_id;
        $t_event_data->changed_user_id    = $t_user_id;
        $t_event_data->date_changed       = time();
        $t_event_data->date_from          = $p_request->date_from;
        $t_event_data->date_to            = $p_request->date_to;
        $t_event_data->duration           = $p_request->duration ?? $p_request->date_to - $p_request->date_from;
        $t_event_data->recurrence_pattern = $t_recurrence_pattern;
        $t_event_data->timezone           = $t_timezone->getName();

        # create() validates the data and raises the plugin errors itself
        $t_event_id = $t_event_data->create();

        if( count( $t_bug_ids ) > 0 ) {
            event_attach_issue( $t_event_id, $t_bug_ids );
        }

        foreach( $t_members as $t_member_id ) {
            event_member_add( $t_event_id, (int)$t_member_id, $t_user_id );
        }

        event_member_accept_author( $t_event_id );

        if( $t_reminder_offsets !== null ) {
            event_reminder_set_all( $t_event_id, $t_reminder_offsets, $t_user_id );
        }

        event_google_add( $t_event_id, $t_event_data->author_id, $t_members );

        # the event is fully assembled now - announce it to the subscribers
        event_signal_created( $t_event_id );

        # the user the event is created on behalf of is the one who acts here,
        # so they are the one recipient the mail is not sent to
        calendar_notify_event_created( $t_event_id, $t_user_id );

        return $t_event_id;
    } finally {
        restore_error_handler();
        plugin_pop_current();
    }
}

/**
 * Users the given user may sign up as members of an event in the given project.
 *
 * This is the read-only counterpart of the member rules enforced by
 * calendar_api_event_create(): feeding any subset of the returned ids into
 * EventCreateRequest::$members is guaranteed to pass them. It exists so that a
 * caller can offer a member picker without reimplementing the rules, and it
 * never raises an error - an unknown project, an unknown user or a user with
 * no access at all simply yields an empty array.
 *
 * The result is a plain list of user ids, the author included when eligible.
 * A user who may not add others gets back only themselves, which is exactly
 * the member list they are allowed to create an event with.
 *
 * @param int $p_project_id Project the event would belong to.
 * @param int $p_user_id    User the event would be created on behalf of.
 * @return array List of user identifiers, may be empty.
 * @access public
 */
function calendar_api_candidate_members( int $p_project_id, int $p_user_id ) : array {

    plugin_push_current( 'Calendar' );

    try {
        if( !project_exists( $p_project_id ) || !user_exists( $p_user_id ) ) {
            return array();
        }

        $t_self_threshold = plugin_config_get( 'view_event_threshold', NULL, FALSE, $p_user_id, $p_project_id );
        $t_self_eligible  = !user_is_anonymous( $p_user_id )
                && access_has_project_level( $t_self_threshold, $p_project_id, $p_user_id );

        $t_add_others_threshold = plugin_config_get( 'member_add_others_event_threshold', NULL, FALSE, $p_user_id, $p_project_id );

        if( !access_has_project_level( $t_add_others_threshold, $p_project_id, $p_user_id ) ) {
            return $t_self_eligible ? array( $p_user_id ) : array();
        }

        # same source as the member selector of the event creation form
        $t_project_users = project_get_all_user_rows( $p_project_id );
        $t_candidates    = array();

        foreach( $t_project_users as $t_project_user ) {
            $c_candidate_id = (int)$t_project_user['id'];

            if( user_is_anonymous( $c_candidate_id ) ) {
                continue;
            }

            $t_candidate_threshold = plugin_config_get( 'view_event_threshold', NULL, FALSE, $c_candidate_id, $p_project_id );

            if( !access_has_project_level( $t_candidate_threshold, $p_project_id, $c_candidate_id ) ) {
                continue;
            }

            $t_candidates[] = $c_candidate_id;
        }

        return $t_candidates;
    } finally {
        plugin_pop_current();
    }
}

/**
 * Issues the given user may attach an event to in the given project.
 *
 * This is the read-only counterpart of the issue rules enforced by
 * calendar_api_event_create(): feeding any subset of the returned ids into
 * EventCreateRequest::$bug_ids is guaranteed to pass them. The rows come from
 * the same source as the issue selector of the event creation form, so a
 * caller can offer an issue picker without reimplementing the filter, and it
 * never raises an error - an unknown project, an unknown user or a user with
 * no access at all simply yields an empty array.
 *
 * The issue list of a project easily outgrows a picker, hence the paging: the
 * page is one-based and a page beyond the last one yields an empty array.
 *
 * @param int $p_project_id Project the event would belong to.
 * @param int $p_user_id    User the event would be created on behalf of.
 * @param int $p_page       One-based number of the page to return.
 * @param int $p_per_page   Number of issues per page.
 * @return array List of arrays with the 'id', 'summary' and 'status' of an
 *               issue, may be empty.
 * @access public
 */
function calendar_api_candidate_issues( int $p_project_id, int $p_user_id, int $p_page = 1, int $p_per_page = 50 ) : array {

    plugin_push_current( 'Calendar' );

    try {
        # pages load filter_api themselves, but a caller of this facade may
        # run outside any page context, e.g. from a cron job of its plugin
        require_api( 'filter_api.php' );

        if( !project_exists( $p_project_id ) || !user_exists( $p_user_id ) ) {
            return array();
        }

        # filter_get_bug_rows() reports the paging back through its arguments,
        # so they have to be variables even where the value is not used here
        $t_requested_page = max( 1, $p_page );
        $t_page_number    = $t_requested_page;
        $t_per_page       = $p_per_page;
        $t_page_count     = null;
        $t_bug_count      = null;

        # same source as the issue selector of the event creation form, which
        # is what makes the result and the create rules agree
        $t_filter = filter_get_default();

        $t_bugs = filter_get_bug_rows( $t_page_number, $t_per_page, $t_page_count, $t_bug_count, $t_filter, $p_project_id, $p_user_id, true );

        # a page past the end is clamped to the last one, which would hand a
        # picker the same rows over and over instead of ending the listing -
        # the clamped number is reported back, so it tells the two apart
        if( $t_page_number != $t_requested_page ) {
            return array();
        }

        $t_candidates = array();

        foreach( $t_bugs as $t_bug ) {
            $t_candidates[] = array(
                'id'      => (int)$t_bug->id,
                'summary' => (string)$t_bug->summary,
                'status'  => (int)$t_bug->status,
            );
        }

        return $t_candidates;
    } finally {
        plugin_pop_current();
    }
}

/**
 * Users the calendar would notify about the given action on the given event.
 *
 * The read-only counterpart of the mails the calendar sends itself: a
 * subscriber of EVENT_CALENDAR_EVENT_CREATED, EVENT_CALENDAR_EVENT_UPDATED or
 * EVENT_CALENDAR_EVENT_DELETED can deliver the news through its own channel -
 * a messenger, a chat room - to exactly the circle the administrator has
 * defined in the notification matrix, instead of inventing a second, diverging
 * audience for the same event.
 *
 * The answer is the outcome of the whole chain the mails go through:
 * - the notification matrix is read for the project of the event, so a project
 *   with its own matrix is honoured;
 * - the personal notify_event_* settings of every candidate apply, as does the
 *   view_event_threshold of the event, which drops users who have meanwhile
 *   lost access to it;
 * - EVENT_CALENDAR_NOTIFY_USER_INCLUDE and EVENT_CALENDAR_NOTIFY_USER_EXCLUDE
 *   are raised on this path too, so a plugin that widens or narrows the circle
 *   of the mails narrows it here as well.
 *
 * $p_actor_id is the user whose action is being announced. Left out, no actor
 * rule is applied and the answer is the full circle; passed, the matrix
 * decides whether they hear about their own action, the way the mails behave.
 *
 * The master switch 'notifications_feature_enabled' is deliberately not
 * consulted: it turns off the mails of the calendar, not the question who
 * would be concerned, and a caller with its own channel has every reason to
 * ask while the mails are off. The core draws the same line - its
 * email_collect_recipients() does not look at 'enable_email_notification'.
 *
 * @param int      $p_event_id Event the notification would be about.
 * @param string   $p_action   One of the actions of the notification matrix,
 *                             see calendar_notify_actions().
 * @param int|null $p_actor_id User whose action is announced, or null for no
 *                             actor rule at all.
 * @return array List of user identifiers, may be empty.
 * @throws \Mantis\Exceptions\ClientException When the event or the action is unknown.
 * @access public
 */
function calendar_api_event_notify_recipients( int $p_event_id, string $p_action, ?int $p_actor_id = null ) : array {

    plugin_push_current( 'Calendar' );

    # the same reason as in calendar_api_event_create(): an API consumer has no
    # error page to fall back to, so every ERROR becomes a catchable exception
    set_error_handler( function( $p_severity, $p_message ) {
        $t_code = is_numeric( $p_message ) ? (int)$p_message : ERROR_GENERIC;
        throw new \Mantis\Exceptions\ClientException( error_string( $p_message ), $t_code );
    }, E_USER_ERROR );

    try {
        event_ensure_exists( $p_event_id );

        # the public contract knows the rows of the matrix only; the internal
        # variants of an action - a cancelled occurrence, a change of the
        # composition seen by the others - are an affair of the calendar itself
        if( !in_array( $p_action, calendar_notify_actions(), true ) ) {
            error_parameters( 'action' );
            trigger_error( ERROR_INVALID_FIELD_VALUE, ERROR );
        }

        return calendar_notify_recipients( calendar_notify_event_fields( $p_event_id ), $p_action, $p_actor_id );
    } finally {
        restore_error_handler();
        plugin_pop_current();
    }
}

/**
 * Members of the given event, as they are stored.
 *
 * The raw member list, for a plugin that keeps a notification matrix of its
 * own: calendar_api_event_notify_recipients() answers who the mails of the
 * calendar would reach, while a caller with its own matrix needs the circle
 * the matrix is applied to - the author, whom the event row names, and the
 * members, whom nothing but this function does. The answer is the one of
 * event_get_member_ids(): it does not depend on the session user and is not
 * gated by show_member_list_threshold, because the list is not shown to
 * anybody here, it is the input of a choice of recipients, and every check
 * the caller wants - the access of a member to the event, their own
 * preferences - is theirs to make, the way the calendar makes them in
 * calendar_notify_recipients().
 *
 * @param int $p_event_id Event the members belong to.
 * @return array List of user identifiers, may be empty.
 * @throws \Mantis\Exceptions\ClientException When the event is unknown.
 * @access public
 */
function calendar_api_event_members( int $p_event_id ) : array {

    plugin_push_current( 'Calendar' );

    set_error_handler( function( $p_severity, $p_message ) {
        $t_code = is_numeric( $p_message ) ? (int)$p_message : ERROR_GENERIC;
        throw new \Mantis\Exceptions\ClientException( error_string( $p_message ), $t_code );
    }, E_USER_ERROR );

    try {
        event_ensure_exists( $p_event_id );

        return event_get_member_ids( $p_event_id );
    } finally {
        restore_error_handler();
        plugin_pop_current();
    }
}

/**
 * Whether the members of the events may reply at all, that is whether the
 * master switch 'rsvp_feature_enabled' of the instance is on.
 *
 * A plugin with a channel of its own asks here before it offers the replies
 * there - the buttons under an invitation, say - since
 * calendar_api_event_member_status_set() takes no reply while the switch is
 * off, the way the event page and the mails offer none then.
 *
 * @return bool
 * @access public
 */
function calendar_api_rsvp_enabled() : bool {

    plugin_push_current( 'Calendar' );

    try {
        return calendar_rsvp_feature_enabled();
    } finally {
        plugin_pop_current();
    }
}

/**
 * Replies of the members of the given event: whether each of them will take
 * part, keyed by user identifier.
 *
 * The values are the CALENDAR_RSVP_* constants - CALENDAR_RSVP_NONE for a
 * member who has not replied yet. Like calendar_api_event_members(), the
 * answer is the stored list, not gated by show_member_list_threshold: it is
 * the input of a plugin with a channel of its own, and whom it shows the
 * replies to is its own decision. The master switch 'rsvp_feature_enabled'
 * is not consulted here either, for the same reason - the replies that were
 * given are still there while the feature is off.
 *
 * @param int $p_event_id Event the members belong to.
 * @return array user id => status, may be empty.
 * @throws \Mantis\Exceptions\ClientException When the event is unknown.
 * @access public
 */
function calendar_api_event_member_statuses( int $p_event_id ) : array {

    plugin_push_current( 'Calendar' );

    set_error_handler( function( $p_severity, $p_message ) {
        $t_code = is_numeric( $p_message ) ? (int)$p_message : ERROR_GENERIC;
        throw new \Mantis\Exceptions\ClientException( error_string( $p_message ), $t_code );
    }, E_USER_ERROR );

    try {
        event_ensure_exists( $p_event_id );

        return event_member_get_statuses( $p_event_id );
    } finally {
        restore_error_handler();
        plugin_pop_current();
    }
}

/**
 * Record the reply of a member of the given event on their behalf, the way
 * the event page and the links in the mails do it: a plugin with a channel
 * of its own - a bot, a mobile client - lets the member answer there.
 *
 * The call guarantees that the event exists, that the user is one of its
 * members and that the reply is one a member may give, that is
 * CALENDAR_RSVP_ACCEPTED, CALENDAR_RSVP_TENTATIVE or CALENDAR_RSVP_DECLINED;
 * it is rejected while 'rsvp_feature_enabled' is off, since the pages would
 * not take the reply either. An unchanged reply is accepted and changes
 * nothing; a changed one is logged in the history of the event under the
 * name of the member, signalled through EVENT_CALENDAR_EVENT_RSVP and mailed
 * to whoever the notification matrix names for the 'rsvp' action.
 *
 * @param int $p_event_id Event the member replies to.
 * @param int $p_user_id  The member who replies.
 * @param int $p_status   The reply, one of the CALENDAR_RSVP_* constants.
 * @return void
 * @throws \Mantis\Exceptions\ClientException When the event, the member or
 *                                            the reply is rejected.
 * @access public
 */
function calendar_api_event_member_status_set( int $p_event_id, int $p_user_id, int $p_status ) : void {

    plugin_push_current( 'Calendar' );

    set_error_handler( function( $p_severity, $p_message ) {
        $t_code = is_numeric( $p_message ) ? (int)$p_message : ERROR_GENERIC;
        throw new \Mantis\Exceptions\ClientException( error_string( $p_message ), $t_code );
    }, E_USER_ERROR );

    try {
        event_ensure_exists( $p_event_id );

        if( !calendar_rsvp_feature_enabled() ) {
            trigger_error( ERROR_ACCESS_DENIED, ERROR );
        }

        if( !user_is_member_event( $p_user_id, $p_event_id ) ) {
            error_parameters( $p_user_id );
            trigger_error( ERROR_USER_BY_ID_NOT_FOUND, ERROR );
        }

        if( !in_array( $p_status, calendar_rsvp_replies(), true ) ) {
            error_parameters( 'status' );
            trigger_error( ERROR_INVALID_FIELD_VALUE, ERROR );
        }

        event_member_set_status( $p_event_id, $p_user_id, $p_status, $p_user_id );
    } finally {
        restore_error_handler();
        plugin_pop_current();
    }
}

/**
 * Put the reminder of a recipient about one occurrence off to a later
 * moment, on their behalf: the "remind me again in a quarter of an hour" of
 * a plugin with a channel of its own, pressed under the reminder it delivered
 * on EVENT_CALENDAR_EVENT_REMINDER.
 *
 * The put off reminder is a reminder like any other and goes the way of the
 * scheduled ones when its moment comes: the mail of the calendar, the signal
 * to every subscriber - the caller included, which gets to offer the buttons
 * again -, a record in the history of the event. It goes out once; a
 * recipient has one put off reminder per occurrence at a time, and putting
 * it off again moves it.
 *
 * The call guarantees that the event exists, that the occurrence is one of
 * the event - the start of a single event, or a timestamp its rule yields,
 * the one the signal carried -, that the user is among those reminded about
 * the event, and that the moment lies ahead but before the occurrence is
 * over; it is rejected while 'reminders_feature_enabled' is off, when no
 * reminder would go out anyway. Whatever changes by the time the moment
 * comes - a cancelled occurrence, a member who left, a switched off feature -
 * drops the reminder silently.
 *
 * @param int $p_event_id   Event the reminder is about.
 * @param int $p_occurrence Start of the occurrence, as EVENT_CALENDAR_EVENT_REMINDER carried it.
 * @param int $p_user_id    Recipient of the reminder.
 * @param int $p_fire_at    Timestamp the reminder is to go out at.
 * @return void
 * @throws \Mantis\Exceptions\ClientException When the event, the occurrence,
 *                                            the user or the moment is
 *                                            rejected.
 * @access public
 */
function calendar_api_event_reminder_snooze( int $p_event_id, int $p_occurrence, int $p_user_id, int $p_fire_at ) : void {

    plugin_push_current( 'Calendar' );

    set_error_handler( function( $p_severity, $p_message ) {
        $t_code = is_numeric( $p_message ) ? (int)$p_message : ERROR_GENERIC;
        throw new \Mantis\Exceptions\ClientException( error_string( $p_message ), $t_code );
    }, E_USER_ERROR );

    try {
        event_ensure_exists( $p_event_id );

        if( !calendar_reminder_feature_enabled() ) {
            trigger_error( ERROR_ACCESS_DENIED, ERROR );
        }

        calendar_reminder_snooze_ensure_valid( $p_event_id, $p_occurrence, $p_user_id, $p_fire_at );

        event_reminder_snooze_set( $p_event_id, $p_occurrence, $p_user_id, $p_fire_at );
    } finally {
        restore_error_handler();
        plugin_pop_current();
    }
}

/**
 * Add a record of the calling plugin to the history of the given event.
 *
 * The counterpart of the core plugin_history_log() for the change log of a
 * calendar event: a plugin that keeps its own data about an event - a booked
 * room, a synchronized meeting, an approval - can make its changes visible
 * where the user already looks for the history of that event, instead of
 * hiding them in a log of its own.
 *
 * The record is stored with the type CALENDAR_HISTORY_PLUGIN and its field
 * name is prefixed with the basename of the caller, so the records of a plugin
 * can never collide with the native ones or with those of another plugin. That
 * prefixed name is at the same time the language key of the caller: a string
 * $s_plugin_<Basename>_<field_name> in its language files is what the history
 * shows as the label, the raw field name is shown when there is none. The
 * values are stored and displayed as they are given, the calendar does not
 * interpret them.
 *
 * Writing history is not access checked - the caller has decided that the
 * change happened, exactly as the core does for issue history. Who gets to
 * *see* the record is decided by the calendar: the whole history block obeys
 * the 'view_event_history_threshold' of the event.
 *
 * @param int         $p_event_id   Event the record belongs to.
 * @param string      $p_field_name Name of the field of the caller.
 * @param string      $p_old_value  Value before the change, or the single
 *                                  value of an action.
 * @param string      $p_new_value  Value after the change, empty for an action.
 * @param int|null    $p_user_id    Acting user, defaults to the logged in one.
 * @param string|null $p_basename   Basename of the plugin the record belongs
 *                                  to, defaults to the calling plugin.
 * @return void
 * @throws \Mantis\Exceptions\ClientException When the event, the user, the
 *                                            basename or the field name is
 *                                            rejected.
 * @access public
 */
function calendar_api_event_history_log( int $p_event_id, string $p_field_name, string $p_old_value = '', string $p_new_value = '', ?int $p_user_id = null, ?string $p_basename = null ) : void {

    # read before the calendar is pushed on the stack of current plugins,
    # otherwise the answer is 'Calendar' rather than the caller
    $t_basename = ( $p_basename === null ) ? plugin_get_current() : $p_basename;

    plugin_push_current( 'Calendar' );

    # the same reason as in calendar_api_event_create(): an API consumer has no
    # error page to fall back to, so every ERROR becomes a catchable exception
    set_error_handler( function( $p_severity, $p_message ) {
        $t_code = is_numeric( $p_message ) ? (int)$p_message : ERROR_GENERIC;
        throw new \Mantis\Exceptions\ClientException( error_string( $p_message ), $t_code );
    }, E_USER_ERROR );

    try {
        event_ensure_exists( $p_event_id );

        # plugin_get_current() answers null outside of any plugin context, in
        # which case the caller has to name itself
        if( is_blank( $t_basename ) ) {
            error_parameters( 'basename' );
            trigger_error( ERROR_INVALID_FIELD_VALUE, ERROR );
        }

        if( is_blank( $p_field_name ) ) {
            error_parameters( 'field_name' );
            trigger_error( ERROR_INVALID_FIELD_VALUE, ERROR );
        }

        $t_field_name = $t_basename . '_' . $p_field_name;

        # a silently truncated name would still be stored, but under a key that
        # resolves to no language string and to no field of the caller
        if( mb_strlen( $t_field_name ) > CALENDAR_HISTORY_FIELD_NAME_MAXLEN ) {
            error_parameters( 'field_name' );
            trigger_error( ERROR_INVALID_FIELD_VALUE, ERROR );
        }

        if( $p_user_id !== null ) {
            user_ensure_exists( $p_user_id );
        }

        event_history_log( $p_event_id, CALENDAR_HISTORY_PLUGIN, $t_field_name,
                           $p_old_value, $p_new_value, $p_user_id );
    } finally {
        restore_error_handler();
        plugin_pop_current();
    }
}

/**
 * Link to the page that offers the iCalendar file of the given event.
 *
 * The very link the notification mails of the calendar carry: a page with
 * the download button rather than the file itself, so that a guest who logs
 * in on the way lands on a page and not on a spent login form. A plugin that
 * delivers its notifications through another channel - a messenger, a chat
 * room - can offer the same "add to your calendar" link there, or fetch the
 * file itself with calendar_api_event_ics() and send it along as a document.
 *
 * The link is built from the configured path of the installation, so it
 * holds outside of any request as well. Whoever follows it has to log in and
 * to pass the view threshold of the event, nothing is disclosed by the link
 * itself - hence no check here and no exception: the link of an event that
 * does not exist merely leads to the error page.
 *
 * @param int $p_event_id Event the file is of.
 * @return string Absolute URL.
 * @access public
 */
function calendar_api_event_ics_url( int $p_event_id ) : string {

    plugin_push_current( 'Calendar' );

    try {
        return calendar_ical_url( $p_event_id );
    } finally {
        plugin_pop_current();
    }
}

/**
 * The iCalendar file of the given event, built for the given user.
 *
 * What the file holds is described in calendar_ical_api.php: one event or one
 * whole series, published rather than sent as an invitation, with a UID that
 * stays the same across the changes of the event so that a client replaces
 * its copy on a later import. A caller that attaches the file to a message
 * gets the name to attach it under along with the content; the media type is
 * text/calendar.
 *
 * The user is the one who is going to receive the file - the recipient of a
 * notification, typically. They have to pass the view threshold of the event,
 * and of the issues the event is attached to the file lists only those they
 * may view, the way the event page does.
 *
 * @param int $p_event_id Event the file is of.
 * @param int $p_user_id  User the file is built for.
 * @return array 'filename' => name of the file, 'content' => its text.
 * @throws \Mantis\Exceptions\ClientException When the event or the user is
 *                                            unknown, or the user may not
 *                                            view the event.
 * @access public
 */
function calendar_api_event_ics( int $p_event_id, int $p_user_id ) : array {

    plugin_push_current( 'Calendar' );

    # the same reason as in calendar_api_event_create(): an API consumer has no
    # error page to fall back to, so every ERROR becomes a catchable exception
    set_error_handler( function( $p_severity, $p_message ) {
        $t_code = is_numeric( $p_message ) ? (int)$p_message : ERROR_GENERIC;
        throw new \Mantis\Exceptions\ClientException( error_string( $p_message ), $t_code );
    }, E_USER_ERROR );

    try {
        event_ensure_exists( $p_event_id );
        user_ensure_exists( $p_user_id );

        $t_event = event_get_row( $p_event_id );

        # the threshold is read per user and per project, so that project
        # specific overrides are honoured
        $t_threshold = plugin_config_get( 'view_event_threshold', NULL, FALSE, $p_user_id, (int)$t_event['project_id'] );

        if( !access_has_event_level( $t_threshold, $p_event_id, $p_user_id ) ) {
            access_denied();
        }

        return array(
            'filename' => calendar_ical_filename( $t_event ),
            'content'  => calendar_ical_event( $p_event_id, $p_user_id ),
        );
    } finally {
        restore_error_handler();
        plugin_pop_current();
    }
}
