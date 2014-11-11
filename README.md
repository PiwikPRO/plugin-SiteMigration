# Piwik SiteMigration Plugin

## Description

Migrate websites and website data between two Piwik installations. 

[![Build Status](https://magnum.travis-ci.com/PiwikPRO/plugin-SiteMigration.svg?token=mhqCmy1K4zUjCiYpLN8c&branch=master)](https://magnum.travis-ci.com/PiwikPRO/plugin-SiteMigration)

## Usage

Migration can be started from CLI by running `./console migration:site idSite`. The command will ask for the credentials to the target database.

You can run `./console migration:site --help` to get a full list of options.
 
```
Usage:
 migration:site [--skip-archive-data] [--skip-log-data] [-H|--host="..."] [-U|--username="..."] [-P|--password="..."] [-N|--dbname="..."] [--prefix="..."] [--port="..."] [-F|--date-from="..."] [-T|--date-to="..."] idSite

Arguments:
 idSite                Site id

Options:
 --skip-archive-data   Skip migration of archive data
 --skip-log-data       Skip migration of log data
 --host (-H)           Destination database host
 --username (-U)       Destination database username
 --password (-P)       Destination database password
 --dbname (-N)         Destination database name
 --prefix              Destination database table prefix (default: "piwik_")
 --port                Destination database port (default: "3306")
 --date-from (-F)      Start date from which data should be migrated
 --date-to (-T)        Start date from which data should be migrated
 --help (-h)           Display this help message.
 --quiet (-q)          Do not output any message.
 --verbose (-v|vv|vvv) Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
 --version (-V)        Display this application version.
 --ansi                Force ANSI output.
 --no-ansi             Disable ANSI output.
 --no-interaction (-n) Do not ask any interactive question.
 --piwik-domain        Piwik URL (protocol and domain) eg. "http://piwik.example.org"
```

## FAQ

**How can I migrate site data between two dates?**

You can use command options: `--date-from` and `--date-to`.

**How can I skip migrating archived data?**

Just add the `--skip-archive-data` option.

## Changelog

**v1.0.0**

- First stable release
- Bugfixes

**v0.1.1**

- Changed license to free plugin
- Changed name to SiteMigration

**v0.1.0**

- Initial release

## Credits

Created by [Piwik PRO](http://piwik.pro/)
