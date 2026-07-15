# Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| 0.1.x   | ✅        |
| < 0.1   | ❌        |

## Reporting a Vulnerability

If you discover a security vulnerability in php-proto-lint, please follow these steps:

1. **Do NOT** open a public GitHub issue
2. Use [GitHub Security Advisories](https://github.com/fangfengxiang/php-proto-lint/security/advisories/new) to report privately with:
   - A description of the vulnerability
   - Steps to reproduce
   - Potential impact
3. You will receive an acknowledgment within **48 hours**
4. A fix will be developed and released within **7 days** for critical issues

## Scope

- Vulnerabilities in `php-proto-lint` source code (`src/`)
- Vulnerabilities in the CLI entry point (`bin/php-proto-lint`)
- Misuse of `file_put_contents` in `inject-attributes` that could lead to file corruption

## Out of Scope

- Vulnerabilities in third-party dependencies (report upstream)
- Issues requiring existing filesystem write access (the tool operates on source files by design)

## Disclosure

We follow coordinated disclosure. Once a fix is released, we will publish a GitHub Security Advisory and credit the reporter (unless they prefer to remain anonymous).
