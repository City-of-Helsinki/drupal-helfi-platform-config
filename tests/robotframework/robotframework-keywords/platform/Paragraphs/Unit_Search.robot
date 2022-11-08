*** Settings ***

Resource        ../Contenthandler.robot
Resource        ../Commonkeywords.robot

*** Keywords ***

Create Unit Search Paragraph
	[Arguments]	  ${pagetype}
	Input Non-paragraph Related Content   ${pagetype}
	Run Keyword If  '${language}'=='fi'  Open Paragraph For Edit   ${Opt_UnitSearch}
	Wait Until Keyword Succeeds  5x  200ms  Input Title To Paragraph   ${Inp_UnitSearch_Title}
	Sleep  1
	#WE´LL USE HARDCODED INDEX VALUES SINCE THESE LIKELY WONT CHANGE IN CI
	Select From List By Index   ${Sel_UnitSearch_Units}   0     
	Select From List By Index   ${Sel_UnitSearch_Units}   2	    
	${TextFileContent}=  Return Correct Content   ${language}
	Wait Until Keyword Succeeds  5x  200ms  Input Text To Frame   ${Frm_UnitSearch_Content}   //body   ${TextFileContent}
	Wait Until Keyword Succeeds  5x  200ms  Click Element   css:details[id*=subform-group-unit-search-metadata]
	Add Metadata
	
Add Metadata
	Wait Until Keyword Succeeds  5x  200ms  Input Text    ${Inp_Chart_Metadata_SearchField_Title}   Hakuotsikko   
	Input Text    ${Inp_Chart_Metadata_SearchField_DefaultText}   Oletustekstiarvo
	Input Text    ${Inp_Chart_Metadata_SearchField_ButtonText}   Painiketeksti
	Input Text    ${Inp_Chart_Metadata_SearchField_LoadMore}   Lisää toimipisteitä tästä
	
Unit Links Are Working Correctly
	${contentpageurl}=   Get Location
	Click Element   //a[contains(@href, 'lippulaivan-kirjasto')]
	${currenturl}=   Get Location
	Should Contain   ${currenturl}   lippulaivan-kirjasto
	Goto   ${contentpageurl}
	Click Element   //a[contains(@href, 'otaniemen-kirjasto')]
	${currenturl}=   Get Location
	Should Contain   ${currenturl}   otaniemen-kirjasto
	Goto   ${contentpageurl}
	
The Search Bar Is Working Correctly
	Search Bar Works By Unit Name
	Clear Element Text   ${Inp_UnitSearch_SearchField}
	Search Bar Works By Unit Address
	Clear Element Text   ${Inp_UnitSearch_SearchField}
	Search Bar Works By Unit Post Number
	Clear Element Text   ${Inp_UnitSearch_SearchField}
	Click Button   ${Inp_UnitSearch_SearchButton}
	Wait Until Keyword Succeeds  5x  200ms  Page Should Contain Link   Lippulaivan kirjasto
	Wait Until Keyword Succeeds  5x  200ms  Page Should Contain Link   Otaniemen kirjasto

Search Bar Metadata is Correct
	${title}=   Get Text   css:form.views-exposed-form > div > label.hds-text-input__label
	Should Be Equal   ${title}   Hakuotsikko   
	Element Attribute Value Should Be   css:input[name=unit_search]   placeholder   Oletustekstiarvo   
	Element Attribute Value Should Be   css:input[data-drupal-selector*=edit-submit-unit-search]   value   Painiketeksti   

	

Search Bar Works By Unit Name
	Input Text  ${Inp_UnitSearch_SearchField}   Otan
	Click Button   ${Inp_UnitSearch_SearchButton}
	Wait Until Keyword Succeeds  5x  200ms  Page Should Not Contain Link   Lippulaivan kirjasto
	Wait Until Keyword Succeeds  5x  200ms  Page Should Contain Link   Otaniemen kirjasto
	
Search Bar Works By Unit Address
	Input Text  ${Inp_UnitSearch_SearchField}   merikar
	Click Button   ${Inp_UnitSearch_SearchButton}
	Wait Until Keyword Succeeds  5x  200ms  Page Should Not Contain Link   Otaniemen kirjasto
	Wait Until Keyword Succeeds  5x  200ms  Page Should Contain Link   Lippulaivan kirjasto

Search Bar Works By Unit Post Number
	Sleep  0.2
	Input Text  ${Inp_UnitSearch_SearchField}   02150
	Click Button   ${Inp_UnitSearch_SearchButton}
	Wait Until Keyword Succeeds  5x  200ms  Page Should Contain Link   Otaniemen kirjasto
	Wait Until Keyword Succeeds  5x  200ms  Page Should Not Contain Link   Lippulaivan kirjasto
	
Unit Address And Phone Data Is Correct
	Input Text  ${Inp_UnitSearch_SearchField}   Otan
	Click Button   ${Inp_UnitSearch_SearchButton}
	Wait Until Keyword Succeeds  5x  200ms  Page Should Not Contain Link   Lippulaivan kirjasto
	${addressline1}=  Get Text   css:.address-line1
	${postalcode}=  Get Text   css:.postal-code
	${city}=  Get Text   css:.locality
	Click Link   Otaniemen kirjasto
	${currenturl}=   Get Location
	Should Contain   ${currenturl}   otaniemen-kirjasto
	${addressline1_unitsite}=  Get Text   css:.address-line1
	${postalcode_unitsite}=  Get Text   css:.postal-code
	${city_unitsite}=  Get Text   css:.locality
	${phone_unitsite}=  Get Text   css:#block-hdbt-subtheme-sidebarcontentblock > div > div > div.unit__contact-row.unit__contact-row--phone > a
	Should Be Equal   ${addressline1}   ${addressline1_unitsite}
	Should Be Equal   ${postalcode}   ${postalcode_unitsite}
	Should Be Equal   ${city}   ${city_unitsite}
	Should Not Be Empty   ${phone_unitsite}
	
Layout Should Not Have Changed
	Run Keyword And Ignore Error  Accept Cookies
	Capture Screenshot For Picture Comparison    css=main.layout-main-wrapper
	Compare Two Pictures	