((Drupal, once) => {
  Drupal.behaviors.helfiCalculator = {
    attach(context, settings) {
      // Find all calculator placeholders.
      const elements = once('helfi-calculator', '.js-helfi-calculator', context);
      elements.forEach((element) => {
        // Get the calculator name and settings.
        const name = element.getAttribute('data-calculator');
        const settingsKey = element.getAttribute('data-settings-key');
        const calculatorSettings =
          settings?.helfiCalculator?.[settingsKey].data ?? settings?.helfiCalculator?.[name] ?? {};

        // Check if the calculator is available and return if not.
        if (!window.helfiCalculator || typeof window.helfiCalculator?.[name] !== 'function') {
          return;
        }

        // Initialize the calculator with the current calculator settings.
        window.helfiCalculator[name](element.id, calculatorSettings);
      });
    },
  };
})(Drupal, once);
