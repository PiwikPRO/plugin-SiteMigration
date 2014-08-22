# Piwik SiteMigration Plugin

## Description

This plugin allows to migrate sites between Piwik instances.

## Usage

Migration can be started from CLI by typing
`./console migration:site idSite`
There is only one argument: idSite. Before migrating data the command will ask for new database credentials. Please provide all necessary credentials to the destination database.

Please run
`./console migration:site --help`
To get a full list of options.

__Usage:__
 migration:site [--skip-archived] [--skip-raw] [-H|--host="..."] [-U|--username="..."] [-P|--password="..."] [-N|--dbname="..."] [--prefix="..."] [--port="..."] [-F|--date-from="..."] [-T|--date-to="..."] [-I|--new-id-site="..."] idSite

__Arguments:__
 idSite                Site id

__Options:__
 --skip-archived       Skip migration of archived data
 --skip-raw            Skip migration of raw data
 --host (-H)           Destination database host
 --username (-U)       Destination database username
 --password (-P)       Destination database password
 --dbname (-N)         Destination database name
 --prefix              Destination database table prefix (default: "piwik_")
 --port                Destination database port (default: "3306")
 --date-from (-F)      Start date from which data should be migrated
 --date-to (-T)        Start date from which data should be migrated
 --new-id-site (-I)    New site id, if provided site config will not be migrated, raw and archive data will be copied into existing site
 --help (-h)           Display this help message.
 --quiet (-q)          Do not output any message.
 --verbose (-v|vv|vvv) Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
 --version (-V)        Display this application version.
 --ansi                Force ANSI output.
 --no-ansi             Disable ANSI output.
 --no-interaction (-n) Do not ask any interactive question.
 --piwik-domain        Piwik URL (protocol and domain) eg. "http://piwik.example.org"

## FAQ

__How can I migrate site data between two dates?__
You can use command options: --date-from and --date-to

__How can I migrate site data to an existing page?__
Please make sure that the config of the new site id is present and it is in sync with the config of the old file (custom vars, goals, etc).
Please check also if the site log is empty (both raw and archive).

If both above conditions are met run the migrate:site command with the -I|--new-site-id param - this will skip config migration and will go straightly to raw data log and archive migration.

__How can I skip migrating archived data?__
Just add the "--skip-archived" option.

## Changelog

__v0.1.1__
- Changed license to free plugin
- Changed name to SiteMigration

__v0.1.0__
- Initial release

## Contributors

Created by [Piwik PRO](http://piwik.pro/)

