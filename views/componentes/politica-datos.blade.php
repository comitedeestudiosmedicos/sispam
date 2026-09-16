@props([
    'name' => 'autoriza_tratamiento_datos',
    'id' => 'autoriza_tratamiento_datos',
    'required' => true
])

<!-- COMPONENTE COMPLIANCE LEY 1581 DE 2012 (REGULACIÓN SIC COLOMBIA) -->
<div class="card border-primary bg-primary bg-opacity-10 p-3 mb-4 shadow-sm" {{ $attributes }}>
    <div class="form-check d-flex align-items-start gap-3">
        <div class="position-relative mt-1">
            <input 
                class="form-check-input border-primary form-check-input-lg" 
                type="checkbox" 
                name="{{ $name }}" 
                id="{{ $id }}" 
                value="1" 
                @if($required) required @endif
                style="width: 24px; height: 24px; cursor: pointer; transform: scale(1.1);"
            >
        </div>
        <label class="form-check-label text-dark" for="{{ $id }}" style="cursor: pointer; line-height: 1.4; font-size: 0.85rem;">
            <span class="d-block fw-bold text-primary mb-1">
                <i class="fa-solid fa-scale-balanced me-1"></i> Autorización de Tratamiento de Datos Personales (Ley 1581 de 2012)
            </span>
            Manifiesto que el paciente (o su acudiente autorizado) ha sido informado sobre la Política de Tratamiento de Datos de la institución. El titular autoriza de manera previa, expresa e informada el tratamiento de sus datos personales y sensibles (datos de salud, RIPS y SGSSS) para fines exclusivamente médicos, asistenciales, de dispensación farmacéutica y reporte oficial a los entes de control del Estado.
        </label>
    </div>
</div>
