*** Settings ***

Resource        ../Contenthandler.robot
Resource        ../Commonkeywords.robot

*** Keywords ***
Accept ${selection} Cookies
	IF    '${selection}'=='Essential'
        Wait Until Keyword Succeeds  5x   200ms   Accept Essential Cookies
    ELSE
    	Wait Until Keyword Succeeds  5x   200ms   Accept All Cookies
    END
	
Get All Currently Used Cookies
	${cookies}=   Get Cookies   as_dict=True
	[Return]   ${cookies}

Accept All Cookies
	Wait Until Keyword Succeeds  6x  400ms  Click Button  //button[@class='agree-button eu-cookie-compliance-default-button hds-button hds-button--primary']	

Accept Essential Cookies
	Wait Until Keyword Succeeds  6x  400ms  Click Button  //button[@class='eu-cookie-compliance-save-preferences-button hds-button hds-button--secondary']
	
User Accepts ${selection} Cookies And Logs In
	Accept ${selection} Cookies
	Input Text   id:edit-name   helfi-admin
	Input Password   id:edit-pass   Test_Automation
	Wait Until Keyword Succeeds  3x  600ms  Log In User	
	
Open Browser For Testing
	IF    (${CI}) | (${CI_LOCALTEST})
        Set CI Arguments And Open Browser
    ELSE
    	Open Browser  ${URL_login_page}  ${BROWSER}
    END
