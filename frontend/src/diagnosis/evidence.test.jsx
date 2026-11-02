import { useState } from 'react'
import { act, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import EvidenceSection from './EvidenceSection'
import PrivateEvidenceImage from './PrivateEvidenceImage'
import diagnosisService from './diagnosisService'
import { defect, evidence, httpError } from './diagnosisFixtures'

vi.mock('./diagnosisService', () => ({ default: { upload: vi.fn(), image: vi.fn() } }))
Object.defineProperty(URL, 'createObjectURL', { writable: true, configurable: true, value: vi.fn() })
Object.defineProperty(URL, 'revokeObjectURL', { writable: true, configurable: true, value: vi.fn() })
beforeEach(() => {
  vi.resetAllMocks()
  URL.createObjectURL.mockReturnValue('blob:foton-evidence')
  diagnosisService.image.mockResolvedValue(new Blob(['image'], { type: 'image/jpeg' }))
  diagnosisService.upload.mockResolvedValue([evidence])
})
function EvidenceHarness() {
  const [current, setCurrent] = useState(defect)
  return <EvidenceSection diagnosisId={12} defect={current} canEdit
    onUploaded={(evidences) => setCurrent((value) => ({ ...value, evidences: [...value.evidences, ...evidences] }))} />
}
const imageFile = () => new File(['image'], 'defecto.jpg', { type: 'image/jpeg' })

describe('private evidence upload', () => {
  it('uploads selected images and displays them through the authenticated API', async () => {
    render(<EvidenceHarness />)
    const file = imageFile()
    await userEvent.upload(screen.getByLabelText('Seleccionar evidencia'), file)
    await userEvent.click(screen.getByRole('button', { name: 'Subir evidencias' }))
    expect(await screen.findByText('Evidencias guardadas.')).toBeInTheDocument()
    expect(await screen.findByRole('img', { name: evidence.original_name })).toHaveAttribute('src', 'blob:foton-evidence')
    expect(diagnosisService.upload).toHaveBeenCalledWith(12, 45, [file], expect.objectContaining({ onUploadProgress: expect.any(Function) }))
    expect(diagnosisService.image).toHaveBeenCalledWith(evidence.view_url, expect.objectContaining({ signal: expect.any(AbortSignal) }))
  })
  it('rejects a non-image file with useful feedback', async () => {
    render(<EvidenceHarness />)
    const user = userEvent.setup({ applyAccept: false })
    await user.upload(screen.getByLabelText('Seleccionar evidencia'), new File(['text'], 'bad.txt', { type: 'text/plain' }))
    await user.click(screen.getByRole('button', { name: 'Subir evidencias' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('Solo se permiten imágenes JPG, PNG o WEBP.')
    expect(diagnosisService.upload).not.toHaveBeenCalled()
  })
  it('rejects oversized images before sending a request', async () => {
    render(<EvidenceHarness />)
    const file = imageFile()
    Object.defineProperty(file, 'size', { value: 10 * 1024 * 1024 + 1 })
    await userEvent.upload(screen.getByLabelText('Seleccionar evidencia'), file)
    await userEvent.click(screen.getByRole('button', { name: 'Subir evidencias' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('máximo 10 MB')
    expect(diagnosisService.upload).not.toHaveBeenCalled()
  })
  it('enforces the five-file batch limit', async () => {
    render(<EvidenceHarness />)
    await userEvent.upload(screen.getByLabelText('Seleccionar evidencia'), Array.from({ length: 6 }, imageFile))
    await userEvent.click(screen.getByRole('button', { name: 'Subir evidencias' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('entre 1 y 5')
    expect(diagnosisService.upload).not.toHaveBeenCalled()
  })
  it('shows backend content validation rather than trusting browser MIME metadata', async () => {
    diagnosisService.upload.mockRejectedValue(httpError(422, { errors: { 'files.0': ['El contenido no es una imagen válida.'] } }))
    render(<EvidenceHarness />)
    await userEvent.upload(screen.getByLabelText('Seleccionar evidencia'), imageFile())
    await userEvent.click(screen.getByRole('button', { name: 'Subir evidencias' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('El contenido no es una imagen válida.')
    expect(screen.queryByRole('img')).not.toBeInTheDocument()
  })
  it.each([[403, 'No tienes permiso'], [404, 'Registro no encontrado'], [413, 'supera el tamaño'], [503, 'No fue posible guardar']])('handles upload error %s without claiming partial success', async (status, text) => {
    diagnosisService.upload.mockRejectedValue(httpError(status))
    render(<EvidenceHarness />)
    await userEvent.upload(screen.getByLabelText('Seleccionar evidencia'), imageFile())
    await userEvent.click(screen.getByRole('button', { name: 'Subir evidencias' }))
    expect(await screen.findByRole('alert')).toHaveTextContent(text)
    expect(screen.queryByText('Evidencias guardadas.')).not.toBeInTheDocument()
    expect(screen.queryByRole('img')).not.toBeInTheDocument()
  })
  it('reports upload progress and prevents duplicate submissions while pending', async () => {
    let finish
    diagnosisService.upload.mockImplementation((_diagnosis, _defect, _files, options) => {
      options.onUploadProgress({ loaded: 50, total: 100 })
      return new Promise((resolve) => { finish = resolve })
    })
    render(<EvidenceHarness />)
    await userEvent.upload(screen.getByLabelText('Seleccionar evidencia'), imageFile())
    await userEvent.click(screen.getByRole('button', { name: 'Subir evidencias' }))
    expect(await screen.findByRole('progressbar', { name: 'Progreso de carga' })).toHaveAttribute('value', '50')
    expect(screen.getByRole('button', { name: 'Subiendo…' })).toBeDisabled()
    await act(async () => finish([evidence]))
    expect(await screen.findByText('Evidencias guardadas.')).toBeInTheDocument()
  })
  it('shows existing images without upload controls for a read-only viewer', async () => {
    render(<EvidenceSection diagnosisId={12} defect={{ ...defect, evidences: [evidence] }} canEdit={false} onUploaded={vi.fn()} />)
    expect(await screen.findByRole('img', { name: evidence.original_name })).toBeInTheDocument()
    expect(screen.queryByLabelText('Seleccionar evidencia')).not.toBeInTheDocument()
  })
})

describe('private image lifecycle', () => {
  it('revokes the object URL when the image leaves the page', async () => {
    const { unmount } = render(<PrivateEvidenceImage evidence={evidence} />)
    expect(await screen.findByRole('img')).toHaveAttribute('src', 'blob:foton-evidence')
    unmount()
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:foton-evidence')
  })
  it('cancels pending image requests and never creates an object URL after unmount', async () => {
    let finish
    diagnosisService.image.mockReturnValue(new Promise((resolve) => { finish = resolve }))
    const { unmount } = render(<PrivateEvidenceImage evidence={evidence} />)
    const signal = diagnosisService.image.mock.calls[0][1].signal
    unmount()
    await act(async () => finish(new Blob(['image'], { type: 'image/jpeg' })))
    expect(signal.aborted).toBe(true)
    expect(URL.createObjectURL).not.toHaveBeenCalled()
  })
  it('supports retry after an image network failure', async () => {
    diagnosisService.image.mockRejectedValueOnce(new Error('private path')).mockResolvedValue(new Blob(['image']))
    render(<PrivateEvidenceImage evidence={evidence} />)
    expect(await screen.findByRole('alert')).toHaveTextContent('No fue posible cargar la evidencia')
    expect(screen.queryByText('private path')).not.toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Reintentar imagen' }))
    await waitFor(() => expect(screen.getByRole('img')).toHaveAttribute('src', 'blob:foton-evidence'))
  })
})