export default class Leijuke {
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

  openAdapter = () => {
    this.adapter.open(()=>{});
    let acaWidgetInitialized = setInterval(() => {
      if (acaWidget) {
        setTimeout(this.doOpenAdapter, 800);
        clearInterval(acaWidgetInitialized);
      }
    }, 500)
  }

  doOpenAdapter = () => this.adapter.open(()=>{});

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
