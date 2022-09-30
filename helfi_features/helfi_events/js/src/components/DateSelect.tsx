import Collapsible from '../components/Collapsible';
import { Checkbox, DateInput } from 'hds-react';
import { parse, format } from 'date-fns';
import { QueryBuilder } from '../utils/QueryBuilder'
import ApiKeys from '../enum/ApiKeys';

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
    const key = date === 'start' ? ApiKeys.START : ApiKeys.END;
    date === 'start' ? setStartDate(value) : setEndDate(value);

    if (!value || value === '') {
      queryBuilder.resetParam(key);
      return;
    }
    
    const parsedDate = parse(value, 'd.M.y', new Date());

    queryBuilder.setParams({[key]: format(parsedDate, 'y-MM-dd')});
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
          <Checkbox
            id='end-disabled'
            label={Drupal.t('End date is the same as start date')}
            checked={endDisabled}
            onChange={() => disableEnd(!endDisabled)}
          />
          <DateInput
            helperText='Use format D.M.YYYY'
            id='start-date'
            label='Choose a date'
            lang={currentLanguage}
            value={startDate}
            onChange={(value: string) => changeDate(value, 'start')}
          />
          <DateInput
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
