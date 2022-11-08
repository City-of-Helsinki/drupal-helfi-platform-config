*** Settings ***
Library    OperatingSystem
Documentation   Common Keywords referred by many testsuites. Platform side tests only
Library           SeleniumLibrary
Library			  String

*** Variables ***
${REPORTS_PATH}    ${EXECDIR}/robotframework-reports
${CONTENT_PATH}    ${EXECDIR}/robotframework-resources/content
${KEYWORDS_PATH}    ${EXECDIR}/robotframework-keywords
${IMAGES_PATH}    ${EXECDIR}/robotframework-resources/images
${SCREENSHOTS_PATH}    ${EXECDIR}/robotframework-resources/screenshots

*** Keywords ***
Debug Error
	[Documentation]   If debug is set on, will capture screenshot of error and save the html page. The data will be 
	...				  in debug folder of test results.
	Maximize Browser Window   
	${wsize}=  Get Window Size
	${width}=  Get From List   ${wsize}   0
	${height}=  Get From List   ${wsize}   1
	IF    ${CI}
		Capture Page Screenshot    filename=/app/helfi-test-automation-python/robotframework-reports/debug/${SUITE NAME}-${TEST NAME}_error_normal.png
		Set Window Size  3840   3160    # SO THAT WHOLE ELEMENT GETS CAPTURED SUCCESFULLY
		Execute javascript  document.body.style.zoom="80%"
		Capture Element Screenshot	css:body   /app/helfi-test-automation-python/robotframework-reports/debug/${SUITE NAME}-${TEST NAME}_error_zoomout.png
    ELSE
    	Capture Page Screenshot    filename=${REPORTS_PATH}/debug/${SUITE NAME}-${TEST NAME}_error_normal.png
    	Set Window Size  3840   3160    # SO THAT WHOLE ELEMENT GETS CAPTURED SUCCESFULLY
		Execute javascript  document.body.style.zoom="80%"
    	Capture Element Screenshot	css:body   ${REPORTS_PATH}/debug/${SUITE NAME}-${TEST NAME}_error_zoomout.png		
    END 
	Execute javascript  document.body.style.zoom="100%"
	Set Window Size   ${width}   ${height}	# LETS RESTORE THE ORIGINAL VALUE USED IN TESTING
	${source}=   Get Source
	IF    ${CI}
		Create File  robotframework-reports/debug/${SUITE NAME}-${TEST NAME}_error_source.html  ${source}	
    ELSE
		Create File  ${REPORTS_PATH}/debug/${SUITE NAME}-${TEST NAME}_error_source.html  ${source}
    END
	
Input Text To Frame
	[Documentation]   Inserts text to given frame and returns to original content
	[Arguments]	   ${frame}   ${locator}   ${input}
	Select Frame   ${frame}
	Input Text   ${locator}   ${input}
	Unselect Frame
    
Click Element With Value
	[Arguments]	   ${value}
	${value}=  Convert To Lower Case   ${value}
	Click Element  css:[value=${value}]

Remove String And Strip Text
	[Documentation]   Value= String to be modified , String = String to be removed from value -content
	[Arguments]	   ${value}   ${string}
	${value}=  Run Keyword And Continue On Failure   Remove String   ${value}   ${string}
	${value}=  Strip String   ${value}
	[Return]    ${value}


Replace Encoded Characters From String
	[Documentation]   Removes Hidden characters from strings which are not seen in log files. 
	[Arguments]	   ${string}  ${replacewith}  ${charset}  ${charstoremove}
	${encoded}=   Encode String To Bytes   ${string}   ${charset}
	${replaced}=  Replace String   b'${encoded}'   ${charstoremove}    ${replacewith}
	${replaced}=  Replace String Using Regexp   ${replaced}   ^.{0,2}   ${EMPTY}
	${string}=  Replace String Using Regexp   ${replaced}   .{0,1}$   ${EMPTY}
	[Return]    ${string}
	
Suite Source Contains Text
	[Arguments]  ${text}
	${containstext}=    Run Keyword And Return Status    Should Contain    ${SUITE SOURCE}    ${text}
	[Return]   ${containstext}

Test Name Contains Text
	[Arguments]  ${text}
	${containstext}=    Run Keyword And Return Status    Should Contain    ${TEST NAME}    ${text}
	[Return]   ${containstext}

Click Element Using JavaScript Xpath
	[Arguments]  ${xpath}
    Execute JavaScript    document.evaluate("${xpath}",document.body,null,9,null).singleNodeValue.click();

Click Element Using JavaScript Id
	[Arguments]  ${id}
    Execute JavaScript    document.getElementById(${id}).click();

Page Should Have Given Number Of Elements 
	[Arguments]   ${element}   ${elemcount} 
	Page Should Contain Element   ${element}   limit=${elemcount}
	
Rename Reports Picture To Use Original Picture Name
	[Documentation]   Renames File In given source folder. Full path must be given in first argument and
	...				  only new name in second one.
	[Arguments]  ${fullpath}   ${newname}
	Move File  ${fullpath}  ${newname}
	
Zoom Out And Capture Page Screenshot
	[Documentation]   Used for debugging purposes during test development
	Sleep  0.1
	Execute javascript  document.body.style.zoom="25%"
	Capture Page Screenshot
	Execute javascript  document.body.style.zoom="100%"
	Sleep  0.1