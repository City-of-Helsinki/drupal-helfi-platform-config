*** Settings ***

Resource        ../Contenthandler.robot
Resource        ../Commonkeywords.robot

*** Keywords ***
Create LiftUpWithImage
	[Arguments]  ${pagetype}   ${design}   ${coloroption}=${EMPTY}
	Set Test Variable  ${design}  ${design}
	Set Test Variable   ${coloroption}   ${coloroption}
	Input Non-paragraph Related Content   ${pagetype}
	Open Paragraph For Edit   ${Opt_AddLiftupWithImage}
	${design}=   Resolve Design Variable   ${design}
	${islandingpage}=  Suite Source Contains Text    Landing_Page
	
	Wait Until Keyword Succeeds  5x  200ms  Select From List By Value  ${Inp_LiftupWithImage_Design}  ${design}
	Input Title To Paragraph    ${Inp_LiftupWithImage_Title}
	Add Picture   train
	Input Description To Paragraph   ${Frm_Content}
	
Resolve Design Variable
	[Arguments]  ${design}
	${design}=  Convert To Lower Case   ${design}
	${designvariable}=   Set Variable If   ('${design}'=='right picture') & ('${coloroption}'=='${EMPTY}')    image-on-right
	...			('${design}'=='left picture') & ('${coloroption}'=='${EMPTY}')    image-on-left
	...			('${design}'=='right picture') & ('${coloroption}'!='${EMPTY}')   image-on-right-secondary
	...			('${design}'=='left picture') & ('${coloroption}'!='${EMPTY}')   image-on-left-secondary
	...			'${design}'=='background picture and text on right'    background-text-on-right
	...			'${design}'=='background picture and text on left'    background-text-on-left
	[Return]   ${designvariable}

Add Picture
	[Arguments]   ${picname}
	${islandingpage}=  Suite Source Contains Text    Landing_Page
	Run Keyword If  not(${islandingpage})  Wait Until Keyword Succeeds  5x  200ms  Click Element   ${Inp_LiftupWithImage_Picture}
	...			ELSE	Wait Until Keyword Succeeds  5x  200ms  Click Element   ${Inp_LiftupWithImage_Picture}
	Wait Until Keyword Succeeds  6x  300ms  Choose File   ${Btn_File_Upload}   ${IMAGES_PATH}/${picname}.jpg
	Wait Until Keyword Succeeds  6x  300ms  Set Focus To Element  ${Inp_Pic_Name}
	Input Text    ${Inp_Pic_Name}   Juna sillalla
	Input Text    ${Inp_Pic_AltText}   Vanha juna kuljettaa matkustajia 
	Input Text    ${Inp_Pic_Photographer}   Testi Valokuvaaja
	Click Button   ${Btn_Save}
	Wait Until Keyword Succeeds  5x  200ms  Submit New Media
	Wait Until Element Is Visible  //input[contains(@data-drupal-selector, 'remove-button')]   timeout=3
	Set Test Variable  ${picture}   picture
	
Layout Should Not Have Changed
	Run Keyword And Ignore Error  Accept Cookies
	Capture Screenshot For Picture Comparison    css=main.layout-main-wrapper
	Compare Two Pictures	