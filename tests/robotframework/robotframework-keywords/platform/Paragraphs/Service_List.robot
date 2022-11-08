*** Settings ***

Resource        ../Contenthandler.robot
Resource        ../Commonkeywords.robot

*** Keywords ***

Add ServiceList
	[Arguments]   ${pagetype}
	Input Non-paragraph Related Content   ${pagetype}
	Open Paragraph For Edit   ${Opt_ServiceList}
	Wait Until Keyword Succeeds  5x  200ms  Input Title To Paragraph   ${Inp_ServiceList_Title}
	Set Focus To Element   ${Sel_ServiceList_Services}
	Select From List By Value   ${Sel_ServiceList_Services}   7716
	Run Keyword If  '${TEST NAME}'=='Two Services'  Select From List By Value   ${Sel_ServiceList_Services}   7705
	Wait Until Keyword Succeeds  5x  100ms   Input Description To Paragraph   css:#cke_1_contents > iframe

Layout Should Not Have Changed
	Run Keyword And Ignore Error  Accept Cookies
	Capture Screenshot For Picture Comparison    css=main.layout-main-wrapper
	Compare Two Pictures		