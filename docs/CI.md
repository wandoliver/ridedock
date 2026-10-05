# CI and quality checks

The [CI workflow](../.github/workflows/ci.yml) runs on pushes to `main`, pull requests targeting `main`, and manual dispatches. Open [GitHub Actions](https://github.com/wandoliver/ridedock/actions/workflows/ci.yml) to inspect a run, its jobs and step logs. Pull requests also expose the results under Checks. The README badge links to the same workflow.

## Jobs

| Job | Runtime | Checks |
| --- | --- | --- |
| Backend tests | PHP 8.3 and 8.4, Node 24 | `composer test`, with SQLite in memory and mocked Vite assets. |
| Code quality | PHP 8.4, Node 24 | Composer manifest/lockfile validation, EditorConfig, ESLint, PHP_CodeSniffer, PHP-CS-Fixer in dry-run mode and PHPStan/Larastan. |
| Frontend tests and build | Node 24 | JavaScript tests and a Vite production build. |
| Fresh installation | PHP 8.4, Node 24 | Production asset build; fresh SQLite migrations; configuration, route and Blade-view caches; HTTP 200 login page rendering with production asset references. |

The backend matrix runs both PHP versions independently, so one failure does not cancel the other. Jobs have bounded timeouts. A newer run of the same branch or pull request cancels its obsolete predecessor.

Each run installs Composer and npm dependencies from their committed lockfiles. Only dependency downloads are cached; databases, Laravel caches and application build output are recreated. Actions are pinned to full commit IDs. The workflow has `contents: read` permission and checkout does not persist Git credentials. No provider or AI secrets are required.

Composer resolves dependencies against PHP 8.3.0 (`config.platform.php`), so updating the lockfile on a newer development runtime preserves the documented minimum. Symfony components use the compatible 7.4 series. The backend matrix still executes the tests on both real PHP runtimes.

The default Laravel test case blocks unmocked Laravel HTTP-client requests. Use explicit HTTP fakes or mocked training/provider contracts in tests. Backend tests replace Vite rendering; the frontend and installation jobs validate the actual build separately. Use synthetic activity data, never personal FIT files, tokens or routes.

## Local checks

Install the dependencies and run the complete equivalent of the CI checks:

```bash
composer install
npm ci --ignore-scripts
composer check
```

`composer check` runs backend tests, code quality, JavaScript tests, the production build and installation verification, using your current local PHP and Node versions. GitHub additionally tests PHP 8.3 and 8.4, with Node 24. Supported Node versions are 22.13+ within Node 22, or Node 24+; Node 24 is recommended.

Individual commands:

```bash
composer test
composer check:quality
npm run test:js
npm run build
composer check:install
```

Use `composer cs-fixer` to apply PHP formatting, then repeat `composer cs-fixer:check` to check it without modifying files. CI never applies formatting changes automatically.

`composer check:install` requires built assets. It creates a temporary environment, random application key, empty SQLite database, compiled views and Laravel cache paths. Every child process uses that temporary environment rather than the checkout's `.env`; the local development hot file is ignored. Migrations, caching and login rendering run against the temporary installation, and the temporary files are removed afterward, including after a failure. Your development database and Laravel caches are not used by this command.

## Interpreting results

A failed step marks its job and the workflow as failed. Open the failing step's log to find the exact test or command. Successful runs establish the checks above; they do not validate real provider access, other database engines, visual layout or an actual deployment.

Branch protection is a separate repository setting. After the workflow has run, its job names can be selected as required status checks for merges. The workflow itself does not change repository merge or push permissions.
