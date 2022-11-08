*** Settings ***
Resource        ../../../../../robotframework-keywords/platform/Paragraphs/Hero.robot
Test Setup      Login And Go To Content Page
Test Teardown   Cleanup and Close Browser	
Force Tags		LANDINGPAGE   HERO

*** Test Cases ***

Landingpage-Left Aligned
	[Documentation]   Left Aligned Hero Block with Short version of text files in Finnish. 'Vaakuna' style.
	[Tags]  CRITICAL
	Given User Goes To New LandingPage Site
	And User Starts Creating a Left Aligned Page With Hero Block
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed
	And Hero Component Is Present	

Landingpage-Left Aligned Picture
	[Documentation]   Left Aligned Hero Block with Picture
	[Tags]  CRITICAL
	Given User Goes To New LandingPage Site
	And User Starts Creating Hero Block Page with Left Picture
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed
	And Hero Component Is Present	

Landingpage-Right Aligned Picture
	[Tags]
	Given User Goes To New LandingPage Site
	And User Starts Creating Hero Block Page with Right Picture
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed
	And Hero Component Is Present	
	
Landingpage-Bottom Aligned Picture
	[Tags]    
	Given User Goes To New LandingPage Site
	And User Starts Creating Hero Block Page with Bottom Picture
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed
	And Hero Component Is Present	

Landingpage-Diagonal Picture
	[Tags]
	Given User Goes To New LandingPage Site
	And User Starts Creating Hero Block Page with Diagonal Picture
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed
	And Hero Component Is Present		

Landingpage-Fullcolor Link
	[Documentation]   Adds Left aligned page and a link with Fullcolor styling option selected
	[Tags]  CRITICAL
	Given User Goes To New LandingPage Site
	And User Starts Creating a Left Aligned Page With Hero Block
	And User Adds Hero Link Button With Fullcolor Style
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed
	And Hero Component Is Present

Landingpage-Framed Link
	[Tags]  
	Given User Goes To New LandingPage Site
	And User Starts Creating a Left Aligned Page With Hero Block
	And User Adds Hero Link Button With Framed Style
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed

Landingpage-Transparent Link
	[Tags]  
	Given User Goes To New LandingPage Site
	And User Starts Creating a Left Aligned Page With Hero Block
	And User Adds Hero Link Button With Transparent Style
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed

Landingpage-Gold Background Color
	[Documentation]   Left Aligned Hero Block with Background Color selection 'Gold' 
	[Tags]  CRITICAL
	Given User Goes To New LandingPage Site
	And User Starts Creating a Left Aligned Page With Hero Block
	And User Adds Gold As Background Color
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed
	And Hero Component Is Present	

Landingpage-Silver Background Color
	[Tags]  
	Given User Goes To New LandingPage Site
	And User Starts Creating a Left Aligned Page With Hero Block
	And User Adds Silver As Background Color
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed
	
Landingpage-Brick Background Color
	[Tags]  
	Given User Goes To New LandingPage Site
	And User Starts Creating a Left Aligned Page With Hero Block
	And User Adds Brick As Background Color
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed
	
Landingpage-Bus Background Color
	[Tags]  
	Given User Goes To New LandingPage Site
	And User Starts Creating a Left Aligned Page With Hero Block
	And User Adds Bus As Background Color
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed
	
Landingpage-Copper Background Color
	[Tags]  
	Given User Goes To New LandingPage Site
	And User Starts Creating a Left Aligned Page With Hero Block
	And User Adds Copper As Background Color
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed
	
Landingpage-Engel Background Color
	[Tags]  
	Given User Goes To New LandingPage Site
	And User Starts Creating a Left Aligned Page With Hero Block
	And User Adds Engel As Background Color
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed
	
Landingpage-Fog Background Color
	[Tags]  
	Given User Goes To New LandingPage Site
	And User Starts Creating a Left Aligned Page With Hero Block
	And User Adds Fog As Background Color
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed
	
Landingpage-Metro Background Color
	[Tags]  
	Given User Goes To New LandingPage Site
	And User Starts Creating a Left Aligned Page With Hero Block
	And User Adds Metro As Background Color
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed
	
Landingpage-Summer Background Color
	[Tags]  
	Given User Goes To New LandingPage Site
	And User Starts Creating a Left Aligned Page With Hero Block
	And User Adds Summer As Background Color
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed
	
Landingpage-Suomenlinna Background Color
	[Tags]  
	Given User Goes To New LandingPage Site
	And User Starts Creating a Left Aligned Page With Hero Block
	And User Adds Suomenlinna As Background Color
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed
	
Landingpage-Tram Background Color
	[Tags]  
	Given User Goes To New LandingPage Site
	And User Starts Creating a Left Aligned Page With Hero Block
	And User Adds Tram As Background Color
	When New Landingpage is Submitted
	Then Layout Should Not Have Changed

Finnish English Swedish Translations
	[Tags]  CRITICAL
	Given User Creates a Left Aligned Page With Hero Block In Finnish Language
	And User Creates a Left Aligned Page With Hero Block In English Language
	And User Creates a Left Aligned Page With Hero Block In Swedish Language
	Then Page Should Have Finnish Translation
	And Page Should Have English Translation
	And Page Should Have Swedish Translation
	
	
	
   
*** Keywords ***
User Adds ${color} As Background Color
	Add ${color} As Background Color

User Goes To New LandingPage Site   Go To New LandingPage Site
New Landingpage is Submitted	Submit The New Landingpage

User Adds Hero Link Button With ${style} Style
	Add Hero Link Button With ${style} Style

User Starts Creating Hero Block Page with ${picalign} Picture
	Start Creating Hero Block Page with ${picalign} Picture
	
User Starts Creating a ${value} Aligned Page With Hero Block
	Start Creating a ${value} Aligned Page With Hero Block

User Creates a ${value} Aligned Page With Hero Block In ${lang_selection} Language
	Create a ${value} Aligned Page With Hero Block In ${lang_selection} Language

Page Should Have ${lang_input} Translation
	Set Language Pointer   ${lang_input}
	Select Language   ${lang_input}
	Page Content Matches Language

Page Content Matches Language
	${Title}=  Return Hero Title From Page
	${Description}=  Return Hero Description From Page
	${Content}=   Return Lead-in From Page
	Title Should Match Current Language Selection   ${Title}
	Description Should Match Current Language Selection   ${Description}
	Content Should Match Current Language Selection   ${Content}

Hero Component Is Present
	Element Should Be Visible   css:.hero-wrapper 