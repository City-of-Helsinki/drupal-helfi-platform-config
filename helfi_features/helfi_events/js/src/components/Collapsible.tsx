import React, { useRef, useState } from 'react';
import { IconAngleDown, IconAngleUp } from 'hds-react';

import useOutsideClick from '../hooks/useOutsideClick';

type Props = {
  active?: boolean;
  ariaControls?: string;
  children: React.ReactElement;
  helper?: string;
  id: string;
  label: string;
  showHandle?: boolean;
  title: string|React.ReactNode;
}

const Collapsible = ({active, ariaControls, helper, id, label, title, children, showHandle}: Props) => {
  const [isActive, setActive] = useState<boolean>(active||false);
  const ref = useRef<HTMLDivElement|null>(null);
  const controlRef = useRef<HTMLButtonElement|null>(null);
  const labelRef = useRef<HTMLLabelElement|null>(null);

  const getHandle = () => {
    if(showHandle !== false) {
      return isActive ? 
        <IconAngleUp className='collapsible__handle' onClick={() => setActive(!isActive)} /> :
        <IconAngleDown className='collapsible__handle' onClick={() => setActive(!isActive)} />;
    }
  }

  const getChildrenStyle = () => {
    let topMargin = 0;

    if (controlRef && controlRef.current) {
      console.log(controlRef.current);
    }

    return {marginTop: topMargin + 'px'}
  }

  useOutsideClick(ref, () => {
    setActive(false);
  });

  return (
    <div className='collapsible-wrapper' ref={ref}>
      <label className='collapsible__label' htmlFor={id} ref={labelRef}>{label}</label>
      <button
        id={id}
        className='collapsible__element collapsible__control'
        aria-controls={ariaControls}
        aria-expanded={isActive}
        onClick={() => setActive(!isActive)}
        ref={controlRef}
      >
        <span className='collapsible__title'>{ title }</span>
        {getHandle()}
      </button>
      {isActive &&
        <div style={getChildrenStyle()} className='collapsible__element collapsible__children'>
          {children}
        </div>
      }
      {helper &&
        <div aria-hidden={true} className='collapsible__helper'>{helper}</div>
      }
    </div>
  );
}; 

export default Collapsible;