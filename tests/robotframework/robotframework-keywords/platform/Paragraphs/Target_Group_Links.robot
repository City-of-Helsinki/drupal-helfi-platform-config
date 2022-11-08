*** Settings ***

Resource        ../Contenthandler.robot
Resource        ../Commonkeywords.robot
*** Variables ***
${link_url1}   /en/news/multamaen-rautatieasema
${link_url2}   /en/news/liitupiippu
${link_url3}   /en/news/sahkoteknillinen-koulu
${link_url4}   /sv/nyheter/sumbawan-kieli

*** Keywords ***
Create Target Group Link
	[Arguments]   ${number}
	Input Non-paragraph Related Content   Etusivu
	Open Paragraph For Edit   ${Opt_TargetGroupLinks}
	Wait Until Keyword Succeeds  5x  200ms  Input Title To Paragraph  ${Inp_TargetGroupLinks_Title}
	Wait Until Keyword Succeeds  5x  200ms  Input Description To Paragraph  ${Frm_TargetGroupLinks_Description}
	IF  '${number}'=='1'    # WE ARE RUNNING THE FIRST TESTCASE (IN TARGET GROUP LINK CASES) WHERE NUMBER IS 1
		Create New Link   1    cogwheel
	ELSE   # OTHERWISE WE ARE RUNNING OTHER TESTCASE OF THE TWO CREATING 4 LINKS 
		Create New Link   1    cogwheel
		Click Add New Link Button
		Create New Link   2    check
		Click Add New Link Button
		Create New Link   3    clock
		Click Add New Link Button
		Create New Link   4    heart
	END

Click Add New Link Button
	Click Button  ${Inp_TargetGroupLinks_Item_NewItem}

Create New Link
	[Arguments]   ${number}   ${icon}
	Wait Until Keyword Succeeds  7x  200ms  Page Should Have Given Number Of Elements   //input[contains(@name, 'field_target_group_item_link')][contains(@name, 'uri')]  ${number}
	Select From List By Value   (//select[contains(@name, 'field_icon')])[last()]   ${icon}
	Input Text  ${Inp_TargetGroupLinks_Item_Link}   Target Link Main Title${number}
	Input Text  ${Inp_TargetGroupLinks_Item_Uri}   ${link_url${number}}
	Input Text	${Inp_TargetGroupLinks_Item_Subtitle}   Test Subtitle${number}
	
Page Contains Target Group List With Content
	target-group-links Is Present In Page
	
Page Should Have Correct Number Of Target Links
	[Arguments]   ${number}
	Page Should Have Given Number Of Elements	css:.target-group-link   ${number}
	
${count} Target Links Work Correctly
	${contentpageurl}=   Get Location
	IF  '${count}'=='1'    # WE ARE RUNNING THE FIRST TESTCASE (IN TARGET GROUP LINK CASES) WHERE NUMBER IS 1
		Click Link And Return   ${contentpageurl}   Target Link Main Title1   multamaen-rautatieasema
	ELSE
		Click Link And Return   ${contentpageurl}   Target Link Main Title1   multamaen-rautatieasema
		Click Link And Return   ${contentpageurl}   Target Link Main Title2   liitupiippu
		Click Link And Return   ${contentpageurl}   Target Link Main Title3   sahkoteknillinen-koulu
		Click Link And Return   ${contentpageurl}   Target Link Main Title4   sumbawan-kieli   
	END
	
Click Link And Return
	[Arguments]   ${contentpageurl}   ${linktitle}   ${assertedpage}   
	Click Link    ${linktitle}
	${currenturl}=   Get Location
	Wait Until Keyword Succeeds  7x  200ms  Should Contain   ${currenturl}   ${assertedpage}
	Go To   ${contentpageurl}
	