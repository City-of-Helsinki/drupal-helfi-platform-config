import { useEffect, useState } from 'react';
import FormContainer from './FormContainer';
import ResultsContainer from './ResultsContainer';
import type Event from '../types/Event';
import { QueryBuilder } from '../utils/QueryBuilder';
import type FilterSettings from '../types/FilterSettings';

type ResponseType = {
  data: Event[],
  meta: {
    count: number,
    next?: string,
    previous?: string
  }
}

export const getEvents = async(url: string): Promise<ResponseType|null> => {
  const response = await fetch(url);

  if (response.status === 200) {
    const result = await response.json();

    if (result.meta && result.meta.count >= 0) {
      return result;
    }
  }

  throw new Error('Failed to get data from the API');
}

type SearchContainerProps = {
  filterSettings: FilterSettings,
  queryBuilder: QueryBuilder
}

const SearchContainer = ({ filterSettings, queryBuilder }: SearchContainerProps) => {
  const [events, setEvents] = useState<Event[]>([]);
  const [count, setCount] = useState<Number|null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [failed, setFailed] = useState<boolean>(false);

  const fetchEvents = () => {
    getEvents(queryBuilder.getUrl()).then(response => {
      if (response) {
        setCount(response.meta.count)
        setEvents(response.data);
      }
    })
    .catch(e => setFailed(true))
    .finally(() => setLoading(false));
  };

  // Initialize events. Keep dependency array empty to make sure this hook is run only once.
  useEffect(() => {
    fetchEvents();
    // eslint-disable-next-line
  }, []);

  return (
    <div className='component--event-list'>
      <FormContainer filterSettings={filterSettings} queryBuilder={queryBuilder} triggerQuery={fetchEvents} />
      <ResultsContainer count={count} failed={failed} loading={loading} events={events} />
    </div>
  )
}

export default SearchContainer;
