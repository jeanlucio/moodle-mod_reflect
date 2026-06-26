@mod @mod_reflect
Feature: Reflect activity student flow
  In order to self-assess
  As a student
  I need to be able to respond to a reflect activity and submit it

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  @javascript
  Scenario: Add a reflect activity, questions and submit a response
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Reflect" to course section "1" and I fill the form with:
      | Activity name | My Reflect          |
      | Description   | Reflect description |
      | Grade method  | Distribute total grade equally |
    And I click on "Save and display" "button"
    Then I should see "No questions have been added yet."
    
    # Add first question
    And I click on "Add question" "button"
    # Wait for the modal animation (we can just verify a string in it)
    And I should see "Reflection question" in the "Add question" "dialogue"
    And I set the following fields to these values:
      | Reflection question | How confident are you? |
      | Response type       | Numeric (0–100 slider) |
      | Maximum grade       | 10                     |
    And I click on "Save" "button" in the "Add question" "dialogue"
    
    # The question should appear inline.
    Then I should see "How confident are you?"
    And I log out

    # Student answers the reflect activity.
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "My Reflect"
    Then I should see "How confident are you?"
    
    # Click Submit to finalize.
    And I click on "Submit" "button"
    Then I should see "Responses submitted successfully."
    
    # Teacher views the report.
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "My Reflect"
    And I click on "View report" "link"
    Then I should see "Student 1"
