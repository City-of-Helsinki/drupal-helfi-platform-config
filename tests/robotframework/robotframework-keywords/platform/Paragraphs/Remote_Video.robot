*** Settings ***

Resource        ../Contenthandler.robot
Resource        ../Commonkeywords.robot

*** Variables ***  
${title}						Helsinki-apu ikääntyneiden helsinkiläisten avuksi					
${description}					Helsinki-avussa varmistetaan, että ikääntyneet helsinkiläiset saavat keskusteluavun lisäksi apua arjen tärkeiden asioiden hoitamiseen

*** Keywords ***

Create ${pagetype} With ${number} Remote Video(s) Content
	Set Test Variable   ${number}   ${number}
	Input Non-paragraph Related Content   ${pagetype}
    Add Remote Video
    Run Keyword If  ('${TEST NAME}'=='Two Videos') | ('${TEST NAME}'=='Landingpage-Two Videos')    Add Remote Video   https://www.youtube.com/watch?v=3HPuT7A0O8c

	
Add Remote Video
    [Arguments]   ${url}=https://www.youtube.com/watch?v=nl5jKA6MMVg
    Run Keyword If  '${language}'=='fi'  Open Paragraph For Edit   ${Opt_AddRemotevideo}
    Wait Until Element Is Enabled   ${Btn_RemoteVideo_Add}   10 s
	Wait Until Keyword Succeeds  5x  200ms  Set Focus To Element   ${Btn_RemoteVideo_Add}
	Wait Until Keyword Succeeds  5x  200ms  Press Keys    None    RETURN
  	Wait Until Keyword Succeeds  6x  300ms  Input Text To Video URL field   ${url}
    Wait Until Keyword Succeeds  5x  100ms  Press Keys    None    TAB
    Wait Until Keyword Succeeds  5x  100ms  Press Keys    None    ENTER
    Sleep  1		#SMALL SLEEP DUE ISSUES IN CONTENT LOADING
    Wait Until Keyword Succeeds  6x  1s  Confirm Video Selection
    Wait Until Keyword Succeeds  5x  200ms  Input Text To Frame   ${Itm_Video_Description}  //body  ${description}
    Wait Until Keyword Succeeds  5x  200ms  Input Text   ${Itm_Video_Title}   ${title}
    Wait Until Keyword Succeeds  5x  200ms  Input Text   ${Assistive_Technology_Title}   Avustavan teknologian otsikko
    
    Set Test Variable  ${mediaadded}    ${mediaadded}+1

Input Text To Video URL field
	[Arguments]   ${url}
	Scroll Element Into View   ${Inp_RemoteVideo_Url}
	Wait Until Element Is Visible   ${Inp_RemoteVideo_Url}   timeout=5
    Wait Until Keyword Succeeds  3x  100ms  Input Text   ${Inp_RemoteVideo_Url}   ${url}

Confirm Video Selection
    Click Button  ${Btn_RemoteVideo_Confirm}
    Wait Until Keyword Succeeds  7x  400ms   Element Should Not Be Visible   ${Btn_RemoteVideo_Confirm}
  
Remote Video Title And Description is Correct
	${actual_title}=  Get Text   ${Video_Title}
	${actual_desciption}=  Get Text   ${Video_Description}
	Should Be Equal   ${title}     ${actual_title}
	Should Be Equal   ${description}     ${actual_desciption}
	
  
Remote Video Play Begins Correctly
	Wait Until Element Is Visible  ${Itm_Video}
	Capture Element Screenshot   css:div.responsive-video-container > iframe    ${REPORTS_PATH}/${BROWSER}_TESTRUN-${SUITE}-${TEST NAME}_${language}_Video1start.png
	${videostart} =  Set Variable    ${REPORTS_PATH}/${BROWSER}_TESTRUN-${SUITE}-${TEST NAME}_${language}_Video1start.png
	Play Video   ${Itm_Video}
	Capture Element Screenshot   ${Itm_Video}   ${REPORTS_PATH}/${BROWSER}_TESTRUN-${SUITE}-${TEST NAME}_${language}_Video1end.png
	${videoend} =  Set Variable    ${REPORTS_PATH}/${BROWSER}_TESTRUN-${SUITE}-${TEST NAME}_${language}_Video1end.png
	Run Keyword And Expect Error   The compared images are different.  Compared Pictures Match  ${videostart}   ${videoend}
	#VIDEO2
	Run Keyword If  ('${TEST NAME}'=='Two Videos') | ('${TEST NAME}'=='Landingpage-Two Videos')   Video 2 Plays Correctly


Video 2 Plays Correctly
	${islandingpage}=  Suite Source Contains Text   Landing_Page
	IF    ${islandingpage}
		Capture Element Screenshot   ${Itm_Landingpage_Video2}   ${REPORTS_PATH}/${BROWSER}_TESTRUN-${SUITE}-${TEST NAME}_${language}_Video2start.png
    ELSE
		Capture Element Screenshot   ${Itm_Video2}   ${REPORTS_PATH}/${BROWSER}_TESTRUN-${SUITE}-${TEST NAME}_${language}_Video2start.png
    END 
	${video2start} =  Set Variable    ${REPORTS_PATH}/${BROWSER}_TESTRUN-${SUITE NAME}-${TEST NAME}_${language}_Video2start.png
	Play Video   ${Itm_Video2}
	IF    ${islandingpage}
		Capture Element Screenshot   ${Itm_Landingpage_Video2}   ${REPORTS_PATH}/${BROWSER}_TESTRUN-${SUITE}-${TEST NAME}_${language}_Video2end.png
    ELSE
		Capture Element Screenshot   ${Itm_Video2}   ${REPORTS_PATH}/${BROWSER}_TESTRUN-${SUITE}-${TEST NAME}_${language}_Video2end.png
    END 
	${video2end} =  Set Variable    ${REPORTS_PATH}/${BROWSER}_TESTRUN-${SUITE}-${TEST NAME}_${language}_Video2end.png
	Run Keyword And Expect Error   The compared images are different.  Compared Pictures Match  ${video2start}   ${video2end}

Play Video
	[Arguments]   ${video_frame}
	Select Frame   ${video_frame}
	Select Frame   css:body > iframe
	Sleep  3
	Click Element   css:#movie_player > div.ytp-cued-thumbnail-overlay > button
	Unselect Frame
	Unselect Frame
	Sleep   8
	
Take Screenshot Of Content
	Maximize Browser Window
	IF  '${TEST NAME}'=='Two Videos'
		Execute javascript  document.body.style.zoom="25%"
	ELSE
		Execute javascript  document.body.style.zoom="30%"
	END
	Capture Screenshot For Picture Comparison
	Execute javascript  document.body.style.zoom="100%"	
	
Layout Should Not Have Changed
	Run Keyword And Ignore Error  Accept Cookies
	Run Keyword If   ${CI}  Sleep  2     # DUE EMBEDDED VIDEO NOT LOADED CORRECTLY IN CI TESTS
	Capture Screenshot For Picture Comparison    css=main.layout-main-wrapper
	Compare Two Pictures   1	