*** Settings ***
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/Content_Cards.robot
Test Setup      Login And Go To Content Page
Test Teardown   Cleanup and Close Browser	
Force Tags		PAGE   CONTENTCARDS



*** Test Cases ***
Small Cards
	[Tags]  CRITICAL
	Given User Goes To New Page -Site
	And User Starts Creating Page With Small Content Card For Link examples Content Page
	And User Adds New ContentCard For Esimerkkisivu Content
	When User Submits The New Page
	Then Layout Should Not Have Changed
	And ContentCards Should Work Correctly
	
Large Cards
	[Tags]  
	Given User Goes To New Page -Site
	And User Starts Creating Page With Large Content Card For Link examples Content Page
	And User Adds New ContentCard For Esimerkkisivu Content
	When User Submits The New Page
	Then Layout Should Not Have Changed
	And ContentCards Should Work Correctly
	
Small Grey Cards
	[Tags]
	Given User Goes To New Page -Site
	And User Starts Creating Page With Small Grey Content Card For Link examples Content Page
	And User Adds New ContentCard For Esimerkkisivu Content
	When User Submits The New Page
	Then Layout Should Not Have Changed
	And ContentCards Should Work Correctly
	
Large Grey Cards
	[Tags]
	Given User Goes To New Page -Site
	And User Starts Creating Page With Large Grey Content Card For Link examples Content Page
	And User Adds New ContentCard For Esimerkkisivu Content
	When User Submits The New Page
	Then Layout Should Not Have Changed
	And ContentCards Should Work Correctly
	
*** Keywords ***
User Starts Creating Page With ${cardsize} Content Card For ${contentname} Content Page
	Create Page With ${cardsize} Cards For ${contentname} Content	
	
User Adds New ContentCard For ${contentname} Content
	Add New ContentCard For ${contentname} Content

ContentCards Should Work Correctly
	Run Keyword And Ignore Error  Accept Cookies
	ContentCards Are Working Correctly
	
User Goes To New Page -Site		Go To New Page Site
User Submits The New Page
	Sleep  1
	Submit The New Page
