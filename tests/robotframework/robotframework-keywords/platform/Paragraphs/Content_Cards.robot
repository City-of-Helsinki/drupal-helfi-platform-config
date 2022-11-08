*** Settings ***

Resource        ../Contenthandler.robot
Resource        ../Commonkeywords.robot

*** Keywords ***

Resolve Card-Size Variable
	[Arguments]  ${card-size}
	${card-size}=  Convert To Lower Case   ${card-size}
	${cardsizevariable}=   Set Variable If   '${card-size}'=='small'    small-cards
	...			'${card-size}'=='large'    large-cards
	...			'${card-size}'=='small grey'    small-cards-grey
	...			'${card-size}'=='large grey'    large-cards-grey
	[Return]   ${cardsizevariable}

Create ${pagetype} With ${cardsize} Cards For ${contentname} Content
 	Set Test Variable  ${cardsize}  ${cardsize}
 	Input Non-paragraph Related Content   ${pagetype}
 	# IF PAGE AND KYMP -repo , lets use lower paragraph add list, ELSE just default.
 	IF    ('${pagetype}'=='Page') & ('${PREFIX}'=='/kaupunkiymparisto-ja-liikenne')
	    Wait Until Keyword Succeeds  5x  200ms  Open Paragraph For Edit   ${Opt_AddContentCards_Lower}   ${Ddn_AddContent_Lower}
    ELSE
    	Wait Until Keyword Succeeds  5x  200ms  Open Paragraph For Edit   ${Opt_AddContentCards}
    END 
    Wait Until Keyword Succeeds  5x  100ms  Input Title To Paragraph   ${Inp_ContentCard_Title}

	${cardsizevalue}=  Resolve Card-Size Variable   ${cardsize}
	Select From List By Value  ${Inp_ContentCard_Design}  ${cardsizevalue}
	Input Text   ${Inp_ContentCard_TargetId}   ${contentname}
	Wait Until Keyword Succeeds  5x  100ms  Click Element   //a[contains(text(),'${contentname}')]
	
Add New ContentCard For ${contentname} Content
	Wait Until Keyword Succeeds  5x  100ms  Click Element  ${Inp_ContentCard_Addnew}
	# Better locators does not match correct element. For some reason only first is returned
	# So Only works for second content card. 
	Sleep  3
	Wait Until Keyword Succeeds  5x  100ms  Input Text   ${Inp_ContentCard_TargetId}   ${contentname}
	
	
ContentCards Are Working Correctly
	${contentpageurl}=   Get Location
	Click Element   //a[contains(@href, 'link-examples')]
	${currenturl}=   Get Location
	Should Contain   ${currenturl}   link-examples
	Go To   ${contentpageurl}
	Wait Until Element Is Visible   //a[contains(@href, 'esimerkkisivu')]
	Click Element   //a[contains(@href, 'esimerkkisivu')]
	${currenturl}=   Get Location
	Should Contain   ${currenturl}   esimerkkisivu
	

	 
Layout Should Not Have Changed
	Run Keyword And Ignore Error  Accept Cookies
	Capture Screenshot For Picture Comparison    css=main.layout-main-wrapper
	Compare Two Pictures	