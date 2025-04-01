@extends('layouts.app')

@section('content')

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
        <label for="cluesInput" class="form-label">CLUES (separar múltiples CLUES con comas):</label>
        <input type="text" id="cluesInput" class="form-control" placeholder="Ej. MCL210000000, MCL210000001">
    </div>

    <div class="mb-3">
        <label for="variablesSelect" class="form-label">Selecciona Variables (opcional, vacío para todas):</label>
        <select id="variablesSelect" class="form-select" multiple></select>
    </div>

    <button class="btn btn-primary mb-4" onclick="consultarVariables()">Consultar</button>

    <div id="spinnerCarga" class="text-center my-4 d-none">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"></div>
        <p class="mt-2">Consultando...</p>
    </div>

    <div id="resultadosContainer" class="d-none">
        <div class="alert alert-info" id="resumenConsulta"></div>
        <div id="resultadosPorClues"></div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

@endsection

@section('scripts')
<script>
const baseUrl = 'http://127.0.0.1:8070';
let cuboActivo = null;

document.addEventListener('DOMContentLoaded', () => {
    $('#variablesSelect').select2({ 
        placeholder: "Busca variables...", 
        width: '100%',
        allowClear: true
    });

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
            
            if (Array.isArray(data?.miembros)) {
                data.miembros.forEach(v => {
                    const nombreLimpio = v.nombre.replace(/^.*\.\[?(.*?)\]?$/, '$1');
                    select.append(new Option(nombreLimpio, nombreLimpio));
                });
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
    document.getElementById('resultadosContainer').classList.add('d-none');
    
    try {
        const cluesInput = document.getElementById('cluesInput').value.trim();
        if (!cluesInput) {
            throw new Error('Por favor ingresa al menos una CLUES');
        }

        const cluesList = cluesInput.split(',').map(clues => clues.trim()).filter(clues => clues);
        
        const payload = {
            catalogo: document.getElementById('catalogoSelect').value,
            cubo: cuboActivo,
            clues_list: cluesList,
            variables: $('#variablesSelect').val() || []
        };

        if (!payload.catalogo || !payload.cubo) {
            throw new Error('Por favor selecciona un catálogo');
        }

        console.log("Enviando payload:", payload);
        
        const response = await fetch(`${baseUrl}/total_pacientes_multiple`, {
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
        
        mostrarResultados(data);
        
    } catch (error) {
        console.error("Error completo:", error);
        document.getElementById('resultadosContainer').classList.remove('d-none');
        document.getElementById('resumenConsulta').innerHTML = `
            <strong>Error:</strong> ${error.message}
        `;
    } finally {
        ocultarSpinner();
    }
}

function mostrarResultados(data) {
    const container = document.getElementById('resultadosContainer');
    const resumen = document.getElementById('resumenConsulta');
    const resultadosDiv = document.getElementById('resultadosPorClues');
    
    // Limpiar resultados anteriores
    resultadosDiv.innerHTML = '';
    
    // Mostrar resumen
    resumen.innerHTML = `
        <strong>Consulta realizada:</strong> 
        Catálogo: ${data.catalogo} | 
        Cubo: ${data.cubo} | 
        CLUES consultadas: ${data.total_clues_consultadas}
    `;
    
    // Mostrar resultados por CLUES
    data.resultados.forEach(cluesData => {
        const card = document.createElement('div');
        card.className = 'card mb-4';
        
        const cardHeader = document.createElement('div');
        cardHeader.className = `card-header ${cluesData.estado === 'error' ? 'bg-danger text-white' : 'bg-primary text-white'}`;
        cardHeader.innerHTML = `
            <h5 class="mb-0">
                CLUES: ${cluesData.clues}
                <span class="badge ${cluesData.estado === 'error' ? 'bg-warning' : 'bg-success'} float-end">
                    ${cluesData.estado === 'error' ? 'Error' : cluesData.total_variables + ' variables'}
                </span>
            </h5>
        `;
        
        const cardBody = document.createElement('div');
        cardBody.className = 'card-body';
        
        if (cluesData.estado === 'error') {
            cardBody.innerHTML = `<p class="text-danger">${cluesData.mensaje}</p>`;
        } else if (cluesData.resultados.length === 0) {
            cardBody.innerHTML = `<p class="text-muted">No se encontraron resultados para esta CLUES</p>`;
        } else {
            const table = document.createElement('table');
            table.className = 'table table-striped table-hover';
            table.innerHTML = `
                <thead>
                    <tr>
                        <th>Variable</th>
                        <th>Total de Pacientes</th>
                    </tr>
                </thead>
                <tbody>
                    ${cluesData.resultados.map(item => `
                        <tr>
                            <td>${item.variable || 'N/A'}</td>
                            <td>${item.total_pacientes !== null ? item.total_pacientes : 'Sin datos'}</td>
                        </tr>
                    `).join('')}
                </tbody>
            `;
            cardBody.appendChild(table);
        }
        
        card.appendChild(cardHeader);
        card.appendChild(cardBody);
        resultadosDiv.appendChild(card);
    });
    
    container.classList.remove('d-none');
}

function mostrarSpinner() {
    document.getElementById('spinnerCarga').classList.remove('d-none');
}

function ocultarSpinner() {
    document.getElementById('spinnerCarga').classList.add('d-none');
}
</script>
@endsection