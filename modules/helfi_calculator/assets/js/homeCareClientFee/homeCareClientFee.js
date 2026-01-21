import form from './_form';
import translations from './_translations';

class HomeCareClientFee {
  // Separate function for calculating client fee, so that it can be used in homeCareServiceVoucher.js too
  static calculateClientFee(
    householdSize,
    grossIncomePerMonth,
    grossIncomePerMonthRaw,
    monthlyUsage,
    calculator,
    calculatorSettings,
    printDebug = false,
  ) {
    // Finds the smallest matching value >= key from object
    function getMinimumRange(value, range) {
      const rangeKeys = Object.keys(range).reverse();
      for (let i = 0; i < rangeKeys.length; i++) {
        const valueLimit = rangeKeys[i];
        if (Number(valueLimit) <= value) {
          return range[valueLimit];
        }
      }
      throw new Error(`Minimum range not found for ${value} from ${range}`);
    }

    // 1. Get proper limits based on given values and the parsed settings.
    const maximumPayment = getMinimumRange(monthlyUsage, calculatorSettings.monthly_usage_max_payment);

    // Currently we have values up until 6 person household sizes, this way it's configurable.
    const household = getMinimumRange(householdSize, calculatorSettings.household_size);

    // Calculate limit for households bigger than in reference tables
    let multipliedLimit = 0; // By default the extra limit is 0
    const maxDefinedLimitNum = Number(Object.keys(calculatorSettings.household_size).at(-1));
    const diff = householdSize - maxDefinedLimitNum;
    if (diff > 0) {
      multipliedLimit = diff * calculatorSettings.household_size_beyond_defined_multiplier_euro;
    }

    // Calculate gross income limit with potential extra limit if the household is bigger than reference table data
    const grossIncomeLimit = household.gross_income_limit + multipliedLimit;
    const paymentPercentage = getMinimumRange(monthlyUsage, household.monthly_usage_percentage);

    // 2. If the gross income field is null, lets use the maximumPayment
    let referencePayment = maximumPayment;

    // 3. If the gross income field has a value, calculate refrence payment
    if (grossIncomePerMonthRaw !== null) {
      referencePayment = (grossIncomePerMonth - grossIncomeLimit) * (paymentPercentage / 100);
    }

    // 4. Payment should never be higher than maximumPayment nor lower than 0
    const payment = calculator.clamp(0, referencePayment, maximumPayment);

    if (printDebug) {
      console.debug(
        `maximumPayment\t${maximumPayment}`,
        `\ngrossIncomeLimit\t${grossIncomeLimit}`,
        `\npaymentPercentage\t${paymentPercentage}`,
        `\nreferencePayment\t${referencePayment}`,
      );
    }

    return payment;
  }

  constructor(id, settings) {
    this.id = id;
    const parsedSettings = JSON.parse(settings);

    // Expecting settings to follow this JSON format:
    /*
    const parsedSettings = {
      "household_size": {
        "1": {
          "gross_income_limit": 653,
          "monthly_usage_percentage": {
            "0": 8,
            "5": 10,
            "9": 17,
            "13": 21,
            "17": 24,
            "21": 26,
            "25": 28,
            "29": 30,
            "33": 32,
            "37": 34,
            "41": 35
          }
        },
        "2": {
          "gross_income_limit": 1205,
          "monthly_usage_percentage": {
            "0": 7,
            "5": 8,
            "9": 14,
            "13": 17,
            "17": 20,
            "21": 22,
            "25": 24,
            "29": 24,
            "33": 24,
            "37": 24,
            "41": 24
          }
        },
        "3": {
          "gross_income_limit": 1891,
          "monthly_usage_percentage": {
            "0": 6,
            "5": 7,
            "9": 12,
            "13": 14,
            "17": 16,
            "21": 18,
            "25": 19,
            "29": 19,
            "33": 19,
            "37": 19,
            "41": 19
          }
        },
        "4": {
          "gross_income_limit": 2338,
          "monthly_usage_percentage": {
            "0": 6,
            "5": 7,
            "9": 12,
            "13": 14,
            "17": 16,
            "21": 16,
            "25": 16,
            "29": 16,
            "33": 16,
            "37": 16,
            "41": 16
          }
        },
        "5": {
          "gross_income_limit": 2830,
          "monthly_usage_percentage": {
            "0": 6,
            "5": 7,
            "9": 12,
            "13": 14,
            "17": 14,
            "21": 14,
            "25": 14,
            "29": 14,
            "33": 14,
            "37": 14,
            "41": 14
          }
        },
        "6": {
          "gross_income_limit": 3251,
          "monthly_usage_percentage": {
            "0": 6,
            "5": 7,
            "9": 12,
            "13": 12,
            "17": 12,
            "21": 12,
            "25": 12,
            "29": 12,
            "33": 12,
            "37": 12,
            "41": 12
          }
        }
      },
      "household_size_beyond_defined_multiplier_euro": 389,
      "monthly_usage_max_payment": {
        "0": 119.66,
        "5": 538.47,
        "9": 1017.12,
        "13": 1495.76,
        "17": 1974.4,
        "21": 2453.05,
        "25": 2931.69,
        "29": 3410.33,
        "33": 3888.98,
        "37": 4367.62,
        "41": 4906.1
      },
      "safetyphone_limits": {
        "1": {
          "0": 35.68,
          "1489.01": 71.36
          },
        "2": {
          "0": 35.68,
          "2198.01": 71.36
        }
      },
      "shopping_service_prices": {
        "first_per_week": 10.40,
        "others_per_week": 11.35
      },
      "meal_service_prices": {
        "lunch": 5.25,
        "delivery": 7.4,
        "max_meals_for_single_delivery_per_week": 3
      }
    };
    // */
    // Form content
    const getFormData = () =>
      form.getFormData(this.id, this.t, {
        firstPerWeekPrice: parsedSettings.shopping_service_prices.first_per_week,
      });

    const update = () => {
      const fields = [
        { field: 'safetyphone', group: 'safetyphone_group' },
        { field: 'meal_service', group: 'meal_service_group' },
      ];

      fields.forEach(({ field, group }) => {
        if (this.calculator.getFieldValue(field) === '1') {
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
      errorMessages.push(...this.calculator.validateBasics('monthly_usage'));
      errorMessages.push(...this.calculator.validateBasics('safetyphone'));
      errorMessages.push(...this.calculator.validateBasics('shopping_service'));

      errorMessages.push(...this.calculator.validateBasics('meal_service'));
      const mealService = this.calculator.getFieldValue('meal_service');
      if (mealService === '1') {
        errorMessages.push(...this.calculator.validateBasics('meal_service_per_week'));
      }

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
      // Get fielf values for calculating.
      const householdSize = Number(this.calculator.getFieldValue('household_size'));
      const grossIncomePerMonth = Number(this.calculator.getFieldValue('gross_income_per_month'));
      const grossIncomePerMonthRaw = this.calculator.getFieldValue('gross_income_per_month');
      const monthlyUsage = Number(this.calculator.getFieldValue('monthly_usage'));
      const safetyphone = this.calculator.getFieldValue('safetyphone');
      const shoppingService = this.calculator.getFieldValue('shopping_service');
      // Meal service is set earlier
      const mealServicePerWeek = Number(this.calculator.getFieldValue('meal_service_per_week'));

      // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
      // Calculate results
      // 1. Get proper limits based on given values and the parsed settings.
      // 2. Check if user has left gross income empty --> maximum payment
      // 3. Otherwise calculate reference payment
      // 4. Check if reference payment is higher than maximum payment value --> scale to maximum payment
      // 5. Calculate optional safetyphone payment
      // 6. Calculate optional shopping service payment
      // 7. Calculate optional meal service payment
      // 8. Show receipt

      let totalExplanation = this.t('receipt_family_estimated_payment_explanation');

      // Steps 1-4 in a separate function, so that they can be used in homeCareServiceVoucher.js too
      const payment = HomeCareClientFee.calculateClientFee(
        householdSize,
        grossIncomePerMonth,
        grossIncomePerMonthRaw,
        monthlyUsage,
        this.calculator,
        parsedSettings,
        false, // Debug
      );

      // If the gross income field does not have a value, show notice on receipt about it
      if (grossIncomePerMonthRaw === null) {
        totalExplanation = this.t('receipt_family_empty_income') + totalExplanation;
      }

      const homecareSubtotal = {
        title: this.t('receipt_homecare_payment'),
        has_details: false,
        details: [],
        sum: this.t('receipt_subtotal_euros_per_month', { value: this.calculator.formatFinnishEuroCents(payment) }),
        sum_screenreader: this.t('receipt_subtotal_euros_per_month_screenreader', {
          value: this.calculator.formatEuroCents(payment),
        }),
      };

      if (householdSize >= 2) {
        homecareSubtotal.has_details = true;
        homecareSubtotal.details.push(this.t('receipt_homecare_payment_two_or_more'));
      }

      const subtotals = [homecareSubtotal];
      const additionalDetails = [];

      // 5. If safetyphone is selected, calculate value for it.
      let safetyphonePayment = 0;
      if (safetyphone === '1') {
        // Lets get the proper range (when writing, only 1 or 2 household size is used, but this approach supports larger sizes too)
        const householdSizeRange = this.calculator.getMinimumRange(householdSize, parsedSettings.safetyphone_limits);

        // If the user has not entered a value to income field, we'll calculate the value as max.
        const calculatedIncomePerMonth = grossIncomePerMonthRaw === null ? Infinity : grossIncomePerMonth;
        // Get the payment based on income and found range.
        safetyphonePayment = this.calculator.getMinimumRange(calculatedIncomePerMonth, householdSizeRange);

        // Add details to receipt
        subtotals.push({
          title: this.t('safetyphone_heading'),
          has_details: false,
          details: [],
          sum: this.t('receipt_subtotal_euros_per_month', {
            value: this.calculator.formatFinnishEuroCents(safetyphonePayment),
          }),
          sum_screenreader: this.t('receipt_subtotal_euros_per_month_screenreader', {
            value: this.calculator.formatEuroCents(safetyphonePayment),
          }),
        });
      }

      // 6. If shopping service is selected, calculate value for it.
      let shoppingPaymentPerWeek = 0;
      let shoppingPaymentPerMonth = 0;

      const shoppingServicePerWeek = 1;

      if (shoppingService === '1') {
        // Since shopping service has only one delivery per week we use the first_per_week value.
        // There used to be possibility in the calculator to have multiple deliveries per week,
        // but it was fixed to one. This is why there is a bit misleading term first_per_week.
        shoppingPaymentPerWeek = parsedSettings.shopping_service_prices.first_per_week;
        shoppingPaymentPerMonth = shoppingPaymentPerWeek * 4;

        // Add details to receipt
        subtotals.push({
          title: this.t('shopping_service_heading'),
          has_details: true,
          details: [
            this.t('receipt_shopping_service_math_single', {
              delivery_count_per_week: shoppingServicePerWeek,
              delivery_count_per_month: shoppingServicePerWeek * 4,
            }),
            this.t('receipt_shopping_service_explanation', {
              first_per_week: this.calculator.formatFinnishEuroCents(
                parsedSettings.shopping_service_prices.first_per_week,
              ),
            }),
          ],
          sum: this.t('receipt_subtotal_euros_per_month', {
            value: this.calculator.formatFinnishEuroCents(shoppingPaymentPerMonth),
          }),
          sum_screenreader: this.t('receipt_subtotal_euros_per_month_screenreader', {
            value: this.calculator.formatEuroCents(shoppingPaymentPerMonth),
          }),
        });
      }

      // 7. If meal service is selected, calculate value for it.
      let mealPaymentPerWeek = 0;
      let deliveriesPerWeek = 0;
      let mealPaymentPerMonth = 0;
      if (mealService === '1') {
        // Calculate meal price
        mealPaymentPerWeek = mealServicePerWeek * parsedSettings.meal_service_prices.lunch;

        // Delivery price is based on meal amount per week, either 1 or 2 deliveries
        if (mealServicePerWeek <= parsedSettings.meal_service_prices.max_meals_for_single_delivery_per_week) {
          deliveriesPerWeek = 1;
        } else {
          deliveriesPerWeek = 2;
        }

        mealPaymentPerMonth = mealPaymentPerWeek * 4;

        // Add details to receipt
        subtotals.push({
          title: this.t('meal_service_heading'),
          has_details: true,
          details: [
            this.t(
              mealServicePerWeek === 1 ? 'receipt_meal_service_count_single' : 'receipt_meal_service_count_multiple',
              {
                meals_per_week: mealServicePerWeek,
                meals_per_month: mealServicePerWeek * 4,
              },
            ),
            this.t('receipt_meal_service_price', {
              meal_service_price: this.calculator.formatFinnishEuroCents(parsedSettings.meal_service_prices.lunch),
              meal_deliveries_per_week: deliveriesPerWeek,
              meal_deliveries_per_month: deliveriesPerWeek * 4,
            }),
            this.t('receipt_meal_service_delivery_price'),
          ],
          sum: this.t('receipt_subtotal_euros_per_month', {
            value: this.calculator.formatFinnishEuroCents(mealPaymentPerMonth),
          }),
          sum_screenreader: this.t('receipt_subtotal_euros_per_month_screenreader', {
            value: this.calculator.formatEuroCents(mealPaymentPerMonth),
          }),
        });
      }

      // console.log(
      //     'maximumPayment', maximumPayment,
      //     '\ngrossIncomeLimit', grossIncomeLimit,
      //     '\npaymentPercentage', paymentPercentage,
      //     '\nreferencePayment', referencePayment,
      //     '\n',
      //     '\npayment', payment,
      //     '\nsafetyphonePayment', safetyphonePayment,
      //     '\nshoppingPaymentPerMonth', shoppingPaymentPerMonth, `(${shoppingPaymentPerWeek} € * 4 weeks)`,
      //     '\nmealPaymentPerMonth', mealPaymentPerMonth, `(${mealPaymentPerWeek} € * 4 weeks)`,
      //   );

      const sum = payment + safetyphonePayment + shoppingPaymentPerMonth + mealPaymentPerMonth;

      const receiptData = {
        id: this.id,
        title: this.t('receipt_estimate_of_payment'),
        total_prefix: this.t('receipt_family_estimated_payment_prefix'),
        total_value: this.calculator.formatFinnishEuroCents(sum),
        total_suffix: this.t('receipt_family_estimated_payment_suffix'),
        total_explanation: totalExplanation,
        hr: true,
        breakdown: {
          title: this.t('receipt_estimate_is_based_on'),
          subtotals,
          additional_details: additionalDetails,
        },
      };

      const receipt = this.calculator.getPartialRender('{{>receipt}}', receiptData);

      return {
        receipt,
        ariaLive: this.t('receipt_aria_live', { payment: sum }),
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
        // this.calculator.clearResult();
      },
      change: () => {
        update();
        // this.calculator.clearResult();
        // validate();
      },
      reset: () => {
        window.setTimeout(update, 1);
        this.calculator.clearResult();
        this.calculator.showAriaLiveText(this.t('reset_aria_live'));
      },
    };

    // Prepare calculator for translations
    this.calculator = window.helfiCalculator({ name: 'homeCareClientFee', translations });

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
window.helfiCalculator.home_care_client_fee = (id, settings) => new HomeCareClientFee(id, settings);

export default HomeCareClientFee.calculateClientFee;
