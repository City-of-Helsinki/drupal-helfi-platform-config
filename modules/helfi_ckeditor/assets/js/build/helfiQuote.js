!function(e,t){"object"==typeof exports&&"object"==typeof module?module.exports=t():"function"==typeof define&&define.amd?define([],t):"object"==typeof exports?exports.CKEditor5=t():(e.CKEditor5=e.CKEditor5||{},e.CKEditor5.helfiQuote=t())}(self,(()=>(()=>{var e={"ckeditor5/src/core.js":(e,t,i)=>{e.exports=i("dll-reference CKEditor5.dll")("./src/core.js")},"ckeditor5/src/ui.js":(e,t,i)=>{e.exports=i("dll-reference CKEditor5.dll")("./src/ui.js")},"ckeditor5/src/utils.js":(e,t,i)=>{e.exports=i("dll-reference CKEditor5.dll")("./src/utils.js")},"ckeditor5/src/widget.js":(e,t,i)=>{e.exports=i("dll-reference CKEditor5.dll")("./src/widget.js")},"dll-reference CKEditor5.dll":e=>{"use strict";e.exports=CKEditor5.dll}},t={};function i(o){var s=t[o];if(void 0!==s)return s.exports;var r=t[o]={exports:{}};return e[o](r,r.exports,i),r.exports}i.d=(e,t)=>{for(var o in t)i.o(t,o)&&!i.o(e,o)&&Object.defineProperty(e,o,{enumerable:!0,get:t[o]})},i.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t);var o={};return(()=>{"use strict";i.d(o,{default:()=>h});var e=i("ckeditor5/src/core.js"),t=i("ckeditor5/src/widget.js");class s extends e.Command{execute({quoteText:e,author:t}){const{model:i}=this.editor;i.change((o=>{e&&i.insertContent(this._createQuote(o,e,t))}))}refresh(){const{model:e}=this.editor,{selection:t}=e.document,i=e.schema.findAllowedParent(t.getFirstPosition(),"helfiQuote");this.isEnabled=null!==i}_createQuote(e,t,i){const o=e.createElement("helfiQuote"),s=e.createElement("helfiQuoteText"),r=e.createElement("helfiQuoteFooter"),n=e.createElement("helfiQuoteFooterCite");return e.append(s,o),e.insertText(t,s),i&&(e.append(r,o),e.append(n,r),e.insertText(i,n)),o}}class r extends e.Plugin{static get requires(){return[t.Widget]}init(){const e=this.editor;this._defineSchema(),this._defineConverters(),e.commands.add("helfiQuoteCommand",new s(e))}static get pluginName(){return"HelfiQuoteEditing"}_defineSchema(){const e=this.editor.model.schema;e.register("helfiQuote",{isObject:!0,allowWhere:"$block"}),e.register("helfiQuoteText",{isLimit:!0,allowIn:"helfiQuote",allowContentOf:"$block"}),e.register("helfiQuoteFooter",{isLimit:!0,allowIn:"helfiQuote",allowContentOf:"$block"}),e.register("helfiQuoteFooterCite",{isLimit:!0,allowIn:"helfiQuoteFooter",allowContentOf:"$block"})}_defineConverters(){const{conversion:e}=this.editor,i=(t,i,o=null)=>{const s={model:t,view:{name:i,...o?{classes:o}:{}}};e.for("upcast").elementToElement(s),e.for("dataDowncast").elementToElement(s)};i("helfiQuote","blockquote","quote"),i("helfiQuoteText","p","quote__text"),i("helfiQuoteFooter","footer","quote__author"),i("helfiQuoteFooterCite","cite"),e.for("editingDowncast").elementToElement({model:"helfiQuote",view:(e,{writer:i})=>{const o=i.createContainerElement("blockquote",{class:"quote"});return(0,t.toWidget)(o,i)}}),e.for("editingDowncast").elementToElement({model:"helfiQuoteText",view:(e,{writer:i})=>{const o=i.createEditableElement("p",{class:"quote__text"});return(0,t.toWidgetEditable)(o,i)}}),e.for("editingDowncast").elementToElement({model:"helfiQuoteFooter",view:(e,{writer:i})=>{const o=i.createContainerElement("footer",{class:"quote__author"});return(0,t.toWidget)(o,i)}}),e.for("editingDowncast").elementToElement({model:"helfiQuoteFooterCite",view:(e,{writer:i})=>{const o=i.createEditableElement("cite",{});return(0,t.toWidgetEditable)(o,i)}})}}var n=i("ckeditor5/src/ui.js");var l=i("ckeditor5/src/utils.js");class a extends n.View{constructor(e,t){super(e,t),this.textAreaLabel=Drupal.t("Quotation",{},{context:"CKEditor5 Helfi Quote plugin"}),this.set("value",void 0),this.set("id",void 0),this.set("label"),this.focusTracker=new l.FocusTracker,this.bind("isFocused").to(this.focusTracker),this.set("isEmpty",!0),this.children=this.createCollection(),this.textArea=this._createTextareaView(e),this.setTemplate({tag:"div",attributes:{class:["ck-helfi-textarea"]},children:this.children})}render(){super.render(),this.children.add(this.textArea),this.focusTracker.add(this.textArea),this.focusTracker.add(this.textArea.fieldView.element),this._setDomElementValue(this.value),this._updateValue()}destroy(){super.destroy(),this.value="",this.focusTracker.destroy()}_createTextareaView(e){const t=this.bindTemplate,i=new n.LabeledFieldView(e,((e,i)=>{const o=new n.View(e.locale);return o.setTemplate({tag:"textarea",attributes:{rows:5,cols:40,id:i,class:["ck","ck-input","ck-helfi-textarea",t.if("isEmpty","ck-input_is-empty"),t.if("isFocused","ck-input_focused")]},on:{input:t.to(((...e)=>{this.fire("input",...e),this._updateValue()})),change:t.to(this._updateValue.bind(this))}}),o.bind("isFocused").to(e,"isFocused"),e.bind("isFocused").to(o,"isFocused"),o}));return i.label=this.textAreaLabel,i}focus(){this.textArea.fieldView.element.focus()}_setDomElementValue(e){this.element.value=e||0===e?e:"",this.textArea.fieldView.element.value=this.element.value}_updateValue(){this.value=this.isInputElementValue(this.textArea.fieldView.element),this.isEmpty=!this.value}updateValueBasedOnSelection(e=""){this.isEmpty=!e,this._setDomElementValue(e)}isInputElementValue(e){return e.value}}class u extends n.View{constructor(t,i){super(t,i),this.editor=i,this.textAreaView=new a(t,i),this.authorInputView=new n.LabeledFieldView(i.locale,n.createLabeledInputText),this.authorInputView.label=Drupal.t("Source / author",{},{context:"CKEditor5 Helfi Quote plugin"}),this.saveButtonView=this._createButton(Drupal.t("Save",{},{context:"CKEditor5 Helfi Quote plugin"}),e.icons.check,"ck-button-save"),this.saveButtonView.type="submit",this.cancelButtonView=this._createButton(Drupal.t("Cancel",{},{context:"CKEditor5 Helfi Quote plugin"}),e.icons.cancel,"ck-button-cancel","cancel"),this.keystrokes=new l.KeystrokeHandler,this.children=this.createCollection(),this.setTemplate({tag:"form",attributes:{class:["ck","ck-helfi-quote-form"],tabindex:"-1"},children:this.children})}render(){super.render(),(0,n.submitHandler)({view:this}),this.children.add(this.textAreaView),this.children.add(this.authorInputView),this.children.add(this.saveButtonView),this.children.add(this.cancelButtonView),this.keystrokes.listenTo(this.element)}destroy(){super.destroy(),this.saveButtonView.focus(),this.editor.editing.view.focus(),this.textAreaView.destroy()}focus(){this?.textAreaView?.children?.first?.fieldView?.element.focus()}_createButton(e,t,i,o=!1){const s=new n.ButtonView(this.locale);return s.set({label:e,icon:t,tooltip:!0}),s.extendTemplate({attributes:{class:i}}),o&&s.delegate("execute").to(this,o),s}}class d extends e.Plugin{constructor(e){super(e),this.editor=e,this.updateSelection=!1,this.quoteFormView=!1,this.dropdownView=!1}init(){const{editor:e}=this,t=Drupal.t("Add a quote",{},{context:"CKEditor5 Helfi Quote plugin"});e.ui.componentFactory.add("helfiQuote",(e=>{const i=this.editor.commands.get("helfiQuoteCommand");return this.dropdownView=(0,n.createDropdown)(e),this.dropdownView.buttonView.set({label:t,icon:'<?xml version="1.0" encoding="utf-8"?>\n<svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"\n     viewBox="0 0 20 20" style="enable-background:new 0 0 20 20;" xml:space="preserve">\n<path d="M-17.3,0.9"/>\n    <path d="M6.4,15.6c-1.1,0-1.9-0.4-2.6-1.1c-0.7-0.8-1-1.8-1-3.2c0-0.7,0.1-1.4,0.3-2.1c0.2-0.7,0.4-1.3,0.7-1.9\n\tc0.3-0.6,0.7-1.1,1.1-1.6c0.4-0.5,0.9-0.9,1.5-1.2l3.3,1.8C9.4,6.4,9,6.5,8.6,6.7C8.2,6.9,7.8,7.2,7.5,7.5c-0.4,0.3-0.7,0.7-1,1.1\n\tC6.2,9,6,9.4,5.8,10c0.1,0,0.2,0,0.3,0c0.1,0,0.2,0,0.3,0c0.8,0,1.5,0.3,2.1,0.8s0.8,1.2,0.8,2.1c0,0.8-0.3,1.5-0.9,2\n\tC7.9,15.3,7.2,15.6,6.4,15.6z M13.9,15.6c-1.1,0-1.9-0.4-2.6-1.1c-0.7-0.8-1-1.8-1-3.2c0-0.7,0.1-1.4,0.3-2.1\n\tc0.2-0.7,0.4-1.3,0.7-1.9c0.3-0.6,0.7-1.1,1.1-1.6c0.4-0.5,0.9-0.9,1.5-1.2l3.3,1.8c-0.3,0.1-0.6,0.3-1,0.5\n\tc-0.4,0.2-0.8,0.5-1.2,0.8c-0.4,0.3-0.7,0.7-1,1.1C13.7,9,13.5,9.4,13.4,10c0.1,0,0.2,0,0.3,0c0.1,0,0.2,0,0.3,0\n\tc0.8,0,1.5,0.3,2.1,0.8c0.6,0.5,0.8,1.2,0.8,2.1c0,0.8-0.3,1.5-0.9,2C15.5,15.3,14.8,15.6,13.9,15.6z"/>\n</svg>\n',tooltip:!0}),this.dropdownView.buttonView.unbind("isEnabled"),this.dropdownView.buttonView.bind("isEnabled").to(i,"isEnabled"),this.dropdownView.extendTemplate({attributes:{class:["helfi-quote"]}}),this.dropdownView.panelView.extendTemplate({attributes:{class:["helfi-quote__dropdown-panel","ck-reset_all-excluded"]}}),this.dropdownView.on("change:isOpen",(()=>{this.quoteFormView||(this.quoteFormView=new u(e,this.editor),this.listenTo(this.quoteFormView,"submit",(()=>{const e=this.quoteFormView.textAreaView.textArea.fieldView.element.value||!1,t=this.quoteFormView.authorInputView.fieldView.element.value||!1;i.execute({quoteText:e,author:t}),this._closeFormView()})),this.listenTo(this.quoteFormView,"cancel",(()=>{this._closeFormView()})),this.quoteFormView.keystrokes.set("Esc",((e,t)=>{this._closeFormView(),t()})),this.dropdownView.panelView.children.add(this.quoteFormView),this.dropdownView.panelPosition="sw",this.quoteFormView.delegate("execute").to(this.dropdownView),this.quoteFormView.focus())})),this.dropdownView.on("change:isOpen",(()=>{this._updateQuoteDefaultValues()})),this.dropdownView}))}_updateQuoteDefaultValues(){const e=this.editor.model.document.selection;if(this.quoteFormView)if(e.isCollapsed)this.quoteFormView.textAreaView.updateValueBasedOnSelection(),this.quoteFormView.authorInputView.isEmpty=!0,this.quoteFormView.authorInputView.fieldView.element.value="";else for(const t of e.getRanges())for(const e of t.getItems())e.data&&("helfiQuoteText"!==e.textNode?.parent?.name&&"paragraph"!==e.textNode?.parent?.name||this.quoteFormView.textAreaView.updateValueBasedOnSelection(e.data),this.quoteFormView.authorInputView.isEmpty="helfiQuoteFooterCite"!==e.textNode?.parent?.name,this.quoteFormView.authorInputView.fieldView.element.value="helfiQuoteFooterCite"===e.textNode?.parent?.name?e.data:"",this.quoteFormView.focus())}_closeFormView(){this.quoteFormView.textAreaView.textArea.fieldView.element.value=null,this.quoteFormView.authorInputView.fieldView.element.value=null,this.dropdownView&&(this.dropdownView.isOpen=!1)}}class c extends e.Plugin{static get requires(){return[r,d]}}const h={HelfiQuote:c}})(),o=o.default})()));