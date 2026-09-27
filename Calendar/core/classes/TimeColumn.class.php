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
 * Description of ColumnTime
 *
 * @author ermolaev
 */
class TimeColumn extends ColumnForm {

    private $range_url;
    private $range_text;

    /**
     * @param string|null $p_range_url  link of the title cell switching the time range; NULL for a plain title
     * @param string      $p_range_text the shown time range, printed under the title
     */
    function __construct( $p_range_url = NULL, $p_range_text = '' ) {
        parent::__construct();
        $this->title_text    = plugin_lang_get( 'time_event' );
        $this->range_url     = $p_range_url;
        $this->range_text    = $p_range_text;
        $t_date              = self::$time_period_list[count( self::$time_period_list ) - 1];
        $this->last_row_text = gmdate( calendar_time_format(), $t_date );
    }

    protected function html_column_param() {
        return '<td class="column-time-td">';
    }

    /**
     * The whole title cell is the time range toggle when a link is given
     */
    protected function html_header() {
        if( $this->range_url === NULL ) {
            return parent::html_header();
        }

        # the plain title keeps the cell as high as the day titles; the button
        # is laid over it, so the hour rows of all columns stay aligned
        return '<ul class="column-header-day column-header-range"><span>' . $this->title_text . '</span>'
                . '<a class="btn btn-primary btn-white btn-round" href="' . htmlspecialchars( $this->range_url ) . '" title="' . string_attribute( plugin_lang_get( 'switch_time_range' ) ) . '">'
                . '<span>' . $this->title_text . '</span><span>' . $this->range_text . '</span>'
                . '</a></ul>';
    }

    protected function html_hour_text( $p_time ) {
        $t_result = '';

//        if( $p_time % (Calendar::$min_segment_time_in_hour * 2) == 0 ) {
//            $t_result .= gmdate( "H:i", $p_time );
//        }
        if( self::$intervals_per_hour % 2 == 0 ) {
            if( ( $p_time / self::$min_segment_time_in_hour ) % 2 == 0 ) {
                $t_result .= gmdate( calendar_time_format(), $p_time );
            }
        } elseif( ( $p_time / self::$min_segment_time_in_hour ) % 2 != 0 ) {
            $t_result .= gmdate( calendar_time_format(), $p_time );
        }
        return $t_result;
    }

}
