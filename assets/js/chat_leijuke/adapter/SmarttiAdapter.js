import AdapterBase from "./AdapterBase";

export default class SmarttiAdapter extends AdapterBase {
  constructor() {
    super();
    this.requiredCookies = ['chat'];
    this.bot = true;
    this.persist = true;
  }

  async getChatExtension() {
    return await new Promise(resolve => {
      let checkChatExtension = setInterval(()=> {
        if (typeof Smartti != 'undefined') {
          resolve(Smartti);
          clearInterval(checkChatExtension);
        }
      }, 100);
    });
  }

  open(callback) {
    // send open command
    this.getChatExtension().then((ext) => {
      ext.open();
      ext.show();
      callback();
    });
  }

  onClosed(callback) {
    // subscribe to closed event
    this.getChatExtension().then((ext) => {
      ext.on('close', ()=> {
        callback();
        ext.hide();
      });
      ext.on('minimize', ()=> {
        callback();
        ext.hide();
      });
    });
  }

  onLoaded(callback) {
    // subscribe to ready event
    this.getChatExtension().then((ext) => {
      callback();
    });

  }
}
