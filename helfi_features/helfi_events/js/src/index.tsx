import React from 'react';
import ReactDOM from 'react-dom';
import SearchContainer from './containers/SearchContainer';

import type FilterSettings from './types/FilterSettings';
import QueryBuilder from './utils/QueryBuilder';

const rootSelector: string = 'helfi-events-search';
const rootElement: HTMLElement | null = document.getElementById(rootSelector);
const eventsUrl = rootElement?.dataset?.eventsUrl;

if (eventsUrl) {
  const queryBuilder = QueryBuilder(eventsUrl);
  const filterSettings: FilterSettings = {
    showLocation: rootElement?.dataset?.showLocationFilter === '1',
    showTimeFilter: rootElement?.dataset?.showTimeFilter === '1',
    showFreeFilter: rootElement?.dataset?.showFreeEventsFilter === '1',
    showRemoteFilter: rootElement?.dataset?.showRemoteEventsFilter === '1'
  };

  ReactDOM.render(
    <React.StrictMode>
      <SearchContainer queryBuilder={queryBuilder} filterSettings={filterSettings} />
    </React.StrictMode>,
    rootElement
  );
}
