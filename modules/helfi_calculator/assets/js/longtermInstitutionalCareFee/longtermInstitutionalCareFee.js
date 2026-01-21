import form from './_form';
import translations from './_translations';

class LongtermInstitutionalCareFee {
  constructor(id, settings) {
    this.id = id;

    const parsedSettings = JSON.parse(settings);

    // Expecting settings to follow this JSON format:
    /*
    const parsedSettings = {
      "payment_percentage_high": 0.85,
      "payment_percentage_low": 0.425,
      "minimum_funds": 131.00,
      "basic_amount": 539.55,
      "minimum_funds_spouse": 724.55,
      "maximum_payment_social_welfare_act": 7656.00,
      "maximum_payment_other": 12045.00
    };
    // */
    // Form content
    const getFormData = () => form.getFormData(this.id, this.t);

    const update = () => {
      const fields = [{ field: 'has_spouse', group: 'spouse_income_group' }];

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

      // Validate required fields
      errorMessages.push(...this.calculator.validateBasics('social_welfare_act'));
      errorMessages.push(...this.calculator.validateBasics('earned_income'));
      errorMessages.push(...this.calculator.validateBasics('client_benefits'));
      errorMessages.push(...this.calculator.validateBasics('capital_income'));
      errorMessages.push(...this.calculator.validateBasics('annual_forest_income'));
      errorMessages.push(...this.calculator.validateBasics('guardianship_fees'));
      errorMessages.push(...this.calculator.validateBasics('client_foreclosure'));
      errorMessages.push(...this.calculator.validateBasics('compensation_or_life_annuity'));
      errorMessages.push(...this.calculator.validateBasics('maintenance_payments'));
      errorMessages.push(...this.calculator.validateBasics('has_spouse'));

      const hasSpouse = this.calculator.getFieldValue('has_spouse');

      if (hasSpouse === 'true') {
        errorMessages.push(...this.calculator.validateBasics('spouse_earned_income'));
        errorMessages.push(...this.calculator.validateBasics('spouse_client_benefits'));
        errorMessages.push(...this.calculator.validateBasics('spouse_capital_income'));
        errorMessages.push(...this.calculator.validateBasics('spouse_annual_forest_income'));
        errorMessages.push(...this.calculator.validateBasics('spouse_guardianship_fees'));
        errorMessages.push(...this.calculator.validateBasics('spouse_client_foreclosure'));
        errorMessages.push(...this.calculator.validateBasics('spouse_compensation_or_life_annuity'));
        errorMessages.push(...this.calculator.validateBasics('spouse_maintenance_payments'));
      }

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
      const socialWelfareAct = this.calculator.getFieldValue('social_welfare_act');

      // Client income
      const earnedIncome = Number(this.calculator.getFieldValue('earned_income'));
      const clientBenefits = Number(this.calculator.getFieldValue('client_benefits'));
      const capitalIncome = Number(this.calculator.getFieldValue('capital_income'));
      const annualForestIncome = Number(this.calculator.getFieldValue('annual_forest_income'));

      // Client deductions
      const guardianshipFees = Number(this.calculator.getFieldValue('guardianship_fees'));
      const clientForeclosure = Number(this.calculator.getFieldValue('client_foreclosure'));
      const compensationOrLifeAnnuity = Number(this.calculator.getFieldValue('compensation_or_life_annuity'));
      const maintenancePayments = Number(this.calculator.getFieldValue('maintenance_payments'));

      // Spouse income
      const spouseEarnedIncome = Number(this.calculator.getFieldValue('spouse_earned_income'));
      const spouseClientBenefits = Number(this.calculator.getFieldValue('spouse_client_benefits'));
      const spouseCapitalIncome = Number(this.calculator.getFieldValue('spouse_capital_income'));
      const spouseAnnualForestIncome = Number(this.calculator.getFieldValue('spouse_annual_forest_income'));

      // Spouse deductions
      const spouseGuardianshipFees = Number(this.calculator.getFieldValue('spouse_guardianship_fees'));
      const spouseClientForeclosure = Number(this.calculator.getFieldValue('spouse_client_foreclosure'));
      const spouseCompensationOrLifeAnnuity = Number(
        this.calculator.getFieldValue('spouse_compensation_or_life_annuity'),
      );
      const spouseMaintenancePayments = Number(this.calculator.getFieldValue('spouse_maintenance_payments'));

      // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
      // Calculate results:
      // 1. Get total income, deductions and net income for client and spouse
      // 2. Calculate total payment

      //Calculate forest income
      const annualForestIncomeActual = (annualForestIncome / 12) * 0.9;

      //1. Total income for client
      const totalIncomeClient = earnedIncome + clientBenefits + capitalIncome + annualForestIncomeActual;
      const totalDeductionsClient =
        guardianshipFees + clientForeclosure + compensationOrLifeAnnuity + maintenancePayments;
      const clientNetIncome = totalIncomeClient - totalDeductionsClient;

      //1. Total income for spouse
      const spouseAnnualForestIncomeActual = hasSpouse === 'true' ? (spouseAnnualForestIncome / 12) * 0.9 : 0;
      const totalIncomeSpouse =
        hasSpouse === 'true'
          ? spouseEarnedIncome + spouseClientBenefits + spouseCapitalIncome + spouseAnnualForestIncomeActual
          : 0;
      const totalDeductionsSpouse =
        hasSpouse === 'true'
          ? spouseGuardianshipFees +
            spouseClientForeclosure +
            spouseCompensationOrLifeAnnuity +
            spouseMaintenancePayments
          : 0;
      const spouseNetIncome = totalIncomeSpouse - totalDeductionsSpouse;

      let paymentPercentage = 0;
      let maximumPayment = 0;

      if (totalIncomeClient <= totalIncomeSpouse) {
        paymentPercentage = parsedSettings.payment_percentage_high;
      } else {
        paymentPercentage = parsedSettings.payment_percentage_low;
      }

      if (socialWelfareAct === 'true') {
        maximumPayment = parsedSettings.maximum_payment_social_welfare_act;
      } else {
        maximumPayment = parsedSettings.maximum_payment_other;
      }

      let totalPayment = 0;
      let disposableAmount = 0;
      let disposableAmountCombined = 0;

      if (paymentPercentage === parsedSettings.payment_percentage_high) {
        totalPayment = clientNetIncome * paymentPercentage;
        disposableAmount = clientNetIncome - totalPayment;
        disposableAmountCombined = clientNetIncome + spouseNetIncome - totalPayment;
        if (disposableAmount < parsedSettings.minimum_funds) {
          totalPayment = clientNetIncome - parsedSettings.minimum_funds;
          disposableAmount = parsedSettings.minimum_funds;
        }
      } else {
        const combinedIncome = clientNetIncome + spouseNetIncome;
        totalPayment = combinedIncome * paymentPercentage;
        disposableAmount = clientNetIncome - totalPayment;
        disposableAmountCombined = combinedIncome - totalPayment;
        if (disposableAmount < parsedSettings.minimum_funds_spouse) {
          totalPayment = combinedIncome - parsedSettings.minimum_funds_spouse;
          disposableAmount = parsedSettings.minimum_funds;
          disposableAmountCombined = parsedSettings.minimum_funds_spouse;
        }
      }

      // Clamp payment between 0 and max payment, round to even eurocents
      totalPayment = this.calculator.clamp(0, Math.round(totalPayment * 100) / 100, maximumPayment);

      const subtotals = [];

      subtotals.push({
        title: this.t('subtotal_total_income_client'),
        has_details: false,
        sum: this.t('receipt_subtotal_euros_per_month', {
          value: this.calculator.formatFinnishEuroCents(totalIncomeClient),
        }),
      });

      subtotals.push({
        title: this.t('subtotal_total_deductions_client'),
        has_details: false,
        sum: this.t('receipt_subtotal_euros_per_month', {
          value: this.calculator.formatFinnishEuroCents(totalDeductionsClient),
        }),
      });

      if (hasSpouse === 'true') {
        subtotals.push({
          title: this.t('subtotal_total_income_spouse'),
          has_details: false,
          sum: this.t('receipt_subtotal_euros_per_month', {
            value: this.calculator.formatFinnishEuroCents(totalIncomeSpouse),
          }),
        });

        subtotals.push({
          title: this.t('subtotal_total_deductions_spouse'),
          has_details: false,
          sum: this.t('receipt_subtotal_euros_per_month', {
            value: this.calculator.formatFinnishEuroCents(totalDeductionsSpouse),
          }),
        });
      }

      if (paymentPercentage === parsedSettings.payment_percentage_high) {
        subtotals.push({
          title: this.t('subtotal_minimum_disposable_amount'),
          has_details: false,
          sum: this.t('receipt_subtotal_euros_per_month', {
            value: this.calculator.formatFinnishEuroCents(disposableAmount),
          }),
        });
      }

      if (hasSpouse === 'true' && paymentPercentage === parsedSettings.payment_percentage_low) {
        subtotals.push({
          title: this.t('subtotal_minimum_disposable_amount_with_spouse', {
            amount: disposableAmountCombined,
            secondAmount: 0,
          }),
          has_details: true,
          details: [
            this.t('subtotal_minimum_disposable_amount_with_spouse_details', {
              disposable_amount: this.calculator.formatFinnishEuroCents(disposableAmountCombined),
              minimum_funds: this.calculator.formatFinnishEuroCents(parsedSettings.minimum_funds),
              basic_amount: this.calculator.formatFinnishEuroCents(parsedSettings.basic_amount),
            }),
          ],
          sum: this.t('receipt_subtotal_euros_per_month', {
            value: this.calculator.formatFinnishEuroCents(disposableAmountCombined),
          }),
        });
      }

      const additionalDetails = [];

      additionalDetails.push({
        title: this.t('receipt_additional_details'),
        text: null,
      });

      if (paymentPercentage === parsedSettings.payment_percentage_high) {
        additionalDetails.push({
          title: null,
          text: this.t('additional_detail_spouse_higher_income'),
        });
      } else {
        additionalDetails.push({
          title: null,
          text: this.t('additional_detail_lower_income'),
        });
      }

      additionalDetails.push({
        title: null,
        text: this.t('additional_detail_forest_income', {
          maximumPayment: this.calculator.formatFinnishEuroCents(maximumPayment),
        }),
      });

      additionalDetails.push({
        title: null,
        text: this.t('additional_detail_social_welfare_act', {
          maximumPayment: this.calculator.formatFinnishEuroCents(maximumPayment),
        }),
      });

      // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

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
            additional_details: additionalDetails,
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
        event.preventDefault();
        this.calculator.clearResult();
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

    // Initialize calculator
    this.calculator = window.helfiCalculator({
      name: 'LongtermInstitutionalCareFee',
      translations,
    });

    // Translation shortcut
    this.t = (key, value) => this.calculator.translate(key, value);

    // Parse settings
    this.settings = this.calculator.parseSettings(settings);

    // Init
    this.calculator.init({
      id,
      formData: getFormData(),
      eventHandlers,
    });
  }
}

// Expose to global scope
window.helfiCalculator = window.helfiCalculator || {};
window.helfiCalculator.long_term_institutional_care_fee = (id, settings) =>
  new LongtermInstitutionalCareFee(id, settings);
