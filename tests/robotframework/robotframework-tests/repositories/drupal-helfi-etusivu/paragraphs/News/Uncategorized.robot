*** Settings ***
Documentation   Mainly contains some cases which do not fall under any parent paragraph like Hero, Columns. Some text,
...				links and pictures still are supported and could be tested here.
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/Uncategorized.robot
Test Setup      Login And Go To Content Page
Test Teardown   Cleanup and Close Browser	
Force Tags		NEWS-ITEM   UNCATEGORIZED   ETUSIVU_SPESIFIC    

*** Test Cases ***

Only Text
	[Tags]
	Given User Goes To New News-Item Site
	And User Starts Creating a New Page With Text Content  
	When New News-Item is Submitted
	Then Layout Should Not Have Changed
	And Text Content Is Present

Only Picture
	[Tags]
	Given User Goes To New News-Item Site
	And User Starts Creating a New Page With Picture Content  
	When New News-Item is Submitted
	Then Layout Should Not Have Changed
	And Picture Content Is Present

Text And Picture
	[Tags]
	Given User Goes To New News-Item Site
	And User Starts Creating a New Page With Mixed Content  
	When New News-Item is Submitted
	Then Layout Should Not Have Changed
	And Text And Picture Content Is Present

	
*** Keywords ***
User Goes To New News-Item Site   Go To New News-Item Site
New News-Item is Submitted	Submit The New News-Item
	
User Starts Creating a New Page With ${content} Content
	Input Etusivu Instance Spesific Content
	Start Creating a New Page With ${content} Content
	
Layout Should Not Have Changed
	Run Keyword And Ignore Error  Accept Cookies	
	Uncategorized.Take Screenshot Of Content
	Compare Two Pictures
	
Text Content Is Present
	Element Should Be Visible  css:.component.component--paragraph-text   timeout=3
	
Picture Content Is Present
	Element Should Be Visible  css:.component.component--image   timeout=3
	
Text And Picture Content Is Present
	Element Should Be Visible  css:.component.component--paragraph-text   timeout=3
	Element Should Be Visible  css:.component.component--image   timeout=3