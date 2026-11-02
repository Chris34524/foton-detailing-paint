import { useState } from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import useAuth from '../auth/useAuth'

export default function LoginPage() {
  const { login, isAuthenticated } = useAuth()
  const location = useLocation()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [validationErrors, setValidationErrors] = useState({})

  if (isAuthenticated) {
    const destination = location.state?.from
    return <Navigate to={destination?.startsWith('/') && !destination.startsWith('//') && destination !== '/login' ? destination : '/dashboard'} replace />
  }

  async function handleSubmit(event) {
    event.preventDefault()
    if (isSubmitting) return
    setIsSubmitting(true)
    setError('')
    setValidationErrors({})
    try {
      await login({ email, password })
      setPassword('')
    } catch (failure) {
      const status = failure.response?.status
      if (status === 422) {
        setValidationErrors(failure.response.data.errors ?? {})
        setError('Revisa los datos del formulario.')
      } else if (status === 401) {
        setError('El correo o la contraseña son incorrectos.')
      } else if (status === 429) {
        setError('Demasiados intentos. Espera un minuto antes de volver a intentar.')
      } else if (status === 419) {
        setError('La sesión expiró. Intenta iniciar sesión nuevamente.')
      } else {
        setError('No fue posible iniciar sesión. Intenta de nuevo.')
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <main className="login-layout">
      <section className="auth-card" aria-labelledby="login-heading">
        <p className="brand">FOTON <span>Detailing &amp; Paint</span></p>
        <h1 id="login-heading">Iniciar sesión</h1>
        <p className="muted">Accede a tu espacio de trabajo.</p>
        <form onSubmit={handleSubmit} noValidate>
          <label htmlFor="email">Correo electrónico</label>
          <input id="email" name="email" type="email" autoComplete="username" value={email}
            onChange={(event) => setEmail(event.target.value)} required disabled={isSubmitting}
            aria-invalid={Boolean(validationErrors.email)} aria-describedby={validationErrors.email ? 'email-error' : undefined} />
          {validationErrors.email && <p className="field-error" id="email-error">{validationErrors.email[0]}</p>}
          <label htmlFor="password">Contraseña</label>
          <input id="password" name="password" type="password" autoComplete="current-password" value={password}
            onChange={(event) => setPassword(event.target.value)} required disabled={isSubmitting}
            aria-invalid={Boolean(validationErrors.password)} aria-describedby={validationErrors.password ? 'password-error' : undefined} />
          {validationErrors.password && <p className="field-error" id="password-error">{validationErrors.password[0]}</p>}
          {error && <p className="error" role="alert">{error}</p>}
          <button className="primary-button" type="submit" disabled={isSubmitting}>
            {isSubmitting ? 'Iniciando sesión…' : 'Entrar'}
          </button>
        </form>
      </section>
    </main>
  )
}
