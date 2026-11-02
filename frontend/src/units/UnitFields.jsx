export default function UnitFields({ values, onChange, errors, includeVin = false }) {
  function field(name, label, maxLength, required = false) {
    return <div className="form-field" key={name}>
      <label htmlFor={'unit-' + name}>{label}</label>
      <input id={'unit-' + name} name={name} value={values[name] ?? ''} maxLength={maxLength}
        required={required} autoComplete="off" aria-invalid={Boolean(errors[name])}
        aria-describedby={errors[name] ? name + '-error' : undefined}
        onChange={(event) => onChange(name, name === 'vin' ? event.target.value.toUpperCase() : event.target.value)} />
      {errors[name] && <p className="field-error" id={name + '-error'}>{errors[name][0]}</p>}
    </div>
  }
  return <>
    {includeVin && field('vin', 'VIN', 17, true)}
    {field('model', 'Modelo', 100)}
    {field('current_location', 'Ubicación actual', 120)}
  </>
}