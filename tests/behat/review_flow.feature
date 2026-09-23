@local @local_aigrader
Feature: Review an AI grading proposal
  In order to keep humans in the loop for every grade
  As a teacher
  I need to review the AI's proposal and either publish it to the gradebook
  or save my edits as a draft without publishing

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | teacher1 | Teacher   | One      | teacher1@test.com |
      | student1 | Student   | One      | student1@test.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | name    | course | idnumber |
      | assign   | Essay 1 | C1     | assign1  |
    And AI Grader Pro is enabled on the "Essay 1" assignment
    And the following local_aigrader submissions exist:
      | student  | assignment | status      | proposed_grade |
      | student1 | Essay 1    | ai_proposed | 8.0            |
    And I log in as "teacher1"

  # The generated assignment is graded out of 100 (Moodle's default), so the
  # AI's 8/10 proposal is shown and published as 80 / 100.

  @javascript
  Scenario: Teacher approves the AI proposal and publishes the grade
    When I open the AI Grader Pro manage page for "Essay 1"
    Then I should see "Student One"
    And I should see "AI proposed"
    And I should see "80 / 100"
    When I follow "Review"
    Then I should see "Review AI proposal"
    And I should see "Final grade (out of 100)"
    And the field "finalgrade" matches value "80.00"
    When I press "Approve and publish"
    Then I should see "Grade approved and published to the gradebook"
    And I should see "Published"

  @javascript
  Scenario: Teacher edits the grade and saves a draft without publishing
    When I open the AI Grader Pro manage page for "Essay 1"
    And I follow "Review"
    And I set the field "finalgrade" to "65"
    And I press "Save without publishing"
    Then I should see "Saved without publishing"
    And I should see "Teacher reviewed"

  @javascript
  Scenario: A draft can be re-opened, edited again and finally published
    When I open the AI Grader Pro manage page for "Essay 1"
    And I follow "Review"
    And I set the field "finalgrade" to "70"
    And I press "Save without publishing"
    Then I should see "Teacher reviewed"
    When I follow "Review"
    Then the field "finalgrade" matches value "70.00"
    When I set the field "finalgrade" to "75"
    And I press "Approve and publish"
    Then I should see "Grade approved and published to the gradebook"
    And I should see "Published"

  @javascript
  Scenario: With a rubric, the teacher is sent to Moodle's grader instead of publishing
    Given the following "activity" exists:
      | activity                          | assign  |
      | course                            | C1      |
      | name                              | Essay 2 |
      | idnumber                          | assign2 |
      | advancedgradingmethod_submissions | rubric  |
    And AI Grader Pro is enabled on the "Essay 2" assignment
    And the following local_aigrader submissions exist:
      | student  | assignment | status      | proposed_grade |
      | student1 | Essay 2    | ai_proposed | 8.0            |
    And I am on "Course 1" course homepage with editing mode on
    And I go to "Essay 2" advanced grading definition page
    And I set the following fields to these values:
      | Name | Essay rubric |
    And I define the following rubric:
      | Argument | Weak | 1 | Solid | 5 | Excellent | 10 |
    And I press "Save rubric and make it ready"
    When I open the AI Grader Pro manage page for "Essay 2"
    And I follow "Review"
    Then I should see "This assignment uses advanced grading (Rubric)"
    And I should see "Open Moodle's grader for this student"
    And I should see "Reference grade (0-10, not published)"
    And "Approve and publish" "button" should not exist
    And "Save without publishing" "button" should exist

  # NOTE: Out-of-range grade rejection is enforced by both the HTML5
  # input attribute (min / max of the assignment's scale) and the PHP-side
  # check that throws errorgradeoutofrange. The PHP path is unreachable
  # from Behat because Chrome refuses to submit a form whose number input
  # is out of range. The PHP-side validation is covered by PHPUnit in
  # grading_scale_test (is_valid_input), and the gradebook conversion by
  # publisher_test.
