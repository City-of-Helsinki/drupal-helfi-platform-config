*** Settings ***

Resource        ../Contenthandler.robot
Resource        ../Commonkeywords.robot

*** Variables ***
${color}	 		 ${EMPTY}

*** Keywords ***

Return Hero Title From Page
	${title}=	Get Text    ${Txt_Hero_Title}
	[Return]		${title}

Return Hero Description From Page
	${description}=	Get Text    ${Txt_Hero_Description}
	[Return]		${description}
#LandingPage
Create a ${value} Aligned ${pagetype} With Hero Block In ${lang_selection} Language
	${language_pointer}=   Get Language Pointer   ${lang_selection}
	Set Test Variable   ${language}   ${language_pointer}
	Run Keyword If  '${lang_selection}'=='Finnish'  Go To New ${pagetype} Site
	Run Keyword If  '${lang_selection}'!='Finnish'  Go To New ${pagetype} -View For ${lang_selection} Translation
	Start Creating a ${value} Aligned Page With Hero Block
	Submit The New ${pagetype}
 
Start Creating a ${value} Aligned Page With Hero Block 
	Set Test Variable   ${value}    ${value} 
	${islandingpage}=   Suite Source Contains Text   Landing_Page
	Set Test Variable   ${islandingpage}   ${islandingpage}
	${containslink}=    Run Keyword And Return Status    Should Contain    ${TEST NAME}    Link
	Input Title  Test Automation: ${SUITE}.${TEST NAME}
	${titleisvisible}=  Run Keyword And Return Status   Element Should Be Enabled   ${Inp_Hero_Title}
	IF    not(${titleisvisible})
		Click Element   ${Swh_HeroOnOff}
	END
	Wait Until Keyword Succeeds  6x  300ms  Run Keyword If  '${value}'=='Center'  Click Element   ${Ddn_Hero_Alignment}
	Run Keyword If  '${value}'=='Center'  Wait Until Keyword Succeeds  5x  200ms  Click Element   ${Opt_Hero_Alignment_Center} 
	Wait Until Keyword Succeeds  6x  300ms   Input Title To Paragraph   ${Inp_Hero_Title}
	${TextFileContent}=  Return Correct Content   ${language}
	@{content} =	Split String	${TextFileContent}   .,.
	${content_up}=  Get From List  ${content}   0
	${content_down}=  Get From List  ${content}   1
	${TextFileDescription}=  Return Correct Description   ${language}

    IF    ${islandingpage} & ('${TEST NAME}'!='Finnish English Swedish Translations')
        Handle LandingPage Content And Description   ${content_up}
    ELSE IF    ('${TEST NAME}'=='Finnish English Swedish Translations') & (${islandingpage}==False)
        Insert Description and Lead-In For Page   ${TextFileDescription}   ${content_up}
    ELSE IF    ('${TEST NAME}'=='Finnish English Swedish Translations') & (${islandingpage}==True)
     	Run Keyword If  '${language}'=='fi'  Wait Until Keyword Succeeds  5x  100ms   Input Description To Paragraph   css:#cke_1_contents > iframe
    	Run Keyword If  '${language}'!='fi'  Wait Until Keyword Succeeds  5x  100ms   Input Description To Paragraph   ${Frm_Content}
    	Add Lead-In Text   ${Tar_Page_LeadIn}	${content_up}    
    ELSE
    	Insert Description and Lead-In For Page   ${TextFileDescription}   ${content_up} 
    END

Handle Page Content And Description
	[Arguments]   ${TextFileDescription}   ${content_up}
	# In case of link we need to add some linebreaks '\n'
	Input Text Content To Frame   ${TextFileDescription}\n   cke_1_contents
	Add Lead-In Text  ${Tar_Page_LeadIn}   ${content_up}
	

Handle LandingPage Content And Description
	[Arguments]   ${content_up}
	# In case of link we need to add some linebreaks '\n'
	Input Text Content To Frame   ${content_up}\n   cke_1_contents

Insert Description and Lead-In For Page
	[Arguments]   ${description}   ${content}
	Input Text Content To Frame   ${description}   cke_1_contents
	Add Lead-In Text   ${Tar_Page_LeadIn}	${content}
	
Add Lead-In Text
	[Arguments]   ${locator}   ${content}    
	Input Text    ${locator}   ${content}

	
Start Creating Hero Block Page with ${picalign} Picture 
	Start Creating a Left Aligned Page With Hero Block
    Set Test Variable   ${picture}  picture
    Set Test Variable   ${picalign}   ${picalign}    
	Run Keyword If  '${picalign}'=='Left'  Click Element   ${Opt_Hero_Picture_On_Left}
	Run Keyword If  '${picalign}'=='Right'  Click Element   ${Opt_Hero_Picture_On_Right}
	Run Keyword If  '${picalign}'=='Bottom'  Click Element   ${Opt_Hero_Picture_On_Bottom}
	Run Keyword If  '${picalign}'=='Background'  Click Element   ${Opt_Hero_Picture_On_Background}
	Run Keyword If  '${picalign}'=='Diagonal'  Click Element   ${Opt_Hero_Diagonal}
	# For some reason any locator does not find this so last effort was focusing element and simulating keyboard enter
	Wait Until Keyword Succeeds  5x  100ms  Set Focus To Element  ${Btn_Hero_Picture}
	Wait Until Keyword Succeeds  5x  100ms  Press Keys    None    RETURN
	Wait Until Keyword Succeeds  6x  300ms  Choose File   ${Btn_File_Upload}   ${IMAGES_PATH}/train.jpg
	Wait Until Keyword Succeeds  6x  200ms  Input Text    ${Inp_Pic_Name}   Juna sillalla
	Input Text    ${Inp_Pic_AltText}   Vanha juna kuljettaa matkustajia 
	Input Text    ${Inp_Pic_Photographer}   Testi Valokuvaaja
	Click Button   ${Btn_Save}
	Wait Until Keyword Succeeds  5x  200ms  Submit New Media
	Wait Until Element Is Visible  //input[@data-drupal-selector='edit-field-hero-0-subform-field-hero-image-selection-0-remove-button']   timeout=3
	  


Add Hero Link Button With ${style} Style
	Set Test Variable   ${linkstyle}  ${style}
	Run Keyword If  '${picalign}'=='Background'   Add ${style} Link In Hero Content Paragraph   
	...    ELSE 	Add ${style} Link In Text Editor


Add ${style} Link In Hero Content Paragraph
	Click Button   ${Btn_Hero_AddLink}
	Wait Until Keyword Succeeds  5x  100ms  Input Text   ${Inp_Hero_Link_URL}   https://fi.wikipedia.org/wiki/Rautatie_(romaani)    
	Input Text   ${Inp_Hero_Link_Title}    ${link_title_${language}}
	Click Element  ${Ddn_Hero_Link_Design}
	Run Keyword If  '${style}'=='Fullcolor'  Click Element   ${Opt_Hero_Link_ButtonFullcolor}
	Run Keyword If  '${style}'=='Framed'  Click Element   ${Opt_Hero_Link_ButtonFramed}
	Run Keyword If  '${style}'=='Transparent'  Click Element   ${Opt_Hero_Link_ButtonTransparent}


Add ${style} Link In Text Editor
	Set Focus To Element   //a[contains(@title, 'Link')]
	Click Element   //a[contains(@title, 'Link')]
	Wait Until Keyword Succeeds  5x  100ms  Input Text   ${Inp_Hero_Link_Texteditor_URL}   https://fi.wikipedia.org/wiki/Rautatie_(romaani)    
	Input Text   ${Inp_Hero_Link_Texteditor_Title}    ${link_title_${language}}
	Wait Until Element Is Visible   ${Ddn_Hero_Link_Texteditor_Design}   timeout=3
	Wait Until Keyword Succeeds  5x  200ms  Click Element  ${Ddn_Hero_Link_Texteditor_Design}
	
	Run Keyword If  '${style}'=='Fullcolor'  Click Element   ${Opt_Hero_Link_Texteditor_ButtonFullcolor}
	Run Keyword If  '${style}'=='Framed'  Click Element   ${Opt_Hero_Link_Texteditor_ButtonFramed}
	Run Keyword If  '${style}'=='Transparent'  Click Element   ${Opt_Hero_Link_Texteditor_ButtonTransparent}
	Click Button   ${Btn_Save}

Add ${color} As Background Color
	Set Test Variable   ${color}  ${color}
	Click Element Using JavaScript Xpath  ${Ddn_Hero_Color}
	Click Element With Value   ${color}

Input Hero Description
	[Arguments]   ${description}   ${cke}
	[Documentation]	  Here. In translation cases cke -identifier numbers have changed. Thus some if else is needed.
	Run Keyword If  '${language}'=='fi'	Input Text To Frame   css:#${cke}_contents > iframe   //body   ${description}
	Run Keyword If  '${language}'!='fi'   Input Text To Frame   ${Frm_Content}   //body   ${description}

Layout Should Not Have Changed
	Run Keyword And Ignore Error  Accept Cookies
	Capture Screenshot For Picture Comparison    css=main.layout-main-wrapper
	Compare Two Pictures	

