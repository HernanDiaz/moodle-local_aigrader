# Architecture of AI Grader Pro (`local_aigrader`)

> Audience: developers porting the plugin, reviewers from Moodle's Plugin
> Directory team, compliance officers checking the AI-Act audit trail.
> If you're a teacher looking for how to use the plugin, see
> [`README.md`](../README.md) instead.

This document explains how `local_aigrader` is wired together: which file
runs when, which tables are touched, which Moodle APIs are called, and
why a few non-obvious decisions are the way they are. It does **not**
duplicate per-method comments — read the source for those — but it does
give the bird's-eye view that no single source file captures.

## 1. Design constraints, stated up front

The plugin's behaviour is driven by four hard contracts. Everything
else falls out of them.

1. **Human-in-the-loop (HITL)** — the AI never writes a grade to the
   gradebook directly. A teacher's click is the only path to
   `assign_grades`.
2. **Through the AI Subsystem only** — the plugin never talks to
   OpenAI / Azure / Ollama / Groq directly. It calls
   `\core_ai\aiactions\generate_text` and lets whatever
   `aiprovider_*` plugin the admin configured do the I/O. This makes
   the plugin model-agnostic and means provider credentials live in
   core, not in our database.
3. **Audit-first** — every action (grade, regrade, edit, approve,
   save_draft) appends a row to `local_aigrader_log` with the prompt
   hash, model, token counts, and (when relevant) the diff between
   the AI's proposal and the teacher's final decision. This is the
   high-risk-AI record-keeping the EU AI Act Annex III asks for.
4. **No direct DML against `mod_assign`** — grades are pushed via
   `\assign::save_grade()` so that completion, notifications,
   gradebook export and observer plugins continue to work
   transparently.

If a future change needs to break any of these, that's an ADR-level
decision, not a code change.

## 2. The state machine

A `local_aigrader_submission` row exists per `assign_submission`. It
walks one of these states:

```
                                    ┌─────────────────────┐
                                    │  unsupported_format │  ← preflight
                                    └─────────────────────┘   (extractor.dispatcher)
                                              ▲
                                              │
                                              │
   (no row yet)  ──Teacher clicks──▶  pending_ai  ──LLM ok──▶  ai_proposed
                  "Calificar con IA"      │                          │
                                          │                          │
                                       LLM error                 Teacher
                                          │                       reviews
                                          ▼                          │
                                       error                    ┌────┴────┐
                                                                │         │
                                                          Save draft   Approve
                                                                │         │
                                                                ▼         ▼
                                                          teacher_      published
                                                          reviewed         │
                                                                │          │
                                                                └──Regrade─┘
                                                                  (back to
                                                                  ai_proposed)
```

State transitions live in `classes/manager.php` (grading) and
`review.php` / `bulk.php` (teacher actions). The states are stored as
strings, not enums, to keep upgrades cheap — the plugin requires
Moodle 4.5 which supports DB string columns natively.

## 3. The four endpoints

### `manage.php?cmid=<X>`

Teacher's hub. Lists every `assign_submission` for cmid X, joined
left-outer with `local_aigrader_submission` so rows without a proposal
still show up with status "No AI grading yet."

- Uses `\local_aigrader\output\manage_table` (a `\table_sql` subclass)
  for free pagination + sortable columns.
- The counter chips at the top come from a **separate** cheap
  `GROUP BY status` query against the full cohort — they stay correct
  regardless of which page is visible.
- Bulk-action form is rendered OUTSIDE the table, with row checkboxes
  using the HTML5 `form="aigrader-bulk-form"` attribute to link them
  across the DOM. This lets `\table_sql`'s pagination work without
  swallowing the bulk-form context.
- Handles two POST cases: enqueue a per-row grading task (action=enqueue),
  or nothing (the bulk dropdown posts to `bulk.php`).

### `review.php?submissionid=<Y>`

Teacher's review surface for a single proposal. Renders an editable
form pre-filled with the AI's proposal. Three actions:

- **`action=approve`** — saves the form values to
  `local_aigrader_submission`, writes the grade to `assign_grades` via
  `\assign::save_grade()`, sets status=`published`, audit log
  action=`approve` (or `edit` if the teacher modified anything).
- **`action=save_draft`** — same persistence to
  `local_aigrader_submission`, but **no** call to `save_grade()` and
  status=`teacher_reviewed`. Audit log action=`save_draft`. The grade
  stays out of the gradebook.
- (no action / Atrás link) — does nothing, redirects back to manage.

Subtle invariant: the form receives `$action` via PARAM_ALPHAEXT, not
PARAM_ALPHA. The values contain underscores (`save_draft`); PARAM_ALPHA
would silently strip them and the handler would never match —
indistinguishable from "teacher pressed nothing." This bit us once;
the parser type is now load-bearing.

### `bulk.php` (POST only)

Two-phase endpoint:

1. First POST from manage.php arrives with `cmid`, `action`,
   `ids[]`. We `\local_aigrader\bulk\dispatcher::classify()` each
   selected row against the action to get the `ok` / `skip:<reason>`
   verdict. For **destructive** actions (currently only
   `approve_publish`), we render the confirmation card
   (`templates/bulk_confirm.mustache`) with row counts + skip summary
   and a confirm/cancel pair.
2. Second POST with `confirm=1` arrives from the confirmation card.
   We re-classify (so a row that changed state between POST 1 and
   POST 2 is re-checked) and then `dispatcher::execute()` runs the
   action over the still-eligible rows.

For grading actions, `execute()` runs synchronously inline when count
≤ `SYNC_LIMIT` (5) and otherwise enqueues `\local_aigrader\task\grade_submission`
adhoc tasks so the request returns quickly. The cron picks them up.

### `retry.php?submissionid=<Y>`

Convenience endpoint for re-running grading on an `error` or
`teacher_reviewed` row. Same code path as bulk's `grade_ai` for one row.

## 4. The grading pipeline

`classes/manager.php` orchestrates one submission's grading. The flow
is linear and well-commented in the source; here are the steps in one
sentence each:

1. **Preflight via extractor dispatcher** — if no file in the
   submission contains text the AI can use (e.g. only an empty .pdf),
   short-circuit to `unsupported_format`. No tokens are spent.
2. **Build the prompt** — `prompt\builder::build_for_submission()`
   merges the per-assignment criteria (from `local_aigrader_assign`)
   with the extracted submission text and the (optional) site-wide
   default system prompt, producing a `built_prompt` value object
   carrying `system_message`, `user_message`, `metadata`,
   `hash` (SHA-256).
3. **Upsert** the `local_aigrader_submission` row to status=`pending_ai`.
4. **Resolve module context** — Moodle requires a context id when
   calling the AI Subsystem, used for capability checks and per-context
   provider overrides.
5. **Call the AI Subsystem** — `\core_ai\aiactions\generate_text` with
   the concatenated prompt (Moodle 4.5's `generate_text` doesn't yet
   accept a separate `systeminstruction` parameter; 4.6+ does). The
   provider's own system instruction must be EMPTY to avoid
   conflicting with ours.
6. **Parse** — `output_parser::parse()` returns a `parsed_proposal`
   with `grade`, `criterion_scores`, `strengths`, `improvements`,
   `justification`, plus `success` and `error` for malformed JSON.
7. **Persist proposal** if success, else **mark error** with the
   classified error code (see `classified_error.php` and
   `error_classifier.php`).
8. **Append audit log row** — always, win or lose.

Every step is wrapped so an exception lands as `status=error` with the
exception message in `local_aigrader_submission.error_message`, never
as an uncaught throw.

## 5. The extractor subsystem

`classes\extractor\dispatcher::extract($submissionid)` walks the files
attached to the submission and produces a single concatenated text
blob (or refuses, with a skip reason).

Per-format handlers live in `classes/extractor/`:

- `text_extractor` — `.txt`, `.md`, plain source code (20+ languages
  by extension).
- `docx_extractor` — `.docx` via direct ZIP+XML parsing (no LibreOffice).
- `pdf_extractor` — `.pdf` up to 5 MB via vendored `smalot/pdfparser`
  (under `thirdparty/vendor/`, declared in `thirdpartylibs.xml`).
- `ipynb_extractor` — Jupyter notebooks; strips outputs by default to
  avoid embedded base64 images bloating the prompt.
- `zip_extractor` — recurses into ZIPs and dispatches each entry to
  the right inner extractor.

Each returns an `extraction_result` value object with `text`,
`warnings`, `is_needs_review`, and `error`. The dispatcher aggregates
results and decides the overall outcome via `decide_outcome()`. The
outcome is the only thing the manager sees — extractor internals are
free to evolve without touching the manager.

The "skip marker" string the extractor emits to flag unprocessable
content is localised (`extract_skip_marker` lang key); the dispatcher
matches both the localised string AND the literal "unsupported" so
old logs stay parseable.

## 6. The prompt builder

`classes/prompt/builder.php` assembles:

- `system_message` — the plugin's default system prompt, plus the
  site-wide setting `default_system_prompt`, plus a language directive
  derived from the assignment's `language_override` (falls back to
  course language).
- `user_message` — the teacher's criteria_text followed by the
  extracted submission text, with explicit section markers
  (`--- CRITERIA ---`, `--- SUBMISSION ---`) to help small models
  separate them.
- `metadata` — `submissionid`, `assignid`, `courseid`, `studentid`,
  `language`, etc. Used by the manager to update rows after
  the call; never sent to the LLM.

The output is a `built_prompt` value object whose `hash()` is the
SHA-256 of `system_message . "\n--- TASK ---\n" . user_message`. The
hash is logged (not the raw prompt) so duplicate-prompt detection and
caching can be added later without rewriting the audit table.

Note: at runtime the system+user messages are concatenated again in
`manager::grade_submission()` because Moodle 4.5's AI Subsystem
`generate_text` action accepts only a single `prompttext`. When 4.6+
becomes the floor, the call site can switch to passing system and
user separately.

## 7. Database tables

Three tables, all `db/install.xml`-defined. The XML is the source of
truth; what follows is the conceptual gloss.

### `local_aigrader_assign`

Per-assignment config. Unique on `assignid`. Holds:

- `enabled` — whether AI grading is wired up on this assignment.
- `criteria_text` — the teacher's prompt-as-rubric.
- `source` — `manual` | `rubric_imported` | `rubric_edited` (the
  rubric importer pre-fills criteria from Moodle's advanced grading
  rubric when present).
- `language_override` — per-assignment feedback language.
- `model_override` — unused since v1.0.27 (Moodle's AI Subsystem cannot
  choose a model per request); kept so older backups restore cleanly.

### `local_aigrader_submission`

Per-submission proposal + final-decision state. Unique on
`submissionid`. Holds both `proposed_*` (from the AI) and `final_*`
(from the teacher), plus the status enum. `final_grader` is the
teacher's `user.id` — never a system id, by HITL contract.

### `local_aigrader_log`

Append-only audit. One row per action (`grade`, `regrade`, `edit`,
`approve`, `save_draft`, `reject` for legacy rows). Holds prompt
text, prompt hash, model, provider, token counts, cost, duration,
proposed and final grades, and the teacher_edits diff JSON. This is
the table compliance officers read.

The `prompt_text` column will be encrypted at-rest in v0.3
(MariaDB transparent encryption + per-deployment key); for now it's
plain so dev environments stay easy. The hash column is the durable
identity of a prompt across encryption changes.

## 8. The privacy provider

`classes/privacy/provider.php` declares:

- **Stored data** — all three tables, with the per-field metadata
  required by GDPR (purpose, lawful basis pointer, retention guidance).
- **`get_users_in_context()`** — finds users referenced in any
  table for a given course/module context (both teachers as
  `final_grader` / `userid` and students as `studentid`).
- **`export_user_data()`** — exports a per-user JSON tree under the
  module subcontext, with one section per table.
- **`delete_data_for_*()`** — destructive deletion for GDPR Article 17
  requests. The audit log is preserved as required for high-risk AI
  systems EXCEPT for personally identifying free-text fields, which
  are scrubbed (`prompt_text` is anonymised to "[deleted by GDPR
  request on YYYY-MM-DD]"; the hash, model, token counts, duration
  and grade columns are retained).

Test coverage lives in `tests/privacy/provider_test.php`.

## 9. The bulk dispatcher

`classes/bulk/dispatcher.php` is the only place that knows which
actions apply to which row states. Two public methods:

- `classify(string $action, stdClass $row): string` — pure function.
  Returns `'ok'` or `'skip:<reason_key>'`. No side effects. Used by
  both the confirmation page (to show counts) and `execute()` (to
  guard each row a second time at apply-time, against TOCTOU).
- `execute(string $action, array $rows, array $applicable): array` —
  applies the action to each `ok` row. For LLM actions, runs sync
  when count ≤ `SYNC_LIMIT` (5) and otherwise enqueues the adhoc task.

The action constants (`ACTION_APPROVE_PUBLISH`, `ACTION_GRADE_AI`)
and the eligibility matrix are tested exhaustively in
`tests/bulk_dispatcher_test.php` (19 PHPUnit cases covering every
action × status combination, plus regressions for the actions we
removed in v1.0.6).

## 10. Adhoc tasks

`classes\task\grade_submission` is a `\core\task\adhoc_task` that calls
`manager::grade_submission()` for a single `submissionid` carried in
its custom data. Enqueued by the bulk dispatcher (and by `retry.php`)
when work needs to happen outside the request lifecycle.

Failures are retried with Moodle's standard adhoc-task exponential
backoff. After max retries the task is buried; the row stays in
`pending_ai` or moves to `error` depending on which step failed.

`task_reset.php` exposes a helper for the admin to mark stuck rows
back to `error` (or clear them entirely); used in pilot sites when
the AI Subsystem provider config changes and old `pending_ai` rows
shouldn't keep retrying.

## 11. Capabilities

Three capabilities, defined in `db/access.php`:

- **`local/aigrader:use`** (CONTEXT_COURSE, write) — allowed to
  trigger AI grading and to use the review/manage pages. Default:
  `editingteacher`, `manager`.
- **`local/aigrader:configure`** (CONTEXT_COURSE, write) — allowed
  to enable AI Grader Pro on an assignment and write evaluation
  criteria via the assignment edit form. Default: same as `:use`.
- **`local/aigrader:viewlog`** (CONTEXT_SYSTEM, read) — allowed to
  read the cross-course audit log. Default: `manager`.

Every endpoint calls `require_capability()` after `require_login()`,
in that order. Students hitting `manage.php` or `review.php` get
Moodle's standard "Sorry, but you do not currently have permissions"
page.

## 12. Tests

- **PHPUnit** under `tests/`:
  - `bulk_dispatcher_test.php` — 19 cases, full action × status matrix.
  - `dispatcher_outcome_test.php` — extractor `decide_outcome()`.
  - `error_classifier_test.php` — classification of provider errors.
  - `ipynb_extractor_test.php`, `pdf_extractor_test.php`,
    `output_parser_test.php` — per-format / parser unit tests.
  - `task_reset_test.php` — admin helper.
  - `tests/privacy/provider_test.php` — privacy provider end-to-end.

- **Behat** under `tests/behat/`:
  - `configure_assignment.feature` — teacher enables the plugin
    on an assignment.
  - `review_flow.feature` — approve+publish, save-draft, re-edit,
    grade range validation.
  - `bulk_actions.feature` — confirmation page with skip summary,
    cancel, no-selection warning.
  - `filter_and_pagination.feature` — counter chips, chip-based
    filter, per-page selector.
  - `capability.feature` — student blocked, teacher allowed,
    manager allowed.

Behat scenarios plant their fixtures via the plugin's data generator
(`tests/generator/lib.php`) — no real LLM call ever happens during the
test suite. The generator can produce `local_aigrader_submission`
rows in any of the six states, plus log entries and per-assignment
configs.

## 13. CI

`.github/workflows/moodle-ci.yml` runs `moodlehq/moodle-plugin-ci` v4
on every push and PR. Matrix:

- PHP **8.1 / 8.2 / 8.3** × Moodle **4.5 LTS / 5.0** × MariaDB / PostgreSQL.
- Per job: `phplint`, `phpmd`, `phpcs --max-warnings 0`,
  `phpdoc --max-warnings 0`, `validate`, `savepoints`, `mustache`,
  `grunt --max-lint-warnings 0`, `phpunit --fail-on-warning`,
  `behat --profile chrome`.

This is the same check pack Plugin Directory peer reviewers run, so a
green CI is a strong signal that submission will land cleanly.

## 14. Non-obvious decisions

A handful of choices that surprised early reviewers — documented so
they don't get "fixed" by accident.

- **Why `aigrader-bulk-form` outside the table?** `\table_sql`
  renders its own `<tbody>` and intersperses pagination controls.
  Wrapping a `<form>` around it would either swallow the pagination
  (broken) or leave the checkboxes orphan (also broken). The HTML5
  `form="<id>"` attribute on each checkbox is the only way to bind
  row inputs to a form whose `<form>` element lives elsewhere.
- **Why a per-row `confirm()` for re-grading published rows?** A
  teacher who re-runs the AI on a published row WILL overwrite the
  proposal cells, even though the gradebook is left untouched. The
  inline JS confirm gives them a chance to back out without
  destroying their previous edits.
- **Why number_format(., 2, '.', '') and not format_float?**
  `format_float` respects the user's locale and renders commas in
  es/fr/de. HTML5 `<input type="number">` accepts only ASCII dots;
  a localised string yields an empty field, hiding the AI proposal
  from the teacher.
- **Why the plugin ships its own `styles.css` with `gap: ...`
  declarations?** Moove (and a few other community themes) ship
  Bootstrap 5 without the `gap-*` utility classes. Browsers report
  `gap: normal` (= 0) on `<div class="d-flex gap-3">`, breaking the
  manage page spacing. The plugin's own CSS forces `gap` explicitly
  per wrapper class.
- **Why `final_grader` is required for HITL?** The column is the
  physical guarantee that the row was approved by a human. A future
  refactor that left it null (e.g. "system auto-publish if confidence
  ≥ 0.95") would break the contract — and the privacy export would
  no longer be able to attribute the grade to a person.
- **Why the audit log keeps `prompt_text` in plaintext today?**
  Trade-off between developer ergonomics and at-rest encryption
  effort. The hash is the durable identity; the body will be moved
  to an encrypted column in v0.3. Pilots that need encryption now
  can use MariaDB / PostgreSQL TDE.

## 15. Where to start when porting

If you're forking this for a different LMS (e.g. Open edX, Canvas):

1. Replace the `manager.php` AI Subsystem call with your LMS's
   equivalent or with a direct provider call.
2. Replace `\assign::save_grade()` with the LMS's gradebook API.
3. Replace the `mod_assign` Quickform hooks with whatever your LMS
   uses for assignment-edit-form extensions.
4. Keep `local_aigrader_log` exactly as is — the audit-table shape is
   independent of the LMS and is the highest-value asset for AI Act
   compliance.
5. Re-implement the privacy provider against your LMS's privacy API.

Tables (1) + state machine (2) + capability model (11) port directly.
The state machine in particular is the part you don't want to redesign.
