export default function FieldError({ errors, name, id }) {
  return errors[name] ? <p className="field-error" id={id}>{errors[name][0]}</p> : null
}