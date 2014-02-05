Feature: Verifying
  In order to add input
  From CSV files
  I need to read csv
@javascript
Scenario: From CSV create header
  Given I get csv files
  When I create header