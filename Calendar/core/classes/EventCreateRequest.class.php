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

namespace CalendarPluginApi;

/**
 * Request object of calendar_api_event_create().
 *
 * This class is the contract between the Calendar plugin and any other
 * plugin that wants to create events: the caller fills in the public
 * properties and hands the object over. Property types are enforced by PHP,
 * so a wrong type fails at the assignment made by the caller rather than
 * somewhere inside the Calendar code.
 *
 * It is a plain data container - no database access and no plugin_* calls
 * happen here, the only logic is validate().
 *
 * @author Grigoriy Ermolaev
 */
class EventCreateRequest {

    /**
     * Identifier of the project the event belongs to. Required, > 0.
     *
     * @var int
     */
    public int $project_id;

    /**
     * Event title, shown in the calendar grid. Required, must not be blank.
     *
     * @var string
     */
    public string $name;

    /**
     * Free text description of the event, optional. Defaults to an empty
     * string.
     *
     * @var string
     */
    public string $description = '';

    /**
     * Identifier of the user the event is created on behalf of. Required,
     * > 0. The access check and the Google synchronisation are both made
     * for this user, and the event is authored by them.
     *
     * @var int
     */
    public int $user_id;

    /**
     * Start of the event, Unix timestamp. Required, > 0.
     *
     * @var int
     */
    public int $date_from;

    /**
     * End of the event, Unix timestamp. Required, > 0, and later than
     * $date_from; it may lie on a later day than $date_from, which makes
     * the event span several days. For a recurring event this is the end of
     * the last occurrence, the same way the event creation form stores it.
     *
     * @var int
     */
    public int $date_to;

    /**
     * Length of one occurrence in seconds, > 0. Optional for a single event,
     * where it defaults to $date_to - $date_from; required for a recurring
     * one, whose $date_to is the end of the whole series.
     *
     * @var int|null
     */
    public ?int $duration = null;

    /**
     * Identifiers of the issues to attach the event to. An empty array means
     * a standalone event; every id must be an existing issue the user of the
     * request is allowed to view.
     *
     * @var int[]
     */
    public array $bug_ids = array();

    /**
     * Identifiers of the event members. An empty array means "the author
     * only", i.e. it is replaced by array( $user_id ).
     *
     * @var int[]
     */
    public array $members = array();

    /**
     * Recurrence of the event as an RFC 5545 string (RRULE, optionally
     * followed by EXDATE lines). An empty string means a single event.
     *
     * @var string
     */
    public string $recurrence_pattern = '';

    /**
     * Name of the timezone the event is anchored to, e.g. 'Europe/Moscow'.
     * An empty or unknown name falls back to the timezone of the MantisBT
     * instance.
     *
     * @var string
     */
    public string $timezone = '';

    /**
     * Reminders of the event, offsets in seconds before the start of an
     * occurrence, mirroring the three states of the event form:
     * - NULL (the default): the event defines no reminders of its own, every
     *   recipient is reminded by their personal default reminders;
     * - a list of offsets, each at least 60: these reminders apply to every
     *   recipient, personal defaults are ignored;
     * - an empty array: reminders are switched off for this event entirely.
     * The offsets are validated against the same limits as the event form
     * (maximum offset, maximum count). Stored regardless of the reminder
     * feature switch; delivery only happens while the feature is on.
     *
     * @var int[]|null
     */
    public ?array $reminders = null;

    /**
     * Check that the request can be used to create an event, and trigger a
     * MantisBT error if it cannot.
     *
     * A typed property that was never assigned stays uninitialized, and
     * isset() reports it as not set - that is how the required properties
     * are told apart from the optional ones, which all carry a default.
     * The date_from < date_to relation is deliberately not checked here,
     * CalendarEventData::validate() is the single place that owns it.
     *
     * @return void
     */
    public function validate() {

        foreach( array( 'project_id', 'name', 'user_id', 'date_from', 'date_to' ) as $t_required_property ) {
            if( !isset( $this->{$t_required_property} ) ) {
                \error_parameters( $t_required_property );
                \trigger_error( \ERROR_EMPTY_FIELD, \ERROR );
            }
        }

        if( \is_blank( $this->name ) ) {
            \error_parameters( 'name' );
            \trigger_error( \ERROR_EMPTY_FIELD, \ERROR );
        }

        foreach( array( 'project_id', 'user_id', 'date_from', 'date_to' ) as $t_positive_property ) {
            if( $this->{$t_positive_property} <= 0 ) {
                \error_parameters( $t_positive_property );
                \trigger_error( \ERROR_INVALID_FIELD_VALUE, \ERROR );
            }
        }

        if( $this->duration !== null && $this->duration <= 0 ) {
            \error_parameters( 'duration' );
            \trigger_error( \ERROR_INVALID_FIELD_VALUE, \ERROR );
        }

        if( $this->duration === null && !\is_blank( $this->recurrence_pattern ) ) {
            \error_parameters( 'duration' );
            \trigger_error( \ERROR_EMPTY_FIELD, \ERROR );
        }

        foreach( $this->bug_ids as $t_bug_id ) {
            if( !is_numeric( $t_bug_id ) || (int)$t_bug_id <= 0 ) {
                \error_parameters( 'bug_ids' );
                \trigger_error( \ERROR_INVALID_FIELD_VALUE, \ERROR );
            }
        }

        foreach( $this->members as $t_member_id ) {
            if( !is_numeric( $t_member_id ) || (int)$t_member_id <= 0 ) {
                \error_parameters( 'members' );
                \trigger_error( \ERROR_INVALID_FIELD_VALUE, \ERROR );
            }
        }

        # only the shape is checked here; the limits live in the plugin
        # config and are enforced by calendar_reminder_offsets_normalize()
        if( $this->reminders !== null ) {
            foreach( $this->reminders as $t_offset ) {
                if( !is_numeric( $t_offset ) || (int)$t_offset < 60 ) {
                    \error_parameters( 'reminders' );
                    \trigger_error( \ERROR_INVALID_FIELD_VALUE, \ERROR );
                }
            }
        }
    }

}
