*** Settings ***
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/Target_Group_Links.robot
Test Setup      Login And Go To Content Page
Test Teardown   Cleanup and Close Browser	
Force Tags		LANDINGPAGE   TARGET_GROUP_LINKS   ETUSIVU_SPESIFIC

*** Test Cases ***

One Link
	[Tags]   CRITICAL
	Given User Goes To New LandingPage Site
	When User Creates 1 Target Group Link(s)
	And New Landingpage is Submitted
	Then Target Group List Should Be Present In Page
	And There Are 1 Target Group List Links In Content
	And All 1 Target Link(s) Work Correctly
	

Four Links
	[Tags]   CRITICAL
	Given User Goes To New LandingPage Site
	When User Creates 4 Target Group Link(s)
	And New Landingpage is Submitted
	Then Target Group List Should Be Present In Page
	And There Are 4 Target Group List Links In Content
	And All 4 Target Link(s) Work Correctly
	
*** Keywords ***
User Goes To New LandingPage Site   Go To New LandingPage Site
New Landingpage is Submitted	Submit The New Landingpage

User Creates ${number} Target Group Link(s)
	Create Target Group Link   ${number} 	
	
Target Group List Should Be Present In Page
	Page Contains Target Group List With Content
	
There Are ${count} Target Group List Links In Content
	Page Should Have Correct Number Of Target Links   ${count}

All ${number} Target Link(s) Work Correctly	
	${number} Target Links Work Correctly