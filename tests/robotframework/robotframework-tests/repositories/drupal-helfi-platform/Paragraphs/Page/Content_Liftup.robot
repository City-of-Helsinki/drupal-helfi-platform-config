*** Settings ***
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/Content_Liftup.robot
Documentation	For These testcases to work units with names Lippulaivan kirjasto and Otaniemen kirjasto should be imported.
...				(Should appear when importing 50 tpr_units). Also tpr_config -module should be enabled. 
Test Setup      Login And Go To Content Page
Test Teardown   Cleanup and Close Browser	
Force Tags		PAGE   CONTENTLIFTUP



*** Test Cases ***
One Unit
	[Tags]   CRITICAL
	Given User Publishes Unit With Name Lippulaivan kirjasto  
	And User Goes To New Page -Site
	And User Adds Content Liftup
	When User Submits The New Page	
	Then Layout Should Not Have Changed
	And ContentLiftup Should Work Correctly

Two Units
	[Tags]
	Given User Publishes Unit With Name Lippulaivan kirjasto
	And User Publishes Unit With Name Otaniemen kirjasto  
	And User Goes To New Page -Site
	And User Adds Content Liftup
	When User Submits The New Page	
	Then Layout Should Not Have Changed
	And ContentLiftup Should Work Correctly For Two Units	

	
*** Keywords ***
User Goes To New Page -Site		Go To New Page Site
User Submits The New Page
	Submit The New Page

User Adds Content Liftup 
	Input Non-paragraph Related Content   Page
	Add Content Liftup For Unit   Page   Lippulaivan Kirjasto
	Run Keyword If  '${TEST NAME}'=='Two Units'  Add Content Liftup For Unit   Page   Otaniemen Kirjasto

	
User Publishes Unit With Name ${name}
	Publish Unit With Name   ${name}

ContentLiftup Should Work Correctly
	ContentLiftup Is Working Correctly For One Unit

ContentLiftup Should Work Correctly For Two Units
	ContentLiftup Is Working Correctly For Two Units

Layout Should Not Have Changed
	Run Keyword And Ignore Error  Accept Cookies
	Content_Liftup.Take Screenshot Of Content
	Compare Two Pictures   1