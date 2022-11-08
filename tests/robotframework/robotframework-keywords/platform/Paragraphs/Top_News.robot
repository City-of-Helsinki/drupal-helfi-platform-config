*** Settings ***
Resource        ../Contenthandler.robot
Resource        ../Commonkeywords.robot

*** Keywords ***

Create Top News Paragraph
	Input Non-paragraph Related Content   Etusivu
	Open Paragraph For Edit   ${Opt_TopNews}
	Zoom Out And Capture Page Screenshot

Top News Paragraph With Content Exists
	top-news Is Present In Page
	Page Should Contain Link  Sähköteknillinen koulu
	Page Should Contain Link  Häivemustesieni