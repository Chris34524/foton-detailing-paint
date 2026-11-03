export default function InventoryStockDetails({ material }) {
  const totals = [['initial_stock', 'Stock inicial'], ['entries', 'Entradas'], ['exits', 'Salidas'],
    ['adjustments_in', 'Ajustes positivos'], ['adjustments_out', 'Ajustes negativos']]
  return <details className="inventory-stock-details"><summary>Desglose de existencias</summary>
    <dl>{totals.map(([field, label]) => <div key={field}><dt>{label}</dt><dd>{material[field] ?? '0.000000'} {material.unit}</dd></div>)}</dl>
    {material.notes && <p className="plain-text">{material.notes}</p>}
  </details>
}
