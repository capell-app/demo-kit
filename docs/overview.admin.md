## What it does

Demo Kit installs example sites, pages, content, media, and supporting users so you can explore a site or theme before adding your own. It is intended for local builds, demos, package development, and repeatable test fixtures.

## Do I need to do anything?

Usually no. When you need sample data locally, an extension manager can open **System -> Demo Kit**, choose **Insert Example Site Data**, enter the site URL, and optionally select the sites and languages to populate. The action runs the package's registered demo command with those choices. For a full reproducible multi-site fixture, an operator can use the `capell:demo-kit-full-demo` command with a chosen seed.

## Where it shows up

The Demo Kit extension page is available only in `local` and `testing` environments. Installed sample pages, content, and media then appear in the selected site and languages.

## Good to know

- The admin action is intentionally unavailable in production. The CLI commands also refuse production by default; `--allow-production` overrides that guard and is almost never appropriate because demo seeding creates sites, pages, media, and known-credential users.
- Use `--seed` when screenshots, tests, or a bug report need the same generated content again. The full command can scope the generated sites, languages, page count, theme, and package demos; `--quick` creates a smaller fixture.
- `--reset` deletes only named sites that carry Demo Kit provenance. It refuses to replace an unmarked existing site; use `--adopt-existing-site` only when you deliberately want to use that site without replacing it.
- The full console flow creates demo users unless you pass `--skip-demo-users`. Treat all generated credentials and content as non-production material.
- Run `capell:demo-kit-doctor` after a full install to check that the demo dependency, content, and public resources are healthy.
- Review and replace generated material before launch so sample content is not published to visitors.

## Screenshot fixture states

The screenshot runner uses the guarded `capell:demo-kit-screenshot-fixture`
command for state-specific captures. It supplies a unique `--attempt-token`, a
state such as `queued` or `completed`, and `--force`; the command accepts these
operations only in the explicit local/testing screenshot environment identified
by `CAPELL_SCREENSHOT_APP_PATH` and `CAPELL_SCREENSHOT_FIXTURE`. The package
screenshot configuration sets those values for the disposable Testbench
application; keep them scoped to that workbench and never copy them into a
shared application environment. Restore with the same state and token plus
`--restore`. Ownership markers make preparation idempotent and restoration
removes only rows created by that attempt. Do not run this command against a
shared or production database.
