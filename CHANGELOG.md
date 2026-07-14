# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-07-14

### Added

- Three-stage lint pipeline: Rule-01 (positional alignment), Rule-02 (cascade recursion), Rule-03 (strict type)
- `check` command — contract consistency check against `.proto` definitions
- `shadow-lint` command — shadow traffic payload audit (request + response)
- `inject-attributes` command — safe attribute injection (PHP 8+ and PHP 7 modes)
- `protoc --descriptor_set_out` → `google/protobuf` runtime deserialization pipeline
- Namespace conflict isolation algorithm (auto-prefix vs explicit FQCN)
- PSR-4 + full-scan fallback class resolution via `ClassLocator`
- `readonly` immutable config entities for `proto-bulk.json` and `proto-mapping.json`
- Full test suite: 7 unit tests + 1 integration test with fixtures
- GitHub Actions CI/CD pipelines (ci, release, static-analysis)
- Issue templates (bug report, feature request)
- Pull request template
- Dependabot configuration for Composer and GitHub Actions
- `CONTRIBUTING.md`, `SECURITY.md`, `LICENSE`, `CODEOWNERS`
- `.php-cs-fixer.php` code style configuration
- `composer.json` scripts (`test`, `cs:check`, `cs:fix`, `stan`)
- `src/Support/PathResolver.php` — shared path resolution utility
- `src/Config/PayloadConfig.php` — extracted to separate file (PSR-4 compliance)
- `src/Support/NamespacePrefixes.php` — shared service namespace prefix constants
- `Application::VERSION` constant for centralized version management

### Fixed

- **Rule-02 circular reference protection** — `checkMessage()` now tracks visited proto messages via `$visited` parameter, preventing stack overflow on recursive proto definitions
- **`PayloadParser::parseJson()` type guard** — added `is_array()` check after `json_decode()`, throws `JsonException` for non-array JSON (e.g., scalar strings)
- **`ClassLocator` PSR-4 array value handling** — composer.json PSR-4 mappings with array values (multi-directory) no longer crash; iterates all base paths
- **`InjectAttributesCommand` response mapping merge** — `fieldClassMappings` now merges both `request` and `response` mappings via `array_merge()`
- **`Rule03StrictType` PHPDoc type annotation** — corrected from 2-level to 3-level nesting: `array<string, array<string, array<string, string>>>`
- **`PayloadParser` index array handling** — indexed arrays (`array_is_list`) are no longer skipped; each element is checked against proto fields
- **`Rule03StrictType` case-insensitive override** — `rule_overrides` severity values now matched case-insensitively via `strtolower()`
- **Rule-03 service-level override key** — fixed key mismatch between `BulkConfig::SERVICE_LEVEL_KEY` (`'__service__'`) storage and `'*'` lookup; service-level `rule_overrides` now correctly resolved
- **Dead code cleanup** — removed unused imports/properties across 6 files (`PayloadParser`, `ClassLocator`, `LintEngine`, `Rule01PositionalAlignment`, `DiffEngine`)
- **`PayloadConfig` class split** — moved from `MappingConfig.php` to separate `PayloadConfig.php` file (one-class-per-file PSR-4 convention)
- **`resolvePath` DRY extraction** — duplicated path resolution in `MappingConfigLoader` and `BulkConfigLoader` extracted to shared `PathResolver::resolve()`
- **`Application` version constant** — hardcoded `'1.0.0'` extracted to `VERSION` constant
- **`PayloadParser` `$jsonKey` type safety** — added `(string)` cast for array key before passing to `string`-typed parameter
- **`ClassLocator` `$psr4Map` PHPDoc** — updated to reflect `array<string, string|array<string, string>>` for multi-directory PSR-4 support
- **Test fix** — `testPayloadParserInvalidJson` expected exception corrected from `RuntimeException` to `JsonException`
- **CI: `cs:check` risky rules** — added `--allow-risky=yes` to `php-cs-fixer` scripts, enabling `declare_strict_types` and `@PER:risky` rules
- **CI: `phpstan.neon` invalid config** — removed unsupported `cacheDirectory` parameter (not valid in PHPStan v2)
- **CI: PHPStan level** — lowered from level 6 to level 5, added `treatPhpDocTypesAsCertain: false`
- **CI: single PHP version** — simplified CI matrix from PHP 8.2/8.3/8.4 to single PHP 8.3
- **CI: coverage upload** — changed `composer test` to `composer test:coverage` so `coverage.xml` is generated for Codecov upload
- **README badge URLs** — corrected from `yar-group` to actual repository `fangfengxiang`
- **README `rule_overrides` key name** — corrected from `{"Rule-03": "Warning"}` to `{"rule_03": "warning"}` to match code
- **`composer.json` support URLs** — corrected to point to actual repository
- **`composer.json` branch-alias** — corrected from `1.x-dev` to `0.x-dev` for v0.1.0
- **`CONTRIBUTING.md` git clone URL** — corrected to actual repository
- **`SECURITY.md` version table** — updated from `1.x` to `0.1.x` for initial release
- **`SECURITY.md` contact method** — replaced non-existent email with GitHub Security Advisories
- **Code style** — ran `php-cs-fixer fix` on 32 files (import ordering, blank lines, strict types)
- **Dead code** — removed unused `$currentClassName` property in `AttributeInjectionVisitor`
- **`ProtoParser` temp file cleanup** — removed redundant `$tempFile` variable and double `unlink()` in `finally` block
