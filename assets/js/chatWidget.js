((Drupal, drupalSettings) => {
  Drupal.behaviors.chatWidget = {
    attach: function attach() {
      setTimeout(() => {
        if (drupalSettings.chatWidgetData.initialized) {
          return;
        }

        // Make sure the chat is configured properly.
        let chatSettings = {};
        try {
          chatSettings = new ChatSettings(drupalSettings.chatWidgetData ?? {});
        } catch (e) {
          console.error(e);
          return;
        }

        // Initialize the chat.
        new ChatWidget(chatSettings);
        drupalSettings.chatWidgetData.initialized = true;
      });
    },
  };
})(Drupal, drupalSettings);

/**
 * Chat widget.
 *
 * Wrapper for the actual chat implementation.
 *
 * How it works:
 * 1. On page load, only a custom chat button is added to a page.
 * 2. User clicks the custom chat button, perform firstClickCallback().
 * 2.1 Chat cookies are set as accepted.
 * 2.2 Custom button state is changed.
 * 2.3 The chat is loaded dynamically with onload-callback attached to it.
 * 2.4 Wait for onload-callback to trigger.
 * 3. The onload-functionality opens the chat and starts observing
 *    the chat trigger.
 * 4. The custom button is shown and hidden based on the chat trigger state.
 * 5. Clicking the custom button after the chat has loaded will reopen the chat.
 * 6. The close button fades out the custom button, shows a toast and
 *    reveals the footer button that opens the chat.
 */
class ChatWidget {
  constructor(chatSettings) {
    this.static = {
      chatId: chatSettings.chatId,
      selector: `chat-widget-trigger-${chatSettings.chatId}`,
      chatTitle: chatSettings.chatTitle,
      chatIcon: chatSettings.chatIcon,
      scriptUrl: chatSettings.scriptUrl,
      provider: chatSettings.provider,
    };
    this.state = {
      cookies: this.cookieCheck(),
      chatLoading: false,
      chatLoaded: false,
      chatOpened: false,
      chatInitialized: false,
      chatDismissed: false,
      openOnLoad: true,
      busy: false,
    };

    const button = this.createChatButton();
    button.addEventListener('click', this.openCallback);
    this.createDismiss();
    this.render();

    // Restore the ongoing IBM chat session.
    if (this.static.provider === 'ibm' && this.state.cookies && this.hasIbmSession()) {
      this.firstClickCallback(false);
    }
  }

  /**
   * Check if the IBM chat has an ongoing session.
   */
  hasIbmSession = () => {
    try {
      return JSON.parse(localStorage.getItem('aiap-wbc-chat-app-button-state'))?.chatOpened === true;
    } catch (_e) {
      return false;
    }
  };

  /**
   * Create the custom chat button.
   */
  createChatButton = () => {
    let wrapper = document.getElementById('chat-widget');
    if (!wrapper) {
      wrapper = document.createElement('aside');
      wrapper.id = 'chat-widget';
      document.body.append(wrapper);
    }
    wrapper.classList.add('chat-widget');

    // The custom chat-button.
    const button = document.createElement('button');
    button.id = this.static.selector;
    button.type = 'button';
    button.classList.add('chat-widget__trigger');

    wrapper.append(button);
    this.wrapper = wrapper;
    this.customChatButton = button;

    return button;
  };

  /**
   * Create the close button, connect the toast and the footer button.
   */
  createDismiss = () => {
    const toast = document.getElementById('chat-toast');
    const footerChat = document.getElementById('chat-footer');
    const footerButton = document.getElementById('chat-footer-button');
    const footer = document.querySelector('.footer .footer-top');
    if (!toast || !footerChat || !footerButton || !footer) {
      return;
    }

    footer.append(footerChat);
    footerButton.addEventListener('click', () => {
      toast.hidden = true;
      this.openCallback();
      this.clearDismissed();
    });

    const dismissButton = document.createElement('button');
    dismissButton.type = 'button';
    dismissButton.classList.add('chat-widget__dismiss');
    dismissButton.setAttribute('aria-label', Drupal.t('Hide the chat', {}, { context: 'Chat widget' }));
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
    this.footerChat = footerChat;
    this.footerLabel = footerButton.querySelector('.chat-widget__footer-label');

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
   * Remove the closed state of this chat from the session.
   */
  clearDismissed = () => {
    try {
      const { [this.static.chatId]: _removed, ...dismissed } = this.getDismissedChats();
      if (Object.keys(dismissed).length) {
        sessionStorage.setItem('chat-dismissed', JSON.stringify(dismissed));
      } else {
        sessionStorage.removeItem('chat-dismissed');
      }
    } catch (_e) {
      // Ignore unavailable session storage.
    }
  };

  /**
   * Restore the chat button after the chat closes.
   */
  syncDismissed = () => {
    if (!this.state.chatOpened && this.state.chatDismissed) {
      this.state.chatDismissed = this.getDismissedChats()[this.static.chatId] === true;
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
   *
   * @param openOnLoad
   *   Open the chat after it has loaded.
   */
  firstClickCallback = (openOnLoad = true) => {
    if (!this.cookieCheck()) {
      this.cookieSet();
    }
    this.state.chatInitialized = true;
    this.state.openOnLoad = openOnLoad;

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
   *   Open the chat window.
   */
  openChat = (openWidget) => {
    if (this.static.provider === 'ibm') {
      if (openWidget) {
        document.getElementById('chat-app-button')?.click();
      }
      const chatOpened = openWidget || this.isIbmChatOpen();
      this.state = { ...this.state, chatLoading: false, chatOpened, busy: false };
      this.render();
      return;
    }

    this.waitFor(
      () => window.humany?.widgets?.find(this.static.chatId),
      () => {
        if (openWidget) {
          const widget = window.humany.widgets.find(this.static.chatId);
          widget.activate();
          widget.invoke('show');
        }
        this.state = { ...this.state, chatLoading: false, chatOpened: true, busy: false };
        this.render();
      },
    );
  };

  /**
   * Run the callback once the check passes.
   *
   * @param check
   *   The check to run.
   * @param callback
   *   The callback to run.
   */
  waitFor = (check, callback) => {
    let attempts = 0;
    const interval = setInterval(() => {
      if (check()) {
        clearInterval(interval);
        callback();
        return;
      }

      // Stop waiting after ten seconds.
      attempts += 1;
      if (attempts >= 100) {
        clearInterval(interval);
        this.state = { ...this.state, chatLoading: false };
        this.render();
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
      ? Drupal.t('Loading chat...', {}, { context: 'Chat widget' })
      : this.static.chatTitle;

    const element = document.getElementById(this.static.selector);
    if (!element) {
      return;
    }

    const innerHTML = `
      <span class="hel-icon hel-icon--${this.static.chatIcon}" aria-hidden="true"></span>
      <span>${label}</span>
    `;

    if (element.innerHTML !== innerHTML) {
      element.innerHTML = innerHTML;
    }

    // Hide and show the buttons based on this.state.
    element.classList.toggle('loading', this.state.chatLoading);
    this.wrapper.hidden = this.state.chatOpened || this.state.chatDismissed;

    if (this.footerChat) {
      this.footerChat.hidden = !this.state.chatDismissed;
      this.footerLabel.textContent = label;
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
    if (this.static.provider === 'ibm') {
      this.onloadIbm();
      return;
    }

    // Wait for the close button, onload triggers before the chat is ready.
    this.waitFor(
      () => document.querySelector('.humany-trigger .humany-close'),
      () => {
        const humany_widget = document.querySelector('.humany-trigger');
        this.state.chatLoading = false;
        this.state.chatLoaded = true;
        this.state.chatOpened = true;

        // Copy the open state from the Telia chat trigger
        // whenever its classes change.
        new MutationObserver(() => {
          this.state.chatOpened = humany_widget.classList.contains('humany-active');
          this.syncDismissed();
          this.render();
        }).observe(humany_widget, { attributes: true, attributeFilter: ['class'] });

        this.openChat(this.state.openOnLoad);
      },
    );
  };

  /**
   * Check if the IBM chat window is open or minimized.
   */
  isIbmChatOpen = () => {
    const frame = document.getElementById('aca--widget-frame');
    return frame !== null && getComputedStyle(frame).display !== 'none';
  };

  /**
   * Onload-callback for the IBM chat.
   */
  onloadIbm = () => {
    this.waitFor(
      () => document.getElementById('chat-app-button') || this.isIbmChatOpen(),
      () => {
        this.state.chatLoaded = true;

        // Show the custom button only when the IBM chat window is closed.
        window.addEventListener('message', (event) => {
          if (event.origin !== window.location.origin || event.data?.type !== 'aiapChatButtonShow') {
            return;
          }
          this.state.chatOpened = !event.data.data;
          this.syncDismissed();
          this.render();
        });

        this.openChat(this.state.openOnLoad && !this.isIbmChatOpen());
      },
    );
  };
}

/**
 * Chat settings class.
 *
 * Check that all required settings exist.
 */
class ChatSettings {
  constructor(settings) {
    const requiredSettings = ['chatTitle', 'chatId', 'scriptUrl'];

    // Check that the required settings exist.
    requiredSettings.forEach((value) => {
      if (!Object.hasOwn(settings, value) || !settings[value]) {
        throw new Error(`Missing expected chat setting ${value}`);
      }
    });

    this.chatTitle = settings.chatTitle;
    this.chatId = settings.chatId;
    this.scriptUrl = settings.scriptUrl;
    this.chatIcon = settings.chatIcon ?? 'speechbubble-text';
    this.provider = settings.provider ?? 'telia';
  }
}
