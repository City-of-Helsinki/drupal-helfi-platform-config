*** Settings ***
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/Remote_Video.robot
Test Setup      Login And Go To Content Page
Test Teardown   Cleanup and Close Browser	
Force Tags		LANDINGPAGE   REMOTEVIDEO

*** Test Cases ***

Landingpage-One Video
	[Tags]   CRITICAL
	Given User Goes To New LandingPage -Site
	And User Adds Content With 1 Remote Video(s)
	When User Submits The New LandingPage
	Then Layout Should Not Have Changed
	And Remote Video Title And Description is Correct
#	And Remote Video Play Begins Correctly      #DISABLED DUE ERROR IN PICTURE DIMENSIONS, TRY SELECTING FRAMES IF FIXES IT

Landingpage-Two Videos
	[Tags]
	Given User Goes To New LandingPage -Site
	And User Adds Content With 2 Remote Video(s)
	When User Submits The New LandingPage
	Then Layout Should Not Have Changed
#	And Remote Video Play Begins Correctly      #DISABLED DUE ERROR IN PICTURE DIMENSIONS, TRY SELECTING FRAMES IF FIXES IT
	
*** Keywords ***
User Adds Content With ${number} Remote Video(s)
	Create Page With ${number} Remote Video(s) Content

User Goes To New LandingPage -Site		Go To New LandingPage Site
User Submits The New LandingPage
	Submit The New LandingPage
