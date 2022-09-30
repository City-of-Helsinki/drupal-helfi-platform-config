import { CSSProperties } from 'react';
import { Button } from 'hds-react';

interface ButtonCSSProps extends CSSProperties {
  '--background-color': string,
  '--background-color-hover': string,
  '--background-color-focus': string,
  '--background-color-hover-focus': string,
  '--border-color': string,
  '--border-color-hover': string,
  '--border-color-focus': string,
  '--border-color-hover-focus': string,
  '--color': string,
  '--color-hover': string,
  '--color-focus': string,
  '--color-hover-focus': string,
  '--focus-outline-color': string
}

const buttonStyle = {
  '--background-color': 'var(--hdbt-color-black)',
  '--background-color-hover': 'var(--hdbt-text-color)',
  '--background-color-focus': 'var(--hdbt-text-color)',
  '--background-color-hover-focus': 'var(--hdbt-text-color)',
  '--border-color': 'var(--hdbt-color-black)',
  '--border-color-hover': 'var(--hdbt-color-black)',
  '--border-color-focus': 'var(--hdbt-color-black)',
  '--border-color-hover-focus': 'var(--hdbt-color-black)',
  '--color': 'var(--hdbt-text-color)',
  '--color-hover': 'var(--hdbt-color-black)',
  '--color-focus': 'var(--hdbt-color-black)',
  '--color-hover-focus': 'var(--hdbt-color-black)',
  '--focus-outline-color': 'var(--hdbt-color-black)'
};

type SubmitButtonProps = {
  triggerQuery: Function
}

const SubmitButton = ({ triggerQuery }: SubmitButtonProps) => {
  return (
    <Button style={buttonStyle as ButtonCSSProps} onClick={() => triggerQuery()}>{Drupal.t('Search')}</Button>
  );
}

export default SubmitButton;
