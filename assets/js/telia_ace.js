// eslint-disable-next-line func-names
(function (Drupal, drupalSettings) {
  Drupal.behaviors.telia_ace = {
    attach: function attach() {
      setTimeout(() => {
        if (drupalSettings.telia_ace_data.initialized) {
          return;
        }

        let chatSettings = {};
        try {
          chatSettings = new ChatSettings(drupalSettings.telia_ace_data ?? {});
        }
        catch (e) {
          console.error(e);
          return;
        }

        new TeliaAceWidget(chatSettings);
        drupalSettings.telia_ace_data.initialized = true;
      });
    }
  };
})(Drupal, drupalSettings);

class TeliaAceWidget {
  constructor(chatSettings) {
    this.static = {
      chatId: chatSettings.chat_id,
      selector: `leijuke_${chatSettings.chat_id}`,
      chatTitle: chatSettings.chat_title,
      scriptUrl: chatSettings.script_url,
    };
    this.state = {
      cookies: this.cookieCheck(),
      chatLoading: false,
      chatLoaded: false,
      chatOpened: false,
      chatInitialized: false,
      busy: false
    };
    this.init();
  }

  /**
   * Initialize the chat libraries and elements.
   */
  init = () => {
    this.widgetChatButton = this.createChatWidget();
    this.addOpenEventlistener();
    this.render();
  }

  /**
   * Set up the chat button elements.
   */
  createChatWidget = () => {
    let teliaAceWidgetWrapper = document.getElementById('telia-ace-leijuke');
    if (!teliaAceWidgetWrapper) {
      teliaAceWidgetWrapper = document.createElement('aside');
      teliaAceWidgetWrapper.id = 'telia-ace-leijuke';
      document.body.append(teliaAceWidgetWrapper);
    }

    const teliaAceWidgetInstance = document.createElement('button');
    teliaAceWidgetInstance.id = this.static.selector;
    teliaAceWidgetInstance.classList.add('chat-leijuke');
    teliaAceWidgetInstance.classList.add('telia-chat-leijuke');

    teliaAceWidgetWrapper.append(teliaAceWidgetInstance);

    return teliaAceWidgetInstance;
  }

  /**
   * Adds self-removing eventlistener to the chat button.
   *
   * Automatically accept the chat cookies and load the chat script
   * when user clicks the chat button. Then open the chat on "onloaad".
   *
   * @param chatButton
   */
  addOpenEventlistener = () => {
    this.widgetChatButton.addEventListener('click', () => {
      if (!this.cookieCheck()) {
        this.cookieSet();
      }
      !this.state.chatInitialized && this.loadChatScript();

      this.openChat(true);
      this.addCloseEventlistener();
    }, { once: true });
  }

  /**
   * A self-removing close-eventlistener to the chat button.
   *
   * There are actually 2 chat buttons. One delivered by the chat
   * and one created by this script. The one delivered by the chat
   * is not gdpr-compliant. Therefore we must make sure the correct
   * button is visible at correct time.
   */
  addCloseEventlistener = () => {
    const interval = setInterval(()=> {
      // Make sure the chat-implementation has given us the close button.
      const humany_widget = document.getElementsByClassName('humany-trigger')[0];
      let close = null;
      humany_widget && (close = humany_widget.getElementsByClassName('humany-close')[0]);

      if (close) {
        // Add an eventlistener to the close-button which makes our "open chat" -button visible on click.
        close.addEventListener('click', ()=> {
          document.getElementsByClassName('telia-chat-leijuke')[0].classList.remove('hidden');
        }, { once: true });

        // Readd the eventlistener to the "open chat" -button.
        this.addOpenEventlistener()
        clearInterval(interval);
      }
    }, 100)
  }

  /**
   * Load the chat script.
   */
  loadChatScript = () => {
    const chatScript = document.createElement('script');
    chatScript.src = this.static.scriptUrl;
    chatScript.type = 'text/javascript';
    chatScript.setAttribute('async', '');

    chatScript.onload = this.loaded;

    const head = document.querySelector('head');
    head.appendChild(chatScript);
    this.state.chatLoading = true;
    this.state.chatInitialized = true;
  }

  /**
   * Render the chat element.
   */
  render = () => {
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

  cookieCheck = () => {
    return Drupal.cookieConsent.getConsentStatus(['chat']);
  }

  cookieSet = () => {
    if (Drupal.cookieConsent.getConsentStatus(['chat'])) return;
    Drupal.cookieConsent.setAcceptedCategories(['chat']);
  }

  /**
   * Onload callback for the chat script.
   */
  loaded = () => {
    this.state = {
      ...this.state,
      chatLoaded: true,
    };
    this.openChat();
    this.render();
  }

  /**
   * Call the humany activation method if chat is loaded.
   *
   * @param openWidget
   *   To open the chat session or not.
   */
  openChat = (openWidget) => {
    const teliaAceWidgetInitialized = setInterval(() => {
      if(typeof window.humany !== 'undefined' && typeof window.humany.widgets !== 'undefined' && window.humany.widgets.find(this.static.chatId)){
        if (openWidget) {
          const myWidget = window.humany.widgets.find(this.static.chatId);
          myWidget.activate();
          myWidget.invoke('show');
        }
        clearInterval(teliaAceWidgetInitialized);
        this.state = {
          ...this.state,
          chatLoading: false,
          chatOpened: true,
          busy: false,
        };
        this.render();
      }
    }, 50);
  }

}

class ChatSettings {
  constructor(settings) {
    const requiredSettings = [
      'chat_title',
      'chat_id',
      'script_url'
    ];

    // Check that the required settings exist.
    requiredSettings.forEach(value => {
      if (!settings.hasOwnProperty(value) || !settings[value]) {
        throw new Error(`Missing expected ace chat setting ${value}`);
      }
    });

    this.chat_title = settings.chat_title
    this.chat_id = settings.chat_id
    this.script_url = settings.script_url
  }
}
