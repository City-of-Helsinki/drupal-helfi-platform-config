*** Settings ***

Resource        ../Contenthandler.robot
Resource        ../Commonkeywords.robot

*** Keywords ***
Create ${pagetype} With List Of Links ${style}
	Set Test Variable  ${style}  ${style}
	Input Non-paragraph Related Content   ${pagetype}
	Open Paragraph For Edit   ${Opt_AddListOfLinks}
	${style}=   Resolve Style Variable   ${style}
	Wait Until Keyword Succeeds  5x  200ms  Select From List By Value  ${Inp_ListOfLinks_Design}  ${style}
	Input Text  ${Inp_ListOfLinks_Link_Uri}   /en/link-examples
	Input Text  ${Inp_ListOfLinks_Link_Title}   Link Examples
	Click Element   ${Swh_ListOfLinks_Link_OpenInNewTab}
	Click Element   ${Swh_ListOfLinks_Link_LinkIsAccessible}
	Run Keyword If   ('${TEST NAME}'=='With Picture') | ('${TEST NAME}'=='Landingpage-With Picture')   Add Picture To Link   train
	...   ELSE IF   ('${TEST NAME}'!='Without Picture And Description') & ('${TEST NAME}'!='Landingpage-Without Picture And Description')   Wait Until Keyword Succeeds  5x  200ms   Input Text    ${Inp_ListOfLinks_Link_Description}    Siirry linkin sisältöön tästä
	
	
Add Picture To Link
	[Arguments]   ${picname}
	${islandingpage}=  Suite Source Contains Text    Landing_Page
	Wait Until Keyword Succeeds  5x  200ms  Click Element  ${Inp_ListOfLinks_Link_AddPicture}
	Wait Until Keyword Succeeds  6x  300ms  Choose File   ${Btn_File_Upload}   ${IMAGES_PATH}/${picname}.jpg
	Wait Until Keyword Succeeds  6x  300ms  Set Focus To Element  ${Inp_Pic_Name}
	Input Text    ${Inp_Pic_Name}   Juna sillalla
	Input Text    ${Inp_Pic_AltText}   Vanha juna kuljettaa matkustajia 
	Input Text    ${Inp_Pic_Photographer}   Testi Valokuvaaja
	Click Button   ${Btn_Save}
	Wait Until Keyword Succeeds  5x  200ms  Submit New Media
	Wait Until Element Is Visible  //input[contains(@data-drupal-selector, 'remove-button')]   timeout=3
	Set Test Variable  ${picture}   picture
	
Resolve Style Variable
	[Arguments]  ${style}
	${style}=  Convert To Lower Case   ${style}
	${stylevariable}=   Set Variable If   '${style}'=='with picture'    with-image
	...			'${style}'=='without picture'    without-image
	...			'${style}'=='without picture and description'    without-image-desc
	[Return]   ${stylevariable}

Add Second Link For Content
	Wait Until Keyword Succeeds  5x  300ms  Click Element  ${Inp_ListOfLinks_Link_NewLink}
	Sleep  1    # Small sleep due issues with fields accepting input for second link
	Wait Until Keyword Succeeds  5x  300ms  Input Text   ${Inp_ListOfLinks_Link_Uri}   /fi/esimerkkisivu
	Wait Until Keyword Succeeds  5x  300ms  Input Text   ${Inp_ListOfLinks_Link_Title}   Esimerkkisivu
	Run Keyword If   ('${TEST NAME}'=='With Picture') | ('${TEST NAME}'=='Landingpage-With Picture')   Add Picture To Link    tulips
	...   ELSE IF  ('${TEST NAME}'!='Without Picture And Description') & ('${TEST NAME}'!='Landingpage-Without Picture And Description')    Input Text    ${Inp_ListOfLinks_Link_Description}    Klikkaa tästä siirtyäksesi
	
	
		
List Of Links Is Working Correctly
	[Documentation]   Link Examples is opened in new window when Esimerkkisivu opens in current.
	${contentpageurl}=   Get Location
	Click Link   Link Examples
	New Window Should Be Opened    Link examples | Helsingin kaupunki
	Click Link   Esimerkkisivu
	${currenturl}=   Get Location
	Should Contain   ${currenturl}   esimerkkisivu
	
Layout Should Not Have Changed
	Run Keyword And Ignore Error  Accept Cookies
	Capture Screenshot For Picture Comparison    css=main.layout-main-wrapper
	Compare Two Pictures	