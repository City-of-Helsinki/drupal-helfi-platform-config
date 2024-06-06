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

        new TeliaAceLeijuke(teliaAceData, new EuCookieManager);
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
        cookieName: `leijuke.${teliaAceData.chat_id}.isOpen`,
        chat_title: teliaAceData.chat_title,
        script_url: teliaAceData.script_url,
        script_sri: teliaAceData.script_sri
      }
      this.state = {
        cookies: extCookieManager.cookieCheck(),
        chatLoaded: false,
        isChatOpen: this.isChatOpen(),
        busy: false
      };

      if (this.state.cookies) {
        console.log('Cookies already set, loading chat.')
        this.loadChat();
      }

      this.initWrapper();
      this.render();
    }

    isChatOpen() {
      if (this.getLeijukeCookie(this.static.cookieName) == "true") {
        return true;
      }
      return false;
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

    loadChat() {
      const { script_url, script_sri } = this.static;
      let chatScript = document.createElement('script');
      chatScript.src = script_url;
      chatScript.type = "text/javascript";
      chatScript.setAttribute('async', '');

      if (script_sri) {
        chatScript.integrity = script_sri;
        chatScript.crossOrigin = 'anonymous';
      }

      chatScript.onload = function() {
        console.log('SCRIPT LOADED!!!');
      }
      chatScript.onerror = function() {
        console.error('Failed to load script ' + script_url);
      };

      // Insert chatScript into head
      let head = document.querySelector('head');
      head.appendChild(chatScript);

      this.loaded();
    }

    loaded() {
      this.state = {
        ...this.state,
        chatLoaded: true
      };
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
          console.log('Chat cookies allowed implicitly and chat being loaded.');

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

      //leijuke.setLeijukeCookie(leijuke.static.cookieName, true);

      let teliaAceWidgetInitialized = setInterval(() => {
        if(typeof humany !== "undefined" && typeof humany.widgets !== "undefined"){
          let myWidget = humany.widgets.find(leijuke.static.chat_id);
          if (open_widget) {
            myWidget.activate();
            myWidget.invoke('show');
          }
          clearInterval(teliaAceWidgetInitialized);
          leijuke.state = {
            ...leijuke.state,
            isChatOpen: true,
            busy: false,
          };
          leijuke.render();
        }
      }, 50);
    }

    render() {
      const { chatLoaded } = this.state;
      const icon = 'speechbubble-text';
      const element = document.getElementById(this.static.selector);
      if (!element) {
        console.log('Element not found');
        return;
      }
      const innerHTML = `
        <span class="hel-icon hel-icon--${icon}"></span>
        <span>${this.static.chat_title}</span>
        <span class="hel-icon hel-icon--angle-up"></span>
      `;

      if (element.innerHTML != innerHTML) {
        element.innerHTML = innerHTML;
      }

      element.classList.toggle('hidden', chatLoaded);
    }

  }

})(Drupal, drupalSettings);
