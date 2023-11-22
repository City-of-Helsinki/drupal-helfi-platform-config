const formElements = {
  linkIcon: {
    label: Drupal.t('Icon', {}, { context: 'CKEditor5 Helfi Link plugin' }),
    machineName: 'icon',
    selectListOptions: {},
    type: 'select',
    group: 'advanced',
    isVisible: false,
    viewAttribute: 'data-hds-icon-start',
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
    viewAttribute: 'data-hds-variant',
  },
  linkButton: {
    machineName: 'data-hds-component',
    type: 'static',
    viewAttribute: 'data-hds-component',
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
    type: 'checkbox',
    group: 'advanced',
    isVisible: true,
  },
  linkIsExternal: {
    machineName: 'data-is-external',
    type: 'static',
    viewAttribute: 'data-is-external',
  },
};
export default formElements;
