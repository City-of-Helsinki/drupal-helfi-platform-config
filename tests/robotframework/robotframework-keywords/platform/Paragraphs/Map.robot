*** Settings ***

Resource        ../Contenthandler.robot
Resource        ../Commonkeywords.robot

*** Keywords ***

Create Map With Given Url
	[Arguments]   ${URL}
	Set Test Variable   ${URL}   ${URL}
	Input Non-paragraph Related Content   Page
	Open Paragraph For Edit   ${Opt_AddMap}
	Wait Until Keyword Succeeds  5x  100ms  Input Text   ${Inp_Map_Title}   ${TEST NAME}
	${description}=  Return Correct Description   ${language}
	Input Text  ${Inp_Map_Description}   ${description}
	Wait Until Keyword Succeeds  5x  200ms  Input Text   ${Assistive_Technology_Title}   Avustavan teknologian otsikko
	Wait Until Keyword Succeeds  6x  300ms   Add Url To Map
	Wait Until Keyword Succeeds  6x  300ms  Submit New Media

Add Url To Map	
	Wait Until Keyword Succeeds  6x  300ms  Click Element   ${Btn_Map_Add}
	Wait Until Keyword Succeeds  6x  300ms  Input Text   name:helfi_media_map_url   ${URL}
	Wait Until Keyword Succeeds  6x  300ms  Set Focus To Element	  ${Btn_Map_Url_Add}
	Wait Until Keyword Succeeds  6x  300ms  Click Button  ${Btn_Map_Url_Add}
	Wait Until Keyword Succeeds  6x  300ms  Click Button  ${Btn_Save}
	
Map Paragraph Works Correctly
	Wait Until Keyword Succeeds  5x   200ms   Run Keyword And Ignore Error   Accept Cookies
	${ispalvelukartta}=  URL Contains Text   palvelukartta
	IF    ${ispalvelukartta}
        Wait Until Element Is Visible  ${Itm_Map_Palvelukartta}
        Select Frame   ${Itm_Map_Palvelukartta}
    ELSE
    	Wait Until Element Is Visible  ${Itm_Map_Kartta}
    	Select Frame   ${Itm_Map_Kartta}
    END 
	Run Keyword And Ignore Error   Wait Until Keyword Succeeds  5x   200ms   Click Button   ${Btn_Map_Palvelukartta_AllowCookies}
	Sleep  2
	
	IF    ${ispalvelukartta}
        Run Keyword If   not(${CI})  Capture Element Screenshot   css:#app > div > div > div:nth-child(3) > div   ${REPORTS_PATH}/${BROWSER}_TESTRUN-${SUITE}-${TEST NAME}_${language}_Mapstart.png
        Run Keyword If   ${CI}  Capture Element Screenshot   css:#app > div > div > div:nth-child(3) > div   ${BROWSER}_TESTRUN-${SUITE}-${TEST NAME}_${language}_Mapstart.png
        Wait Until Keyword Succeeds  5x   300ms   Click Element  ${Btn_Map_Palvelukartta_ZoomOut}
    ELSE
    	Run Keyword If   not(${CI})  Capture Element Screenshot   css:#mapcontainer > div.ol-viewport > canvas   ${REPORTS_PATH}/${BROWSER}_TESTRUN-${SUITE}-${TEST NAME}_${language}_Mapstart.png
    	Run Keyword If   ${CI}  Capture Element Screenshot   css:#mapcontainer > div.ol-viewport > canvas   ${BROWSER}_TESTRUN-${SUITE}-${TEST NAME}_${language}_Mapstart.png
    	Wait Until Keyword Succeeds  5x   300ms   Click Element   ${Btn_Map_Kartta_ZoomOut}
    END 
	
	Sleep   1
	IF    ${ispalvelukartta}
        Capture Element Screenshot   css:#app > div > div > div:nth-child(3) > div   ${REPORTS_PATH}/${BROWSER}_TESTRUN-${SUITE}-${TEST NAME}_${language}_Mapend.png
    ELSE
    	Capture Element Screenshot   css:#mapcontainer > div.ol-viewport > canvas   ${REPORTS_PATH}/${BROWSER}_TESTRUN-${SUITE}-${TEST NAME}_${language}_Mapend.png
    END
    ${mapstart} =  Set Variable    ${REPORTS_PATH}/${BROWSER}_TESTRUN-${SUITE}-${TEST NAME}_${language}_Mapstart.png 
	${mapend} =  Set Variable    ${REPORTS_PATH}/${BROWSER}_TESTRUN-${SUITE}-${TEST NAME}_${language}_Mapend.png
	Run Keyword And Expect Error   The compared images are different.  Compared Pictures Match  ${mapstart}   ${mapend}
	Unselect Frame

URL Contains Text
	[Arguments]  ${text}
	${containstext}=    Run Keyword And Return Status    Should Contain    ${URL}    ${text}
	[Return]   ${containstext}
	
Allow Palvelukartta Cookies
	Sleep  2
	Wait Until Element Is Visible  ${Itm_Map_Palvelukartta}   timeout=3
	Select Frame   ${Itm_Map_Palvelukartta}
	Run Keyword And Ignore Error   Wait Until Keyword Succeeds  5x   200ms   Click Button   ${Btn_Map_Palvelukartta_AllowCookies}
	Unselect Frame
	Sleep  4	