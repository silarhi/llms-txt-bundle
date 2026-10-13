# Contributing

Contributions are welcome! Bug reports and feature requests go to the
[issue tracker](https://github.com/silarhi/llms-txt-bundle/issues), changes to a pull request against `main`.

## Setup

```bash
composer install
yarn install # Git hooks: lint-staged formats the staged files on commit
```

## Quality checks

The CI runs PHPUnit on every supported PHP version, with the lowest and the latest dependencies, and PHPStan, PHP CS
Fixer and the `composer.json` checks once. It does not run Rector: run it locally before opening a pull request.

```bash
# Tests
vendor/bin/phpunit

# Static analysis (level: max)
vendor/bin/phpstan analyse

# Code style (drop --dry-run --diff to fix)
vendor/bin/php-cs-fixer fix --dry-run --diff

# Code modernization (drop --dry-run to apply)
vendor/bin/rector process --dry-run

# composer.json
composer validate --strict
composer normalize --dry-run
```

## Documentation

The documentation site, [llms-txt-bundle.silarhi.dev](https://llms-txt-bundle.silarhi.dev), is built from the
[`docs/`](docs) folder with [Starlight](https://starlight.astro.build). Document a change in the same pull request:

```bash
cd docs
yarn install
yarn dev # http://localhost:4321
```

Pull requests touching `docs/` build the site; it is published once merged into `main`.

## License

By contributing, you agree that your contributions are released under the [MIT License](LICENSE).
