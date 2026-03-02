import form from './_form';
import translations from './_translations';

class CommunalAndSupportedHousingFee {
  constructor(id, settings) {
    this.id = id;
    const parsedSettings = JSON.parse(settings);

    // Expecting settings to follow this JSON format:
    /*
    const parsedSettings = {
      "income_limit": {
        "1": 699,
        "2": 1290,
        "3": 2025,
        "4": 2503,
        "5": 3030,
        "6": 3481
      },
      "monthly_usage": {
        "0": {
          "max": 119.66,
          "percent": {
            "1": 8,
            "2": 7,
            "3": 6,
            "4": 6,
            "5": 6,
            "6": 6
          }
        },
        "5": {
          "max": 538.47,
          "percent": {
            "1": 10,
            "2": 8,
            "3": 7,
            "4": 7,
            "5": 7,
            "6": 7
          }
        },
        "9": {
          "max": 1017.12,
          "percent": {
            "1": 17,
            "2": 14,
            "3": 12,
            "4": 12,
            "5": 12,
            "6": 12
          }
        },
        "13": {
          "max": 1495.76,
          "percent":  {
            "1": 21,
            "2": 17,
            "3": 14,
            "4": 14,
            "5": 14,
            "6": 12
          }
        },
        "17": {
          "max": 1974.4,
          "percent": {
            "1": 24,
            "2": 20,
            "3": 16,
            "4": 16,
            "5": 14,
            "6": 12
          }
        },
        "21": {
          "max": 2453.05,
          "percent": {
            "1": 26,
            "2": 22,
            "3": 18,
            "4": 16,
            "5": 14,
            "6": 12
          }
        },
        "25": {
          "max": 2931.69,
          "percent": {
            "1": 28,
            "2": 24,
            "3": 19,
            "4": 16,
            "5": 14,
            "6": 12
          }
        },
        "29": {
          "max": 3410.33,
          "percent": {
            "1": 30,
            "2": 24,
            "3": 19,
            "4": 16,
            "5": 14,
            "6": 12
          }
        },
        "33": {
          "max": 3888.98,
          "percent": {
            "1": 32,
            "2": 24,
            "3": 19,
            "4": 16,
            "5": 14,
            "6": 12
          }
        },
        "37": {
          "max": 4367.62,
          "percent": {
            "1": 34,
            "2": 24,
            "3": 19,
            "4": 16,
            "5": 14,
            "6": 12
          }
        },
        "41": {
          "max": 4906.1,
          "percent": {
            "1": 35,
            "2": 24,
            "3": 19,
            "4": 16,
            "5": 14,
            "6": 12
          }
        }
      },
      "safety_phone_and_bracelet_true": {
        "single_low_income": "35,68",
        "single_high_income": "71,36",
        "couple_low_income": "35,68",
        "couple_high_income": "71,36",
        "single_income_limit": 1470,
        "couple_income_limit": 2170,
        "price_per_visit_low_income": "23,25",
        "price_per_visit_high_income": "46,50",
        "max_price_per_month_low_income": "116,25",
        "max_price_per_month_high_income": "232,50"
      },
      "grocery_delivery_service": {
        "price_per_delivery": "9,73",
        "price_per_month": "38,92"
      },
      "meal_service_true_full_service": "497,95",
      "meal_service_true_partial_service": "373,50"
    };
    // */
    // Form content
    const getFormData = () =>
      form.getFormData(this.id, this.t, parsedSettings, this.calculator.formatFinnishEuroCents.bind(this.calculator));

    const update = () => {
      const fields = [{ field: 'safety_phone_and_bracelet', group: 'safetyphone_group' }];

      fields.forEach(({ field, group }) => {
        if (this.calculator.getFieldValue(field) === 'true') {
          this.calculator.showGroup(group);
        } else {
          this.calculator.hideGroup(group);
        }
      });
    };

    const validate = () => {
      const errorMessages = [];

      // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
      // Validate basics from form
      errorMessages.push(...this.calculator.validateBasics('household_size'));
      errorMessages.push(...this.calculator.validateBasics('gross_income_per_month'));
      errorMessages.push(...this.calculator.validateBasics('guardianship_fees'));
      errorMessages.push(...this.calculator.validateBasics('monthly_usage'));
      errorMessages.push(...this.calculator.validateBasics('safety_phone_and_bracelet'));
      errorMessages.push(...this.calculator.validateBasics('grocery_delivery_service'));
      errorMessages.push(...this.calculator.validateBasics('meal_service'));

      // Check if any missing input errors were found
      if (errorMessages.length) {
        return {
          error: {
            title: this.t('missing_input'),
            message: errorMessages,
          },
        };
      }

      // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
      // Get field values for calculating.
      const householdSize = Number(this.calculator.getFieldValue('household_size'));
      const grossIncomePerMonth = Number(this.calculator.getFieldValue('gross_income_per_month'));
      const grossIncomePerMonthRaw = this.calculator.getFieldValue('gross_income_per_month');
      const monthlyUsage = Number(this.calculator.getFieldValue('monthly_usage'));
      const guardianshipFeesRaw = this.calculator.getFieldValue('guardianship_fees');
      const guardianshipFees = Number(this.calculator.getFieldValue('guardianship_fees'));
      const safetyPhoneAndBracelet = this.calculator.getFieldValue('safety_phone_and_bracelet');
      const groceryDeliveryService = this.calculator.getFieldValue('grocery_delivery_service');
      const mealService = this.calculator.getFieldValue('meal_service');

      // Calculate results:
      // 1. Get income limit
      // 2. Get max payment and payment percent
      // 3. Assume that user has not given income details, use max value
      // 4. If user has given income details, calculate based on that
      // 5. Add extras (safety phone and bracelet, grocery delivery service, meal service)

      // 1. Get income limit
      const incomeLimit = parsedSettings.income_limit[householdSize];

      // 2. Get max payment and payment percent
      const { max, percent } = this.calculator.getMinimumRange(monthlyUsage, parsedSettings.monthly_usage);
      const percentForHouseholdSize = percent[householdSize];

      // 3. Assume that user has not given income details, use max value
      let communalAndSupportedHousingPayment = max;

      // 4. If user has given income details, calculate based on that
      if (grossIncomePerMonthRaw !== null) {
        if (guardianshipFeesRaw !== null) {
          // If user has given guardianship fees, we subtract them from gross income before calculating payment
          communalAndSupportedHousingPayment =
            (grossIncomePerMonth - guardianshipFees - incomeLimit) * (percentForHouseholdSize / 100);
        } else {
          communalAndSupportedHousingPayment = (grossIncomePerMonth - incomeLimit) * (percentForHouseholdSize / 100);
        }
      }

      // Clamp payment between 0 and max payment, round to even eurocents
      communalAndSupportedHousingPayment = this.calculator.clamp(
        0,
        Math.round(communalAndSupportedHousingPayment * 100) / 100,
        max,
      );

      const subtotals = [];
      let totalPayment = communalAndSupportedHousingPayment;

      //Helper function to transform string to float
      const transformToFloat = (string) => {
        if (typeof string === 'string') {
          return parseFloat(string.replace(',', '.'));
        }
        return string;
      };

      if (communalAndSupportedHousingPayment >= 0) {
        subtotals.push({
          title: this.t('communal_and_supported_housing_payment'),
          has_details: false,
          details: [],
          sum: this.t('receipt_subtotal_euros_per_month', {
            value: this.calculator.formatFinnishEuroCents(communalAndSupportedHousingPayment),
          }),
          sum_screenreader: this.t('receipt_subtotal_euros_per_month', {
            value: this.calculator.formatEuroCents(communalAndSupportedHousingPayment),
          }),
        });
      }

      // 5. Add safety phone and bracelet payment
      if (safetyPhoneAndBracelet === 'true') {
        let safetyPhoneAndBraceletPayment = 0;
        if (householdSize === 1) {
          if (grossIncomePerMonth <= parsedSettings.safety_phone_and_bracelet_true.single_income_limit) {
            safetyPhoneAndBraceletPayment = transformToFloat(
              parsedSettings.safety_phone_and_bracelet_true.single_low_income,
            );
          } else {
            safetyPhoneAndBraceletPayment = transformToFloat(
              parsedSettings.safety_phone_and_bracelet_true.single_high_income,
            );
          }
        } else {
          if (grossIncomePerMonth <= parsedSettings.safety_phone_and_bracelet_true.couple_income_limit) {
            safetyPhoneAndBraceletPayment = transformToFloat(
              parsedSettings.safety_phone_and_bracelet_true.couple_low_income,
            );
          } else {
            safetyPhoneAndBraceletPayment = transformToFloat(
              parsedSettings.safety_phone_and_bracelet_true.couple_high_income,
            );
          }
        }
        totalPayment += safetyPhoneAndBraceletPayment;
        subtotals.push({
          title: this.t('safety_phone_and_bracelet_payment'),
          has_details: true,
          details: [
            this.t('helper_safety_phone_and_bracelet', {
              price_per_visit_low_income: parsedSettings.safety_phone_and_bracelet_true.price_per_visit_low_income,
              price_per_visit_high_income: parsedSettings.safety_phone_and_bracelet_true.price_per_visit_high_income,
              max_price_per_month_low_income:
                parsedSettings.safety_phone_and_bracelet_true.max_price_per_month_low_income,
              max_price_per_month_high_income:
                parsedSettings.safety_phone_and_bracelet_true.max_price_per_month_high_income,
            }),
          ],
          sum: this.t('receipt_subtotal_euros_per_month', {
            value: this.calculator.formatFinnishEuroCents(safetyPhoneAndBraceletPayment),
          }),
        });
      } else {
        subtotals.push({
          title: this.t('no_safety_phone_and_bracelet_payment'),
          has_details: false,
        });
      }

      // 5. Add grocery delivery service payment
      if (groceryDeliveryService === 'true') {
        const groceryPayment = transformToFloat(parsedSettings.grocery_delivery_service.price_per_month);
        totalPayment += groceryPayment;
        subtotals.push({
          title: this.t('grocery_delivery_service_payment'),
          has_details: true,
          details: [
            this.t('grocery_delivery_service_payment_additional_details_text_1'),
            this.t('grocery_delivery_service_payment_additional_details_text_2', {
              per_delivery: parsedSettings.grocery_delivery_service.price_per_delivery,
            }),
          ],
          sum: this.t('receipt_subtotal_euros_per_month', {
            value: this.calculator.formatFinnishEuroCents(groceryPayment),
          }),
          sum_screenreader: this.t('receipt_subtotal_euros_per_month', {
            value: this.calculator.formatEuroCents(groceryPayment),
          }),
        });
      } else {
        subtotals.push({
          title: this.t('no_grocery_delivery_service_payment'),
          has_details: false,
        });
      }

      // 5. Add meal service payment
      if (mealService !== 'false') {
        let mealServicePayment = 0;
        if (mealService === 'full_service') {
          mealServicePayment = transformToFloat(parsedSettings.meal_service_true_full_service);
        } else if (mealService === 'partial_service') {
          mealServicePayment = transformToFloat(parsedSettings.meal_service_true_partial_service);
        }
        totalPayment += mealServicePayment;
        subtotals.push({
          title: this.t('meal_service_payment'),
          has_details: false,
          details: [],
          sum: this.t('receipt_subtotal_euros_per_month', {
            value: this.calculator.formatFinnishEuroCents(mealServicePayment),
          }),
          sum_screenreader: this.t('receipt_subtotal_euros_per_month', {
            value: this.calculator.formatEuroCents(mealServicePayment),
          }),
        });
      } else {
        subtotals.push({
          title: this.t('no_meal_service_payment'),
          has_details: false,
        });
      }

      // Create receipt
      const receiptData = {
        id: this.id,
        title: this.t('receipt_estimate_of_payment'),
        total_prefix: this.t('receipt_estimated_payment_prefix'),
        total_value: this.calculator.formatFinnishEuroCents(totalPayment),
        total_suffix: this.t('receipt_estimated_payment_suffix'),
        total_explanation: this.t('receipt_estimated_payment_explanation'),
        hr: true,
        breakdown: [
          {
            title: this.t('receipt_estimate_of_payment_breakdown_title'),
            subtotals: subtotals,
          },
        ],
      };

      const receipt = this.calculator.getPartialRender('{{>receipt}}', receiptData);

      return {
        receipt,
        ariaLive: this.t('receipt_aria_live', { totalPayment }),
      };
    };

    const eventHandlers = {
      submit: (event) => {
        this.calculator.clearResult();
        event.preventDefault();
        const result = validate();
        this.calculator.renderResult(result);
      },
      keydown: () => {
        update();
      },
      change: () => {
        update();
      },
      reset: () => {
        window.setTimeout(update, 1);
        this.calculator.clearResult();
        this.calculator.showAriaLiveText(this.t('reset_aria_live'));
      },
    };

    // Prepare calculator for translations
    this.calculator = window.helfiCalculator({ name: 'communalAndSupportedHousingFee', translations });

    // Create shortcut for translations
    this.t = (key, value) => this.calculator.translate(key, value);

    // Parse settings to js
    this.settings = this.calculator.parseSettings(settings);

    // Initialize calculator
    this.calculator.init({
      id,
      formData: getFormData(),
      eventHandlers,
    });
  }
}

window.helfiCalculator = window.helfiCalculator || {};
window.helfiCalculator.communal_and_supported_housing_fee = (id, settings) =>
  new CommunalAndSupportedHousingFee(id, settings);
