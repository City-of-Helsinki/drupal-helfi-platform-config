import form from './_form';
import translations from './_translations';

class HelsinkiBenefitAmountEstimate {
  constructor(id, settings) {
    this.id = id;
    const config = JSON.parse(settings);

    const checkConfiguration = () => {
      const NEEDED_CONFIG_KEYS = [
        'HELSINKI_BENEFIT_MAX_AMOUNT',
        'STATE_AID_PERCENTAGES',
        'SALARY_OTHER_EXPENSES_PERCENTAGE',
      ];
      if (!NEEDED_CONFIG_KEYS.every((setting) => Object.keys(config).includes(setting))) {
        console.error('Missing Drupal settings. Calculator won´t work!');
      }
    };

    checkConfiguration();

    const getFormData = () => form.getFormData(this.id, this.t);

    const isEmployeeAssociation = () => this.calculator.getFieldValue('company_type') === 'association';

    const isEmployeeBusiness = () => this.calculator.getFieldValue('company_type') === 'business';

    const hasBusinessActivities = () =>
      isEmployeeAssociation() && this.calculator.getFieldValue('association_has_business_activities');

    const getMonthlyPay = () => parseInt(this.calculator.getFieldValue('monthly_pay'), 10);

    const getVacationMoney = () => {
      const vacationMoney = parseInt(this.calculator.getFieldValue('vacation_money'), 10);
      return Number.isNaN(vacationMoney) ? 0 : vacationMoney;
    };

    const setVacationMoneyMax = () => {
      this.calculator.getElement('vacation_money').dataset.max = Math.floor(getMonthlyPay() / 2 / 11);
    };

    const getOtherExpenses = () => config.SALARY_OTHER_EXPENSES_PERCENTAGE * getMonthlyPay();

    const getSalaryWithExpenses = () =>
      [getMonthlyPay(), getVacationMoney()].reduce(
        (acc, value) => parseInt(acc, 10) + parseInt(value, 10),
        getOtherExpenses(),
      );

    const getStateAidPercentage = () => {
      // Associations with business activities are treated like businesses
      if (isEmployeeBusiness() || hasBusinessActivities()) {
        return config.STATE_AID_PERCENTAGES[1];
      }
      // Associations always get the highest state aid percentage
      return config.STATE_AID_PERCENTAGES[0];
    };

    const getStateAidAmount = () => getStateAidPercentage() * getSalaryWithExpenses();

    const getHelsinkiBenefitAmount = () => getStateAidAmount();

    const formatCurrency = (number) => this.calculator.formatFinnishEuroCents(number);

    const debugUpdate = () => {
      if (process.env.NODE_ENV === 'development') {
        const data = {
          isEmployeeAssociation: isEmployeeAssociation(),
          isEmployeeBusiness: isEmployeeBusiness(),
          hasBusinessActivities: hasBusinessActivities(),
          monthlyPay: getMonthlyPay(),
          vacationMoney: getVacationMoney(),
          otherExpenses: getOtherExpenses(),
          allExpenses: getSalaryWithExpenses(),
          stateAidPercentage: getStateAidPercentage(),
          stateAidAmount: getStateAidAmount(),
          helsinkiBenefitAmount: getHelsinkiBenefitAmount(),
        };
        console.debug('\n\n###################');
        Object.keys(data).forEach((key) => {
          const value = data[key];
          console.debug(key, value);
        });
      }
    };

    const update = () => {
      if (isEmployeeAssociation()) {
        this.calculator.showGroup('association_has_business_activities_true_group');
      } else {
        this.calculator.hideGroup('association_has_business_activities_true_group');
      }

      if (getMonthlyPay() > 0) {
        setVacationMoneyMax();
      }

      debugUpdate();
    };

    const validate = () => {
      const errorMessages = [];

      errorMessages.push(...this.calculator.validateBasics('company_type'));
      errorMessages.push(...this.calculator.validateBasics('monthly_pay'));
      errorMessages.push(...this.calculator.validateBasics('vacation_money'));
      if (!this.calculator.getFieldValue('pay_subsidy_granted')) {
        errorMessages.push(...this.calculator.getError('pay_subsidy_granted', 'select_radio'));
      }

      if (errorMessages.length) {
        return {
          error: {
            title: this.t('missing_input'),
            message: errorMessages,
          },
        };
      }
      const helsinkiBenefitResult = getHelsinkiBenefitAmount();

      if (Number.isNaN(helsinkiBenefitResult)) {
        return {
          error: {
            title: this.t('error_calculation_title'),
            message: this.t('error_calculation_message'),
          },
        };
      }

      const subtotals = {
        title: this.t('subtotal_title'),
        has_details: true,
        details: [
          this.t('subtotal_details_1', { value: formatCurrency(getMonthlyPay()) }),
          this.t('subtotal_details_2', { value: formatCurrency(getVacationMoney()) }),
          this.t('subtotal_details_3', {
            value: formatCurrency(getOtherExpenses()),
            percentage: config.SALARY_OTHER_EXPENSES_PERCENTAGE * 100,
          }),
        ],
      };

      const receiptData = {
        id: this.id,
        title: this.t('total_title'),
        total_prefix: this.t('total_prefix'),
        // Total value is always at least zero but no more than HELSINKI_BENEFIT_MAX_AMOUNT
        total_value: formatCurrency(
          Math.max(0, Math.min(helsinkiBenefitResult, config.HELSINKI_BENEFIT_MAX_AMOUNT)),
        ),
        total_suffix: this.t('total_suffix'),
        total_explanation: this.t('total_explanation'),
        hr: true,
        breakdown: [
          {
            title: this.t('breakdown_title'),
            subtotals,
          },
        ],
      };

      const receipt = this.calculator.getPartialRender('{{>receipt}}', receiptData);

      return {
        receipt,
        ariaLive: this.t('receipt_aria_live', { payment: 1234 }),
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
    this.calculator = window.helfiCalculator({ name: 'helsinkiBenefitAmountEstimate', translations });

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
window.helfiCalculator.helsinki_benefit_amount_estimate = (id, settings) =>
  new HelsinkiBenefitAmountEstimate(id, settings);
