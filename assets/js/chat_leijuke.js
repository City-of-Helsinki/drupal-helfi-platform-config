(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.chat_leijuke = {
    attach: function (context, settings) {

      const leijukeData = drupalSettings.leijuke_data;

      const adapter = getAdapter(leijukeData.chat_selection);

      setTimeout(() => {
        new Leijuke(leijukeData, new EuCookieManager, adapter);
      });
    }
  }
})(Drupal, drupalSettings);

function getAdapter(chatSelection) {
  if (chatSelection.indexOf('genesys') != -1) {
    return new GenesysAdapter;
  }
}

class EuCookieManager {
  cookieCheck = (cookieNames) => {
    let cookiesOk = true;
    cookieNames.map((cookieName) => {
      if (!Drupal.eu_cookie_compliance.hasAgreedWithCategory(cookieName)) cookiesOk = false;
    });
    console.log(`${cookiesOk ? 'OK: ': 'NO: '} Checked cookies: `, cookieNames);
    return cookiesOk;
  };

  cookieSet = (cookieNames) => {
    Drupal.eu_cookie_compliance.setAcceptedCategories([ ...Drupal.eu_cookie_compliance.getAcceptedCategories(), ...cookieNames ]);
    console.log('Checked cookies: ', cookieNames);
  };
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
    this.getChatExtension().then((ext) => chatExtension.command('WebChat.open').done(callback).fail('Failed WebChat open command.'));
  }

  close(callback) {
    // send close command
    this.getChatExtension().then((ext) => chatExtension.command('WebChat.close').done(callback).fail('Failed WebChat close command.'));
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

class Leijuke {

  constructor(leijukeData, extCookieManager, chatAdapter) {

    this.extCookieManager = extCookieManager;
    this.adapter = chatAdapter;

    this.static = {
      chatSelection: leijukeData.chat_selection,
      cookieName: `leijuke.${leijukeData.chat_selection}.isOpen`,
      modulePath: leijukeData.modulepath,
      libraries: leijukeData.libraries,
      title: leijukeData.title
    }

    this.state = {
      cookies: extCookieManager.cookieCheck(this.adapter.requiredCookies),
      chatLoaded: false,
      isChatOpen: this.isChatOpen(),
    };

    if (this.state.cookies) {
      this.loadChat();
    }
    console.log('current state', this.state);
    console.log('current static', this.static);

    this.initWrapper();
    this.render();
  }

  prepButton() {
    const button = document.querySelector('#chat-leijuke');

    button.addEventListener('click', (event) => {
      if (this.state.chatLoaded) {
        console.log('Chat was loaded previously, just opening it now.');

        this.openChat();
        return;
      }

      if (!this.state.cookies) {
        // Implicitly allow chat cookies if clicking Leijuke.
        console.log('Chat cookies allowed implicitly and chat being loaded.');

        this.extCookieManager.cookieSet(this.adapter.requiredCookies);
      }

      this.loadChat();
      this.state = {
        ...this.state,
        cookies: this.extCookieManager.cookieCheck(this.adapter.requiredCookies),
        chatLoaded: true
      };
      this.adapter.onLoaded(this.openChat.bind(this));
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
    console.log('Trying to open chat!');
    const leijuke = this;
    // try to open genesys chat
    this.adapter.open(function(e){
      console.log('Opened genesys chat succesfully!');
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
    console.log('Chat loaded complete!');
    this.render();
  }

  closeChat() {
    console.log('Chat closed event.');
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
    let leijukeWrapper = document.createElement('div');
    leijukeWrapper.id = 'chat-leijuke-wrapper';
    document.body.append(leijukeWrapper)
  }

  render() {
    console.log('current state during render', this.state);

    const { isChatOpen } = this.state;

    const icon = this.adapter.bot ? 'customer-bot-neutral' : 'speechbubble-text';

    document
    .getElementById("chat-leijuke-wrapper")
    .innerHTML = `
      <div id="chat-leijuke" ${isChatOpen ? 'class="hidden"' : ''}>
        <span class="hel-icon hel-icon--${icon}"></span><span>${this.static.title}</span><span class="hel-icon hel-icon--angle-up"></span>
      </div>
    `;

    this.prepButton();
  }
}
