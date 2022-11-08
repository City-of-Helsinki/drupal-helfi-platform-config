*** Settings ***
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/Remote_Video.robot
Test Setup      Login And Go To Content Page
Test Teardown   Cleanup and Close Browser	
Force Tags		NEWS-ITEM   REMOTEVIDEO   ETUSIVU_SPESIFIC

*** Test Cases ***

One Video
	[Tags]   CRITICAL
	Given User Goes To New News-Item Site
	And User Adds Content With 1 Remote Video(s)
	When New News-Item is Submitted
	Then Layout Should Not Have Changed
	And Remote Video Title And Description is Correct
#	And Remote Video Play Begins Correctly      #DISABLED DUE ERROR IN PICTURE DIMENSIONS, TRY SELECTING FRAMES IF FIXES IT


Two Videos
	[Tags]
	Given User Goes To New News-Item Site
	And User Adds Content With 2 Remote Video(s)
	When New News-Item is Submitted
	Then Layout Should Not Have Changed
#	And Remote Video Play Begins Correctly      #DISABLED DUE ERROR IN PICTURE DIMENSIONS, TRY SELECTING FRAMES IF FIXES IT
	
*** Keywords ***
User Adds Content With ${number} Remote Video(s)
	Input Etusivu Instance Spesific Content
	Create Page With ${number} Remote Video(s) Content

User Goes To New News-Item Site   Go To New News-Item Site
New News-Item is Submitted	Submit The New News-Item
