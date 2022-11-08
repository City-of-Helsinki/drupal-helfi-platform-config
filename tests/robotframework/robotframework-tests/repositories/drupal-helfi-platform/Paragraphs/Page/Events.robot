*** Settings ***
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/Events.robot
Test Setup      Login And Go To Content Page
Test Teardown   Cleanup and Close Browser	
Force Tags		EVENTS   PAGE

*** Test Cases ***
Event List
	[Tags]
	Given User Goes To New Page -Site
	And User Starts Creating Page With Event List Content
	When User Submits The New Page
	Then Event List Should Be Present In Page


Event List With Load More Enabled
	[Tags]
	Given User Goes To New Page -Site
	And User Starts Creating Event List With Load More Option Enabled
	When User Submits The New Page
	Then Event List Should Be Present In Page
	And Load More Button Should Be Present In Page	

	
	
*** Keywords ***
User Goes To New Page -Site		Go To New Page Site
User Submits The New Page
	Submit The New Page
	
User Starts Creating Page With Event List Content
	Create Event   Page

User Starts Creating Event List With Load More Option Enabled
	Create Event   Page   True


Event List Should Be Present In Page
	Events Are Present In Page
	
Load More Button Should Be Present In Page	
	Load More Button Is Present