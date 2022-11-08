*** Settings ***
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/Popular_Services.robot
Test Setup      Login And Go To Content Page
Test Teardown   Cleanup and Close Browser	
Force Tags		LANDINGPAGE   POPULAR_SERVICES   ETUSIVU_SPESIFIC

*** Test Cases ***

Two Items
	[Tags]   CRITICAL
	Given User Goes To New LandingPage Site
	When User Creates 2 Popular Services -Link(s)
	And New Landingpage is Submitted
	Then Popular Services Link(s) Should Be Present In Page
	And There Are 2 Popular Services Link(s) In Content
	And All 2 Popular Services Link(s) Work Correctly

Three Items
	[Tags]   CRITICAL
	Given User Goes To New LandingPage Site
	When User Creates 3 Popular Services -Link(s)
	And New Landingpage is Submitted
	Then Popular Services Link(s) Should Be Present In Page
	And There Are 3 Popular Services Link(s) In Content
	And All 3 Popular Services Link(s) Work Correctly
		
*** Keywords ***
User Goes To New LandingPage Site   Go To New LandingPage Site
New Landingpage is Submitted	Submit The New Landingpage

User Creates ${number} Popular Services -Link(s)
	Create Popular Services Link   ${number} 	
	
Popular Services Link(s) Should Be Present In Page
	Page Contains Popular Services Links With Content
	
There Are ${count} Popular Services Link(s) In Content
	Page Should Have Correct Number Of Popular Services Links   ${count}

All ${number} Popular Services Link(s) Work Correctly	
	${number} Popular Services Links Work Correctly