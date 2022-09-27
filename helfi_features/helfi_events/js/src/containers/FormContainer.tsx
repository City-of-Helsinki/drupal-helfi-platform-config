import { useEffect, useState } from 'react';
import { parse, format } from 'date-fns';

import { getEvents } from './SearchContainer';
import LocationFilter from '../components/LocationFilter';
import type Location from '../types/Location';
import { QueryBuilder } from '../utils/QueryBuilder'
import Collapsible from '../components/Collapsible';
import { Button, Checkbox, DateInput } from 'hds-react';
import ApiKeys from '../enum/ApiKeys';

type FormContainerProps = {
  queryBuilder: QueryBuilder,
  triggerQuery: Function
};

const transformLocations = (data: any, currentLanguage: string): Location[] => {
  const usedIds: string[] = [];
  const locations = data.reduce((prev: any, current: any) => {
    if (current.location && current.location.id && !(usedIds.indexOf(current.location.id) >= 0) && current.location.name && current.location.name[currentLanguage]) {
      usedIds.push(current.location.id);
      return [...prev, {value: current.location.id, label: current.location.name[currentLanguage]}]
    }

    return prev;
  }, [])

  return locations;
}

const FormContainer = ({ queryBuilder, triggerQuery }: FormContainerProps) => {
  const [locationOptions, setLocationOptions] = useState<Location[]>([]);
  const [endDisabled, disableEnd] = useState<boolean>(false);
  const [startDate, setStartDate] = useState<string>();
  const [endDate, setEndDate] = useState<string>();
  const [loading, setLoading] = useState<boolean>(true);
  const { currentLanguage } = drupalSettings.path;

  // Initiate locations
  useEffect(() => {
    console.log('get all events')
    getEvents(queryBuilder.allEventsQuery()).then(response => {
      if (response && response.data && response.data.length) {
        setLocationOptions(transformLocations(response.data, currentLanguage));
      }
    })
    .finally(() => setLoading(false));
  }, [currentLanguage, queryBuilder])

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

  return (
    <div className='event-form-container'>
      <h3>{Drupal.t('Filter events')}</h3>
      <div className='event-form__filters-container'>
        <div className='event-form__filter event-form__filter--location'>
          <LocationFilter loading={loading} options={locationOptions} queryBuilder={queryBuilder} />
        </div>
        <div className='event-form__filter event-form__filter--date'>
          <Collapsible
            title='123'
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
                onChange={(value) => changeDate(value, 'start')}
              />
              <DateInput
                disabled={endDisabled}
                helperText='Use format D.M.YYYY'
                id='end-date'
                label='Choose a date'
                lang={currentLanguage}
                value={endDisabled ? startDate : endDate}
                onChange={(value) => changeDate(value, 'end')}
              />
            </div>
          </Collapsible>
          <Button onClick={() => triggerQuery()}>{Drupal.t('Search')}</Button>
        </div>
      </div>
    </div>
  )
}

export default FormContainer;
