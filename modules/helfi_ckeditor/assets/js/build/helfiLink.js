!function(e,t){"object"==typeof exports&&"object"==typeof module?module.exports=t():"function"==typeof define&&define.amd?define([],t):"object"==typeof exports?exports.CKEditor5=t():(e.CKEditor5=e.CKEditor5||{},e.CKEditor5.helfiLink=t())}(self,(()=>(()=>{var e={"ckeditor5/src/core.js":(e,t,i)=>{e.exports=i("dll-reference CKEditor5.dll")("./src/core.js")},"ckeditor5/src/typing.js":(e,t,i)=>{e.exports=i("dll-reference CKEditor5.dll")("./src/typing.js")},"ckeditor5/src/ui.js":(e,t,i)=>{e.exports=i("dll-reference CKEditor5.dll")("./src/ui.js")},"ckeditor5/src/utils.js":(e,t,i)=>{e.exports=i("dll-reference CKEditor5.dll")("./src/utils.js")},"ckeditor5/src/widget.js":(e,t,i)=>{e.exports=i("dll-reference CKEditor5.dll")("./src/widget.js")},"dll-reference CKEditor5.dll":e=>{"use strict";e.exports=CKEditor5.dll}},t={};function i(n){var s=t[n];if(void 0!==s)return s.exports;var o=t[n]={exports:{}};return e[n](o,o.exports,i),o.exports}i.d=(e,t)=>{for(var n in t)i.o(t,n)&&!i.o(e,n)&&Object.defineProperty(e,n,{enumerable:!0,get:t[n]})},i.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t);var n={};return(()=>{"use strict";i.d(n,{default:()=>V});var e=i("ckeditor5/src/core.js"),t=i("ckeditor5/src/widget.js"),s=i("ckeditor5/src/typing.js");const o=(e,t)=>{let i;if("/"===e||"<front>"===e)return!1;try{i=new URL(e)}catch(t){if(i=new URL(`https://${e}`),!/https?:\/\/(?:www\.)?[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/.test(i))return!1}const n=i.hostname;return!t.some((e=>e.startsWith("*.")&&n.endsWith(e.slice(2))||e===n))},l=e=>{try{const t=new URL(e);return("tel:"===t.protocol||"mailto:"===t.protocol)&&t.protocol.replace(":","")}catch(e){return!1}},r=e=>{if(!e.includes("safelinks.protection.outlook.com"))return e;const t=e.match(/(?<=\?url=).*?(?=&data=)/);return t?decodeURIComponent(t[0]):e},a={linkIcon:{label:Drupal.t("Icon",{},{context:"CKEditor5 Helfi Link plugin"}),machineName:"icon",selectListOptions:{},type:"select",group:"advanced",isVisible:!1,viewAttribute:"data-hds-icon-start"},linkVariant:{label:Drupal.t("Design"),machineName:"variant",selectListOptions:{link:Drupal.t("Normal link",{},{context:"CKEditor5 Helfi Link plugin"}),primary:Drupal.t("Button primary"),secondary:Drupal.t("Button secondary"),supplementary:Drupal.t("Button supplementary")},type:"select",group:"advanced",isVisible:!0,viewAttribute:"data-hds-variant"},linkButton:{machineName:"data-hds-component",type:"static",viewAttribute:"data-hds-component"},linkProtocol:{label:Drupal.t("Protocol",{},{context:"CKEditor5 Helfi Link plugin"}),machineName:"protocol",selectListOptions:{https:"https://",tel:"tel:",mailto:"mailto:"},type:"select",group:"helper",viewAttribute:"data-protocol",isVisible:!0},linkNewWindowConfirm:{label:Drupal.t("The link meets the accessibility requirements",{},{context:"CKEditor5 Helfi Link plugin"}),description:Drupal.t('I have made sure that the description of this link clearly states that it will open in a new tab. <a href="@wcag-techniques" target="_blank">See WCAG 3.2.5 accessibility requirement (the link opens in a new tab).</a>',{"@wcag-techniques":"https://www.w3.org/WAI/WCAG21/Techniques/general/G200.html"},{context:"CKEditor5 Helfi Link plugin"}),machineName:"link-new-window-confirm",viewAttribute:{target:"_blank"},type:"checkbox",group:"advanced",isVisible:!1},linkNewWindow:{label:Drupal.t("Open in new window/tab",{},{context:"CKEditor5 Helfi Link plugin"}),machineName:"link-new-window",type:"checkbox",group:"advanced",isVisible:!0},linkIsExternal:{machineName:"data-is-external",type:"static",viewAttribute:"data-is-external"}};class c extends e.Plugin{static get requires(){return[t.Widget]}static get pluginName(){return"HelfiLinkEditing"}init(){Object.keys(a).forEach((e=>{a[e].machineName&&(this._convertAttribute(e,a[e].viewAttribute),this._removeAttributeOnUnlinkCommandExecute(e),this._refreshAttributeValue(e))})),this._defineHelfiButtonConverters(),this._trimHref(),this._addAttributeOnLinkCommandExecute(Object.keys(a))}_trimHref(){const{editor:e}=this;e.conversion.for("upcast").elementToAttribute({view:"a",model:{key:"linkHref",value:e=>{if(!e.hasAttribute("href"))return null;let t=e.getAttribute("href");return t=r(t),t?t.trim().replace(/^%20+/,"").replace(/%20+$/,"").trim():null}},converterPriority:"highest"})}_convertAttribute(e,t){const{editor:i}=this;if(t)if(i.model.schema.extend("$text",{allowAttributes:e}),i.conversion.for("downcast").attributeToElement({model:e,view:(e,{writer:i})=>{const n={};e&&"object"==typeof t?n[Object.keys(t)]=t[Object.keys(t)]:n[t]=e;const s=i.createAttributeElement("a",n,{priority:5});return i.setCustomProperty("link",!0,s),s}}),"object"==typeof t){const n=Object.keys(t)[0],s=t[Object.keys(t)];i.conversion.for("upcast").attributeToAttribute({view:{name:"a",key:n,value:s},model:{key:e,value:e=>!(!e.hasAttribute(n)||e.getAttribute(n)!==s)}})}else"linkIsExternal"===e||"linkProtocol"===e?i.conversion.for("upcast").elementToAttribute({view:"a",model:{key:e,value:t=>{if(!t.hasAttribute("href"))return null;const i=t.getAttribute("href"),{whiteListedDomains:n}=this.editor.config.get("link");if(!n||!i||i.startsWith("#"))return null;const s=o(i,n),r=l(i);return r&&"linkProtocol"===e?r:!(!s||"linkIsExternal"!==e)||null}},converterPriority:"high"}):i.conversion.for("upcast").elementToAttribute({view:{name:"a",attributes:{[t]:!0}},model:{key:e,value:e=>e.getAttribute(t)}})}_removeAttributeOnUnlinkCommandExecute(e){const{editor:t}=this,{model:i}=this.editor,{selection:n}=i.document,o=t.commands.get("unlink");let l=!1;o.on("execute",(o=>{l||(o.stop(),i.change((()=>{l=!0,t.execute("unlink"),l=!1,i.change((t=>{let o;o=n.isCollapsed?[(0,s.findAttributeRange)(n.getFirstPosition(),e,n.getAttribute(e),i)]:i.schema.getValidRanges(n.getRanges(),e),Array.isArray(o)&&o.forEach((i=>t.removeAttribute(e,i)))}))})))}),{priority:"high"})}_refreshAttributeValue(e){const{editor:t}=this,{model:i}=this.editor,{selection:n}=i.document,s=t.commands.get("link");s.set(e,null),i.document.on("change",(()=>{s[e]=n.getAttribute(e)}))}_defineHelfiButtonConverters(){const{editor:e}=this;e.model.schema.isRegistered("tableCell")&&e.model.schema.extend("tableCell",{allowContentOf:"$block"});const t=e=>{const t=e.split(" "),i=t.find((e=>e.startsWith("hds-button--"))),n=t.find((e=>e.endsWith("hds-button")));return i?i.replace("hds-button--",""):n?"primary":null};e.conversion.for("upcast").elementToElement({view:{name:"a"},model:e=>{const t=Array.from(e.getChildren()).find((e=>"span"===e.name&&e.hasClass("hds-button__label")));if(t){const i=Array.from(e.getChildren()),n=i.length;n>0&&e._removeChildren(0,n),i.forEach((t=>{if("span"===t.name&&t.hasClass("hel-icon")){const i=(e=>{let t=!1,i=e.next();for(;!i.done;){const n=i.value;if(n&&n.startsWith("hel-icon--")){t=n.replace("hel-icon--","");break}i=e.next()}return t})(t.getClassNames());i&&e._setAttribute("data-hds-icon-start",i)}})),Array.from(t.getChildren()).forEach((t=>{e._appendChild(t)}))}const i=Array.from(e.getChildren()).find((e=>!(!e.name||"span"!==e.name)&&(!e.getAttribute("dir")&&!e.getAttribute("lang")&&e)));return i&&(e._removeChildren(i.index,1),Array.from(i.getChildren()).forEach((t=>{e._appendChild(t)}))),e.hasAttribute("data-protocol")&&e.getAttribute("data-protocol").startsWith("http")&&e._removeAttribute("data-protocol"),e},converterPriority:"highest"}),e.conversion.for("upcast").attributeToAttribute({view:{name:"a",key:"data-design"},model:{key:"linkVariant",value:e=>{let i;return e.hasClass("hds-button")&&(i=t([...e._classes].join(" ")),i||(i=t(e.getAttribute("data-design")))),i&&"primary"===i&&(i=null),i}}}),e.conversion.for("upcast").attributeToAttribute({view:{name:"a",key:"data-design"},model:{key:"linkButton",value:e=>{let i;return e.hasClass("hds-button")&&(i=t([...e._classes].join(" ")),i||(i=t(e.getAttribute("data-design")))),!!i&&"button"}}}),e.conversion.for("upcast").attributeToAttribute({view:{name:"a",key:"class",value:"hds-button"},model:{key:"linkButton",value:"button"}}),e.conversion.for("upcast").attributeToAttribute({view:{name:"a",key:"data-protocol"},model:{key:"linkProtocol",value:e=>{return"https"!==(t=e.getAttribute("data-protocol"))&&"http"!==t&&t;var t}},converterPriority:"highest"}),e.conversion.for("upcast").attributeToAttribute({view:{name:"a",key:"data-selected-icon"},model:{key:"linkIcon"}})}_addAttributeOnLinkCommandExecute(e){const{editor:t}=this,i=t.commands.get("link");let n=!1;i.on("execute",((i,s)=>{if(s.length<3)return;if(n)return void(n=!1);i.stop(),n=!0;const{model:o}=t,a=s[0];let c=a;if(a){c=a.trim().replace(/^%20+/,"").replace(/%20+$/,"").trim(),c=r(c);"tel"===l(c)&&(c=c.replace(/[\s()-]/g,"")),s[0]=c}const d=s[1];o.change((()=>{t.execute("link",...s)}));try{const i=t.model.createBatch({isUndoable:!1});t.model.enqueueChange(i,(i=>{const s=t.model.document.selection;e.forEach((e=>{if(s.isCollapsed){const t=s.getFirstPosition(),n=t.textNode||t.nodeAfter||t.nodeBefore;if(!n)return;const o=i.createRangeOn(n);d[e]?i.setAttribute(e,d[e],o):i.removeAttribute(e,o),i.removeSelectionAttribute(e),i.setSelection(o)}else{o.schema.getValidRanges(s.getRanges(),e).forEach((t=>{d[e]?i.setAttribute(e,d[e],t):i.removeAttribute(e,t)}))}})),n=!1}))}catch(e){i.stop()}}),{priority:"high"})}}var d=i("ckeditor5/src/ui.js"),h=i("ckeditor5/src/utils.js");class m extends d.View{constructor(e){super(e);const t=this.bindTemplate;this.set("class"),this.set("isVisible",!0),this.set("isChecked",!1),this.set("label"),this.set("description"),this.set("id",null),this.set("tabindex",-1),this.children=this.createCollection(),this.labelView=this._createLabelView(),this.checkboxInputView=this._createCheckboxInputView(),this.checkboxSpanToggle=this._createCheckboxSpanToggleView(),this.bind("isVisible").to(this,"updateVisibility"),this.bind("isChecked").to(this,"updateChecked"),this.setTemplate({tag:"div",attributes:{class:["form-type--checkbox","helfi-link-checkbox",t.if("isVisible","is-hidden",(e=>!e)),t.to("class")]},on:{keydown:t.to((e=>{e.target===this.element&&e.keyCode===(0,h.getCode)("space")&&(this.isChecked=!this.isChecked)}))},children:this.children})}render(){super.render(),this.children.add(this.checkboxInputView),this.children.add(this.checkboxSpanToggle),this.children.add(this.labelView)}focus(){this.element.focus()}_createCheckboxInputView(){const e=new d.View,t=this.bindTemplate;return e.setTemplate({tag:"input",attributes:{type:"checkbox",id:t.to("id"),checked:t.if("isChecked")},on:{change:t.to((e=>{this.isChecked=e.target.checked}))}}),e}_createCheckboxSpanToggleView(){const e=new d.View,t=this.bindTemplate;return e.setTemplate({tag:"span",attributes:{class:["checkbox-toggle"],id:t.to("id")},children:[{tag:"span",attributes:{class:["checkbox-toggle__inner"]}}]}),e}_createLabelView(){const e=new d.View;return e.setTemplate({tag:"label",attributes:{for:this.bindTemplate.to("id")},children:[{text:this.bindTemplate.to("label")}]}),e}updateVisibility(e){e?this.element.classList.remove("is-hidden"):this.element.classList.add("is-hidden")}updateChecked(e){e!==this.isChecked&&this.checkboxInputView?.element?.click()}}class u extends d.View{constructor(e,t){super(e.locale),this.options=t,this.tomSelect=!1,this.linkCommandConfig=e.config.get("link"),this.loadedIcons=this.linkCommandConfig?.loadedIcons,this.set("isVisible",!1),this.bind("isVisible").to(this,"updateVisibility");const i=this.bindTemplate;this.set("isOpen",!1),this.set("label"),this.set("id",null),this.setTemplate({tag:"select",attributes:{id:i.to("id"),class:["ck-helfi-link-select-list"],"data-placeholder":this.options.label,autocomplete:"off"},on:{keydown:i.to((e=>{e.target===this.element&&e.keyCode===(0,h.getCode)("space")&&(this.isOpen=!this.isOpen)}))}})}updateVisibility(e){e?(this.tomSelect?.wrapper?.classList.remove("is-hidden"),this.element.classList.remove("is-hidden")):(this.tomSelect?.wrapper?.classList.add("is-hidden"),this.element.classList.add("is-hidden"))}render(){super.render()}focus(){this.element.focus()}selectListDefaultOptions(){return{valueField:"option",labelField:"name",searchField:"title",maxItems:1,placeholder:this.options.label,create:!1}}}class p extends u{renderTomSelect(e,t){if(!this.tomSelect&&e){const e=super.selectListDefaultOptions(),i=(e,t)=>`\n          <span style="align-items: center; display: flex; height: 100%;">\n            <span class="hel-icon--name" style="margin-left: 8px;">${t(e.title)}</span>\n          </span>\n        `,n={...e,options:Object.keys(t).map((e=>({option:e,title:t[e]}))),render:{option:(e,t)=>i(e,t),item:(e,t)=>i(e,t)}};this.tomSelect=new TomSelect(this.element,n)}}}class k extends d.View{constructor(e,t){super(e),this.advancedChildren=t;const i=this.bindTemplate;this.set("isOpen",!1),this.set("label"),this.set("id",null),this.children=this.createCollection(),this.detailsSummary=this._createDetailsSummary(),this.setTemplate({tag:"details",attributes:{id:i.to("id"),class:["ck-helfi-link-details",i.if("isOpen","ck-is-open",(e=>e))],open:i.if("isOpen")},on:{keydown:i.to((e=>{e.target===this.element&&e.keyCode===(0,h.getCode)("space")&&(this.isOpen=!this.isOpen)}))},children:this.children})}render(){super.render(),this.children.add(this.detailsSummary),this.children.addMany(this.advancedChildren)}focus(){this.element.focus()}_createDetailsSummary(){const e=new d.View;return e.setTemplate({tag:"summary",attributes:{role:"button",class:["ck-helfi-link-details__summary"],tabindex:0},children:[{text:this.bindTemplate.to("label")}]}),e}}class w extends u{renderTomSelect(e,t){if(!this.tomSelect&&e){const e=super.selectListDefaultOptions(),i=(e,t)=>`\n          <span style="align-items: center; display: flex; height: 100%;">\n            <span class="hel-icon--name" style="margin-left: 8px;">${t(e.title)}</span>\n          </span>\n        `,n={...e,plugins:{dropdown_input:{},remove_button:{title:"Remove this item"}},options:Object.keys(t).map((e=>({option:e,title:t[e]}))),render:{option:(e,t)=>i(e,t),item:(e,t)=>i(e,t)}};this.tomSelect=new TomSelect(this.element,n)}}}class b extends u{renderTomSelect(e){if(!this.tomSelect&&e){const e=super.selectListDefaultOptions(),t=(e,t)=>`\n          <span style="align-items: center; display: flex; height: 100%;">\n            <span class="hel-icon hel-icon--${e.icon}" aria-hidden="true"></span>\n            <span class="hel-icon--name" style="margin-left: 8px;">${t(e.name)}</span>\n          </span>\n        `,i={...e,plugins:{dropdown_input:{},remove_button:{title:"Remove this item"}},valueField:"icon",searchField:["name"],options:Object.keys(this.loadedIcons).map((e=>({icon:e,name:this.loadedIcons[e]}))),render:{option:(e,i)=>t(e,i),item:(e,i)=>t(e,i)}};this.tomSelect=new TomSelect(this.element,i)}}}class f extends e.Plugin{constructor(e){super(e),this.editor=e,this.advancedChildren=new h.Collection,this.formElements=a,this.helfiContextualBalloonInitialized=!1,this.linkFormView={}}init(){this.editor.plugins.get("LinkUI")._createViews&&this.editor.plugins.get("LinkUI")._createViews(),this._addContextualBalloonClass(),this.editor.plugins.get("ContextualBalloon").on("change:visibleView",((e,t,i,n)=>{if(this.linkFormView=this.editor.plugins.get("LinkUI").formView,i===n||i!==this.linkFormView||!this.helfiContextualBalloonInitialized)return;const s=Object.keys(this.formElements).reverse();s.forEach((e=>{if(!this.formElements[e].machineName)return;const t=this._createFormField(e);this.formElements[e].group&&"advanced"===this.formElements[e].group&&void 0!==t&&this.advancedChildren.add(t)})),this._createAdvancedSettingsAccordion(),s.forEach((e=>{this.formElements[e].machineName&&this._handleDataLoadingIntoFormField(e)})),this.linkFormView.urlInputView.infoText||(this.linkFormView.urlInputView.infoText=Drupal.t("Start typing to find content.",{},{context:"CKEditor5 Helfi Link plugin"})),this._handleCheckboxes(),this._moveSubmitButtonToBottom(),this._reorderFormFields(),this._handleFormFieldSubmit(s)}))}_addContextualBalloonClass(){const{editor:e}=this;e.plugins.get("ContextualBalloon").on("set:visibleView",((e,t,i,n)=>{if(i===n||i!==this.linkFormView)return;const s=this.editor.plugins.get("ContextualBalloon");s.hasView(this.linkFormView)&&!s.view.element.classList.contains("helfi-contextual-balloon")&&(this.linkFormView.template.attributes.class.push("helfi-link-form"),this.linkFormView.template.attributes.class.push("ck-reset_all-excluded"),s.remove(i),s.add({view:this.linkFormView,position:s.getPositionOptions(),balloonClassName:"helfi-contextual-balloon",withArrow:!1}),this.helfiContextualBalloonInitialized=!0)}))}_createSelectList(e,t){const{editor:i}=this;let n={};switch(e){case"linkProtocol":n=new p(i,t),this.linkFormView.urlInputView.on("change:isEmpty",((e,t,i)=>{n.updateVisibility(i)}));break;case"linkVariant":n=new w(i,t);break;case"linkIcon":n=new b(i,t),this.linkFormView.linkVariant.on("change:isEmpty",((e,t,i)=>{n.updateVisibility(i)}))}return n.set({isVisible:t.isVisible,id:t.machineName,label:t.label}),n}_createAdvancedSettingsAccordion(){if(this.linkFormView.advancedSettings)return this.linkFormView.advancedSettings.element.open=!1,this.linkFormView.advancedSettings.detailsSummary.element.ariaExpanded=!1,this.linkFormView.advancedSettings.detailsSummary.element.ariaPressed=!1,this.linkFormView.advancedSettings;const{editor:e}=this,t=new k(e.locale,this.advancedChildren);return t.set({label:Drupal.t("Advanced settings",{},{context:"CKEditor5 Helfi Link plugin"}),id:"advanced-settings",isOpen:!1}),this.linkFormView.children.add(t,2),this.linkFormView.urlInputView.errorText&&(this.linkFormView.urlInputView.errorText=""),this.linkFormView.advancedSettings=t,this.linkFormView.advancedSettings}_createCheckbox(e){const t=new m(this.editor.locale),i=this.formElements[e];return t.set({isVisible:i.isVisible,tooltip:!0,class:"ck-find-checkboxes__box",id:i.machineName,label:i.label}),t}_createFormField(e){const{editor:t}=this,i=this.formElements[e],n=t.commands.get("link");if(!this.linkFormView[e]){let s={};switch(i.type){case"select":s=this._createSelectList(e,i);break;case"checkbox":s=this._createCheckbox(e);break;case"static":s=!1;break;default:s=new d.LabeledFieldView(t.locale,d.createLabeledInputText)}if(!s)return;return s.machineName=e,s.class=`helfi-link--${i.machineName}`,s.label=i.label,i.description&&(s.infoText=i.description),i.group&&"advanced"===i.group||this.linkFormView.children.add(s,"select"===i.type?0:1),this.linkFormView._focusables.add(s,1),this.linkFormView.focusTracker.add(s.element),this.linkFormView[e]=s,"checkbox"===i.type&&this.linkFormView[e].checkboxInputView.bind("isChecked").to(n,e),"input"===i.type&&this.linkFormView[e].fieldView.bind("value").to(n,e),"linkProtocol"!==e||this.linkFormView.urlInputView.isEmpty||this.linkFormView[e].updateVisibility(!1),this.linkFormView[e]}}_handleDataLoadingIntoFormField(e){const{editor:t}=this,i=t.commands.get("link"),n=this.formElements[e];if(void 0!==this.linkFormView[e])switch(n.type){case"static":return;case"checkbox":{const t=!!i.linkNewWindowConfirm;this.linkFormView[e].updateChecked(t),this.linkFormView.linkNewWindowConfirm&&this.linkFormView.linkNewWindowConfirm.updateVisibility(t);break}case"select":this.linkFormView[e].renderTomSelect(this.linkFormView[e].element,n?.selectListOptions),"linkIcon"===e&&this.linkFormView[e].tomSelect.on("initialize",(()=>{this.linkFormView[e].updateVisibility(!1)})),this.linkFormView[e].tomSelect.clear(),i[e]&&this.linkFormView[e].tomSelect.addItem(i[e],!0),"linkProtocol"===e&&this.linkFormView[e].tomSelect.on("item_add",(t=>{this.linkFormView.urlInputView.isEmpty&&(this.linkFormView.urlInputView.fieldView.value=n.selectListOptions[t],this.linkFormView.urlInputView.focus(),this.linkFormView[e].tomSelect.clear())})),"linkVariant"===e&&("button"!==i.linkButton||i[e]&&"primary"!==i[e]||this.linkFormView[e].tomSelect.addItem("primary",!0),i.linkVariant||"button"===i.linkButton||this.linkFormView?.linkIcon.updateVisibility(!1),this.linkFormView[e].tomSelect.on("item_remove",(()=>{this.linkFormView?.linkIcon.tomSelect.clear(),this.linkFormView?.linkIcon.updateVisibility(!1)})),this.linkFormView[e].tomSelect.on("item_add",(e=>{this.linkFormView?.linkIcon.updateVisibility("link"!==e),"link"===e&&this.linkFormView?.linkIcon.tomSelect.clear()})));break;default:this.linkFormView[e].fieldView.element.value=i[e]||""}}_handleCheckboxes(){if(this.formElements.linkNewWindowConfirm&&this.formElements.linkNewWindow&&this.linkFormView&&this.linkFormView.linkNewWindow&&this.linkFormView.linkNewWindowConfirm&&this.linkFormView.linkNewWindowConfirm.element){if(!this.linkFormView.linkNewWindowConfirm.element.querySelector(".helfi-link-form__field_description")){const e=document.createElement("div");e.innerHTML=this.formElements.linkNewWindowConfirm.description,e.classList.add("helfi-link-form__field_description"),this.linkFormView.linkNewWindowConfirm.element.appendChild(e)}this.linkFormView.linkNewWindow.on("change:isChecked",((e,t,i)=>{this.linkFormView?.linkNewWindowConfirm.updateChecked(!1),this.linkFormView?.linkNewWindowConfirm.updateVisibility(i)}))}}_handleFormFieldSubmit(e){const{editor:t}=this,{selection:i}=t.model.document,n=t.commands.get("link");this.stopListening(this.linkFormView,"submit"),this.listenTo(this.linkFormView,"submit",(t=>{this.linkFormView.urlInputView?.fieldView?.element?.value||(this.linkFormView.urlInputView.errorText=Drupal.t("The link URL field cannot be empty.",{},{context:"CKEditor5 Helfi Link plugin"}),t.stop());const{whiteListedDomains:s}=this.editor.config.get("link"),r=this.linkFormView.urlInputView?.fieldView?.element?.value,a=e.reduce(((e,t)=>{switch(t){case"linkVariant":{const i=this.linkFormView?.[t]?.tomSelect.getValue();if(!i||"link"===i)return e;if("primary"===i)return e.linkButton="button",e;e[t]=i,e.linkButton="button";break}case"linkIcon":e[t]=this.linkFormView?.[t]?.tomSelect.getValue();break;case"linkProtocol":if(!s||!r||r.startsWith("#"))break;l(r)&&(e[t]=l(r));break;case"linkIsExternal":if(!s||!r||r.startsWith("#"))break;if(!l(r)&&o(r,s)){e[t]=o(r,s);break}break;default:e[t]=this.linkFormView?.[t]?.fieldView?.element?.value??""}return"checkbox"===this.formElements[t].type&&(e[t]=this.linkFormView?.[t]?.checkboxInputView?.element?.checked),e}),{});i.isCollapsed&&!this.linkFormView.urlInputView?.fieldView?.element?.value&&a.linkId&&(this.linkFormView.urlInputView.errorText=Drupal.t("When there is no selection, the link URL must be provided. To add a link without a URL, first select text in the editor and then proceed with adding the link.",{},{context:"CKEditor5 Helfi Link plugin"}),t.stop()),a.linkNewWindowConfirm&&a.linkNewWindow||(a.linkNewWindowConfirm=!1,a.linkNewWindow=!1,this.linkFormView.linkNewWindowConfirm.checkboxInputView.element.checked&&this.linkFormView.linkNewWindowConfirm.checkboxInputView.element.click(),this.linkFormView.linkNewWindow.checkboxInputView.element.checked&&this.linkFormView.linkNewWindow.checkboxInputView.element.click());n.once("execute",((e,t)=>{"object"==typeof t[1]?Object.assign(t[1],a):t[1]=a}),{priority:"highest"})}),{priority:"high"})}_moveSubmitButtonToBottom(){const{urlInputView:e}=this.linkFormView;if(!e||!e.children)return;const t=e.children.find((e=>e.element?.classList.contains("ck-form__row_with-submit")));t&&(e.children.remove(t),this.linkFormView.children.add(t),this.linkFormView.saveButtonView=t)}_reorderFormFields(){const{advancedSettings:e,children:t,displayedTextInputView:i,linkProtocol:n,saveButtonView:s,urlInputView:o,backButtonView:l}=this.linkFormView,r=t.find((e=>e.template?.attributes?.class?.includes("ck-form__header")));[[o,"helfi-link-url-input"],[i,"helfi-link-text-input"],[s,"helfi-link-save-button"],[l,"helfi-link-back-button"]].forEach((([e,t])=>{((e,t)=>{if(!e||"string"!=typeof e.class)return;e.class.split(" ").includes(t)||(e.class+=` ${t}`)})(e,t)}));const a=[r,n,i,o,e,l,s].filter(Boolean);t.clear(),a.forEach((e=>{t.add(e)}));const c=new Set(a);t._items.filter((e=>!c.has(e))).forEach((e=>{t.add(e)}))}}class g extends e.Plugin{static get requires(){return[c,f]}}const V={HelfiLink:g}})(),n=n.default})()));