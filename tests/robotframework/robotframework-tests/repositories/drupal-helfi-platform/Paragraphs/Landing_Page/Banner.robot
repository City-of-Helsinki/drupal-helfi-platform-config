*** Settings ***
Documentation   Testing Banner paragraph in landing page content type
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/Banner.robot
Test Setup      Login And Go To Content Page
Test Teardown   Cleanup and Close Browser
Force Tags		LANDINGPAGE   BANNER

*** Test Cases ***
Left Aligned Banner With Fullcolor Link
	[Tags]  CRITICAL
	Given User Goes To New LandingPage Site
	And User Starts Creating Left Aligned Banner With Fullcolor Link
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed

Left Aligned Banner Secondary Color
	[Tags]  CRITICAL
	Given User Goes To New LandingPage Site
	And User Starts Creating Left Aligned Banner With Fullcolor Link And Secondary Color
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed

Center Aligned Banner Secondary Color
	[Tags]
	Given User Goes To New LandingPage Site
	And User Starts Creating Center Aligned Banner With Fullcolor Link And Secondary Color
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed
	
Left Aligned Banner With Transparent Link
	[Tags]  
	Given User Goes To New LandingPage Site
	And User Starts Creating Left Aligned Banner With Transparent Link
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed
	
Left Aligned Banner With Framed Link
	[Tags]  
	Given User Goes To New LandingPage Site
	And User Starts Creating Left Aligned Banner With Framed Link
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed	

Center Aligned Banner With Fullcolor Link
	[Tags]  CRITICAL
	Given User Goes To New LandingPage Site
	And User Starts Creating Center Aligned Banner With Fullcolor Link
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed

Center Aligned Banner With Transparent Link
	[Tags]
	Given User Goes To New LandingPage Site
	And User Starts Creating Center Aligned Banner With Transparent Link
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed
	
Center Aligned Banner With Framed Link
	[Tags]  
	Given User Goes To New LandingPage Site
	And User Starts Creating Center Aligned Banner With Framed Link
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed		

Link Opens In New Window
	[Tags]  CRITICAL
	Given User Goes To New LandingPage Site
	And User Starts Creating Left Aligned Banner With Fullcolor Link
	When New Landingpage is Submitted
	And User Clicks The Content Link
	Then Link Should Be Opened In New Window
	
Left Aligned Banner With Color Palette
	[Documentation]   Uses one 'if' in 'Create Banner' method that changes color. Test then checks if color is changed.
	[Tags]  CRITICAL
	Given User Goes To New LandingPage Site
	And User Starts Creating Left Aligned Banner With Fullcolor Link
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed

	
*** Keywords ***
User Goes To New LandingPage Site   Go To New LandingPage Site
New Landingpage is Submitted	Submit The New Landingpage
User Starts Creating ${alignment} Aligned Banner With ${linkstyle} Link   Create Banner   LandingPage   ${alignment}   ${linkstyle}
User Starts Creating ${alignment} Aligned Banner With ${linkstyle} Link And Secondary Color   Create Banner   LandingPage   ${alignment}   ${linkstyle}   secondary

User Clicks The Content Link   
	Wait Until Keyword Succeeds  5x  200ms  Click Link In Content

Link Should Be Opened In New Window   New Window Should Be Opened   Rautatie (romaani) – Wikipedia
 
Layout Should Not Have Changed
	Run Keyword And Ignore Error  Accept Cookies
	Banner.Take Screenshot Of Content
	Compare Two Pictures