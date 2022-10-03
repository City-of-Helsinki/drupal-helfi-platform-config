import React, { useEffect, useState } from 'react';

import { getEvents } from './SearchContainer';
import LocationFilter from '../components/LocationFilter';
import type Location from '../types/Location';
import { QueryBuilder } from '../utils/QueryBuilder';
import ApiKeys from '../enum/ApiKeys';
import SubmitButton from '../components/SubmitButton';
import DateSelect from '../components/DateSelect';
import CheckboxFilter from '../components/CheckboxFilter';

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
  const [freeFilter, setFreeFilter] = useState<boolean>(false);
  const [remoteFilter, setRemoteFilter] = useState<boolean>(false);
  const [loading, setLoading] = useState<boolean>(true);
  const { currentLanguage } = drupalSettings.path;

  // Initiate locations
  useEffect(() => {
    getEvents(queryBuilder.allEventsQuery()).then(response => {
      if (response && response.data && response.data.length) {
        setLocationOptions(transformLocations(response.data, currentLanguage));
      }
    })
    .finally(() => setLoading(false));
  }, [currentLanguage, queryBuilder])

  const toggleFreeEvents = (event: React.ChangeEvent<HTMLInputElement>) => {
    const checked = event?.target?.checked;

    if (!checked) {
      setFreeFilter(false);
      queryBuilder.resetParam(ApiKeys.FREE);

      return;
    }

    setFreeFilter(true);
    queryBuilder.setParams({[ApiKeys.FREE]: 'true'})
  }

  const toggleRemoteEvents = (event: React.ChangeEvent<HTMLInputElement>) => {
    const checked = event?.target?.checked;

    if (!checked) {
      setRemoteFilter(false);
      queryBuilder.resetParam(ApiKeys.REMOTE);

      return;
    }

    setRemoteFilter(true);
    queryBuilder.setParams({[ApiKeys.REMOTE]: 'true'})
  }

  return (
    <div className='event-form-container'>
      <h3>{Drupal.t('Filter events')}</h3>
      <div className='event-form__filters-container'>
        <div className='event-form__filter-section-container'>
          <LocationFilter loading={loading} options={locationOptions} queryBuilder={queryBuilder} />
          <DateSelect 
            endDate={endDate}
            endDisabled={endDisabled}
            disableEnd={disableEnd}
            queryBuilder={queryBuilder}
            setEndDate={setEndDate}
            setStartDate={setStartDate}
            startDate={startDate}
          />
        </div>
        <div className='event-form__filter-section-container'>
          <CheckboxFilter
            checked={freeFilter}
            id='free-toggle'
            label={`${Drupal.t('Show only')} ${Drupal.t('Free events')}`}
            onChange={toggleFreeEvents}
          />
          <CheckboxFilter
            checked={remoteFilter}
            id='remote-toggle'
            label={`${Drupal.t('Show only')} ${Drupal.t('Remote events')}`}
            onChange={toggleRemoteEvents}
          />
          <SubmitButton triggerQuery={triggerQuery} />
        </div>
      </div>
    </div>
  )
}

export default FormContainer;
