(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.chat_leijuke = {
    attach: function (context, settings) {

      const leijukeData = drupalSettings.leijuke_data;

      for (const chat_selection in leijukeData) {
        const adapter = getAdapter(chat_selection);
        if (!adapter) return;

        setTimeout(() => {
          // Only load any leijuke once, in case of ajax triggers.
          if (leijukeData[chat_selection].initialized) {
            console.warn(`Already initialized ${chat_selection}!`);
            return;
          }

          new Leijuke(leijukeData[chat_selection], new EuCookieManager, adapter);
          drupalSettings.leijuke_data[chat_selection].initialized = true;
        });
      }
    }
  }

  function getAdapter(chatSelection) {
    if (chatSelection.indexOf('genesys') != -1) {
      return new GenesysAdapter;
    }
    if (chatSelection.indexOf('smartti') != -1) {
      return new SmarttiAdapter;
    }
    if (chatSelection.indexOf('watson') != -1) {
      return new WatsonAdapter;
    }
    if (chatSelection.indexOf('kuura') != -1) {
      return new KuuraAdapter;
    }
    console.warn(`No adapter found for ${chatSelection}!`);
  }

  class EuCookieManager {
    cookieCheck(cookieNames) {
      let cookiesOk = true;
      cookieNames.map((cookieName) => {
        if (!Drupal.eu_cookie_compliance.hasAgreedWithCategory(cookieName)) cookiesOk = false;
      });
      return cookiesOk;
    }

    cookieSet() {
      Drupal.eu_cookie_compliance.setAcceptedCategories([ ...Drupal.eu_cookie_compliance.getAcceptedCategories(), 'chat' ]);
    }
  }

  class GenesysAdapter {

    constructor() {
      this.requiredCookies = ['chat'];
      this.bot = false;
      this.persist = true;
    }

    async getChatExtension() {
      return await new Promise(resolve => {
        let checkChatExtension = setInterval(()=> {
          if (typeof chatExtension != 'undefined') {
            resolve(chatExtension);
            clearInterval(checkChatExtension);
          }
        }, 100);
      });
    }

    open(callback) {
      // send open command
      this.getChatExtension().then((ext) => chatExtension.command('WebChat.open').done(callback).fail(console.warn('Failed WebChat open command.')));
    }

    onClosed(callback) {
      // subscribe to closed event
      this.getChatExtension().then((ext) => chatExtension.subscribe('WebChat.closed', callback));
    }

    onLoaded(callback) {
      // subscribe to ready event
      this.getChatExtension().then((ext) => chatExtension.subscribe('WebChat.ready', callback));
    }
  }

  class SmarttiAdapter {

    constructor() {
      this.requiredCookies = ['chat'];
      this.bot = true;
      this.persist = true;
    }

    async getChatExtension() {
      return await new Promise(resolve => {
        let checkChatExtension = setInterval(()=> {
          if (typeof Smartti != 'undefined') {
            resolve(Smartti);
            clearInterval(checkChatExtension);
          }
        }, 100);
      });
    }

    open(callback) {
      // send open command
      this.getChatExtension().then((ext) => {
        ext.open();
        ext.show();
        callback();
      });
    }

    onClosed(callback) {
      // subscribe to closed event
      this.getChatExtension().then((ext) => {
        ext.on('close', ()=> {
          callback();
          ext.hide();
        });
        ext.on('minimize', ()=> {
          callback();
          ext.hide();
        });
      });
    }

    onLoaded(callback) {
      // subscribe to ready event
      this.getChatExtension().then((ext) => {
        callback();
      });

    }
  }

  class WatsonAdapter {

    constructor() {
      this.requiredCookies = ['chat'];
      this.bot = false;
      this.persist = true;
    }

    async getChatExtension() {
      return await new Promise(resolve => {
        let checkChatExtension = setInterval(()=> {
          if (typeof acaWidget != 'undefined') {
            resolve(acaWidget);
            clearInterval(checkChatExtension);
          }
        }, 100);
      });
    }

    open(callback) {
      // send open command
      this.getChatExtension().then((ext) => {
        ext.__open();
        callback();
      });
    }

    onClosed(callback) {
      // subscribe to closed event
      this.getChatExtension().then((ext) => {
        let findbutton = setInterval(()=> {
          const acabutton = document.getElementById('aca--widget-button');
          if (typeof acabutton != 'null') {
            const style = window.getComputedStyle(acabutton);
            if (style.display !== 'none') {
              ext.__hideStartButton();
              callback();
              clearInterval(findbutton);
            }
          }
        }, 1000);
      });
    }

    onLoaded(callback) {
      // subscribe to ready event
      this.getChatExtension().then((ext) => {

        let findbutton = setInterval(()=> {
          const acabutton = document.getElementById('aca--widget-button');
          if (typeof acabutton != 'null') {
            const style = window.getComputedStyle(acabutton);
            if (style.display !== 'none') {
              ext.__hideStartButton();
              callback();
              clearInterval(findbutton);
            }

          }
        }, 1000);
      });

    }
  }

  class KuuraAdapter {

    constructor() {
      this.requiredCookies = ['chat'];
      this.bot = false;
      this.persist = false;
    }

    async getChatExtension() {
      return await new Promise(resolve => {
        let findKuura = setInterval(()=> {
          const kuuracontainer = document.getElementsByClassName('kuura-widget-container')[0];
          if (typeof kuuracontainer != 'undefined') {
            console.log('kuura extension found');
            resolve(kuuracontainer);
            clearInterval(findKuura);
          }
        }, 100);
      });
    }

    open(callback) {
      // send open command
      this.getChatExtension().then((ext) => {
        let findButton = setInterval(()=> {
          const kuurabutton = ext.getElementsByClassName('kuura-chat-toggle')[0];
          if (typeof kuurabutton != 'undefined') {
              kuurabutton.click();
              console.log('kuura open command');
              callback();
              clearInterval(findButton);
          }
        }, 100);
      });
    }

    onClosed(callback) {
      // subscribe to closed event
      this.getChatExtension().then((ext) => {
        console.log('kuura on closed event setup');
        let findButton = setInterval(()=> {
          const kuurabutton = ext.getElementsByClassName('kuura-chat-toggle')[0];
          if (typeof kuurabutton != 'undefined') {
            if (kuurabutton.classList.contains('closed-chat')) {
              console.log('kuura on closed event triggered');
              callback();
              clearInterval(findButton);
            }
          }
        }, 1000);
      });
    }

    onLoaded(callback) {
      // subscribe to ready event
      this.getChatExtension().then((ext) => {
        console.log('setting up on loaded interval');
        let findbutton = setInterval(() => {
          const kuurabutton = ext.getElementsByClassName('kuura-chat-toggle')[0];
          if (typeof kuurabutton != 'undefined') {
            console.log('kuura on loaded event');
            callback();
            clearInterval(findbutton);
          }
        }, 100);
      });
    }

  }

  class Leijuke {

    constructor(leijukeData, extCookieManager, chatAdapter) {

      this.extCookieManager = extCookieManager;
      this.adapter = chatAdapter;

      this.static = {
        selector: `chat-leijuke-${leijukeData.name}`,
        chatSelection: leijukeData.name,
        cookieName: `leijuke.${leijukeData.name}.isOpen`,
        modulePath: leijukeData.modulepath,
        libraries: leijukeData.libraries,
        title: leijukeData.title
      }

      this.state = {
        cookies: extCookieManager.cookieCheck(this.adapter.requiredCookies),
        chatLoaded: false,
        isChatOpen: this.isChatOpen(),
        busy: false
      };

      if (this.state.cookies) {
        this.loadChat();
      }

      this.initWrapper();
      this.render();
    }

    prepButton(button) {

      button.addEventListener('click', (event) => {

        // Debounce button.
        if (this.state.busy) {
          return;
        }
        this.state = {
          ...this.state,
          busy: true,
        };

        // If chat was loaded, cookies are ok.
        if (this.state.chatLoaded) {
          this.openChat();
          return;
        }

        if (!this.state.cookies) {
          // Implicitly allow chat cookies if clicking Leijuke.
          console.log('Chat cookies allowed implicitly and chat being loaded.');

          this.extCookieManager.cookieSet();
        }

        this.state = {
          ...this.state,
          cookies: this.extCookieManager.cookieCheck(this.adapter.requiredCookies)
        };

        if (this.state.cookies) {
          this.loadChat();
          this.adapter.onLoaded(this.openChat.bind(this));
        } else {
          console.warn('Missing the required cookies to open chat. Missing cookie not allowed to be set implicitly.')
        }

      });
    }

    setLeijukeCookie(cname, cvalue) {
      document.cookie = `${cname}=${cvalue}; path=/; SameSite=Strict; `;
    }

    getLeijukeCookie(cname) {
      var name = cname + "=";
      var ca = document.cookie.split(";");
      for (var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) == " ") {
          c = c.substring(1);
        }
        if (c.indexOf(name) == 0) {
          return c.substring(name.length, c.length);
        }
      }
      return "";
    }

    openChat() {
      const leijuke = this;
      // Try to open a chat via adapter.
      this.adapter.open((e) => {
        if(leijuke.adapter.persist) {
          leijuke.setLeijukeCookie(leijuke.static.cookieName, true);
        }
        leijuke.state = {
          ...leijuke.state,
          isChatOpen: true,
          busy: false,
        };
        leijuke.render();
        leijuke.adapter.onClosed(leijuke.closeChat.bind(leijuke));
      });
    }

    loadChat() {
      const { modulePath, libraries } = this.static;
      libraries.js.map((script) => {

        let chatScript = document.createElement('script');
        chatScript.src = script.ext ? script.url : `/${modulePath}/${script.url}`;
        chatScript.type = "text/javascript";

        if (script.onload) {
          chatScript.setAttribute('onload', script.onload);
        }

        if (script.async) {
          chatScript.setAttribute('async', '');
        }

        if (script.dataContainerId) {
          chatScript.setAttribute('data-container-id', script.data_container_id);
        }

        // Insert chatScript into head
        let head = document.querySelector('head');
        head.appendChild(chatScript);
      });

      if (libraries.hasOwnProperty('css')) {
        libraries.css.map((script) => {
          // Create new link Element for loading css
          let css = document.createElement('link');
          css.rel = 'stylesheet';
          css.href = script.ext ? script.url : `/${modulePath}/${script.url}`;

          // Insert chatScript into head
          let head = document.querySelector('head');
          head.append(css);
        });
      }

      this.adapter.onLoaded(this.loaded.bind(this));
    }

    loaded() {
      this.state = {
        ...this.state,
        chatLoaded: true
      };
      this.render();
    }

    closeChat() {
      if(this.adapter.persist) {
        this.setLeijukeCookie(this.static.cookieName, false);
      }
      this.state = {
        ...this.state,
        isChatOpen: false
      };
      this.render();
    }

    isChatOpen() {
      if (this.getLeijukeCookie(this.static.cookieName) == "true") {
        this.adapter.onClosed(this.closeChat.bind(this));
        return true;
      }
      return false;
    }

    initWrapper() {
      let leijukeWrapper = document.getElementById('chat-leijuke-wrapper');
      if (!leijukeWrapper) {
        leijukeWrapper = document.createElement('aside');
        leijukeWrapper.id = 'chat-leijuke-wrapper';
        document.body.append(leijukeWrapper)
      }

      let leijukeTitle = document.createElement('h2');
      leijukeTitle.classList.add('visually-hidden');
      leijukeTitle.innerHTML = Drupal.t('Chat', {}, { context: 'Floating chat title' });
      leijukeWrapper.append(leijukeTitle);
      

      let leijukeInstance = document.createElement('button');
      leijukeInstance.id = this.static.selector;
      leijukeInstance.classList.add('chat-leijuke')
      leijukeWrapper.append(leijukeInstance);

      this.prepButton(leijukeInstance);
    }

    render() {
      const { isChatOpen } = this.state;

      const icon = this.adapter.bot ? 'customer-bot-neutral' : 'speechbubble-text';

      const element = document.getElementById(this.static.selector);

      const innerHTML = `
        <span class="hel-icon hel-icon--${icon}"></span>
        <span>${this.static.title}</span>
        <span class="hel-icon hel-icon--angle-up"></span>
      `;

      if (element.innerHTML != innerHTML) {
        element.innerHTML = innerHTML;
      }

      element.classList.toggle('hidden', isChatOpen);

    }

  }

})(Drupal, drupalSettings);
