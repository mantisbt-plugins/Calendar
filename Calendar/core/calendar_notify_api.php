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
 * Mail notifications about the changes of an event.
 *
 * The counterpart of the core notifications about issues: the author and the
 * members of an event are told when it is created, changed or cancelled, and
 * a user is told when the composition of the members changes for them. What
 * is announced is the action of the user, not the way the plugin happens to
 * store it - editing one occurrence of a series splits a child event off, and
 * that split is still announced as a change of the event rather than as a new
 * one.
 *
 * Who is mailed about which action is an administrator setting, the matrix
 * behind notify_flags: every action names the groups it reaches - the author
 * of the event, its members, and the user who acts. The last one is the
 * counterpart of email_receive_own of the core, switched off by default, so
 * that whoever acts is not told what they have just done. A project either
 * keeps its own copy of the whole matrix or follows the global one.
 *
 * The dispatch is called from the action pages and from the public API, so it
 * must work without a session user: whoever acts is passed in instead of
 * being looked up.
 */

/**
 * Whether the notification feature is switched on for the whole instance
 * @return boolean
 * @access public
 */
function calendar_notify_feature_enabled() {
    return plugin_config_get( 'notifications_feature_enabled' ) == ON;
}

/**
 * Actions the notification matrix has a row for, in the order they are shown
 * @return array action names
 * @access public
 */
function calendar_notify_actions() {
    return array( 'created', 'updated', 'deleted', 'member_added', 'member_removed', 'rsvp' );
}

/**
 * Groups of recipients the notification matrix has a column for, in the order
 * they are shown
 * @return array target names
 * @access public
 */
function calendar_notify_targets() {
    return array( 'author', 'members', 'actor' );
}

/**
 * Row of the notification matrix that governs the given action.
 *
 * Cancelling one occurrence or the tail of a series is a deletion as far as
 * the recipients are concerned, and a change of the composition is announced
 * to the rest of the event by the same row that announces it to the user it
 * is about.
 * @param string $p_action One of the actions understood by calendar_notify_send().
 * @return string key of the matrix row
 * @access private
 */
function calendar_notify_action_row( $p_action ) {

    switch( $p_action ) {

        case 'created':
            return 'created';

        case 'updated':
            return 'updated';

        case 'member_added':
        case 'member_added_others':
            return 'member_added';

        case 'member_removed':
        case 'member_removed_others':
            return 'member_removed';

        case 'rsvp':
            return 'rsvp';

        default :
            return 'deleted';
    }
}

/**
 * Merge a stored matrix over the built-in one.
 *
 * A matrix is written as a whole, but an older or a hand edited row may still
 * be missing a cell, so every cell of the result is taken from the defaults
 * unless the stored matrix has an answer for it.
 * @param mixed $p_flags   Matrix as read from the configuration, of any shape.
 * @param array $p_default Matrix to fall back to, cell by cell.
 * @return array complete matrix of ON and OFF
 * @access public
 */
function calendar_notify_flags_normalize( $p_flags, array $p_default ) {

    if( !is_array( $p_flags ) ) {
        $p_flags = array();
    }

    $t_flags = array();

    foreach( calendar_notify_actions() as $t_action ) {
        foreach( calendar_notify_targets() as $t_target ) {

            if( isset( $p_flags[$t_action][$t_target] ) ) {
                $t_value = $p_flags[$t_action][$t_target];
            } else if( isset( $p_default[$t_action][$t_target] ) ) {
                $t_value = $p_default[$t_action][$t_target];
            } else {
                $t_value = OFF;
            }

            $t_flags[$t_action][$t_target] = (int)$t_value > 0 ? ON : OFF;
        }
    }

    return $t_flags;
}

/**
 * The notification matrix that applies to the given project.
 *
 * The whole matrix is stored under a single option, so the cascade of the
 * configuration does the choosing: a project that has been given its own copy
 * uses it, every other one falls back to the global matrix and, failing that,
 * to the built-in defaults.
 * @param integer $p_project_id Integer representing project identifier.
 * @return array complete matrix of ON and OFF
 * @access public
 */
function calendar_notify_flags( $p_project_id = ALL_PROJECTS ) {

    $t_default = plugin_config_get( 'notify_flags', array(), TRUE );

    return calendar_notify_flags_normalize(
                              plugin_config_get( 'notify_flags', $t_default, FALSE, NO_USER, (int)$p_project_id ),
                              $t_default );
}

/**
 * Personal setting that gates the mails of the given action.
 *
 * Adding a user to an event and removing them from it are gated by the
 * settings of the creation and of the deletion: a mail about an event that
 * starts or stops concerning the recipient is the same kind of news as a mail
 * about an event that appears or disappears.
 * @param string $p_action One of the actions understood by calendar_notify_send().
 * @return string configuration key of the personal setting
 * @access private
 */
function calendar_notify_action_pref( $p_action ) {

    switch( $p_action ) {

        case 'created':
        case 'member_added':
        case 'member_added_others':
            return 'created';

        case 'updated':
        case 'rsvp':
            return 'updated';

        default :
            return 'deleted';
    }
}

/**
 * Whether the given user wants to be notified about the given action
 * @param integer $p_user_id Integer representing user identifier.
 * @param string  $p_action  One of created, updated, deleted.
 * @return boolean
 * @access public
 */
function calendar_notify_user_enabled( $p_user_id, $p_action ) {
    return plugin_config_get( 'notify_event_' . $p_action, ON, FALSE, (int)$p_user_id ) == ON;
}

/**
 * Users to be notified about the given event, as the matrix of its project
 * has it: the author, the members, and the user who acts.
 *
 * The member list is read with event_get_member_ids(), because
 * event_get_members() checks the access level of the session user, which the
 * public API does not have; it is only read when the matrix asks for the
 * members at all.
 *
 * Other plugins have a say through two signals, modelled on
 * EVENT_NOTIFY_USER_INCLUDE and EVENT_NOTIFY_USER_EXCLUDE of the core:
 * EVENT_CALENDAR_NOTIFY_USER_INCLUDE is raised once, before any filtering, and
 * every user identifier it yields joins the candidates on equal terms - the
 * actor rule, the personal settings and the access threshold apply to an added
 * user just as they do to the author and the members;
 * EVENT_CALENDAR_NOTIFY_USER_EXCLUDE is raised for every candidate that
 * survived all the checks, and a truthy answer of any plugin drops it.
 * Both are handed the raw action, not the row of the matrix it maps to, so
 * that a subscriber can tell a cancelled occurrence from a deleted event.
 * @param array        $p_event           Event fields as returned by calendar_notify_event_fields().
 * @param string       $p_action          One of the actions understood by calendar_notify_send().
 * @param integer|null $p_actor_id        User who acted, a recipient only where the matrix says so.
 * @param integer|null $p_exclude_user_id User the action is about, who is told on their own.
 * @return array user identifiers
 * @access public
 * @uses database_api.php
 */
function calendar_notify_recipients( array $p_event, $p_action, $p_actor_id = null, $p_exclude_user_id = null ) {

    $t_event_id = (int)$p_event['id'];

    $t_flags = calendar_notify_flags( (int)$p_event['project_id'] );
    $t_flags = $t_flags[calendar_notify_action_row( $p_action )];

    $t_user_ids = array();

    if( $t_flags['author'] == ON ) {
        $t_user_ids[] = (int)$p_event['author_id'];
    }

    if( $t_flags['members'] == ON ) {
        $t_user_ids = array_merge( $t_user_ids, event_get_member_ids( $t_event_id ) );
    }

    # users named by other plugins, before anything is filtered out: they are
    # candidates like any other, not a way past the checks below
    $t_include_data = event_signal( 'EVENT_CALENDAR_NOTIFY_USER_INCLUDE', array( $t_event_id, $p_action ) );

    foreach( $t_include_data as $t_plugin => $t_plugin_answers ) {
        foreach( $t_plugin_answers as $t_included_users ) {

            # a plugin that has nothing to add answers with anything but a list
            if( !is_array( $t_included_users ) ) {
                continue;
            }

            foreach( $t_included_users as $t_included_user_id ) {

                $t_user_ids[] = (int)$t_included_user_id;

                plugin_log_event( sprintf( 'event #%d, %s: user #%d added by the %s plugin',
                                           $t_event_id, $p_action, (int)$t_included_user_id, $t_plugin ) );
            }
        }
    }

    $t_pref_action = calendar_notify_action_pref( $p_action );

    $t_recipients = array();
    foreach( array_unique( $t_user_ids ) as $t_user_id ) {

        if( $t_user_id <= 0 || !user_exists( $t_user_id ) || !user_is_enabled( $t_user_id ) ) {
            continue;
        }

        # what the user has just done is only mailed back to them where the
        # matrix asks for it, the way the core does with email_receive_own
        if( $p_actor_id !== null && $t_user_id == (int)$p_actor_id && $t_flags['actor'] != ON ) {
            continue;
        }

        if( $p_exclude_user_id !== null && $t_user_id == (int)$p_exclude_user_id ) {
            continue;
        }

        if( !calendar_notify_user_enabled( $t_user_id, $t_pref_action ) ) {
            continue;
        }

        # the author and the members have access implicitly, this only catches
        # users that were meanwhile removed from the project
        if( !access_has_event_level( plugin_config_get( 'view_event_threshold' ), $t_event_id, $t_user_id ) ) {
            continue;
        }

        # the last word belongs to the other plugins: a candidate that passed
        # every check of the calendar is still dropped when any of them vetoes
        $t_exclude_data = event_signal( 'EVENT_CALENDAR_NOTIFY_USER_EXCLUDE', array( $t_event_id, $p_action, $t_user_id ) );
        $t_excluded     = false;

        foreach( $t_exclude_data as $t_plugin => $t_plugin_answers ) {
            foreach( $t_plugin_answers as $t_plugin_answer ) {

                if( $t_plugin_answer ) {

                    $t_excluded = true;

                    plugin_log_event( sprintf( 'event #%d, %s: user #%d dropped by the %s plugin',
                                               $t_event_id, $p_action, $t_user_id, $t_plugin ) );
                }
            }
        }

        if( $t_excluded ) {
            continue;
        }

        $t_recipients[] = $t_user_id;
    }

    return $t_recipients;
}

/**
 * Send the mail of one action to one recipient.
 *
 * Every body is formatted with the same six arguments - name, project, date,
 * link to the event, link to its iCalendar file and the paragraph with the
 * reply links of the recipient - so that a translation is free to leave out
 * the links of an event that no longer exists without changing the call. An
 * action that has more to say appends its own arguments after those six; in
 * the subject they follow the name, from %2$s on. An argument given as
 * array( 'lang' => key ) is replaced by that string of the plugin in the
 * language of the recipient.
 * @param array        $p_event   Event row with id, project_id, name, date_from.
 * @param integer      $p_user_id Integer representing user identifier.
 * @param string       $p_action  Suffix of the notify_<action>_email_* strings.
 * @param integer|null $p_date    Timestamp shown in the mail, defaults to the start of the event.
 * @param array        $p_args    Further arguments of the action, from %7$s on.
 * @return boolean true if the mail was queued
 * @access public
 * @uses email_api.php
 */
function calendar_notify_send( array $p_event, $p_user_id, $p_action, $p_date = null, array $p_args = array() ) {

    $t_email = user_get_email( $p_user_id );

    if( is_blank( $t_email ) ) {
        return false;
    }

    # the mail is written in the language and in the timezone of its recipient,
    # not in those of whoever happens to act
    lang_push( user_pref_get_language( $p_user_id ) );

    $t_timezone = user_pref_get_pref( $p_user_id, 'timezone' );
    if( is_blank( $t_timezone ) ) {
        $t_timezone = config_get_global( 'default_timezone' );
    }

    $t_date = $p_date === null ? (int)$p_event['date_from'] : (int)$p_date;

    date_set_timezone( $t_timezone );
    $t_date_text = date( config_get( 'normal_date_format' ), $t_date );
    date_restore_timezone();

    # the public API has no request to derive a host from, so the link is
    # built from the configured path; a cancelled occurrence is gone, hence
    # the link always addresses the start of the event itself
    $t_url = config_get_global( 'path' ) . plugin_page( 'view', true )
            . '&event_id=' . (int)$p_event['id'] . '&date=' . (int)$p_event['date_from'];

    foreach( $p_args as $t_index => $t_arg ) {
        if( is_array( $t_arg ) && isset( $t_arg['lang'] ) ) {
            $p_args[$t_index] = plugin_lang_get( $t_arg['lang'] );
        }
    }

    $t_subject = vsprintf( plugin_lang_get( 'notify_' . $p_action . '_email_subject' ),
                           array_merge( array( $p_event['name'] ), $p_args ) );
    $t_body    = vsprintf( plugin_lang_get( 'notify_' . $p_action . '_email_body' ),
                           array_merge( array(
                                                    $p_event['name'],
                                                    project_get_name( (int)$p_event['project_id'], false ),
                                                    $t_date_text,
                                                    $t_url,
                                                    calendar_ical_url( (int)$p_event['id'] ),
                                                    calendar_rsvp_mail_block( (int)$p_event['id'], $p_user_id ),
                                          ), $p_args ) );

    email_store( $t_email, $t_subject, $t_body );

    lang_pop();

    return true;
}

/**
 * Read the fields the mails need out of whatever the caller has at hand.
 * A deletion has to be announced before the row is gone, so its caller passes
 * the data object it is about to delete instead of an identifier.
 * @param integer|array|CalendarEventData $p_event Event identifier, row or data object.
 * @return array event row with id, project_id, author_id, name, date_from
 * @access private
 */
function calendar_notify_event_fields( $p_event ) {

    if( is_object( $p_event ) || is_array( $p_event ) ) {
        $t_event = (array)$p_event;
    } else {
        $t_event = event_get_row( (int)$p_event );
    }

    # a data object keeps its fields protected, so the cast prefixes their
    # names with a null byte and the name of the class
    $t_fields = array();
    foreach( $t_event as $t_name => $t_value ) {

        $t_position = strrpos( $t_name, "\0" );

        if( $t_position !== false ) {
            $t_name = substr( $t_name, $t_position + 1 );
        }

        $t_fields[$t_name] = $t_value;
    }

    return array(
                              'id'         => (int)$t_fields['id'],
                              'project_id' => (int)$t_fields['project_id'],
                              'author_id'  => (int)$t_fields['author_id'],
                              'name'       => $t_fields['name'],
                              'date_from'  => (int)$t_fields['date_from'],
    );
}

/**
 * Send the mails of one action to every recipient of the given event
 * @param integer|array|CalendarEventData $p_event    Event identifier, row or data object.
 * @param string                          $p_action   Suffix of the notify_<action>_email_* strings.
 * @param integer|null                    $p_actor_id User who acted, never a recipient.
 * @param integer|null                    $p_date     Timestamp shown in the mail, defaults to the start of the event.
 * @return void
 * @access private
 */
function calendar_notify_event( $p_event, $p_action, $p_actor_id, $p_date = null ) {

    if( !calendar_notify_feature_enabled() ) {
        return;
    }

    $t_event = calendar_notify_event_fields( $p_event );

    $t_recipients = calendar_notify_recipients( $t_event, $p_action, $p_actor_id );

    foreach( $t_recipients as $t_user_id ) {
        calendar_notify_send( $t_event, $t_user_id, $p_action, $p_date );
    }
}

/**
 * Announce a newly created event to its author and its members
 * @param integer      $p_event_id Integer representing event identifier.
 * @param integer|null $p_actor_id User who created the event, never a recipient.
 * @return void
 * @access public
 */
function calendar_notify_event_created( $p_event_id, $p_actor_id ) {
    calendar_notify_event( $p_event_id, 'created', $p_actor_id );
}

/**
 * Announce a changed event to its author and its members.
 * Splitting an occurrence or the tail of a series off is a change of the
 * event as well, so its child is announced through this function too.
 * @param integer      $p_event_id Integer representing event identifier.
 * @param integer|null $p_actor_id User who changed the event, never a recipient.
 * @return void
 * @access public
 */
function calendar_notify_event_updated( $p_event_id, $p_actor_id ) {
    calendar_notify_event( $p_event_id, 'updated', $p_actor_id );
}

/**
 * Announce a cancelled event, or a cancelled part of a series, to its author
 * and its members.
 *
 * Must be called before the rows are deleted: neither the members nor the
 * name of the event can be read afterwards. Hence the event is passed as data
 * rather than as an identifier.
 * @param array|CalendarEventData $p_event           Event row or data object, still readable.
 * @param integer|null            $p_actor_id        User who deleted, never a recipient.
 * @param integer|null            $p_occurrence_date Occurrence that was cancelled on its own.
 * @param integer|null            $p_from_date       First occurrence of a cancelled tail of a series.
 * @return void
 * @access public
 */
function calendar_notify_event_deleted( $p_event, $p_actor_id, $p_occurrence_date = null, $p_from_date = null ) {

    if( $p_occurrence_date !== null ) {
        calendar_notify_event( $p_event, 'occurrence_cancelled', $p_actor_id, $p_occurrence_date );
        return;
    }

    if( $p_from_date !== null ) {
        calendar_notify_event( $p_event, 'from_date_cancelled', $p_actor_id, $p_from_date );
        return;
    }

    calendar_notify_event( $p_event, 'deleted', $p_actor_id );
}

/**
 * Tell one user that they became a member of an existing event.
 * The members of a brand new event are told about the event itself instead,
 * so this is only used when the composition of an event changes.
 * @param integer      $p_event_id Integer representing event identifier.
 * @param integer      $p_user_id  User that was added.
 * @param integer|null $p_actor_id User who added them, never a recipient.
 * @return void
 * @access public
 */
function calendar_notify_member_added( $p_event_id, $p_user_id, $p_actor_id ) {
    calendar_notify_member( $p_event_id, $p_user_id, 'member_added', $p_actor_id );
    calendar_notify_member_changed( $p_event_id, $p_user_id, 'member_added_others', $p_actor_id );
}

/**
 * Tell one user that they are no longer a member of an event
 * @param integer      $p_event_id Integer representing event identifier.
 * @param integer      $p_user_id  User that was removed.
 * @param integer|null $p_actor_id User who removed them, never a recipient.
 * @return void
 * @access public
 */
function calendar_notify_member_removed( $p_event_id, $p_user_id, $p_actor_id ) {
    calendar_notify_member( $p_event_id, $p_user_id, 'member_removed', $p_actor_id );
    calendar_notify_member_changed( $p_event_id, $p_user_id, 'member_removed_others', $p_actor_id );
}

/**
 * Tell the rest of the event that its composition has changed.
 *
 * Separate from the mail to the user the change is about: that one is news
 * they cannot do without, while this one is an announcement the matrix
 * switches off by default. The user the change is about is left out here, and
 * a user who acts on themselves - leaving an event of their own accord - is
 * still announced to the others.
 * @param integer      $p_event_id Integer representing event identifier.
 * @param integer      $p_user_id  User that was added or removed.
 * @param string       $p_action   Suffix of the notify_<action>_email_* strings.
 * @param integer|null $p_actor_id User who acted, a recipient only where the matrix says so.
 * @return void
 * @access private
 */
function calendar_notify_member_changed( $p_event_id, $p_user_id, $p_action, $p_actor_id ) {

    if( !calendar_notify_feature_enabled() ) {
        return;
    }

    $t_event = calendar_notify_event_fields( $p_event_id );

    # the name is resolved once, outside the language of any recipient, so
    # that every mail names the same user the same way
    $t_member_name = user_get_name( (int)$p_user_id );

    $t_recipients = calendar_notify_recipients( $t_event, $p_action, $p_actor_id, $p_user_id );

    foreach( $t_recipients as $t_user_id ) {
        calendar_notify_send( $t_event, $t_user_id, $p_action, null, array( $t_member_name ) );
    }
}

/**
 * Send the mail about a change of the composition to the one user it concerns.
 * The recipient is not looked for among the members: the removed user is gone
 * from the list by the time the mail is sent, and the added one is the only
 * user the news is about.
 * @param integer      $p_event_id Integer representing event identifier.
 * @param integer      $p_user_id  User the composition changed for.
 * @param string       $p_action   Suffix of the notify_<action>_email_* strings.
 * @param integer|null $p_actor_id User who acted, never a recipient.
 * @return void
 * @access private
 */
function calendar_notify_member( $p_event_id, $p_user_id, $p_action, $p_actor_id ) {

    if( !calendar_notify_feature_enabled() ) {
        return;
    }

    $c_user_id = (int)$p_user_id;

    if( $c_user_id <= 0 || !user_exists( $c_user_id ) || !user_is_enabled( $c_user_id ) ) {
        return;
    }

    if( $p_actor_id !== null && $c_user_id == (int)$p_actor_id ) {
        return;
    }

    if( !calendar_notify_user_enabled( $c_user_id, calendar_notify_action_pref( $p_action ) ) ) {
        return;
    }

    if( !access_has_event_level( plugin_config_get( 'view_event_threshold' ), $p_event_id, $c_user_id ) ) {
        return;
    }

    calendar_notify_send( calendar_notify_event_fields( $p_event_id ), $c_user_id, $p_action );
}

/**
 * Tell the author of an event - or whoever else the matrix names - that a
 * member replied whether they will take part. The member is never among the
 * recipients unless the matrix asks for the actor, and a reply of the author
 * to their own event is announced to nobody.
 * @param integer      $p_event_id Integer representing event identifier.
 * @param integer      $p_user_id  The member who replied.
 * @param integer      $p_status   One of the CALENDAR_RSVP_* constants.
 * @param integer|null $p_actor_id User who acted, a recipient only where the matrix says so.
 * @return void
 * @access public
 */
function calendar_notify_rsvp( $p_event_id, $p_user_id, $p_status, $p_actor_id ) {

    if( !calendar_notify_feature_enabled() ) {
        return;
    }

    $t_event = calendar_notify_event_fields( $p_event_id );

    if( (int)$p_user_id == $t_event['author_id'] ) {
        return;
    }

    $t_member_name = user_get_name( (int)$p_user_id );

    $t_recipients = calendar_notify_recipients( $t_event, 'rsvp', $p_actor_id, $p_user_id );

    foreach( $t_recipients as $t_recipient_id ) {
        calendar_notify_send( $t_event, $t_recipient_id, 'rsvp', null,
                              array( $t_member_name, array( 'lang' => 'rsvp_status_' . calendar_rsvp_status_name( $p_status ) ) ) );
    }
}
