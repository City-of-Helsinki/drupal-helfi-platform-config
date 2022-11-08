*** Settings ***
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/Add_From_Library.robot
Test Setup      Login And Go To Content Page
Test Teardown   Cleanup and Close Browser	
Force Tags		LANDINGPAGE   ADDFROMLIBRARY

*** Test Cases ***
Landingpage-Columns
	[Tags]
	Given User Creates New Columns Paragraphs To Library
	When User Starts Creating LandingPage With Add From Library Content For Columns -Paragraph
	Then Page Should Have Filled Columns Paragraph From Library

Landingpage-Banner
	[Tags]
	Given User Creates New Banner Paragraphs To Library
	When User Starts Creating LandingPage With Add From Library Content For Banner -Paragraph
	Then Page Should Have Filled Banner Paragraph From Library

Landingpage-ContentCards
	[Tags]
	Given User Creates New ContentCards Paragraphs To Library
	When User Starts Creating LandingPage With Add From Library Content For ContentCards -Paragraph
	Then Page Should Have Filled ContentCards Paragraph From Library

Landingpage-LiftupWithImage
	[Tags]
	Given User Creates New LiftupWithImage Paragraphs To Library
	When User Starts Creating LandingPage With Add From Library Content For LiftupWithImage -Paragraph
	Then Page Should Have Filled LiftupWithImage Paragraph From Library


Landingpage-ListOfLinks
	[Tags]
	Given User Creates New ListOfLinks Paragraphs To Library
	When User Starts Creating LandingPage With Add From Library Content For ListOfLinks -Paragraph
	Then Page Should Have Filled ListOfLinks Paragraph From Library
	
Landingpage-Unit Search
	[Tags]
	Given User Creates New Unitsearch Paragraphs To Library
	When User Starts Creating LandingPage With Add From Library Content For Unitsearch -Paragraph
	Then Page Should Have Filled Unitsearch Paragraph From Library
		
*** Keywords ***
User Creates New Columns Paragraphs To Library	
	Create New Columns Paragraph To Library

User Creates New Banner Paragraphs To Library	
	Create New Banner Paragraph To Library

User Creates New ContentCards Paragraphs To Library	
	Create New ContentCards Paragraph To Library

User Creates New LiftupWithImage Paragraphs To Library	
	Create New LiftupWithImage Paragraph To Library

User Creates New ListOfLinks Paragraphs To Library	
	Create New ListOfLinks Paragraph To Library

User Creates New Unitsearch Paragraphs To Library	
	Create New Unit Search Paragraph To Library

User Starts Creating ${pagetype} With Add From Library Content For ${paragraph} -Paragraph
	Go To  ${URL_content_page}
	Go To New LandingPage Site
	Create ${paragraph} -Paragraph ${pagetype} Content
