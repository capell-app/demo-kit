# Changelog

All notable changes to `capell-app/demo-kit` will be documented in this file.

## Unreleased

- Prepared package metadata and documentation for ongoing Capell 4.x package work.

## 2026-06-03

### Security

- Demo seeding commands (`capell:demo-kit-full-demo`, `capell:admin-demo`, `capell:demo`) now refuse to run in the `production` environment unless an explicit `--allow-production` flag is supplied.
- `CreateDemoUsersAction` now hard-refuses to mint the known-credential demo users (including the `demo@example.com` super-admin) outside the `local` and `testing` environments, regardless of any override.

### Fixed

- `capell:demo-kit-full-demo` now forwards the selected `--user` author to the package demo fan-out (`capell:demo`) as a value instead of a boolean flag, so package-contributed demo content is attributed to the chosen author.
- Removed a duplicated `--page-count` option parse in `capell:demo-kit-full-demo`.

### Changed

- Rewrote the marketplace summary and Composer/manifest descriptions to describe Demo Kit's deterministic, multi-site, multi-language orchestration and per-package fan-out.
- Reworded the manifest health-check label to describe the Capell API-version compatibility contract it actually enforces.
