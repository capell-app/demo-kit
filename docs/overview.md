---
title: 'Demo Kit Overview'
description: 'How the Capell Demo Kit package creates repeatable local demo sites, languages, pages, media, and package demo content.'
---

# Demo Kit Overview

Demo Kit creates example Capell content for local demos, screenshots, package testing, and bug reports. It can build repeatable multi-site and multi-language demo plans, then call package-owned demo commands for installed packages.

Use it when a developer needs a populated Capell install quickly without committing demo fixtures into the host app.

## What It Adds

- Demo site, language, user, page, and media generation actions.
- Commands for admin-only demos, package demos, full demos, and demo health checks.
- Repeatable generation through a numeric seed.
- A package demo dispatcher that calls installed packages with the options they declare in their manifest.
- Health checks for validating generated demo installs.

## Commands

| Command                         | Purpose                                                                    |
| ------------------------------- | -------------------------------------------------------------------------- |
| `capell:admin-demo`             | Creates admin/core demo data such as users, sites, languages, and pages.   |
| `capell:demo`                   | Runs selected package demo commands for installed packages.                |
| `capell:demo-kit-full-demo`     | Builds a full demo plan, creates admin demo data, then runs package demos. |
| `capell:demo-kit-doctor --json` | Checks demo health and can return JSON for automation.                     |

In non-interactive environments, demo generation requires `--force`.

## Repeatable Demo Plans

The publishable config lives at `packages/demo-kit/config/capell-demo-kit.php`.

| Config                      | Default               | Purpose                                                              |
| --------------------------- | --------------------- | -------------------------------------------------------------------- |
| `seed`                      | `null`                | Leave null for random demos or set an integer for repeatable output. |
| `counts.sites`              | `3`                   | Default number of generated sites.                                   |
| `counts.languages_per_site` | `[1, 4]`              | Range for language generation.                                       |
| `counts.pages_per_site`     | `[12, 30]`            | Range for generated pages.                                           |
| `counts.page_depth`         | `[1, 4]`              | Range for generated page tree depth.                                 |
| `counts.media_per_page`     | `[0, 2]`              | Range for generated media attachments.                               |
| `archive.*`                 | demo archive metadata | Download source, checksum, and size guard for archived demo assets.  |

For screenshot runs or bug reproduction, pass a seed:

```bash
php artisan capell:demo-kit-full-demo --url=https://example.test --seed=1234 --force
```

## Package Demo Dispatch

`capell:demo` reads installed package metadata and calls each package's declared demo command. It only passes options the package says it accepts, such as `url`, `user`, `languages`, or `sites`.

That keeps Demo Kit generic: packages own their demo content, while Demo Kit owns the orchestration and common input prompts.

## Rendering Boundary

Demo Kit seeds CMS records, but it should not seed designed frontend markup into content columns. Keep page and element translations portable: simple paragraphs, headings, lists, links, and emphasis are acceptable because editors and themes can preserve them.

Put public presentation in Capell rendering surfaces instead:

- use Layout Builder elements for page regions;
- put designed markup and classes in package Blade files under `packages/demo-kit/resources/views`;
- store only the element key, component, `view_file`, and simple editable copy in the database;
- add a focused test when a demo layout switches from stored content to a Blade-backed element.

`DemoCreator` currently uses `demo-page-content` for designed demo pages and `homepage-section` for homepage-specific sections. Follow that pattern for future demos instead of adding heredoc HTML to `DemoCreator`.

## Maintenance Notes

Keep demo content pools in code when they need variety or generation logic. Use config for scale, archive safety, and repeatability settings that host apps may need to override.

Test generation plans through `BuildDemoGenerationPlanAction` first. Command tests should focus on option parsing, non-interactive guards, and package dispatch behaviour.
