import AdapterBase from "./AdapterBase";

export default class GenesysAdapter extends AdapterBase {
  constructor() {
    super();
    this.requiredCookies = ['chat'];
    this.bot = false;
    this.persist = true;
  }

  async getChatExtension() {
    return await new Promise(resolve => {
      let checkChatExtension = setInterval(()=> {
        if (typeof chatExtension != 'undefined') {
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

  onClosed(callback) {
    // subscribe to closed event
    this.getChatExtension().then((ext) => chatExtension.subscribe('WebChat.closed', callback));
  }

  onLoaded(callback) {
    // subscribe to ready event
    this.getChatExtension().then((ext) => chatExtension.subscribe('WebChat.ready', callback));
  }
}
