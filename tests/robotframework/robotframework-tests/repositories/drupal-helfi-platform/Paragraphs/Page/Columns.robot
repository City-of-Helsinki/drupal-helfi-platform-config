*** Settings ***
Documentation   Testing Columns Settings in Platform by comparing layout to default picture. Testing is performed with
...				Different text deviation like 50-50, 30-70 and with pictures and links added.
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/Columns.robot
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/Add_From_Library.robot
Test Setup      Login And Go To Content Page
Test Teardown   Cleanup and Close Browser	
Force Tags		PAGE   COLUMNS

*** Test Cases ***
50-50
	[Tags]  CRITICAL
	Given User Goes To New Page -Site
	And User Starts Creating Page With 50-50 Division And Text Content
	And User Adds Text to Left Column
	And User Adds Text to Right Column
	When User Submits The New Page
	Then Layout Should Not Have Changed	
	And Columns Paragraph Is Present
	
30-70
	[Tags]
	Given User Goes To New Page -Site 
	And User Starts Creating Page With 30-70 Division And Text Content
	And User Adds Text to Left Column
	And User Adds Text to Right Column
	When User Submits The New Page
	Then Layout Should Not Have Changed
	And Columns Paragraph Is Present	

70-30
	[Tags]
	Given User Goes To New Page -Site
	And User Starts Creating Page With 70-30 Division And Text Content
	And User Adds Text to Left Column
	And User Adds Text to Right Column
	When User Submits The New Page
	Then Layout Should Not Have Changed
	And Columns Paragraph Is Present

50-50 with picture
	[Tags]  CRITICAL
	Given User Goes To New Page -Site  
	And User Starts Creating Page With 50-50 Division And Picture Content
	And User Adds Picture to Left Column
	And User Adds Picture to Right Column
	When User Submits The New Page
	Then Layout Should Not Have Changed
	And Columns Paragraph Is Present

50-50 with picture and text
	[Tags]  CRITICAL
	Given User Goes To New Page -Site 
	And User Starts Creating Page With 50-50 Division And Mixed Content
	And User Adds Picture to Left Column
	And User Adds Text to Right Column
	When User Submits The New Page
	Then Layout Should Not Have Changed
	And Columns Paragraph Is Present

70-30 with original size picture and text
	[Tags]  CRITICAL
	Given User Goes To New Page -Site
	And User Starts Creating Page With 70-30 Division And Mixed Content
	And User Adds Original Picture to Left Column
	And Picture on Left Has Original Aspect Ratio Enabled
	And User Adds Text to Right Column
	When User Submits The New Page
	Then Layout Should Not Have Changed
	And Columns Paragraph Is Present

Finnish English Swedish Translations
	[Tags]  CRITICAL
	Given User Creates Page With 50-50 Division And Mixed Content in Finnish Language
	And User Creates Page With 50-50 Division And Mixed Content in English Language
	And User Creates Page With 50-50 Division And Mixed Content in Swedish Language
	Then Page Should Have Finnish Translation
	And Page Should Have English Translation
	And Page Should Have Swedish Translation


50-50 with sidebar text
	[Tags]  CRITICAL   SIDEBAR
	Given User Goes To New Page -Site
	And User Starts Creating Page With 50-50 Division And Text Content
	And User Adds Text to Left Column
	And User Adds Text to Right Column
	And User Adds Text to Sidebar
	When User Submits The New Page
	Then Layout Should Not Have Changed
	And Columns Paragraph Is Present

50-50 with sidebar content from library
	[Tags]  SIDEBAR
	Given User Creates New SidebarText Paragraphs To Library
	Given User Goes To New Page -Site
	And User Starts Creating Page With 50-50 Division And Text Content
	And User Adds Text to Left Column
	And User Adds Text to Right Column
	And User Adds Content From Library to Sidebar
	When User Submits The New Page
	Then Layout Should Not Have Changed
	And Columns Paragraph Is Present

*** Keywords ***
User Goes To New Page -Site		Go To New Page Site
User Submits The New Page
	Submit The New Page

User Starts Creating ${pagetype} With ${division} Division And ${contenttype} Content
	Create ${pagetype} With ${division} Division And ${contenttype} Content

User Creates ${pagetype} With ${division} Division And ${contenttype} Content in ${lang_selection} Language
	Create ${pagetype} With ${division} Division And ${contenttype} Content in ${lang_selection} Language

User Adds ${content} to Left Column
	${content}=  Convert To Lower Case   ${content}
	Add ${content} to Left Column

User Adds ${content} to Right Column
	Add ${content} to Right Column

User Creates New SidebarText Paragraphs To Library
	Create New Finnish SidebarText Paragraph To Library

User Adds ${content} to Sidebar
	${TextFileContent}=  Get File  ${CONTENT_PATH}/text_content_short_${language}.txt
	Run Keyword If  '${content}'=='Text'  Open Paragraph For Edit   ${Opt_SideBarText}   ${Ddn_AddContent_Sidebar}
	Run Keyword If  '${content}'=='Text'  Wait Until Keyword Succeeds  5x  100ms  Input Title To Paragraph   ${Inp_Sidebar_Text}
	Run Keyword If  '${content}'=='Text'  Wait Until Keyword Succeeds  6x  300ms  Input Text To Frame   ${Frm_Sidebar_Text}   //body   ${TextFileContent}    
	Run Keyword If  '${content}'=='Content From Library'   Open Paragraph For Edit   ${Opt_SideBarContentFromLibrary}   ${Ddn_AddContent_Sidebar}
	Run Keyword If  '${content}'=='Content From Library'   Wait Until Keyword Succeeds  6x  300ms  Click Element   //option[contains(text(),'Test_Automation_Add_From_Library_SidebarText')]

User Adds Link Button With ${linkstyle} Style into ${side} Column
	Set Test Variable   ${linkstyle}   ${linkstyle}
	${side}=  Convert To Lower Case   ${side}
	Run Keyword If  '${side}'=='right'  Add Link to Right Column
	Run Keyword If  '${side}'=='left'  Add Link to Left Column

Picture on ${side} Has Original Aspect Ratio Enabled
	Use Original Aspect Ratio on ${side}

Layout Should Not Have Changed
	Run Keyword And Ignore Error  Accept Cookies
	Columns.Take Screenshot Of Content
	Compare Two Pictures

Page Should Have ${lang_input} Translation
	Set Language Pointer   ${lang_input}
	Select Language   ${lang_input}
	Page Content Matches Language

Page Content Matches Language
	${Title}=  Columns.Return Title From Page
	${Description}=  Columns.Return Description From Page
	${Content}=  Columns.Return Content From Page
	Title Should Match Current Language Selection   ${Title}
	Description Should Match Current Language Selection   ${Description}	
	Columns.Content Should Match Current Language Selection   ${Content}

Columns Paragraph Is Present
	columns Is Present In Page