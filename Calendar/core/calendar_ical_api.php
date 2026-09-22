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
 * iCalendar (RFC 5545) export of a single event.
 *
 * The file is what a user imports into the calendar of their choice - the
 * Google sync covers Google only, the .ics file covers everybody else. It is
 * offered as a link in the notification mails and on the event page, and it
 * is served by pages/event_ics.php; the public API hands the same file to
 * other plugins that deliver notifications through their own channel.
 *
 * One file holds one event row: a plain event, or a whole series with its
 * RRULE and EXDATEs. An occurrence that was split off a series is a row of
 * its own and is exported on its own, so the series and the split-off
 * occurrence import as two calendar entries, exactly the way the plugin
 * stores them. The file is published (METHOD:PUBLISH), never sent as an
 * invitation: a client does not answer it, and there are no attendees in it.
 *
 * The UID is derived from the event identifier and the SEQUENCE from the
 * date of the last change, so a client that matches on UID replaces its copy
 * of the event when a newer file is imported instead of adding a second one.
 *
 * Times: a plain event is written in UTC, which every client understands
 * without a timezone definition. A series is anchored in the timezone of its
 * DTSTART - the same anchor the plugin expands the occurrences with - so the
 * local time of the occurrences survives the DST transitions (issue #104);
 * the file then carries a VTIMEZONE built from the transitions of that zone,
 * as the RFC requires and as Outlook insists on.
 */

# Longest line of an iCalendar file, in octets, before it has to be folded.
define( 'CALENDAR_ICAL_LINE_LENGTH', 75 );
# Span the timezone definition covers on either side of the event.
define( 'CALENDAR_ICAL_TIMEZONE_MARGIN', 366 * SECONDS_PER_DAY );

/**
 * Absolute link to the iCalendar file of an event.
 *
 * Built from the configured path of the installation, the way the mails
 * build the link to the event, so it holds without a request to take the
 * host from.
 * @param integer $p_event_id Integer representing event identifier.
 * @return string
 * @access public
 */
function calendar_ical_url( $p_event_id ) {
    return config_get_global( 'path' ) . plugin_page( 'event_ics', true ) . '&event_id=' . (int)$p_event_id;
}

/**
 * Name of the iCalendar file of an event, the name of the event with the
 * characters a file name cannot hold replaced.
 * @param array $p_event Event row.
 * @return string
 * @access public
 */
function calendar_ical_filename( array $p_event ) {

    $t_name = file_clean_name( trim( (string)$p_event['name'] ) );

    if( is_blank( $t_name ) ) {
        $t_name = 'event-' . (int)$p_event['id'];
    }

    return $t_name . '.ics';
}

/**
 * The iCalendar file of an event, built for the given user.
 *
 * The user decides what the description discloses: of the issues the event
 * is attached to, only those they may view are listed, the way the event
 * page lists them. Access to the event itself is not checked here, the
 * caller does that.
 * @param integer $p_event_id Integer representing event identifier.
 * @param integer $p_user_id  User the file is built for.
 * @return string text of the file, lines ended with CRLF
 * @access public
 */
function calendar_ical_event( $p_event_id, $p_user_id ) {

    $t_event = event_get_row( (int)$p_event_id );

    $t_duration = (int)$t_event['duration'];
    $t_start    = (int)$t_event['date_from'];
    $t_end      = $t_duration > 0 ? $t_start + $t_duration : (int)$t_event['date_to'];

    $t_timezone  = new DateTimeZone( 'UTC' );
    $t_rrules    = array();
    $t_exdates   = array();
    $t_series_to = $t_end;

    if( !is_blank( $t_event['recurrence_pattern'] ) ) {

        $t_rset  = new \RRule\RSet( $t_event['recurrence_pattern'] );
        $t_rules = $t_rset->getRRules();

        if( count( $t_rules ) > 0 ) {

            # the occurrences are expanded from the DTSTART of the rule, so the
            # file is anchored there too, timezone included
            $t_dtstart = $t_rules[0]->getRule()['DTSTART'];

            if( $t_dtstart instanceof DateTimeInterface ) {
                $t_start    = $t_dtstart->getTimestamp();
                $t_end      = $t_start + max( 0, $t_duration );
                $t_timezone = calendar_ical_timezone( $t_dtstart->getTimezone() );
            }

            foreach( $t_rules as $t_rule ) {
                # rfcString() writes the DTSTART line first, the rule follows
                foreach( explode( "\n", $t_rule->rfcString() ) as $t_line ) {
                    if( strpos( $t_line, 'RRULE:' ) === 0 ) {
                        $t_rrules[] = $t_line;
                    }
                }
            }

            $t_exdates   = $t_rset->getExDates();
            $t_series_to = max( $t_end, (int)$t_event['date_to'] );
        }
    }

    $t_lines = array(
                              'BEGIN:VCALENDAR',
                              'VERSION:2.0',
                              'PRODID:-//MantisBT//Calendar plugin//EN',
                              'CALSCALE:GREGORIAN',
                              'METHOD:PUBLISH',
    );

    if( $t_timezone->getName() != 'UTC' ) {
        # a year on either side, so that the transition in force at the start
        # of the series is known and the last occurrences are covered too
        $t_lines = array_merge( $t_lines, calendar_ical_vtimezone( $t_timezone, $t_start - CALENDAR_ICAL_TIMEZONE_MARGIN, $t_series_to + CALENDAR_ICAL_TIMEZONE_MARGIN ) );
    }

    $t_lines[] = 'BEGIN:VEVENT';
    $t_lines[] = 'UID:' . calendar_ical_uid( (int)$t_event['id'] );
    $t_lines[] = 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' );

    $t_changed = (int)$t_event['date_changed'];
    if( $t_changed > 0 ) {
        $t_lines[] = 'LAST-MODIFIED:' . gmdate( 'Ymd\THis\Z', $t_changed );
        # grows with every change, which is all a client asks of it
        $t_lines[] = 'SEQUENCE:' . $t_changed;
    }

    $t_lines[] = calendar_ical_datetime_property( 'DTSTART', $t_start, $t_timezone );

    # DTEND has to lie after DTSTART, an event without a length has none
    if( $t_end > $t_start ) {
        $t_lines[] = calendar_ical_datetime_property( 'DTEND', $t_end, $t_timezone );
    }

    $t_lines = array_merge( $t_lines, $t_rrules );

    # an exception date has to be written the way DTSTART is - some clients
    # compare the two literally rather than as instants
    foreach( $t_exdates as $t_exdate ) {
        $t_lines[] = calendar_ical_datetime_property( 'EXDATE', $t_exdate->getTimestamp(), $t_timezone );
    }

    $t_lines[] = 'SUMMARY:' . calendar_ical_escape( project_get_name( (int)$t_event['project_id'], false ) . ': ' . $t_event['name'] );

    $t_description = calendar_ical_description( $t_event, (int)$p_user_id );
    if( !is_blank( $t_description ) ) {
        $t_lines[] = 'DESCRIPTION:' . calendar_ical_escape( $t_description );
    }

    $t_lines[] = 'URL:' . config_get_global( 'path' ) . plugin_page( 'view', true )
            . '&event_id=' . (int)$t_event['id'] . '&date=' . (int)$t_event['date_from'];
    $t_lines[] = 'STATUS:CONFIRMED';

    # the reminders the user gets from the plugin, as display alarms; a client
    # that honours them needs no setting up by hand, one that ignores them
    # falls back to its own defaults
    if( calendar_reminder_feature_enabled() ) {
        $t_effective = calendar_reminder_effective( (int)$t_event['id'], (int)$p_user_id );

        foreach( $t_effective['offsets'] as $t_offset ) {
            $t_lines[] = 'BEGIN:VALARM';
            $t_lines[] = 'ACTION:DISPLAY';
            $t_lines[] = 'TRIGGER:' . calendar_ical_duration( -(int)$t_offset );
            $t_lines[] = 'DESCRIPTION:' . calendar_ical_escape( $t_event['name'] );
            $t_lines[] = 'END:VALARM';
        }
    }

    $t_lines[] = 'END:VEVENT';
    $t_lines[] = 'END:VCALENDAR';

    $t_content = '';
    foreach( $t_lines as $t_line ) {
        $t_content .= calendar_ical_fold( $t_line ) . "\r\n";
    }

    return $t_content;
}

/**
 * Identifier of an event that is the same in every file ever built of it.
 * The host is taken from the configured path rather than from the request,
 * so a file built by a cron job carries the same identifier as one built by
 * a page.
 * @param integer $p_event_id Integer representing event identifier.
 * @return string
 * @access private
 */
function calendar_ical_uid( $p_event_id ) {

    $t_host = parse_url( config_get_global( 'path' ), PHP_URL_HOST );

    if( is_blank( $t_host ) ) {
        $t_host = 'mantisbt';
    }

    return 'calendar-event-' . (int)$p_event_id . '@' . $t_host;
}

/**
 * Timezone a file can name: an IANA zone as it is, anything an iCalendar
 * TZID cannot express - UTC itself, or a bare offset such as "+03:00" - as
 * UTC.
 * @param DateTimeZone $p_timezone Zone of the DTSTART of the rule.
 * @return DateTimeZone
 * @access private
 */
function calendar_ical_timezone( DateTimeZone $p_timezone ) {

    $t_name = $p_timezone->getName();

    if( strpos( $t_name, ':' ) !== false || in_array( $t_name, array( 'UTC', 'GMT', 'Z' ), true ) ) {
        return new DateTimeZone( 'UTC' );
    }

    return $p_timezone;
}

/**
 * A DATE-TIME property: in UTC with the Z suffix, or as the local time of
 * the given zone with a TZID parameter.
 * @param string       $p_property  Name of the property.
 * @param integer      $p_timestamp Unix timestamp of the instant.
 * @param DateTimeZone $p_timezone  Zone the file is anchored in.
 * @return string
 * @access private
 */
function calendar_ical_datetime_property( $p_property, $p_timestamp, DateTimeZone $p_timezone ) {

    if( $p_timezone->getName() == 'UTC' ) {
        return $p_property . ':' . gmdate( 'Ymd\THis\Z', (int)$p_timestamp );
    }

    $t_datetime = new DateTime( '@' . (int)$p_timestamp );
    $t_datetime->setTimezone( $p_timezone );

    return $p_property . ';TZID=' . $p_timezone->getName() . ':' . $t_datetime->format( 'Ymd\THis' );
}

/**
 * VTIMEZONE component of a zone, listing every transition of the given span
 * outright rather than as yearly rules: it is longer, but it is right for
 * any zone, the ones whose rules changed over the years included.
 * @param DateTimeZone $p_timezone Zone to describe.
 * @param integer      $p_from     Start of the span, Unix timestamp.
 * @param integer      $p_to       End of the span, Unix timestamp.
 * @return array lines of the component
 * @access private
 */
function calendar_ical_vtimezone( DateTimeZone $p_timezone, $p_from, $p_to ) {

    $t_lines = array( 'BEGIN:VTIMEZONE', 'TZID:' . $p_timezone->getName() );

    # the first entry is not a transition but the offset in force at $p_from
    $t_offset_from = null;

    foreach( $p_timezone->getTransitions( (int)$p_from, (int)$p_to ) as $t_transition ) {

        $t_offset_to = (int)$t_transition['offset'];

        if( $t_offset_from === null ) {
            $t_offset_from = $t_offset_to;
        }

        $t_component = $t_transition['isdst'] ? 'DAYLIGHT' : 'STANDARD';

        $t_lines[] = 'BEGIN:' . $t_component;
        $t_lines[] = 'TZOFFSETFROM:' . calendar_ical_utc_offset( $t_offset_from );
        $t_lines[] = 'TZOFFSETTO:' . calendar_ical_utc_offset( $t_offset_to );
        $t_lines[] = 'TZNAME:' . calendar_ical_escape( $t_transition['abbr'] );
        # the moment of the transition as the wall clock showed it just before
        $t_lines[] = 'DTSTART:' . gmdate( 'Ymd\THis', (int)$t_transition['ts'] + $t_offset_from );
        $t_lines[] = 'END:' . $t_component;

        $t_offset_from = $t_offset_to;
    }

    $t_lines[] = 'END:VTIMEZONE';

    return $t_lines;
}

/**
 * An offset from UTC in seconds as the +HHMM form of the TZOFFSET properties
 * @param integer $p_seconds Offset in seconds, negative west of Greenwich.
 * @return string
 * @access private
 */
function calendar_ical_utc_offset( $p_seconds ) {

    $t_abs = abs( (int)$p_seconds );

    return ( $p_seconds < 0 ? '-' : '+' ) . sprintf( '%02d%02d', intdiv( $t_abs, 3600 ), intdiv( $t_abs % 3600, 60 ) );
}

/**
 * A number of seconds as an RFC 5545 duration, e.g. -PT10M, -PT1H30M, -P2D
 * @param integer $p_seconds Length in seconds, negative for "before".
 * @return string
 * @access private
 */
function calendar_ical_duration( $p_seconds ) {

    $t_abs   = abs( (int)$p_seconds );
    $t_days  = intdiv( $t_abs, 86400 );
    $t_hours = intdiv( $t_abs % 86400, 3600 );
    $t_mins  = intdiv( $t_abs % 3600, 60 );
    $t_secs  = $t_abs % 60;

    $t_text = ( $p_seconds < 0 ? '-' : '' ) . 'P';

    if( $t_days > 0 ) {
        $t_text .= $t_days . 'D';
    }

    if( $t_hours > 0 || $t_mins > 0 || $t_secs > 0 || $t_days == 0 ) {
        $t_text .= 'T';

        if( $t_hours > 0 ) {
            $t_text .= $t_hours . 'H';
        }

        if( $t_mins > 0 ) {
            $t_text .= $t_mins . 'M';
        }

        if( $t_secs > 0 || ( $t_hours == 0 && $t_mins == 0 ) ) {
            $t_text .= $t_secs . 'S';
        }
    }

    return $t_text;
}

/**
 * Text of the DESCRIPTION: the description of the event, then the issues it
 * is attached to that the user may view, each with its link.
 * @param array   $p_event   Event row.
 * @param integer $p_user_id User the file is built for.
 * @return string
 * @access private
 */
function calendar_ical_description( array $p_event, $p_user_id ) {

    $t_parts = array();

    if( !is_blank( $p_event['description'] ) ) {
        $t_parts[] = trim( (string)$p_event['description'] );
    }

    foreach( event_get_attached_bugs_id( (int)$p_event['id'] ) as $t_bug_id ) {

        # a deleted issue leaves its link behind, an unreadable one is not
        # disclosed - the event page draws the same line
        if( !bug_exists( $t_bug_id ) ) {
            continue;
        }

        $t_view_threshold = config_get( 'view_bug_threshold', null, $p_user_id, bug_get_field( $t_bug_id, 'project_id' ) );

        if( !access_has_bug_level( $t_view_threshold, $t_bug_id, $p_user_id ) ) {
            continue;
        }

        $t_parts[] = bug_format_id( $t_bug_id ) . ': ' . bug_get_field( $t_bug_id, 'summary' )
                . "\n" . string_get_bug_view_url_with_fqdn( $t_bug_id );
    }

    return implode( "\n\n", $t_parts );
}

/**
 * A text value with the characters the format reserves escaped
 * @param string $p_text Raw text.
 * @return string
 * @access private
 */
function calendar_ical_escape( $p_text ) {

    $t_text = str_replace( "\r", '', (string)$p_text );
    $t_text = addcslashes( $t_text, '\\;,' );

    return str_replace( "\n", '\\n', $t_text );
}

/**
 * A content line folded to the length the format allows, without ever
 * cutting a multi-byte character in two
 * @param string $p_line Content line without its line ending.
 * @return string
 * @access private
 */
function calendar_ical_fold( $p_line ) {

    if( strlen( $p_line ) <= CALENDAR_ICAL_LINE_LENGTH ) {
        return $p_line;
    }

    $t_chars = preg_split( '//u', $p_line, -1, PREG_SPLIT_NO_EMPTY );

    # not valid UTF-8, better one long line than a broken character
    if( $t_chars === false ) {
        return $p_line;
    }

    $t_folded = '';
    $t_length = 0;

    foreach( $t_chars as $t_char ) {

        $t_size = strlen( $t_char );

        if( $t_length + $t_size > CALENDAR_ICAL_LINE_LENGTH ) {
            $t_folded .= "\r\n ";
            $t_length  = 1;
        }

        $t_folded .= $t_char;
        $t_length += $t_size;
    }

    return $t_folded;
}
