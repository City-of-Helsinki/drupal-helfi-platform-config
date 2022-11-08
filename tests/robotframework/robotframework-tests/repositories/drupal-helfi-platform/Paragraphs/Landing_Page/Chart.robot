*** Settings ***
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/Chart.robot
Test Setup      Login And Go To Content Page
Test Teardown   Cleanup and Close Browser	
Force Tags		LANDINGPAGE   CHART

*** Test Cases ***
Landingpage-One Chart
	[Tags]  CRITICAL
	Given User Goes To New Landingpage -Site
	And User Starts Creating New Chart
	When User Submits The New Landingpage
	Then Layout Should Not Have Changed
	
	
*** Keywords ***
User Goes To New Landingpage -Site		Go To New LandingPage Site
User Submits The New Landingpage
	Submit The New Landingpage
	
User Starts Creating New Chart
	Create Chart   Landingpage