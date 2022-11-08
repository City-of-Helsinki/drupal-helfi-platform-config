*** Settings ***
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/Content_Cards.robot
Test Setup      Login And Go To Content Page
Test Teardown   Cleanup and Close Browser	
Force Tags		LANDINGPAGE   CONTENTCARDS

*** Test Cases ***
Landingpage-Small Cards
	[Tags]  CRITICAL
	Given User Goes To New LandingPage Site
	And User Starts Creating Landingpage With Small Content Card For Link examples Content Page
	And User Adds New ContentCard For Esimerkkisivu Content
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed
	And ContentCards Should Work Correctly
	
Landingpage-Large Cards
	[Tags]  
	Given User Goes To New LandingPage Site
	And User Starts Creating Landingpage With Large Content Card For Link examples Content Page
	And User Adds New ContentCard For Esimerkkisivu Content
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed
	And ContentCards Should Work Correctly
	
Landingpage-Small Grey Cards
	[Tags]
	Given User Goes To New LandingPage Site
	And User Starts Creating Landingpage With Small Grey Content Card For Link examples Content Page
	And User Adds New ContentCard For Esimerkkisivu Content
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed
	And ContentCards Should Work Correctly
	
Landingpage-Large Grey Cards
	[Tags]
	Given User Goes To New LandingPage Site
	And User Starts Creating Landingpage With Large Grey Content Card For Link examples Content Page
	And User Adds New ContentCard For Esimerkkisivu Content
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed
	And ContentCards Should Work Correctly
	
*** Keywords ***
User Starts Creating Landingpage With ${cardsize} Content Card For ${contentname} Content Page
	Create Landingpage With ${cardsize} Cards For ${contentname} Content	
	
User Adds New ContentCard For ${contentname} Content
	Add New ContentCard For ${contentname} Content

ContentCards Should Work Correctly
    Run Keyword And Ignore Error  Accept Cookies
	Wait Until Keyword Succeeds  5x  200ms  ContentCards Are Working Correctly
	
User Goes To New LandingPage Site   Go To New LandingPage Site
New Landingpage is Submitted	Submit The New Landingpage
 