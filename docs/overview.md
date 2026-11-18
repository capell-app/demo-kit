# Demo Kit

<!-- prettier-ignore-start -->

A developer or agency can fill a local or test copy of Capell with example sites, pages, media and two sample users. That gives them something to click through and try editing on before any real content exists.

On those copies, Demo Kit adds a System page for this, shown to people who manage packages. Before anything is made, the page shows how many sites and pages the chosen plan will create and waits for your confirmation. Generation then runs in the background, and each finished site gets Edit and View links.

Set a fixed number in the settings file and Demo Kit plans the same sites and pages every time. Sample images come from capell.app and all the text is invented. Replace both before launch. Demo Kit will not create the two sample users anywhere except those copies. One is a full administrator and the other an editor, and both use the public password "password". Reset clears the sites Demo Kit made and leaves your own alone.

Anyone who wants to try editing can sign in as the Demo Editor.

## What it does

Demo Kit installs example sites, pages, content, media, and supporting users so you can explore a site or theme before adding your own. It is intended for local builds, demos, package development, and repeatable test fixtures.

## Do I need to do anything?

Usually no. When you need sample data locally, an extension manager can open **System -> Demo Kit**, choose **Plan demo generation**, enter the site URL, and optionally select the sites and languages to populate. Review the plan, then choose **Queue reviewed generation** to confirm it. For a full reproducible multi-site fixture, an operator can use the `capell:demo-kit-full-demo` command with a chosen seed.

## Where it shows up

The Demo Kit extension page is available only in `local` and `testing` environments. Installed sample pages, content, and media then appear in the selected site and languages.

## Good to know

- The admin action is intentionally unavailable in production. The CLI commands also refuse production by default; `--allow-production` overrides that guard and is almost never appropriate because demo seeding creates sites, pages, media, and known-credential users.
- Use `--seed` when screenshots, tests, or a bug report need the same generated content again. The full command can scope the generated sites, languages, page count, theme, and package demos; `--quick` creates a smaller fixture.
- `--reset` deletes only named sites that carry Demo Kit provenance. It refuses to replace an unmarked existing site; use `--adopt-existing-site` only when you deliberately want to use that site without replacing it.
- The full console flow creates demo users unless you pass `--skip-demo-users`. Treat all generated credentials and content as non-production material.
- Run `capell:demo-kit-doctor` after a full install to check that the demo dependency, content, and public resources are healthy.
- Review and replace generated material before launch so sample content is not published to visitors.

After generation, the completion panel offers an edit action when permitted and a public homepage link only when the current domain and published page resolve for that site. If no public homepage is available, enable a domain and publish an accessible homepage before viewing it. Older results are checked against current site state.

---

For developers: see the [README](../README.md).

<!-- prettier-ignore-end -->
