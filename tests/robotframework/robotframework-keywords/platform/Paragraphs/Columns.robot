*** Settings ***
Resource        ../Contenthandler.robot
Resource        ../Commonkeywords.robot

*** Variables ***
@{blur}   css=p


*** Keywords ***
Create ${pagetype} With ${division} Division And ${contenttype} Content
 	Set Test Variable  ${contenttype}   ${contenttype}
 	Set Test Variable   ${division}   ${division}
 	Input Non-paragraph Related Content   ${pagetype}
	Run Keyword If  '${language}'=='fi'  Open Paragraph For Edit   ${Opt_AddColumns}
	Wait Until Keyword Succeeds  5x  100ms  Input Title To Paragraph   ${Inp_Column_Title}
	Click Element With Value   '${division}'

Create ${pagetype} With ${division} Division And ${contenttype} Content in ${lang_selection} Language
	${language_pointer}=   Get Language Pointer   ${lang_selection}
	Set Test Variable   ${language}   ${language_pointer}
	Run Keyword If  '${lang_selection}'=='Finnish'  Go To New ${pagetype} Site
	Run Keyword If  '${lang_selection}'!='Finnish'  Go To New ${pagetype} -View For ${lang_selection} Translation
	Create ${pagetype} With ${division} Division And ${contenttype} Content
	Run Keyword If  '${lang_selection}'=='Finnish'  Add Picture to Left Column
	Add Picture Caption to Left
	Run Keyword If  '${lang_selection}'=='Finnish'  Add Text to Right Column
	Run Keyword If  '${lang_selection}'!='Finnish'	Add Text Content To Column on Right
	Submit The New ${pagetype}
	Columns.Take Screenshot Of Content

Add ${linkstyle} Link To ${side} Column
	${linkstyle}=  Remove String And Strip Text   ${linkstyle}   "
	Wait Until Element Is Visible  ${Opt_Column_${side}_AddContent_Link}   timeout=3
	Click Element  ${Opt_Column_${side}_AddContent_Link}
	Wait Until Keyword Succeeds  5x  100ms  Input Text   ${Inp_Column_${side}_Link_URL}   https://fi.wikipedia.org/wiki/Rautatie_(romaani)    
	Input Text   ${Inp_Column_${side}_Link_Title}    ${link_title_${language}}
	Click Element  ${Ddn_Column_${side}_Link_Design}
	Run Keyword If  '${linkstyle}'=='Fullcolor'  Click Element   ${Opt_Column_${side}_Link_ButtonFullcolor}
	Run Keyword If  '${linkstyle}'=='Framed'  Click Element   ${Opt_Column_${side}_Link_ButtonFramed}
	Run Keyword If  '${linkstyle}'=='Transparent'  Click Element   ${Opt_Column_${side}_Link_ButtonTransparent}



Use Original Aspect Ratio on ${side}
	#Element is behind another. --> Scroll it into view so we can click it
	Execute javascript  window.scrollTo(0, 400)
	Wait Until Keyword Succeeds  5x  200ms  Click Element   ${Swh_Column_${side}_Picture_Orig_Aspect_Ratio}
	Set Test Variable  ${picsize}   original

Take Screenshot Of Content
	Maximize Browser Window
	Capture Screenshot For Picture Comparison   css=main.layout-main-wrapper
	
${pagetype} Content Matches Language
	${Title}=  Return Title From ${pagetype}
	${Description}=  Return Description From ${pagetype}
	${Content}=   Return Content From ${pagetype}
	Title Should Match Current Language Selection   ${Title}
	Description Should Match Current Language Selection   ${Description}	
	Columns.Content Should Match Current Language Selection   ${Content}
	
Content Should Match Current Language Selection
	[Arguments]   ${string}
	Run Keyword If  '${language}'=='fi'  Should Match Regexp  ${string}   Viittatie teki niemen nenässä polvekkeen
	Run Keyword If  '${language}'=='en'  Should Match Regexp  ${string}   If all else perished, and he remained
	Run Keyword If  '${language}'=='sv'  Should Match Regexp  ${string}   Det är bara synd, att han inte är
	
Return Title From ${pagetype}
	${title}=	Get Text    ${Txt_Title}
	[Return]		${title}

Return Description From ${pagetype}
	IF    ('${pagetype}'=='Page')
        ${description}=  Get Text    ${Txt_Leadin_Content}
    ELSE
    	${description}=  Get Text    ${Txt_Column_Description}
    END 
	[Return]		${description}

Return Content From ${pagetype}
	${content}=	Get Text    ${Txt_Content}
	[Return]		${content}
