# Plugin Directory submission copy

Source text for the **Moodle Plugin Directory** listing form at
<https://moodle.org/plugins/manage.php>. Paste the relevant blocks
into the corresponding form fields when submitting a new plugin or
publishing a new version.

The README.md is the canonical source of truth for users; this file
exists because the Plugin Directory form has its own short/long
description fields with their own character limits and conventions.

---

## Plugin Directory metadata

| Field | Value |
|---|---|
| **Plugin type** | Local |
| **Plugin component** | `local_aigrader` |
| **Plugin name** | AI Grader Pro |
| **Frankenstyle full name** | `local_aigrader` |
| **Maintainer** | Hernán Díaz |
| **Bug tracker** | https://github.com/HernanDiaz/moodle-local_aigrader/issues |
| **Source control** | https://github.com/HernanDiaz/moodle-local_aigrader |
| **Documentation** | https://github.com/HernanDiaz/moodle-local_aigrader#readme |
| **Discussion** | https://github.com/HernanDiaz/moodle-local_aigrader/discussions |
| **License** | GPL-3.0-or-later |
| **Supported Moodle versions** | 4.5+ (LTS) |
| **Required PHP** | 8.2+ |
| **Maturity** | BETA |

### Tags (pick up to 10)

```
ai, assessment, assignment, llm, grading, feedback, automation,
audit, gdpr, ai-act
```

### Categories

- **Activities and resources** → no
- **Assessment** → **yes**
- **Course administration** → secondary
- **Site administration** → secondary

---

## Short description (≤ 200 chars, plain text)

> AI-assisted grading for Moodle assignments. The LLM proposes a grade and structured feedback; the teacher reviews, edits and decides. Nothing reaches the gradebook without an explicit teacher click. EU AI-Act ready, multi-LLM, multi-language.

(199 chars including spaces.)

### Alternative short (one-liner, ≤ 120 chars)

> AI-assisted grading: the LLM proposes, the teacher decides. EU AI-Act ready, multi-LLM, 5 languages out of the box.

(118 chars.)

---

## Long description (markdown)

> Paste into the "Description" field. Moodle Plugin Directory accepts
> Markdown, links, code blocks and a limited set of inline HTML.

---

**AI Grader Pro** is an AI-assisted grading assistant for Moodle
`mod_assign` activities, designed around a strict
**human-in-the-loop** workflow: the AI proposes a grade and structured
feedback; the teacher reviews, edits and decides whether to publish.
Nothing reaches the gradebook without an explicit teacher click.

### Why teachers like it

- The LLM does the boring part — reading every submission, scoring
  it against the rubric, drafting the per-criterion feedback. A
  cohort of 30 essays that takes 4-6 hours to grade by hand drops to
  about 30 minutes of review + approve.
- Bulk publish: tick a few boxes, choose "Publish proposed grade"
  in the "With selected…" dropdown, confirm once, done.
- Per-criterion scores let the teacher see WHERE the AI saw weakness,
  not just a global grade.
- An "Submission as seen by the AI" disclosure lets the teacher
  inspect exactly what text the LLM received — useful when the AI
  proposal seems off (e.g. the student's notebook outputs were
  too verbose and got truncated).

### Why admins like it

- Works with **any LLM** that exposes the `generate_text` action via
  Moodle's AI Subsystem: OpenAI, Azure OpenAI, Groq (via the
  OpenAI-compatible endpoint), and any future provider. No
  provider lock-in inside the plugin.
- **Privacy provider** complete: GDPR export, deletion and the AI
  Act Annex III audit trail (append-only `local_aigrader_log` with
  prompt hashes, model, token counts, teacher edits).
- **GPL-3.0-or-later**, no external SaaS dependency, no telemetry,
  no API keys baked into the code.

### Features

- 🧠 **Multi-LLM** via Moodle's AI Subsystem — OpenAI, Azure
  OpenAI, Groq, Anthropic, anyone implementing `generate_text`.
- 📄 **File-format coverage**: online text, plain text, Markdown,
  Word (`.docx`), Jupyter notebooks (`.ipynb`), PDF (text-based,
  up to 5 MB), ZIP archives (recursed), and 20+ source-code
  languages.
- 🛡️ **Manual-review fallback**: submissions whose attached files
  are entirely unprocessable (e.g. only a PDF too large or
  image-only) are flagged for the teacher with a clear banner.
  No fake 0/10 grades are produced.
- 📋 **Bulk actions**: checkbox column + "With selected…" dropdown
  to publish or re-grade many submissions in one click.
  Confirmation page for destructive actions.
- 📊 **Paginated + sortable manage page**: server-side `\table_sql`
  with 10 / 25 / 50 / 100 / All per-page, sortable columns, and a
  counter banner of clickable status chips that filter the view.
- 🚦 **Classified error banner**: rate limit, payload too large,
  auth failure, network error, parse error — each gets a localised
  banner with cause, suggested action and per-student retry.
- 🌐 **5 languages out of the box**: English, Spanish, Brazilian
  Portuguese, Catalan, French — all 250 strings, full parity.
- ✅ **148 PHPUnit tests** + 18 Behat scenarios, all passing.

### Requirements

- Moodle **4.5 LTS** or later.
- The PHP version your Moodle requires (**8.1** or later on Moodle 4.5).
- An LLM provider configured in
  *Site administration → General → AI → AI providers*.

### Quick start

1. Install the plugin from this Directory listing.
2. *Site administration → General → AI → AI providers* — enable one (OpenAI is
   the easiest if you have an API key; Groq is the cheapest).
3. Open any assignment → edit → expand "AI Grader Pro" → tick
   "Enable" and paste evaluation criteria in plain prose. Save.
4. When a student submits, the **AI Grader Pro** tab on the
   assignment shows the proposal once it has been graded. Click
   **Revisar**, optionally edit, click **Aprobar y publicar**.

For full installation and configuration walkthrough see the
[README on GitHub](https://github.com/HernanDiaz/moodle-local_aigrader#readme).

### EU AI Act note

The plugin is designed for the **high-risk AI system** category
defined in Regulation (EU) 2024/1689 Annex III, point 3 (education).
Concretely:

- **Human oversight** is structural, not optional: the AI never
  writes a grade directly. `local_aigrader_submission.final_grader`
  is always the teacher's user id, never a system id.
- **Audit trail** lives in `local_aigrader_log` with the prompt
  hash, model used, token counts, the LLM's raw response, and a
  JSON diff of any teacher edits.
- **Right to explanation** is achievable end-to-end: the teacher
  can show the student the full input the LLM received, the
  per-criterion scores it produced, and the justification text —
  before or after publication.

Note that the **DPA with the LLM provider** is a separate site-admin
responsibility; the plugin does not sign or negotiate contracts on
your behalf.

### Languages

| Code | Language | Coverage |
|---|---|---|
| `en` | English | 250 / 250 strings |
| `es` | Spanish (universal) | 250 / 250 |
| `pt_br` | Brazilian Portuguese | 250 / 250 |
| `ca` | Catalan | 250 / 250 |
| `fr` | French (European) | 250 / 250 |

Contributions for additional languages welcome — either as a PR to
the repository, or once the plugin lands in the Directory, via the
[AMOS translation server](https://lang.moodle.org/local/amos/).

### Support and contributions

- Bug reports / feature requests:
  [GitHub Issues](https://github.com/HernanDiaz/moodle-local_aigrader/issues).
- PRs welcome on `main`; please run `phpcs --standard=moodle` and
  the PHPUnit test suite before submitting.
- The maintainer also offers commercial implementation services,
  custom rubric design, AI-Act compliance documentation packages,
  and managed-LLM endpoint hosting — contact via the GitHub repo.

---

## Release notes for the version bump form

Each Moodle Plugin Directory release submission asks for a "Release
notes" field. Use the most recent CHANGELOG.md entry, trimmed to ~5
bullets. For the **first** submission, use this:

> v1.0.17-beta — first Plugin Directory release. AI-assisted grading
> for `mod_assign` with human-in-the-loop guarantees. Multi-LLM via
> the Moodle AI Subsystem, multi-language (en / es / pt_br / ca / fr),
> paginated manage page, bulk actions, classified error banner, full
> Privacy provider including the EU AI Act Annex III audit trail.
> PHPUnit and Behat test suites, 0 phpcs errors.
