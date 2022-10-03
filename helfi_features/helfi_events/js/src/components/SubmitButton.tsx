import { Button } from 'hds-react';

type SubmitButtonProps = {
  triggerQuery: Function
}

const SubmitButton = ({ triggerQuery }: SubmitButtonProps) => {
  return (
    <Button className='hdbt-search__submit-button event-list__submit-button' onClick={() => triggerQuery()}>{Drupal.t('Search')}</Button>
  );
}

export default SubmitButton;
