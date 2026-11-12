import { useEffect } from 'react'
export default function useInventoryRefresh(reload) {
  useEffect(() => {
    if (!reload) return
    window.addEventListener('focus', reload)
    window.addEventListener('inventory-changed', reload)
    return () => { window.removeEventListener('focus', reload); window.removeEventListener('inventory-changed', reload) }
  }, [reload])
}
