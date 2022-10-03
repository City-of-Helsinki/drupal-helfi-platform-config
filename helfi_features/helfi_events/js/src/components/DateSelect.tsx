import Collapsible from '../components/Collapsible';
import { DateInput } from 'hds-react';
import { QueryBuilder } from '../utils/QueryBuilder'
import CheckboxFilter from '../components/CheckboxFilter';

type DateSelectProps = {
  endDate: string|undefined;
  endDisabled: boolean;
  disableEnd: Function;
  queryBuilder: QueryBuilder;
  setEndDate: Function;
  setStartDate: Function;
  startDate: string|undefined;
};

const DateSelect = ({ endDate, endDisabled, disableEnd, queryBuilder, setEndDate, setStartDate, startDate }: DateSelectProps) => {
  const { currentLanguage } = drupalSettings.path;

  const changeDate = (value: string, date: 'start' | 'end') => {
    date === 'start' ? setStartDate(value) : setEndDate(value);
  };

  const getTitle = () => {
    if ((!startDate || startDate ==='') && (!endDate || endDate === '')) {
      return Drupal.t('All dates');
    }

    if((startDate && startDate !== '') && (!endDate || endDate === '')) {
      return startDate;
    }

    if((!startDate || startDate === '') && (endDate && endDate !== '')) {
      return `- ${endDate}`;
    }

    return `${startDate} - ${endDate}`;
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
            onChange={() => disableEnd(!endDisabled)}
          />
          <DateInput
            className='hdbt-search__filter hdbt-search__date-input'
            helperText='Use format D.M.YYYY'
            id='start-date'
            label='Choose a date'
            lang={currentLanguage}
            value={startDate}
            onChange={(value: string) => changeDate(value, 'start')}
          />
          <DateInput
            className='hdbt-search__filter hdbt-search__date-input'
            disabled={endDisabled}
            helperText='Use format D.M.YYYY'
            id='end-date'
            label='Choose a date'
            lang={currentLanguage}
            value={endDisabled ? startDate : endDate}
            onChange={(value: string) => changeDate(value, 'end')}
          />
        </div>
      </Collapsible>
    </div>
  )

}

export default DateSelect;
