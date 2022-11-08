*** Settings ***
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/Unit_Search.robot
Test Setup      Login And Go To Content Page
Test Teardown   Cleanup and Close Browser	
Force Tags		PAGE   UNITSEARCH

*** Test Cases ***

Page-Two Units
	[Tags]   CRITICAL
	When User Goes To New Page -Site
	And User Starts Creating UnitSearch Paragraph
	And User Submits The New Page
	Then Layout Should Not Have Changed
	And Unit Links Are Working Correctly
	And The Search Bar Is Working Correctly
	And Search Bar Metadata is Correct
	And Unit Address and Phone Data Is Correct

*** Keywords ***
User Starts Creating UnitSearch Paragraph
	Create Unit Search Paragraph   Page
	

	
User Goes To New Page -Site		Go To New Page Site

User Submits The New Page	Submit The New Page
