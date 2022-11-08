*** Settings ***
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/Top_News.robot
Test Setup      Login And Go To Content Page
Test Teardown   Cleanup and Close Browser	
Force Tags		LANDINGPAGE   TOP_NEWS   ETUSIVU_SPESIFIC

*** Test Cases ***

One Top News Paragraph
	[Tags]   CRITICAL
	Given User Goes To New LandingPage Site
	When User Creates Top News Paragraph
	And New Landingpage is Submitted
	Then Top News Is Present In Content Page
		
*** Keywords ***
User Goes To New LandingPage Site   Go To New LandingPage Site
New Landingpage is Submitted	Submit The New Landingpage

User Creates Top News Paragraph
	Create Top News Paragraph 	

Top News Is Present In Content Page
	Top News Paragraph With Content Exists