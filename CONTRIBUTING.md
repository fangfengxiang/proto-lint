# Contributing to proto-lint

Thanks for your interest in contributing! This guide covers the essentials.

## Quick Start

```bash
git clone https://github.com/fangfengxiang/proto-lint.git
cd proto-lint
composer install
```

### Prerequisites

- **PHP >= 8.2** with extensions: `mbstring`, `xml`, `json`
- **protoc** compiler — macOS: `brew install protobuf`, Ubuntu: `apt install protobuf-compiler`
- **Composer** 2.x

## Development Workflow

### Run Tests

```bash
composer test          # Run all tests
composer test:unit     # Unit tests only
composer test:integration  # Integration tests only
```

### Code Style

This project follows [PER 2.0](https://www.php-fig.org/per/coding-style/) via `friendsofphp/php-cs-fixer`:

```bash
composer cs:check     # Check for style violations
composer cs:fix       # Auto-fix violations
```

### Static Analysis

```bash
composer stan         # Run PHPStan at level 5
```

### Pre-Commit Checklist

Before submitting a PR, ensure all pass:

```bash
composer cs:check
composer stan
composer test
```

## Branch & Commit Convention

- **Branch naming:** `feature/<short-description>`, `fix/<issue-number>-<description>`, `chore/<description>`
- **Commit message format:** Conventional Commits

```
feat: add support for proto3 optional fields
fix: correct tag number alignment for nested messages
docs: update README with PHP 7 mode examples
refactor: extract namespace prefix resolution to shared class
test: add integration test for shadow-lint response audit
chore: bump nikic/php-parser to 5.1
```

## Pull Request Process

1. Fork the repository and create a branch from `develop`
2. Write tests for your change (TDD encouraged)
3. Ensure `composer cs:check`, `composer stan`, and `composer test` all pass
4. Update `CHANGELOG.md` under the `[Unreleased]` section
5. Open a PR targeting `develop` (or `main` for hotfixes)
6. Ensure the PR template checklist is complete

## Release Process

Maintainers follow these steps for releases:

1. Merge `develop` into `main`
2. Update `CHANGELOG.md` with the release date
3. Tag with `vX.Y.Z` (follow [SemVer](https://semver.org/))
4. Push tag — the `Release` GitHub Action auto-creates the GitHub Release

## Reporting Security Issues

Please **do not** open a public issue for security vulnerabilities. See [SECURITY.md](SECURITY.md) for the disclosure process.
