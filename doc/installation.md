# Installation

Calendar requires MantisBT 2.26.0 or newer and PHP 7.4 or newer (the bundled dependencies need PHP 7.4). For MantisBT 2.14 – 2.25.x use the [2.6.x line](https://github.com/mantisbt-plugins/Calendar/releases/tag/v2.6.5) (fixes only).

1. Download `Calendar-vX.Y.Z.zip` from the Assets of the [latest release](https://github.com/mantisbt-plugins/Calendar/releases/latest) (not the "Source code" archive) and unpack it.
2. Copy the Calendar folder into the plugins folder.
3. Open Mantis with browser.
4. Log in as administrator.
5. Go to Manage -> Manage Plugins.
6. Find Calendar in the list.
7. Click Install.


## Upgrading

Replace the `Calendar` folder in `plugins/` with the new one, then open Manage -> Manage Plugins and click **Upgrade** next to Calendar. Release notes of every version list the steps a particular upgrade needs; a release that migrates data asks for confirmation before touching the database.
