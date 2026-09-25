# AI Grader Pro (`local_aigrader`)

AI-assisted grading for Moodle 4.5+ assignments, with the teacher always in
the loop. The plugin proposes a grade and structured feedback using a Large
Language Model accessed through Moodle's AI Subsystem; the teacher reviews,
edits if needed, and decides whether to publish. Nothing reaches the
gradebook without an explicit teacher click.

[![CI](https://github.com/HernanDiaz/moodle-local_aigrader/actions/workflows/moodle-ci.yml/badge.svg)](https://github.com/HernanDiaz/moodle-local_aigrader/actions/workflows/moodle-ci.yml)
[![Tests](https://img.shields.io/badge/PHPUnit-140%20tests%20passing-brightgreen)](#tests)
[![Code style](https://img.shields.io/badge/phpcs-0%20errors-brightgreen)](#code-quality)
[![Languages](https://img.shields.io/badge/i18n-5%20languages-blue)](#features)
[![License](https://img.shields.io/badge/license-GPL--3.0--or--later-blue.svg)](LICENSE)

**Watch the 2-minute video demo** (English, with Spanish subtitles):

<a href="https://youtu.be/5whb8pkGnfM"><img src="docs/media/aigrader_video.jpg" alt="AI Grader Pro video demo on YouTube" width="480"></a>

---

## What it does

- Teachers write evaluation criteria in plain language on the assignment
  edit page ("Eres profesor de la microcredencial de IA. Evalúa esta
  práctica final…"). No rigid rubric grid required — the LLM is good at
  reading nuanced prose.
- When the teacher clicks **Grade with AI** (for one or many
  submissions), or as soon as a student submits if the teacher turned
  on automatic grading, the plugin extracts text from the submission
  (`.txt`, `.md`, `.docx`, `.pptx`, `.odt`, `.odp`, `.ipynb`, `.zip`,
  `.pdf` up to 5 MB, plus source-code files in 20+ languages), replaces
  the student's name with `[STUDENT]`, builds a prompt combining the
  teacher's criteria with the extracted content, and calls the
  configured LLM provider via Moodle's AI Subsystem.
- The proposal — a grade, per-criterion scores, strengths, areas for
  improvement, and a narrative justification — lands in the teacher's
  panel.
- The teacher reviews the proposal, modifies anything they want, and
  either **Approves & publishes** (grade + feedback go to the gradebook,
  the student sees them, the `mod_assign\event\submission_graded` event
  fires for completion / notifications / observers), or **Rejects**
  (grades manually in Moodle's standard UI).
- Every action is recorded in an append-only audit log with the
  teacher's id, the prompt hash, the model used, token counts and
  edits — designed to satisfy the EU AI Act's audit requirements for
  high-risk educational AI systems.

### Human-in-the-loop guarantee

By design, the AI never writes a grade to the gradebook directly. The
plugin stores its proposal in its own table (`local_aigrader_submission`)
with status `ai_proposed`; the row only becomes `published` after the
teacher clicks **Aprobar y publicar**. The `final_grader` column always
contains the teacher's `user.id`, never a system id.

## Features

- 🧠 **Multi-LLM** via Moodle's AI Subsystem — OpenAI, Azure OpenAI,
  Groq, any provider that implements the AI provider contract.
- 📄 **File-format coverage**: online text, plain text, Markdown, Word
  (`.docx`), PowerPoint (`.pptx`, with speaker notes), OpenDocument text
  and presentations (`.odt`, `.odp`), Jupyter notebooks (`.ipynb`), PDF
  (text-based, up to 5 MB), ZIP archives (including the office
  documents inside them), and 20+ source-code languages.
- 📈 **Class report**: per-assignment statistics (average, median,
  distribution, weakest criteria) and an AI-written executive summary
  of the class — common gaps and how often they appear, what the class
  did well, topics to reinforce and next steps. The AI receives only
  the anonymised grades and feedback, never the submissions.
- ⚡ **Automatic grading on submission** (per assignment, off by
  default): the proposal is waiting when the teacher opens the panel.
  Proposals the teacher already reviewed or published are never
  replaced.
- 📋 **Copy criteria from another assignment** in the same course or
  in another course the teacher teaches.
- 🙈 **Student names kept out of the AI**: name, email, username and ID
  number are replaced with `[STUDENT]` before anything is sent to the
  provider, also in file names (site setting, on by default).
- 🗜️ **Notebook truncation**: Jupyter outputs longer than 30 lines /
  1500 chars per cell get head+tail truncation with a marker. A Fashion-
  MNIST notebook with 50 epochs × 1875 batches still fits comfortably
  in a 30 K-TPM LLM budget.
- 🛡️ **Manual-review fallback**: submissions whose attached files are
  entirely in unsupported formats (e.g. only a PDF that is too large or
  image-only) are flagged as `needs_manual_review` with a clear banner.
  No fake 0/10 grades are produced.
- 🚦 **Classified error banner**: when an LLM call fails (rate limit,
  payload too large, auth failure, network error, parse error), the
  teacher sees a localised banner with the cause, a suggested action,
  and a per-student "Retry now" button. No log-diving required.
- 📋 **Bulk actions**: select N rows + "With selected…" dropdown to
  publish or re-grade many submissions in one click. Hybrid sync/async
  execution: ≤5 rows run inline, larger batches queue as adhoc tasks
  so the request returns immediately.
- 📊 **Paginated + sortable manage page**: server-side `\table_sql`
  with 10 / 25 / 50 / 100 / All per-page options, sortable columns,
  and a counter banner of clickable status chips that filter the
  view (ai_proposed / teacher_reviewed / published / problems / none).
- 🌐 **i18n**: ships with English, Spanish, Brazilian Portuguese,
  Catalan and French — all 194 strings, full key parity. Other
  languages welcome via PR or via AMOS once on the Plugin Directory.
- 🎯 **Grades on the assignment's own scale**: the AI reasons on 0-10,
  the teacher sees and publishes "80 / 100" on an assignment out of
  100, or the scale item on an assignment graded with a scale.
  Assignments with a rubric or marking guide are graded in Moodle's
  grader (the AI proposal stays as guidance).
- 🏫 **Choose where it is available**: the site administrator can limit
  the plugin to some course categories or courses.
- 💾 **Backup and restore**: the configuration travels with backups,
  imports and duplications; with user data, so do the proposals,
  teacher decisions and audit log.
- 🛡️ **Privacy provider** implementing GDPR Art. 15 (data export),
  Art. 17 (deletion) and the AI Act Annex III audit trail.
- ✅ **Tested**: 140 PHPUnit tests + 18 Behat scenarios, on Moodle 4.5,
  5.0, 5.1 and 5.2 in CI.

## Requirements

- Moodle **4.5 LTS** or later (`$plugin->requires = 2024100700`);
  tested on 4.5, 5.0, 5.1 and 5.2.
- The PHP version your Moodle requires (8.1+ on 4.5, 8.3+ on 5.2).
- An LLM provider configured through *Site administration → AI →
  Providers*. The plugin uses the `generate_text` action of the AI
  Subsystem so any provider exposing it works (OpenAI, Azure OpenAI,
  Groq via the OpenAI-compatible endpoint, etc.).
- ~~Composer~~ — not required at runtime. All third-party libraries
  used by the plugin (`smalot/pdfparser`, `symfony/polyfill-mbstring`)
  are vendored under `thirdparty/vendor/` with their license files. See
  [`thirdpartylibs.xml`](thirdpartylibs.xml).

## Installation

### From the Moodle Marketplace (recommended)

1. Download the ZIP of "AI Grader Pro" from the Moodle Marketplace.
2. Site administration → Plugins → Install plugins → upload the ZIP.
3. Confirm the upgrade prompt.

### Manual

```bash
cd /path/to/moodle/local
git clone https://github.com/HernanDiaz/moodle-local_aigrader.git aigrader
# or unzip the release ZIP into local/aigrader/
```

Then visit `/admin/index.php` as a site administrator to apply the
upgrade.

## Configuration

### 1. Pick an LLM provider

Site administration → AI → Providers → enable a provider, paste the API
key, set the default model. The plugin uses whatever the AI Subsystem
returns; no provider lock-in.

### 2. Choose where it is available (optional)

Site administration → Plugins → Local plugins → AI Grader Pro →
**Where AI Grader Pro is available**: pick course categories
(subcategories included) and/or list course short names. Leave both
empty to make it available in every course.

### 3. Per-assignment setup

Open any assignment → edit settings → expand **AI Grader Pro**:

- Tick **Enable AI-assisted grading for this assignment**.
- Write your **Evaluation criteria** in plain prose, or use **Copy
  criteria from** to start from another assignment's. Be specific.
  Example:

  ```
  Eres profesor de la microcredencial de IA.
  REQUISITOS IMPRESCINDIBLES (si falla cualquiera, max 5/10):
  1. División train/val/test estrictamente disjunta.
  2. Código compilable y ejecutado con métricas razonables.
  3. El test no se usa durante el entrenamiento.

  ASPECTOS DE CALIDAD (suman sobre los requisitos):
  - Reproducibilidad (1.0)
  - Early stopping (0.75)
  - ...
  ```

- Optionally tick **Grade automatically when a student submits**.
- Save the assignment.

### 4. Triggering grading

From the **AI Grader Pro** page of the assignment, click **Grade with
AI** on a submission, or select several and use the bulk action (more
than 5 are queued as background tasks). With **Grade automatically when
a student submits**, each submission queues an adhoc task that calls
the LLM on the next cron run (≤60 s on a healthy site); the task runs
as the teacher who turned the option on.

## Usage flow

1. Student submits files (or online text) as usual.
2. The teacher clicks **Grade with AI**, or, with automatic grading on,
   the plugin enqueues `\local_aigrader\task\grade_submission`.
3. The plugin extracts the text, replaces the student's name, builds the
   prompt, calls the LLM, parses the response and stores the proposal
   with `status = 'ai_proposed'`.
4. Teacher visits the **AI Grader Pro** tab, sees the list of proposals.
5. Teacher clicks **Revisar →**, sees the LLM's grade, strengths,
   improvements, justification. All editable.
6. Teacher clicks **Aprobar y publicar** — grade lands in the gradebook
   via `\assign::save_grade()`, `submission_graded` event fires,
   student sees the result.

## Capabilities

| Capability                 | Default                       | What it allows                                          |
|----------------------------|-------------------------------|---------------------------------------------------------|
| `local/aigrader:use`       | editingteacher, manager       | Use AI grading on an assignment submission              |
| `local/aigrader:configure` | editingteacher, manager       | Enable AI grading, write criteria, set feedback language |
| `local/aigrader:viewlog`   | manager                       | Reserved for the audit log report (not used yet)        |

## Privacy

The plugin transfers the student's submitted content (extracted as
plain text, including code) and the teacher's evaluation criteria to
the LLM provider configured in Moodle's AI Subsystem. The provider may
process this in a jurisdiction other than the EU depending on the
institution's choice; the site administrator is responsible for signing
a DPA with the chosen provider. By default the student's name, email,
username and ID number are replaced with `[STUDENT]` before the text is
sent, also in file names (setting **Keep student names out of the
AI**). Names written in other forms, such as initials or nicknames, can
still get through.

The plugin's Privacy provider implements:

- `get_contexts_for_userid`
- `export_user_data`
- `delete_data_for_all_users_in_context`
- `delete_data_for_user`
- `delete_data_for_users`
- `get_users_in_context`

All audit logs (`local_aigrader_log`) retain the prompt text and model
response so the teacher can review what the AI saw and how it responded
— required for the AI Act audit trail. Personal data in the logs is
covered by Moodle's standard user-data deletion flows.

## Tests

```bash
# PHPUnit (in a development Moodle install with PHPUnit initialised)
vendor/bin/phpunit --testsuite local_aigrader_testsuite

# Behat (with a Selenium grid running)
vendor/bin/behat --tags @local_aigrader
```

Current status: 140 PHPUnit tests + 18 Behat scenarios, all passing.

For manual end-to-end smoke testing (after an upgrade, or during
peer review), see [TESTPLAN.md](TESTPLAN.md) — 24 scenarios walking
through install, configure, grade, publish, bulk, filter, error
paths, privacy export, backup/restore, file formats, automatic
grading, copying criteria, name removal, class report and uninstall.

## Code quality

```bash
vendor/bin/phpcs --standard=moodle local/aigrader/
```

Current status: 0 errors against the `moodle` and `moodle-extra` rule
sets. Warnings are acknowledged and documented per-file where they
exist (mostly comment-separator cosmetics and long lines in lang
strings).

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for the full release history.
Highlights:

- **v1.1.0-beta** — Class report: statistics and an AI-written
  executive summary of how the class did on each assignment.
- **v1.0.28-beta** — PowerPoint and OpenDocument files (also inside
  ZIPs). Automatic grading when a student submits. Copy criteria from
  another assignment. Student names replaced with `[STUDENT]` before
  anything is sent to the AI.
- **v1.0.27-beta** — Fixes backups (v1.0.26 broke the backup of every
  activity). Grades shown and published on the assignment's own scale.
  Availability per category/course. Proposals and audit log included
  in backups with user data. Tested on Moodle 5.1 and 5.2.
- **v1.0.26-beta** — Assignment configuration included in backups.
- **v1.0.17-beta** — Brazilian Portuguese (`pt_br`), Catalan (`ca`)
  and French (`fr`) translations. Full key parity (194 strings each).
- **v1.0.16-beta** — The third action on the review form is now a
  real "Save without publishing" — persists teacher edits without
  touching the gradebook.
- **v1.0.15-beta** — Distinct colour for "AI proposed" (cyan) vs
  "Published" (green); previously both used green which the pilot
  flagged as confusing.
- **v1.0.14-beta** — Own `styles.css` forces the manage-page spacing
  rules (Moove ships a Bootstrap build without `gap-*` utilities).
- **v1.0.13-beta** — Microcopy, extracted-text size, and structured
  warnings under the "Submission as seen by the AI" disclosure.
- **v1.0.12-beta** — Humanise criterion-score labels (snake_case →
  Title Case).
- **v1.0.11-beta** — UX polish: shorter bulk label, drop arrow from
  Revisar.
- **v1.0.10-beta** — Drop misleading "openai" tag from the review
  meta-info (was hiding the actual model).
- **v1.0.9-beta** — Paginate + sort the manage page via `\table_sql`,
  matching mod_assign's native grading view.
- **v1.0.8-beta** — Per-row `confirm()` when re-grading a published
  row; counter-bar spacing fixed.
- **v1.0.7-beta** — Typo fixes; i18n of dispatcher skip reasons;
  long error details collapsed into a hover-tooltip ⓘ icon.
- **v1.0.6-beta** — Simplified bulk dropdown to two actions; clickable
  status counter chips with filter; `PARAM_ALPHAEXT` for filter param.
- **v1.0.5-beta** — Bulk actions on the manage page: checkbox column
  + "With selected…" dropdown + confirmation page for destructive
  actions. Hybrid sync/async execution.
- **v1.0.4-beta** — Plugin Directory submission readiness: LICENSE,
  `thirdpartylibs.xml`, dev CLI scripts removed, phpcs cleanup,
  README rewrite, `\assign::save_grade()` instead of direct DML.
- **v1.0.3-beta** — PDF support via vendored `smalot/pdfparser`.
- **v1.0.2-beta** — Locale-safe grade input, `needs_manual_review`
  for unprocessable submissions.
- **v1.0.1-beta** — Classified error banner, ipynb output truncation,
  retry in-place, i18n dedupe.
- **v1.0.0-beta** — First pilot-ready release with full Privacy
  provider and Behat coverage.

## License

GPL-3.0-or-later. See [LICENSE](LICENSE).

Third-party libraries bundled under `thirdparty/vendor/` retain their
own licenses (LGPL-3.0 for `smalot/pdfparser`, MIT for
`symfony/polyfill-mbstring`); both are one-way compatible with
GPL-3.0+.

## Support

- Bugs and ideas for new features:
  https://github.com/HernanDiaz/moodle-local_aigrader/issues/new/choose
  — every suggestion is read.
