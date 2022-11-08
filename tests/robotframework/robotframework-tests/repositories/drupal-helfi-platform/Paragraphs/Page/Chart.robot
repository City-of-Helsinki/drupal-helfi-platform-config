*** Settings ***
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/Chart.robot
Test Setup      Login And Go To Content Page
Test Teardown   Cleanup and Close Browser	
Force Tags		PAGE   CHART

*** Test Cases ***
One Chart
	[Tags]  CRITICAL
	Given User Goes To New Page -Site
	And User Starts Creating New Chart
	When User Submits The New Page
	Then Layout Should Not Have Changed
	And Chart Is Present
	
	
*** Keywords ***
User Goes To New Page -Site		Go To New Page Site
User Submits The New Page
	Submit The New Page
	
User Starts Creating New Chart
	Create Chart   Page
	
Chart Is Present
	chart Is Present In Page