*** Settings ***
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/Contact_Card_Listing.robot
Test Setup      Login And Go To Content Page
Test Teardown   Cleanup and Close Browser	
Force Tags		PAGE   CONTACTCARDLISTING



*** Test Cases ***
One Contact Card
	[Tags]  CRITICAL
	User Goes To New Page -Site
	When User Starts Creating Contactcard Paragraph
	And User Submits The New Page
	Then Layout Should Not Have Changed
	And Contactcard Content Is Valid
		
Two Contact Cards
	[Tags]  CRITICAL
	User Goes To New Page -Site
	When User Starts Creating Contactcard Paragraph
	And User Submits The New Page
	Then Layout Should Not Have Changed
		
*** Keywords ***
User Goes To New Page -Site		Go To New Page Site

User Starts Creating Contactcard Paragraph
	Contenthandler.Input Non-paragraph Related Content   Page
	Create ContactCard Paragraph
	Create ContactCard1
	Run Keyword If  '${TEST NAME}'=='Two Contact Cards'  Create ContactCard Paragraph
	Run Keyword If  '${TEST NAME}'=='Two Contact Cards'  Create ContactCard2

User Submits The New Page
	Submit The New Page
	
Layout Should Not Have Changed
	Run Keyword And Ignore Error  Accept Cookies
	Contact_Card_Listing.Take Screenshot Of Content
	Compare Two Pictures

	
Contactcard Content Is Valid
	${currenturl}=   Get Location
	Page Should Contain Link   9876543210
	Page Should Contain Link   0123456789
	Page Should Contain Link   lion66366@testmail.com
	Page Should Contain Link   https://www.helsinki.fi
