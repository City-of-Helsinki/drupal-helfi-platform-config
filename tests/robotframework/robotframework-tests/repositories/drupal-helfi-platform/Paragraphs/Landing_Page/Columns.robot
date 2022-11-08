*** Settings ***
Documentation   Testing Columns Settings in Platform by comparing layout to default picture. Testing is performed with
...				Different text deviation like 50-50, 30-70 and with pictures and links added.
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/Columns.robot
Test Setup      Login And Go To Content Page
Test Teardown   Cleanup and Close Browser	
Force Tags		LANDINGPAGE   COLUMNS
*** Variables ***


*** Test Cases ***
Landingpage-50-50
	[Tags]  CRITICAL
	Given User Goes To New LandingPage Site
	And User Starts Creating Page With 50-50 Division And Text Content
	And User Adds Text to Left Column
	And User Adds Text to Right Column
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed	
	
Landingpage-30-70
	[Tags]
	Given User Goes To New LandingPage Site
	And User Starts Creating Page With 30-70 Division And Text Content
	And User Adds Text to Left Column
	And User Adds Text to Right Column
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed	

Landingpage-70-30
	[Tags]
	Given User Goes To New LandingPage Site
	And User Starts Creating Page With 70-30 Division And Text Content
	And User Adds Text to Left Column
	And User Adds Text to Right Column
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed	

Landingpage-50-50 with picture
	[Tags]  CRITICAL
	Given User Goes To New LandingPage Site
	And User Starts Creating Page With 50-50 Division And Picture Content
	And User Adds Picture to Left Column
	And User Adds Picture to Right Column
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed	

Landingpage-50-50 with picture and text
	[Tags]  CRITICAL
	Given User Goes To New LandingPage Site
	And User Starts Creating Page With 50-50 Division And Mixed Content
	And User Adds Picture to Left Column
	And User Adds Text to Right Column
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed

Landingpage-70-30 with original size picture and text
	[Tags]   CRITICAL
	Given User Goes To New LandingPage Site
	And User Starts Creating Page With 70-30 Division And Mixed Content
	And User Adds Original Picture to Left Column
	And Picture on Left Has Original Aspect Ratio Enabled
	And User Adds Text to Right Column
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed

Finnish English Swedish Translations
	[Tags]   CRITICAL
	Given User Creates Page With 50-50 Division And Mixed Content in Finnish Language
	And User Creates Page With 50-50 Division And Mixed Content in English Language
	And User Creates Page With 50-50 Division And Mixed Content in Swedish Language
	Then Page Should Have Finnish Translation
	And Page Should Have English Translation
	And Page Should Have Swedish Translation

*** Keywords ***
User Goes To New LandingPage Site   Go To New LandingPage Site
New Landingpage is Submitted	Submit The New Landingpage

User Starts Creating ${pagetype} With ${division} Division And ${contenttype} Content
	Create ${pagetype} With ${division} Division And ${contenttype} Content

User Creates ${pagetype} With ${division} Division And ${contenttype} Content in ${lang_selection} Language
	Create ${pagetype} With ${division} Division And ${contenttype} Content in ${lang_selection} Language

User Adds ${content} to Left Column
	${content}=  Convert To Lower Case   ${content}
	Add ${content} to Left Column

User Adds ${content} to Right Column
	Add ${content} to Right Column

User Adds Link Button With ${linkstyle} Style into ${side} Column
	Set Test Variable   ${linkstyle}   ${linkstyle}
	${side}=  Convert To Lower Case   ${side}
	Run Keyword If  '${side}'=='right'  Add Link to Right Column
	Run Keyword If  '${side}'=='left'  Add Link to Left Column

Picture on ${side} Has Original Aspect Ratio Enabled
	Use Original Aspect Ratio on ${side}

Layout Should Not Have Changed
	Run Keyword And Ignore Error  Accept Cookies
	Columns.Take Screenshot Of Content
	Compare Two Pictures

Page Should Have ${lang_input} Translation
	Set Language Pointer   ${lang_input}
	Select Language   ${lang_input}
	Page Content Matches Language

Page Content Matches Language
	${Title}=  Return Title From LandingPage
	${Content}=  Return Content From LandingPage
	Title Should Match Current Language Selection   ${Title}
	Columns.Content Should Match Current Language Selection   ${Content}
