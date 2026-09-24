@local @local_aigrader
Feature: Class report of an assignment
  In order to see how my class did on an assignment
  As a teacher
  I need a class report built from the AI Grader Pro proposals

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | teacher1 | Teacher   | One      | teacher1@test.com |
      | student1 | Student   | One      | student1@test.com |
      | student2 | Student   | Two      | student2@test.com |
      | student3 | Student   | Three    | student3@test.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
    And the following "activities" exist:
      | activity | name    | course | idnumber | grade |
      | assign   | Essay 1 | C1     | assign1  | 100   |
    And AI Grader Pro is enabled on the "Essay 1" assignment

  Scenario: Statistics are shown and the report can be generated with enough graded submissions
    Given the following local_aigrader submissions exist:
      | student  | assignment | status           | proposed_grade | final_grade |
      | student1 | Essay 1    | ai_proposed      | 8.0            |             |
      | student2 | Essay 1    | teacher_reviewed | 6.0            | 5.0         |
      | student3 | Essay 1    | published        | 9.0            | 9.0         |
    When I am on the "Essay 1" "assign activity" page logged in as "teacher1"
    And I navigate to "Class report" in current page administration
    Then I should see "Class report: Essay 1"
    And I should see "3 submissions graded with AI Grader Pro, 2 of them reviewed by the teacher."
    And I should see "73.3 / 100"
    And I should see "Grade distribution"
    And "Generate report" "button" should exist

  Scenario: The report cannot be generated with too few graded submissions
    Given the following local_aigrader submissions exist:
      | student  | assignment | status      | proposed_grade |
      | student1 | Essay 1    | ai_proposed | 8.0            |
    When I am on the "Essay 1" "assign activity" page logged in as "teacher1"
    And I navigate to "Class report" in current page administration
    Then I should see "At least 3 submissions must be graded with AI Grader Pro"
    And "Generate report" "button" should not exist
