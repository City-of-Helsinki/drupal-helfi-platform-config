*** Settings ***
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/Latest_News.robot
Test Setup      Login And Go To Content Page
Test Teardown   Cleanup and Close Browser	
Force Tags		LANDINGPAGE   LATEST_NEWS   ETUSIVU_SPESIFIC

*** Test Cases ***

One Latest News Paragraph
	[Tags]   CRITICAL
	Given User Goes To New LandingPage Site
	When User Creates Latest News Paragraph
	And New Landingpage is Submitted
	Then Latest News Is Present In Content Page
		
*** Keywords ***
User Goes To New LandingPage Site   Go To New LandingPage Site
New Landingpage is Submitted	Submit The New Landingpage

User Creates Latest News Paragraph
	Create Latest News Paragraph 	

Latest News Is Present In Content Page
	Latest News Paragraph With Content Exists