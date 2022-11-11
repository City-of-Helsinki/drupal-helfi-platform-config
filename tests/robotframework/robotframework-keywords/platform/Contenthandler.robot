*** Settings ***
Documentation   Handler class for several content handling keywords.
...				Variables:
...				Submitted:  Is the new page submitted. This is needed when tearing down creating content after test.
...				Picalign:   Picture alignment value in hero cases.
...				Picture:    Is at least one picture added to content = picture , else 'nopicture'
...				Picsadded:  Number of pictures added to content. This is needed in teardown after test, so all media
...				Picsize:   Picture size for column pictures. Original=  If use original aspect ratio. Cropped otherwise  
...				gets deleted succesfully. Please note that pictures with greater value of width than length are not 
...				modified in any way by drupal.
...				Linkstyle:	Styling of the link used in some test cases.
...				language:  Not UI language but content translation.
...				gallery:  is gallery paragraph used in this test.  
Resource        Commonkeywords.robot
Resource		  ./variables/create_content.robot
Library           RobotEyes
Library           OperatingSystem
Library			  Collections

*** Variables ***
${submitted}								false
${picalign} 		 						${EMPTY}
${picture} 			 						nopicture
${mediaadded}								0
${paragraphsadded}							0
${pagesadded}								0
${picsize}									cropped
${linkstyle} 		 						${EMPTY}
${language}	 		 						fi
${gallery}									false
${serviceispublished}						false
${unitispublished}							false
@{excludetaglist}     					    <something>
${URL_login_page}							${PROTOCOL}://${BASE_URL}/fi${PREFIX}/user/login
${URL_content_page}							${PROTOCOL}://${BASE_URL}/fi${PREFIX}/admin/content
${URL_unit_page}							${PROTOCOL}://${BASE_URL}/fi${PREFIX}/admin/content/integrations/tpr-unit
${URL_service_page}							${PROTOCOL}://${BASE_URL}/fi${PREFIX}/admin/content/integrations/tpr-service
${URL_media_page}							${PROTOCOL}://${BASE_URL}/fi${PREFIX}/admin/content/media
${URL_paragraphs_page}						${PROTOCOL}://${BASE_URL}/fi${PREFIX}/admin/content/paragraphs
${URL_paragraphs_add_page}					${PROTOCOL}://${BASE_URL}/fi${PREFIX}/admin/content/paragraphs/add/default
		
*** Keywords ***

Open Paragraph For Edit
	[Arguments]   ${paragraph}    ${paragraphlist}=${EMPTY}
	[Documentation]  'paragraphlist' can be given if list should be some other than default paragraph list.
	Wait Until Element Is Visible   ${Ddn_AddContent}   timeout=3
	Run Keyword If  '${paragraphlist}'=='${EMPTY}'  Click Element	${Ddn_AddContent}
	Run Keyword If  '${paragraphlist}'!='${EMPTY}'   Click Element	${paragraphlist}
	Wait Until Keyword Succeeds  5x  200ms 	Click Element   ${paragraph}
	
Input Title To Paragraph
	[Arguments]    ${paragraph_title_locator}
	${title}=  Return Correct Title   ${language}
	Input Text  ${paragraph_title_locator}   ${title}

Input Description To Paragraph
	[Arguments]    ${paragraph_description_locator}
	${TextFileDescription}=  Return Correct Description   ${language}
	Input Text To Frame   ${paragraph_description_locator}   //body   ${TextFileDescription}

Input Title
	[Arguments]   ${title}
	Wait Until Element Is Visible   ${Inp_Title}   timeout=3  
	Input Text  ${Inp_Title}   ${title}  

Input Author
	[Arguments]   ${author}
	Wait Until Element Is Visible   ${Inp_Author}   timeout=3  
	Input Text  ${Inp_Author}   ${author}  

Input Lead
	[Arguments]   ${lead}
	Wait Until Element Is Visible   ${Inp_Lead}   timeout=3  
	Input Text  ${Inp_Lead}   ${lead} 

Capture Screenshot For Picture Comparison
	[Arguments]   ${locator}   ${blur}=${EMPTY}    ${redact}=${EMPTY}
	[Documentation]  See   https://github.com/jz-jess/RobotEyes
	${wsize}=  Get Window Size
	${width}=  Get From List   ${wsize}   0
	${height}=  Get From List   ${wsize}   1
	Set Window Size  3840   3160    # SO THAT WHOLE ELEMENT GETS CAPTURED SUCCESFULLY
	Open Eyes   SeleniumLibrary
	IF    ${CI}
		Capture Element   ${locator}     name=/app/helfi-test-automation-python/robotframework-reports/${BROWSER}_TESTRUN-${SUITE}-${TEST NAME}_${language}   blur=${blur}   redact=${redact}
	ELSE
		Capture Element   ${locator}   name=${REPORTS_PATH}/${BROWSER}_TESTRUN-${SUITE}-${TEST NAME}_${language}   blur=${blur}   redact=${redact}
	END
	Set Window Size   ${width}   ${height}	# LETS RESTORE THE ORIGINAL VALUE USED IN TESTING
	
Input Content Header Title
	[Arguments]   ${content}   ${pagetype}
	Run Keyword If   '${pagetype}'=='Page'   Input Text  name:field_lead_in[0][value]   ${content}
	Run Keyword If   '${pagetype}'!='Page'  Input Text To Frame   ${Frm_Content}   //body   ${content}
	
Go To Translations Tab
	Click Element   //a[contains(text(),'Translate')]
	
Go To Modify Tab
	Scroll Element Into View   css:ul.local-tasks > li:nth-child(2) > a
	Click Element   css:ul.local-tasks > li:nth-child(2) > a

Search And Click Content From Content Pages
	[Arguments]  ${contentname}
	FOR    ${i}    IN RANGE    10
    	${hascontent}=  Run Keyword And Return Status   Wait Until Keyword Succeeds  5x   200ms   Page Should Contain Link   ${contentname}
    	Run Keyword If  ${hascontent}  Wait Until Keyword Succeeds  5x   200ms   Click Link   ${contentname}
    	Run Keyword If  ${hascontent}  Return From Keyword   True
    	${nextbuttonvisible}=  Run Keyword And Return Status   Element Should Be Visible   css:.pager__item--next
    	Run Keyword If  ${nextbuttonvisible}   Click Element   css:.pager__item--next
    	Run Keyword If  not(${nextbuttonvisible})   Return From Keyword   False
    END
	
	
Go To ${language} Translation Page
	${language_pointer}=  Get Language Pointer   ${language}
	Click Element   //a[contains(@href, 'translations/add/fi/${language_pointer}')]
		
Cleanup and Close Browser
	[Documentation]  Deletes content created by testcases. Page , if created and picture if added.
	${currenturl}=   Get Location
	Run Keyword If   ${DEBUG}   Run Keyword If Test Failed   Debug Error
	FOR    ${i}    IN RANGE    10
		   Go To   ${URL_content_page}
		   ${contentfound}=   Search And Click Content From Content Pages   Test Automation: ${SUITE}.${TEST NAME}
		   Run Keyword If   ${contentfound}   Delete Test Automation Created Content
		   Run Keyword If   not(${contentfound})   Exit For Loop
    END
    IF    (not(${CI})) & (not(${CI_LOCALTEST}))
		TearDown Test Paragraphs
		TearDown Media Content
	END
    Close Browser	

TearDown Test Paragraphs
	# REMOVING PARAGRAPHS IS LIKELY NOT NECESSARY FROM NOW ON SO LETS JUST DISABLE THIS
    FOR    ${i}    IN RANGE    10
    	   Go To   ${URL_paragraphs_page}
    	   ${count}=  Get Element Count   partial link:Test_Automation
    	   Exit For Loop If   ${count}==0
           Delete Test Automation Created Paragraphs
    END

TearDown Media Content
	# REMOVING MEDIA IS LIKELY NOT NECESSARY FROM NOW ON SO LETS JUST DISABLE THIS
	FOR    ${i}    IN RANGE    ${mediaadded}
           Wait Until Keyword Succeeds  2x  200ms 	Delete Test Automation Created Media
    END
	
Set Service Back To Unpublished
	[Arguments]   ${name}
	Goto  https://${BASE_URL}/fi/admin/content/integrations/tpr-service
	Click Link   ${name}
	Wait Until Keyword Succeeds  5x  200ms 	Set Focus To Element   css:ul.local-tasks > li:nth-child(2) > a
	Click Link   css:ul.local-tasks > li:nth-child(2) > a
	Capture Page Screenshot
	${ispublished}=  Run Keyword And Return Status   Page Should Contain Element  css:input.tpr-entity-status:checked
	Run Keyword If   ${ispublished}  Click Element   id:edit-status
	Click Button   ${Btn_Submit}
	

Set Unit Back To Unpublished
	[Documentation]   Publishing function works other way too so it can unpublish with the same keyword
	[Arguments]   ${name}
	Goto  https://${BASE_URL}/fi/admin/content/integrations/tpr-unit
	Click Link   ${name}
	Wait Until Keyword Succeeds  5x  200ms 	Click Link   css:ul.local-tasks > li:nth-child(2) > a
	${ispublished}=  Run Keyword And Return Status   Page Should Contain Element  css:input.tpr-entity-status:checked
	Run Keyword If   ${ispublished}  Click Element   id:edit-status
	Click Button   ${Btn_Submit}
	
Publish Unit With Name
	[Arguments]   ${unitname}
	Goto  https://${BASE_URL}/fi/admin/content/integrations/tpr-unit
	Click Link   ${unitname}
	Run Keyword And Ignore Error   Accept Cookies
	Click Link   css:ul.local-tasks > li:nth-child(2) > a
	Set Unit As Published
	Click Button   ${Btn_Submit}


Publish Service With Name
	[Arguments]   ${servicename}
	Goto  https://${BASE_URL}/fi/admin/content/integrations/tpr-service
	Click Link   ${servicename}
	Run Keyword And Ignore Error   Accept Cookies
	Set Focus To Element   css:ul.local-tasks > li:nth-child(2) > a
	Click Link   css:ul.local-tasks > li:nth-child(2) > a
	Set Service As Published
	Click Button   ${Btn_Submit}	

	
Image Comparison Needs To Exclude Areas
	[Documentation]   Image Comparison needs to exclude some parts of the picture in case of for example changing date
	... 			  values and such which cause the test to fail in comparion stage. For this reason we check if
	...				  excluding is needed and save possible excludetag in test variable so that right parts will later
	...				  be excluded
	Log List   ${TEST TAGS}
	${count}=  Get Length   ${excludetaglist}
	FOR    ${i}    IN RANGE    ${count}
		   ${tag}=  Get From List  ${excludetaglist}   ${i}	
           ${status}=   Run Keyword And Return Status   Should Contain Match   ${TEST TAGS}    ${tag}
           Run Keyword If   '${status}'=='True'   Set Test Variable   ${excludetag}    ${tag}
           Exit For Loop If   '${status}'=='True'   
    END
    [Return]   ${status}

Click Content Link From Notification Banner
	Wait Until Element Is Visible   css:div.messages__content > em > a
	Wait Until Keyword Succeeds  5x  200ms  Click Element   css:div.messages__content > em > a
	Element Should Not Be Visible   //a[contains(@href, '/node/add')]

Accept Cookies
	Wait Until Keyword Succeeds  6x  400ms  Click Button  //button[@class='agree-button eu-cookie-compliance-default-button hds-button hds-button--secondary']

Open Created Content
	Run Keyword If  '${CI}'!='true'  Open Content In Non CI Environments
	Run Keyword If   (${CI}) & ('${language}'=='fi')  	Accept Cookies
	Run Keyword If   ${CI}  Reload Page
	  
Log In
	Wait Until Keyword Succeeds  5x  200ms  Accept Cookies
	Wait Until Keyword Succeeds  7x  300ms  Input Credentials And Log In

Input Credentials And Log In
	Input Text   id:edit-name   helfi-admin
	Input Password   id:edit-pass   Test_Automation
	Run Keyword And Ignore Error   Accept Cookies
	Wait Until Keyword Succeeds  3x  600ms  Log In User


Log In User
	Click Button   id:edit-submit
	Wait Until Keyword Succeeds  7x  200ms  Element Should Not Be Visible   id:edit-submit	

Open Content In Non CI Environments
	[Documentation]   Goes to content view of created content through content list page (since local environment errors prevent
	...				  viewing it directly after creation)
	
	Go To   ${URL_content_page}
	Wait Until Keyword Succeeds  5x  200ms  Click Content Link From Notification Banner
	Run Keyword If  '${language}'=='fi'  	Accept Cookies
	
Get Language Pointer
	[Arguments]     ${language}
	[Documentation]  fi = Finnish is default
	${language_pointer}=  Set Variable If  '${language}'=='Finnish'   fi
	...		'${language}'=='Swedish'   sv
	...		'${language}'=='English'   en
	...		'${language}'=='Russian'   ru
	[Return]   ${language_pointer}

Set Language Pointer
	[Arguments]     ${language}
	[Documentation]  Language to set to Test Variable
	${language_pointer}=  Set Variable If  '${language}'=='Finnish'   fi
	...		'${language}'=='Swedish'   sv
	...		'${language}'=='English'   en
	...		'${language}'=='Russian'   ru
	Set Test Variable   ${language}   ${language_pointer}
	
	
Compared Pictures Match
	[Documentation]   Tests that two pictures look same --> layout is not broken
	[Arguments]	   ${pic1}   ${pic2}   ${movetolerance}=${EMPTY}
	Open Eyes   lib=none   # SETTING LIBRARY TO NONE BECAUSE PICTURE COMPARISON DOES OTHERWISE GIVE FALSE POSITIVES
    Compare Two Images   ref=${pic1}   actual=${pic2}   output=diffimage.png   tolerance=${movetolerance}
     


Go To New Annoucement Site
	GoTo   ${URL_content_page}
	Click Add Content
	Wait Until Keyword Succeeds  5x  200ms  Click Add Announcement

Go To New Page Site
	GoTo   ${URL_content_page}
	Click Add Content
	Wait Until Keyword Succeeds  5x  200ms  Click Add Page

Go To New Service Site
	Click Add Content
	Wait Until Keyword Succeeds  5x  200ms  Click Add Service

Go To New LandingPage Site
	GoTo   ${URL_content_page}
	Click Add Content
	Wait Until Keyword Succeeds  5x  200ms  Click Add Landing Page

Go To New News-Item Site
	GoTo   ${URL_content_page}
	Click Add Content
	Wait Until Keyword Succeeds  5x  200ms  Click Add News-Item

Click Add Content
	[Documentation]   Add Content ('Lisää sisältöä') in Content Menu
	Wait Until Element Is Visible   css:#block-hdbt-admin-local-actions > ul > li > a   timeout=3
	Wait Until Keyword Succeeds  5x  200ms  Click Element  css:#block-hdbt-admin-local-actions > ul > li > a
	
   
Click Add Page
	[Documentation]   Add Page ('Sivu') click in Add Content('Lisää sisältöä') -menu
	Wait Until Element Is Visible  //a[contains(@href, '/node/add/page')][@class='admin-item__link']   timeout=3
	Wait Until Keyword Succeeds  5x  200ms  Click Element  //a[contains(@href, '/node/add/page')][@class='admin-item__link']
	Element Should Not Be Visible   //a[contains(@href, '/node/add/page')][@class='admin-item__link']

Click Add Service
	Wait Until Element Is Visible  //a[contains(@href, '/node/add/service')][@class='admin-item__link']   timeout=3
	Wait Until Keyword Succeeds  5x  200ms  Click Element  //a[contains(@href, '/node/add/service')][@class='admin-item__link']
	Element Should Not Be Visible   //a[contains(@href, '/node/add/service')][@class='admin-item__link']

Click Add Landing Page
	[Documentation]   Add LandingPage ('Laskeutumissivu') click in Add Content('Lisää sisältöä') -menu
	Wait Until Element Is Visible  //a[contains(@href, '/node/add/landing_page')][@class='admin-item__link']   timeout=3
	Wait Until Keyword Succeeds  5x  200ms  Click Element  //a[contains(@href, '/node/add/landing_page')][@class='admin-item__link']
	Element Should Not Be Visible   //a[contains(@href, '/node/add/landing_page')][@class='admin-item__link']

Click Add Announcement
	[Documentation]   Add Annoucement ('Poikkeusilmoitus') click in Add Content('Lisää sisältöä') -menu
	Wait Until Element Is Visible  //a[contains(@href, '/node/add/announcement')][@class='admin-item__link']   timeout=3
	Wait Until Keyword Succeeds  5x  200ms  Click Element  //a[contains(@href, '/node/add/announcement')][@class='admin-item__link']
	Element Should Not Be Visible   //a[contains(@href, '/node/add/announcement')][@class='admin-item__link']

Click Add News-Item
	[Documentation]   Add News Item ('Uutinen') click in Add Content('Lisää sisältöä') -menu
	Wait Until Element Is Visible  //a[contains(@href, '/node/add/news_item')][@class='admin-item__link']   timeout=3
	Wait Until Keyword Succeeds  5x  200ms  Click Element  //a[contains(@href, '/node/add/news_item')][@class='admin-item__link']
	Element Should Not Be Visible   //a[contains(@href, '/node/add/news_item')][@class='admin-item__link']

Go To Translate Selection Page
	[Documentation]   Goes To Translations Page for first document in the content list
	Set Focus To Element   css:ul.local-tasks > li:nth-child(5) > a
	Click Link   css:ul.local-tasks > li:nth-child(5) > a

Submit The New ${pagetype}
	[Documentation]   Sleeps 1 second in case of pictures added so that they have time to load into content view.
	Run Keyword If  ${mediaadded} > 0   Sleep  1
	Wait Until Keyword Succeeds  2x  1  Submit New Content

Submit New Content
	[Documentation]  User submits new page and it is saved and appears in content view
	Execute javascript  window.scrollTo(0, 400)    # SCROLL TO PAGE TOP SINCE SAVE BUTTON IS BEHIND OTHER ELEMENTS IN SOME TESTS
	Wait Until Keyword Succeeds  5x  100ms  Click Button   ${Btn_Submit}
	Wait Until Keyword Succeeds  5x  100ms  Element Should Not Be Visible   ${Btn_Submit}
	${isserviceandunit}=  Suite Source Contains Text  ServiceAndUnit
	Run Keyword If  '${isserviceandunit}'!='True'  Set Test Variable  ${pagesadded}    ${pagesadded}+1

Submit New Media
	[Documentation]  User submits new media content and it is saved and appears in media view
	Wait Until Keyword Succeeds  5x  200ms  Click Element   ${Btn_Insert_Pic}
	Wait Until Keyword Succeeds  5x  200ms  Element Should Not Be Visible     ${Btn_Insert_Pic}
	Set Test Variable  ${mediaadded}    ${mediaadded}+1
		
Go To New ${pagetype} -View For ${language} Translation
	Go To Translate Selection Page
	Go To ${language} Translation Page
	
Delete Test Automation Created Content
	[Documentation]   Deletes Created Item By assuming it is the topmost one in the list. Returns to content page afterwards.
	Wait Until Keyword Succeeds  5x  200ms  Click Element  css:.local-tasks__wrapper > ul > li:nth-child(3) > a
	Wait Until Keyword Succeeds  5x  200ms  Click Button   ${Btn_Actions_SelectedItem_Deletebutton}  
	

	
Delete Test Automation Created Media
	Go To   ${URL_media_page}
	Wait Until Keyword Succeeds  5x  200ms  Click Button   ${Btn_Actions_Dropbutton}
	Click Element  ${Btn_Actions_ContentMenu_Deletebutton}
	Click Element  ${Btn_Actions_SelectedItem_Deletebutton}
	Go To   ${URL_media_page}	
	
Delete Test Automation Created Paragraphs
	Click Link   partial link: Test_Automation
	Wait Until Keyword Succeeds  5x  200ms  Click Element  css:.local-tasks__wrapper > ul > li:nth-child(3) > a
	Wait Until Keyword Succeeds  5x  200ms  Click Button   ${Btn_Actions_SelectedItem_Deletebutton}
	Go To   ${URL_paragraphs_page}	
	
Copy Original Screenshot To Reports Folder
	[Arguments]     ${source}
	Copy File    ${source}    robotframework-reports/originals/

Get Admin Url
   [Documentation]   Gets URL needed in localhost testing.
   ${admin_url} =   Run  ${ADMIN_URL}
   Set Test Variable   ${admin_url}

Set Service As Published
	${isalreadypublished}=  Run Keyword And Return Status   Wait Until Page Contains Element  css:input.tpr-entity-status:checked   1
	Run Keyword If  '${isalreadypublished}'!='True'  Click Element   id:edit-status
	Set Test Variable  ${serviceispublished}   true

Set Unit As Published
	${isalreadypublished}=  Run Keyword And Return Status   Wait Until Page Contains Element  css:input.tpr-entity-status:checked   1
	Run Keyword If  not(${isalreadypublished})  Click Element   id:edit-status
	Run Keyword If  not(${isalreadypublished})  Set Test Variable  ${unitispublished}   true

Select Language
	[Arguments]     ${value}
	[Documentation]  fi = Finnish , sv = Swedish , en = English , ru = Russian
	${value}=  Convert To Lower Case   ${value}
	IF    '${value}'=='finnish'
		Wait Until Keyword Succeeds  5x  200ms  Set Focus To Element  css:[lang|=fi]
        Click Element  css:[lang|=fi]
    ELSE IF    '${value}'=='english'
        Wait Until Keyword Succeeds  5x  200ms  Set Focus To Element  css:[lang|=en]
        Click Element  css:[lang|=en]
    ELSE IF    '${value}'=='swedish'
    	Wait Until Keyword Succeeds  5x  200ms  Set Focus To Element  css:[lang|=sv]
    	Click Element  css:[lang|=sv]
    ELSE
    	Click Element  css:[lang|=ru]
    END 

Return Correct Title
	[Arguments]     ${language}
	${title}=	Set Variable If  '${language}'=='fi'  Juhani Aho: Rautatie
	...				'${language}'=='en'  Emily Bronte: Wuthering Heights
	...		 		'${language}'=='sv'  Selma Lagerlof: Bannlyst
	[Return]		${title}

Return Correct Description
	[Arguments]     ${language}
	${description}=	Get File  ${CONTENT_PATH}/text_description_short_${language}.txt
	[Return]		${description}

Return Correct Content
	[Arguments]     ${language}
	${content}=	Get File  ${CONTENT_PATH}/text_content_short_${language}.txt
	[Return]		${content}

Return Lead-in From Page	
	${content}=	Get Text    ${Txt_Leadin_Content}
	[Return]		${content}

Title Should Match Current Language Selection
	[Arguments]   ${string}
	${string}=  Replace Encoded Characters From String   ${string}   ${EMPTY}    UTF-8    \\xc2\\xad
	Run Keyword If  '${language}'=='fi'  Should Match Regexp   ${string}   Juhani Aho: Rautatie
	Run Keyword If  '${language}'=='en'  Should Match Regexp   ${string}   Emily Bronte: Wuthering Heights
	Run Keyword If  '${language}'=='sv'  Should Match Regexp   ${string}   Selma Lagerlof: Bannlyst  

Lead-In Should Match Current Language Selection
	[Arguments]   ${string}
	Run Keyword If  '${language}'=='fi'  Should Match Regexp  ${string}   "Rautatie" on Juhani Ahon
	Run Keyword If  '${language}'=='en'  Should Match Regexp  ${string}   In the late winter months
	Run Keyword If  '${language}'=='sv'  Should Match Regexp  ${string}   Sven Elversson var nära att dö under en nordpolsexpedtion

Description Should Match Current Language Selection
	[Arguments]   ${string}
	Run Keyword If  ('${language}'=='fi') & ('${TEST NAME}'=='Accordion')  Should Match Regexp  ${string}   Sitä Matti ajatteli, mitä rovastin ja ruustinnan
	Run Keyword If  ('${language}'=='fi') & (not('${TEST NAME}'=='Accordion'))   Should Match Regexp  ${string}   "Rautatie" on Juhani Ahon
	Run Keyword If  ('${language}'=='en') & ('${TEST NAME}'=='Accordion')  Should Match Regexp  ${string}   “It is not,” retorted she
	Run Keyword If  ('${language}'=='en') & (not('${TEST NAME}'=='Accordion'))  Should Match Regexp  ${string}   In the late winter months
	Run Keyword If  ('${language}'=='sv') & ('${TEST NAME}'=='Accordion')  Should Match Regexp  ${string}   På Grimön i den västra	
	Run Keyword If  ('${language}'=='sv') & (not('${TEST NAME}'=='Accordion'))  Should Match Regexp  ${string}   Sven Elversson var nära att dö under en nordpolsexpedtion

Content Should Match Current Language Selection
	[Arguments]   ${string}
	Run Keyword If  '${language}'=='fi'  Should Match Regexp  ${string}   Sitä Matti ajatteli, mitä rovastin ja ruustinnan kanssa oli puhunut
	Run Keyword If  '${language}'=='en'  Should Match Regexp  ${string}   “It is not,” retorted she
	Run Keyword If  '${language}'=='sv'  Should Match Regexp  ${string}   På Grimön i den västra skärgården bodde

Login And Go To Content Page
	[Documentation]   Preparatory action for platform tests: User logs in and then navigates to Content('Sisältö')
	...				  page.
	Set Shortened Suite Name
	IF    ${CI}
		Register Keyword To Run On Failure   NONE
		Log-In In CI Environment
	ELSE
		Open Browser  ${URL_login_page}  ${BROWSER}
		Log In
	END

	Set Window Size   1296   696

Set CI Arguments And Open Browser
	${chrome_options}=  Evaluate  sys.modules['selenium.webdriver'].ChromeOptions()  sys, selenium.webdriver
    Call Method    ${chrome_options}    add_argument    --no-sandbox
    Call Method    ${chrome_options}    add_argument    --headless
    Call Method    ${chrome_options}    add_argument    --ignore-certificate-errors
    Call Method    ${chrome_options}    add_argument    --disable-gpu
        
    Open Browser    ${URL_login_page}    chrome    options=${chrome_options}
	
	
Log-In In CI Environment
	Set CI Arguments And Open Browser
    Log In
	
Rename Picture With New Name
	[Documentation]   Idea is to Replace Reports file picture with new name in order to help in 
	...				  maintenance of comparison pictures
	[Arguments]   ${originalpic}   ${comparisonpic}
	
	IF    ${CI}
		   ${newname}=  Fetch From Right   ${originalpic}   ${BROWSER}/ci/
		   Move File   robotframework-reports/${comparisonpic}   robotframework-reports/${newname}
	ELSE
		   ${newname}=  Fetch From Right   ${originalpic}   ${BROWSER}/	   
		   Move File   ${REPORTS_PATH}/${comparisonpic}   ${REPORTS_PATH}/${newname}
	END

Select Icon With Name
	[Arguments]   ${icon_name}
	Click Element With Value   ${icon_name}
	
Take Screenshot Of Content
	Maximize Browser Window
	Execute javascript  document.body.style.zoom="30%"
	Capture Screenshot For Picture Comparison
	Execute javascript  document.body.style.zoom="100%"

New Window Should Be Opened
	[Arguments]   ${title}
	${titles}=  Get Window Titles
	Should Contain   ${titles}   ${title}
	
# COLUMNS RELATED
	
Add ${content} to Left Column
	[Documentation]  Here we need to do some tricks in case picture tests original size. Content -string is modified
	...				 so that picture compare assertion works. Also long, snowdrops picture is used in the case because
	...				 pictures with longer width value does not get cropped. Only long pictures do.
	${content}=  Convert To Lower Case   ${content}
	Wait Until Keyword Succeeds  5x  100ms  Click Button  ${Ddn_Column_Left_AddContent}
	Run Keyword If  '${content}'=='picture'  Add Picture to Column   Left    train   @{pic_1_texts_${language}}
	Run Keyword If  '${content}'=='original picture'  Add Picture to Column   Left    snowdrops   @{pic_1_texts_${language}}
	Run Keyword If  '${content}'=='text'  Add Text Content To Column on Left
	Run Keyword If  ('${content}'=='picture') & ('${language}'=='fi')  Add Picture Caption to Left
	${content}=  Remove String And Strip Text   ${content}   original
	Set Test Variable  ${content1}   ${content}
	
Add ${content:[^"]+} to Right Column
	[Documentation]   Adds given content to Right column.
	${content}=  Convert To Lower Case   ${content}
	Set Test Variable  ${content2}   ${content}
	Wait Until Element Is Visible  ${Ddn_Column_Right_AddContent}   timeout=3
	Wait Until Keyword Succeeds  10x  500ms  Click Button  ${Ddn_Column_Right_AddContent}
	Run Keyword If  '${content}'=='picture'  Add Picture to Column   Right    temple   @{pic_2_texts_${language}}
	Run Keyword If  '${content}'=='text'  Add Text Content To Column on Right
	Run Keyword If  '${content}'=='link'  Add "${linkstyle}" Link To Right Column
	
Add Picture to Column
	[Documentation]  Adds picture and fills given content. selection= picture name from images -folder at src/main/
	...			     resources.  side = 'left' of 'right' column  . content = content as list items.    
	[Arguments]     ${side}   ${selection}   @{content}
	Wait Until Element Is Visible  ${Opt_Column_${side}_AddContent_Image}   timeout=3
	Click Element  ${Opt_Column_${side}_AddContent_Image}
	${pictitle}=  Get From List  ${content}   0
	${picdescription}=  Get From List  ${content}   1
	${pgrapher}=  Get From List  ${content}   2
	Wait Until Element Is Visible  ${Btn_Column_${side}_Picture}   timeout=3
	Wait Until Keyword Succeeds  6x  300ms  Click Element  ${Btn_Column_${side}_Picture}
	Wait Until Keyword Succeeds  6x  300ms  Choose File   ${Btn_File_Upload}   ${IMAGES_PATH}/${selection}.jpg
	Wait Until Keyword Succeeds  6x  300ms  Input Text    ${Inp_Pic_Name}   ${pictitle}
	Input Text    ${Inp_Pic_AltText}   ${picdescription} 
	Input Text    ${Inp_Pic_Photographer}   ${pgrapher}
	Click Button   ${Btn_Save}
	Wait Until Keyword Succeeds  5x  200ms  Submit New Media
	Wait Until Keyword Succeeds  10x  500ms   Add Picture Caption to ${side}  
	Set Test Variable  ${picture}    picture   

Add Picture Caption to ${side}
	${editpicturevisible}=  Run Keyword And Return Status    Element Should Be Visible  ${Btn_Column_${side}_Edit}   timeout=1
	Run Keyword If   ${editpicturevisible}   Wait Until Keyword Succeeds  5x  200ms  Click Element   ${Btn_Column_${side}_Edit}
	
	Wait Until Keyword Succeeds  5x  200ms  Scroll Element Into View   ${Inp_Column_${side}_Picture_Caption}
	Run Keyword If  '${side}'=='Left'	Wait Until Keyword Succeeds  5x  200ms  Input Text    ${Inp_Column_Left_Picture_Caption}   ${pic_1_caption_${language}}
	Run Keyword If  '${side}'=='Right'	Wait Until Keyword Succeeds  5x  200ms  Input Text    ${Inp_Column_Right_Picture_Caption}   ${pic_2_caption_${language}}
	
Add Text Content To Column on ${side}
	[Documentation]   Adds text content to selected column by selecting content type first and then inserting text
	${isaddfromlibrary}=  Suite Source Contains Text    Add_From_Library
	${issidebar}=  Test Name Contains Text    Sidebar
	Run Keyword If  ${isaddfromlibrary} & ('${language}'!='fi')   Click And Select Text As ${side} Content Type
	Run Keyword If  '${language}'=='fi'  Click And Select Text As ${side} Content Type
	${TextFileContent}=  Get File  ${CONTENT_PATH}/text_content_short_${language}.txt
	@{content} =	Split String	${TextFileContent}   .,.
	${content_left}=  Get From List  ${content}   0
	${content_right}=  Get From List  ${content}   1
	${content_text}=  Set Variable If
	... 	 '${side}'=='Left'	${content_left}
	... 	 '${side}'=='Right'	${content_right}
	${editpicturevisible}=  Run Keyword And Return Status    Element Should Not Be Visible  ${Btn_Column_${side}_Edit}   timeout=1
	Run Keyword If   '${editpicturevisible}'!='True'   Wait Until Keyword Succeeds  5x  200ms  Click Element   ${Btn_Column_${side}_Edit}
	
	IF    (${issidebar})
        Wait Until Keyword Succeeds  10x  500ms  Input Text To Frame   css:#cke_1_contents > iframe   //body   ${content_text}
    ELSE IF    (${isaddfromlibrary})
        Wait Until Keyword Succeeds  10x  500ms  Input Text To Frame   ${Frm_Paragraph_Column_${side}_Text}   //body   ${content_text}
    ELSE
    	Wait Until Keyword Succeeds  10x  500ms  Input Text To Frame   ${Frm_Column_${side}_Text}   //body   ${content_text}
    END 
		
Click And Select Text As ${side} Content Type
	Wait Until Element Is Visible  ${Opt_Column_${side}_AddContent_Text}   timeout=3
	Wait Until Keyword Succeeds  3x  100ms  Click Element  ${Opt_Column_${side}_AddContent_Text}

Set Shortened Suite Name
	[Documentation]  Lets set shortened suite name for clarity and pabot purposes on content removal
	${suitesplitted}=  Split String   ${SUITE NAME}   separator=.Repositories.
	${modified}=   Get From List  ${suitesplitted}  -1
	Set Suite Variable   ${SUITE}   ${modified}  

Compare Two Pictures
	[Arguments]   ${movetolerance}=${EMPTY}
	IF  ${CI}   
		${originalpic} =  Set Variable   ${SCREENSHOTS_PATH}/${BROWSER}/ci/${language}_${TEST NAME}_${BROWSER}.png
	ELSE
		${originalpic} =  Set Variable   ${SCREENSHOTS_PATH}/${BROWSER}/${language}_${TEST NAME}_${BROWSER}.png
	END
	
	${comparisonpic}=  Set Variable  ${BROWSER}_TESTRUN-${SUITE}-${TEST NAME}_${language}.png
	Compare Pictures And Handle PictureData   ${originalpic}   ${comparisonpic}   ${movetolerance}
	
Compare Pictures And Handle PictureData
	[Arguments]   ${originalpic}   ${comparisonpic}   ${movetolerance}=${EMPTY}
	Run Keyword If   ${USEORIGINALNAME}   Rename Picture With New Name   ${originalpic}   ${comparisonpic}
	Run Keyword If   ${PICCOMPARE}   Copy Original Screenshot To Reports Folder   ${originalpic}
	Run Keyword If   ${PICCOMPARE}   Compared Pictures Match   ${originalpic}    ${comparisonpic}   ${movetolerance}

Input Text Content To Frame
	[Arguments]   ${content}    ${cke}
	Input Text To Frame   css:#${cke} > iframe   //body   ${content}
		
Input Non-paragraph Related Content
	[Arguments]   ${pagetype}
	Input Title  Test Automation: ${SUITE}.${TEST NAME}
	${headertitle}=  Get File  ${CONTENT_PATH}/text_description_short_${language}.txt
	${islandingpage}=  Suite Source Contains Text    Landing_Page
	Run Keyword If  not(${islandingpage})   Input Content Header Title  ${headertitle}   ${pagetype}	

${paragraphname} Is Present In Page
	Wait Until Keyword Succeeds  6x  200ms  Element Should Be Visible  css:.component.component--${paragraphname}
	Wait Until Keyword Succeeds  6x  200ms  Element Should Be Visible  css:.component__content.${paragraphname}
		
Input Etusivu Instance Spesific Content
	[Documentation]   Part Of Content Only Exists in Etusivu(FrontPage) Instance
	Input Non-paragraph Related Content   Etusivu
	Click Element   css:#edit-group-main-image > summary
	Wait Until Keyword Succeeds  6x  300ms  Click Button  ${Btn_MainImage}
	Wait Until Keyword Succeeds  6x  300ms  Choose File   ${Btn_File_Upload}   ${IMAGES_PATH}/grand-canyon.jpg
	Wait Until Keyword Succeeds  6x  300ms  Input Text    ${Inp_Pic_Name}   Grand Canyon
	Wait Until Keyword Succeeds  6x  300ms  Input Text    ${Inp_Pic_AltText}   Hieno käyntikohde Arizonassa
	Input Text    ${Inp_Pic_Photographer}   Testi Valokuvaaja
	Click Button   ${Btn_Save}
	Wait Until Keyword Succeeds  5x  200ms  Submit New Media
	Wait Until Keyword Succeeds  6x  300ms   Input Text  ${Inp_MainImage_Caption}   Kuvaselostusteksti
	Click Element   css:#edit-group-news-item-links > summary
	#LINK 1
	Wait Until Keyword Succeeds  5x  200ms  Input Text    ${Inp_FrontPage_Links_Url}   Multamäen rautatieasema
	Wait Until Keyword Succeeds  5x  200ms  Input Text    ${Inp_FrontPage_Links_Title}   Multamäen rautatieasema
	Click Button  ${Inp_FrontPage_Links_Addmore}
	Wait Until Keyword Succeeds  6x  300ms  Page Should Have Given Number Of Elements   css:input[name*=uri]   2
	#LINK 2

	Wait Until Keyword Succeeds  5x  200ms  Input Text    ${Inp_FrontPage_Links_Url}   Liitupiippu
	Wait Until Keyword Succeeds  5x  200ms  Input Text    ${Inp_FrontPage_Links_Title}   Liitupiippu
	
		
	Select From List By Label   css:#edit-field-news-item-tags   Kaupunki ja hallinto
	Select From List By Label   css:#edit-field-news-groups   Vanhukset
	Select From List By Label   css:#edit-field-news-neighbourhoods   Kluuvi
	Select From List By Label   css:#edit-field-news-neighbourhoods   Kruununhaka
