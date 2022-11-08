*** Settings ***
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/Current_Right_Now.robot
Test Setup      Login And Go To Content Page
Test Teardown   Cleanup and Close Browser	
Force Tags		LANDINGPAGE   CURRENT_RIGHT_NOW   ETUSIVU_SPESIFIC

*** Test Cases ***

One Item
	[Tags]   CRITICAL
	Given User Goes To New LandingPage Site
	When User Creates 1 Current Right Now -Link(s)
	And New Landingpage is Submitted
	Then Current Right Now Link(s) Should Be Present In Page
	And There Are 1 Current Right Now Link(s) In Content
	And All 1 Current Right Now Link(s) Work Correctly

Four Items
	[Tags]   CRITICAL
	Given User Goes To New LandingPage Site
	When User Creates 4 Current Right Now -Link(s)
	And New Landingpage is Submitted
	Then Current Right Now Link(s) Should Be Present In Page
	And There Are 4 Current Right Now Link(s) In Content
	And All 4 Current Right Now Link(s) Work Correctly
		
*** Keywords ***
User Goes To New LandingPage Site   Go To New LandingPage Site
New Landingpage is Submitted	Submit The New Landingpage

User Creates ${number} Current Right Now -Link(s)
	Create Current Right Now Link   ${number} 	
	
Current Right Now Link(s) Should Be Present In Page
	Page Contains Current Right Now Links With Content
	
There Are ${count} Current Right Now Link(s) In Content
	Page Should Have Correct Number Of Current Right Now Links   ${count}

All ${number} Current Right Now Link(s) Work Correctly	
	${number} Current Right Now Links Work Correctly