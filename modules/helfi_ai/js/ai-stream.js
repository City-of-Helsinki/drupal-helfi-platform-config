/**
 * @file
 * Reads server-sent events streamed by the Helfi AI routes.
 */
((Drupal) => {
  Drupal.helfiAi = Drupal.helfiAi || {};

  /**
   * Reads server-sent events from a response and passes on their JSON data.
   *
   * @param {Response} response
   *   The fetch response with a text/event-stream body.
   * @param {function(object): void} onData
   *   Called with the parsed JSON data of each event, in order.
   *
   * @return {Promise<void>}
   *   Resolves when the stream ends. Rejects, and closes the connection, if
   *   onData throws.
   */
  Drupal.helfiAi.readEvents = async (response, onData) => {
    const reader = response.body.pipeThrough(new TextDecoderStream()).getReader();
    let buffer = '';

    for (;;) {
      const { value, done } = await reader.read();
      if (done) {
        return;
      }
      buffer += value;

      // Keep the last, still incomplete event in the buffer.
      const events = buffer.split('\n\n');
      buffer = events.pop();
      for (const event of events) {
        const data = event.split('\n').find((line) => line.startsWith('data:'));
        if (data) {
          try {
            onData(JSON.parse(data.slice(5)));
          } catch (error) {
            // Close the connection, so the server stops generating.
            reader.cancel();
            throw error;
          }
        }
      }
    }
  };
})(Drupal);
