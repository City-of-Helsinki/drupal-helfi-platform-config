import AdapterBase from "./AdapterBase";

export default class UserInquiryAdapter  extends AdapterBase {
  constructor() {
    super();
    // todo: dont know yet what we need.
    this.requiredCookies = ['chat'];
    this.bot = false;
    this.persist = false;
  }

  async getChatExtension() {
    /*
    return await new Promise(resolve => {
      let checkChatExtension = setInterval(()=> {
        if (typeof Smartti != 'undefined') {
          resolve(Smartti);
          clearInterval(checkChatExtension);
        }
      }, 100);
    });
    */
  }

  open(callback) {

    // Ei saa avata jos keksit estetty TAI käyttäjä jo kerran sulkenut

    // send open command
    this.getChatExtension().then((ext) => {
      ext.open();
      ext.show();
      callback();
    });
  }

  onClosed(callback) {
    // Suljettua ei saa enää avata tai näyttää käyttäjälle
  }

  onLoaded(callback) {
    // Ei saa ladata jos keksit estetty TAI käyttäjä jo kerran sulkenut
  }
}
