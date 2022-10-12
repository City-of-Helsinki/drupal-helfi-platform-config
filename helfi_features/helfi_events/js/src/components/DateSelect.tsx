import Collapsible from '../components/Collapsible';
import { DateInput } from 'hds-react';
import { QueryBuilder } from '../utils/QueryBuilder'
import CheckboxFilter from '../components/CheckboxFilter';
import type DateSelectDateTimes from '../types/DateSelectDateTimes';
import HDS_DATE_FORMAT from '../utils/HDS_DATE_FORMAT';
interface DateSelectActions {
  endDisabled: boolean;
  disableEnd: Function;
  queryBuilder: QueryBuilder;
  setEndDate: Function;
  setStartDate: Function;
  invalidStartDate?: boolean;
  invalidEndDate?: boolean;
};

type DateSelectProps = DateSelectDateTimes & DateSelectActions;

const getTitle = ({ startDate, endDate }: DateSelectDateTimes): string => {
  if ((!startDate || !startDate.isValid) && (!endDate || !endDate.isValid)) {
    return Drupal.t('All dates');
  }

  if ((startDate && startDate.isValid) && (!endDate || !endDate.isValid)) {
    return startDate.toFormat(HDS_DATE_FORMAT);
  }

  if ((!startDate || !startDate.isValid) && endDate?.isValid) {
    return `- ${endDate.toFormat(HDS_DATE_FORMAT)}`;
  }
  return `${startDate?.toFormat(HDS_DATE_FORMAT) || 'unset?'} - ${endDate?.toFormat(HDS_DATE_FORMAT)}`;
}


const dateHelperText = Drupal.t('Use format D.M.YYYY')
const dateLabel = Drupal.t('Choose a date')

const DateSelect = ({ endDate, endDisabled, disableEnd, queryBuilder, setEndDate, setStartDate, startDate, invalidStartDate = false, invalidEndDate = false }: DateSelectProps) => {

  const { currentLanguage } = drupalSettings.path;

  const changeDate = (value: string, date: 'start' | 'end') => {
    date === 'start' ? setStartDate(value) : setEndDate(value);
  };

  const title = getTitle({ startDate, endDate });
  const startDateErrorText = invalidStartDate ? Drupal.t("Invalid start date") : ''
  const endDateErrorText = invalidEndDate ? Drupal.t("Invalid end date") : ''

  return (
    <div className='hdbt-search__filter event-form__filter--date'>
      <Collapsible
        id='event-search__date-select'
        label={Drupal.t('Pick dates')}
        helper={Drupal.t('Pick a range between which events shoud take place')}
        title={title}
      >
        <div className='event-form__date-container'>
          <CheckboxFilter
            checked={endDisabled}
            id='end-disabled'
            label={Drupal.t('End date is the same as start date')}
            onChange={disableEnd}
          />

          <DateInput
            className='hdbt-search__filter hdbt-search__date-input'
            helperText={dateHelperText}
            id='start-date'
            label={dateLabel}
            lang={currentLanguage}
            invalid={invalidStartDate}
            errorText={startDateErrorText}
            value={startDate?.toFormat(HDS_DATE_FORMAT)}
            onChange={(value: string) => changeDate(value, 'start')}
          />
          <DateInput
            minDate={endDisabled ? undefined : startDate?.plus({ 'days': 1 }).toJSDate()}
            className='hdbt-search__filter hdbt-search__date-input'
            disabled={endDisabled}
            helperText={dateHelperText}
            id='end-date'
            label={dateLabel}
            lang={currentLanguage}
            invalid={invalidEndDate}
            errorText={endDateErrorText}
            value={endDisabled ? startDate?.toFormat(HDS_DATE_FORMAT) : endDate?.toFormat(HDS_DATE_FORMAT)}
            onChange={(value: string) => changeDate(value, 'end')}
          />
        </div>
      </Collapsible>
    </div>
  )

}

export default DateSelect;
