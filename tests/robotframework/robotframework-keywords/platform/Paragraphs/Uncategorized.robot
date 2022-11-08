*** Settings ***

Resource        ../Contenthandler.robot
Resource        ../Commonkeywords.robot

*** Keywords ***

Take Screenshot Of Content
	Maximize Browser Window
	Capture Screenshot For Picture Comparison   css=main.layout-main-wrapper

Start Creating a New ${pagetype} With ${content} Content
	Set Test Variable  ${content}  ${content}
	Input Non-paragraph Related Content   ${pagetype}
	Wait Until Element Is Visible   ${Ddn_AddContent}   timeout=3
	Run Keyword If  '${language}'=='fi'  Click Element	${Ddn_AddContent}
	Run Keyword If  '${content}'=='Text'	Add Text Content To Page
	Run Keyword If  '${content}'=='Picture'	Add Picture Content To Page
	Run Keyword If  '${content}'=='Mixed'	Add Text And Picture To Page

Add Text And Picture To Page
	Add Text Content To Page
	Add Picture Content To Page
	
	
Add Text Content To Page
	Run Keyword If  '${language}'=='fi'  Click Element   ${Opt_AddText}
	${TextFileContent}=  Return Correct Content   ${language}
	Wait Until Keyword Succeeds  5x  200ms  Input Text To Frame   ${Frm_Text_Content}   //body   ${TextFileContent}   


Add Picture Content To Page
	${addpicturevisible}=  Run Keyword And Return Status    Wait Until Element Is Visible  ${Opt_AddPicture}   timeout=1
	Run Keyword If   not(${addpicturevisible})   Click Element   ${Ddn_AddContent}
	Run Keyword If  '${language}'=='fi'  Click Element   ${Opt_AddPicture}
	Wait Until Keyword Succeeds  5x  200ms  Click Element  ${Btn_Picture}
	Wait Until Keyword Succeeds  6x  300ms  Choose File   ${Btn_File_Upload}   ${IMAGES_PATH}/train.jpg
	Wait Until Keyword Succeeds  6x  300ms  Set Focus To Element  ${Inp_Pic_Name}
	Input Text    ${Inp_Pic_Name}   Juna sillalla
	Input Text    ${Inp_Pic_AltText}   Vanha juna kuljettaa matkustajia 
	Input Text    ${Inp_Pic_Photographer}   Testi Valokuvaaja
	Click Button   ${Btn_Save}
	Wait Until Keyword Succeeds  5x  200ms  Submit New Media
	Wait Until Element Is Visible  ${Btn_Picture_Remove}   timeout=3
	
	
		


