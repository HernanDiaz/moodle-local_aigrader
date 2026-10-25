# AI Grader Pro user guide

This guide takes you from an empty Moodle site to a published,
AI-assisted grade and a class report. It is written so that someone who
has never used the plugin, for example a reviewer, can install and test
it without help. Expect about 30 minutes, plus the time to get an API key
from an AI provider.

Contents:

1. [Requirements](#1-requirements)
2. [Installation](#2-installation)
3. [Configure an AI provider](#3-configure-an-ai-provider)
4. [Plugin settings](#4-plugin-settings)
5. [Set up an assignment and its criteria](#5-set-up-an-assignment-and-its-criteria)
6. [Grade submissions with AI](#6-grade-submissions-with-ai)
7. [Review and publish](#7-review-and-publish)
8. [Class report](#8-class-report)
9. [Privacy and audit trail](#9-privacy-and-audit-trail)
10. [Complete example, step by step](#10-complete-example-step-by-step)
11. [Troubleshooting](#11-troubleshooting)

## 1. Requirements

- Moodle 4.5 LTS or later. The plugin is tested in CI on Moodle 4.5,
  5.0, 5.1, 5.2 and 5.3, with PostgreSQL and MariaDB.
- The PHP version your Moodle needs (8.1 or later on Moodle 4.5).
- An account with an AI provider supported by Moodle's AI subsystem and
  its API key: OpenAI, Azure OpenAI, or any OpenAI-compatible service
  such as Groq (which has a free tier). Newer Moodle versions add more
  providers; any provider that offers the **Generate text** action works.
- Cron running, if you want automatic grading on submission or bulk
  grading of more than 5 submissions (both run as background tasks).
  Grading a single submission, or up to 5 at once, runs immediately and
  does not need cron.

No Composer step is needed: the PDF library the plugin uses is bundled
in `thirdparty/vendor/`.

## 2. Installation

The plugin folder must end up as `local/aigrader` inside your Moodle
code (on Moodle 5.1 and later, `public/local/aigrader`).

**From a ZIP, through the web interface** (Moodle Marketplace ZIP or the
ZIP of a GitHub release):

1. Log in as an administrator.
2. Go to *Site administration → Plugins → Install plugins*.
3. Upload the ZIP (plugin type: *Local plugin*) and follow the prompts:
   Moodle validates the package before installing it.
4. Click *Upgrade Moodle database now*.

**From a ZIP, by hand:** unzip it into `local/`, make sure the resulting
folder is named `aigrader` (rename it if needed), then visit
`/admin/index.php` as an administrator.

**From git:**

```bash
cd /path/to/moodle/local        # public/local on Moodle 5.1+
git clone https://github.com/HernanDiaz/moodle-local_aigrader.git aigrader
```

Then visit `/admin/index.php` as an administrator and upgrade. To try a
specific release, check out its tag (for example `git checkout v1.2.0`).

## 3. Configure an AI provider

AI Grader Pro never stores API keys. It sends its requests through
Moodle's AI subsystem, so the provider is configured once, in Moodle.

**Moodle 4.5**

1. *Site administration → General → AI → AI providers*.
2. Open the settings of **OpenAI API provider** (or Azure OpenAI), paste
   the API key and save.
3. In the provider's actions, make sure **Generate text** is enabled and
   open its settings: choose the **AI model** and keep the default
   **API endpoint** for OpenAI.
4. Enable the provider on the *AI providers* page.

**Moodle 5.0 and later**

1. *Site administration → General → AI → AI providers → Create a new
   provider instance*.
2. Choose the provider plugin, give the instance a name, paste the API
   key and click *Create instance*.
3. Make sure the instance is enabled and that its **Generate text**
   action is enabled; in the action settings choose the model (and the
   endpoint, for an OpenAI-compatible service).

**Using Groq (free tier) through the OpenAI provider.** Groq offers an
OpenAI-compatible API, so the OpenAI provider works with it: use your
Groq API key, set the **API endpoint** of the *Generate text* action to
`https://api.groq.com/openai/v1/chat/completions`, and set the **AI
model** to a chat model available in your Groq account (for example
`openai/gpt-oss-120b`). The free tier has a low tokens-per-minute limit;
when it is hit, AI Grader Pro shows a "Provider rate limit hit" banner
and the background tasks retry on their own.

## 4. Plugin settings

*Site administration → Plugins → Local plugins → AI Grader Pro*. The
defaults are fine for a first test.

- **Enable plugin**: site-wide switch.
- **Keep student names out of the AI** (on by default): before anything
  is sent, the student's name, email address, username and ID number are
  replaced with `[STUDENT]`, also in file names.
- **Default system prompt**: an optional instruction added to every
  grading request, for example your institution's tone.
- **Auto-import from grading rubric**: when an assignment uses Moodle's
  rubric, its text pre-fills the AI Grader Pro criteria.
- **Where AI Grader Pro is available**: limit the plugin to some course
  categories and/or course short names. Empty means every course.

Teachers need the capabilities `local/aigrader:configure` (set up an
assignment) and `local/aigrader:use` (grade and review). Both are given
to editing teachers and managers by default.

## 5. Set up an assignment and its criteria

1. Create or edit an *Assignment* activity. Any submission type works:
   online text, file submissions, or both.
2. In the settings form, expand the **AI Grader Pro** section.
3. Tick **Enable AI-assisted grading for this assignment**.
4. Write the **Evaluation criteria** in plain language, as you would brief
   a teaching assistant: what must be present, what each criterion is
   worth, and what lowers the grade. Or use **Copy criteria from** to
   start from another assignment's criteria.
5. Optional: **Feedback language** (by default the course language) and
   **Grade automatically when a student submits**.
6. Save. The assignment now has two new entries in its *More* menu:
   **AI Grader Pro** (the grading panel) and **Class report**.

The grade the AI proposes is converted to the assignment's own grade
type: points, a Moodle scale, or "No grade" (feedback only). If the
assignment uses advanced grading (a rubric or a marking guide), the
proposal is shown as a reference and the grade is entered in Moodle's
grader, because the plugin cannot fill in rubrics yet.

## 6. Grade submissions with AI

Open the assignment's **AI Grader Pro** panel. It lists the submissions
with their status: *No AI grading yet*, *Pending*, *AI proposed*,
*Teacher reviewed*, *Published*, *Error* or *Unsupported format*. The
status chips at the top filter the list.

- **One submission**: click **Grade with AI** on its row. The proposal
  appears after the provider answers (usually a few seconds).
- **Several**: tick the rows, choose **Grade with AI** under *With
  selected* and confirm. Up to 5 are graded at once; more are queued for
  cron and the page refreshes while they run.
- **Automatically**: with *Grade automatically when a student submits*,
  each submission is queued as soon as it arrives, so the proposal is
  waiting when you open the panel. It is still only a proposal.

Supported content: online text; PDF; Word (.docx); PowerPoint (.pptx);
OpenDocument text and presentations (.odt, .odp); Markdown and plain
text; Jupyter notebooks (.ipynb); source code (Python, Java, C, C++,
C#, JavaScript, TypeScript, SQL, PHP, Ruby, Go, Rust, Kotlin, Swift,
shell, HTML, CSS and more); and ZIP files containing any of these.
A submission made only of files the plugin cannot read is marked as
*Unsupported format*. Scanned PDFs without a text layer give no text to
grade.

## 7. Review and publish

Click **Review** on a row with status *AI proposed*. The review page
shows:

- **Student submission** and **Attached files**, and **Submission as seen
  by the AI**: the exact text the model received, with notes about the
  extraction. Check it if a proposal looks odd.
- **Criterion scores (from AI, for context)**: one score per criterion.
- **Proposed grade and feedback (editable)**: final grade, strengths,
  improvements and a justification. Everything is visible to the student
  once published, and everything can be edited.

Then:

- **Approve and publish** writes the grade and feedback to the gradebook
  through Moodle's Assignment API. The student is notified according to
  the assignment's settings, exactly as with manual grading.
- **Save without publishing** keeps your edits as a draft (status
  *Teacher reviewed*). Nothing reaches the gradebook.

From the panel you can also select several proposals and use **Publish
proposed grade** to publish them as they are, after a confirmation
screen.

If a request fails, the row shows *Error* and a banner explains the
cause (rate limit, authentication, network, unreadable answer...) and
what to do. Temporary errors are retried automatically; **Retry now**
calls the model again at once.

## 8. Class report

Open **Class report** from the assignment's *More* menu (or from the
panel) once at least 3 submissions have a proposal.

- **Class statistics** are computed without AI: number of submissions
  graded and reviewed, average, median, range, grade distribution and the
  average per criterion, weakest first.
- **Generate report** makes one AI call that receives only the grades
  and the feedback (with names replaced), never the submissions. It
  writes an executive summary: what the class did well, the most common
  gaps, topics to reinforce in class and suggested next steps. Earlier
  reports are kept, and **Update report** writes a new one.

The summary is written by AI from the class's feedback: read it before
sharing it.

## 9. Privacy and audit trail

- Every AI call and every teacher decision is stored in the plugin's
  audit log: the prompt as sent (with names replaced), the model and
  provider, token counts, the AI's answer and the teacher's edits. The
  review page shows when and by whom each proposal was made.
- The plugin implements Moodle's Privacy API, so its data is included in
  data exports and deletions requested through *Site administration →
  Users → Privacy and policies*.
- The site administrator is responsible for the agreement with the AI
  provider (where data is processed, retention). Names written in other
  forms, such as initials or nicknames, are not detected by the name
  replacement.

## 10. Complete example, step by step

This walk-through uses fictional users and online-text submissions, so
you only need a test site and an API key.

**a. Users and course** (as administrator)

1. Create a course "AI Grader test" (short name `AIGTEST`).
2. Create one teacher and three students, for example Teacher One,
   Student A, Student B and Student C, and enrol them in the course
   (*Participants → Enrol users*) with the roles Teacher and Student.

**b. Provider and plugin**

3. Configure a provider as in [section 3](#3-configure-an-ai-provider).
4. Leave the plugin settings at their defaults.

**c. Assignment** (as the teacher)

5. Add an *Assignment* "Short essay: renewable energy" with submission
   type *Online text*, maximum grade 10.
6. In **AI Grader Pro**, tick *Enable AI-assisted grading for this
   assignment* and paste these criteria:

   ```
   Short essay (about 150 words) on the pros and cons of renewable energy.
   1. Thesis (2 points): a clear position in the first paragraph.
   2. Arguments (4 points): at least two advantages and one disadvantage,
      each explained, not just listed.
   3. Evidence (2 points): at least one concrete example or figure.
   4. Writing (2 points): clear structure and correct spelling.
   An essay under 80 words cannot get more than 4 points.
   ```

7. Save and return to course.

**d. Submissions** (log in as each student, or use *Log in as*)

8. Submit three essays of different quality. For example:
   - Student A: a complete essay with a thesis, two advantages (lower
     emissions, cheaper electricity over time), one disadvantage
     (intermittency) and a figure.
   - Student B: a list of advantages with no explanation and no example.
   - Student C: two sentences.

**e. Grading** (as the teacher)

9. Open the assignment → *More* → **AI Grader Pro**. The three
   submissions show *No AI grading yet*.
10. Tick all three, choose **Grade with AI** under *With selected*, and
    confirm. After a few seconds they show *AI proposed*.
11. Click **Review** on Student A. Check *Submission as seen by the AI*,
    the criterion scores and the proposed feedback. Edit one of the
    strengths and click **Approve and publish**. The row shows
    *Published*, and the grade appears in the course gradebook and in
    Moodle's own grading table for the assignment.
12. Review Student B, change the grade and click **Save without
    publishing**: the row shows *Teacher reviewed* and the gradebook is
    unchanged.
13. Select Student B and Student C and use **Publish proposed grade**:
    the confirmation screen warns that the grades are published as they
    are.

**f. Class report**

14. Open **Class report**. The statistics are shown at once. Click
    **Generate report** to get the AI summary of the class.

**g. What the student sees**

15. Log in as Student A and open the assignment: the grade and the
    feedback (strengths, improvements, justification) are shown as for
    any graded assignment.

For a fuller checklist (permissions, automatic grading, errors, backup
and restore, other file formats), see [TESTPLAN.md](../TESTPLAN.md).

## 11. Troubleshooting

| Symptom | Likely cause and fix |
|---|---|
| No **AI Grader Pro** entry in the assignment's *More* menu | AI-assisted grading is not enabled in the assignment settings, the plugin is disabled, the course is outside *Where AI Grader Pro is available*, or you lack `local/aigrader:use`. |
| "Provider rate limit hit" | The provider's per-minute limit (common on free tiers). Wait; background tasks retry on their own. |
| Authentication error | Wrong or expired API key in the AI provider settings. |
| Rows stay in *Pending* | Cron is not running (needed for more than 5 submissions at once and for automatic grading). |
| *Unsupported format* | None of the submitted files is in a supported format; ask the student for a supported file. |
| The AI says the submission is empty | Check *Submission as seen by the AI*: a scanned PDF has no text layer. |
| The grade cannot be published from the review page | The assignment uses a rubric or marking guide; use the proposal as a reference in Moodle's grader. |
