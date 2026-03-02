function getFormData(id, t, parsedSettings, formatFinnishEuroCents) {
  return {
    form_id: id,
    has_required_fields: true,
    items: [
      {
        input_integer: {
          id: 'household_size',
          label: t('household_size'),
          unit: t('unit_person'),
          min: 1,
          max: 6, // The maximum comes from specs not defining what happens at size of 7
          size: 2,
          required: true,
          helper_text: t('household_size_explanation'),
        },
      },
      {
        input_float: {
          id: 'gross_income_per_month',
          label: t('gross_income_per_month'),
          unit: t('unit_euro'),
          min: 0,
          size: 8,
          required: false, // If user does not enter this value, we calculate with max limits
          strip: '[€eE ]',
          helper_text: t('gross_income_per_month_explanation'),
        },
      },
      {
        input_float: {
          id: 'guardianship_fees',
          label: t('guardianship_fees'),
          unit: t('unit_euro'),
          min: 0,
          max: parsedSettings.guardianship_fee,
          size: 20,
          required: false,
          strip: '[€eE ]',
          helper_text: t('guardianship_fees_explanation', {
            guardianship_fee: formatFinnishEuroCents(parsedSettings.guardianship_fee),
          }),
        },
      },
      {
        input_integer: {
          id: 'monthly_usage',
          label: t('monthly_usage'),
          unit: t('unit_hour'),
          min: 0,
          max: 744, // Mathematical max hours: 31 days * 24 hours = 744 hours per month
          size: 3,
          required: true,
          helper_text: t('monthly_usage_explanation'),
        },
      },
      {
        heading: {
          text: t('supporting_services_heading'),
          level: 3,
        },
      },
      {
        paragraph: {
          text: t('supporting_services_paragraph'),
        },
      },
      {
        heading: {
          text: t('safety_phone_and_bracelet_heading'),
          level: 4,
        },
      },
      {
        paragraph: {
          text: t('safety_phone_and_bracelet_paragraph'),
        },
      },
      {
        radio: {
          id: 'safety_phone_and_bracelet',
          label: t('label_safety_phone_and_bracelet'),
          required: true,
          radio_items: [
            {
              name: 'safety_phone_and_bracelet',
              item_id: 'safety_phone_and_bracelet_true',
              label: t('label_safety_phone_and_bracelet_true'),
              value: true,
            },
            {
              name: 'safety_phone_and_bracelet',
              item_id: 'safety_phone_and_bracelet_false',
              label: t('label_safety_phone_and_bracelet_false'),
              value: false,
            },
          ],
        },
      },
      {
        group: {
          id: 'safetyphone_group',
          hide_group: true,
          items: [
            {
              paragraph: {
                text: t('helper_safety_phone_and_bracelet', {
                  price_per_visit_low_income: parsedSettings.safety_phone_and_bracelet_true.price_per_visit_low_income,
                  price_per_visit_high_income:
                    parsedSettings.safety_phone_and_bracelet_true.price_per_visit_high_income,
                  max_price_per_month_low_income:
                    parsedSettings.safety_phone_and_bracelet_true.max_price_per_month_low_income,
                  max_price_per_month_high_income:
                    parsedSettings.safety_phone_and_bracelet_true.max_price_per_month_high_income,
                }),
                class: 'hdbt-helper-text',
              },
            },
          ],
        },
      },
      {
        heading: {
          text: t('grocery_delivery_service_heading'),
          level: 4,
        },
      },
      {
        paragraph: {
          text: t('grocery_delivery_service_paragraph'),
        },
      },
      {
        radio: {
          id: 'grocery_delivery_service',
          label: t('label_grocery_delivery_service'),
          helper_text: t('helper_grocery_delivery_service', {
            per_delivery: parsedSettings.grocery_delivery_service.price_per_delivery,
          }),
          required: true,
          radio_items: [
            {
              name: 'grocery_delivery_service',
              item_id: 'grocery_delivery_service_true',
              label: t('label_grocery_delivery_service_true'),
              value: true,
            },
            {
              name: 'grocery_delivery_service',
              item_id: 'grocery_delivery_service_false',
              label: t('label_grocery_delivery_service_false'),
              value: false,
            },
          ],
        },
      },
      {
        heading: {
          text: t('meal_service_heading'),
          level: 4,
        },
      },
      {
        paragraph: {
          text: t('meal_service_paragraph'),
        },
      },
      {
        radio: {
          id: 'meal_service',
          label: t('label_meal_service'),
          helper_text: t('helper_meal_service', {
            full_service_price: parsedSettings.meal_service_true_full_service,
            partial_service_price: parsedSettings.meal_service_true_partial_service,
          }),
          required: true,
          radio_items: [
            {
              name: 'meal_service',
              item_id: 'meal_service_true_full_service',
              label: t('label_meal_service_true_full_service'),
              value: 'full_service',
            },
            {
              name: 'meal_service',
              item_id: 'meal_service_true_partial_service',
              label: t('label_meal_service_true_partial_service'),
              value: 'partial_service',
            },
            {
              name: 'meal_service',
              item_id: 'meal_service_false',
              label: t('label_meal_service_false'),
              value: false,
            },
          ],
        },
      },
    ],
  };
}

export default { getFormData };
