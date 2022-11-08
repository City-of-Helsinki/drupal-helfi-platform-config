*** Settings ***

Resource        ../Contenthandler.robot
Resource        ../Commonkeywords.robot

*** Keywords ***
Create New Columns Paragraph To Library
	Open Paragraph Add Page
	Wait Until Keyword Succeeds  5x  200ms  Input Text    ${Inp_Paragraph_Title}    Test_Automation_Add_From_Library_Columns  
	Click Element   ${Btn_Actions_Dropbutton}
	Click Element  ${Opt_Paragraph_AddColumns}
	Wait Until Keyword Succeeds  5x  200ms  Input Title To Paragraph  ${Inp_Paragraph_Columns_Title}
	Add Text to Left Column
	Add Text to Right Column
	Click Button   ${Btn_Paragraph_Submit}
	Wait Until Keyword Succeeds  5x  200ms  Element Should Not Be Visible   ${Btn_Paragraph_Submit}
	Set Test Variable  ${paragraphsadded}    ${paragraphsadded}+1

Create New Banner Paragraph To Library
	Open Paragraph Add Page
	Wait Until Keyword Succeeds  5x  200ms  Input Text    ${Inp_Paragraph_Title}    Test_Automation_Add_From_Library_Banner
	Click Element   ${Btn_Actions_Dropbutton}
	Click Element  ${Opt_Paragraph_AddBanner}
	Wait Until Keyword Succeeds  5x  200ms  Input Title To Paragraph   ${Inp_Paragraph_Banner_Title}
	Click Element   ${Opt_Banner_Left}
	Select Icon With Name   ticket
	Input Description To Paragraph   ${Frm_Content}
	Wait Until Keyword Succeeds  5x  100ms  Input Text   ${Inp_Paragraph_Banner_Link_Uri}   https://fi.wikipedia.org/wiki/Rautatie_(romaani)
	Input Text   ${Inp_Paragraph_Banner_Link_Text}   Test Automation Banner Link 
	Click Button   ${Btn_Paragraph_Submit}
	Wait Until Keyword Succeeds  5x  200ms  Element Should Not Be Visible   ${Btn_Paragraph_Submit}
	Set Test Variable  ${paragraphsadded}    ${paragraphsadded}+1 
 
Create New Accordion Paragraph To Library
	Open Paragraph Add Page
	Wait Until Keyword Succeeds  5x  200ms  Input Text    ${Inp_Paragraph_Title}    Test_Automation_Add_From_Library_Accordion
	Click Element   ${Btn_Actions_Dropbutton}
	Click Element  ${Opt_Paragraph_AddAccordion}
	Wait Until Element Is Visible   ${Inp_Paragraph_Accordion_Accordion1_Text}
	Input Title To Paragraph   ${Inp_Paragraph_Accordion_Accordion1_Text}
	Select Icon With Name   cogwheel
	Add SubContent To Accordion   Text
	Add Content To Text Subcategory
	Click Button   ${Btn_Paragraph_Submit}
	Wait Until Keyword Succeeds  5x  200ms  Element Should Not Be Visible   ${Btn_Paragraph_Submit}
	Set Test Variable  ${paragraphsadded}    ${paragraphsadded}+1  

Create New ContentCards Paragraph To Library
	Open Paragraph Add Page
	Wait Until Keyword Succeeds  5x  200ms  Input Text    ${Inp_Paragraph_Title}    Test_Automation_Add_From_Library_ContentCards 
	Click Element   ${Btn_Actions_Dropbutton}
	Click Element  ${Opt_Paragraph_AddContentCards}
	Wait Until Keyword Succeeds  5x  200ms  Input Title To Paragraph  ${Inp_Paragraph_ContentCards_Title}
	Input Text   ${Inp_Paragraph_ContentCard_TargetId}   Esimerkkisivu
	Wait Until Keyword Succeeds  5x  100ms  Click Element   //a[contains(text(),'Esimerkkisivu')]
	Click Button   ${Btn_Paragraph_Submit}
	Wait Until Keyword Succeeds  5x  200ms  Element Should Not Be Visible   ${Btn_Paragraph_Submit}
	Set Test Variable  ${paragraphsadded}    ${paragraphsadded}+1

Create New Picture Paragraph To Library
	Open Paragraph Add Page
	Wait Until Keyword Succeeds  5x  200ms  Input Text    ${Inp_Paragraph_Title}    Test_Automation_Add_From_Library_Picture  
	Click Element   ${Btn_Actions_Dropbutton}
	Click Element  ${Opt_Paragraph_AddPicture}
	Add Picture 'train' And Caption To 1:th Picture
	Sleep  2     # small wait needed so page gets loaded
	Click Button   ${Btn_Paragraph_Submit}
	Wait Until Keyword Succeeds  5x  200ms  Element Should Not Be Visible   ${Btn_Paragraph_Submit}
	Set Test Variable  ${paragraphsadded}    ${paragraphsadded}+1

Create New LiftupWithImage Paragraph To Library
	Open Paragraph Add Page
	Wait Until Keyword Succeeds  5x  200ms  Input Text    ${Inp_Paragraph_Title}    Test_Automation_Add_From_Library_LiftupWithImage 
	Click Element   ${Btn_Actions_Dropbutton}
	Click Element  ${Opt_Paragraph_AddLiftupWithImage}
	Wait Until Keyword Succeeds  5x  200ms  Input Title To Paragraph    ${Inp_LiftupWithImage_Title}
	Add Picture 'train' And Caption To 1:th Picture
	Input Description To Paragraph   ${Frm_Content}
	Wait Until Keyword Succeeds  5x  200ms  Click Button   ${Btn_Paragraph_Submit}
	Wait Until Keyword Succeeds  5x  200ms  Element Should Not Be Visible   ${Btn_Paragraph_Submit}
	Set Test Variable  ${paragraphsadded}    ${paragraphsadded}+1

Create New ListOfLinks Paragraph To Library
	Open Paragraph Add Page
	Wait Until Keyword Succeeds  5x  200ms  Input Text    ${Inp_Paragraph_Title}    Test_Automation_Add_From_Library_ListOfLinks  
	Click Element   ${Btn_Actions_Dropbutton}
	Click Element  ${Opt_Paragraph_AddListOfLinks}
	Wait Until Keyword Succeeds  5x  200ms  Input Title To Paragraph    ${Inp_Paragraph_ListOfLinks_Title}
	Input Text  ${Inp_ListOfLinks_Link_Uri}   /fi/linkkiesimerkit
	Input Text  ${Inp_ListOfLinks_Link_Title}   Linkkiesimerkit
	Add Picture 'train' And Caption To 1:th Picture
	Sleep  2     # small wait needed so page gets loaded
	Wait Until Keyword Succeeds  5x  200ms  Click Button   ${Btn_Paragraph_Submit}
	Wait Until Keyword Succeeds  5x  200ms  Element Should Not Be Visible   ${Btn_Paragraph_Submit}
	Set Test Variable  ${paragraphsadded}    ${paragraphsadded}+1

Create New Text Paragraph To Library
	Open Paragraph Add Page
	Wait Until Keyword Succeeds  5x  200ms  Input Text    ${Inp_Paragraph_Title}    Test_Automation_Add_From_Library_Text  
	Click Element   ${Btn_Actions_Dropbutton}
	Click Element  ${Opt_Paragraph_AddText}
	${TextFileContent}=  Return Correct Content   ${language}
	Wait Until Keyword Succeeds  5x  200ms  Input Text To Frame   ${Frm_Content}   //body   ${TextFileContent}
	Wait Until Keyword Succeeds  5x  200ms  Click Button   ${Btn_Paragraph_Submit}
	Wait Until Keyword Succeeds  5x  200ms  Element Should Not Be Visible   ${Btn_Paragraph_Submit}
	Set Test Variable  ${paragraphsadded}    ${paragraphsadded}+1

Create New ${lang_selection} SidebarText Paragraph To Library
	${language_pointer}=   Get Language Pointer   ${lang_selection}
	Set Test Variable   ${language}   ${language_pointer}
	Open Paragraph Add Page
	Wait Until Keyword Succeeds  5x  200ms  Input Text    ${Inp_Paragraph_Title}    Test_Automation_Add_From_Library_SidebarText  
	Click Element   ${Btn_Actions_Dropbutton}
	Click Element  ${Opt_Paragraph_AddSidebarText}
	Wait Until Keyword Succeeds  5x  200ms  Input Title To Paragraph    ${Inp_Paragraph_SidebarText_Title}
	Wait Until Keyword Succeeds  5x  200ms  Input Description To Paragraph   ${Frm_Content}
	
	Wait Until Keyword Succeeds  5x  200ms  Click Button   ${Btn_Paragraph_Submit}
	Wait Until Keyword Succeeds  5x  200ms  Element Should Not Be Visible   ${Btn_Paragraph_Submit}
	Set Test Variable  ${paragraphsadded}    ${paragraphsadded}+1

Create New Unit Search Paragraph To Library
	Open Paragraph Add Page
	Wait Until Keyword Succeeds  5x  200ms  Input Text    ${Inp_Paragraph_Title}    Test_Automation_Add_From_Library_Unitsearch  
	Click Element   ${Btn_Actions_Dropbutton}
	Click Element  ${Opt_Paragraph_UnitSearch}
	Wait Until Keyword Succeeds  5x  200ms  Input Title To Paragraph    ${Inp_Paragraph_UnitSearch_Title}
	Wait Until Keyword Succeeds  5x  200ms  Select From List By Index   ${Sel_UnitSearch_Units}   0
	Wait Until Keyword Succeeds  5x  200ms  Input Description To Paragraph   ${Frm_Content}
	Wait Until Keyword Succeeds  5x  200ms  Click Button   ${Btn_Paragraph_Submit}
	Wait Until Keyword Succeeds  5x  200ms  Element Should Not Be Visible   ${Btn_Paragraph_Submit}
	Set Test Variable  ${paragraphsadded}    ${paragraphsadded}+1


 
Add SubContent To Accordion 
	[Arguments]   ${content}
	Wait Until Element Is Visible   ${Ddn_Accordion_AddContent}   timeout=3
	Click Element	${Ddn_Accordion_AddContent}
	Run Keyword If  '${content}'=='Text'  Click Element   ${Opt_Accordion_Content_Text}
	Run Keyword If  '${content}'=='Columns'  Click Element   ${Opt_Accordion_Content_Columns}

Add Content To Text Subcategory
	${TextFileContent}=  Return Correct Content   ${language}
	Wait Until Keyword Succeeds  5x  200ms  Input Text To Frame   ${Frm_Content}   //body   ${TextFileContent}
	 
Open Paragraph Add Page
	Go To   ${URL_paragraphs_add_page}

	
Create ${paragraph} -Paragraph ${pagetype} Content 
	${islandingpage}=  Suite Source Contains Text    Landing_Page
	Set Test Variable   ${paragraph}   ${paragraph}
	Input Non-paragraph Related Content   ${pagetype}
	Run Keyword If  not(${islandingpage})  Open Paragraph For Edit   ${Opt_AddFromLibrary_Lower}   ${Ddn_AddContent_Lower}
	Run Keyword If  ${islandingpage}  Open Paragraph For Edit   ${Opt_AddFromLibrary}
	Wait Until Keyword Succeeds  6x  300ms  Click Element   //option[contains(text(),'Test_Automation_Add_From_Library_${paragraph}')]
	Wait Until Keyword Succeeds  6x  300ms  Submit The New ${pagetype}

Add Picture '${name}' And Caption To ${number}:th Picture
	${number}=   Convert To Integer   ${number}
	Run Keyword If  ${number}>=2   Click Element   ${Btn_Gallery_Picture_Addmore}
	${editgalleryvisible}=  Run Keyword And Return Status    Wait Until Element Is Visible  name:field_content_0_edit   timeout=1
	Run Keyword If  ${editgalleryvisible}  Wait Until Keyword Succeeds  5x  500ms  Click Edit Paragraph
	Run Keyword If  ${editgalleryvisible}  Wait Until Keyword Succeeds  5x  200ms  Click Element   ${Btn_Gallery_Picture_Addmore}
	Run Keyword If  '${name}'=='tulips'   Sleep   1    # Sleep due issues of missing paragraph in page
	Wait Until Keyword Succeeds  5x  500ms   Open Add Picture   ${number}
	@{content}=  Set Variable  @{pic_1_texts_${language}}
	${pictitle}=  Get From List  ${content}   0
	${picdescription}=  Get From List  ${content}   1
	${pgrapher}=  Get From List  ${content}   2
	Wait Until Keyword Succeeds  6x  300ms  Choose File   ${Btn_File_Upload}   ${IMAGES_PATH}/${name}.jpg
	Wait Until Keyword Succeeds  6x  300ms  Input Text    ${Inp_Pic_Name}   ${pictitle}
	Input Text    ${Inp_Pic_AltText}   ${picdescription}
	Input Text    ${Inp_Pic_Photographer}   ${pgrapher}
	Run Keyword If  '${language}'=='fi'   Click Button   ${Btn_Save}
	Run Keyword If  '${language}'=='en'   Click Button   ${Btn_Save_En}
	Run Keyword If  '${language}'=='sv'   Click Button   ${Btn_Save_Sv}
	Wait Until Keyword Succeeds  5x  200ms  Submit New Media
	Run Keyword If  '${language}'=='en'   Sleep  0.1    # DUE SOME ISSUES WITH PRINTING ENGLISH CAPTION
	Run Keyword If  '${TEST NAME}'=='Gallery'  Wait Until Keyword Succeeds  5x  200ms   Input Text      ${Tar_Paragraph_Gallery_Image_Caption}   ${pic_1_caption_${language}}
	Run Keyword If  '${TEST NAME}'=='Picture'  Wait Until Keyword Succeeds  5x  200ms   Input Text      ${Tar_Paragraph_Picture_Image_Caption}   ${pic_1_caption_${language}}
	Set Test Variable  ${picture}    picture

Open Add Picture
	[Arguments]   ${number}
	Run Keyword If  '${TEST NAME}'=='Gallery'  Wait Until Element Is Visible   ${Btn_Paragraph_Gallery_Picture}${number-1}-subform   timeout=4
	Run Keyword If  '${TEST NAME}'=='Picture'  Wait Until Element Is Visible   ${Btn_Paragraph_Image_Picture}   timeout=4
	Run Keyword If  ('${TEST NAME}'=='LiftupWithImage') | ('${TEST NAME}'=='Landingpage-LiftupWithImage')  Wait Until Element Is Visible   ${Btn_Paragraph_LiftupWithImage_Picture}   timeout=4
	Run Keyword If  ('${TEST NAME}'=='ListOfLinks') | ('${TEST NAME}'=='Landingpage-ListOfLinks')  Wait Until Element Is Visible   ${Btn_Paragraph_ListOfLinks_Picture}   timeout=4
	Run Keyword If  '${TEST NAME}'=='Gallery'  Wait Until Keyword Succeeds  5x  200ms  Click Element	${Btn_Paragraph_Gallery_Picture}${number-1}-subform
	Run Keyword If  '${TEST NAME}'=='Picture'  Wait Until Keyword Succeeds  5x  200ms  Click Element	${Btn_Paragraph_Image_Picture}
	Run Keyword If  ('${TEST NAME}'=='LiftupWithImage') | ('${TEST NAME}'=='Landingpage-LiftupWithImage')  Wait Until Keyword Succeeds  5x  200ms  Click Element	${Btn_Paragraph_LiftupWithImage_Picture}
	Run Keyword If  ('${TEST NAME}'=='ListOfLinks') | ('${TEST NAME}'=='Landingpage-ListOfLinks')  Set Focus To Element   ${Btn_Paragraph_ListOfLinks_Picture}
	Run Keyword If  ('${TEST NAME}'=='ListOfLinks') | ('${TEST NAME}'=='Landingpage-ListOfLinks')  Wait Until Keyword Succeeds  5x  100ms  Press Keys    None    RETURN
	Wait Until Keyword Succeeds  5x  300ms  Element Should Be Visible   name:files[upload] 

Focus And Click ListOfLinks Add Picture Button
	Execute javascript  window.scrollTo(0, 800)
	Click Element	${Btn_Paragraph_ListOfLinks_Picture}

Page Should Have Filled ${paragraph} Paragraph From Library
	Run Keyword And Ignore Error  Accept Cookies
	Page Content Matches Language

Page Content Matches Language
	${islandingpage}=  Suite Source Contains Text    Landing_Page
	# CONTENT FETCH FOR VALIDATIONS
    IF  '${TEST NAME}'=='Accordion'
        Wait Until Keyword Succeeds  5x  200ms  Click Element  ${Btn_Accordion_View}
    END
    IF    (not('${TEST NAME}'=='Gallery')) & (not('${TEST NAME}'=='Picture')) & (not('${TEST NAME}'=='Text'))
    	${Title}=  Return Title From Page
    END
    IF   not(${islandingpage})
    	${Lead-in}=    Return Lead-in From Page
    END
    IF   (${islandingpage} & (not('${TEST NAME}'=='Landingpage-Columns')) & (not('${TEST NAME}'=='Landingpage-ContentCards')) & (not('${TEST NAME}'=='Landingpage-ListOfLinks'))) | ('${TEST NAME}'=='Landingpage-Accordion') | ('${TEST NAME}'=='Unit Search') | ('${TEST NAME}'=='Landingpage-Unit Search')
    	${Description}=    Return Description From Page
    END
    IF  (not('${TEST NAME}'=='ContentCards')) & (not('${TEST NAME}'=='Landingpage-ContentCards')) & (not('${TEST NAME}'=='Gallery')) & (not('${TEST NAME}'=='Picture')) & (not('${TEST NAME}'=='ListOfLinks')) & (not('${TEST NAME}'=='Landingpage-ListOfLinks')) & (not('${TEST NAME}'=='LiftupWithImage')) & (not('${TEST NAME}'=='Landingpage-LiftupWithImage')) & (not('${TEST NAME}'=='Banner')) & (not('${TEST NAME}'=='Landingpage-Banner')) & (not('${TEST NAME}'=='Accordion')) & (not('${TEST NAME}'=='Unit Search')) & (not('${TEST NAME}'=='Landingpage-Unit Search'))
    	${Content}=  Add_From_Library.Return Content From Page
    END
    IF  	('${TEST NAME}'=='Banner') | ('${TEST NAME}'=='Landingpage-Banner') | ('${TEST NAME}'=='ListOfLinks') | ('${TEST NAME}'=='Landingpage-ListOfLinks') | ('${TEST NAME}'=='ContentCards') | ('${TEST NAME}'=='Landingpage-ContentCards')
    	${Linktext}=  Return Link Text From Page
    END
    IF  	('${TEST NAME}'=='Gallery') | ('${TEST NAME}'=='Picture')
    	${Piccaption}=   Return Picture Caption From Page
    END

	# CONTENT VALIDATIONS
	IF  (not('${TEST NAME}'=='Gallery')) & (not('${TEST NAME}'=='Picture')) & (not('${TEST NAME}'=='Text'))
    	Title Should Match Current Language Selection   ${Title}
    END
    IF   ((${islandingpage}) & (not('${TEST NAME}'=='Landingpage-Columns')) & (not('${TEST NAME}'=='Landingpage-ContentCards')) & (not('${TEST NAME}'=='Landingpage-ListOfLinks'))) | ('${TEST NAME}'=='Landingpage-Accordion') | ('${TEST NAME}'=='Unit Search') | ('${TEST NAME}'=='Landingpage-Unit Search')
    	Description Should Match Current Language Selection   ${Description}
    END
    IF   not(${islandingpage})
    	Lead-in Should Match Current Language Selection   ${Lead-in}
    END
    IF  ((${islandingpage}) & ('${TEST NAME}'=='Landingpage-Columns')) | (not('${TEST NAME}'=='ContentCards')) & (not('${TEST NAME}'=='Landingpage-ContentCards')) & (not('${TEST NAME}'=='Gallery')) & (not('${TEST NAME}'=='Picture')) & (not('${TEST NAME}'=='LiftupWithImage')) & (not('${TEST NAME}'=='Landingpage-LiftupWithImage')) & (not('${TEST NAME}'=='ListOfLinks')) & (not('${TEST NAME}'=='Landingpage-ListOfLinks')) & (not('${TEST NAME}'=='Banner')) & (not('${TEST NAME}'=='Landingpage-Banner')) & (not('${TEST NAME}'=='Accordion')) & (not('${TEST NAME}'=='Unit Search')) & (not('${TEST NAME}'=='Landingpage-Unit Search'))
    	  Content Should Match Current Language Selection   ${Content}
    END
    
    IF  ('${TEST NAME}'=='Banner') | ('${TEST NAME}'=='Landingpage-Banner') | ('${TEST NAME}'=='ListOfLinks') | ('${TEST NAME}'=='Landingpage-ListOfLinks') | ('${TEST NAME}'=='ContentCards') | ('${TEST NAME}'=='Landingpage-ContentCards')
    	  LinkText Is Correct   ${Linktext}
    END
    IF   ('${TEST NAME}'=='Gallery') | ('${TEST NAME}'=='Picture')
    	  Picture Caption Is Correct   ${Piccaption}
    END
		
Return Title From Page
	IF    ('${TEST NAME}'=='Columns') | ('${TEST NAME}'=='Landingpage-Columns') | ('${TEST NAME}'=='Unit Search') | ('${TEST NAME}'=='Landingpage-Unit Search')
        ${title}=   Get Text    ${Txt_Title}
    ELSE IF    ('${TEST NAME}'=='Banner') | ('${TEST NAME}'=='Landingpage-Banner')
        ${title}=   Get Text    ${Txt_Banner_Title}
    ELSE IF    '${TEST NAME}'=='Accordion'
    	Wait Until Element Is Visible   ${Txt_Accordion_Title}
    	${title}=   Get Text    ${Txt_Accordion_Title}
    ELSE IF  ('${TEST NAME}'=='ContentCards') | ('${TEST NAME}'=='Landingpage-ContentCards')
    	${title}=   Get Text    ${Txt_ContentCards_Title}
    ELSE IF  ('${TEST NAME}'=='LiftupWithImage') | ('${TEST NAME}'=='Landingpage-LiftupWithImage')	
    	${title}=   Get Text    ${Txt_LiftupWithImage_Title}
    ELSE IF  ('${TEST NAME}'=='ListOfLinks') | ('${TEST NAME}'=='Landingpage-ListOfLinks')	
    	${title}=   Get Text    ${Txt_Title}
    END
	[Return]		${title}

Return Description From Page
	IF   (not('${TEST NAME}'=='Accordion')) & (not('${TEST NAME}'=='Unit Search')) & (not('${TEST NAME}'=='Landingpage-Unit Search'))
		${description}=	   Get Text    ${Txt_Description}
	ELSE
		${description}=	   Get Text    ${Txt_Component_Description}
	END
	[Return]		${description}

Return Content From Page
	IF   '${TEST NAME}'=='Accordion'
		Sleep  2
	END
	${content}= 	Get Text    ${Txt_Content}
	[Return]		${content}
	
Return Link Text From Page
	IF    ('${TEST NAME}'=='Banner') | ('${TEST NAME}'=='Landingpage-Banner')
		${linktxt}=	 Get Text    ${Txt_Banner_Link}
	ELSE IF   ('${TEST NAME}'=='ListOfLinks') | ('${TEST NAME}'=='Landingpage-ListOfLinks')
		${linktxt}=	 Get Text    ${Txt_ListOfLinks_Link}
	ELSE IF    ('${TEST NAME}'=='ContentCards') | ('${TEST NAME}'=='Landingpage-ContentCards')
		${linktxt}=	 Get Text    ${Txt_ContentCards_Link}
	END
	[Return]		${linktxt}

Return Picture Caption From Page
	${caption}=	 Get Text    ${Txt_Gallery_Image_Caption}
	[Return]		${caption}

Picture Caption Is Correct
	[Arguments]   ${caption}
	Should Contain    ${caption}    ${pic_1_caption_${language}}
	
LinkText Is Correct
	[Arguments]   ${linktext}
	${linktext}=  Replace Encoded Characters From String   ${linktext}   ${EMPTY}    UTF-8    \\xc2\\xad
	IF    ('${TEST NAME}'=='Banner') | ('${TEST NAME}'=='Landingpage-Banner')
		Should Match    ${linktext}    Test Automation Banner Link
	ELSE IF   ('${TEST NAME}'=='ListOfLinks') | ('${TEST NAME}'=='Landingpage-ListOfLinks')
		Should Match    ${linktext}    Linkkiesimerkit
	ELSE IF   (('${TEST NAME}'=='ContentCards') | ('${TEST NAME}'=='Landingpage-ContentCards')) & ('${language}'=='fi')
		Should Match    ${linktext}    Esimerkkisivu
	ELSE IF   (('${TEST NAME}'=='ContentCards') | ('${TEST NAME}'=='Landingpage-ContentCards')) & ('${language}'=='en')
		Should Match    ${linktext}    Link examples
	END