import React, { useEffect, useState } from 'react';
import { parse, format } from 'date-fns';

import { getEvents } from './SearchContainer';
import LocationFilter from '../components/LocationFilter';
import type Location from '../types/Location';
import { QueryBuilder } from '../utils/QueryBuilder';
import ApiKeys from '../enum/ApiKeys';
import SubmitButton from '../components/SubmitButton';
import DateSelect from '../components/DateSelect';
import CheckboxFilter from '../components/CheckboxFilter';
import type FilterSettings from '../types/FilterSettings';

type FormContainerProps = {
  filterSettings: FilterSettings,
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

const FormContainer = ({ filterSettings, queryBuilder, triggerQuery }: FormContainerProps) => {
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

  useEffect(() => {
    const setDate = (key: string, value: string|undefined) => {
      if (!value || value === '') {
        queryBuilder.resetParam(key);
        return;
      }
  
      let parsedDate = null;
      try {
        parsedDate = parse(value, 'd.M.y', new Date());
      }
      catch (e) {
      }
  
      if (parsedDate) {
        queryBuilder.setParams({[key]: format(parsedDate, 'y-MM-dd')});
      }
    }

    setDate(ApiKeys.START, startDate);
    if (endDisabled) {
      setDate(ApiKeys.END, startDate);
    }
    if (!endDisabled) {
      setDate(ApiKeys.END, endDate);
    }
  }, [startDate, endDate, endDisabled, queryBuilder])

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

  const bothCheckboxes = filterSettings.showFreeFilter && filterSettings.showRemoteFilter;
  const showOnlyLabel = Drupal.t('Show only');
  const freeTranslation = Drupal.t('Free events');
  const remoteTranslation = Drupal.t('Remote events');
  const freeLabel = bothCheckboxes ? freeTranslation : `${showOnlyLabel} ${freeTranslation.toLowerCase()}`;
  const remoteLabel = bothCheckboxes ? remoteTranslation : `${showOnlyLabel} ${remoteTranslation.toLowerCase()}`;

  return (
    <div className='event-form-container'>
      <h3>{Drupal.t('Filter events')}</h3>
      <div className='event-form__filters-container'>
        <div className='event-form__filter-section-container'>
          {
            filterSettings.showLocation &&
            <LocationFilter loading={loading} options={locationOptions} queryBuilder={queryBuilder} />
          }
          {
            filterSettings.showTimeFilter &&
            <DateSelect 
              endDate={endDate}
              endDisabled={endDisabled}
              disableEnd={disableEnd}
              queryBuilder={queryBuilder}
              setEndDate={setEndDate}
              setStartDate={setStartDate}
              startDate={startDate}
            />
          }
        </div>
        {
          bothCheckboxes &&
          <div className='event-form__checkboxes-label'>{Drupal.t('Show only')}</div>
        }
        <div className='event-form__filter-section-container'>
          {
            filterSettings.showFreeFilter &&
            <CheckboxFilter
              checked={freeFilter}
              id='free-toggle'
              label={freeLabel}
              onChange={toggleFreeEvents}
            />
          }
          {
            filterSettings.showRemoteFilter &&
            <CheckboxFilter
              checked={remoteFilter}
              id='remote-toggle'
              label={remoteLabel}
              onChange={toggleRemoteEvents}
            />
          }
        </div>
        <SubmitButton triggerQuery={triggerQuery} />
      </div>
    </div>
  )
}

export default FormContainer;
