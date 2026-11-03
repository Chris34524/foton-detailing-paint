export default function OrderEditor({ entries, onChange, removable = false, title }) {
  function move(index, offset) {
    const next = [...entries]
    ;[next[index], next[index + offset]] = [next[index + offset], next[index]]
    onChange(next)
  }
  return <ol className="ordered-criteria" aria-label={title}>
    {entries.map((entry, index) => <li key={entry.id}>
      <span>{entry.code ? entry.code + ' · ' : ''}{entry.name || entry.label}{entry.is_active === false ? ' (inactivo)' : ''}</span>
      <div className="form-actions">
        <button type="button" disabled={index === 0} onClick={() => move(index, -1)} aria-label={'Subir ' + (entry.name || entry.label)}>Subir</button>
        <button type="button" disabled={index === entries.length - 1} onClick={() => move(index, 1)} aria-label={'Bajar ' + (entry.name || entry.label)}>Bajar</button>
        {removable && <button type="button" onClick={() => onChange(entries.filter((item) => item.id !== entry.id))} aria-label={'Retirar ' + entry.name}>Retirar</button>}
      </div>
    </li>)}
  </ol>
}
