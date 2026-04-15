# Changelog

## Unreleased

## v0.2.8

- rename localData.contacts.webPage to localData.contacts.homepageUrl (for unified naming)

## v0.2.7

- rename contacts.officeHours to contacts.secretariatInformation

## v0.2.6

- Add support for multi-term search parameters

## v0.2.5

- Fix migration: Update tables (add foreign key constraints) instead of re-creating them 
- Add re-create cache command

## v0.2.3

- fix parent replacement on cache refresh
- re-create cache tables according to schema auto-generated from entity metadata

## v0.2.2

- Fix org name missing in some cases

## v0.2.0

- Add more local data source attributes
- Implement Campusonline Public REST API, caching the results in a local database table and getting organizations form there
- Drop support for CO XML Legacy API

## v0.1.23

- Add support for Symfony 7

## v0.1.22

- Modernize code
- Increase cache ttl to a bit more than one day

## v0.1.21

* Remove api-platform dependency
* Drop support for PHP 8.1
* Drop support for Psalm
* Drop support for Symfony 5

## v0.1.20

* Add support for api-platform 3.2

## v0.1.18

* Add support for Symfony 6

## v0.1.17

* Drop support for PHP 7.4/8.0

## v0.1.16

* Drop support for PHP 7.3

## v0.1.11

* Use the global "cache.app" adapter for caching instead of always using the filesystem adapter

## v0.1.8

* require api-platform 2.7

## v0.1.5

* Added a health check
