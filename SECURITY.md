# Security policy

`local_aigrader` (AI Grader Pro) is a Moodle local plugin that touches
grades, assignment submissions, AI provider credentials and an audit
log designed to satisfy EU AI Act high-risk-AI obligations. Security
reports are taken seriously.

## Supported versions

The plugin is in beta. Security fixes are only backported to the latest
released minor version. Older versions should be upgraded.

| Version       | Supported          |
|---------------|--------------------|
| `v1.0.x`      | ✅ (latest minor)  |
| earlier betas | ❌ (please upgrade)|

The plugin requires Moodle 4.5 LTS (build `2024100700`) or later. If a
vulnerability is found that only reproduces on an unsupported Moodle
version, please report it anyway so we can document it.

## Reporting a vulnerability

**Please do NOT open a public GitHub issue for security bugs.** Public
issues are visible to attackers before a fix can land on every pilot
instance.

Instead, email **`security@aigraderpro.com`** with:

1. A description of the vulnerability.
2. Affected version(s) — output of `local/aigrader/version.php`
   (`$plugin->release`, `$plugin->version`) is ideal.
3. Reproduction steps. A minimal failing case helps a lot.
4. Impact assessment from your side (data exposure? privilege
   escalation? auth bypass?).
5. Whether you'd like to be credited in the changelog when the fix
   ships (and under what name / handle).

We acknowledge reports **within 3 working days** and aim to ship a
patched release **within 14 days** for high-severity issues. We will
keep you in the loop while the fix is being prepared and before
disclosure.

If the vulnerability is in Moodle core's AI Subsystem, a bundled
third-party library (e.g. `smalot/pdfparser` under `thirdparty/vendor/`),
or another upstream — we will help coordinate disclosure with the
upstream maintainer.

## What we consider in scope

In scope for this repository:

- The plugin's PHP code (`classes/`, `lib.php`, `manage.php`,
  `review.php`, `bulk.php`, `retry.php`, `cli/enqueue.php`,
  `cli/grade.php`, `cli/validate-privacy.php`).
- The plugin's database schema (`db/install.xml`, `db/upgrade.php`).
- The plugin's templates (`templates/*.mustache`).
- The plugin's language packs (`lang/`).
- The privacy provider (`classes/privacy/provider.php`).
- The bundled third-party libraries (`thirdparty/vendor/`) when used
  the way the plugin uses them.

Out of scope (please report upstream instead):

- Vulnerabilities in Moodle core itself — report via
  <https://moodle.org/security>.
- Vulnerabilities in the LLM provider (OpenAI, Azure OpenAI, etc.) —
  report to the provider.
- Vulnerabilities in `mod_assign` or other Moodle-bundled plugins.
- Misconfigurations in a specific Moodle site (e.g. wide-open AI
  provider API keys) that are not caused by the plugin's defaults.

## Severity guidance

Roughly, we classify reports as:

- **Critical** — Remote unauthenticated code execution, SQL injection,
  AI provider credential exfiltration, write access to gradebook
  without teacher authorisation. Patch within 7 days, coordinated
  disclosure.
- **High** — Authenticated privilege escalation, audit log tampering,
  bypass of the human-in-the-loop guarantee (AI publishing without an
  explicit teacher click), exposure of another teacher's drafts.
  Patch within 14 days.
- **Medium** — Stored XSS in the review form, CSRF in non-destructive
  endpoints, leakage of LLM prompt or response between sessions.
  Patch within 30 days.
- **Low** — Information disclosure with low impact, missing CSP
  headers, etc. Patched in the next regular release.

## Cryptographic / secrets handling

The plugin does not store LLM provider API keys itself — those live in
Moodle's AI Subsystem (`aiprovider_openai` and siblings). The plugin
only reads the configured provider via the AI Subsystem API. If you
discover a code path where the plugin reads, logs, persists or emits a
provider API key, please report it as **Critical**.

The audit log (`local_aigrader_log`) stores, for every AI call, the
full prompt sent to the LLM (which includes the student's submission
text) next to its SHA-256 hash, so that a grading decision can be
reviewed later. This is declared in the privacy provider. On a GDPR
deletion request for a student, that student's proposals and audit
entries (prompt included) are deleted; a teacher's request anonymises
the teacher in the entries. Course backups include the audit log only
when user data is included. If you find a path where the prompt or the student's
text leaves Moodle other than through the AI Subsystem call itself, or
survives a GDPR deletion, that's a privacy bug — please report it.

## Privacy issues

For privacy-only issues (e.g. the privacy provider missing a field,
GDPR export incomplete, AI-Act audit log incomplete), you can either
email `security@aigraderpro.com` or open a regular GitHub issue with
the `privacy` label — those don't need confidential disclosure.

## Credits

Researchers who responsibly disclose are credited in
[`CHANGELOG.md`](CHANGELOG.md) under the release that contains the
fix, with the attribution they choose at report time.
