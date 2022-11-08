*** Settings ***

Resource        ../Contenthandler.robot
Resource        ../Commonkeywords.robot
Resource        Phasing.robot

*** Keywords ***

Create ${pagetype} With ${color} Color , ${heading} Heading And ${contenttype} Content
	Set Test Variable  ${pagetype}  ${pagetype}
	Set Test Variable  ${contenttype}  ${contenttype}
	Set Test Variable  ${color}  ${color}
	Set Test Variable  ${heading}  ${heading}
	Input Non-paragraph Related Content   ${pagetype}
	Open Paragraph For Edit   ${Opt_AddAccordion}
	Run Keyword If  '${color}'!='White'  Wait Until Keyword Succeeds  5x  100ms  Click Element With Value   grey
	Wait Until Keyword Succeeds  5x  200ms  Click Element   //option[text()='${heading}']
	Input Title To Paragraph   ${Inp_Accordion_Title}
	Select Icon With Name   cogwheel
	Add SubContent To Accordion   ${contenttype}

Add Second Accordion
	Set Test Variable  ${pagetype}  ${pagetype}
	Set Test Variable  ${contenttype}  ${contenttype}
	Set Test Variable  ${color}  ${color}
	Set Test Variable  ${heading}  ${heading}
	Open Paragraph For Edit   ${Opt_AddAccordion}
	Sleep  1			# LETS WAIT A BIT SO THAT NEW ACCORDION IS ADDED 
	Wait Until Keyword Succeeds  5x  200ms  Input Title To Paragraph   ${Inp_Accordion_Title}
	Wait Until Keyword Succeeds  5x  200ms  Select From List By Label   ${Ddn_Accordion2_Icon}   check
	Wait Until Keyword Succeeds  6x  300ms  Add Text Content For Accordion
	${TextFileContent}=  Return Correct Content   ${language}
	Wait Until Keyword Succeeds  5x  200ms  Input Text To Frame   ${Frm_Accordion2_Content}   //body   ${TextFileContent} To Text Subcategory

Add Text Content For Accordion
	Click Element   ${Ddn_Accordion_AddContent}
	Wait Until Keyword Succeeds  5x  200ms  Element Should Be Visible   ${Opt_Accordion_Content_Text}
	Click Element   ${Opt_Accordion_Content_Text}
		
Add ${content} Content to Columns Subcategory
	${content}=  Convert To Lower Case   ${content}
	Run Keyword If  '${content}'=='picture'	 Wait Until Keyword Succeeds  5x  200ms  Add Pictures to Column
	Run Keyword If  '${content}'=='text'	 Wait Until Keyword Succeeds  5x  200ms  Add Text to Column
	Input Title To Paragraph   ${Inp_Column_Title}
	
Add Pictures to Column
	Add picture to Left Column
	Add picture to Right Column

Add Picture to Accordion
	Wait Until Keyword Succeeds  5x  300ms  Click Element  ${Btn_Accordion_Picture_Addnew}
	Wait Until Keyword Succeeds  6x  300ms  Choose File   ${Btn_File_Upload}   ${IMAGES_PATH}/train.jpg
	Wait Until Keyword Succeeds  6x  300ms  Input Text    ${Inp_Pic_Name}   Juna sillalla
	Input Text    ${Inp_Pic_AltText}   Vanha juna kuljettaa matkustajia 
	Input Text    ${Inp_Pic_Photographer}   Testi Valokuvaaja
	Click Button   ${Btn_Save}
	Wait Until Keyword Succeeds  5x  200ms  Submit New Media

Add Phasing To Accordion
	Input Phasing Data
	# LETS ADD MISSING DESCRIPTION FROM PHASING PARAGRAPH
	Input Description To Paragraph   (//div[contains(@id,'cke_edit-field-content')]//iframe)[2]    
	Create New Phase   Phase 2    Phase 2 Description Text	

Add Text to Column
	Add text to Left Column
	Add text to Right Column

Add Content To Text Subcategory
	${TextFileContent}=  Return Correct Content   ${language}
	Wait Until Keyword Succeeds  5x  200ms  Input Text To Frame   ${Frm_Accordion_Content}   //body   ${TextFileContent}
	
Add SubContent To Accordion 
	[Arguments]   ${content}
	Wait Until Element Is Visible   ${Ddn_Accordion_AddContent}   timeout=3
	Click Element	${Ddn_Accordion_AddContent}
	IF    '${content}'=='Text'
		Click Element   ${Opt_Accordion_Content_Text}
		Wait Until Keyword Succeeds  5x  200ms  Add Content To Text Subcategory
	ELSE IF   '${content}'=='Columns'
		Click Element   ${Opt_Accordion_Content_Columns}
	ELSE IF  '${content}'=='Picture'
		Click Element   ${Opt_Accordion_Content_Picture}
	ELSE IF  '${content}'=='Phasing'
		Click Element   ${Opt_Accordion_Content_Phasing}
	END
		
Accordions ${contenttype} Content Works As Expected
	Click Element  ${Btn_Accordion_View}
	IF    '${contenttype}'=='Columns'
		Wait Until Keyword Succeeds  5x  200ms  Columns Paragraph With Pictures Exist In Created Accordion
	ELSE IF    '${contenttype}'=='Picture'
		Picture Exists in Created Accordion
	ELSE IF    '${contenttype}'=='Text'
		Wait Until Keyword Succeeds  5x  200ms  Text Content Exists In Created Accordion
	ELSE IF    '${contenttype}'=='Phasing'
		phasing Is Present In Page
	END   
	

Text Content Exists In Created Accordion
	IF    '${TEST NAME}'=='Columns With Text'
        ${content}=  Return Content From Accordion Column Text Content
        Column Texts Matches To Expected Content  ${content}
    ELSE
    	${content}=  Accordion.Return Content From Page
    	Content Should Match Current Language Selection   ${content}
    END 

Column Texts Matches To Expected Content
	[Arguments]   ${contentlist} 
	${left}=  Get From List  ${contentlist}   0
	${right}=  Get From List  ${contentlist}   1
	Should Match Regexp  ${left}   Sitä Matti ajatteli, mitä rovastin ja ruustinnan kanssa oli puhunut
	Should Match Regexp  ${right}   Viittatie teki niemen nenässä polvekkeen
	

Columns Paragraph With Pictures Exist In Created Accordion
	${pic1}=  Get Element Attribute  //picture//img   src
	${pic2}=  Get Element Attribute  //picture//following::picture//img   src
	Should Contain   ${pic1}    train
	Should Contain   ${pic2}    temple

Picture Exists in Created Accordion
	${pic1}=  Get Element Attribute  //picture//img   src
	Should Contain   ${pic1}    train
			
Return Content From Page
	Wait Until Element Is Visible   css:#handorgel1-fold1-content > div > div > div > div > p   timeout=3	
	${content}=	Get Text    css:#handorgel1-fold1-content > div > div > div > div > p
	[Return]		${content}	

Return Content From Accordion Column Text Content
	Wait Until Element Is Visible   css:#handorgel1-fold1-content > div > div > div > div > div:nth-child(1) > div > div > div > p   timeout=3	
	${contentleft}=	Get Text    css:#handorgel1-fold1-content > div > div > div > div > div:nth-child(1) > div > div > div > p
	${contentright}=	Get Text    css:#handorgel1-fold1-content > div > div > div > div > div:nth-child(2) > div > div > div > p
	[Return]		${contentleft}   ${contentright}	