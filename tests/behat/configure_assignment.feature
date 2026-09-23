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
  Scenario: Validation requires evaluation criteria when AI grading is enabled
    Given I am logged in as "teacher1"
    When I am on the "Essay 1" "assign activity editing" page
    And I expand all fieldsets
    And I set the field "Enable AI-assisted grading for this assignment" to "1"
    And I press "Save and return to course"
    Then I should see "Evaluation criteria are required"
