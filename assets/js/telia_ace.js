// eslint-disable-next-line func-names
(function (Drupal, drupalSettings) {
  Drupal.behaviors.telia_ace = {
    attach: function attach() {
      setTimeout(() => {
        if (drupalSettings.telia_ace_data.initialized) {
          return;
        }

        // Make sure the chat is configured properly.
        let chatSettings = {};
        try {
          chatSettings = new ChatSettings(drupalSettings.telia_ace_data ?? {});
        }
        catch (e) {
          console.error(e);
          return;
        }

        // Initialize the chat.
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

    this.createChatButton();

    this.initialize()

    this.render();
  }

  /**
   * Create the open button.
   */
  createChatButton = () => {
    // Wrapper
    let wrapper = document.getElementById('telia-ace-leijuke');
    if (!wrapper) {
      wrapper = document.createElement('aside');
      wrapper.id = 'telia-ace-leijuke';
      document.body.append(wrapper);
    }

    // The custom chat-button.
    const button = document.createElement('button');
    button.id = this.static.selector;
    button.classList.add('chat-leijuke');
    button.classList.add('telia-chat-leijuke');

    wrapper.append(button);
    this.customChatButton = button;
  }

  /**
   * Check cookies, add event listener.
   */
  initialize = () => {
    if (!this.cookieCheck()) {
      this.cookieSet();
    }

    this.addOpenEventListener()

    this.render();
  }

  /**
   * "Open" -chat button functionality.
   *
   * Self removing event listener, no need for debounce-code since
   * it can only be triggered once. It is added back when
   * "close" -button is pressed
   *
   * The very first click also adds the chat script to DOM.
   */
  addOpenEventListener = () => {
    this.customChatButton.addEventListener('click', () => {
      // Only on first click, the chat script cannot be loaded earlier.
      if (!this.state.chatInitialized) {
        this.state.chatInitialized = true;
        this.addChatScript();
        this.state.chatLoading = true;

        this.render();
        return;
      }

      // second "open" -click only needs to open the chat.
      this.state.chatOpened = true;
      this.openChat(true);
      this.render();
    }, {once: true});
  }

  /**
   * "Close" -chat button functionality.
   *
   * Uses the actual ACE-close button.
   */
  addCloseEventListener = () => {
    this.closeButton.addEventListener('click', () => {
      this.state.chatOpened = false;
      // readd the self-removing event listener.
      this.addOpenEventListener();
      this.render();
    });
  }

  /**
   * Open the chat after the chat has been loaded.
   *
   * @param openWidget
   */
  openChat = (openWidget) => {
    const widgetInitialized = setInterval(() => {
      if(typeof window.humany !== 'undefined' && typeof window.humany.widgets !== 'undefined' && window.humany.widgets.find(this.static.chatId)){
        if (openWidget) {
          const widget = window.humany.widgets.find(this.static.chatId);
          widget.activate();
          widget.invoke('show');
        }
        this.state = {
          ...this.state,
          chatLoading: false,
          chatOpened: true,
          busy: false,
        };
        this.render();
        clearInterval(widgetInitialized);
      }

    }, 100);
  }

  /**
   * Load the chat script by adding it to DOM.
   */
  addChatScript = () => {
    const chatScript = document.createElement('script');
    chatScript.src = this.static.scriptUrl;
    chatScript.type = 'text/javascript';
    chatScript.setAttribute('async', '');

    chatScript.onload = this.onload;

    const head = document.querySelector('head');
    head.appendChild(chatScript);
  }

  /**
   * Render and rerender the chat element.
   *
   * Render is called every time the widget state changes.
   * It changes the button texts and visibility.
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

    // Hide and show the button based on this.state
    element.classList.toggle('loading', chatLoading);
    element.classList.toggle('hidden', chatOpened);
  }

  /**
   * Check if cookies has been accepted.
   */
  cookieCheck = () => {
    return Drupal.cookieConsent.getConsentStatus(['chat']);
  }

  /**
   * Set the chat acceptance if chat-button is clicked.
   */
  cookieSet = () => {
    if (Drupal.cookieConsent.getConsentStatus(['chat'])) return;
    Drupal.cookieConsent.setAcceptedCategories(['chat']);
  }

  /**
   * Onload-callback.
   *
   * This is triggered after the third party library has been loaded.
   */
  onload = () => {
    // Interval must be set because onload triggers too soon.
    // This caused the chat not to open when cache was disabled.
    const loaded = setInterval(() => {
      // The close button seems to be the best way to figure out
      // if the chat has actually ready to be used.
      const humany_widget = document.getElementsByClassName('humany-trigger')[0];
      let close = null;
      if (!humany_widget) {
        return;
      }
      close = humany_widget.getElementsByClassName('humany-close')[0];

      if (humany_widget && close) {
        this.closeButton = close;
        this.state.chatLoading = false;
        this.state.chatLoaded = true;
        this.state.chatOpened = true;

        this.addCloseEventListener()
        this.openChat(true);
        clearInterval(loaded);
      }
    }, 50);
  }

}

/**
 * Chat settings class.
 *
 * Check that all required settings exist.
 */
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
