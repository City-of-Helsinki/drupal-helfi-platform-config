*** Settings ***
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/Phasing.robot
Test Setup      Login And Go To Content Page
Test Teardown   Cleanup and Close Browser	
Force Tags		PAGE   PHASING

*** Test Cases ***

Two Phases No Numbering
	[Tags]
	When User Goes To New Page -Site
	And User Creates a New Phasing Paragraph Without Numbering
	When User Submits The New Page
	Then Page Has Phasing Paragraph

Two Phases With Numbering
	[Tags]
	When User Goes To New Page -Site
	And User Creates a New Phasing Paragraph With Numbering
	When User Submits The New Page
	Then Page Has Phasing Paragraph

Two Phases With h3 Title Level
	[Tags]
	When User Goes To New Page -Site
	And User Creates a New Phasing With h3 Title Leveling
	When User Submits The New Page
	Then Page Has Phasing Paragraph

Two Phases With h4 Title Level
	[Tags]
	When User Goes To New Page -Site
	And User Creates a New Phasing With h4 Title Leveling
	When User Submits The New Page
	Then Page Has Phasing Paragraph
	
Two Phases With h5 Title Level
	[Tags]
	When User Goes To New Page -Site
	And User Creates a New Phasing With h5 Title Leveling
	When User Submits The New Page
	Then Page Has Phasing Paragraph

Two Phases With h6 Title Level
	[Tags]
	When User Goes To New Page -Site
	And User Creates a New Phasing With h6 Title Leveling
	When User Submits The New Page
	Then Page Has Phasing Paragraph

*** Keywords ***
User Goes To New Page -Site		Go To New Page Site
User Submits The New Page
	Submit The New Page
	
User Creates a New Phasing Paragraph ${state} Numbering
	Run Keyword If  '${state}'=='Without'  Create New Phasing
	Run Keyword If  '${state}'=='With'  Create New Phasing   numbering=True

User Creates a New Phasing With ${level} Title Leveling
	Create New Phasing  titlelevel=${level}  
	
Page Has Phasing Paragraph
	Phasing Is Found On Page