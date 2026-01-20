function getFormData(id, t) {
  return {
    form_id: id,
    has_required_fields: true,
    items: [
      {
        heading: {
          text: t('social_welfare_act_heading'),
          level: 3,
        },
      },
      {
        paragraph: {
          text: t('social_welfare_act_paragraph'),
        },
      },
      {
        radio: {
          id: 'social_welfare_act',
          label: t('label_social_welfare_act'),
          required: true,
          radio_items: [
            {
              name: 'social_welfare_act',
              item_id: 'social_welfare_act_true',
              label: t('label_yes'),
              value: true,
            },
            {
              name: 'social_welfare_act',
              item_id: 'social_welfare_act_false',
              label: t('label_no'),
              value: false,
            },
          ],
        },
      },
      {
        heading: {
          text: t('net_income_heading'),
          level: 3,
        },
      },
      {
        input_float: {
          id: 'earned_income',
          label: t('earned_income'),
          unit: t('unit_euro'),
          min: 0,
          size: 20,
          required: false,
          strip: '[€eE ]',
          helper_text: t('earned_income_explanation'),
        },
      },
      {
        input_float: {
          id: 'client_benefits',
          label: t('client_benefits'),
          unit: t('unit_euro'),
          min: 0,
          size: 20,
          required: false,
          strip: '[€eE ]',
          helper_text: t('client_benefits_explanation'),
        },
      },
      {
        input_float: {
          id: 'capital_income',
          label: t('capital_income'),
          unit: t('unit_euro'),
          min: 0,
          size: 20,
          required: false,
          strip: '[€eE ]',
          helper_text: t('capital_income_explanation'),
        },
      },
      {
        input_float: {
          id: 'annual_forest_income',
          label: t('annual_forest_income'),
          unit: t('unit_euro'),
          min: 0,
          size: 20,
          required: false,
          strip: '[€eE ]',
          helper_text: t('annual_forest_income_explanation'),
        },
      },
      {
        heading: {
          text: t('deductions_heading'),
          level: 3,
        },
      },
      {
        input_float: {
          id: 'guardianship_fees',
          label: t('guardianship_fees'),
          unit: t('unit_euro'),
          min: 0,
          size: 20,
          required: false,
          strip: '[€eE ]',
          helper_text: t('guardianship_fees_explanation'),
        },
      },
      {
        input_float: {
          id: 'client_foreclosure',
          label: t('client_foreclosure'),
          unit: t('unit_euro'),
          min: 0,
          size: 20,
          required: false,
          strip: '[€eE ]',
          helper_text: t('client_foreclosure_explanation'),
        },
      },
      {
        input_float: {
          id: 'compensation_or_life_annuity',
          label: t('compensation_or_life_annuity'),
          unit: t('unit_euro'),
          min: 0,
          size: 20,
          required: false,
          strip: '[€eE ]',
          helper_text: t('compensation_or_life_annuity_explanation'),
        },
      },
      {
        input_float: {
          id: 'maintenance_payments',
          label: t('maintenance_payments'),
          unit: t('unit_euro'),
          min: 0,
          size: 20,
          required: false,
          strip: '[€eE ]',
          helper_text: t('maintenance_payments_explanation'),
        },
      },
      {
        radio: {
          id: 'has_spouse',
          label: t('label_has_spouse'),
          required: true,
          radio_items: [
            {
              name: 'has_spouse',
              item_id: 'has_spouse_true',
              label: t('label_yes'),
              value: true,
            },
            {
              name: 'has_spouse',
              item_id: 'has_spouse_false',
              label: t('label_no'),
              value: false,
            },
          ],
        },
      },
      {
        group: {
          id: 'spouse_income_group',
          hide_group: true,
          items: [
            {
              heading: {
                text: t('spouse_net_income_heading'),
                level: 3,
              },
            },
            {
              paragraph: {
                text: t('spouse_net_income_paragraph'),
              },
            },
            {
              input_float: {
                id: 'spouse_earned_income',
                label: t('spouse_earned_income'),
                unit: t('unit_euro'),
                min: 0,
                size: 20,
                required: false,
                strip: '[€eE ]',
                helper_text: t('spouse_earned_income_explanation'),
              },
            },
            {
              input_float: {
                id: 'spouse_client_benefits',
                label: t('spouse_client_benefits'),
                unit: t('unit_euro'),
                min: 0,
                size: 20,
                required: false,
                strip: '[€eE ]',
                helper_text: t('spouse_client_benefits_explanation'),
              },
            },
            {
              input_float: {
                id: 'spouse_capital_income',
                label: t('spouse_capital_income'),
                unit: t('unit_euro'),
                min: 0,
                size: 20,
                required: false,
                strip: '[€eE ]',
                helper_text: t('spouse_capital_income_explanation'),
              },
            },
            {
              input_float: {
                id: 'spouse_annual_forest_income',
                label: t('spouse_annual_forest_income'),
                unit: t('unit_euro'),
                min: 0,
                size: 20,
                required: false,
                strip: '[€eE ]',
                helper_text: t('spouse_annual_forest_income_explanation'),
              },
            },
            {
              heading: {
                text: t('spouse_deductions_heading'),
                level: 3,
              },
            },
            {
              input_float: {
                id: 'spouse_guardianship_fees',
                label: t('spouse_guardianship_fees'),
                unit: t('unit_euro'),
                min: 0,
                size: 20,
                required: false,
                strip: '[€eE ]',
                helper_text: t('spouse_guardianship_fees_explanation'),
              },
            },

            {
              input_float: {
                id: 'spouse_client_foreclosure',
                label: t('spouse_client_foreclosure'),
                unit: t('unit_euro'),
                min: 0,
                size: 20,
                required: false,
                strip: '[€eE ]',
                helper_text: t('spouse_client_foreclosure_explanation'),
              },
            },

            {
              input_float: {
                id: 'spouse_compensation_or_life_annuity',
                label: t('spouse_compensation_or_life_annuity'),
                unit: t('unit_euro'),
                min: 0,
                size: 20,
                required: false,
                strip: '[€eE ]',
                helper_text: t('spouse_compensation_or_life_annuity_explanation'),
              },
            },
            {
              input_float: {
                id: 'spouse_maintenance_payments',
                label: t('spouse_maintenance_payments'),
                unit: t('unit_euro'),
                min: 0,
                size: 20,
                required: false,
                strip: '[€eE ]',
                helper_text: t('spouse_maintenance_payments_explanation'),
              },
            },
          ],
        },
      },
    ],
  };
}

export default { getFormData };
