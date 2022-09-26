import { useEffect, useState } from 'react';
import FormContainer from './FormContainer';
import ResultsContainer from './ResultsContainer';
import type Event from '../types/Event';

type ResponseType = {
  data: Event[],
  meta: {
    count: number,
    next?: string,
    previous?: string
  }
}

const getEvents = async(url: string): Promise<ResponseType|null> => {
  const response = await fetch(url);

  if (response.status === 200) {
    const result = await response.json();

    if (result.meta && result.meta.count > 0) {
      return result;
    }
  }

  throw new Error('Failed to get data from the API');
}

const SearchContainer = () => {
  const [events, setEvents] = useState<Event[]>([]);
  const [count, setCount] = useState<Number|null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [failed, setFailed] = useState<boolean>(false);
  const { eventsUrl } = drupalSettings.helfi_events;

  // Initialize data
  useEffect(() => {
    getEvents(eventsUrl).then(response => {
      if (response) {
        setCount(response.meta.count)
        setEvents(response.data);
      }
    })
    .catch(e => setFailed(true))
    .finally(() => setLoading(false))
    ;
  }, [eventsUrl]);

  return (
    <div className='component--event-list'>
      <FormContainer />
      <ResultsContainer count={count} failed={failed} loading={loading} events={events} />
    </div>
  )
}

export default SearchContainer;
