Feature: Verifying
  In order to verify from
  From CSV files
  I need to read csv
@javascript
Scenario: From CSV Data verify form
  Given I get csv files
  When I verify data
  Then I should check result