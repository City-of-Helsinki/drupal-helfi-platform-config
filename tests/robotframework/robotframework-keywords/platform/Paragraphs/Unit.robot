*** Settings ***

Resource        ../Contenthandler.robot
Resource        ../Commonkeywords.robot

*** Keywords ***

Publish The ${nth} Unit In The Unit List
	Goto  ${PROTOCOL}://${BASE_URL}/fi/admin/content/integrations/tpr-unit/${nth}/edit
	Set Unit As Published
	Submit New Content

Open Unit With Name
	[Arguments]	   ${name}
	Goto  ${PROTOCOL}://${BASE_URL}/fi/admin/content/integrations/tpr-unit
	Click Link   ${name}

Submit Unit Changes
	Wait Until Keyword Succeeds  5x  100ms  Click Button   ${Btn_Submit}

Add Banner For Unit With Name And Color
	[Arguments]	   ${name}   ${color}
	${color}=  Convert To Lower Case   ${color}
	Open Unit With Name    ${name}
	Set Focus To Element   css:ul.local-tasks > li:nth-child(2) > a
	Click Link   css:ul.local-tasks > li:nth-child(2) > a
	Open Paragraph For Edit   ${Opt_AddBanner}
	Wait Until Keyword Succeeds  5x  200ms  Input Title To Paragraph   ${Inp_Banner_Title}
	Select From List By Value   name:color_palette[0]   silver

Delete Banner For Unit With Name
	[Arguments]	   ${name}
	Open Unit With Name    ${name}
	Set Focus To Element   css:ul.local-tasks > li:nth-child(2) > a
	Click Link   css:ul.local-tasks > li:nth-child(2) > a
	Wait Until Keyword Succeeds  5x  200ms  Click Element  css:.paragraphs-dropdown-toggle
	Wait Until Keyword Succeeds  5x  200ms  Click Element  css:#field-content-0-remove
	Sleep  0.5		# Little pause so that banner gets succesfully deleted
	Submit Unit Changes
	
Get Unit Title
	${value}=   Get Text   css:.unit__title
	[Return]   ${value}

Get Contact Card Title
	${value}=   Get Text   css:.unit__contact__title
	[Return]   ${value}
	

Get Address Main Title
	${value}=   Get Text   css:div.unit__contact-row.unit__contact-row--address > label
	[Return]   ${value}
	
Get Address Line 1
	${value}=   Get Text   css:.address-line1
	[Return]   ${value}
	
Get Postal Code
	${value}=   Get Text   css:.postal-code
	[Return]   ${value}	
	
Get Locality
	${value}=   Get Text   css:.locality
	[Return]   ${value}
	
Get Country
	${value}=   Get Text   css:.country
	[Return]   ${value}

Get Phone Main Title
	${value}=   Get Text   css:div.unit__contact-row.unit__contact-row--phone > label
	[Return]   ${value}
	
Get Phone
	${value}=   Get Text   css:.unit__contact-row__label:last-of-type
	[Return]   ${value}

Unit Has Service Link
	${status}=  Run Keyword And Return Status    Element Should Be Visible   css:.service__link
	[Return]   ${status}
	
Get Units Service Title 
	${value}=  Get Text   css:.service__title
	[Return]   ${value}
	
Get Units Service Description
	${value}=  Get Text   css:.service__short-desc
	[Return]   ${value}