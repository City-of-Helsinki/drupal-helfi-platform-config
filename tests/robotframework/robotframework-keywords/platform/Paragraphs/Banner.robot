*** Settings ***
Resource        ../Contenthandler.robot
Resource        ../Commonkeywords.robot

*** Keywords ***
Create Banner
	[Arguments]   ${pagetype}   ${alignment}   ${linkstyle}   ${coloroption}=${EMPTY}
	Set Test Variable   ${alignment}   ${alignment}
	Set Test Variable   ${linkstyle}   ${linkstyle}
	Set Test Variable   ${coloroption}   ${coloroption}
	Input Non-paragraph Related Content   ${pagetype}
	Open Paragraph For Edit   ${Opt_AddBanner}
	Wait Until Keyword Succeeds  5x  200ms  Input Title To Paragraph   ${Inp_Banner_Title}
	Run Keyword If  ('${alignment}'=='Left') & ('${coloroption}'=='${EMPTY}')   Click Element   ${Opt_Banner_Left}
	Run Keyword If  ('${alignment}'=='Left') & ('${coloroption}'!='${EMPTY}')  Click Element   ${Opt_Banner_Left_Secondary}
	Run Keyword If  ('${alignment}'=='Center') & ('${coloroption}'!='${EMPTY}')  Click Element   ${Opt_Banner_Center_Secondary}
	Run Keyword If  ('${TEST NAME}'=='Left Aligned Banner With Color Palette')   Select From List By Value   name:color_palette[0]   summer
	Input Description To Paragraph   ${Frm_Banner_Description}
	Wait Until Keyword Succeeds  5x  100ms  Input Text   ${Inp_Banner_Link_Uri}   https://fi.wikipedia.org/wiki/Rautatie_(romaani) 
	Input Text   ${Inp_Banner_Link_Title}    ${link_title_${language}}
	Scroll Element Into View   ${Swh_Banner_Link_OpenInNewWindow}
	Run Keyword If  '${TEST NAME}'=='Link Opens In New Window'   Select Checkbox  ${Swh_Banner_Link_OpenInNewWindow}
	Run Keyword If  '${TEST NAME}'=='Link Opens In New Window'   Select Checkbox  ${Swh_Banner_Link_LinkIsAccessable}
	Run Keyword If  '${linkstyle}'=='Fullcolor'  Click Element   ${Opt_Link_Fullcolor}
	Run Keyword If  '${linkstyle}'=='Framed'  Click Element   ${Opt_Link_Framed}
	Run Keyword If  '${linkstyle}'=='Transparent'  Click Element   ${Opt_Link_Transparent}

	
Take Screenshot Of Content
	Maximize Browser Window
	Capture Screenshot For Picture Comparison   css=main.layout-main-wrapper

Set Banner Title
	Input Text  ${Inp_Banner_Title}   Juhani Aho: Rautatie

Click Link In Content
	Run Keyword If  '${language}'=='fi'   Click Link   css:div.banner__content-wrapper > a
