*** Settings ***
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/Service.robot
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/Unit.robot
Test Setup      Login And Go To Content Page
Test Teardown   Delete Banners And Do Other Teardown Actions	
Force Tags		SERVICE   UNIT


*** Test Cases ***
Service
	[Tags]
	When User Opens Service With Name Sosiaalineuvonta
	Then Service Contents Should Be Correct

#Unit With Service
#	[Tags]
#	[Documentation]   Currently no Units with Services are imported, thus disabling this case    
#	When User Opens Unit With Name Lippulaivan kirjasto
#	Then Unit Contents Should Be Correct
#	And Units Service Link Works Correctly

Unit With Color Palette
	[Tags]
	Given User Edits Unit With Name Lippulaivan kirjasto
	And User Adds Banner To Unit With 'silver' Color Selection
	When User Submits The Unit Changes
	Then Banner Paragraph Should Have Selected Color

*** Keywords ***

User Submits The Unit Changes
	Submit Unit Changes

	
User Opens Service With Name ${name}
	Open Service With Name   ${name}

User ${action} Unit With Name ${name}
	Open Unit With Name   ${name}
	Run Keyword And Ignore Error   Accept Cookies
	
User Adds Banner To Unit With '${color}' Color Selection
	Add Banner For Unit With Name And Color   Lippulaivan kirjasto   ${color}
	
Service Contents Should Be Correct
	${title}=  Get Service Title
	${title}=   Replace Encoded Characters From String   ${title}   ${EMPTY}    UTF-8    \\xc2\\xad
	${shortdesc}=  Get Service Short Description
	${longdesc}=  Get Service Long Description
	Should Be Equal   ${title}   Sosiaalineuvonta
	Should Be Equal   ${shortdesc}   Sosiaalineuvonta palvelee helsinkiläisiä kaikissa aikuissosiaalityöhön liittyvissä kysymyksissä.
	Should Contain   ${longdesc}   Sosiaalineuvonta palvelee helsinkiläisiä
	
	
Unit Contents Should Be Correct
	Run Keyword And Ignore Error  Accept Cookies
	${title}=  Get Unit Title
	${title}=  Replace Encoded Characters From String   ${title}   ${EMPTY}    UTF-8    \\xc2\\xad
	${title}=  Replace Encoded Characters From String   ${title}   ä    UTF-8    \\xc3\\xa4
	${ccardtitle}=  Get Contact Card Title
	${ccardtitle}=  Replace Encoded Characters From String   ${ccardtitle}   ${EMPTY}    UTF-8    \\xc2\\xad
	${addrmain}=  Get Address Main Title
	${addrline1}=  Get Address Line 1
	${postcode}=  Get Postal Code
	${locality}=  Get Locality
	#${phonemain}=  Get Phone Main Title
	${servicetitle}=  Get Units Service Title
	${servicetitle}=  Replace Encoded Characters From String   ${servicetitle}   ${EMPTY}    UTF-8    \\xc2\\xad
	${servicedesc}=  Get Units Service Description

	Should Be Equal   ${title}   Lippulaivan kirjasto
	Should Be Equal   ${ccardtitle}   Yhteystiedot
	Should Be Equal   ${addrmain}   Käyntiosoite:
	Should Be Equal   ${addrline1}   Merikarhunkuja 11
	Should Be Equal   ${postcode}   02320
	Should Be Equal   ${locality}   Espoo
	#Should Be Equal   ${phonemain}   Puhelinnumero:
	${hasservicelink}=  Unit Has Service Link
	Should Be True   ${hasservicelink}
	Should Be Equal   ${servicetitle}   Sosiaalineuvonta
	Should Be Equal   ${servicedesc}   Sosiaalineuvonta palvelee helsinkiläisiä kaikissa aikuissosiaalityöhön liittyvissä kysymyksissä.
	
Units Service Link Works Correctly
	Click Element   //a[contains(@href, 'https://helfi.docker.so/fi/sosiaalineuvonta')]
	${currenturl}=   Get Location
	Should Contain   ${currenturl}   sosiaalineuvonta

Banner Paragraph Should Have Selected Color
	Open Unit With Name   Lippulaivan kirjasto
	Run Keyword If   not(${CI})   Capture Element Screenshot  css:#juhani-aho--rautatie   filename=${REPORTS_PATH}/${BROWSER}_TESTRUN-${SUITE}-${TEST NAME}_${language}.png
	Run Keyword If   ${CI}   Capture Element Screenshot  css:#juhani-aho--rautatie   filename=/app/helfi-test-automation-python/robotframework-reports/${BROWSER}_TESTRUN-${SUITE}-${TEST NAME}_${language}.png
	Compare Two Pictures 
	
Delete Banners And Do Other Teardown Actions
	Run Keyword If  '${TEST NAME}'=='Unit With Color Palette'  Delete Banner For Unit With Name   Lippulaivan kirjasto
	Cleanup and Close Browser
	