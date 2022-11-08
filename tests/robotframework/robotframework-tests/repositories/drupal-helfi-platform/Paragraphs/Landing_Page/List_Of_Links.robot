*** Settings ***
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/List_Of_Links.robot
Test Setup      Login And Go To Content Page
Test Teardown   Cleanup and Close Browser	
Force Tags		LANDINGPAGE   LISTOFLINKS

*** Test Cases ***
Landingpage-Without Picture
	[Tags]
	Given User Goes To New LandingPage Site
	And User Creates List Of Links Without Picture
	And User Adds Second Link For Esimerkkisivu Content
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed
	And List Of Links Should Work Correctly

Landingpage-With Picture
	[Tags]  CRITICAL
	Given User Goes To New LandingPage Site
	And User Creates List Of Links With Picture
	And User Adds Second Link For Esimerkkisivu Content
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed
	And List Of Links Should Work Correctly

Landingpage-Without Picture And Description
	[Tags]
	Given User Goes To New LandingPage Site
	And User Creates List Of Links Without Picture And Description
	And User Adds Second Link For Esimerkkisivu Content
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed
	And List Of Links Should Work Correctly
	
*** Keywords ***
User Creates List Of Links ${style}
	Create Landingpage With List Of Links ${style}

User Goes To New LandingPage Site   Go To New LandingPage Site
New Landingpage is Submitted	Submit The New Landingpage

User Adds Second Link For ${content} Content
	Add Second Link For Content

List Of Links Should Work Correctly
	List Of Links Is Working Correctly
