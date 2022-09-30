import React from 'react';
import ReactDOM from 'react-dom';
import SearchContainer from './containers/SearchContainer';

import QueryBuilder from './utils/QueryBuilder';

const rootSelector: string = 'helfi-events-search';
const rootElement: HTMLElement | null = document.getElementById(rootSelector);
const eventsUrl = rootElement?.dataset?.eventsUrl;

if (eventsUrl) {
  const queryBuilder = QueryBuilder(eventsUrl);

  ReactDOM.render(
    <React.StrictMode>
      <SearchContainer queryBuilder={queryBuilder}/>
    </React.StrictMode>,
    rootElement
  );
}
