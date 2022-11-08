*** Settings ***

Resource        ../Contenthandler.robot
Resource        ../Commonkeywords.robot

*** Variables ***
${TAPAHTUMAT_URL}		https://tapahtumat.hel.fi/fi/home

*** Keywords ***

Create Event
	[Arguments]   ${pagetype}   ${loadmore}=False
	Input Non-paragraph Related Content   ${pagetype}
	Open Paragraph For Edit   ${Opt_AddEvent}
	Wait Until Keyword Succeeds  5x  200ms  Input Title To Paragraph   ${Inp_Event_Title}
	Input Description To Paragraph   ${Frm_Content}
	Input Text   ${Inp_Event_Url}   ${TAPAHTUMAT_URL}
	IF  	${loadmore}
		No Operation	# DOES NOTHING SINCE LOAD MORE IS ENABLED BY DEFAULT
	ELSE
		Click Button  ${Swh_Event_LoadMore}
	END
		
Events Are Present In Page
	Wait Until Element Is Visible  css:.component--event-list   timeout=5

Load More Button Is Present
	Wait Until Element Is Visible  css:.event-list__load-more > button   timeout=5