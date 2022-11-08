*** Settings ***
Resource        ../Contenthandler.robot
Resource        ../Commonkeywords.robot

*** Variables ***
${test_url}	 		 https://app.powerbi.com/view?r=eyJrIjoiYjE5OTFhMmEtMWYzNC00YjY2LTllODMtMzhhZDRiNTJiMDQ5IiwidCI6IjNmZWI2YmMxLWQ3MjItNDcyNi05NjZjLTViNThiNjRkZjc1MiIsImMiOjh9

*** Keywords ***
Create Chart
	[Arguments]   ${pagetype}
	Input Non-paragraph Related Content   ${pagetype}
	Open Paragraph For Edit   ${Opt_AddChart}
	Wait Until Keyword Succeeds  5x  200ms  Input Title To Paragraph  ${Inp_Chart_Title}
	Input Description To Paragraph   ${Inp_Chart_Description}
	Wait Until Keyword Succeeds  5x  200ms  Input Text   ${Assistive_Technology_Title}   Avustavan teknologian otsikko
	Click Button   ${Btn_Chart_Add_Media}
	Wait Until Keyword Succeeds  5x  200ms  Input Text   ${Inp_Chart_Url}   ${test_url}
	Click Button   ${Btn_Chart_Url_Add}
	Wait Until Keyword Succeeds  5x  200ms  Input Text  ${Inp_Chart_Url_Title}    Chart Picture  
	Input Text To Frame   ${Frm_Chart_Url_Transcription}   //body   Just transcription text for testcase:${TEST NAME}
	Wait Until Keyword Succeeds  5x  200ms  Submit New Media
	  
Layout Should Not Have Changed
	Run Keyword And Ignore Error  Accept Cookies
	Run Keyword If   ${CI}  Sleep  2    # Small sleep so that content gets loaded
	Chart.Take Screenshot Of Content
	Compare Two Pictures
	
Take Screenshot Of Content
	Maximize Browser Window
	Capture Screenshot For Picture Comparison   css=main.layout-main-wrapper	