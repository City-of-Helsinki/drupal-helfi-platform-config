*** Settings ***
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/List_Of_Links.robot
Test Setup      Login And Go To Content Page
Test Teardown   Cleanup and Close Browser	
Force Tags		PAGE   LISTOFLINKS

*** Test Cases ***
Without Picture
	[Tags]
	Given User Goes To New Page -Site
	And User Creates List Of Links Without Picture
	And User Adds Second Link For Esimerkkisivu Content
	When User Submits The New Page
	Then Layout Should Not Have Changed
	And List Of Links Should Work Correctly

With Picture
	[Tags]  CRITICAL
	Given User Goes To New Page -Site
	And User Creates List Of Links With Picture
	And User Adds Second Link For Esimerkkisivu Content
	When User Submits The New Page
	Then Layout Should Not Have Changed
	And List Of Links Should Work Correctly

Without Picture And Description
	[Tags] 
	Given User Goes To New Page -Site
	And User Creates List Of Links Without Picture And Description
	And User Adds Second Link For Esimerkkisivu Content
	When User Submits The New Page
	Then Layout Should Not Have Changed
	And List Of Links Should Work Correctly
	
*** Keywords ***
User Creates List Of Links ${style}
	Create Page With List Of Links ${style}

User Goes To New Page -Site		Go To New Page Site
User Submits The New Page
	Submit The New Page

User Adds Second Link For ${content} Content
	Add Second Link For Content

List Of Links Should Work Correctly
	List Of Links Is Working Correctly
