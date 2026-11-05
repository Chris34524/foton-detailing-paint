import { useEffect, useRef, useState } from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import useAuth from '../auth/useAuth'
import AppIcon from '../components/AppIcon'

export default function LoginPage() {
  const { login, isAuthenticated } = useAuth()
  const location = useLocation()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [validationErrors, setValidationErrors] = useState({})
  const feedback = useRef(null)
  useEffect(() => { document.title = 'Iniciar sesión · FOTON' }, [])
  useEffect(() => { if (error) feedback.current?.focus() }, [error])

  if (isAuthenticated) {
    const destination = location.state?.from
    return <Navigate to={destination?.startsWith('/') && !destination.startsWith('//') && destination !== '/login' ? destination : '/materials'} replace />
  }

  async function handleSubmit(event) {
    event.preventDefault()
    if (isSubmitting) return
    setIsSubmitting(true); setError(''); setValidationErrors({})
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
    } finally { setIsSubmitting(false) }
  }

  return <main className="login-layout">
    <section className="login-introduction" aria-label="FOTON Detailing & Paint">
      <div className="sidebar-brand"><span className="brand-mark" aria-hidden="true">F</span><p className="brand">FOTON <span>Detailing &amp; Paint</span></p></div>
      <div className="login-message"><p className="eyebrow">CONTROL DE OPERACIÓN</p><h2>Cada unidad.<br />Cada proceso.<br /><span>Todo en su lugar.</span></h2>
        <p>Da seguimiento al trabajo, los materiales y la calidad de cada unidad desde un mismo espacio.</p></div>
      <div className="login-stages" aria-hidden="true"><span><AppIcon name="units" />Unidades</span><span><AppIcon name="work" />Trabajo</span><span><AppIcon name="inspection" />Calidad</span></div>
    </section>
    <div className="login-form-panel">
      <section className="auth-card" aria-labelledby="login-heading">
        <p className="eyebrow">BIENVENIDO A FOTON</p><h1 id="login-heading">Iniciar sesión</h1>
        <p className="muted">Accede a tu espacio de trabajo.</p>
        <form onSubmit={handleSubmit} noValidate>
          <div className="form-field"><label htmlFor="email">Correo electrónico</label>
            <input id="email" name="email" type="email" autoComplete="username" placeholder="nombre@empresa.com" value={email}
              onChange={(event) => setEmail(event.target.value)} required disabled={isSubmitting}
              aria-invalid={Boolean(validationErrors.email)} aria-describedby={validationErrors.email ? 'email-error' : undefined} />
            {validationErrors.email && <p className="field-error" id="email-error">{validationErrors.email[0]}</p>}</div>
          <div className="form-field"><label htmlFor="password">Contraseña</label>
            <div className="password-field"><input id="password" name="password" type={showPassword ? 'text' : 'password'} autoComplete="current-password" value={password}
              onChange={(event) => setPassword(event.target.value)} required disabled={isSubmitting}
              aria-invalid={Boolean(validationErrors.password)} aria-describedby={validationErrors.password ? 'password-error' : undefined} />
              <button type="button" className="password-toggle" aria-label={showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'} aria-pressed={showPassword} disabled={isSubmitting} onClick={() => setShowPassword((shown) => !shown)}><AppIcon name="eye" /></button></div>
            {validationErrors.password && <p className="field-error" id="password-error">{validationErrors.password[0]}</p>}</div>
          {error && <p className="error" role="alert" tabIndex={-1} ref={feedback}>{error}</p>}
          <button className="primary-button login-submit" type="submit" disabled={isSubmitting}>{isSubmitting ? 'Iniciando sesión…' : 'Entrar'}<AppIcon name="arrow" /></button>
        </form>
        <p className="login-help">Utiliza el correo y la contraseña asignados por tu administrador.</p>
      </section>
      <p className="login-footer">FOTON · Detailing &amp; Paint</p>
    </div>
  </main>
}
