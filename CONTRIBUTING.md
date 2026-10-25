# Contributing to AI Grader Pro

Thanks for considering a contribution! AI Grader Pro is a small, opinionated
Moodle plugin and we try to keep contributions equally small and opinionated.
Read this once before opening a PR — it'll save you a round-trip.

## TL;DR for first-time contributors

1. Open an issue first if your change is non-trivial. We may already be
   working on it or have decided against it for reasons documented in
   prior pilot feedback.
2. Fork the repo and branch from `main`.
3. Match the **Moodle coding style** (`phpcs --standard=moodle`).
4. Add tests for behaviour you add or change (PHPUnit for logic,
   Behat for UI flows).
5. Update `lang/en/local_aigrader.php` for every user-visible string;
   we ship in 5 languages, so other lang files are mirrored on release.
6. Bump `version.php` and add a `CHANGELOG.md` entry **only if the
   maintainer asks you to** — version bumps are coordinated with
   release tags.
7. Open the PR against `main`. CI must pass.

## Reporting issues

Open an issue at
https://github.com/HernanDiaz/moodle-local_aigrader/issues/new/choose
and pick a template:

- **Bug report**: what you did, what you expected and what happened.
  Include the Moodle version, the plugin version (*Site administration
  → Plugins → Plugins overview*), the database, the AI provider and
  model, and the error banner or the debugging message, if any. Remove
  API keys and student data from logs and screenshots.
- **Feature request**: the teaching or admin problem it solves, not only
  the feature you have in mind.
- **Security problems**: do **not** open a public issue; follow
  [SECURITY.md](SECURITY.md).

## Proposing changes

1. For anything beyond a small fix, open an issue first so we can agree
   on the approach (see "Ground rules" below).
2. Fork the repository, create a branch from `main` and keep the change
   focused on one thing.
3. Add or update tests, run the checks below, and open a pull request
   against `main`. The pull request template lists what reviewers look
   for; CI runs on every pull request.

## Ground rules

### What we accept

- Bug fixes that come with a regression test.
- Documentation improvements (README, TESTPLAN, lang strings).
- Translations into new languages — see the "Translations" section.
- Compatibility fixes for new Moodle versions (e.g. Moodle 5.0).
- Small UX polish on `manage.php` / `review.php` that doesn't break
  the human-in-the-loop guarantee.
- New extractors (additional file formats) under
  `classes/extractor/`, with a unit test.
- New AI provider integrations through the **Moodle AI Subsystem**
  (we don't accept direct vendor SDK integrations — they go through
  `aiprovider_*` plugins, not this one).

### What we are unlikely to accept

- Auto-publish features. The plugin is built around the contract
  "the AI never writes a grade without an explicit teacher click."
  PRs that weaken this — silent publishing, optional auto-approval,
  default-on quick-publish — will be rejected on principle.
- Bypassing the audit log. Every grading action writes to
  `local_aigrader_grading_log`. PRs that skip this for performance
  or convenience reasons will be rejected.
- Vendor-specific code paths (e.g. "if provider is OpenAI, do X").
  Use the AI Subsystem abstraction.
- Direct SQL against `mod_assign` tables. Use the `\assign` API
  (`save_grade()`, `get_submission()`, etc.) so completion,
  notifications, and observer plugins keep working.
- New JS frameworks. The plugin uses native `<details>`, plain
  HTML forms, and a handful of inline `<script>` blocks. We won't
  add Vue/React/AMD complexity for cosmetic gains.
- New runtime dependencies. We vendor `smalot/pdfparser` already
  under `thirdparty/vendor/` (declared in `thirdpartylibs.xml`).
  Adding a new vendored dep needs a strong case.

### How to disagree with this list

Open an issue, make your case. Several of the items above have been
debated already; one or two may move with evidence from real pilots.

## Setting up a dev environment

Easiest path:

```bash
# Clone Moodle 4.5 LTS.
git clone --branch MOODLE_405_STABLE \
  https://github.com/moodle/moodle.git ~/moodle
cd ~/moodle

# Clone the plugin into local/aigrader/.
git clone https://github.com/HernanDiaz/moodle-local_aigrader.git \
  local/aigrader

# Standard Moodle install (Docker, native, or moodle-docker — your
# call). Once Moodle is up, browse to /admin to trigger the plugin
# install.
```

Then set up `moodle-plugin-ci` so you can run the same checks CI runs:

```bash
composer create-project -n --prefer-dist moodlehq/moodle-plugin-ci ci ^4
export PATH="$PWD/ci/bin:$PWD/ci/vendor/bin:$PATH"
cd ~/moodle
moodle-plugin-ci install --plugin ./local/aigrader --db-host=127.0.0.1
moodle-plugin-ci phpcs --max-warnings 0
moodle-plugin-ci phpunit --fail-on-warning
moodle-plugin-ci behat --profile chrome
```

Note: contributors don't need to test against a real OpenAI key. The
plugin's tests use a mock AI provider; you only need real credentials
for end-to-end smoke testing (see `TESTPLAN.md`).

### Running the tests locally

Without `moodle-plugin-ci`, from the Moodle root of a development site
with `$CFG->phpunit_prefix`, `$CFG->phpunit_dataroot` (and, for Behat,
`$CFG->behat_wwwroot`, `$CFG->behat_prefix`, `$CFG->behat_dataroot`) set
in `config.php`:

```bash
# PHPUnit: initialise once (and again after a version bump), then run the suite.
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite local_aigrader_testsuite

# One test file.
vendor/bin/phpunit local/aigrader/tests/output_parser_test.php

# Behat: initialise once, then run the plugin's scenarios (needs Selenium/Chrome).
php admin/tool/behat/cli/init.php
vendor/bin/behat --config <behat_dataroot>/behatrun/behat/behat.yml --tags=@local_aigrader

# Coding style, as in CI.
vendor/bin/phpcs --standard=moodle local/aigrader
```

The suite currently has 144 PHPUnit tests and 18 Behat scenarios; all of
them must pass. On Moodle 5.1 and later, paths are under `public/` (for
example `public/local/aigrader`).

## Coding style

- **PHP**: Moodle coding style, full stop. Run
  `moodle-plugin-ci phpcs --max-warnings 0` before pushing.
- **Mustache**: Run `moodle-plugin-ci mustache`.
- **JS**: Plain ES5/ES6 only, no transpilation. AMD modules only if
  you genuinely need require/define; otherwise inline `<script>`.
- **CSS**: One file (`styles.css`). Bootstrap 5 classes preferred,
  but we cannot rely on `gap-*` utilities (Moove theme drops them);
  see existing comments in `styles.css` for the convention.
- **Strings**: Every user-visible string in `lang/en/local_aigrader.php`.
  Never hardcode English in PHP. Even error messages.
- **Database**: All schema changes go through `db/install.xml` AND
  a matching `db/upgrade.php` block with a savepoint.
- **Capabilities**: New capabilities go in `db/access.php` with a
  matching `lang` entry (`local/aigrader:newcapability`).
- **Privacy**: Any new table that stores user data must be reflected
  in `classes/privacy/provider.php` AND in
  `tests/privacy/provider_test.php`.

## Testing requirements

PRs without tests for new behaviour are unlikely to be merged.

- **Unit tests** under `tests/` — extend `\advanced_testcase`,
  follow Moodle conventions, use `resetAfterTest(true)`.
- **Behat scenarios** under `tests/behat/` for UI changes. Tag
  with `@javascript` when JS is involved.
- We run **PHPUnit + Behat** in CI across PHP 8.1 to 8.4, Moodle 4.5
  to 5.3 (plus `main`, allowed to fail), PostgreSQL and MariaDB (matrix
  in `.github/workflows/moodle-ci.yml`). If your change is incompatible
  with one cell of the matrix, document it.

## Translations

We ship 5 lang packs: `en`, `es`, `pt_br`, `ca`, `fr`. PRs that add
a new lang pack are very welcome. To add one:

1. Copy `lang/en/local_aigrader.php` to `lang/<code>/local_aigrader.php`.
2. Translate every string. The English file is the source of truth.
3. Run `moodle-plugin-ci validate` — it checks for missing/extra keys
   per lang.
4. Open the PR; mention you're a native speaker (or who reviewed your
   translation) in the description.

Existing translations are kept in sync by the maintainers per release;
contributors don't need to update non-English packs for code changes.
We do a batch translation pass before each tagged release.

## Commit message style

Look at `git log --oneline -20` for the house style. In short:

- Title in present tense, max ~70 chars, lowercase ok ("fix manage
  page spacing — Moove drops Bootstrap gap-* utilities").
- Body wraps at ~72 chars. Explain *why*, not just *what*.
- Include the version tag when bumping (e.g. "(v1.0.16-beta)").
- Commits written with an AI assistant carry a `Co-Authored-By:` line
  naming it. Human-only commits don't need that line.

## Pull request checklist

Before opening the PR:

- [ ] CI passes locally (`phpcs`, `phpunit`, `behat`, `mustache`, `validate`).
- [ ] New strings added to `lang/en/local_aigrader.php` (other langs handled at release).
- [ ] New tests added (or rationale why not in the PR description).
- [ ] `README.md` / `CHANGELOG.md` updated **only if maintainer requests**.
- [ ] `version.php` **not** bumped (maintainer does this at release tag).
- [ ] If schema changed: `db/install.xml` + `db/upgrade.php` + savepoints + XMLDB validate.
- [ ] If new user-facing capability: `db/access.php` + lang string.
- [ ] If new table stores user data: privacy provider updated + privacy test updated.

## Release process (maintainer-only)

For reference, releases happen on tagged commits:

1. Maintainer bumps `$plugin->version` in `version.php` and updates `$plugin->release`.
2. `CHANGELOG.md` gets a new section.
3. `git tag -a v1.0.N-beta -m "v1.0.N-beta — short summary"`.
4. `git push --follow-tags origin main`.
5. CI runs, must be green.
6. The Moodle Marketplace ZIP is generated from the tag.

## Code of Conduct

This project follows the [Contributor Covenant 2.1](CODE_OF_CONDUCT.md).
In short: be kind, be concise, assume good faith. We're all working on a
small plugin that ends up touching real teachers' real grading
workloads. Reviews are about the code, not the contributor.

If something feels off in a review thread, ping the maintainer
privately (`hernan@aigraderpro.com`) — they will mediate.

## Questions?

- **General**: open a GitHub issue.
- **Bug reports**: GitHub issue with the bug-report template.
- **Feature ideas**: GitHub issue with the feature-request template.
- **Security**: see `SECURITY.md` (do **not** open public issues).
- **Privacy / EU AI Act compliance**: GitHub issue with the `privacy`
  label.
