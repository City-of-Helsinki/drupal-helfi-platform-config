// Retrieve query parameters.
const params = new URLSearchParams(window.location.search);

// Act on URL param "focus".
if (params.has('focus') && params.get('focus') === '') {

  // Apply styles to the element.
  const applyElementStyles = (element) => {
    element.focus();

    // Save the focus styles to a variable to be used later.
    const computedStyles = window.getComputedStyle(element);

    Array.from(computedStyles).forEach(prop => {
      // Only apply the style to an element if the style has a value.
      const value = computedStyles.getPropertyValue(prop);
      if (value) {
        element.style[prop] = value;
      }
    });
  };

  // Simulate hover on inputs.
  const simulateFocusOnInputs = () => {
    const inputs = [
      'input.hds-text-input__input',
      'textarea.hds-text-input__input',
      'input.hds-checkbox__input',
      'input.hds-button--primary',
      'input.hds-button--secondary',
      'select.hdbt--select',
    ];

    inputs.forEach(tag => {
      // Get all elements that match the tag.
      const elements = document.querySelectorAll(`.components--test-content ${tag}`);

      // Iterate over each element and simulate focus.
      elements.forEach((element) => {
        applyElementStyles(element);
      })
    });

    // Focusing the radio input element does not apply the styles to the
    // label::before pseudo-element. We need to apply the styles via class
    // instead.
    const radioInputs = document.querySelectorAll('.components--test-content input.hds-radio-button__input');

    // Iterate over each element and apply the
    // hds-radio-button--simulate-focus class.
    radioInputs.forEach((radioInput) => {
      const radioLabel = radioInput.nextElementSibling;
      if (radioLabel && radioLabel.classList.contains('hds-radio-button__label')) {
        radioLabel.classList.add('hds-radio-button--simulate-focus');
      }
    });
  }

  // Simulate hover on inputs after the page has been rendered.
  window.addEventListener('load', () => {
    requestAnimationFrame(() => {
      simulateFocusOnInputs();
    });
  });
}
