(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.chat_leijuke = {
    attach: function (context, settings) {
      const teliaAceData = drupalSettings.telia_ace_data;

      setTimeout(() => {
        // Only load any leijuke once, in case of ajax triggers.
        if (teliaAceData.initialized) {
          console.warn(`Already initialized Telia ACE script!`);
          return;
        }

        const teliaAce = new TeliaAceLeijuke(teliaAceData, new EuCookieManager);
        teliaAce.init();
        drupalSettings.telia_ace_data.initialized = true;
      });
    }
  }

  class EuCookieManager {
    cookieCheck() {
      return Drupal.eu_cookie_compliance.hasAgreedWithCategory('chat');
    }
    cookieSet() {
      if (Drupal.eu_cookie_compliance.hasAgreedWithCategory('chat')) return;
      Drupal.eu_cookie_compliance.setAcceptedCategories([ ...Drupal.eu_cookie_compliance.getAcceptedCategories(), 'chat' ]);
    }
  }

  class TeliaAceLeijuke {
    constructor(teliaAceData, extCookieManager) {
      this.extCookieManager = extCookieManager;
      this.static = {
        chat_id: teliaAceData.chat_id,
        selector: `leijuke_${teliaAceData.chat_id}`,
        chat_title: teliaAceData.chat_title,
        script_url: teliaAceData.script_url,
        script_sri: teliaAceData.script_sri
      }
      this.state = {
        cookies: extCookieManager.cookieCheck(),
        chatLoading: false,
        chatLoaded: false,
        chatOpened: false,
        busy: false
      };
    }

    init() {
      if (this.state.cookies) {
        // Cookies already set so chat assets can be loaded.
        this.loadChat();
      }

      this.initWrapper();
      this.render();
    }

    loadChat() {
      const { script_url, script_sri } = this.static;
      const leijuke = this;
      let chatScript = document.createElement('script');
      chatScript.src = script_url;
      chatScript.type = "text/javascript";
      chatScript.setAttribute('async', '');

      if (script_sri) {
        chatScript.integrity = script_sri;
        chatScript.crossOrigin = 'anonymous';
      }

      chatScript.onload = function() {
        leijuke.loaded();
      }
      chatScript.onerror = function() {
        console.error('Failed to load script ' + script_url);
      };

      // Insert chatScript into head
      let head = document.querySelector('head');
      head.appendChild(chatScript);
      this.state.chatLoading = true;
    }

    loaded() {
      this.state = {
        ...this.state,
        chatLoaded: true,
      };
      this.openChat();
      this.render();
    }

    initWrapper() {
      let teliaAceLeijukeWrapper = document.getElementById('telia-ace-leijuke');
      if (!teliaAceLeijukeWrapper) {
        teliaAceLeijukeWrapper = document.createElement('aside');
        teliaAceLeijukeWrapper.id = 'telia-ace-leijuke';
        document.body.append(teliaAceLeijukeWrapper)
      }

      let teliaAceLeijukeInstance = document.createElement('button');
      teliaAceLeijukeInstance.id = this.static.selector;
      teliaAceLeijukeInstance.classList.add('chat-leijuke')
      teliaAceLeijukeInstance.classList.add('telia-chat-leijuke')

      teliaAceLeijukeWrapper.append(teliaAceLeijukeInstance);

      this.prepButton(teliaAceLeijukeInstance);
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
          this.openChat(true);
          return;
        }

        if (!this.state.cookies) {
          // Implicitly allow chat cookies if clicking Leijuke.
          this.extCookieManager.cookieSet();
        }

        this.state = {
          ...this.state,
          cookies: this.extCookieManager.cookieCheck()
        };

        if (this.state.cookies) {
          this.loadChat();
          this.openChat(true);
        } else {
          console.warn('Missing the required cookies to open chat. Missing cookie not allowed to be set implicitly.')
        }

      });
    }

    openChat(open_widget) {
      const leijuke = this;

      let teliaAceWidgetInitialized = setInterval(() => {
        if(typeof humany !== "undefined" && typeof humany.widgets !== "undefined" && humany.widgets.find(leijuke.static.chat_id)){
          if (open_widget) {
            let myWidget = humany.widgets.find(leijuke.static.chat_id);
            myWidget.activate();
            myWidget.invoke('show');
          }
          clearInterval(teliaAceWidgetInitialized);
          leijuke.state = {
            ...leijuke.state,
            chatLoading: false,
            chatOpened: true,
            busy: false,
          };
          leijuke.render();
        }
      }, 50);
    }

    render() {
      const { chatOpened, chatLoading } = this.state;
      const icon = 'speechbubble-text';
      const label = chatLoading ? Drupal.t('Loading chat...', {}, {context: 'Telia ACE chat'}) : this.static.chat_title;
      const element = document.getElementById(this.static.selector);
      if (!element) {
        return;
      }
      let innerHTML = `
        <span class="hel-icon hel-icon--${icon}"></span>
        <span>${label}</span>
        <span class="hel-icon hel-icon--angle-up"></span>
      `;

      if (element.innerHTML != innerHTML) {
        element.innerHTML = innerHTML;
      }
      element.classList.toggle('loading', chatLoading);
      element.classList.toggle('hidden', chatOpened);
    }

  }

})(Drupal, drupalSettings);
