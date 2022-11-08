*** Settings ***
Resource        ../../../../robotframework-keywords/platform/Contenthandler.robot
Resource        ../../../../robotframework-keywords/platform/Commonkeywords.robot
Test Setup      Login And Go To Content Page
Test Teardown   Cleanup and Close Browser	
Force Tags		PAGE   TABLEOFCONTENTS

*** Test Cases ***
Table Of Contents With Two Links
	[Tags]
	Given User Goes To New Page -Site
	And User Enables Table Of Contents
	And User Creates Banner With Name   Banner1
	And User Creates Banner With Name   Banner2
	When User Submits The New Page
	Then Table Of Contents Has Correct Content  
	






*** Keywords ***
User Goes To New Page -Site		
	Go To New Page Site
	Wait Until Keyword Succeeds  5x  200ms  Input Non-paragraph Related Content   Page

User Creates Banner With Name
	[Arguments]   ${name}
	Element Should Not Be Visible   ${Opt_AddBanner}
	Wait Until Keyword Succeeds  5x  200ms  Open Paragraph For Edit   ${Opt_AddBanner}
	Wait Until Keyword Succeeds  5x  200ms  Element Should Not Be Visible   ${Opt_AddBanner}
	Wait Until Keyword Succeeds  5x  200ms  Input Text  ${Inp_Banner_Title}   ${name}
	Input Description To Paragraph   (//iframe)[last()]
			
User Enables Table Of Contents
	Click Element   ${Swh_TOC}
	
User Submits The New Page
	Submit The New Page
	
Table Of Contents Has Correct Content
	Wait Until Keyword Succeeds  5x  200ms  Page Should Contain Link   Banner1
	Page Should Contain Link   Banner2
