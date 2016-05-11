# Piwik SiteMigration Plugin

[![Build Status](https://travis-ci.org/PiwikPRO/plugin-SiteMigration.svg?branch=master)](https://travis-ci.org/PiwikPRO/plugin-SiteMigration)

## Description

Migrate websites, and all the tracking data between two Piwik installations. 

This tool is useful in case you want to merge two Piwik installations, or if you want to move one or several websites to another Piwik server.

### Requirements

To migrate data from one Piwik server to another server, you must:

 * First make sure that both Piwik servers are using the latest Piwik version.
 * You must be able to connect to the Mysql server of the Target Piwik Server.
 * You must run the console command on the Piwik Server that data will be copied from.
  
### Migrating the data

Start the migration by calling from the command line CLI the following command:

    ./console migration:site idSite --db-prefix piwik_
    
The command will ask for the credentials to the target database.
 
It will then migrate the data from the current Piwik to the target Piwik.

### Options

Run `./console migration:site --help` to get a full list of options.
 
## FAQ

**How do I migrate site data between two dates only?**

You can use command options: `--date-from` and `--date-to`.

**How do I migrate tracking log data only, and skip migrating archived data?**

Just add the `--skip-archive-data` option.

**How do I migrate the archived data and skip the tracking data?**

Just add the `--skip-log-data` option.

**Can I run the command on the Target Piwik server (where data will be imported)?**

No, you must run the command from the source Piwik server (the server which contains the data you want to migrate).

**Can I run SiteMigration in big load environment?**

That is not advised. Process of moving large amounts of data may interfere with regular processing.

**Will SiteMigration take full data into the account?**

Data related to some of the plugins may be lost. If you are using such, we don't advise using automated migration process provided by this plugin.

We strongly advise to test whole process against your use case before using it in production environment.

## Changelog

**v1.0.7**

- Updated the plugin for compatibility with Piwik 2.10.

**v1.0.6**

- [#6](https://github.com/PiwikPRO/plugin-SiteMigration/issues/6): fixed a PHP 5.3 incompatibility

**v1.0.5**

- [#5](https://github.com/PiwikPRO/plugin-SiteMigration/issues/5): fixed `Integrity constraint violation: 1048 Column 'idaction_url' cannot be null`

**v1.0.4**

- [#3](https://github.com/PiwikPRO/plugin-SiteMigration/issues/3): fixed `--db-prefix` option

**v1.0.3**

- Documentation update

**v1.0.2**

- Documentation update & fixed bug when archive_blob tables are not found 

**v1.0.1**

- Documentation update

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
