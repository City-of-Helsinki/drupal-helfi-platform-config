#############################     Naming Conventions    #######################
#
#				 Dropdown   = Ddn
#				 Option		= Opt
#				 Text		= Txt
#				 Switch     = Swh
#				 Frame      = Frm
#				 Input      = Inp
#				 Button     = Btn
#				 Menu-item  = Mtm
#				 Item		= Itm    (For example div elements or page view components)
#				 TextArea	= Tar
#				 Select     = Sel

*** Variables ***
#LISTS
@{pic_1_texts_fi}  							Juna sillalla   Vanha juna kuljettaa matkustajia   Testi Valokuvaaja
@{pic_1_texts_en}  							Train on The Bridge   Old Train Carries Passengers   Test Photographer
@{pic_1_texts_sv}  							Träna på bron   Gammalt tåg bär passagerare   Testfotograf
@{pic_1_texts_ru}  							Поезд на мосту  Старый поезд везет пассажиров   Тестовый фотограф
@{pic_2_texts_fi}							Temppeli koreassa   Buddhalaistemppeli talvella Aasiassa   Testi Valokuvaaja2
@{pic_2_texts_en}							Temple in Korea   Buddhist temple in winter in Asia   Test Photographer2
@{pic_2_texts_sv}							Templet i Korea   Buddistisk tempel i vinter i Asien   Testfotograf2
@{pic_2_texts_ru}							Храм в Корее   Буддийский храм зимой в Азии   Тестовый фотограф2		
${pic_1_caption_fi}							Juna puksuttaa kohti uutta pysäkkiä
${pic_1_caption_en}							The train pans towards a new stop
${pic_1_caption_sv}							Tåget går mot ett nytt stopp
${pic_1_caption_ru}							Кастрюли поезда к новой остановке
${pic_2_caption_fi}							Buddhalaisessa temppelissä suoritetaan hartausharjoituksia
${pic_2_caption_en}							In the Buddhist temple devotional exercises are performed
${pic_2_caption_sv}							I de buddhistiska templet utförs devotionsövningarna
${pic_2_caption_ru}							В буддийских храмах преданные упражнения выполняются
${link_title_fi}							Tietoa teoksesta
${link_title_en}							About a book
${link_title_sv}							Om en bok
${link_title_ru}							О книге

#SHARED
${Btn_Save}									//button[contains(text(),'Tallenna')]
${Btn_Save_En}								//button[contains(text(),'Save')]
${Btn_Save_Sv}								//button[contains(text(),'Spara')]
${Ddn_AddContent}							//ul[@data-drupal-selector='edit-field-content-add-more-operations']//li[2]/button
${Ddn_AddContent_Sidebar}					css:#edit-field-sidebar-content-add-more > div > div > ul > li.dropbutton-toggle > button
${Ddn_AddContent_Lower}						css:#edit-field-lower-content-add-more > div > div > ul > li.dropbutton-toggle > button
${Opt_AddColumns}						    name:field_content_columns_add_more
${Opt_AddPicture}						    name:field_content_image_add_more
${Opt_AddText}								name:field_content_text_add_more
${Opt_AddLink}								name:field-content-link-add-more
${Opt_AddChart}								name:field_content_chart_add_more
${Opt_AddEvent}								name:field_content_event_list_add_more
${Opt_AddBanner}   							name:field_content_banner_add_more
${Opt_AddAccordion}   						name:field_content_accordion_add_more
${Opt_AddContentCards} 						name:field_content_content_cards_add_more
${Opt_AddContentCards_Lower}				name:field_lower_content_content_cards_add_more
${Opt_AddLiftupWithImage}					name:field_content_liftup_with_image_add_more
${Opt_AddRemotevideo}						name:field_content_remote_video_add_more
${Opt_AddFromLibrary}						name:field_content_from_library_add_more
${Opt_AddFromLibrary_Lower}					name:field_lower_content_from_library_add_more
${Opt_ServiceList}							name:field_content_service_list_add_more
${Opt_SideBarText}							name:field_sidebar_content_sidebar_text_add_more
${Opt_SideBarContentFromLibrary}			name:field_sidebar_content_from_library_add_more
${Opt_ContentLiftup}						name:field_content_content_liftup_add_more
${Opt_TargetGroupLinks}						name:field_content_target_group_links_add_more
${Opt_Current}								name:field_content_current_add_more
${Opt_TopNews}								name:field_content_front_page_top_news_add_more
${Opt_LatestNews}							name:field_content_front_page_latest_news_add_more
${Opt_PopularServices}						name:field_content_popular_services_add_more
${Opt_AddMap}								name:field_content_map_add_more
${Opt_UnitSearch}							name:field_content_unit_search_add_more
${Opt_ContactCardListing}					name:field_content_contact_card_listing_add_more
${Opt_Unit_Accessibility_Information}		name:field_content_unit_accessibility_information_add_more
${Inp_FrontPage_Links_Url}					(//input[contains(@name, 'field_news_item_links_link')][contains(@name, 'uri')])[last()]
${Inp_FrontPage_Links_Title}				(//input[contains(@name, 'field_news_item_links_link')][contains(@name, 'title')])[last()]
${Inp_FrontPage_Links_Addmore}				name:field_news_item_links_link_add_more
${Opt_Phasing}								name:field_content_phasing_add_more
${Btn_File_Upload}					    	name:files[upload]
${Inp_Pic_Name}								css:[data-drupal-selector=edit-media-0-fields-name-0-value]
${Inp_Pic_AltText}							css:[data-drupal-selector=edit-media-0-fields-field-media-image-0-alt]
${Inp_Pic_Photographer}						css:[data-drupal-selector=edit-media-0-fields-field-photographer-0-value]
${Btn_Insert_Pic}							css:div.ui-dialog-buttonset.form-actions > button
${Ddn_SelectLanguage}						//select[@id='edit-langcode-0-value']
${Inp_Title}								//input[@id='edit-title-0-value']
${Tar_Page_LeadIn}							name:field_lead_in[0][value]
${Frm_Content}							    css:#cke_1_contents > iframe
${Frm_Content2}								css:#cke_2_contents > iframe
${Frm_Content_Description}				    css:#cke_58_contents > iframe
${Btn_Submit}							    //input[@id='edit-submit--2--gin-edit-form']		
${Mtm_Content}								//li[contains(@class, 'menu-item menu-item__system-admin_content')]
${Btn_Actions_Dropbutton}					//button[@class='dropbutton__toggle']
${Btn_Actions_ContentMenu_Deletebutton}		//li[contains(@class, 'delete dropbutton')]/child::a
${Btn_Actions_ContentMenu_Translatebutton}	//li[contains(@class, 'translate dropbutton')]/child::a
${Btn_Actions_SelectedItem_Deletebutton}	//input[@id='edit-submit']
${Opt_Link_Fullcolor}			css:[value=primary]
${Opt_Link_Framed}				css:[value=secondary]
${Opt_Link_Transparent}			css:[value=supplementary]
${Ddn_Icon} 							//select[contains(@data-drupal-selector, 'subform-field-icon')]
${Txt_Title}								css:.component__title
${Txt_Content}								css:div[class$="__content"] > p
${Txt_Description}							css:div[class$="__desc"] > p
${Txt_Component_Description}				css:div[class$="__description"] > p
${Swh_TOC}									id:edit-toc-enabled-value
${Assistive_Technology_Title}				(//input[contains(@name, 'field_iframe_title')])[last()]

#TAGS
${Inp_Frontpage_TagsGeneral}  				css:.select2-search__field

#HERO
${Inp_Hero_Title}							//input[contains(@id, 'edit-field-hero-0-subform')]
${Swh_HeroOnOff}						    //input[@id='edit-field-has-hero-value']
${Ddn_Hero_Alignment}						css:.select2-selection__arrow
${Opt_Hero_Alignment_Center}				css:[value=without-image-center]
${Opt_Hero_Picture_On_Right}				css:[value=with-image-right]
${Opt_Hero_Picture_On_Left}					css:[value=with-image-left]
${Opt_Hero_Picture_On_Bottom}				css:[value=with-image-bottom]
${Opt_Hero_Picture_On_Background}			css:[value=background-image]
${Opt_Hero_Diagonal}						css:[value=diagonal]
${Btn_Hero_Picture}							name:field_hero_image-media-library-open-button-field_hero-0-subform
${Btn_Hero_AddLink}						    name:field_hero_0_subform_field_hero_cta_link_add_more
${Inp_Hero_Link_URL}						css:[data-drupal-selector=edit-field-hero-0-subform-field-hero-cta-0-subform-field-link-link-0-uri]
${Inp_Hero_Link_Texteditor_URL}				css:[data-drupal-selector=edit-attributes-href]
${Inp_Hero_Link_Title}						css:[data-drupal-selector=edit-field-hero-0-subform-field-hero-cta-0-subform-field-link-link-0-title]
${Inp_Hero_Link_Texteditor_Title}			css:[data-drupal-selector=edit-attributes-data-link-text]
${Ddn_Hero_Link_Design}						css:[data-drupal-selector=edit-field-hero-0-subform-field-hero-cta-0-subform-field-link-design]
${Ddn_Hero_Link_Texteditor_Design}			css:[data-drupal-selector=edit-attributes-data-design]
${Opt_Hero_Link_Texteditor_ButtonFullcolor}		//option[@value='hds-button hds-button--primary']
${Opt_Hero_Link_Texteditor_ButtonFramed}		//option[@value='hds-button hds-button--secondary']
${Opt_Hero_Link_Texteditor_ButtonTransparent}	//option[@value='hds-button hds-button--supplementary']
${Ddn_Hero_Color}							//select[@data-drupal-selector='edit-field-hero-0-subform-field-hero-bg-color']



#COLUMNS
${Inp_Column_Title}	  						//input[contains(@data-drupal-selector, 'subform-field-columns-title-0-value')]
${Ddn_Column_Left_AddContent}               //ul[contains(@data-drupal-selector, 'subform-field-columns-left-column-add-more-operations')]//button
${Ddn_Column_Right_AddContent}				//ul[contains(@data-drupal-selector, 'subform-field-columns-right-column-add-more-operations')]//button
${Opt_Column_Left_AddContent_Image}			//ul[contains(@data-drupal-selector, 'subform-field-columns-left-column-add-more-operations')]//input[contains(@name,'subform_field_columns_left_column_image_add_more')]
${Opt_Column_Left_AddContent_Text}			//ul[contains(@data-drupal-selector, 'subform-field-columns-left-column-add-more-operations')]//input[contains(@name,'subform_field_columns_left_column_text_add_more')]
${Opt_Column_Left_AddContent_ListOfLinks}	//ul[@data-drupal-selector='edit-field-content-1-subform-field-columns-left-column-add-more-operations']//input[@name='field_content_1_subform_field_columns_left_column_list_of_links_add_more']
${Opt_Column_Left_AddContent_Link}			//ul[contains(@data-drupal-selector, 'subform-field-columns-left-column-add-more-operations')]//input[contains(@name,'subform_field_columns_left_column_link_add_more')]
${Opt_Column_Right_AddContent_Link}			//ul[contains(@data-drupal-selector, 'subform-field-columns-right-column-add-more-operations')]//input[contains(@name,'subform_field_columns_right_column_link_add_more')]
${Opt_Column_Right_AddContent_Image}		//ul[contains(@data-drupal-selector, 'subform-field-columns-right-column-add-more-operations')]//input[contains(@name,'subform_field_columns_right_column_image_add_more')]
${Opt_Column_Right_AddContent_Text}			//ul[contains(@data-drupal-selector, 'subform-field-columns-right-column-add-more-operations')]//input[contains(@name,'subform_field_columns_right_column_text_add_more')]
${Btn_Column_Left_AddPicture}				name:field_image-media-library-open-button-field_content-1-subform-field_columns_left_column-0-subform
${Btn_Column_Right_AddPicture}				name:field_image-media-library-open-button-field_content-1-subform-field_columns_right_column-0-subform
${Frm_Column_Left_Text}						//div[contains(@id,'cke_edit-field-content')][contains(@id,'left')]//iframe
${Frm_Column_Right_Text}					//div[contains(@id,'cke_edit-field-content')][contains(@id,'right')]//iframe
${Btn_Column_Left_Picture}					//input[contains(@data-drupal-selector, 'subform-field-image-open-button')][contains(@data-drupal-selector, 'left')]
${Btn_Column_Right_Picture}					//input[contains(@data-drupal-selector, 'subform-field-image-open-button')][contains(@data-drupal-selector, 'right')]
${Btn_Column_Left_Edit}						//input[contains(@name, 'subform_field_columns_left_column')][contains(@name, 'edit')]
${Btn_Column_Right_Edit}					//input[contains(@name, 'subform_field_columns_right_column')][contains(@name, 'edit')]
${Inp_Column_Left_Picture_Caption}			//textarea[contains(@data-drupal-selector, 'subform-field-image-caption')][contains(@data-drupal-selector, 'left')]
${Inp_Column_Right_Picture_Caption}			//textarea[contains(@data-drupal-selector, 'subform-field-image-caption')][contains(@data-drupal-selector, 'right')]
${Swh_Column_Left_Picture_Orig_Aspect_Ratio}   //input[contains(@data-drupal-selector, 'field-original-aspect-ratio-value')][contains(@data-drupal-selector, 'left')]
${Swh_Column_Right_Picture_Orig_Aspect_Ratio}   //input[contains(@data-drupal-selector, 'field-original-aspect-ratio-value')][contains(@data-drupal-selector, 'right')]
${Inp_Column_Left_Link_Title}				css:[data-drupal-selector=edit-field-content-1-subform-field-columns-left-column-0-subform-field-link-link-0-title]
${Inp_Column_Right_Link_Title}				css:[data-drupal-selector=edit-field-content-1-subform-field-columns-right-column-0-subform-field-link-link-0-title]
${Inp_Column_Left_Link_URL}					css:[data-drupal-selector=edit-field-content-1-subform-field-columns-left-column-0-subform-field-link-link-0-uri]
${Inp_Column_Right_Link_URL}				css:[data-drupal-selector=edit-field-content-1-subform-field-columns-right-column-0-subform-field-link-link-0-uri]	
${Ddn_Column_Left_Link_Design}				css:[data-drupal-selector=edit-field-content-1-subform-field-columns-left-column-0-subform-field-link-design]
${Ddn_Column_Right_Link_Design}				css:[data-drupal-selector=edit-field-content-1-subform-field-columns-right-column-0-subform-field-link-design]	
${Opt_Column_Left_Link_ButtonFullcolor}		//select[@data-drupal-selector='edit-field-content-1-subform-field-columns-left-column-0-subform-field-link-design']//option[@value='primary']
${Opt_Column_Right_Link_ButtonFullcolor}	//select[@data-drupal-selector='edit-field-content-1-subform-field-columns-right-column-0-subform-field-link-design']//option[@value='primary']
${Opt_Column_Left_Link_ButtonFramed}		//select[@data-drupal-selector='edit-field-content-1-subform-field-columns-left-column-0-subform-field-link-design']//option[@value='secondary']
${Opt_Column_Right_Link_ButtonFramed}		//select[@data-drupal-selector='edit-field-content-1-subform-field-columns-right-column-0-subform-field-link-design']//option[@value='secondary']
${Opt_Column_Left_Link_ButtonTransparent}	//select[@data-drupal-selector='edit-field-content-1-subform-field-columns-left-column-0-subform-field-link-design']//option[@value='supplementary']
${Opt_Column_Right_Link_ButtonTransparent}	//select[@data-drupal-selector='edit-field-content-1-subform-field-columns-right-column-0-subform-field-link-design']//option[@value='supplementary']

#GALLERY
${Opt_AddGallery}						    name:field_content_gallery_add_more
${Btn_Gallery_Picture}						(//input[contains(@name, 'field_gallery_slide_media-media-library-open-button-field_content')][contains(@name, 'subform-field_gallery_slides')])[last()]
${Btn_Gallery_Picture_Caption}				//textarea[contains(@name, '[subform][field_gallery_slides]')][contains(@name, '[subform][field_gallery_slide_caption]')]
${Btn_Gallery_Picture_Addmore}				//input[contains(@name, 'subform_field_gallery_slides_gallery_slide_add_more')]
${Inp_Gallery_Edit}						    //input[contains(@value, 'Edit')][contains(@id, 'field-content')]
# PAGE VIEW
${Txt_Hero_Title}								css:.hero__title
${Txt_Hero_Description}							css:.hero__description
${Txt_Leadin_Content}							css:section[class$="lead-in"]
${Txt_Column_Description}						xpath://p[1]
${Txt_Column_Content}							css:p
${Itm_Gallery_Slidetrack}						id:splide01-track  

# BANNER
${Frm_Banner_Description}					css:textarea[name*=field_banner_desc] + div > div > div > iframe
${Opt_Banner_Left}							css:[value=align-left]
${Opt_Banner_Left_Secondary}				css:[value=align-left-secondary]
${Opt_Banner_Center_Secondary}				css:[value=align-center-secondary]
${Inp_Banner_Title}							(//input[contains(@name, '[field_banner_title]')])[last()]
${Inp_Banner_Link_Uri}						css:input[name*=field_banner_link][name*=uri]
${Inp_Banner_Link_Title}					css:input[name*=field_banner_link][name*=title]
${Swh_Banner_Link_OpenInNewWindow}   		css:input[name*=field_banner_link][name*=target_new]
${Swh_Banner_Link_LinkIsAccessable}    		css:input[name*=field_banner_link][name*=target_check]

#UNCATEGORIZED
${Btn_Picture}								(//input[contains(@name, 'field_image-media-library-open-button-field_content')])[last()]

${Btn_MainImage}							name:field_main_image-media-library-open-button
${Inp_MainImage_Caption}					css:textarea[name*=field_main_image_caption]
${Btn_Picture_Remove}						//input[contains(@name, 'media-library-remove-button-field_content')]
${Frm_Text_Content}							//div[contains(@id,'subform-field-text')][contains(@id,'value')] >> css:div > div > iframe

#ACCORDION
${Inp_Accordion_Title}	  					(//input[contains(@name, '[field_accordion_item_heading]')][contains(@name, '[value]')])[last()]
${Ddn_Accordion_AddContent}					(//ul[contains(@data-drupal-selector, 'subform-field-accordion-item-content-add-more-operations')])[last()]//li[2]/button
${Ddn_Accordion2_Icon}						(//select[contains(@name, '[subform][field_accordion_items]')][contains(@name,'[subform][field_icon]')][contains(@name,'[icon]')])[last()]
${Opt_Accordion_Content_Text}				(//input[contains(@name, 'subform_field_accordion_item_content_text_add_more')])[last()]
${Opt_Accordion_Content_Columns}			//input[contains(@name, 'subform_field_accordion_item_content_columns_add_more')]
${Opt_Accordion_Content_Picture}			//input[contains(@name, 'subform_field_accordion_item_content_image_add_more')]
${Opt_Accordion_Content_Phasing}			//input[contains(@name, 'subform_field_accordion_item_content_phasing_add_more')]
${Frm_Accordion_Content}					//div[contains(@id,'subform-field-accordion-items')][contains(@id,'value')] >> css:div > div > iframe
${Frm_Accordion_Description}				//div[contains(@id,'subform-field-accordion-description')][contains(@id,'value')] >> css:div > div > iframe
${Frm_Accordion2_Content}					//div[contains(@id,'subform-field-accordion-item-content')][contains(@id,'subform-field-text')] >> css:div > div > iframe	
${Btn_Accordion_View}						css:#handorgel1-fold1-header > button
${Opt_Accordion_Column_Left_AddContent_Text}	//ul[contains(@data-drupal-selector, 'subform-field-columns-left-column-add-more-operations')]//input[contains(@name,'subform_field_accordion_item_content_text_add_more')]
${Opt_Accordion_Column_Right_AddContent_Text}	//ul[contains(@data-drupal-selector, 'subform-field-columns-right-column-add-more-operations')]//input[contains(@name,'subform_field_accordion_item_content_text_add_more')]
${Btn_Accordion_Picture_Addnew}				(//input[contains(@name, 'subform-field_accordion_item_content')])[last()]

# CONTENT_CARDS
${Inp_ContentCard_Title}							//input[contains(@name, 'field_content_cards_title')]
${Inp_ContentCard_Design}							//select[contains(@name, 'field_content_cards_design')][contains(@name, 'subform')]
${Inp_ContentCard_TargetId}							(//input[contains(@name, 'target_id')][contains(@name, 'field_content_cards_content')])[last()]
${Inp_ContentCard_Addnew}							//input[contains(@name, 'subform_field_content_cards_content_add_more')]

#LIST-OF-LINKS
${Opt_AddListOfLinks}							    name:field_content_list_of_links_add_more
${Inp_ListOfLinks_Design}							//select[contains(@name, 'field_list_of_links_design')]
${Inp_ListOfLinks_Link_Uri}							(//input[contains(@name, 'field_list_of_links_link')][contains(@name, 'uri')])[last()]
${Inp_ListOfLinks_Link_Title}		    			(//input[contains(@name, 'field_list_of_links_link')][contains(@name, 'title')])[last()]
${Inp_ListOfLinks_Link_NewLink}						//input[contains(@name, 'field_list_of_links_links_list')]
${Inp_ListOfLinks_Link_Description}					(//input[contains(@name, 'field_list_of_links_desc')])[last()]
${Inp_ListOfLinks_Link_AddPicture}					(//input[contains(@name, 'image-media-library-open-button-field_content')])[last()]
${Swh_ListOfLinks_Link_OpenInNewTab}				//input[contains(@name, 'field_list_of_links_link')][contains(@name, 'target_new')]
${Swh_ListOfLinks_Link_LinkIsAccessible}			//input[contains(@name, 'field_list_of_links_link')][contains(@name, 'target_check')]

#LIFTUP-WITH-IMAGE
${Inp_LiftupWithImage_Title}		    			//input[contains(@name, 'liftup_with_image')][contains(@name, 'title')]
${Inp_LiftupWithImage_Picture}		    			//input[contains(@name, 'liftup_with_image')][contains(@name, 'image-media-library-open-button')]
${Inp_LiftupWithImage_Design}						name:field_content[0][subform][field_liftup_with_image_design][0]

#SERVICE
${Inp_Service_Visible_Title}						name:field_service_visible_title[0][value]
${Inp_Service_ParentService}						name:field_service_parent_service[0][target_id]

# REMOTE VIDEO
${Btn_RemoteVideo_Add}								(//input[contains(@name, 'field_remote_video-media-library-open-button-field_content')][contains(@name, 'subform')])[last()]
${Inp_RemoteVideo_Url}								name:url
${Btn_RemoteVideo_AddUrl}							//form/div[2]/input[@data-drupal-selector='edit-submit']

${Btn_RemoteVideo_Confirm}							css:div.ui-dialog-buttonset.form-actions > button
													
${Itm_Video}										css:div.responsive-video-container > iframe
${Itm_Video2}  										(//div[contains(@class,'responsive-video-container')])[last()] >> css:div > div > iframe
${Itm_Video_Description}							(//div[contains(@class,'remote-video-video-desc')][contains(@class, 'cke')])[last()] >> css:div > div > iframe
${Itm_Video_Title}									//input[contains(@name, 'remote_video_video_title')]

${Video_Title}										//h2[contains(@class, 'remote-video__video-title')]
${Video_Description}								//div[contains(@class, 'remote-video__video-desc')]


# ADD FROM LIBRARY
${Inp_Paragraph_Title}								name:label[0][value]
${Inp_Paragraph_Columns_Title}						name:paragraphs[0][subform][field_columns_title][0][value]
${Inp_Paragraph_Banner_Title}						name:paragraphs[0][subform][field_banner_title][0][value]
${Inp_Paragraph_Accordion_Title}						name:paragraphs[0][subform][field_accordion_title][0][value]
${Inp_Paragraph_ContentCards_Title}					name:paragraphs[0][subform][field_content_cards_title][0][value]
${Inp_Paragraph_Banner_Link_Uri}					name:paragraphs[0][subform][field_banner_link][0][uri]
${Inp_Paragraph_Banner_Link_Text}					name:paragraphs[0][subform][field_banner_link][0][title]
${Inp_Paragraph_Accordion_Accordion1_Text}			name:paragraphs[0][subform][field_accordion_items][0][subform][field_accordion_item_heading][0][value]
${Inp_Paragraph_ContentCard_TargetId}				name:paragraphs[0][subform][field_content_cards_content][0][target_id]
${Inp_Paragraph_ListOfLinks_Title}					name:paragraphs[0][subform][field_list_of_links_title][0][value]
${Inp_Paragraph_SidebarText_Title}					name:paragraphs[0][subform][field_sidebar_text_title][0][value]
${Inp_Paragraph_UnitSearch_Title}					(//input[contains(@name, 'field_unit_search_title')])[last()]


${Btn_Paragraph_Gallery_Picture}					name:field_gallery_slide_media-media-library-open-button-paragraphs-0-subform-field_gallery_slides-
${Btn_Paragraph_Image_Picture}						name:field_image-media-library-open-button-paragraphs-0-subform
${Btn_Paragraph_LiftupWithImage_Picture}            name:field_liftup_with_image_image-media-library-open-button-paragraphs-0-subform
${Btn_Paragraph_ListOfLinks_Picture}   				name:field_list_of_links_image-media-library-open-button-paragraphs-0-subform-field_list_of_links_links-0-subform
${Btn_Paragraph_Submit}							    //input[@id='edit-submit']
${Opt_Paragraph_AddColumns}						    name:paragraphs_columns_add_more
${Opt_Paragraph_AddPicture}						    name:paragraphs_image_add_more
${Opt_Paragraph_AddText}							name:paragraphs_text_add_more
${Opt_Paragraph_AddSidebarText}						name:paragraphs_sidebar_text_add_more
${Opt_Paragraph_AddLink}							name:paragraphs_link_add_more
${Opt_Paragraph_AddBanner}   						name:paragraphs_banner_add_more
${Opt_Paragraph_AddAccordion}   					name:paragraphs_accordion_add_more
${Opt_Paragraph_AddContentCards} 					name:paragraphs_content_cards_add_more
${Opt_Paragraph_AddGallery} 					    name:paragraphs_gallery_add_more
${Opt_Paragraph_AddLiftupWithImage}					name:paragraphs_liftup_with_image_add_more
${Opt_Paragraph_AddListOfLinks}						name:paragraphs_list_of_links_add_more
${Opt_Paragraph_AddRemotevideo}						name:paragraphs_remote_video_add_more
${Opt_Paragraph_UnitSearch}							name:paragraphs_unit_search_add_more
${Frm_Paragraph_Column_Left_Text}					//div[contains(@id,'cke_edit-paragraphs-0-subform-field-columns')][contains(@id,'left')]//iframe
${Frm_Paragraph_Column_Right_Text}					//div[contains(@id,'cke_edit-paragraphs-0-subform-field-columns')][contains(@id,'right')]//iframe
${Tar_Paragraph_Picture_Image_Caption}				css:textarea[name*=field_image_caption]
${Tar_Paragraph_Gallery_Image_Caption}				name:paragraphs[0][subform][field_gallery_slides][0][subform][field_gallery_slide_caption][0][value]

${Txt_Banner_Title}									css:.banner__title
${Txt_Banner_Description}							css:div.banner__content-wrapper > div > div > p
${Txt_Banner_Link}									css:div.banner__content-wrapper > a > span
${Txt_ContentCards_Link}							css:h3.content-card__title > span
${Txt_Accordion_Title}								//button[@class='accordion-item__button accordion-item__button--toggle handorgel__header__button']

${Txt_ContentCards_Title}							css:.component__title
${Txt_Gallery_Title}								css:#block-hdbt-page-title > div > h1 > span
${Txt_Gallery_Image_Caption}						css:.image__caption
${Txt_LiftupWithImage_Title}						css:.liftup-with-image__title
${Txt_ListOfLinks_Title}							css:.list-of-links__title
${Txt_ListOfLinks_Link}								css:.list-of-links__item__title

#MAP
${Inp_Map_Title}									//input[contains(@id, 'field-map-title')]
${Inp_Map_Description}								//textarea[contains(@id, 'field-lead-in')]
${Btn_Map_Add}										//input[contains(@name, 'field_map_map-media-library-open-button-field_content')]
${Btn_Map_Url_Add}									(//input[@data-drupal-selector='edit-submit'])[3]
${Itm_Map_Palvelukartta}							css:#palvelukartta-map + div > div > iframe
${Itm_Map_Kartta}									css:#kartta-map + div > div > iframe
${Btn_Map_Kartta_ZoomOut}							css:.custom-zoom-out
${Btn_Map_Palvelukartta_ZoomOut}					//button[contains(@class,'zoomOut')]
${Btn_Map_Palvelukartta_AllowCookies}				//button[contains(text(),'Salli kaikki evästeet')]

#UNIT SEARCH
${Inp_UnitSearch_Title}								//input[contains(@name, 'field_unit_search_title')][contains(@name, 'value')]
${Sel_UnitSearch_Units}								//select[contains(@name, 'field_unit_search_units')]
${Frm_UnitSearch_Content}							//div[contains(@id,'subform-field-unit-search-description')] >> css:div > div > iframe
${Inp_UnitSearch_SearchField}						css:[data-drupal-selector=edit-unit-search]
${Inp_UnitSearch_SearchButton}						css:[data-drupal-selector=edit-actions] > input
${Inp_Chart_Metadata_SearchField_Title}				css:input[name*=field_unit_search_meta_label]
${Inp_Chart_Metadata_SearchField_DefaultText}		css:input[name*=field_unit_search_meta_placehold]
${Inp_Chart_Metadata_SearchField_ButtonText}		css:input[name*=field_unit_search_meta_button]
${Inp_Chart_Metadata_SearchField_LoadMore}	    	css:input[name*=field_unit_search_meta_load_more]
					



#SERVICELIST
${Inp_ServiceList_Title}							//input[contains(@name, 'field_service_list_title')]   
${Sel_ServiceList_Services}							//select[contains(@name, 'field_service_list_services')]

# SIDEBAR
${Inp_Sidebar_Text}									name:field_sidebar_content[0][subform][field_sidebar_text_title][0][value]
${Frm_Sidebar_Text}									//div[contains(@id,'cke_edit-field-sidebar-content')] >> css:div > div > iframe


#CONTENT-LIFTUP
${Inp_UnitId_Text} 									(//input[contains(@id,'edit-field-content')][contains(@id,'target-id')])[last()]

#ANNOUNCEMENTS
${Inp_Announcement_Title}								name:title[0][value]
${Inp_Announcement_Type}								name:field_announcement_type
${Ddn_Announcement_Language}							name:langcode[0][value]
${Swh_Announcement_Visibility}							name:field_announcement_all_pages[value]
${Inp_Announcement_Link_Url}					 	  	id:edit-field-announcement-link-0-uri
${Inp_Announcement_Link_Title}							name:field_announcement_link[0][title]

#CHART
${Inp_Chart_Title}										css:input[name*=field_chart_title]
${Inp_Chart_Description}								//div[contains(@id,'cke_edit-field-content')]//iframe
${Btn_Chart_Add_Media}									//input[contains(@id,'chart-open-button')]
${Inp_Chart_Url}										name:helfi_chart_url
${Btn_Chart_Url_Add}									css:form[id*=helfi-chart-add-form] > div > div > div > input
${Inp_Chart_Url_Title}									css:input[name*=field_helfi_chart_title]
${Frm_Chart_Url_Transcription}							//div[contains(@id,'field-helfi-chart-transcript')]//iframe



# CONTACT CARD LISTING
${Inp_Contact_Card_Listing_Title}								css:input[name*=field_title]
${Inp_Contact_Card_Listing_Description}					//div[contains(@id,'cke_edit-field-content')]//iframe
${Btn_Add_ContactCard_File}								css:input[name*=files]
${Inp_ContactCard_Picture_AlternateText}				(//input[contains(@name, 'field_contact_image')][contains(@name, 'alt')])[last()]
${Inp_ContactCard_Photographer}							(//input[contains(@name, 'field_contact_image_photographer')])[last()]
${Inp_ContactCard_Name}									(//input[contains(@name, 'field_contact_name')])[last()]
${Inp_ContactCard_Title}								(//input[contains(@name, 'field_contact_title')])[last()]
${Inp_ContactCard_Email}								(//input[contains(@name, 'field_email')])[last()]
${Inp_ContactCard_PhoneNumber_1}						(//table[contains(@id, 'field-phone-number-values')])[last()] >> css:tr:nth-child(1) > td:nth-child(2) > div > input
${Inp_ContactCard_PhoneNumber_2}						(//table[contains(@id, 'field-phone-number-values')])[last()] >> css:tr:nth-child(2) > td:nth-child(2) > div > input
${Tar_ContactCard_Description}							(//textarea[contains(@name, 'field_contact_description')])[last()]
${Btn_AddSocialMediaLink}								(//input[contains(@name, 'contact_social_media_social_media_link_add_more')])[last()]
${Sel_ContactCard_SocialMedia_Icon}						(//select[contains(@name, 'field_icon')])[last()]
${Inp_ContactCard_SocialMedia_Url}						(//input[contains(@name, 'field_social_media_link')])[last()]

#UNIT ACCESSIBILITY INFORMATION
${Inp_Unit_Accessibility_Information_Unit} 				(//input[contains(@name, 'field_unit_accessibility_unit')])[last()]

#EVENTS
${Inp_Event_Title}										(//input[contains(@name, 'field_event_list_title')])[last()]
${Inp_Event_Description}								(//div[contains(@id,'cke_edit-field-content')]//iframe)[last()]
${Inp_Event_Url}										(//input[contains(@name, 'field_api_url')])[last()]
${Swh_Event_LoadMore}									(//input[contains(@name, 'field_load_more')])[last()]


#TARGET GROUP LINKS
${Inp_TargetGroupLinks_Title}							css:input[name*=field_title]
${Frm_TargetGroupLinks_Description}						css:textarea[name*=field_description][name*=value] + div > div > div > iframe
${Inp_TargetGroupLinks_Item_Uri}						(//input[contains(@name, 'field_target_group_item_link')][contains(@name, 'uri')])[last()]
${Inp_TargetGroupLinks_Item_Link}						(//input[contains(@name, 'field_target_group_item_link')][contains(@name, 'title')])[last()]
${Inp_TargetGroupLinks_Item_Subtitle}					(//input[contains(@name, 'field_target_group_item_subtitle')])[last()]
${Inp_TargetGroupLinks_Item_NewItem}					css:input[name*=subform_field_target_group_item_target_group_link_item_add_more]

#CURRENT RIGHT NOW
${Sel_CurrentRightNow_Seasons}							css:select[name*=field_seasons]
${Inp_CurrentRightNow_Item_Title}						(//input[contains(@name, 'field_current_links')][contains(@name, 'title')])
${Inp_CurrentRightNow_Item_Link}						(//input[contains(@name, 'field_current_links')][contains(@name, 'uri')])

#POPULAR SERVICES
${Inp_PopularServices_Title}							(//input[contains(@name, 'field_service_title')][contains(@name, 'title')])[last()]
${Inp_PopularServices_Item_NewItem}						css:input[name*=subform_field_service_items_popular_service_item_add_more]
${Inp_PopularServices_Item_Title}						(//input[contains(@name, 'field_service_links')][contains(@name, 'title')])
${Inp_PopularServices_Item_Link}						(//input[contains(@name, 'field_service_links')][contains(@name, 'uri')])

#PHASING
${Sel_Phasing_Title_Level}								(//select[contains(@name, 'field_phasing_title_level')])[last()]
${Swh_Phasing_ShowPhaseNumbers}							(//input[contains(@name, 'field_show_phase_numbers]')])[last()]
${Inp_Phasing_Title}									(//input[contains(@name, 'field_title]')])[1]
${Inp_Phasing_Phase_Title}								(//input[contains(@name, 'field_title]')])[last()]
${Inp_Phasing_Description}								(//div[contains(@id,'cke_edit-field-content')]//iframe)[1]
${Inp_Phasing_Item_Description}							(//div[contains(@id,'cke_edit-field-content')]//iframe)[last()]
${Btn_AddPhasingItem}									(//input[contains(@name, 'subform_field_phasing_item_phasing_item_add_more')])[last()]

