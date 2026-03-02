const translations = {
  calculator_instructions: {
    fi: 'Täytä laskuriin nettotulosi ja vähennystietoja siltä osin kuin niitä sinulla on ilmoitettavana.',
    sv: 'Lämna dina uppgifter om inkomster och avdrag i räknaren, såvitt du har sådana.',
    en: 'Fill in your net income and any deductible items in the calculator, where applicable.',
  },
  net_income_heading: {
    fi: 'Nettotulot',
    sv: 'Nettoinkomster',
    en: 'Net income',
  },
  earned_income: {
    fi: 'Ansiotulot',
    sv: 'Förvärvsinkomster',
    en: 'Earned income',
  },
  earned_income_explanation: {
    fi: 'Asiakkaan yhteenlasketut palkkatulot, ammatinharjoittamisesta tai yritystoiminnasta saadut tulot tai omaishoidon tuet verojen vähentämisen jälkeen (nettotulot) kuukaudessa.',
    sv: 'Klientens sammanlagda månatliga löneinkomster, inkomster från yrkesutövning eller näringsverksamhet eller stöd för närståendevård efter skatt (nettoinkomster) per månad.',
    en: "The client's total monthly wages, income from self-employment or business activities, or informal care support, after taxes (net income) per month.",
  },
  client_benefits: {
    fi: 'Etuudet',
    sv: 'Förmåner',
    en: 'Benefits',
  },
  client_benefits_explanation: {
    fi: 'Asiakkaan yhteenlaskettu työeläke, kansaneläke, perhe-eläke, takuueläke, ulkomaan eläke, ansiosidonnaiset työttömyyspäivärahat ja työmarkkinatuki, sairauspäivärahat, veroista vapaat tulot, opintoraha tai aikuiskoulutustuki, eläkettä saavan hoitotuki, lasten kotihoidon tuki, elatusapu tai -tuki, vammaistuki ja päivärahat (esim. sairaus-, työttömyys- tai vanhempainrahat) verojen vähentämisen jälkeen (nettotulot) kuukaudessa.',
    sv: 'Klientens sammanlagda arbetspension, folkpension, familjepension, garantipension, utländska pensioner, inkomstrelaterade arbetslöshetsdagpenning och arbetsmarknadsstöd, sjukdagpenning, skattefria inkomster, studiestöd, vårdbidrag för pensionstagare, hemvårdsstöd för barn, underhållsbidrag eller -stöd, handikappbidrag och dagpenningar (för sjukdom eller föräldraledighet) efter skatt (nettoinkomster) per månad.',
    en: 'The client’s total monthly earnings-related pension, national pension, survivors’ pension, guarantee pension, foreign pension, earnings-related unemployment benefits and labour market subsidy, sickness allowance, tax-exempt income, student financial aid or adult education allowance, care allowance for pensioners, child home care allowance, maintenance support or maintenance allowance, disability allowance, and daily allowances (e.g. sickness, unemployment or parental allowances) after taxes (net income) per month.',
  },
  capital_income: {
    fi: 'Pääomatulot',
    sv: 'Kapitalinkomster',
    en: 'Capital income',
  },
  capital_income_explanation: {
    fi: 'Asiakkaan yhteenlasketut osinko-, vuokra- tai korkotulot, elinkorot ja muut pääomatulot ennen verojen vähentämistä kuukaudessa. Pääomatulon määrän voi tarkistaa edellisvuoden verotuspäätöksestä.',
    sv: 'Klientens sammanlagda dividendinkomster, hyresinkomster eller ränteinkomster, livränta och övriga kapitalinkomster per månad, före avdrag. Kapitalinkomsterna kan ses på beskattningsbeslutet från det föregående skatteåret.',
    en: 'The client’s total monthly dividends, rental income, interest income, life annuities and other forms of capital income, before tax. The amount of capital income can be checked in the previous year’s tax decision.',
  },
  annual_forest_income: {
    fi: 'Metsän vuotuinen tuotto',
    sv: 'Årlig avkastning på skog',
    en: 'Annual forest income',
  },
  annual_forest_income_explanation: {
    fi: 'Asiakkaan omistamien metsien yhteenlaskettu vuotuinen tuotto ennen verojen vähentämistä vuodessa. Metsän keskimääräisen tuoton voi tarkastaa verottajan sivuilta aluekohtaisesti.',
    sv: 'Den genomsnittliga årliga avkastningen på skog som klienten äger, före skatter. På Skatteförvaltningens webbplats kan man kolla den genomsnittliga avkastningen på skog enligt område.',
    en: 'The client’s total annual forest income before tax. The average regional forest yield can be checked on the Tax Administration’s website.',
  },
  deductions_heading: {
    fi: 'Vähennykset',
    sv: 'Avdrag',
    en: 'Deductions',
  },
  guardianship_fees: {
    fi: 'Edunvalvontamaksut',
    sv: 'Intressebevakningsavgift',
    en: 'Guardianship fees',
  },
  guardianship_fees_explanation: {
    fi: 'Asiakkaan yhteenlasketut edunvalvojan palkkion perusmaksu ja edunvalvontavaltuutetun palkkiot kuukaudessa. Edunvalvontamaksu huomioidaan vähennyksenä enintään edunvalvojan palkkion perusmaksun suuruisena. Perusmaksu on ${guardianship_fee} euroa kuukaudessa vuonna 2026.',
    sv: 'Klientens sammanlagda avgift för intressebevakarens arvode och intressebevakningsombudets arvode per månad. Intressebevakningsavgiften beaktas som avdrag högst till beloppet av intressebevakararvodet. Basavgiften är ${guardianship_fee} euro i månaden under 2026.',
    en: 'The client’s total monthly basic fee charged by the guardian and fees charged by an authorised representative. The guardianship fee is taken into account as a deduction up to the amount of the guardian’s basic fee. The basic fee is €${guardianship_fee} per month in 2026.',
  },
  client_foreclosure: {
    fi: 'Ulosmittaus',
    sv: 'Utmätning',
    en: 'Foreclosure',
  },
  client_foreclosure_explanation: {
    fi: 'Asiakkaan ulosmittauksen määrä kuukaudessa.',
    sv: 'Klientens belopp som utmäts per månad.',
    en: 'The client’s monthly amount of foreclosure.',
  },
  compensation_or_life_annuity: {
    fi: 'Hyvitys tai syytinki',
    sv: 'Gottgörelse eller sytning',
    en: 'Compensation or life annuity',
  },
  compensation_or_life_annuity_explanation: {
    fi: 'Asiakkaan osuus avopuolisoiden yhteistalouden purkamisesta annetussa laissa tarkoitettu hyvitys tai syytinki kuukaudessa.',
    sv: 'Klientens andel av månatlig gottgörelse eller sytning som avses i lagen om upplösning av sambors gemensamma hushåll.',
    en: 'Compensation or life annuity under the Act on the Dissolution of the Household of Cohabiting Partners, paid monthly by the client.',
  },
  maintenance_payments: {
    fi: 'Elatusapu',
    sv: 'Underhållsbidrag',
    en: 'Maintenance payments',
  },
  maintenance_payments_explanation: {
    fi: 'Asiakkaan maksaman elatusavun yhteenlaskettu määrä kuukaudessa.',
    sv: 'Totalbeloppet underhållsbidrag som klienten betalar månatligen.',
    en: 'The client’s total monthly child maintenance payments.',
  },
  medication_costs: {
    fi: 'Lääkekulut',
    sv: 'Läkemedelsutgifter',
    en: 'Medication costs',
  },
  medication_costs_explanation: {
    fi: 'Keskimääräiset kulut muista kuin SV-korvattavista lääkkeistä, kliinisistä ravintovalmisteista ja perusvoiteista.',
    sv: 'Genomsnittskostnad för läkemedel som inte ersätts med stöd av sjukförsäkringslagen, kliniska näringspreparat och baskrämer.',
    en: 'Average costs of medicines not reimbursed under the Finnish Health Insurance Act, clinical nutritional supplements, and basic ointments.',
  },
  share_of_housing_costs: {
    fi: 'Asumiskulujen omavastuu',
    sv: 'Självrisk för boendekostnader',
    en: "Client's share of housing costs",
  },
  share_of_housing_costs_explanation: {
    fi: 'Asumispalvelun vuokra, josta vähennetty Kelan asumistuki.',
    sv: 'Hyra för boendeservice varifrån FPA:s bostadsbidrag dragits av.',
    en: 'The rent for the assisted living service, minus any housing allowance from Kela.',
  },
  label_has_spouse: {
    fi: 'Onko asiakkaalla puolisoa?',
    sv: 'Är klienten gift?',
    en: 'Does the client have a spouse?',
  },
  label_yes: {
    fi: 'Kyllä',
    sv: 'Ja',
    en: 'Yes',
  },
  label_no: {
    fi: 'Ei',
    sv: 'Nej',
    en: 'No',
  },
  spouse_earned_income: {
    fi: 'Puolison ansiotulot',
    sv: 'Makens förvärvsinkomster',
    en: "Spouse's earned income",
  },
  spouse_earned_income_explanation: {
    fi: 'Yhteenlasketut palkkatulot, ammatinharjoittamisesta tai yritystoiminnasta saadut tulot tai omaishoidon tuet verojen vähentämisen jälkeen (nettotulot) kuukaudessa.',
    sv: 'Sammanlagda månatliga löneinkomster, inkomster från yrkesutövning eller näringsverksamhet eller stöd för närståendevård efter skatt (nettoinkomster) per månad.',
    en: 'Total monthly wages, income from self-employment or business activities, or informal care support, after taxes (net income) per month.',
  },
  spouse_client_benefits: {
    fi: 'Puolison etuudet',
    sv: 'Makens förmåner',
    en: "Spouse's benefits",
  },
  spouse_client_benefits_explanation: {
    fi: 'Yhteenlaskettu työeläke, kansaneläke, perhe-eläke, takuueläke, ulkomaan eläke, ansiosidonnaiset työttömyyspäivärahat ja työmarkkinatuki, sairauspäivärahat, veroista vapaat tulot, opintoraha tai aikuiskoulutustuki, eläkettä saavan hoitotuki, lasten kotihoidon tuki, elatusapu tai -tuki, vammaistuki ja päivärahat (esim. sairaus-, työttömyys- tai vanhempainrahat) verojen vähentämisen jälkeen (nettotulot) kuukaudessa.',
    sv: 'Sammanlagd arbetspension, folkpension, familjepension, garantipension, utländska pensioner, inkomstrelaterad arbetslöshetsdagpenning och arbetsmarknadsstöd, sjukdagpenning, skattefria inkomster, studiestöd, vårdbidrag för pensionstagare, hemvårdsstöd för barn, underhållsbidrag eller -stöd, handikappbidrag och dagpenningar (för sjukdom eller föräldraledighet, efter skatt (nettoinkomster) per månad.',
    en: 'Total monthly amounts of the spouse’s earnings-related pension, national pension, survivors’ pension, guarantee pension, foreign pension, earnings-related unemployment benefits and labour market subsidy, sickness allowance, tax-exempt income, student financial aid or adult education allowance, care allowance for pensioners, child home care allowance, maintenance payments or support, disability allowance, and daily allowances (e.g., sickness, unemployment, or parental allowances) after taxes (net income) per month.',
  },
  spouse_capital_income: {
    fi: 'Puolison pääomatulot',
    sv: 'Makens kapitalinkomster',
    en: "Spouse's capital income",
  },
  spouse_capital_income_explanation: {
    fi: 'Yhteenlasketut osinko-, vuokra- tai korkotulot, elinkorot ja muut pääomatulot ennen verojen vähentämistä kuukaudessa. Pääomatulon määrän voi tarkistaa edellisvuoden verotuspäätöksestä.',
    sv: 'Sammanlagda dividendinkomster, hyresinkomster eller ränteinkomster, livränta och övriga kapitalinkomster per månad, före avdrag. Kapitalinkomsterna kan ses på beskattningsbeslutet från det föregående skatteåret.',
    en: 'Total monthly amounts of the above capital income, before tax. The amount can be checked in the previous year’s tax decision.',
  },
  spouse_annual_forest_income: {
    fi: 'Puolison metsän vuotuinen tuotto',
    sv: 'Makens årliga avkastning på skog',
    en: "Spouse's annual forest income",
  },
  spouse_annual_forest_income_explanation: {
    fi: 'Metsäomistuksien yhteenlaskettu vuotuinen tuotto ennen verojen vähentämistä vuodessa. Metsän keskimääräisen tuoton voi tarkastaa verottajan sivuilta aluekohtaisesti.',
    sv: 'Den genomsnittliga årliga avkastningen på skogsegendom, före skatter. På Skatteförvaltningens webbplats kan man kolla den genomsnittliga avkastningen på skog enligt område.',
    en: 'Total annual forest income before tax. The average regional forest yield can be checked on the Tax Administration’s website.',
  },
  spouse_guardianship_fees: {
    fi: 'Puolison edunvalvontamaksut',
    sv: 'Makens intressebevakningsavgift',
    en: "Spouse's guardianship fees",
  },
  spouse_guardianship_fees_explanation: {
    fi: 'Yhteenlasketut edunvalvojan palkkion perusmaksu ja edunvalvontavaltuutetun palkkiot kuukaudessa. Edunvalvontamaksu huomioidaan vähennyksenä enintään edunvalvojan palkkion perusmaksun suuruisena. Perusmaksu on 43,34 euroa kuukaudessa vuonna 2026.',
    sv: 'Avgift för intressebevakars arvode och intressebevakningsombudets arvode per månad. Intressebevakningsavgiften beaktas som avdrag högst till beloppet av intressebevakararvodet. Basavgiften är 43,34 euro i månaden under 2026.',
    en: 'Total monthly basic fee charged by the guardian and fees charged by an authorised representative. The guardianship fee is taken into account as a deduction up to the amount of the guardian’s basic fee. The basic fee is €43.34 per month in 2026.',
  },
  spouse_client_foreclosure: {
    fi: 'Puolison ulosmittaus',
    sv: 'Makens utmätning',
    en: "Spouse's foreclosure",
  },
  spouse_client_foreclosure_explanation: {
    fi: 'Ulosmittauksen määrä kuukaudessa.	',
    sv: 'Belopp som utmäts per månad.',
    en: 'Monthly amount of foreclosure.',
  },
  spouse_compensation_or_life_annuity: {
    fi: 'Puolison hyvitys tai syytinki',
    sv: 'Makens gottgörelse eller sytning',
    en: "Spouse's compensation or life annuity",
  },
  spouse_compensation_or_life_annuity_explanation: {
    fi: 'Avopuolisoiden yhteistalouden purkamisesta annetussa laissa tarkoitettu hyvitys tai syytinki kuukaudessa.',
    sv: 'Månatlig gottgörelse eller sytning som avses i lagen om upplösning av sambors gemensamma hushåll.',
    en: 'Monthly compensation or life annuity under the same Act.',
  },
  spouse_maintenance_payments: {
    fi: 'Puolison elatusapu',
    sv: 'Makens underhållsbidrag',
    en: "Spouse's maintenance payments",
  },
  spouse_maintenance_payments_explanation: {
    fi: 'Suoritettavan elatusavun yhteenlaskettu määrä kuukaudessa.',
    sv: 'Totalbeloppet underhållsbidrag som betalas månatligen.',
    en: 'Total monthly child maintenance payments paid.',
  },
  spouse_medication_costs: {
    fi: 'Puolison lääkekulut',
    sv: 'Makens läkemedelsutgifter',
    en: "Spouse's medication costs",
  },
  spouse_medication_costs_explanation: {
    fi: 'Keskimääräiset kulut muista kuin SV-korvattavista lääkkeistä, kliinisistä ravintovalmisteista ja perusvoiteista.',
    sv: 'Genomsnittskostnad för läkemedel som inte ersätts med stöd av sjukförsäkringslagen, kliniska näringspreparat och baskrämer.',
    en: 'Average costs of medicines not reimbursed under the Finnish Health Insurance Act, clinical nutritional supplements, and basic ointments.',
  },
  spouse_share_of_housing_costs: {
    fi: 'Puolison asumiskulujen omavastuu',
    sv: 'Makens självrisk för boendekostnader',
    en: "Spouse's share of housing costs",
  },
  spouse_share_of_housing_costs_explanation: {
    fi: 'Asumispalvelun vuokra, josta vähennetty Kelan asumistuki.',
    sv: 'Hyra för boendeservice varifrån FPA:s bostadsbidrag dragits av.',
    en: 'The rent for the assisted living service, minus any housing allowance from Kela.',
  },
  subtotal_total_income_client: {
    fi: 'Asiakkaan nettotulot',
    sv: 'Klientens nettoinkomster',
    en: 'Client’s net income',
  },
  subtotal_total_deductions_client: {
    fi: 'Asiakkaan vähennykset',
    sv: 'Klientens avdrag',
    en: "Client's deductions",
  },
  subtotal_total_income_spouse: {
    fi: 'Puolison nettotulot',
    sv: 'Makens nettoinkomster',
    en: 'Spouse’s net income',
  },
  subtotal_total_deductions_spouse: {
    fi: 'Puolison vähennykset',
    sv: 'Makens avdrag',
    en: "Spouse's deductions",
  },
  spouse_net_income_heading: {
    fi: 'Puolison nettotulot',
    sv: 'Makens nettoinkomster',
    en: "Spouse's net income",
  },
  spouse_deductions_heading: {
    fi: 'Puolison vähennykset',
    sv: 'Makens avdrag',
    en: "Spouse's deductions",
  },
  spouse_net_income_paragraph: {
    fi: 'Mikäli asiakkaan tulot ovat pienemmät tai yhtä suuret kuin mahdollisella puolisolla, puolison tuloja ei oteta huomioon asiakasmaksua määriteltäessä.',
    sv: 'Om klientens inkomster är mindre än eller lika stora som makens, beaktas makens inkomster inte då klientavgiften fastställs.',
    en: "If the client's income is lower than or equal to that of their spouse, the spouse's income is not considered when determining the fee.",
  },
  receipt_estimate_of_payment: {
    fi: 'Arvio koostuu seuraavista tiedoista',
    sv: 'Uppskattad klientavgift för långvarig sluten vård',
    en: 'Estimated long-term institutional care fee',
  },
  receipt_estimated_payment_prefix: {
    fi: 'Arvoitu asiakasmaksu on yhteensä',
    sv: 'Den uppskattade totala klientavgiften är',
    en: 'The estimated client fee is',
  },
  receipt_estimated_payment_suffix: {
    fi: 'euroa kuukaudessa',
    sv: 'euro i månaden',
    en: 'euros per month',
  },
  receipt_estimated_payment_explanation: {
    fi: 'Tämä arvio on suuntaa antava. Asiakasmaksujen tarkka määrä lasketaan laitoshoidon asiakasmaksupäätökseen. Tämä arvio ei ole viranomaisen tekemä virallinen asiakasmaksupäätös.',
    sv: 'Denna uppskattning är riktgivande. Den exakta klientavgiften räknas ut då man fattar beslut om sluten vård. Denna uppskattning är inte myndighetens officiella klientavgiftsbeslut.',
    en: 'This estimate is indicative. The exact fee will be determined in the official decision on the long-term institutional care fee. This estimate is not an official authority decision.',
  },
  receipt_estimate_of_payment_breakdown_title: {
    fi: 'Arvio koostuu seuraavista tiedoista:',
    sv: 'Uppskattningen grundar sig på följande uppgifter:',
    en: 'The estimate is based on the following information:',
  },
  subtotal_minimum_disposable_amount: {
    fi: 'Asiakkaalle jäävä vähimmäiskäyttövara',
    sv: 'Minimibelopp för klientens disponibla medel',
    en: 'Client’s remaining minimum disposable amount',
  },
  subtotal_minimum_disposable_amount_with_spouse: {
    fi: 'Yhteinen käyttövara',
    sv: 'Tillsammans med maken',
    en: 'Combined with spouse',
  },
  subtotal_minimum_disposable_amount_with_spouse_details: {
    fi: 'Asiakkaalle ja puolisolle jäävä yhteinen vähimmäiskäyttövara ${disposable_amount} euroa kuukaudessa, josta ${minimum_funds} euroa on vähimmäiskäyttövara ja ${basic_amount} euroa toimeentulotuen perusosa.',
    sv: 'Klientens och make/makans gemensamma disponibla medel ska uppgå till minst ${disposable_amount} euro per månad, varav ${minimum_funds} euro är minimibeloppet och ${basic_amount} euro är utkomststödets grunddel.',
    en: 'The joint minimum reserve left for the client and spouse  is EUR ${disposable_amount} euros per month, of which EUR ${minimum_funds} is the minimum amount of disposable funds and EUR ${basic_amount} is the basic amount of social assistance.',
  },
  additional_detail_spouse_higher_income: {
    fi: 'Asiakkaan tulot ovat pienemmät tai yhtä suuret kuin puolisolla, jolloin asiakasmaksu on 85 % asiakkaan nettokuukausituloista, joihin on tehty lainmukaiset vähennykset.',
    sv: 'Klientens inkomster är mindre än eller lika stora som makans varmed klientavgiften är 85 % av klientens sammanlagda nettoinkomster per månad, med de avdrag som föreskrivs i lag.',
    en: 'The client’s income is lower than or equal to the spouse’s, in which case the fee is 85% of the client’s net monthly income after statutory deductions.',
  },
  additional_detail_no_spouse_higher_income: {
    fi: 'Asiakasmaksu on 85 % asiakkaan nettokuukausituloista, joihin on tehty lainmukaiset vähennykset.',
    sv: 'Klientavgiften är 85 % av klientens sammanlagda nettoinkomster per månad, med de avdrag som föreskrivs i lag.',
    en: 'The fee is 85% of the client’s net monthly income after statutory deductions.',
  },
  additional_detail_lower_income: {
    fi: 'Asiakkaan tulot ovat suuremmat kuin puolisolla, jolloin asiakasmaksu on 42,5 % asiakkaan ja puolison yhteenlasketuista nettokuukausituloista, joihin on tehty lainmukaiset vähennykset.',
    sv: 'Alternativt: Klientens inkomster är större än makans varmed klientavgiften är 42,5 % av klientens och makans sammanlagda nettoinkomster per månad, med de avdrag som föreskrivs i lag.',
    en: 'The client’s income is higher than the spouse’s, in which case the fee is 42.5% of the spouses’ combined net monthly income after statutory deductions.',
  },
  additional_detail_forest_income: {
    fi: 'Metsätulo huomioidaan 90-prosenttisesti.',
    sv: 'Skogsinkomster beaktas till 90 procent.',
    en: 'Forest income is taken into account at 90%.',
  },
  receipt_subtotal_euros_per_month: {
    fi: '${value} €/kk',
    sv: '${value} euro/månad',
    en: '${value} €/month',
  },
  receipt_additional_details: {
    fi: 'Lisähuomiot:',
    sv: 'Ytterligare anmärkningar:',
    en: 'Additional remarks:',
  },
};

export default translations;
