Grant Metrics
=============

A Wikimedia Foundation tool that provides grantees a simple, easy to use interface for reporting their shared metrics, removing the need for any manual counting.

[![License: GPL v3](https://img.shields.io/badge/License-GPL%20v3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![Build Status](https://scrutinizer-ci.com/g/wikimedia/grantmetrics/badges/build.png?b=master)](https://scrutinizer-ci.com/g/wikimedia/grantmetrics/build-status/master)
[![Code Coverage](https://scrutinizer-ci.com/g/wikimedia/grantmetrics/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/wikimedia/grantmetrics/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/wikimedia/grantmetrics/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/wikimedia/grantmetrics/?branch=master)
[![Code Intelligence Status](https://scrutinizer-ci.com/g/wikimedia/grantmetrics/badges/code-intelligence.svg?b=master)](https://scrutinizer-ci.com/code-intelligence)

## Installation

After cloning the repository, run:

* `composer install`.
    * Use `grantmetrics` as the `database_name`.
    * `database.replica.is_wikimedia` tells the tests to go off of live replica data when making assertions, rather than the fixtures. Set this to `1` if you are working locally and are connected to the replicas.
    * Fill out your credentials accordingly.
* `php bin/console server:start` to start the server.
* You should be up and running at http://localhost:8000

## Usage

The web interface is hopefully straightforward to use. However developers can also do some functionality via the console. In the same directory as the application:

* `php bin/console app:process-event <eventId>` - will generate [`EventStat`](https://github.com/wikimedia/grantmetrics/blob/master/src/AppBundle/Model/EventStat.php)s for the Event with the ID `<eventId>`.
* `php bin/console app:spawn-jobs` - queries the [Job queue](https://github.com/wikimedia/grantmetrics/blob/master/src/AppBundle/Model/Job.php) and runs `app:process-event` for Events that are in the queue. There is a limit on the number of concurrent jobs to ensure the database quota on the replicas is not exceeded.

## PHP and framework

There is one internal [Symfony bundle](https://symfony.com/doc/current/bundles.html), called `AppBundle`. It contains a separate directory for the controllers, models, respositories, Twig helpers, and fixtures.

Models are [Doctrine ORM entities](http://docs.doctrine-project.org/projects/doctrine-orm/en/latest/reference/working-with-objects.html) that directly correlate to tables in the `grantmetrics` database. Database interaction should generally be done with Doctrine's `EntityManager`.

Repositories are responsible for querying the replicas, MediaWiki API, file system, etc., wherever external data lives. They do not do any post-processing. Repositories should automatically be assigned to the models, but you may need to set the DI container too. The process would look something like:

```php
$em = $this->container->get('doctrine')->getManager();
$organizerRepo = new OrganizerRepository($em);
$organizerRepo->setContainer($this->container);
```

## Assets

Local CSS and JavaScript live in [app/Resources/assets](https://github.com/wikimedia/grantmetrics/tree/master/app/Resources/assets). Fonts and vendor assets must be defined in [config.yml](https://github.com/wikimedia/grantmetrics/blob/master/app/config/config.yml#L44), and if needed, sourced in the `<head>` of [base.html.twig](https://github.com/wikimedia/grantmetrics/blob/master/app/Resources/views/base.html.twig).

Ultimately all compiled assets are copied to the web/ directory (publicly accessible). This should happen automatically, but if not try dumping the assets with `php bin/console assetic:dump`. If you find you have to keep doing this regularly, you can continually watch for changes with `php bin/console assetic:watch`.

When committing new asset changes, be sure to bump the version number in [config.yml](https://github.com/wikimedia/grantmetrics/blob/master/app/config/config.yml) under framework/assets/version. This will force the cache to be invalidated in production so that users download the newest assets.

## i18n

All messages live in the i18n/ directory.

For PHP, [Intuition](https://packagist.org/packages/krinkle/intuition) is used. Within the views, you can access a message using the `msg('message-key', ['arg1', 'arg2', ...])` function. Intuition is not available outside the views, but you probably don't need it in those cases anyway.

When working with model validations, you'll provide the message key and parameters that will in turn get passed into the view. For basic constraints, just put the key name. For instance `@UniqueEntity("title", message="error-program-title-dup")` for a duplicate program title. The name of the program is automatically passed in as the first parameter in the message. If you need to pass a variable, use the `payload` options, e.g. `@Assert\NotNull(message="error-invalid", payload={"0"="start-date"})`.

For [custom callbacks](https://symfony.com/doc/current/reference/constraints/Callback.html), use the validation builder and set the parameters accordingly. For instance, to validate that a program title is not reserved:

```php
if (in_array($this->title, ['edit', 'delete'])) {
    $context->buildViolation('error-program-title-reserved')
        ->setParameter(0, '<code>edit</code>, <code>delete</code>')
        ->atPath('title')
        ->addViolation();
}
```

In JavaScript, we use [jquery.i18n](https://github.com/wikimedia/jquery.i18n). The syntax is `$.i18n('message-key', 'arg1', 'arg2', ...)`.

## Tests

Use `composer test` to run the full test suite. The individual commands that it runs are as follows:

* `composer migrate-test` – Creates and migrates the test database.
* `composer lint` – Runs CodeSniffer that will test the PHP for linting errors.
* `composer docs` – Validates PHP block-level documentation. If [phpDocumentor](https://www.phpdoc.org/) is not already installed, it will automatically be downloaded into the root of the repo, and will be ingored via .gitignore.
* `composer unit` – Runs unit and integration tests with [PHPUnit](https://phpunit.de/).

The test database is automatically populated with the fixtures, which live in `src/DataFixtures/ORM`. This data, along with what is populated in [install-mediawiki.sh](https://github.com/wikimedia/grantmetrics/blob/master/build/ci/install-mediawiki.sh), are intended to mimic production data so that you can run the tests locally against the replicas and get the same results as the test MediaWiki installation that is used for the CI build. The [basic fixture set](https://github.com/wikimedia/grantmetrics/blob/master/src/AppBundle/DataFixtures/ORM/basic.yml) is loaded by default. The [extended set](https://github.com/wikimedia/grantmetrics/blob/master/src/AppBundle/DataFixtures/ORM/extended.yml) supplies a lot more test data, and is meant for testing beyond the workflow of creating events, etc., such as statistics generation.

Repository classes should not need tests. Add `@codeCoverageIgnore` to the bottom of the class summary so that coverage statistics are not affected.

### Functional/integration tests

Controller tests extend [`DatabaseAwareWebTestCase`](https://github.com/wikimedia/grantmetrics/blob/master/tests/AppBundle/Controller/DatabaseAwareWebTestCase.php), which loads fixtures and ensures full stack traces are shown when there is an HTTP error. Some class properties must be set for this to work:

* `$this->client` - the Symfony client.
* `$this->container` - the DI container.
* `$this->crawler` - the DOM crawler.
* `$this->response` - response of any requests you make.

See [`ProgramControllerTest`](https://github.com/wikimedia/grantmetrics/blob/master/tests/AppBundle/Controller/ProgramControllerTest.php) for an example.

## Deployment

The application currently is running on WMF's Toolforge environment at https://tools.wmflabs.org/grantmetrics

You'll need to run deploy cammands in the bash shell for the Kubernetes container:

* `webservice --backend=kubernetes php5.6 shell`
* `sh deploy.sh`
