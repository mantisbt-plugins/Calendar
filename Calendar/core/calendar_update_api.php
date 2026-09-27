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
 * Check for a newer release of the plugin.
 *
 * The check is manual only: the administrator presses a button on the
 * configuration page, pages/update_check.php calls calendar_update_check()
 * and the outcome is kept in the plugin configuration until the next press.
 * Nothing leaves the server on its own, so an installation without internet
 * access or with a strict outbound policy is never surprised by a request.
 *
 * The source of truth is the latest published release of the upstream
 * repository, read through the GitHub REST API. Drafts and pre-releases are
 * not reported by that endpoint, so only a release meant for everybody is
 * offered. The HTTP client is the Guzzle of the MantisBT core, the same one
 * the Google client already relies on.
 */

/**
 * Endpoint returning the latest published release of the plugin.
 */
define( 'CALENDAR_UPDATE_RELEASES_LATEST_URL', 'https://api.github.com/repos/mantisbt-plugins/Calendar/releases/latest' );

/**
 * Prefix every release page link has to start with; anything else in the
 * answer is not shown as a link.
 */
define( 'CALENDAR_UPDATE_RELEASE_PAGE_PREFIX', 'https://github.com/mantisbt-plugins/Calendar/' );

/**
 * Query the latest release and remember the outcome.
 *
 * The stored array always holds 'checked_at'. On success it also holds
 * 'latest' (the version without the leading "v"), 'url' of the release page
 * and 'published_at'; on failure it holds 'error' with a short reason and the
 * previous successful values are dropped, so the page never shows a stale
 * verdict next to a failure.
 *
 * @return array the stored outcome
 */
function calendar_update_check() {
    $t_result = array( 'checked_at' => time() );

    try {
        $t_client = new \GuzzleHttp\Client( array(
            'timeout'         => 10,
            'connect_timeout' => 5,
            'http_errors'     => TRUE,
            'headers'         => array(
                # GitHub rejects requests without a User-Agent
                'User-Agent' => 'MantisBT-Calendar-plugin/' . plugin_get()->version,
                'Accept'     => 'application/vnd.github+json',
            ),
        ) );

        $t_response = $t_client->get( CALENDAR_UPDATE_RELEASES_LATEST_URL );
        $t_release  = json_decode( (string)$t_response->getBody(), TRUE );

        if( !is_array( $t_release ) || empty( $t_release['tag_name'] ) ) {
            $t_result['error'] = 'unexpected answer';
        } else {
            # older tags read "v.2.7.3", hence the dot
            $t_result['latest']       = ltrim( $t_release['tag_name'], 'vV.' );
            # the link is shown to the administrator, so it has to lead to the
            # release pages of the plugin and nowhere else
            $t_result['url']          = isset( $t_release['html_url'] ) && is_string( $t_release['html_url'] )
                    && strpos( $t_release['html_url'], CALENDAR_UPDATE_RELEASE_PAGE_PREFIX ) === 0 ? $t_release['html_url'] : '';
            $t_result['published_at'] = isset( $t_release['published_at'] ) ? (int)strtotime( $t_release['published_at'] ) : 0;
        }
    } catch( \Exception $e ) {
        $t_result['error'] = $e->getMessage();
    }

    plugin_config_set( 'update_check_result', $t_result );

    return $t_result;
}

/**
 * The outcome of the last check, or an empty array when it never ran.
 * @return array
 */
function calendar_update_result_get() {
    $t_result = plugin_config_get( 'update_check_result' );
    return is_array( $t_result ) ? $t_result : array();
}

/**
 * Whether the last successful check found a version newer than the installed one.
 * @param array $p_result outcome as returned by calendar_update_result_get()
 * @return boolean
 */
function calendar_update_is_available( array $p_result ) {
    return isset( $p_result['latest'] ) && version_compare( $p_result['latest'], plugin_get()->version, '>' );
}
