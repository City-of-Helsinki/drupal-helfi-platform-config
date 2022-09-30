import { Checkbox } from 'hds-react';

type CheckboxFilterProps = {
  checked: boolean;
<<<<<<< HEAD
=======
  className: string;
>>>>>>> fa3170f (UHF-6666: Move filter to own components)
  id: string;
  label: string;
  onChange: Function
}

<<<<<<< HEAD
const CheckboxFilter = ({ checked, id, label, onChange }: CheckboxFilterProps) => {
  return (
    <Checkbox
      checked={checked}
      className={'hdbt-search__filter hdbt-search__checkbox'}
=======
const CheckboxFilter = ({ checked, className, id, label, onChange }: CheckboxFilterProps) => {
  return (
    <Checkbox
      checked={checked}
      className={className}
>>>>>>> fa3170f (UHF-6666: Move filter to own components)
      id={id}
      label={label}
      onChange={(event) => onChange(event)}
    />
  );
}

export default CheckboxFilter;
