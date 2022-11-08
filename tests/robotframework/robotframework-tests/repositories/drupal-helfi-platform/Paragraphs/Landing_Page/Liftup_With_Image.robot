*** Settings ***
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/Liftup_With_Image.robot
Test Setup      Login And Go To Content Page
Test Teardown   Cleanup and Close Browser	
Force Tags		LANDINGPAGE   LIFTUPWITHIMAGE

*** Test Cases ***
Left Picture
	[Tags]  CRITICAL
	Given User Goes To New LandingPage Site
	When User Starts Creating LandingPage With Left Picture -Design
	And New Landingpage is Submitted
	Then Layout Should Not Have Changed
	LiftUpWithImage Is Present
	
Right Picture
	[Tags]
	Given User Goes To New LandingPage Site
	When User Starts Creating LandingPage With Right Picture -Design
	And New Landingpage is Submitted
	Then Layout Should Not Have Changed
	LiftUpWithImage Is Present	

Left Picture Secondary Color
	[Tags]  CRITICAL
	Given User Goes To New LandingPage Site
	When User Starts Creating LandingPage With Left Picture -Design and Alternate Color
	And New Landingpage is Submitted
	Then Layout Should Not Have Changed
	LiftUpWithImage Is Present	

Right Picture Secondary Color
	[Tags]
	Given User Goes To New LandingPage Site
	When User Starts Creating LandingPage With Right Picture -Design and Alternate Color
	And New Landingpage is Submitted
	Then Layout Should Not Have Changed
	LiftUpWithImage Is Present	

Background Picture Text Left
	[Tags]
	Given User Goes To New LandingPage Site
	When User Starts Creating LandingPage With Background Picture And Text On Left -Design
	And New Landingpage is Submitted
	Then Layout Should Not Have Changed
	LiftUpWithImage Is Present	
	
Background Picture Text Right
	[Tags] 
	Given User Goes To New LandingPage Site
	When User Starts Creating LandingPage With Background Picture And Text On Right -Design
	And New Landingpage is Submitted
	Then Layout Should Not Have Changed
	LiftUpWithImage Is Present	
	

*** Keywords ***
User Goes To New LandingPage Site   Go To New LandingPage Site
New Landingpage is Submitted	Submit The New Landingpage

User Starts Creating ${pagetype} With ${design} -Design 	Create LiftUpWithImage   ${pagetype}   ${design}
User Starts Creating ${pagetype} With ${design} -Design and Alternate Color 	Create LiftUpWithImage   ${pagetype}   ${design}   Secondary	

LiftUpWithImage Is Present
	liftup-with-image Is Present In Page
	Element Should Be Visible  css:.liftup-with-image__image   timeout=3
	Element Should Be Visible  css:.liftup-with-image__content   timeout=3