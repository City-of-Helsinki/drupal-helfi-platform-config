const formElements = {
  linkProtocol: {
    label: Drupal.t('Protocol'),
    machineName: 'protocol',
    selectListOptions: {
      https: 'https://',
      tel: 'tel:',
      mailto: 'mailto:',
    },
    type: 'select',
    group: 'helper',
  },
  linkNewWindowConfirm: {
    label: Drupal.t('The link meets the accessibility requirements'),
    description: Drupal.t('I have made sure that the description of this link clearly states that it will open in a new tab. <a href="@wcag-techniques" target="_blank">See WCAG 3.2.5 accessibility requirement (the link opens in a new tab).</a>', {
      '@wcag-techniques': 'https://www.w3.org/WAI/WCAG21/Techniques/general/G200.html',
    }),
    machineName: 'link-new-window-confirm',
    viewAttribute: {
      'target': '_blank',
    },
    type: 'checkbox',
    group: 'advanced',
    isVisible: false,
  },
  linkNewWindow: {
    label: Drupal.t('Open in new window/tab'),
    machineName: 'link-new-window',
    viewAttribute: 'data-link-new-window',
    type: 'checkbox',
    group: 'advanced',
    isVisible: true,
  },
  linkTitle: {
    label: Drupal.t('Title'),
    description: Drupal.t('Populates the title attribute of the link, usually shown as a small tooltip on hover.'),
    machineName: 'link-title',
    viewAttribute: 'title',
    type: 'input',
    group: 'advanced',
  },
  linkId: {
    label: Drupal.t('ID'),
    description: Drupal.t('Allows linking to this content using a URL fragment (#). Must be unique.'),
    machineName: 'link-id',
    viewAttribute: 'id',
    type: 'input',
    group: 'advanced',
  },
  linkClass: {
    machineName: 'link-class',
    viewAttribute: {
      'class': 'link',
    },
    type: 'static',
  }
}

export {
  formElements,
}
