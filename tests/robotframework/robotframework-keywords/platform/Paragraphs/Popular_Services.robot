*** Settings ***

Resource        ../Contenthandler.robot
Resource        ../Commonkeywords.robot

*** Variables ***
${link_url1}   /en/news/multamaen-rautatieasema
${link_url2}   /en/news/liitupiippu
${link_url3}   /en/news/sahkoteknillinen-koulu
${link_url4}   /sv/nyheter/sumbawan-kieli
${link_url5}   /fi/uutiset/haivemustesieni
${link_url7}   /fi/uutiset/pienojanlampi
${link_url8}   /en/news/multamaen-rautatieasema

*** Keywords ***

Create Popular Services Link
	[Arguments]   ${number}
	Input Non-paragraph Related Content   Etusivu
	Open Paragraph For Edit   ${Opt_PopularServices}
	Wait Until Keyword Succeeds  5x  200ms  Input Title To Paragraph   ${Inp_PopularServices_Title}
	IF  '${number}'=='2'    # WE ARE RUNNING THE FIRST TESTCASE (IN TARGET GROUP LINK CASES) WHERE NUMBER IS 1
		Create New Link   1
		Create New Link   2
		Click Add New Link Button
		Wait Until Keyword Succeeds  7x  200ms  Page Should Have Given Number Of Elements   //input[contains(@name, 'field_service_title')][contains(@name, 'title')]  2
		Wait Until Keyword Succeeds  5x  200ms  Input Title To Paragraph   ${Inp_PopularServices_Title}
		Create New Link   3
		Create New Link   4
	ELSE   # OTHERWISE WE ARE RUNNING OTHER TESTCASE OF THE TWO CREATING 4 LINKS 
		Create New Link   1
		Create New Link   2
		Click Add New Link Button
		Wait Until Keyword Succeeds  7x  200ms  Page Should Have Given Number Of Elements   //input[contains(@name, 'field_service_title')][contains(@name, 'title')]  2
		Wait Until Keyword Succeeds  5x  200ms  Input Title To Paragraph   ${Inp_PopularServices_Title}
		Create New Link   4
		Create New Link   5
		Click Add New Link Button
		Wait Until Keyword Succeeds  7x  200ms  Page Should Have Given Number Of Elements   //input[contains(@name, 'field_service_title')][contains(@name, 'title')]  3
		Wait Until Keyword Succeeds  5x  200ms  Input Title To Paragraph   ${Inp_PopularServices_Title}
		Create New Link   7
		Create New Link   8
	END
	
Create New Link
	[Arguments]   ${number}
	${titlelocator}=   Catenate   SEPARATOR=    ${Inp_PopularServices_Item_Title}   [${number}]
	${linklocator}=   Catenate   SEPARATOR=    ${Inp_PopularServices_Item_Link}   [${number}]
	Wait Until Keyword Succeeds  5x  200ms  Input Text  ${titlelocator}   Popular Services Link Title${number}
	Wait Until Keyword Succeeds  5x  200ms  Input Text  ${linklocator}   ${link_url${number}}
	
Click Add New Link Button
	Click Button  ${Inp_PopularServices_Item_NewItem}	
	
Page Contains Popular Services Links With Content
	popular-services Is Present In Page
	
Page Should Have Correct Number Of Popular Services Links
	[Arguments]   ${number}
	Page Should Have Given Number Of Elements	css:.popular-service-item__links   ${number}
	
${count} Popular Services Links Work Correctly
	${contentpageurl}=   Get Location
	IF  '${count}'=='2'    # WE ARE RUNNING THE FIRST TESTCASE (IN TARGET GROUP LINK CASES) WHERE NUMBER IS 1
		Click Link And Return   ${contentpageurl}   Popular Services Link Title1   multamaen-rautatieasema
		Click Link And Return   ${contentpageurl}   Popular Services Link Title2   liitupiippu
		Click Link And Return   ${contentpageurl}   Popular Services Link Title3   sahkoteknillinen-koulu
		Click Link And Return   ${contentpageurl}   Popular Services Link Title4   sumbawan-kieli
	ELSE
		Click Link And Return   ${contentpageurl}   Popular Services Link Title1   multamaen-rautatieasema
		Click Link And Return   ${contentpageurl}   Popular Services Link Title2   liitupiippu
		Click Link And Return   ${contentpageurl}   Popular Services Link Title4   sumbawan-kieli
		Click Link And Return   ${contentpageurl}   Popular Services Link Title5   haivemustesieni
		Click Link And Return   ${contentpageurl}   Popular Services Link Title7   pienojanlampi
		Click Link And Return   ${contentpageurl}   Popular Services Link Title8   multamaen-rautatieasema   
	END
	
Click Link And Return
	[Arguments]   ${contentpageurl}   ${linktitle}   ${assertedpage}   
	Click Link    ${linktitle}
	${currenturl}=   Get Location
	Wait Until Keyword Succeeds  7x  200ms  Should Contain   ${currenturl}   ${assertedpage}
	Go To   ${contentpageurl}