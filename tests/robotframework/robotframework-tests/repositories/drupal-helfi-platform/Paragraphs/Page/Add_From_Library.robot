*** Settings ***
Documentation   For these testcases to work  'helfi_example_content' module should be enabled
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/Add_From_Library.robot
Test Setup      Login And Go To Content Page
Test Teardown   Cleanup and Close Browser	
Force Tags		PAGE   ADDFROMLIBRARY

*** Test Cases ***
Columns
	[Tags]
	Given User Creates New Columns Paragraphs To Library
	When User Starts Creating Page With Add From Library Content For Columns -Paragraph
	Then Page Should Have Filled Columns Paragraph From Library

Accordion
	[Tags]
	Given User Creates New Accordion Paragraphs To Library
	When User Starts Creating Page With Add From Library Content For Accordion -Paragraph
	Then Page Should Have Filled Accordion Paragraph From Library

ContentCards
	[Tags]
	Given User Creates New ContentCards Paragraphs To Library
	When User Starts Creating Page With Add From Library Content For ContentCards -Paragraph
	Then Page Should Have Filled ContentCards Paragraph From Library

Picture
	[Tags]
	Given User Creates New Picture Paragraphs To Library
	When User Starts Creating Page With Add From Library Content For Picture -Paragraph
	Then Page Should Have Filled Picture Paragraph From Library

ListOfLinks
	[Tags]
	Given User Creates New ListOfLinks Paragraphs To Library
	When User Starts Creating Page With Add From Library Content For ListOfLinks -Paragraph
	Then Page Should Have Filled ListOfLinks Paragraph From Library

Text
	[Tags]
	Given User Creates New Text Paragraphs To Library
	When User Starts Creating Page With Add From Library Content For Text -Paragraph
	Then Page Should Have Filled Text Paragraph From Library

Unit Search
	[Tags]
	Given User Creates New Unitsearch Paragraphs To Library
	When User Starts Creating Page With Add From Library Content For Unitsearch -Paragraph
	Then Page Should Have Filled Unitsearch Paragraph From Library
		
*** Keywords ***
User Creates New Columns Paragraphs To Library	
	Create New Columns Paragraph To Library

User Creates New Accordion Paragraphs To Library	
	Create New Accordion Paragraph To Library

User Creates New ContentCards Paragraphs To Library	
	Create New ContentCards Paragraph To Library

User Creates New Gallery Paragraphs To Library	
	Create New Gallery Paragraph To Library


User Creates New Picture Paragraphs To Library	
	Create New Picture Paragraph To Library


User Creates New ListOfLinks Paragraphs To Library	
	Create New ListOfLinks Paragraph To Library

User Creates New Text Paragraphs To Library	
	Create New Text Paragraph To Library


User Creates New Unitsearch Paragraphs To Library	
	Create New Unit Search Paragraph To Library
		
User Starts Creating ${pagetype} With Add From Library Content For ${paragraph} -Paragraph
	Go To  ${URL_content_page}
	Go To New Page Site
	Create ${paragraph} -Paragraph ${pagetype} Content
