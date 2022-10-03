import Collapsible from '../components/Collapsible';
import { DateInput } from 'hds-react';
import { QueryBuilder } from '../utils/QueryBuilder'
import CheckboxFilter from '../components/CheckboxFilter';
import type { DateTime } from 'luxon';
import HDS_DATE_FORMAT from '../utils/HDS_DATE_FORMAT';


type DateSelectProps = {
  endDate: DateTime | undefined;
  endDisabled: boolean;
  disableEnd: Function;
  queryBuilder: QueryBuilder;
  setEndDate: Function;
  setStartDate: Function;
  startDate: DateTime | undefined;
  invalidStartDate?: boolean;
  invalidEndDate?: boolean;
  // outOfRangeError?: boolean;
};

const DateSelect = ({ endDate, endDisabled, disableEnd, queryBuilder, setEndDate, setStartDate, startDate, invalidStartDate = false, invalidEndDate = false }: DateSelectProps) => {

  const { currentLanguage } = drupalSettings.path;

  const changeDate = (value: string, date: 'start' | 'end') => {
    // This calendar doe not support dates past year 9999
    if (value.length > 10) {
      console.warn('too much future')
      return;
    }
    date === 'start' ? setStartDate(value) : setEndDate(value);
  };

  const getTitle = () => {
    if ((!startDate || !startDate.isValid) && (!endDate || !endDate.isValid)) {
      return Drupal.t('All dates');
    }

    if ((startDate && startDate.isValid) && (!endDate || !endDate.isValid)) {
      return startDate.toFormat(HDS_DATE_FORMAT);
    }

    if ((!startDate || !startDate.isValid) && endDate?.isValid) {
      return `- ${endDate.toFormat(HDS_DATE_FORMAT)}`;
    }
    return `${startDate?.toFormat(HDS_DATE_FORMAT)|| 'unset?'} - ${endDate?.toFormat(HDS_DATE_FORMAT)}`;
  }

  return (
    <div className='hdbt-search__filter event-form__filter--date'>
      <Collapsible
        id='event-search__date-select'
        label={Drupal.t('Pick dates')}
        helper={Drupal.t('Pick a range between which events shoud take place')}
        title={getTitle()}
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
            helperText={Drupal.t('Use format D.M.YYYY')}
            id='start-date'
            label={Drupal.t('Choose a date')}
            lang={currentLanguage}
            invalid={invalidStartDate}
            errorText={invalidStartDate ? "Invalid start date" : ''}
            value={startDate?.toFormat('d.M.yyyy')}
            onChange={(value: string) => changeDate(value, 'start')}
          />
          {invalidStartDate && <p>Invalid start date</p>}

          <DateInput
            minDate={endDisabled ? undefined : startDate?.plus({ 'days': 1 }).toJSDate()}
            className='hdbt-search__filter hdbt-search__date-input'
            disabled={endDisabled}
            helperText={Drupal.t('Use format D.M.YYYY')}
            id='end-date'
            label={Drupal.t('Choose a date')}
            lang={currentLanguage}
            invalid={invalidEndDate}
            errorText={invalidEndDate ? "Invalid end date" : ''}
            value={endDisabled ? startDate?.toFormat(HDS_DATE_FORMAT) : endDate?.toFormat(HDS_DATE_FORMAT)}
            onChange={(value: string) => changeDate(value, 'end')}
          />
          {invalidEndDate && <p>Invalid end date</p>}
          {/* {outOfRangeError && <p>Out of range error</p>} */}

        </div>
      </Collapsible>
    </div>
  )

}

export default DateSelect;
