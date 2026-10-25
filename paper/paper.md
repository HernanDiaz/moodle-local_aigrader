---
title: 'AI Grader Pro: teacher-in-the-loop, provider-agnostic AI-assisted grading for Moodle assignments'
tags:
  - Moodle
  - educational technology
  - automated assessment
  - large language models
  - PHP
  - privacy
authors:
  - name: Hernán Díaz Rodríguez
    orcid: 0000-0000-0000-0000  # TODO: ORCID
    affiliation: 1
affiliations:
  - name: Department of Computer Science, University of Oviedo, Spain
    index: 1
date: 1 December 2026  # TODO: fecha de envío
bibliography: paper.bib
---

# Summary

AI Grader Pro is a free, open-source plugin (GPL-3.0-or-later) for the Moodle learning management system that adds AI-assisted grading to the standard *Assignment* activity. For each submission, a large language model (LLM) proposes a grade per assessment criterion and a structured piece of feedback; the teacher reviews, edits and decides. Nothing reaches the gradebook without an explicit teacher action. Proposals can also be prepared automatically as soon as a student submits (off by default), but publishing a grade always takes a teacher action.

The plugin is built on Moodle's native AI subsystem (Moodle 4.5 and later), so institutions can use whichever LLM provider they have already configured (OpenAI, Azure OpenAI, Groq and others) without vendor lock-in or additional credentials. It handles essays and documents (PDF, Word .docx, PowerPoint .pptx, OpenDocument text and presentations, Markdown and plain text), source code in 24 file types (from Python, Java, C/C++ and C# to SQL, shell scripts and web languages), Jupyter notebooks and ZIP projects. By default, student identifiers are replaced by placeholders before every call to the model, every proposal and decision is written to an audit trail, and a class report offers anonymised statistics for the teacher. The interface is available in English, Spanish, Brazilian Portuguese, Catalan and French.

AI Grader Pro is distributed through the Moodle Marketplace, Moodle's official plugin directory, and, at the time of writing, is installed on more than fifty Moodle sites. <!-- TODO: actualizar la cifra del Marketplace el día del envío -->

# Statement of need

Frequent, low-stakes assignments with timely feedback improve learning, but in large first-year courses they are rarely feasible: a weekly deliverable in a course with several hundred students produces a grading load that no instructor can absorb, so such tasks are either not set at all or are returned without comments. Generic chatbots are not a solution in an institutional setting: they require copying student work into third-party services, give no control over criteria or consistency, leave no record of what was proposed and what the teacher decided, and raise obvious data-protection problems.

Existing Moodle tools cover only part of this gap. Autograders such as CodeRunner [@TODO] or VPL [@TODO] evaluate programs against tests, not open-ended written work, and do not give rubric-based feedback; commercial AI grading services sit outside the LMS and outside the institution's data governance. AI Grader Pro was designed for the remaining case: criterion-based assessment of written and programming work, inside Moodle, with the teacher retaining the final decision and the institution retaining control of the model provider and of student data.

The design choices follow from that goal. *Teacher-in-the-loop by default* keeps the pedagogical responsibility where it belongs and makes the tool usable under the transparency and human-oversight expectations of the EU Artificial Intelligence Act [@EUAIAct2024]. *Provider-agnostic integration* through Moodle's AI subsystem lets each institution apply its own policy on which models may be used. *Anonymisation before every call* and *an audit trail of proposals and decisions* address the requirements of the General Data Protection Regulation [@GDPR2016] and make the grading process inspectable afterwards. Per-criterion proposals, rather than a single score, map directly onto the rubrics teachers already use.

The intended users are instructors who want to give feedback on weekly or formative deliverables that would otherwise go uncorrected, and Moodle administrators who need an AI tool they can deploy under institutional governance. The plugin is used in the author's own first-year courses at the University of Oviedo <!-- TODO: añadir asignaturas, número de estudiantes y entregas corregidas cuando haya datos del piloto --> and by other institutions through the Moodle Marketplace.

# Design and implementation

AI Grader Pro is a Moodle *local* plugin written in PHP 8.1 and later, compatible with Moodle 4.5 to 5.3. It adds its settings to the Assignment activity's settings form, writes grades through the Assignment API (so the gradebook, notifications and completion behave as with manual grading), and reaches the model through the AI subsystem's provider abstraction. Submissions are converted to text (documents, notebooks and archives are unpacked and normalised), identifiers are replaced by placeholders, and a prompt built from the teacher's criteria is sent to the configured provider. Responses are parsed into per-criterion grades and feedback, stored with the audit record, and presented in a review interface where the teacher can edit and publish each proposal, keep it as an unpublished draft, or publish several unedited proposals at once. Errors are classified and can be retried, and a class report summarises outcomes without exposing individual students.

The project ships with 144 PHPUnit tests and 18 Behat acceptance scenarios, and is checked by a continuous-integration matrix across Moodle versions, PHP 8.1 to 8.4, MariaDB and PostgreSQL. Documentation, a test plan, a security policy and contribution guidelines are included in the repository.

# Acknowledgements

<!-- TODO: agradecimientos y declaración de financiación (obligatoria en JOSE; si no hay, decirlo). -->
This work received no specific funding.

# References
