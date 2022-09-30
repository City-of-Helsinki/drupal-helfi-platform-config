import { Checkbox } from 'hds-react';

type CheckboxFilterProps = {
  checked: boolean;
  className: string;
  id: string;
  label: string;
  onChange: Function
}

const CheckboxFilter = ({ checked, className, id, label, onChange }: CheckboxFilterProps) => {
  return (
    <Checkbox
      checked={checked}
      className={className}
      id={id}
      label={label}
      onChange={(event) => onChange(event)}
    />
  );
}

export default CheckboxFilter;
