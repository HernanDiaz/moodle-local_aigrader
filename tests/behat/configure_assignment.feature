@local @local_aigrader
Feature: Configure AI Grader Pro on an assignment
  In order to use AI-assisted grading on student submissions
  As a teacher
  I need to enable AI Grader Pro and write evaluation criteria on the assignment edit form

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | teacher1 | Teacher   | One      | teacher1@test.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name    | course | idnumber |
      | assign   | Essay 1 | C1     | assign1  |

  @javascript
  Scenario: Teacher enables AI Grader Pro with criteria on an assignment
    Given I am logged in as "teacher1"
    When I am on the "Essay 1" "assign activity editing" page
    And I expand all fieldsets
    And I set the field "Enable AI-assisted grading for this assignment" to "1"
    And I set the field "Evaluation criteria" to "Evaluate clarity of thesis, structure and academic language."
    And I press "Save and return to course"
    Then I should see "Essay 1"

  @javascript
  Scenario: AI Grader Pro settings are hidden in courses the administrator did not allow
    Given the following config values are set as admin:
      | config         | value        | plugin         |
      | allowedcourses | OTHER-COURSE | local_aigrader |
    And I am logged in as "teacher1"
    When I am on the "Essay 1" "assign activity editing" page
    And I expand all fieldsets
    Then I should not see "Enable AI-assisted grading for this assignment"

  @javascript
  Scenario: Teacher copies the criteria of another assignment
    Given the following "activities" exist:
      | activity | name    | course | idnumber |
      | assign   | Essay 2 | C1     | assign2  |
    And AI Grader Pro is enabled on the "Essay 1" assignment with criteria "Reuse me: thesis 50%, evidence 50%."
    And I am logged in as "teacher1"
    When I am on the "Essay 2" "assign activity editing" page
    And I expand all fieldsets
    And I set the field "Enable AI-assisted grading for this assignment" to "1"
    And I set the field "aigrader_copyfrom" to "C1: Essay 1"
    And I press "Copy"
    And I expand all fieldsets
    Then I should see "Criteria copied from"
    And the field "Evaluation criteria" matches value "Reuse me: thesis 50%, evidence 50%."
    And I press "Save and return to course"
    And I am on the "Essay 2" "assign activity editing" page
    And I expand all fieldsets
    And the field "Evaluation criteria" matches value "Reuse me: thesis 50%, evidence 50%."

  @javascript
  Scenario: With automatic grading on, a student's submission is queued for the AI
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | student1 | Student   | One      | student1@test.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "activities" exist:
      | activity | name    | course | idnumber | assignsubmission_onlinetext_enabled | submissiondrafts |
      | assign   | Essay 3 | C1     | assign3  | 1                                   | 0                |
    And I am logged in as "teacher1"
    And I am on the "Essay 3" "assign activity editing" page
    And I expand all fieldsets
    And I set the following fields to these values:
      | Enable AI-assisted grading for this assignment | 1                      |
      | Evaluation criteria                            | Evaluate the argument. |
      | Grade automatically when a student submits     | 1                      |
    And I press "Save and return to course"
    And I log out
    And I am on the "Essay 3" "assign activity" page logged in as "student1"
    And I press "Add submission"
    And I set the field "Online text" to "My essay about artificial intelligence."
    And I press "Save changes"
    And I log out
    When I am logged in as "teacher1"
    And I open the AI Grader Pro manage page for "Essay 3"
    Then I should see "Pending"

  @javascript
  Scenario: Validation requires evaluation criteria when AI grading is enabled
    Given I am logged in as "teacher1"
    When I am on the "Essay 1" "assign activity editing" page
    And I expand all fieldsets
    And I set the field "Enable AI-assisted grading for this assignment" to "1"
    And I press "Save and return to course"
    Then I should see "Evaluation criteria are required"
