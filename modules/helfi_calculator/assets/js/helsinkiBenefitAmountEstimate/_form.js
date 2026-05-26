function getFormData(id, t) {
  return {
    form_id: id,
    has_required_fields: true,
    items: [
      {
        heading: {
          text: t('heading_employer'),
          level: 3,
        },
      },
      {
        radio: {
          id: 'company_type',
          label: t('label_company_type'),
          required: true,
          radio_items: [
            {
              name: 'company_type',
              item_id: 'company_type_business',
              label: t('label_company_type_business'),
              value: 'business',
            },
            {
              name: 'company_type',
              item_id: 'company_type_association',
              label: t('label_company_type_association'),
              value: 'association',
            },
          ],
        },
      },

      {
        group: {
          id: 'association_has_business_activities_true_group',
          hide_group: true,
          items: [
            {
              paragraph: {
                text: '',
              },
            },
            {
              checkbox: {
                id: 'association_has_business_activities',
                label: t('label_association_has_business_activities'),
                helper_text: t('helper_text_association_has_business_activities'),
              },
            },
          ],
        },
      },
      {
        heading: {
          text: t('heading_employee'),
          level: 3,
        },
      },
      {
        input_integer: {
          id: 'monthly_pay',
          label: t('label_monthly_pay'),
          unit: t('unit_euro'),
          min: 0,
          max: 10000,
          size: 12,
          required: true,
          strip: '[€eE ]',
          helper_text: t('helper_text_monthly_pay'),
        },
      },
      {
        input_integer: {
          id: 'vacation_money',
          label: t('label_vacation_money'),
          unit: t('unit_euro'),
          min: 0,
          max: 454, // Vacation money per month is half monthly_pay divided with 11 months; also adjusted in code when monthly_pay changes
          size: 12,
          required: false,
          strip: '[€eE ]',
          helper_text: t('helper_text_vacation_money'),
        },
      },
      {
        heading: {
          text: t('heading_pay_subsidy_information'),
          level: 3,
        },
      },
      {
        checkbox: {
          id: 'pay_subsidy_granted',
          label: t('label_pay_subsidy_false'),
          helper_text: t('text_pay_subsidy_information'),
          required: true,
        },
      },
    ],
  };
}

export default { getFormData };
