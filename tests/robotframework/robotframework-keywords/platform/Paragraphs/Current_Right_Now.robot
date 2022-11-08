*** Settings ***

Resource        ../Contenthandler.robot
Resource        ../Commonkeywords.robot

*** Variables ***
${link_url1}   /en/news/multamaen-rautatieasema
${link_url2}   /en/news/liitupiippu
${link_url3}   /en/news/sahkoteknillinen-koulu
${link_url4}   /sv/nyheter/sumbawan-kieli

*** Keywords ***

Create Current Right Now Link
	[Arguments]   ${number}
	Input Non-paragraph Related Content   Etusivu
	Open Paragraph For Edit   ${Opt_Current}
	Wait Until Keyword Succeeds  5x  200ms  Select From List By Label   ${Sel_CurrentRightNow_Seasons}   Spring
	IF  '${number}'=='1'    # WE ARE RUNNING THE FIRST TESTCASE (IN TARGET GROUP LINK CASES) WHERE NUMBER IS 1
		Create New Link   1
	ELSE   # OTHERWISE WE ARE RUNNING OTHER TESTCASE OF THE TWO CREATING 4 LINKS 
		Create New Link   1
		Create New Link   2
		Create New Link   3
		Create New Link   4
	END
	
Create New Link
	[Arguments]   ${number}
	${titlelocator}=   Catenate   SEPARATOR=    ${Inp_CurrentRightNow_Item_Title}   [${number}]
	${linklocator}=   Catenate   SEPARATOR=    ${Inp_CurrentRightNow_Item_Link}   [${number}]
	Wait Until Keyword Succeeds  5x  200ms  Input Text  ${titlelocator}   Current Right Now Link Title${number}
	Wait Until Keyword Succeeds  5x  200ms  Input Text  ${linklocator}   ${link_url${number}}
	
Click Add New Link Button
	Click Button  ${Inp_TargetGroupLinks_Item_NewItem}	
	
Page Contains Current Right Now Links With Content
	current Is Present In Page
	
Page Should Have Correct Number Of Current Right Now Links
	[Arguments]   ${number}
	Page Should Have Given Number Of Elements	css:.link__style--highlight  ${number}
	
${count} Current Right Now Links Work Correctly
	${contentpageurl}=   Get Location
	IF  '${count}'=='1'    # WE ARE RUNNING THE FIRST TESTCASE (IN TARGET GROUP LINK CASES) WHERE NUMBER IS 1
		Click Link And Return   ${contentpageurl}   Current Right Now Link Title1   multamaen-rautatieasema
	ELSE
		Click Link And Return   ${contentpageurl}   Current Right Now Link Title1   multamaen-rautatieasema
		Click Link And Return   ${contentpageurl}   Current Right Now Link Title2   liitupiippu
		Click Link And Return   ${contentpageurl}   Current Right Now Link Title3   sahkoteknillinen-koulu
		Click Link And Return   ${contentpageurl}   Current Right Now Link Title4   sumbawan-kieli   
	END
	
Click Link And Return
	[Arguments]   ${contentpageurl}   ${linktitle}   ${assertedpage}   
	Click Link    ${linktitle}
	${currenturl}=   Get Location
	Wait Until Keyword Succeeds  7x  200ms  Should Contain   ${currenturl}   ${assertedpage}
	Go To   ${contentpageurl}