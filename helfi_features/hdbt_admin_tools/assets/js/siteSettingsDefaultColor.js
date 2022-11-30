const defaultColor = document.querySelectorAll('[data-drupal-selector="edit-site-settings-theme-color"] input:checked')[0].value;

// Add event listeners.
document.addEventListener('DOMContentLoaded', function () {
  setDefaultColor();

  // Check if language switcher button exists and add the event listeners.
  const themeColors = document.querySelectorAll('[data-drupal-selector="edit-site-settings-theme-color"] input');
  if (themeColors) {
    for (const color of themeColors) {
      color.addEventListener('click', (event) => {
        setDefaultColor(event.target.value);
      });
    }
  }
});

// Act on click event.
function setDefaultColor(newColor = defaultColor) {
  const style = document.getElementById('hdbt-admin-default-color');

  if (style !== null) {
    style.remove();
  }

  let styles = document.createElement('style');
  styles.setAttribute('id', 'hdbt-admin-default-color');
  styles.innerHTML = `:root {\n\
      --hdbt-admin-theme-color: var(--hdbt-color-${newColor}--primary);\n\
      --hdbt-admin-text-color: var(--hdbt-color-${newColor}-text--primary);\n\
    }`;
  document.body.appendChild(styles);
}
