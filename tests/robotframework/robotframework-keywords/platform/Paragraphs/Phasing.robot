*** Settings ***

Resource        ../Contenthandler.robot
Resource        ../Commonkeywords.robot

*** Keywords ***
Create New Phasing
	[Arguments]   ${pagetype}=Page   ${numbering}=False   ${titlelevel}=h2
	Input Non-paragraph Related Content   ${pagetype}
	Open Paragraph For Edit   ${Opt_Phasing}
	Input Phasing Data   ${numbering}   ${titlelevel}
	Create New Phase   Phase 2    Phase 2 Description Text
	
Input Phasing Data
	[Arguments]   ${numbering}=False   ${titlelevel}=h2
	Wait Until Keyword Succeeds  5x  200ms  Select From List By Label   ${Sel_Phasing_Title_Level}   ${titlelevel}
	Run Keyword If   ${numbering}   Scroll Element Into View   ${Swh_Phasing_ShowPhaseNumbers}
	Run Keyword If   ${numbering}   Click Element   ${Swh_Phasing_ShowPhaseNumbers}
	Wait Until Keyword Succeeds  5x  200ms  Input Title To Paragraph   ${Inp_Phasing_Title}
	Input Description To Paragraph   ${Inp_Phasing_Description}
	Wait Until Keyword Succeeds  5x  200ms  Input Text  ${Inp_Phasing_Phase_Title}   Phase 1
	Input Text To Frame  ${Inp_Phasing_Item_Description}   //body    Phase 1 Description Text

Create New Phase
	[Arguments]   ${title}   ${description}
	Click Element	${Btn_AddPhasingItem}
	# LETS WAIT FOR ELEMENT COUNT BE CORRECT SO THAT PHASE 2 TITLE IS ADDED CORRECTLY
	Wait Until Keyword Succeeds  5x  200ms  Page Should Have Given Number Of Elements   //input[contains(@name, 'field_title]')]   3
	Input Text  ${Inp_Phasing_Phase_Title}   ${title}
	Input Text To Frame  ${Inp_Phasing_Item_Description}   //body    ${description}
	
Phasing Is Found On Page
	phasing Is Present In Page
	