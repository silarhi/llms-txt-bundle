---
title: Serving the file
description: Serve llms.txt on the fly or dump it with the llms-txt:dump command.
sidebar:
    order: 4
---

## On the fly

With the route imported, `GET /llms.txt` builds the file when it is not dumped, with a public `Cache-Control` of
`max_age` seconds and `X-Content-Type-Options: nosniff`. The file is rendered whole before the response starts, into a
`php://temp` buffer that spills to a temporary file past 256 KiB, so the memory stays flat and:

- a listener failing half way gives an error page, never a truncated `200` left in the caches
- the response carries a `Content-Length` and an `ETag`, and answers `304 Not Modified` to a matching `If-None-Match`

The built file is kept `cache.ttl` seconds (300 by default) in the `cache.pool` pool (`cache.app` by default), one
entry per base URL: the listeners run once per period, not on each request, a `304` or a `?random` query string
included. A file larger than 1 MiB stays out of the cache (a hit would hold it whole in memory) and is built on each
request: dump it instead. To see a change before the end of the period, clear the pool
(`php bin/console cache:pool:clear cache.app`) or set `cache.ttl: 0`.

## The host of the URLs

The absolute URLs of the file, those of the `llms_txt` route option and those your listeners generate with the router,
come from `framework.router.default_uri` when it is set, whatever the `Host` of the request:

```yaml
# config/packages/routing.yaml
framework:
    router:
        default_uri: '%env(DEFAULT_URI)%' # e.g. https://example.com
```

Without it (or with `http://localhost`, the placeholder of the Symfony recipe), they keep the host of the request, like
any URL Symfony generates. The file is publicly cached: a request with a forged `Host` header (or `X-Forwarded-Host`
behind a trusted proxy whose cache ignores it) would leave its host in the cached file. Set
[`framework.trusted_hosts`](https://symfony.com/doc/current/reference/configuration/framework.html#trusted-hosts)
then, to the hosts of the site.

## Dumped

```bash
php bin/console llms-txt:dump
php bin/console llms-txt:dump --base-url=https://example.com   # without "framework.router.default_uri"
php bin/console llms-txt:dump var/llms_txt                     # another directory
```

The file is streamed to a temporary file next to it, renamed once complete: the web server never serves a half written
file, and a failing listener leaves the previous one in place.

- **Into `public/`** (the default): the web server serves it without booting Symfony. The route is a fallback until the first dump: remember to dump again, from a cron or after a deployment, or the file goes stale.
- **Elsewhere** (`dump_directory: '%kernel.project_dir%/var/llms_txt'`): the controller serves the dumped file when there is one, with `ETag` and `Last-Modified`, and builds it on the fly otherwise.
