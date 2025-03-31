@extends('layouts.app')

@section('content')
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<div class="container mt-4">
    <h3 class="mb-4">Consulta por CLUES y Variables</h3>

    <div class="mb-3">
        <label for="catalogoSelect" class="form-label">Selecciona un catálogo SIS:</label>
        <select id="catalogoSelect" class="form-select">
            <option value="">-- Selecciona un catálogo --</option>
        </select>
    </div>

    <div class="mb-3">
        <label for="cluesInput" class="form-label">CLUES:</label>
        <input type="text" id="cluesInput" class="form-control" placeholder="Ej. MCL210000000">
    </div>

    <div class="mb-3">
        <label for="variablesSelect" class="form-label">Selecciona Variables:</label>
        <select id="variablesSelect" class="form-select" multiple></select>
    </div>

    <button class="btn btn-primary mb-4" onclick="consultarVariables()">Consultar</button>

    <div id="spinnerCarga" class="text-center my-4 d-none">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"></div>
        <p class="mt-2">Consultando...</p>
    </div>

    <div class="table-responsive d-none" id="resultadoTabla">
        <table class="table table-bordered">
            <thead><tr><th>Variable</th><th>Valor</th></tr></thead>
            <tbody id="tablaResultados"></tbody>
        </table>
    </div>
</div>

<!-- jQuery (requerido por Select2) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

@endsection

@section('scripts')
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>


<script>
const baseUrl = 'http://127.0.0.1:8070';
let cuboActivo = null;

document.addEventListener('DOMContentLoaded', () => {
    $('#variablesSelect').select2({ placeholder: "Busca variables...", width: '100%' });

    fetch(`${baseUrl}/cubos_sis`)
        .then(res => res.json())
        .then(data => {
            const select = document.getElementById('catalogoSelect');
            data.cubos_sis.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c;
                opt.textContent = c;
                select.appendChild(opt);
            });
        });

    document.getElementById('catalogoSelect').addEventListener('change', () => {
        const catalogo = document.getElementById('catalogoSelect').value;
        if (!catalogo) return;

        fetch(`${baseUrl}/cubos_en_catalogo/${catalogo}`)
            .then(res => res.json())
            .then(data => {
                cuboActivo = data.cubos[0];
                cargarVariables(catalogo, cuboActivo);
            });
    });
});

function cargarVariables(catalogo, cubo) {
    mostrarSpinner();
    fetch(`${baseUrl}/miembros_jerarquia2?catalogo=${encodeURIComponent(catalogo)}&cubo=${encodeURIComponent(cubo)}&jerarquia=Variable`)
        .then(res => {
            if (!res.ok) {
                throw new Error(`Error HTTP: ${res.status}`);
            }
            return res.json();
        })
        .then(data => {
            const select = $('#variablesSelect');
            select.empty();
            
            // Verificamos que existan miembros y sea un array
            if (Array.isArray(data?.miembros)) {
                data.miembros.forEach(v => {
                    // Extraemos solo el nombre final si viene en formato [dimension].[nombre]
                    const nombreLimpio = v.nombre.replace(/^.*\.\[?(.*?)\]?$/, '$1');
                    select.append(new Option(nombreLimpio, nombreLimpio));
                });
                
                // Habilitar el select2 después de cargar opciones
                select.trigger('change');
            } else {
                console.warn('No se recibieron variables válidas', data);
                alert('No se encontraron variables disponibles para este cubo');
            }
        })
        .catch(err => {
            console.error("Error al cargar variables:", err);
            alert("Error al cargar variables. Verifica la consola para más detalles.");
        })
        .finally(() => ocultarSpinner());
}

async function consultarVariables() {
    mostrarSpinner();
    document.getElementById('resultadoTabla').classList.add('d-none');
    
    try {
        const payload = {
            catalogo: document.getElementById('catalogoSelect').value,
            cubo: cuboActivo,
            clues: document.getElementById('cluesInput').value.trim(),
            variables: $('#variablesSelect').val()
        };

        if (!payload.catalogo || !payload.cubo || !payload.clues) {
            throw new Error('Por favor completa el catálogo y la CLUES');
        }
        
        if (!payload.variables || payload.variables.length === 0) {
            throw new Error('Por favor selecciona al menos una variable');
        }

        console.log("Enviando payload:", payload);
        
        const response = await fetch(`${baseUrl}/variables_por_clues_multiple`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();
        console.log("Datos recibidos:", data);
        
        if (!response.ok) {
            throw new Error(data.error || `Error HTTP ${response.status}`);
        }
        
        // Mostrar resultados en la tabla
        const tbody = document.getElementById('tablaResultados');
        tbody.innerHTML = '';
        
        if (data.resultados && data.resultados.length > 0) {
            data.resultados.forEach(item => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${item.variable || 'N/A'}</td>
                    <td>${item.valor !== null ? item.valor : 'Sin valor'}</td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            tbody.innerHTML = `
                <tr>
                    <td colspan="2" class="text-center">
                        No se encontraron resultados para esta consulta.<br>
                        <small class="text-muted">Verifica que la CLUES y las variables seleccionadas existan en el cubo.</small>
                    </td>
                </tr>
            `;
        }
        
        document.getElementById('resultadoTabla').classList.remove('d-none');
        
    } catch (error) {
        console.error("Error completo:", error);
        const tbody = document.getElementById('tablaResultados');
        tbody.innerHTML = `
            <tr>
                <td colspan="2" class="text-center text-danger">
                    Error: ${error.message}
                </td>
            </tr>
        `;
        document.getElementById('resultadoTabla').classList.remove('d-none');
    } finally {
        ocultarSpinner();
    }
}
function mostrarSpinner() {
    document.getElementById('spinnerCarga').classList.remove('d-none');
}
function ocultarSpinner() {
    document.getElementById('spinnerCarga').classList.add('d-none');
}
</script>

@endsection
