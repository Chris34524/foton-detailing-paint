import { ActionError } from './Feedback'
export default function QuantityEquivalence({ preview, canonicalUnit }) {
  return <div className="quantity-equivalence">
    {preview.isLoading && <p role="status">Calculando equivalencia…</p>}
    <label htmlFor="quantity-equivalent">Cantidad equivalente ({canonicalUnit || 'unidad de inventario'})</label>
    <input id="quantity-equivalent" value={preview.data?.quantity_equivalent || ''} readOnly aria-label="Cantidad equivalente" />
    <dl className="unit-detail"><div><dt>Stock antes</dt><dd>{preview.data?.stock_before || '—'}</dd></div>
      <div><dt>Stock después estimado</dt><dd>{preview.data?.stock_after ?? '—'}</dd></div></dl>
    {preview.data && !preview.data.sufficient_stock && <p role="alert" className="error">Existencia insuficiente o cantidad fuera del límite permitido.</p>}
    {preview.data && !preview.data.opening_allowed && <p role="alert" className="error">Este material ya tiene movimientos. La apertura solo puede registrarse al inicio.</p>}
    <ActionError error={preview.error} />
    <p className="muted">La equivalencia y el saldo definitivo se validan nuevamente al confirmar.</p>
  </div>
}
