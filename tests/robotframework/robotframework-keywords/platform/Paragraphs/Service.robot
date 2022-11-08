*** Settings ***

Resource        ../Contenthandler.robot
Resource        ../Commonkeywords.robot

*** Keywords ***

Publish The ${nth} Service In The Service List
	Goto  https://helfi.docker.so/fi/admin/content/integrations/tpr-service/${nth}/edit
	${ispublished}=   Run Keyword And Return Status   Checkbox Should Be Selected  id:edit-status
	Run Keyword If   not(${ispublished})   Set Service As Published
	Submit New Content


Open Service With Name
	[Arguments]	   ${name}
	Goto  ${PROTOCOL}://${BASE_URL}/fi/admin/content/integrations/tpr-service
	Click Link   ${name}
	
Get Service Title
	${title}=   Get Text   css:.page-title > h1
	[Return]   ${title}
	
Get Service Short Description
	${shortdesc}=   Get Text   css:.lead-in > div > div > div
	[Return]   ${shortdesc}
	
Get Service Long Description
	${longdesc}=   Get Text   css:.long-desc > p
	[Return]   ${longdesc}	