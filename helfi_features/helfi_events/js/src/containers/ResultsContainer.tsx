import EmptyMessage from '../components/EmptyMessage';
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
      {!Number.isNaN(count) &&
        <div className='event-list__count'>
          <strong>{count}</strong> {Drupal.t('events')}
        </div>
      }
      {events.length > 0 ?
        events.map(event => <ResultCard key={event.id} {...event} />) :
        <EmptyMessage />
      }
      {(loading || failed) &&
        <div className='event-list-spinner' dangerouslySetInnerHTML={{__html: Drupal.theme('ajaxProgressThrobber')}} />
      }
    </div>
  )

}

export default ResultsContainer;
