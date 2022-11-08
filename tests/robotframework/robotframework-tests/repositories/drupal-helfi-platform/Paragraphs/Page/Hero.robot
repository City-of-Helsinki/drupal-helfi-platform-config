*** Settings ***
Documentation   Testing Hero Block Settings in Platform. Tests are created with different text alignatiotions like
...				Left, Center. For pictures there are more alignation options. Also differentlink icon styles are 
...				tested with default pictureless layout. Also Background color options are tested in several testcases
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/Hero.robot
Test Setup      Login And Go To Content Page
Test Teardown   Cleanup and Close Browser	
Force Tags		PAGE   HERO
*** Variables ***
${color}	 		 ${EMPTY}

*** Test Cases ***

Left Aligned
	[Documentation]   Left Aligned Hero Block with Short version of text files in Finnish. 'Vaakuna' style.
	[Tags]   CRITICAL
	Given User Goes To New Page -Site
	And User Starts Creating a Left Aligned Page With Hero Block
	When User Submits The New Page
	Then Layout Should Not Have Changed
	And Hero Component Is Present	

Left Aligned Picture
	[Documentation]   Left Aligned Hero Block with Picture
	[Tags]  CRITICAL
	Given User Goes To New Page -Site
	And User Starts Creating Hero Block Page with Left Picture
	When User Submits The New Page
	Then Layout Should Not Have Changed
	And Hero Component Is Present	

Right Aligned Picture
	[Tags]
	Given User Goes To New Page -Site
	And User Starts Creating Hero Block Page with Right Picture
	When User Submits The New Page
	Then Layout Should Not Have Changed
	And Hero Component Is Present
	
Bottom Aligned Picture
	[Tags]  
	Given User Goes To New Page -Site
	And User Starts Creating Hero Block Page with Bottom Picture
	When User Submits The New Page
	Then Layout Should Not Have Changed
	And Hero Component Is Present

Diagonal Picture
	[Tags]
	Given User Goes To New Page -Site
	And User Starts Creating Hero Block Page with Diagonal Picture
	When User Submits The New Page
	Then Layout Should Not Have Changed
	And Hero Component Is Present	

Fullcolor Link
	[Documentation]   Adds Left aligned page and a link with Fullcolor styling option selected
	[Tags]   CRITICAL
	Given User Goes To New Page -Site
	And User Starts Creating a Left Aligned Page With Hero Block
	And User Adds Hero Link Button With Fullcolor Style
	When User Submits The New Page
	Then Layout Should Not Have Changed
	And Hero Component Is Present

Framed Link
	[Tags]
	Given User Goes To New Page -Site
	And User Starts Creating a Left Aligned Page With Hero Block
	And User Adds Hero Link Button With Framed Style
	When User Submits The New Page
	Then Layout Should Not Have Changed
	And Hero Component Is Present

Transparent Link
	[Tags]   
	Given User Goes To New Page -Site
	And User Starts Creating a Left Aligned Page With Hero Block
	And User Adds Hero Link Button With Transparent Style
	When User Submits The New Page
	Then Layout Should Not Have Changed
	And Hero Component Is Present

Gold Background Color
	[Documentation]   Left Aligned Hero Block with Background Color selection 'Gold' 
	[Tags]   CRITICAL
	Given User Goes To New Page -Site
	And User Starts Creating a Left Aligned Page With Hero Block
	And User Adds Gold As Background Color
	When User Submits The New Page
	Then Layout Should Not Have Changed
	And Hero Component Is Present

Silver Background Color
	[Tags]   
	Given User Goes To New Page -Site
	And User Starts Creating a Left Aligned Page With Hero Block
	And User Adds Silver As Background Color
	When User Submits The New Page
	Then Layout Should Not Have Changed
	
Brick Background Color
	[Tags]   
	Given User Goes To New Page -Site
	And User Starts Creating a Left Aligned Page With Hero Block
	And User Adds Brick As Background Color
	When User Submits The New Page
	Then Layout Should Not Have Changed
	
Bus Background Color
	[Tags]   
	Given User Goes To New Page -Site
	And User Starts Creating a Left Aligned Page With Hero Block
	And User Adds Bus As Background Color
	When User Submits The New Page
	Then Layout Should Not Have Changed
	
Copper Background Color
	[Tags]   
	Given User Goes To New Page -Site
	And User Starts Creating a Left Aligned Page With Hero Block
	And User Adds Copper As Background Color
	When User Submits The New Page
	Then Layout Should Not Have Changed
	
Engel Background Color
	[Tags]   
	Given User Goes To New Page -Site
	And User Starts Creating a Left Aligned Page With Hero Block
	And User Adds Engel As Background Color
	When User Submits The New Page
	Then Layout Should Not Have Changed
	
Fog Background Color
	[Tags]   
	Given User Goes To New Page -Site
	And User Starts Creating a Left Aligned Page With Hero Block
	And User Adds Fog As Background Color
	When User Submits The New Page
	Then Layout Should Not Have Changed
	
Metro Background Color
	[Tags]   
	Given User Goes To New Page -Site
	And User Starts Creating a Left Aligned Page With Hero Block
	And User Adds Metro As Background Color
	When User Submits The New Page
	Then Layout Should Not Have Changed
	
Summer Background Color
	[Tags]   
	Given User Goes To New Page -Site
	And User Starts Creating a Left Aligned Page With Hero Block
	And User Adds Summer As Background Color
	When User Submits The New Page
	Then Layout Should Not Have Changed
	
Suomenlinna Background Color
	[Tags]   
	Given User Goes To New Page -Site
	And User Starts Creating a Left Aligned Page With Hero Block
	And User Adds Suomenlinna As Background Color
	When User Submits The New Page
	Then Layout Should Not Have Changed
	
Tram Background Color
	[Tags]   
	Given User Goes To New Page -Site
	And User Starts Creating a Left Aligned Page With Hero Block
	And User Adds Tram As Background Color
	When User Submits The New Page
	Then Layout Should Not Have Changed

Finnish English Swedish Translations
	[Tags]   CRITICAL
	Given User Creates a Left Aligned Page With Hero Block In Finnish Language
	And User Creates a Left Aligned Page With Hero Block In English Language
	And User Creates a Left Aligned Page With Hero Block In Swedish Language
	Then Page Should Have Finnish Translation
	And Page Should Have English Translation
	And Page Should Have Swedish Translation
	
	
	
   
*** Keywords ***
User Adds ${color} As Background Color
	Add ${color} As Background Color

User Submits The New Page
	Submit The New Page

User Adds Hero Link Button With ${style} Style
	Add Hero Link Button With ${style} Style

User Starts Creating Hero Block Page with ${picalign} Picture
	Start Creating Hero Block Page with ${picalign} Picture
	
User Starts Creating a ${value} Aligned Page With Hero Block
	Start Creating a ${value} Aligned Page With Hero Block

User Creates a ${value} Aligned Page With Hero Block In ${lang_selection} Language
	Create a ${value} Aligned Page With Hero Block In ${lang_selection} Language

User Goes To New Page -Site
	Go To New Page Site

Page Should Have ${lang_input} Translation
	Set Language Pointer   ${lang_input}
	Select Language   ${lang_input}
	Page Content Matches Language

Page Content Matches Language
	${Title}=  Return Hero Title From Page
	${Description}=  Return Hero Description From Page
	${Content}=  Return Lead-in From Page
	Title Should Match Current Language Selection   ${Title}
	Description Should Match Current Language Selection   ${Description}	
	Content Should Match Current Language Selection   ${Content}

Hero Component Is Present
	Element Should Be Visible   css:.hero-wrapper 
	