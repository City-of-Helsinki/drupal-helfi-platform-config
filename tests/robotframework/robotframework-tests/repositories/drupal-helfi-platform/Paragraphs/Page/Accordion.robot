*** Settings ***
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/Accordion.robot
Test Setup      Login And Go To Content Page
Test Teardown   Cleanup and Close Browser	
Force Tags		PAGE   ACCORDION

*** Test Cases ***
White Accordion
	[Tags]  CRITICAL
	Given User Goes To New Page -Site
	And User Creates White Accordion With h2 Heading And Text Content
	When User Submits The New Page
	Then Layout Should Not Have Changed
	And Accordions Text Content Works As Expected	

White Accordion With Picture
	[Tags]  CRITICAL
	Given User Goes To New Page -Site
	And User Creates White Accordion With h2 Heading And Picture Content
	And User Adds Picture to Accordion
	When User Submits The New Page
	Then Layout Should Not Have Changed
	And Accordions Picture Content Works As Expected	
	
Grey Accordion
	[Tags]
	Given User Goes To New Page -Site
	And User Creates Grey Accordion With h2 Heading And Text Content
	And User Adds Content to Text Subcategory
	When User Submits The New Page
	Then Layout Should Not Have Changed
	And Accordions Text Content Works As Expected	

Columns With Pictures
	[Tags]  CRITICAL
	Given User Goes To New Page -Site
	And User Creates White Accordion With h2 Heading And Columns Content
	User Adds Picture Content to Columns Subcategory
	When User Submits The New Page
	Then Layout Should Not Have Changed
	And Accordions Columns Content Works As Expected	

Columns With Text
	[Tags]
	Given User Goes To New Page -Site
	And User Creates White Accordion With h2 Heading And Columns Content
	User Adds Text Content to Columns Subcategory
	When User Submits The New Page
	Then Layout Should Not Have Changed
	And Accordions Text Content Works As Expected	

Phasing
	[Tags]
	Given User Goes To New Page -Site
	And User Creates White Accordion With h2 Heading And Phasing Content
	And User Adds Phasing To Accordion
	When User Submits The New Page
	Then Layout Should Not Have Changed
	And Accordions Phasing Content Works As Expected

Two Accordions
	[Tags]
	Given User Goes To New Page -Site
	And User Creates White Accordion With h2 Heading And Text Content
	And User Adds Second Accordion
	When User Submits The New Page
	Then Layout Should Not Have Changed
	
	
*** Keywords ***
User Goes To New Page -Site		Go To New Page Site
User Submits The New Page
	Submit The New Page

User Adds Second Accordion
	Add Second Accordion

User Adds ${content} Content to Columns Subcategory
	Add ${content} Content to Columns Subcategory

User Adds Picture To Accordion
	Add Picture to Accordion

User Adds Phasing To Accordion
	Add Phasing To Accordion

User Adds Content to Text Subcategory
	Add Content To Text Subcategory

Capture Screenshot Of Accordion Contents
	 #TODO: OPEN ALSO SECOND ACCORDION IN CASE OF TWO
	 Click Element  ${Btn_Accordion_View}
	 Sleep  1
	 Capture Screenshot For Picture Comparison   css=main.layout-main-wrapper
	 Click Element  ${Btn_Accordion_View}

User Creates ${color} Accordion With ${heading} Heading And ${contenttype} Content
	Create Page With ${color} Color , ${heading} Heading And ${contenttype} Content

User Starts Creating ${color} Accordion With ${heading} Heading And ${contenttype} With ${subcontent} Content
	Create Page With ${color} Color , ${heading} Heading And ${contenttype} With ${subcontent} Content
	
Layout Should Not Have Changed
	Run Keyword And Ignore Error   Accept Cookies
	Capture Screenshot Of Accordion Contents
	Compare Two Pictures