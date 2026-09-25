# Installation

Calendar requires MantisBT 2.26.0 or newer and PHP 7.4 or newer (the bundled dependencies need PHP 7.4). For MantisBT 2.14 – 2.25.x use the [2.6.x line](https://github.com/mantisbt-plugins/Calendar/releases/tag/v2.6.5) (fixes only).

Download `Calendar-vX.Y.Z.zip` from the [latest release](https://github.com/mantisbt-plugins/Calendar/releases/latest) — the archive contains the third-party dependencies. A plain git checkout is **not** installable: run `composer install --no-dev --optimize-autoloader` in it first, otherwise MantisBT marks the plugin as invalid (`api/vendor/autoload.php` is missing).

1. Copy Calendar folder into plugins folder.
2. Open Mantis with browser.
3. Log in as administrator.
4. Go to Manage -> Manage Plugins.
5. Find Calendar in the list.
6. Click Install.


## Upgrading

Replace the `Calendar` folder in `plugins/` with the new one, then open Manage -> Manage Plugins and click **Upgrade** next to Calendar. Release notes of every version list the steps a particular upgrade needs; a release that migrates data asks for confirmation before touching the database.
