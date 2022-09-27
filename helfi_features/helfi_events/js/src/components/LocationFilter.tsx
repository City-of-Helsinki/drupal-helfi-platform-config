import { Select } from 'hds-react';

import type Location from '../types/Location';
import { QueryBuilder } from '../utils/QueryBuilder';
import ApiKeys from '../enum/ApiKeys';

type LocationFilterProps = {
  loading: boolean,
  options: Location[],
  queryBuilder: QueryBuilder
};

const LocationFilter = ({ loading, options, queryBuilder }: LocationFilterProps) => {
  const onChange = (value: any) => {
    queryBuilder.setParams({[ApiKeys.LOCATION]: value.map((location: Location) => location.value).join(',')});
  }

  return (
    <Select
      clearButtonAriaLabel={Drupal.t('Clear selection', {}, { context: 'News archive clear button aria label' })}
      disabled={loading}
      helper={Drupal.t('If you wish to see remote events only, select the option "Internet"')}
      label={Drupal.t('Select a location')}
      multiselect
      onChange={onChange}
      options={options}
      selectedItemRemoveButtonAriaLabel={Drupal.t('Remove item', {}, { context: 'News archive remove item aria label' })}
    />
  );
}

export default LocationFilter;
