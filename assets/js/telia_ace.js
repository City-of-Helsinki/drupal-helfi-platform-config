// eslint-disable-next-line func-names
(function (Drupal, drupalSettings) {
  class TeliaAceLeijuke {
    constructor(teliaAceData) {
      this.static = {
        chatId: teliaAceData.chat_id,
        selector: `leijuke_${teliaAceData.chat_id}`,
        chatTitle: teliaAceData.chat_title,
        scriptUrl: teliaAceData.script_url,
        scriptSri: teliaAceData.script_sri
      };
      this.state = {
        cookies: this.cookieCheck(),
        chatLoading: false,
        chatLoaded: false,
        chatOpened: false,
        busy: false
      };
    }

    // eslint-disable-next-line class-methods-use-this
    cookieCheck() {
      return Drupal.eu_cookie_compliance.hasAgreedWithCategory('chat');
    }

    // eslint-disable-next-line class-methods-use-this
    cookieSet() {
      if (Drupal.eu_cookie_compliance.hasAgreedWithCategory('chat')) return;
      Drupal.eu_cookie_compliance.setAcceptedCategories([ ...Drupal.eu_cookie_compliance.getAcceptedCategories(), 'chat' ]);
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
      const { scriptUrl, scriptSri } = this.static;
      const leijuke = this;
      const chatScript = document.createElement('script');
      chatScript.src = scriptUrl;
      chatScript.type = 'text/javascript';
      chatScript.setAttribute('async', '');

      if (scriptSri) {
        chatScript.integrity = scriptSri;
        chatScript.crossOrigin = 'anonymous';
      }

      // eslint-disable-next-line func-names
      chatScript.onload = function() {
        leijuke.loaded();
      };

      // eslint-disable-next-line func-names
      chatScript.onerror = function() {
        // console.error('Failed to load script ' + scriptUrl);
      };

      // Insert chatScript into head
      const head = document.querySelector('head');
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
        document.body.append(teliaAceLeijukeWrapper);
      }

      const teliaAceLeijukeInstance = document.createElement('button');
      teliaAceLeijukeInstance.id = this.static.selector;
      teliaAceLeijukeInstance.classList.add('chat-leijuke');
      teliaAceLeijukeInstance.classList.add('telia-chat-leijuke');

      teliaAceLeijukeWrapper.append(teliaAceLeijukeInstance);

      this.prepButton(teliaAceLeijukeInstance);
    }

    prepButton(button) {

      button.addEventListener('click', () => {

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
          this.cookieSet();
        }

        this.state = {
          ...this.state,
          cookies: this.cookieCheck()
        };

        if (this.state.cookies) {
          this.loadChat();
          this.openChat(true);
        } else {
          // console.warn('Missing the required cookies to open chat. Missing cookie not allowed to be set implicitly.')
        }

      });
    }

    openChat(openWidget) {
      const leijuke = this;

      const teliaAceWidgetInitialized = setInterval(() => {
        if(typeof window.humany !== 'undefined' && typeof window.humany.widgets !== 'undefined' && window.humany.widgets.find(leijuke.static.chatId)){
          if (openWidget) {
            const myWidget = window.humany.widgets.find(leijuke.static.chatId);
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
        // Interval is set to 50 milliseconds here.
        // This doesn't poll the variables too often but should seem quick to users.
      }, 50);
    }

    render() {
      const { chatOpened, chatLoading } = this.state;
      const label = chatLoading ? Drupal.t('Loading chat...', {}, {context: 'Telia ACE chat'}) : this.static.chatTitle;
      const element = document.getElementById(this.static.selector);
      if (!element) {
        return;
      }
      const innerHTML = `
        <span class="hel-icon hel-icon--speechbubble-text"></span>
        <span>${label}</span>
        <span class="hel-icon hel-icon--angle-up"></span>
      `;

      if (element.innerHTML !== innerHTML) {
        element.innerHTML = innerHTML;
      }
      element.classList.toggle('loading', chatLoading);
      element.classList.toggle('hidden', chatOpened);
    }
  }


  Drupal.behaviors.chat_leijuke = {
    attach: function attach() {
      const teliaAceData = drupalSettings.telia_ace_data;

      setTimeout(() => {
        // Only load any leijuke once, in case of ajax triggers.
        if (teliaAceData.initialized) {
          // console.warn(`Already initialized Telia ACE script!`);
          return;
        }

        const teliaAce = new TeliaAceLeijuke(teliaAceData);
        teliaAce.init();
        drupalSettings.telia_ace_data.initialized = true;
      });
    }
  };
})(Drupal, drupalSettings);
