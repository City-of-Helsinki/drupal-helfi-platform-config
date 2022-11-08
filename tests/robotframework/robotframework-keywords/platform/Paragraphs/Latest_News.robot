*** Settings ***
Resource        ../Contenthandler.robot
Resource        ../Commonkeywords.robot

*** Keywords ***

Create Latest News Paragraph
	Input Non-paragraph Related Content   Etusivu
	Open Paragraph For Edit   ${Opt_LatestNews}
	Wait Until Page Contains Element   css:tbody > tr[class*=front-page-latest-news]
	

Latest News Paragraph With Content Exists
	latest-news Is Present In Page
	Page Should Contain Link  Sähköteknillinen koulu
	Page Should Contain Link  Nurhaci
	Page Should Have Given Number Of Elements	css:.news-listing__item   6