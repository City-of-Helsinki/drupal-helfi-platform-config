(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.chat_leijuke = {
    attach: function (context, settings) {

      const leijukeData = drupalSettings.leijuke_data;
      console.log({leijukeData});

      for (const chat_selection in leijukeData) {
        const adapter = getAdapter(chat_selection);

        setTimeout(() => {
          new Leijuke(leijukeData[chat_selection], new EuCookieManager, adapter);
        });
      }
    }
  }
})(Drupal, drupalSettings);

function getAdapter(chatSelection) {
  if (chatSelection.indexOf('genesys') != -1) {
    return new GenesysAdapter;
  }
  if (chatSelection.indexOf('smartti') != -1) {
    return new SmarttiAdapter;
  }
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
  }

  async getChatExtension() {
    return await new Promise(resolve => {
      let checkChatExtension = setInterval(()=> {
        if (typeof chatExtension != 'undefined') {
          console.log('Chat extension resolution ready!');
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

  close(callback) {
    // send close command
    this.getChatExtension().then((ext) => chatExtension.command('WebChat.close').done(callback).fail(console.warn('Failed WebChat close command.')));
  }

  onOpened(callback) {
    // subscribe to opened event
    this.getChatExtension().then((ext) => chatExtension.subscribe('WebChat.opened', callback));
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
  }

  open(callback) {
    // send open command
    // Smartti.open(); // this doesn't work?
    Smartti.show();
  }

  close(callback) {
    // send close command
    Smartti.close();
    // Smartti.hide(); // this also hides the button sometimes?
  }

  onOpened(callback) {
    // subscribe to opened event
    // Smartti.on('open', callback); // need to find docs
  }

  onClosed(callback) {
    // subscribe to closed event
    // Smartti.on('close', callback); // need to find docs
  }

  onLoaded(callback) {
    // subscribe to ready event
    // Smartti.on('load', callback); // need to find docs
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
      isChatOpen: this.isChatOpen()
    };

    if (this.state.cookies) {
      this.loadChat();
    }

    this.initWrapper();
    this.render();
  }

  prepButton(button) {

    button.addEventListener('click', (event) => {
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
    this.adapter.open(function(e){
      leijuke.setLeijukeCookie(leijuke.static.cookieName, true);
      leijuke.state = {
        ...leijuke.state,
        isChatOpen: true,
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

    libraries.css.map((script) => {
      // Create new link Element for loading css
      let css = document.createElement('link');
      css.rel = 'stylesheet';
      css.href = script.ext ? script.url : `/${modulePath}/${script.url}`;

      // Insert chatScript into head
      let head = document.querySelector('head');
      head.append(css);
    });

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
    this.setLeijukeCookie(this.static.cookieName, false);
    this.state = {
      ...this.state,
      isChatOpen: false,
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
      leijukeWrapper = document.createElement('div');
      leijukeWrapper.id = 'chat-leijuke-wrapper';
      document.body.append(leijukeWrapper)
    }

    let leijukeInstance = document.createElement('div');
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
