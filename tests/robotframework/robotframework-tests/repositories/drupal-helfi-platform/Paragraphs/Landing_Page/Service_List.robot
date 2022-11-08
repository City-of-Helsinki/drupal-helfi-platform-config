*** Settings ***
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/Service_List.robot
Documentation	This Paragraph requires some services and tpr_config module enabled as prerequisite.
Test Setup      Login And Go To Content Page
Test Teardown   Cleanup and Close Browser	
Force Tags		LANDINGPAGE   SERVICELIST

*** Test Cases ***

One Service
	[Tags]   CRITICAL
	When User Adds Content With Service List
	Then Layout Should Not Have Changed
	And ServiceList Paragraph Works Correctly

Two Services
	[Tags]
	When User Adds Content With Service List
	Then Layout Should Not Have Changed
	And ServiceList Paragraph Works Correctly
	
*** Keywords ***
User Adds Content With Service List
	Go To New LandingPage Site
	Add ServiceList   LandingPage
	Submit The New Landingpage
	
ServiceList Paragraph Works Correctly
	${contentpage}=   Get Location
	Click Link   css:.service__link
	${currenturl}=   Get Location
	Should Contain   ${currenturl}   parkletit
	Run Keyword If  '${TEST NAME}'=='Two Services'   GoTo   ${contentpage}
	Run Keyword If  '${TEST NAME}'=='Two Services'  Click Link   css:div.views-element-container > div > div > div:nth-child(2) > div > a
	${currenturl}=   Run Keyword If  '${TEST NAME}'=='Two Services'  Get Location
	Run Keyword If  '${TEST NAME}'=='Two Services'  Should Contain   ${currenturl}   sosiaalineuvonta   

	