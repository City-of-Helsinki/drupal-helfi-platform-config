// Retrieve query parameters.
const params = new URLSearchParams(window.location.search);

// Act on URL param "focus".
if (params.has('focus') && params.get('focus') === '') {

  // Simulate focus on inputs.
  const simulateFocusOnInputs = () => {
    const inputs = [
      'input.hds-text-input__input',
      'textarea.hds-text-input__input',
      'input.hds-checkbox__input',
      'input.hds-button--primary',
      'input.hds-button--secondary',
      'input.hds-radio-button__input',
      'select.hdbt--select',
    ];

    inputs.forEach(tag => {
      // Get all elements that match the tag.
      const elements = document.querySelectorAll(
        `.components--test-content ${tag}`
      );

      // Iterate over each element and add the simulation classes.
      elements.forEach((element) => {
        element.classList.add('simulate-focus');
      })
    });
  }

  // Simulate hover on inputs after the page has been rendered.
  window.addEventListener('load', () => {
    requestAnimationFrame(() => {
      simulateFocusOnInputs();
    });
  });
}
