*** Settings ***
Documentation   For these testcases to work, 'helfi_example_content' should be enabled.
Resource        ../../../../robotframework-keywords/platform/Others/CookieCompliance.robot
Test Setup      Open Browser For Testing
Force Tags		COOKIECOMPLIANCE

*** Test Cases ***
Essential Cookies
	[Tags]
	When User Accepts Essential Cookies And Logs In
	Then Essential Cookies Are Created	

All Cookies
	[Tags]
	When User Accepts All Cookies And Logs In
	Then All Cookies Are Created




*** Keywords ***
User Opens Content With Title ${contenttitle}
    Go To   ${URL_content_page}
    Search And Click Content From Content Pages   ${contenttitle}

User Accepts ${selection} Cookies
	Run Keyword If   (not(${CI})) | (not(${CI_LOCALTEST}))  User Opens Content With Title Esimerkkisivu
	Accept ${selection} Cookies
	
${consent_option} Cookies Are Created 
		${cookies}=   Get All Currently Used Cookies
		${agreed}=  Get From Dictionary   ${cookies}   cookie-agreed-categories
		${consent_option}=   Remove String   ${consent_option}   Then
		${consent_option}=   Strip String   ${consent_option}
		IF    '${consent_option}'=='Essential'
        	Should Be Equal   ${agreed}     [%22essential%22]
    	ELSE
    		Should Be Equal   ${agreed}     [%22essential%22%2C%22preference%22%2C%22statistics%22%2C%22marketing%22%2C%22chat%22]
   		END
