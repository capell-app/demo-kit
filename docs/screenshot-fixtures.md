# Demo Kit screenshot fixtures

This page is for maintainers who capture Demo Kit screenshots. Editors never need it.

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
