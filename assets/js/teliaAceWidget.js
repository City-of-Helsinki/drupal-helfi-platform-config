((Drupal, drupalSettings) => {
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
        } catch (e) {
          console.error(e);
          return;
        }

        // Initialize the chat.
        new TeliaAceWidget(chatSettings);
        drupalSettings.telia_ace_data.initialized = true;
      });
    },
  };
})(Drupal, drupalSettings);

/**
 * Telia ace widget.
 *
 * Wrapper for the actual telia ace chat implementation.
 *
 * How it works:
 * 1. On page load, only a custom chat button is added to a page.
 * 2. User clicks the custom chat button, perform firstClickCallback().
 * 2.1 Chat cookies are set as accepted.
 * 2.2 Custom button state is changed.
 * 2.3 The ACE-chat is loaded dynamically with onload-callback attached to it.
 * 2.4 Wait for onload-callback to trigger.
 * 3. The onload-functionality opens the chat and starts observing
 *    the chat trigger.
 * 4. The custom button is shown and hidden based on the chat trigger state.
 * 5. Clicking the custom button after the chat has loaded will reopen the chat.
 * 6. The close button fades out the custom button, shows a toast and
 *    reveals the footer button that opens the chat.
 */
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
      chatDismissed: false,
      busy: false,
    };

    const button = this.createChatButton();
    button.addEventListener('click', this.openCallback);
    this.createDismiss();
    this.render();
  }

  /**
   * Create the custom chat button.
   */
  createChatButton = () => {
    let wrapper = document.getElementById('telia-ace-leijuke');
    if (!wrapper) {
      wrapper = document.createElement('aside');
      wrapper.id = 'telia-ace-leijuke';
      document.body.append(wrapper);
    }
    wrapper.classList.add('chat-widget');

    // The custom chat-button.
    const button = document.createElement('button');
    button.id = this.static.selector;
    button.type = 'button';
    button.classList.add('chat-widget__open');

    wrapper.append(button);
    this.wrapper = wrapper;
    this.customChatButton = button;

    return button;
  };

  /**
   * Create the close button, connect the toast and the footer button.
   */
  createDismiss = () => {
    const toast = document.getElementById('telia-ace-toast');
    const footerButton = document.getElementById('telia-ace-footer-button');
    const footer = document.querySelector('.footer .footer-top');
    if (!toast || !footerButton || !footer) {
      return;
    }

    footer.append(footerButton);
    footerButton.addEventListener('click', () => {
      toast.hidden = true;
      this.openCallback();
    });

    const dismissButton = document.createElement('button');
    dismissButton.type = 'button';
    dismissButton.classList.add('chat-widget__dismiss');
    dismissButton.setAttribute('aria-label', Drupal.t('Hide the chat', {}, { context: 'Telia ACE chat' }));
    dismissButton.innerHTML = '<span class="hel-icon hel-icon--cross" aria-hidden="true"></span>';
    dismissButton.addEventListener('click', this.dismissCallback);
    this.customChatButton.after(dismissButton);

    toast.setAttribute('tabindex', '-1');
    toast.querySelector('.toast__close').addEventListener('click', this.closeToast);
    toast.querySelector('.toast__link')?.addEventListener('click', (event) => {
      event.preventDefault();
      footerButton.focus();
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !toast.hidden) {
        this.closeToast();
      }
    });

    this.toast = toast;
    this.footerButton = footerButton;

    // Restore the closed state of this chat for the session.
    this.state.chatDismissed = this.getDismissedChats()[this.static.chatId] === true;
  };

  /**
   * Get the closed chats of the session.
   */
  getDismissedChats = () => {
    try {
      return JSON.parse(sessionStorage.getItem('chat-dismissed')) ?? {};
    } catch (_e) {
      return {};
    }
  };

  /**
   * Open the chat, or load it on the first click.
   */
  openCallback = () => {
    if (this.state.chatLoaded) {
      this.openChat(true);
      return;
    }

    if (!this.state.chatInitialized) {
      this.firstClickCallback();
    }
  };

  /**
   * Fade out the button and show the toast.
   */
  dismissCallback = () => {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    this.wrapper.animate([{ opacity: 1 }, { opacity: 0 }], { duration: reducedMotion ? 0 : 300 }).finished.then(() => {
      this.state.chatDismissed = true;
      try {
        sessionStorage.setItem(
          'chat-dismissed',
          JSON.stringify({ ...this.getDismissedChats(), [this.static.chatId]: true }),
        );
      } catch (_e) {
        // Ignore unavailable session storage.
      }
      this.render();
      this.toast.hidden = false;
      this.toast.focus();
    });
  };

  /**
   * Hide the toast.
   */
  closeToast = () => {
    this.toast.hidden = true;

    // Move focus to the start of the page.
    document.body.setAttribute('tabindex', '-1');
    document.body.addEventListener('blur', () => document.body.removeAttribute('tabindex'), { once: true });
    document.body.focus({ preventScroll: true });
  };

  /**
   * User clicks chat button for the first time.
   *
   * Handle the cookies and load the third party js-script.
   */
  firstClickCallback = () => {
    if (!this.cookieCheck()) {
      this.cookieSet();
    }
    this.state.chatInitialized = true;

    const chatScript = document.createElement('script');
    chatScript.src = this.static.scriptUrl;
    chatScript.type = 'text/javascript';
    chatScript.setAttribute('async', '');
    chatScript.onload = this.onload;

    const head = document.querySelector('head');
    head.appendChild(chatScript);

    this.state.chatLoading = true;
    this.render();
  };

  /**
   * Open the chat after the chat has been loaded.
   *
   * @param openWidget
   */
  openChat = (openWidget) => {
    const widgetInitialized = setInterval(() => {
      if (
        typeof window.humany !== 'undefined' &&
        typeof window.humany.widgets !== 'undefined' &&
        window.humany.widgets.find(this.static.chatId)
      ) {
        if (openWidget) {
          const widget = window.humany.widgets.find(this.static.chatId);
          widget.activate();
          widget.invoke('show');
        }
        this.state = { ...this.state, chatLoading: false, chatOpened: true, busy: false };
        this.render();
        clearInterval(widgetInitialized);
      }
    }, 100);
  };

  /**
   * Render and rerender the chat element.
   *
   * Render is called every time the widget state changes.
   * It changes the button texts and visibility.
   */
  render = () => {
    const label = this.state.chatLoading
      ? Drupal.t('Loading chat...', {}, { context: 'Telia ACE chat' })
      : this.static.chatTitle;

    const element = document.getElementById(this.static.selector);
    if (!element) {
      return;
    }

    const innerHTML = `
      <span class="hel-icon hel-icon--customer-bot-neutral" aria-hidden="true"></span>
      <span>${label}</span>
    `;

    if (element.innerHTML !== innerHTML) {
      element.innerHTML = innerHTML;
    }

    // Hide and show the buttons based on this.state.
    element.classList.toggle('loading', this.state.chatLoading);
    this.wrapper.hidden = this.state.chatOpened || this.state.chatDismissed;

    if (this.footerButton) {
      this.footerButton.hidden = !this.state.chatDismissed;
    }
  };

  /**
   * Check if cookies has been accepted.
   */
  cookieCheck = () => {
    return Drupal.cookieConsent.getConsentStatus(['chat']);
  };

  /**
   * Set the chat acceptance if chat-button is clicked.
   */
  cookieSet = () => {
    if (Drupal.cookieConsent.getConsentStatus(['chat'])) return;
    Drupal.cookieConsent.setAcceptedCategories(['chat']);
  };

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
        this.state.chatLoading = false;
        this.state.chatLoaded = true;
        this.state.chatOpened = true;

        // Copy the open state from the Telia chat trigger
        // whenever its classes change.
        new MutationObserver(() => {
          this.state.chatOpened = humany_widget.classList.contains('humany-active');
          this.render();
        }).observe(humany_widget, { attributes: true, attributeFilter: ['class'] });

        this.openChat(true);
        clearInterval(loaded);
      }
    }, 100);
  };
}

/**
 * Chat settings class.
 *
 * Check that all required settings exist.
 */
class ChatSettings {
  constructor(settings) {
    const requiredSettings = ['chat_title', 'chat_id', 'script_url'];

    // Check that the required settings exist.
    requiredSettings.forEach((value) => {
      if (!Object.hasOwn(settings, value) || !settings[value]) {
        throw new Error(`Missing expected ace chat setting ${value}`);
      }
    });

    this.chat_title = settings.chat_title;
    this.chat_id = settings.chat_id;
    this.script_url = settings.script_url;
  }
}
