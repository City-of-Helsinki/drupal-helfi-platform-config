import ResultCard from '../components/ResultCard';
import type Event from '../types/Event';

type ResultsContainerProps = {
  count: Number|null,
  failed: boolean,
  events: Event[],
  loading: boolean
};

const ResultsContainer = ({ count, failed, events, loading }: ResultsContainerProps) => {
  return (
    <div className='event-list__list-container'>
      {count &&
        <div className='event-list__count'>
          <strong>{count}</strong> {Drupal.t('events')}
        </div>
      }
      {events.map(event => <ResultCard key={event.id} {...event} />)}
      {(loading || failed) &&
        <div className='event-list-spinner' dangerouslySetInnerHTML={{__html: Drupal.theme('ajaxProgressThrobber')}} />
      }
    </div>
  )

}

export default ResultsContainer;
