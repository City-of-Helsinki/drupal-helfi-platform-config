/**
 * @file defines translationWarmer function.
 * The translationWarmer function is workaround for creating translations for
 * languages.
 *
 * As the ckeditor5-dev-translations will pick up only translations which
 * are called via t() function, none of the languages would get translated
 * without running this function.
 *
 * If you have an idea how to fix this, please do.
 *
 * @param {Object} locale The localization services instance.
 */
export default function translationWarmer(locale) {
  const { t } = locale;
  // Run all the languages through t() to trigger the ckeditor5-dev-utils
  // during build.
  t('Afrikaans');
  t('Albanian');
  t('Amharic');
  t('Arabic');
  t('Armenian');
  t('Asturian');
  t('Azerbaijani');
  t('Bahasa Malaysia');
  t('Basque');
  t('Belarusian');
  t('Bengali');
  t('Bosnian');
  t('Bulgarian');
  t('Burmese');
  t('Catalan');
  t('Chichewa');
  t('Chinese');
  t('Chinese, Simplified');
  t('Chinese, Traditional');
  t('Corsican');
  t('Croatian');
  t('Czech');
  t('Danish');
  t('Dutch');
  t('Dzongkha');
  t('English');
  t('Esperanto');
  t('Estonian');
  t('Faeroese');
  t('Filipino');
  t('Finnish');
  t('French');
  t('Frisian');
  t('Frisian, Western');
  t('Gaelic');
  t('Galician');
  t('Georgian');
  t('German');
  t('Greek');
  t('Gujarati');
  t('Haitian Creole');
  t('Hausa');
  t('Hebrew');
  t('Hindi');
  t('Hungarian');
  t('Icelandic');
  t('Igbo');
  t('Indonesian');
  t('Irish');
  t('Italian');
  t('Japanese');
  t('Javanese');
  t('Kannada');
  t('Kazakh');
  t('Khmer');
  t('Kinyarwanda');
  t('Korean');
  t('Kurdish (Kurmanji)');
  t('Kurdish');
  t('Kyrgyz');
  t('Lao');
  t('Latin');
  t('Latvian');
  t('Lithuanian');
  t('Luxembourgish');
  t('Macedonian');
  t('Malagasy');
  t('Malay');
  t('Malayalam');
  t('Maltese');
  t('Maori');
  t('Marathi');
  t('Mongolian');
  t('Myanmar (Burmese)');
  t('Nepali');
  t('Norwegian');
  t('Odia (Oriya)');
  t('Pashto');
  t('Persian');
  t('Polish');
  t('Portuguese');
  t('Punjabi');
  t('Romanian');
  t('Russian');
  t('Sami');
  t('Samoan');
  t('Serbian');
  t('Sesotho');
  t('Shona');
  t('Sindhi');
  t('Sinhala');
  t('Slovak');
  t('Slovenian');
  t('Somali');
  t('Spanish');
  t('Sundanese');
  t('Swahili');
  t('Swedish');
  t('Swiss German');
  t('Tajik');
  t('Tamil');
  t('Tamil, Sri Lanka');
  t('Tatar');
  t('Telugu');
  t('Thai');
  t('Tibetan');
  t('Turkish');
  t('Turkmen');
  t('Tuvan');
  t('Ukrainian');
  t('Urdu');
  t('Uyghur');
  t('Uzbek');
  t('Vietnamese');
  t('Welsh');
  t('Xhosa');
  t('Yiddish');
  t('Yoruba');
  t('Zulu');
  t('Norwegian Bokmål');
  t('Norwegian Nynorsk');
  t('Occitan');
  t('Persian, Farsi');
}
