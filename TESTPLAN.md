# Test plan — `local_aigrader` (AI Grader Pro)

This document is for **Moodle Plugin Directory peer review** and for
post-upgrade smoke testing. It walks through the user flows that the
plugin guarantees, in the order they would happen on a fresh
Moodle 4.5 LTS site.

The plan is deliberately step-by-step so a reviewer who has never
seen the plugin can validate the full pipeline in **30-45 minutes**
without reading any code.

---

## Prerequisites

Before starting:

- A clean Moodle 4.5 LTS site (or later).
- Site administrator account.
- One **editing-teacher** account in a test course.
- One **student** account enrolled in the same course.
- An LLM provider configured in *Site administration → General → AI →
  AI providers* (see the [user guide](docs/user-guide.md#3-configure-an-ai-provider)).
  Any provider exposing the `generate_text` action works. The cheapest
  way to run this test plan end-to-end is a free Groq account
  (https://console.groq.com/) plugged into the built-in `aiprovider_openai`
  using Groq's OpenAI-compatible endpoint, with a chat model available
  in your Groq account (for example `openai/gpt-oss-120b`).
- The screens are described with the plugin's English strings; on a site
  in another language, the labels are the translated ones.
- Cron running every minute (`php admin/cli/cron.php` in a loop is fine
  for testing — the plugin also grades synchronously when the teacher
  clicks "Grade with AI" so cron is only strictly required if you
  want to exercise the async path).

Two helper files in the repo are useful but not required:

- `cli/seed_test_course.php` — internal dev script, not bundled, kept
  outside the published release. Reviewers can ignore.
- `tests/` — PHPUnit fixtures. Independent of this test plan.

---

## Scenario 1: Plugin installs cleanly

1. Place the plugin source under `<wwwroot>/local/aigrader/`.
2. Visit `<wwwroot>/admin/index.php` as administrator.
3. **Expected**: the upgrade screen lists `local_aigrader` with the
   current version and prompts to upgrade. No PHP warnings or notices
   appear on the screen.
4. Click "Upgrade Moodle database now".
5. **Expected**: the upgrade reports success, three tables are
   created (`local_aigrader_assign`, `local_aigrader_submission`,
   `local_aigrader_log`), and three capabilities are registered
   (`local/aigrader:use`, `local/aigrader:configure`,
   `local/aigrader:viewlog`).

---

## Scenario 2: Capabilities are assigned correctly

1. *Site administration → Users → Permissions → Define roles*.
2. Edit the **Teacher** role.
3. **Expected**: `local/aigrader:use` and `local/aigrader:configure`
   are set to **Allow**.
4. Edit the **Manager** role.
5. **Expected**: all three capabilities (`use`, `configure`,
   `viewlog`) are set to **Allow**.
6. Edit the **Student** role.
7. **Expected**: no `local/aigrader:*` capabilities are listed (the
   plugin grants nothing to students).

---

## Scenario 3: Admin global settings

1. *Site administration → Plugins → Local plugins → AI Grader Pro*.
2. **Expected**: a settings page renders with at least the following
   controls:
   - **Enable plugin** (`setting_enabled`) — checkbox, default ON.
   - **Auto-import from advanced rubric** (`setting_rubric_autoimport`).
   - **Default system prompt** (`setting_default_system_prompt`) —
     textarea.
3. Leave defaults.

---

## Scenario 4: Teacher enables AI Grader Pro on an assignment

1. Log out, log in as the **editing teacher**.
2. In the test course, add a new **Assignment** activity called
   "Test essay". Allow online text + file submissions.
3. Scroll down to **AI Grader Pro**.
4. **Expected**: a collapsible section labelled "AI Grader Pro" is
   present with the following controls visible when expanded:
   - **Enable AI-assisted grading for this assignment** — checkbox.
   - **Copy criteria from** — select with the other assignments that
     have criteria, and a **Copy** button.
   - **Evaluation criteria** — textarea (initially empty unless an
     advanced rubric has been imported).
   - **Feedback language (optional)** — select, "Auto (use course
     language)" by default.
   - **Grade automatically when a student submits** — checkbox.
5. Tick the enable checkbox.
6. Try to save the assignment **without** writing any criteria.
7. **Expected**: form validation error — *"Evaluation criteria are required when AI-assisted grading is enabled."*
8. Paste a short criteria text such as:
   ```
   Evaluate this essay (300-500 words) on the social impact of AI:
   - Clarity of the thesis (40%): identifiable in the first paragraph.
   - Quality of the evidence (30%): sources are cited.
   - Language (30%): academic register.
   Tone: constructive.
   ```

   Criteria can be written in any language; the AI keeps the teacher's
   wording for the criterion names.
9. Save the assignment.
10. **Expected**: assignment saves without errors. A row exists in
    `mdl_local_aigrader_assign` with `enabled = 1` and the
    `criteria_text` set.

---

## Scenario 5: Student submits

1. Log out, log in as the **student**.
2. Open "Test essay".
3. Submit a short online-text essay (200-400 words). Any topic
   adjacent to the criteria works — for a quick test, paste two
   paragraphs from any opinion article.
4. **Expected**: standard mod_assign submit flow, no plugin-specific
   UI shown to the student.

---

## Scenario 6: Teacher triggers AI grading

Two sub-paths exist; either is acceptable for peer review.

### 6a. Synchronous (recommended for the review)

1. Log out, log in as the **editing teacher**.
2. Open "Test essay".
3. Open **AI Grader Pro** from the assignment's *More* menu.
4. **Expected**: the manage page renders with the student listed
   under status "No AI grading yet".
5. Click **Grade with AI** on the student's row.
6. **Expected**: the page hangs for 2-5 seconds (synchronous LLM
   call), then redirects with a green toast *"AI grading completed.
   Open Revisar → to review the proposal."* (the English string still
   names the button by its Spanish label).
7. **Expected**: the status badge has flipped to "AI proposed" and a
   numeric grade is shown in the "Proposed grade" column.

### 6b. Async (cron)

1. Same as 6a but after step 2 click **Grade with AI** then leave
   the page.
2. Wait for the next cron tick.
3. Reload manage; status is "Procesando..." while in flight,
   "AI proposed" once done.

---

## Scenario 7: Review the AI proposal (HITL gate)

1. Click **Review** on the student's row.
2. **Expected**: review form opens with:
   - Student's submitted files listed under "Entrega del alumno".
   - A collapsible "Entrega tal y como la vio la IA" disclosure
     showing the exact text the LLM received, with a help paragraph
     and the extracted size in KB.
   - A "Criterion scores (from AI, for context)" list with
     per-criterion scores.
   - Editable form fields: **Final grade**, **Strengths**,
     **Improvements**, **Justification (visible to the student)**, all
     pre-filled with the AI's proposal.
   - Three action buttons: **Approve and publish** (green),
     **Save without publishing** (gray outline), and an **Back** link.
3. **Expected**: at the bottom, a small text-muted line reads
   *"Proposed at `<datetime>` by `<model name>`"*.

---

## Scenario 8: Save without publishing

1. Edit the grade in the form (e.g. change to 6.5).
2. Add a line to "Strengths" or "Improvements".
3. Click **Save without publishing**.
4. **Expected**: redirect to manage with toast *"Saved without publishing. The grade is not in the gradebook yet."*
5. **Expected**: the row's status badge changes from "AI proposed"
   (cyan) to "Teacher reviewed" (purple).
6. **Expected**: gradebook entry for the student is empty (verify
   via *Course → Grades*).
7. Re-open the same student's review page.
8. **Expected**: the form pre-fills with the values you typed in
   step 1-2, NOT the original AI proposal. This validates that
   the draft persisted.

---

## Scenario 9: Approve and publish

1. On the same student's review page, leave the form as is.
2. Click **Approve and publish**.
3. **Expected**: redirect to manage with toast *"Grade approved and published to the gradebook."*
4. **Expected**: the row's status badge is now "Published" (green
   solid), the action column shows **View ✓** instead of **Review**.
5. Open *Course → Grades*. The student now has the grade you saved
   (e.g. 6.50).
6. As the student, open the gradebook. The grade and feedback
   (Strengths, Improvements, Justification) are visible.

---

## Scenario 10: Re-grade an already-published row

1. Back on the manage page as teacher, click **Grade with AI** on
   the already-published row.
2. **Expected**: a native browser `confirm()` dialog appears with
   the message *"This submission is already published. Re-grade with AI?…"*.
3. Click OK.
4. **Expected**: the LLM is called again, the row badge flips back
   to "AI proposed". The **gradebook entry stays untouched** —
   verify via *Course → Grades* that the value from Scenario 9 is
   still there.
5. Cancel the dialog instead; verify nothing changes.

---

## Scenario 11: Bulk action — Publish proposed grade

(This step expects at least 2 submissions in `ai_proposed` state.
Have a second student submit before running it.)

1. On the manage page, tick the row-level checkbox for two
   `ai_proposed` rows.
2. In the "With selected:" dropdown, choose **Publish proposed grade**.
3. Click **Apply**.
4. **Expected**: an intermediate confirmation page renders with:
   - The action label ("Publish proposed grade").
   - A warning paragraph stating grades will be written to the
     gradebook.
   - A count "2 submissions will be processed."
   - Two buttons: **Yes, publish** and **Cancel**.
5. Click **Yes, publish**.
6. **Expected**: redirect to manage with toast *"2 submissions processed"*. Both rows flip to "Published".

---

## Scenario 12: Filter chips

1. Click the counter chip *"X publicadas"* above the table.
2. **Expected**: table reloads with only `published` rows visible.
   URL gains `?filter=published`. The clicked chip gains a dark
   border indicating it is active. A small "Show all" link
   appears.
3. Click "Show all".
4. **Expected**: filter clears, all rows are visible again.
5. Try the other chips one by one (`ai_proposed`,
   `teacher_reviewed`, `problems`, `none`) and verify each shows
   only the right rows.

---

## Scenario 13: Pagination + sorting

1. With at least 10 submissions in the cohort, lower **Show per page:** to 10.
2. **Expected**: pagination bar renders (`1 2 »`) above and below
   the table. Only 10 rows shown.
3. Click the "Proposed grade" column header.
4. **Expected**: rows reorder by `proposed_grade`. An arrow icon
   appears next to the header showing sort direction. URL gains
   `?tsort=grade&tdir=...`.
5. Click the header again — sort direction inverts.

---

## Scenario 14: Unsupported file format

1. As a student in a fresh assignment, upload a single large PDF
   (>5 MB) or an image-only PDF. Submit.
2. As the teacher, open AI Grader Pro from that assignment's *More* menu.
3. Click **Grade with AI** on the row.
4. **Expected**: status flips to "Unsupported format" (yellow
   warning badge) with a small ⓘ info icon next to it. Hovering the
   icon shows a tooltip with the explanation in the teacher's
   language (e.g. *"Todos los archivos enviados son ilegibles.
   Formatos soportados: …"*).
5. Click **Review** on that row.
6. **Expected**: the review form opens with an amber notice at the
   top *"AI grading was not available for this submission…"*, the form fields are blank (no AI proposal to
   pre-fill), and the teacher can still grade manually and publish
   via the same **Approve and publish** path.

---

## Scenario 15: LLM error path (rate limit / timeout / auth)

The cleanest way to reproduce this in the peer-review sandbox is
to temporarily revoke the LLM API key.

1. *Site administration → General → AI → AI providers* → blank the
   API key, save.
2. As the teacher, click **Grade with AI** on any row.
3. **Expected**: row flips to "Error" (red danger badge) with the
   ⓘ icon showing the classified reason ("Provider rejected the API
   key").
4. **Expected**: an "AI grading failed" banner renders at the top
   of the manage page grouping all errored rows by classification
   kind, with a "Retry now" link per student and a "View raw error"
   `<details>` block.
5. Restore the API key and click "Retry now" on the affected row.
6. **Expected**: grading succeeds, row returns to "AI proposed".

---

## Scenario 16: Privacy provider

1. *Site administration → Users → Privacy and policies → Data
   requests*.
2. Create a data export request for the student in this test.
3. Run cron once.
4. **Expected**: an export ZIP is generated. Inside, the student
   finds a folder for AI Grader Pro containing JSON of their
   proposed grade, feedback, and audit log entries that refer to
   them.
5. Create a deletion request for the same student.
6. Run cron once.
7. **Expected**: the student's data in `mdl_local_aigrader_submission`
   and `mdl_local_aigrader_log` is anonymised (student id replaced)
   while the rows themselves remain (audit-trail requirement of the
   EU AI Act Annex III).

---

## Scenario 17: Internationalisation

1. Switch the user language to **English** in profile preferences.
2. Reload the manage page.
3. **Expected**: all plugin strings render in English. No
   `[[missing_key]]` placeholders anywhere.
4. Repeat for `pt_br`, `ca`, `fr` (these lang packs ship inside the
   plugin under `lang/<code>/local_aigrader.php`; they require the
   corresponding Moodle core lang pack to be installed via
   *Administration → Language → Language packs*).

---

## Scenario 18: Uninstall

1. *Site administration → Plugins → Plugins overview*.
2. Find AI Grader Pro, click **Uninstall**.
3. **Expected**: confirmation prompt warns that all plugin tables
   will be dropped. Confirm.
4. **Expected**: uninstall completes without errors. The three
   `mdl_local_aigrader_*` tables and three capabilities are gone
   from the database. No orphaned settings remain in
   `mdl_config_plugins`.

---

## Scenario 19: Course backup and restore preserve AI Grader Pro data

Verifies that the Backup/Restore API hooks (rewritten in v1.0.27) keep
the per-assignment evaluation criteria intact when a course is
duplicated, restored on the same site, or imported on another site,
and that backups with user data also carry the AI proposals, teacher
decisions and audit log.

### Backups of other activities (regression, v1.0.26)

0. In a course with a forum (or any activity that is not an
   assignment), use *Duplicate* on that activity, then back up the
   whole course. **Expected**: both complete without errors. v1.0.26
   failed here with "Call to a member function get_element() on
   string".

### Same-site backup → restore

1. Open a course with at least one assignment that has AI Grader
   Pro **enabled** and a non-trivial **Evaluation criteria** value
   (write something distinctive: "TEST-MARKER-2026 — evaluate
   thesis clarity"), plus an optional **Feedback language** (`es`).
2. *Course administration → Course reuse → Backup*. Tick
   "Include user data" if you want to test the full path; untick
   it for the minimal config-only path.
3. Complete the backup wizard. Download the resulting `.mbz`.
4. *Course administration → Course reuse → Restore*. Upload the
   `.mbz`. Restore as a **new course** in the same site.
5. **Expected**: the new course has the assignment, and editing it
   shows:
   - AI Grader Pro fieldset is **enabled**.
   - Evaluation criteria contains `TEST-MARKER-2026 — evaluate
     thesis clarity`.
   - Feedback language carries the original value.
6. Open the new course's AI Grader Pro manage page. **Expected**:
   loads without "AI Grader Pro is not enabled on this
   assignment" error.
6b. If the backup included user data: **Expected**: the manage page
   lists the same proposals with the same statuses and grades, the
   review page shows the teacher's saved drafts, and
   `mdl_local_aigrader_log` has the audit entries pointing at the
   restored submissions. Without user data, the list is empty
   (only the configuration travels).

### Course duplication (Course reuse → Import)

7. From a different course, *Course administration → Course reuse
   → Import* → pick the source course. Run the import.
8. **Expected**: same outcome as steps 5-6 — the assignment in the
   destination course inherits AI Grader Pro config from the
   source.

### Cross-site restore (different Moodle install)

9. Take the `.mbz` from step 3 to a separate Moodle 4.5+ instance
   that ALSO has `local_aigrader` installed.
10. *Site administration → Courses → Restore course*. Upload the
    file, restore as a new course.
11. **Expected**: the configuration row is re-created. If the
    `usermodified` user does not exist on the destination,
    `local_aigrader_assign.usermodified` falls back to the
    restoring admin's user id (the row is still queryable, no
    foreign-key violation).

### Cross-site restore (plugin NOT installed on destination)

12. Take the `.mbz` to a Moodle 4.5+ instance WITHOUT
    `local_aigrader` installed.
13. Restore the course.
14. **Expected**: restore completes successfully. The plugin's
    XML data inside the `.mbz` is silently ignored (Moodle's
    restore engine handles missing plugin classes gracefully).
    The assignment is restored without the AI Grader Pro fields,
    as expected.

### Notes

- Proposals and audit entries are only restored when their student
  and submission are restored too (user data included, student
  enrolled). A teacher missing on the destination site is recorded
  as user 0 in the audit log, as the privacy provider does.
- Timestamps of proposals, publications and audit entries are kept
  as they were; they are not shifted with the course start date.
- Automated coverage: `tests/backup_restore_test.php`.

---

## Scenario 20: PowerPoint, OpenDocument and office files inside a ZIP

1. As a student, submit a `.pptx` with speaker notes on some slides.
   Reorder a slide in PowerPoint before saving, so file order and slide
   order differ.
2. Grade it with AI and open **Submission as seen by the AI**.
   **Expected**: one `--- Slide N ---` block per slide in the order
   shown in PowerPoint, with `Speaker notes:` under the slides that
   have them; no slide numbers or dates in the text.
3. Repeat with an `.odt` written in LibreOffice (with a tracked change
   and a comment) and an `.odp`. **Expected**: body text only (no deleted
   text, no comments), slides split for the `.odp`.
4. Submit a `.zip` with `report.docx`, `slides.pptx` and a `.py` file.
   **Expected**: all three appear, each under its own `--- name (type) ---`
   header.

## Scenario 21: Automatic grading when a student submits

1. Edit an assignment, enable AI Grader Pro, tick **Grade automatically
   when a student submits**, save.
2. As a student, submit (press "Submit assignment" if the assignment has
   that button). **Expected**: the AI Grader Pro page shows the
   submission as **Pending**; after the next cron run it shows the AI
   proposal.
3. Review and save the proposal (or publish it). Have the student
   resubmit (reopen the submission if needed). **Expected**: the reviewed
   proposal is not replaced; the teacher can re-grade by hand.
4. Untick the option. **Expected**: new submissions are not graded until
   the teacher clicks **Grade with AI**.

## Scenario 22: Copy criteria from another assignment

1. Edit an assignment without AI Grader Pro, tick **Enable…**, choose an
   assignment in **Copy criteria from** and press **Copy**.
   **Expected**: the form reloads with that assignment's criteria and
   feedback language and a green "Criteria copied from…" notice; nothing
   is saved yet.
2. Save. **Expected**: the criteria are stored for this assignment.
3. As a non-editing teacher of another course, check that assignments
   of that course are not listed.

## Scenario 23: Student names kept out of the AI

1. As a student named e.g. "María José García", submit a document whose
   cover page says "María José García", "MARIA GARCIA" and the student's
   email, named `practica_maria_garcia.docx`, and mentioning the colour
   "rosa" or "blanco" in lower case.
2. Grade with AI and open **Submission as seen by the AI**.
   **Expected**: `[STUDENT]` instead of the name, the email and in the
   file name; the lower-case common words are unchanged. The audit log
   (`mdl_local_aigrader_log.prompt_text`) contains the same replaced text.
3. Untick **Keep student names out of the AI** in the site settings and
   repeat. **Expected**: the name is sent as written.

## Scenario 24: Class report

1. On an assignment with 1-2 graded submissions, click **Class report**
   on the AI Grader Pro page. **Expected**: statistics are shown and a
   notice says at least 3 graded submissions are needed; no
   **Generate report** button.
2. With 3 or more graded submissions (some reviewed), **Expected**:
   counts, average, median and range on the assignment's scale (e.g.
   "73.3 / 100"), grade bands and per-criterion averages, weakest first.
3. Click **Generate report**. **Expected**: after a few seconds, an
   overview, most common gaps with an approximate count and advice,
   what the class did well, topics to reinforce and next steps, in the
   assignment's feedback language, with no student named.
4. Generate again. **Expected**: the new report is shown and the earlier
   one is listed under **Earlier reports** and can be opened.
5. Break the provider (wrong API key, or no network). **Expected**: a
   readable "The AI provider could not write the report: …" message,
   nothing stored.

---

## What is intentionally **not** in this plan

- **Automated coverage**: the flows above that can run without a
  real LLM are also covered by PHPUnit (`tests/`) and Behat
  (`tests/behat/`); this plan is for manual checks on a real site.
- **Performance / load**: the manage page uses `\table_sql` with
  server-side pagination, so the cohort size has no effect on the
  page render. We have not yet stress-tested 10 K-row cohorts
  end-to-end.
- **Multi-tenant SaaS billing**: outside the GPL plugin's scope.
  An optional companion subplugin (`aiprovider_aigrader`) provides
  a managed-endpoint layer; it is distributed separately and not
  part of the Plugin Directory submission.
