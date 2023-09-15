const formElements = {
  linkIcon: {
    label: Drupal.t('Icon', {}, { context: 'CKEditor5 Helfi Link plugin' }),
    machineName: 'icon',
    selectListOptions: {},
    type: 'select',
    group: 'advanced',
    isVisible: false,
    viewAttribute: 'data-icon-start',
  },
  linkVariant: {
    label: Drupal.t('Design'),
    machineName: 'variant',
    selectListOptions: {
      link: Drupal.t('Normal link', {}, { context: 'CKEditor5 Helfi Link plugin' }),
      primary: Drupal.t('Button primary'),
      secondary: Drupal.t('Button secondary'),
      supplementary: Drupal.t('Button supplementary'),
    },
    type: 'select',
    group: 'advanced',
    isVisible: true,
    viewAttribute: 'data-variant',
  },
  linkProtocol: {
    label: Drupal.t('Protocol', {}, { context: 'CKEditor5 Helfi Link plugin' }),
    machineName: 'protocol',
    selectListOptions: {
      https: 'https://',
      tel: 'tel:',
      mailto: 'mailto:',
    },
    type: 'select',
    group: 'helper',
    viewAttribute: 'data-protocol',
    isVisible: true,
  },
  linkNewWindowConfirm: {
    label: Drupal.t('The link meets the accessibility requirements', {}, { context: 'CKEditor5 Helfi Link plugin' }),
    description: Drupal.t('I have made sure that the description of this link clearly states that it will open in a new tab. <a href="@wcag-techniques" target="_blank">See WCAG 3.2.5 accessibility requirement (the link opens in a new tab).</a>', {
      '@wcag-techniques': 'https://www.w3.org/WAI/WCAG21/Techniques/general/G200.html',
    }, { context: 'CKEditor5 Helfi Link plugin' }),
    machineName: 'link-new-window-confirm',
    viewAttribute: {
      'target': '_blank',
    },
    type: 'checkbox',
    group: 'advanced',
    isVisible: false,
  },
  linkNewWindow: {
    label: Drupal.t('Open in new window/tab', {}, { context: 'CKEditor5 Helfi Link plugin' }),
    machineName: 'link-new-window',
    viewAttribute: 'data-link-new-window',
    type: 'checkbox',
    group: 'advanced',
    isVisible: true,
  },
  linkTitle: {
    label: Drupal.t('Title', {}, { context: 'CKEditor5 Helfi Link plugin' }),
    description: Drupal.t('Populates the title attribute of the link, usually shown as a small tooltip on hover.', {}, { context: 'CKEditor5 Helfi Link plugin' }),
    machineName: 'link-title',
    viewAttribute: 'title',
    type: 'input',
    group: 'advanced',
  },
  linkId: {
    label: Drupal.t('ID', {}, { context: 'CKEditor5 Helfi Link plugin' }),
    description: Drupal.t('Allows linking to this content using a URL fragment (#). Must be unique.', {}, { context: 'CKEditor5 Helfi Link plugin' }),
    machineName: 'link-id',
    viewAttribute: 'id',
    type: 'input',
    group: 'advanced',
  },
};

export {
  formElements,
};
