# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.1] - 2026-10-05

### Changed

- The file renders about 2.3 times faster, the memory unchanged (200,000 links: 615 ms to 266 ms on the fly, 588 ms to 262 ms dumped). The renderer yielded one chunk per link, and PHP file streams do not buffer writes: the controller buffer and the dumped file took one write and one hash update per line. Lines are now batched into chunks of about 64 KiB, and the controller sends its buffer by 64 KiB instead of 8 KiB.
- The whitespace of titles and descriptions only goes through the regex when there is something to collapse, Unicode spaces included.

## [1.1.0] - 2026-10-05

### Added

- On-the-fly responses carry a `Content-Length` and an `ETag`, and answer `304 Not Modified` to a matching `If-None-Match`. The dumped file answers `304` too.

### Fixed

- The controller rendered the file while sending it: a `LlmsTxtPopulateEvent` listener failing half way (a URL that cannot be generated, a lost database connection…) left a truncated `200` in the caches. The file is now rendered whole before the response starts, into a `php://temp` buffer that spills to a temporary file past 256 KiB: a failure becomes an error page, and the memory stays flat.

## [1.0.0] - 2026-10-05

### Added

- `LlmsTxtPopulateEvent`: its listeners add the links of the [llms.txt](https://llmstxt.org) file, the way `SitemapPopulateEvent` fills a sitemap.
- The `llms_txt` route option (`title`, `description`, `section`) adds static pages without a listener.
- `GET /llms.txt` (`@LlmsTxtBundle/config/routes.php`) serves the dumped file when there is one and builds it on the fly otherwise; `llms-txt:dump [target] [--base-url]` writes it atomically.
- `Document::addLinks()` takes an iterable read only while the file is rendered: with a generator, the memory stays flat whatever the number of links.
- Discovery: the `llms_txt_link()` and `llms_txt_url()` Twig functions, and an opt-in `Link` header on HTML pages through WebLink (`discovery.link_header`).

[1.1.1]: https://github.com/silarhi/llms-txt-bundle/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/silarhi/llms-txt-bundle/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/silarhi/llms-txt-bundle/releases/tag/v1.0.0
