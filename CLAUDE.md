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
├── Attribute/          # AsLlmsTxtLinkProvider (class or method, repeatable): adds the llms_txt.link_provider tag
├── Command/            # DumpCommand (llms-txt:dump [target] [--base-url]: llms.txt, and llms-full.txt when enabled)
├── Controller/         # LlmsTxtController (two services: llms.txt, llms-full.txt): serves the dumped file, or renders it whole into a php://temp buffer
│                       #   (Content-Length, ETag, 304) before responding
├── DependencyInjection/ # WebLinkPass: discovery.link_header needs symfony/web-link and framework.web_link,
│                       #   LinkProviderPass: one LinkProviderListener per llms_txt.link_provider tag,
│                       #   MarkdownEscapingPass: MarkdownEscapingStrategy as Twig's autoescape, with markdown_versions
├── Event/              # LlmsTxtPopulateEvent
├── EventListener/      # RouteOptionsListener (the "llms_txt" route option), LinkProviderListener (one link provider),
│                       #   MarkdownVersionListener (md format, Accept negotiation, Vary, rel="alternate" of the
│                       #   Markdown routes),
│                       #   DiscoveryLinkListener (Link header of HTML pages, through WebLink's "_links")
├── Exception/          # LlmsTxtExceptionInterface and implementations
├── Model/              # Document, Section (links or lazy iterables of links), Link (+ content for llms-full.txt)
├── Routing/            # LlmsTxtEntry: the "llms_txt" route option as an object (fromArray() for YAML/XML routes),
│                       #   MarkdownRoutes (@internal: the routes whose "_format" requirement lists "md", cached)
├── Service/            # Generator (+ interface), MarkdownRenderer, FullMarkdownRenderer (+ RendererInterface),
│                       #   Markdown (@internal: inline text, escaping, section order, chunks), Dumper (+ interface),
│                       #   UrlGenerator (absolute URL of the file, for discovery)
├── Twig/               # LlmsTxtExtension (llms_txt_url, llms_txt_link), MarkdownEscapingStrategy (no escaping in *.md.twig)
└── LlmsTxtBundle.php   # Bundle class with configuration tree and service wiring
config/
└── routes.php          # GET|HEAD /llms.txt, /llms-full.txt (404 unless full.enabled)
tests/                  # PHPUnit tests mirroring src/, Functional/ (LlmsTxtTest, FlatMemoryTest),
                        #   Fixtures/ (TestKernel, PopulateSubscriber, CatalogLinks, PageController (an HTML and Markdown action),
                        #   FailingStreamWrapper, FailingPhpStreamWrapper, templates/)
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
- **No application cache, no lock.** Built on the fly, the file runs every listener on each request: production sites
  dump it from a cron (`llms-txt:dump`), which the docs push. URLs come from the request context: the bundle relies on
  `framework.trusted_hosts` against forged `Host` headers, it does not override the host.
- **Dumper** writes the chunks to a temporary file next to the target and renames it: atomic, and the previous file
  stays when a listener fails.
- **Route options are cached.** `RouteOptionsListener` loads the route collection (every attribute, YAML or XML route
  file: the router does not keep it in production) only to build `%kernel.build_dir%/llms_txt_routes.php`, a
  `ConfigCache` tracking the collection's resources like the router's own cache. It is also an optional cache warmer.
  URLs are still generated per request (they depend on the request context). Without the cache factory and directory
  (the 2-argument constructor), or when the file cannot be written, it reads the collection as before.
- **Link providers are event listeners.** `#[AsLlmsTxtLinkProvider]` (autoconfigured in `loadExtension()`, the
  reflector typed `ReflectionClass|ReflectionMethod` since Symfony reads the targets from it) only adds the
  `llms_txt.link_provider` tag `{section, method, priority}`. `LinkProviderPass` (before optimization, after the
  autoconfiguration, before `RegisterListenersPass`) validates each tag and registers a `LinkProviderListener` tagged
  `kernel.event_listener` with the provider's priority: providers and hand-written listeners share one order. The
  provider is a `Closure::fromCallable()` definition; the listener checks the result is iterable, not each link.
- **llms-full.txt** reuses everything: the same document and listeners, `FullMarkdownRenderer` instead of
  `MarkdownRenderer`, a second controller and dumper with `FULL_FILENAME`. `Link::$content` (a string, or a closure
  returning a string or `iterable<string>`) is only read by the full renderer: the closure spares `llms.txt` building
  the pages. Its route is always in `routes.php`; the controller answers 404 while `full.enabled` is false.
- **Markdown versions are a route requirement.** With `markdown_versions` (off by default), a route whose `_format`
  requirement names `md` among its `|` alternatives (`MarkdownRoutes::accepts()`: a pattern merely matching `md`, such
  as `[a-z]+`, does not opt in) has a Markdown version: at its `.md` URL with `{_format}` in the path, Accept-only
  without (the router ignores a requirement on a variable not in the path). `MarkdownRoutes` caches the names in
  `%kernel.build_dir%/llms_txt_markdown_routes.php` (a `ConfigCache` on the collection resources, also a cache
  warmer). `MarkdownVersionListener` (`kernel.request` 16, after the router) registers the `md` format on the request
  (static, unless the app maps it), switches `_format` to `md` when `Accept` lists a Markdown type before an HTML one
  (wildcards ignored); on the response, `Vary: Accept` and the WebLink `alternate` link on HTML main requests (the
  `.md` URL, or the page URL when `_format` lands in the query string). `MarkdownEscapingPass` swaps Twig's `autoescape`
  `'name'` (TwigBundle's value without `autoescape_service`) for `[MarkdownEscapingStrategy::class, 'guess']`: an array,
  since the compiled container cannot dump a closure and Twig ignores string callables (Rector's
  `ArrayToFirstClassCallableRector` is skipped there). `LlmsTxtEntry::$markdown` `null` follows the requirement:
  the route options cache stores the option (5th element) and whether the route has a `.md` URL (6th), the toggle is
  applied in `populate()` since the cache does not follow the configuration; a 1.4 cache (4 elements) still works.
- **Discovery** goes through WebLink: `DiscoveryLinkListener` (priority 8) adds a link to the request's `_links`, which
  WebLink's `AddLinkHeaderListener` (priority 0) serializes with the other links. Only successful HTML main requests
  (not XHR, Turbo Frames, redirects, errors, other formats).
- **Optional services** are registered with `class_exists()`, not `ContainerBuilder::willBeAvailable()`: the latter
  ignores packages that are dev dependencies of the root package, which they are in this bundle's own test suite.
- `MarkdownRenderer::inline()` skips its `/\s+/u` regex when there is nothing to collapse. PHP's `/u` enables PCRE's
  UCP mode: `\s` matches Unicode spaces too, hence the UTF-8 lead bytes in the fast-path check.

## Domain Exceptions

| Exception                      | Thrown when                                                                     |
| ------------------------------ | ------------------------------------------------------------------------------- |
| `MissingTitleException`        | The document has no title (`llms_txt.title` unset and no listener sets it)      |
| `InvalidRouteOptionException`  | A route's `llms_txt` option is invalid, or set on a route that needs parameters |
| `InvalidLinkProviderException` | A `llms_txt.link_provider` tag is invalid, or its provider returns no iterable  |

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

- **Adding links**: `#[AsLlmsTxtLinkProvider(section: …)]` on a method returning `iterable<Link>` (a generator for
  large sections); a `LlmsTxtPopulateEvent` listener to change the document itself, which calls
  `$event->getDocument()->addLinks($section, $generator)`, `addLink()` only for a handful of links.
- **Static pages**: `options: ['llms_txt' => new LlmsTxtEntry(title: …, description: …, section: …)]` on the route;
  the array form (`{title, description, section}`) for YAML/XML routes, both validated by `LlmsTxtEntry`.
- **Bundle configuration**: all options are defined in `LlmsTxtBundle::configure()` and wired in
  `LlmsTxtBundle::loadExtension()`.
