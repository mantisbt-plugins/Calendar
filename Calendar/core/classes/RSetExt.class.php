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

namespace CalendarPluginRRuleExt;
/**
 * Description of RsetRule
 *
 * @author g.ermolaev
 */
class RSetExt extends \RRule\RSet {
    //put your code here
    public function rfcString() {
        $t_rfc_string = '';
        foreach( $this->getRRules() as $t_rrule ) {
            $t_rfc_string .= $t_rrule->rfcString();
        }
        foreach( $this->getExDates() as $t_date ) {
            // EXDATE is written with a literal "Z", so the value has to be in UTC
            $t_date_utc = ( clone $t_date )->setTimezone( new \DateTimeZone( 'UTC' ) );
            $t_rfc_string .= sprintf( "\nEXDATE:%s", $t_date_utc->format( 'Ymd\THis\Z' ) );
        }
        return $t_rfc_string;
    }
}
