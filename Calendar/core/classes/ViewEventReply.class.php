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
 * The week grid of the reply page: the days one occurrence of an event
 * covers, with every event of the member on those days, so that they see
 * what the invitation collides with before they answer it. The invitation
 * is drawn as usual and the other events faded, so the eye finds it first.
 *
 * The grid is read only - no time range selection, no switch of the time
 * range; the range is the full day when the occurrence reaches outside the
 * working hours of the member, which would otherwise cut it off.
 */
class ViewEventReply extends WeekCalendar {

    /**
     * @param integer $p_event_id   The event of the invitation.
     * @param integer $p_occurrence Start of the occurrence shown.
     * @param integer $p_duration   Length of the occurrence in seconds.
     * @param integer $p_user_id    The member whose events are shown.
     */
    public function __construct( $p_event_id, $p_occurrence, $p_duration, $p_user_id ) {

        self::$focus_event_id = (int)$p_event_id;

        $t_days = array_keys( calendar_event_day_segments( $p_occurrence, max( 1, (int)$p_duration ) ) );

        parent::__construct( get_days_object( $t_days, ALL_PROJECTS, $p_user_id ), plugin_page( 'view' ),
                             self::needs_full_time( $p_occurrence, $p_duration, $p_user_id ) );
    }

    /**
     * Whether the occurrence reaches outside the working hours of the member
     * @param integer $p_occurrence Start of the occurrence.
     * @param integer $p_duration   Length of the occurrence in seconds.
     * @param integer $p_user_id    The member.
     * @return boolean
     */
    private static function needs_full_time( $p_occurrence, $p_duration, $p_user_id ) {

        $t_day_start  = (int)plugin_config_get( 'time_day_start', NULL, FALSE, $p_user_id );
        $t_day_finish = (int)plugin_config_get( 'time_day_finish', NULL, FALSE, $p_user_id );
        $t_midnight   = strtotime( date( 'Y-m-d', $p_occurrence ) );

        return $p_occurrence - $t_midnight < $t_day_start
                || $p_occurrence + (int)$p_duration - $t_midnight > $t_day_finish;
    }

    protected function print_headline() {
        echo '<div class="widget-header widget-header-small">';
        echo '<h4 class="widget-title lighter">';
        echo '<i class="ace-icon fa fa-calendar"></i>';
        echo plugin_lang_get( 'rsvp_page_calendar_title' );
        echo '</h4>';
        echo '</div>';
    }

}
