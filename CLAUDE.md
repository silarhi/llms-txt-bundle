# CLAUDE.md

This file provides guidance for Claude Code when working on the LLMs.txt Bundle project.

## Project Overview

LLMs.txt Bundle is a Symfony bundle that builds, dumps and serves an [llms.txt](https://llmstxt.org) file, the way
PrestaSitemapBundle handles sitemaps: listeners of `LlmsTxtPopulateEvent` add the links, a controller serves the file
and a command dumps it.

## Tech Stack

- **Language**: PHP 8.2+
- **Framework**: Symfony 6.4 / 7.x / 8.x (`symfony/framework-bundle`)
- **Optional**: `symfony/console` (`llms-txt:dump`), `symfony/twig-bundle` (`llms_txt_url()`, `llms_txt_link()`),
  `symfony/web-link` (`discovery.link_header`)

## Repository Structure

```
src/
├── Command/            # DumpCommand (llms-txt:dump [target] [--base-url])
├── Controller/         # LlmsTxtController: serves the dumped file, or renders it whole into a php://temp buffer
│                       #   (Content-Length, ETag, 304) before responding, cached in a pool (cache.pool, cache.ttl)
├── DependencyInjection/ # WebLinkPass: discovery.link_header needs symfony/web-link and framework.web_link,
│                       #   DefaultUriPass: hands framework.router.default_uri to DefaultUriContext
├── Event/              # LlmsTxtPopulateEvent
├── EventListener/      # RouteOptionsListener (the "llms_txt" route option),
│                       #   DiscoveryLinkListener (Link header of HTML pages, through WebLink's "_links")
├── Exception/          # LlmsTxtExceptionInterface and implementations
├── Model/              # Document, Section (links or lazy iterables of links), Link
├── Routing/            # LlmsTxtEntry: the "llms_txt" route option as an object (fromArray() for YAML/XML routes),
│                       #   DefaultUriContext (URLs from framework.router.default_uri during a request)
├── Service/            # Generator (+ interface), MarkdownRenderer (+ RendererInterface), Dumper (+ interface),
│                       #   UrlGenerator (absolute URL of the file, for discovery)
├── Twig/               # LlmsTxtExtension (llms_txt_url, llms_txt_link)
└── LlmsTxtBundle.php   # Bundle class with configuration tree and service wiring
config/
└── routes.php          # GET|HEAD /llms.txt
tests/                  # PHPUnit tests mirroring src/, Functional/ (LlmsTxtTest, FlatMemoryTest),
                        #   Fixtures/ (TestKernel, PopulateSubscriber, PageController,
                        #   FailingStreamWrapper, templates/)
```

## Build & Test Commands

```bash
# Install dependencies
composer install

# Run tests
vendor/bin/phpunit

# Static analysis (level: max)
vendor/bin/phpstan analyse

# Code style check
vendor/bin/php-cs-fixer fix --dry-run --diff

# Code style fix
vendor/bin/php-cs-fixer fix

# Code modernization check
vendor/bin/rector process --dry-run

# composer.json validation + normalization check
composer validate --strict
composer normalize --dry-run

# composer.json normalization fix
composer normalize
```

## CI/CD

- Uses **Laminas CI Matrix Action** (`.github/workflows/continuous-integration.yml`)
- Configured extensions: `pcov`
- Ignores PHP platform requirements for PHP 8.4+ (future versions)
- Additional check (`.laminas-ci.json`): `composer validate --strict && composer normalize --dry-run --diff` on lowest PHP with latest dependencies
- The `main` ruleset requires the `CI passed` job; coverage is published to the `badges` branch
- Runs on: `ubuntu-latest`
- Documentation site (`.github/workflows/docs.yml`): the reusable `silarhi/docs-kit` workflow builds `docs/` when it
  changes (pull requests and `main`), with GitHub Pages permissions to deploy it from `main`
- pnpm is the package manager (root for the git hooks, `docs/` for the site, each with its own lockfile): its version is pinned by `packageManager` in `package.json`, and `pnpm-workspace.yaml` lists the dependencies allowed to run install scripts (`allowBuilds`; pnpm fails the install on a new unlisted one).

## Architecture Notes

- **Memory stays flat whatever the number of links.** `Section::addLinks()` keeps the iterable unread until rendering;
  `MarkdownRenderer::render()` checks the title eagerly, then yields the file in ~64 KiB chunks (PHP file streams do
  not buffer writes: one chunk per link meant one write per link). Never collect links or chunks into an array.
- **Never a truncated 200.** The controller renders the whole file into `php://temp` (spills to disk past 256 KiB)
  before creating the response, so a listener failing half way is an error page. It sends the buffer with an
  `fread()` loop, not `fpassthru()`, which maps the whole temporary file into the output buffers. A
  `StreamedResponse` runs its callback even on a 304: the controller swaps it for a no-op then.
- **Cached on the fly.** The controller keeps the rendered file `cache.ttl` seconds in `cache.pool`, keyed on the
  base URL (`default_uri`, or the scheme, host and base URL of the request), and answers the 304 from it. Past 1 MiB
  it is not cached: a hit would hold it whole in memory, against the flat memory rule. The cache callback returns
  null with `$save = false` then, and the controller streams the buffer it rendered.
- **Host of the URLs.** During a request, RouterListener fills the router's `RequestContext` from the request (Host,
  X-Forwarded-Host). `DefaultUriContext::apply()` swaps in the scheme, host, ports and base URL of
  `default_uri` and restores them afterwards. `DefaultUriPass` copies it from the first argument of
  `router.request_context`: FrameworkBundle 6.4.0 writes it there, later versions pass the
  `router.request_context.base_url` parameter. The
  controller wraps the whole rendering in it, so the listeners' URLs (generated while the chunks are read) follow
  too; `UrlGenerator` (discovery) as well. Not configured: empty, no host, or the recipe's `http://localhost`. No
  main request (commands, workers): no-op, so `llms-txt:dump --base-url` keeps working.
- **Dumper** writes the chunks to a temporary file next to the target and renames it: atomic, and the previous file
  stays when a listener fails.
- **Route options are cached.** `RouteOptionsListener` loads the route collection (every attribute, YAML or XML route
  file: the router does not keep it in production) only to build `%kernel.build_dir%/llms_txt_routes.php`, a
  `ConfigCache` tracking the collection's resources like the router's own cache. It is also an optional cache warmer.
  URLs are still generated per request (they depend on the request context). Without the cache factory and directory
  (the 2-argument constructor), or when the file cannot be written, it reads the collection as before.
- **Discovery** goes through WebLink: `DiscoveryLinkListener` (priority 8) adds a link to the request's `_links`, which
  WebLink's `AddLinkHeaderListener` (priority 0) serializes with the other links. Only successful HTML main requests
  (not XHR, Turbo Frames, redirects, errors, other formats).
- **Optional services** are registered with `class_exists()`, not `ContainerBuilder::willBeAvailable()`: the latter
  ignores packages that are dev dependencies of the root package, which they are in this bundle's own test suite.
- `MarkdownRenderer::inline()` skips its `/\s+/u` regex when there is nothing to collapse. PHP's `/u` enables PCRE's
  UCP mode: `\s` matches Unicode spaces too, hence the UTF-8 lead bytes in the fast-path check.

## Domain Exceptions

| Exception                     | Thrown when                                                                     |
| ----------------------------- | ------------------------------------------------------------------------------- |
| `MissingTitleException`       | The document has no title (`llms_txt.title` unset and no listener sets it)      |
| `InvalidRouteOptionException` | A route's `llms_txt` option is invalid, or set on a route that needs parameters |

All implement `LlmsTxtExceptionInterface`.

## Coding Conventions

- Strict types everywhere: `declare(strict_types=1)` in all PHP files
- PSR-4 autoloading under `Silarhi\LlmsTxtBundle\`
- Code style enforced by PHP-CS-Fixer (`.php-cs-fixer.dist.php`) — uses `@Symfony` and `@Symfony:risky` rulesets
- Static analysis enforced by PHPStan (`phpstan.neon`) at **level max**
- Code modernization managed by Rector (`rector.php`) — targets PHP 8.2+, includes deadCode, codeQuality, and
  typeDeclarations rulesets. `RenameClassRector` is skipped: Symfony 8.1 moves the bundle classes to
  `DependencyInjection\Kernel`, which 6.4 and 7.x do not have.

### Dependency Constraints

- **Integration libraries** (`symfony/*`) keep **wide version ranges** (`^6.4 || ^7.0 || ^8.0`) so the CI matrix
  genuinely tests from the lowest to the latest supported versions. Never bump their floors to the installed version.
- **QA tools** (php-cs-fixer, phpstan/\*, phpunit, rector, composer-normalize) are bumped via `composer bump:tools`.
- `config.bump-after-update` is left out on purpose: it bumps **all** dev dependencies after every `composer update`,
  integration libraries included — do not add it.

## API Documentation

**Always update documentation when adding or modifying public API**: the event, the models, the services and their
interfaces, configuration options, the route option, Twig functions, the controller route.

1. **`docs/`** — the documentation site (llms-txt-bundle.silarhi.dev): usage guides, configuration reference. Keep
   `README.md` short: update its features list or quick start only when they change.
2. **`CLAUDE.md`** — Repository Structure, Architecture Notes, Domain Exceptions.
3. **PHPDoc blocks** — accurate `@param`, `@return` and `@throws` on interfaces and services.
4. **`CHANGELOG.md`** — an entry in the top section, titled with the next release, never a bare `[Unreleased]`
   section: the next minor (e.g. `[1.3.0] - Unreleased`), or the next major for a BC break. Follow
   [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) sections (Added, Changed, Deprecated, Removed, Fixed,
   Security). When tagging the release, replace `Unreleased` with the release date and `HEAD` in its compare link with
   the tag. Prefix breaking changes with **BC break:**. Skip dependency bumps, CI and tooling changes.

## Releases

Lightweight tag `vX.Y.Z` on `origin/main` once CI is green, with a GitHub release whose notes follow the
`CHANGELOG.md` entry. Packagist updates from the GitHub hook.

## Common Patterns

- **Adding links**: a listener of `LlmsTxtPopulateEvent` calls `$event->getDocument()->addLinks($section, $generator)`;
  `addLink()` only for a handful of links.
- **Static pages**: `options: ['llms_txt' => new LlmsTxtEntry(title: …, description: …, section: …)]` on the route;
  the array form (`{title, description, section}`) for YAML/XML routes, both validated by `LlmsTxtEntry`.
- **Bundle configuration**: all options are defined in `LlmsTxtBundle::configure()` and wired in
  `LlmsTxtBundle::loadExtension()`.
