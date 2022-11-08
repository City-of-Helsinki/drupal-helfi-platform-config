*** Settings ***
Resource        ../Contenthandler.robot
Resource        ../Commonkeywords.robot

*** Variables ***
@{contact1phonenumbers}   9876543210   0123456789
@{contact2phonenumbers}   +358110299000   8818129814 

*** Keywords ***

Create ContactCard Paragraph
	Run Keyword If  '${language}'=='fi'  Open Paragraph For Edit   ${Opt_ContactCardListing}
	Wait Until Keyword Succeeds  5x  100ms  Input Title To Paragraph   ${Inp_Contact_Card_Listing_Title}
	Input Description To Paragraph   ${Inp_Contact_Card_Listing_Description}


Create ContactCard${number}
	IF   ${number}==1
		Fill Contact Data Fields   contactcard_picture_1   image not visible  Photographer 1  Leo  feline  lion66366@testmail.com  ${contact1phonenumbers}  Contactcard1 description field text here
		Fill Social Media Data Fields   home   https://www.helsinki.fi
	ELSE
		Fill Contact Data Fields   contactcard_picture_2   image not visible  Photographer 2  Aves  birdie  deserteagle111@testmail.com  ${contact2phonenumbers}  Contactcard2 description field text here also for second contact card with longer text right here
		Fill Social Media Data Fields   home   https://www.hel.fi
	END 
	
	
	   
Fill Contact Data Fields
	[Arguments]  ${picturefilename}  ${altpictext}   ${photographername}  ${name}  ${title}  ${email}  ${phonenumbers}  ${description}
	Wait Until Keyword Succeeds  6x  300ms  Choose File   ${Btn_Add_ContactCard_File}   ${IMAGES_PATH}/${picturefilename}.jpg
	Wait Until Keyword Succeeds  6x  300ms  Input Text  ${Inp_ContactCard_Photographer}   ${photographername}
	Input Text  ${Inp_ContactCard_Name}   ${name}
	Input Text  ${Inp_ContactCard_Title}  ${title}
	Input Text  ${Inp_ContactCard_Email}   ${email}
	${phone1}=  Get From List   ${phonenumbers}   0
	${phone2}=  Get From List   ${phonenumbers}   1
	Input Text  ${Inp_ContactCard_PhoneNumber_1}   ${phone1}
	Input Text  ${Inp_ContactCard_PhoneNumber_2}   ${phone2}
	Input Text  ${Tar_ContactCard_Description}   ${description}
	Wait Until Keyword Succeeds  5x  300ms  Input Text  ${Inp_ContactCard_Picture_AlternateText}  ${altpictext}
	
Fill Social Media Data Fields
	[Arguments]   ${iconname}   ${url}
	Select From List By Label   ${Sel_ContactCard_SocialMedia_Icon}   ${iconname}
	Input Text  ${Inp_ContactCard_SocialMedia_Url}   ${url}
	
Take Screenshot Of Content
	Maximize Browser Window
	Capture Screenshot For Picture Comparison   css=main.layout-main-wrapper

