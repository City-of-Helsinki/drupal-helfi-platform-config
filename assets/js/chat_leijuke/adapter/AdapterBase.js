export default class AdapterBase {

  constructor() {
    this.requiredCookies = []; // For example 'chat'.
    this.bot = false; // Show chat or bot icon.
    this.persist = false;  // Do something with cookies.
  }

  /**
   * Wait until the chat is loaded and return resolved promise.
   */
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

  /**
   * Implement the chat opening functionality.
   */
  open(callback) {
    this.getChatExtension().then((extension)=>{
      // Call the chat opening function from the chat extension
      // After that trigger the callback function
      callback();
    })
  }

  /**
   * Implement the chat closing and minimizing functionality.
   */
  onClosed(callback) {
    this.getChatExtension().then((extension)=>{
      // Call the chat closing function from the chat extension
      // After that trigger the callback function
      callback();
    })
  }

  /**
   * Implement functionality that is run after the chat has been loaded.
   */
  onLoaded(callback) {
    this.getChatExtension().then((extension) => {
      // Call the chat closing function from the chat extension
      // After that trigger the callback function
      callback();
    });
  }
}
