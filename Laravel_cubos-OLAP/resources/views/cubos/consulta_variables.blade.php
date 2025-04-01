@extends('layouts.app')

@section('content')

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<div class="container mt-4">
    <h3 class="mb-4">Consulta por CLUES y Variables,</h3>

    <div class="mb-3">
        <label for="catalogoSelect" class="form-label">Selecciona un catálogo SIS:</label>
        <select id="catalogoSelect" class="form-select">
            <option value="">-- Selecciona un catálogo --</option>
        </select>
    </div>

    <div class="mb-3">
        <label for="cluesInput" class="form-label">CLUES (una sola CLUES para cargar variables específicas):</label>
        <input type="text" id="cluesInput" class="form-control" placeholder="Ej. MCL210000000">
    </div>

    <div class="mb-3">
        <button class="btn btn-secondary" onclick="cargarVariables()">🔍 Cargar Variables por CLUES</button>
    </div>

    <div class="mb-3">
        <label for="variablesSelect" class="form-label">Selecciona Variables (opcional):</label>
        <select id="variablesSelect" class="form-select" multiple disabled></select>
    </div>

    <!-- ✅ Mensaje de variables cargadas -->
    <div id="mensajeCargadas" class="alert alert-success d-none">
        ✅ Variables cargadas correctamente. Ya puedes seleccionar las que deseas consultar.
    </div>

    <button class="btn btn-primary mb-2" onclick="consultarVariables()">Consultar</button>
    <button class="btn btn-success mb-2 ms-2" onclick="exportarExcel()">⬇️ Exportar a Excel</button>

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
<script src="https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js"></script>

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
            });
    });
});

function cargarVariables() {
    const catalogo = document.getElementById('catalogoSelect').value;
    const clues = document.getElementById('cluesInput').value.trim();

    document.getElementById('mensajeCargadas').classList.add('d-none');

    if (!catalogo || !cuboActivo || !clues) {
        alert("Selecciona un catálogo y escribe una CLUES.");
        return;
    }

    if (clues.includes(',')) {
        alert("Solo puedes cargar variables con una CLUES a la vez.");
        return;
    }

    mostrarSpinner();

    fetch(`${baseUrl}/variables_pacientes_por_clues?catalogo=${encodeURIComponent(catalogo)}&cubo=${encodeURIComponent(cuboActivo)}&clues=${encodeURIComponent(clues)}`)
        .then(res => res.json())
        .then(data => {
            const select = $('#variablesSelect');
            select.empty();

            if (data.variables && data.variables.length > 0) {
                data.variables.forEach(v => {
                    select.append(new Option(v.variable, v.variable));
                });
                select.prop('disabled', false);
                select.trigger('change');

               
                document.getElementById('mensajeCargadas').classList.remove('d-none');
            } else {
                alert("No se encontraron variables con datos para esta CLUES.");
                select.prop('disabled', true);
            }
        })
        .catch(err => {
            console.error("Error al cargar variables por CLUES:", err);
            alert("Ocurrió un error al cargar las variables.");
        })
        .finally(() => ocultarSpinner());
}

async function consultarVariables() {
    mostrarSpinner();
    document.getElementById('resultadosContainer').classList.add('d-none');

    try {
        const catalogo = document.getElementById('catalogoSelect').value;
        const cluesInput = document.getElementById('cluesInput').value.trim();
        const cluesList = cluesInput.split(',').map(c => c.trim()).filter(c => c);
        const variables = $('#variablesSelect').val() || [];

        if (!catalogo || !cuboActivo || cluesList.length === 0) {
            throw new Error("Por favor completa el catálogo y al menos una CLUES");
        }

        const payload = {
            catalogo: catalogo,
            cubo: cuboActivo,
            clues_list: cluesList,
            variables: variables
        };

        const response = await fetch(`${baseUrl}/total_pacientes_multiple`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || `Error HTTP ${response.status}`);
        }

        mostrarResultados(data);

    } catch (error) {
        console.error("Error completo:", error);
        document.getElementById('resultadosContainer').classList.remove('d-none');
        document.getElementById('resumenConsulta').innerHTML = `<strong>Error:</strong> ${error.message}`;
    } finally {
        ocultarSpinner();
    }
}

function mostrarResultados(data) {
    const container = document.getElementById('resultadosContainer');
    const resumen = document.getElementById('resumenConsulta');
    const resultadosDiv = document.getElementById('resultadosPorClues');

    resultadosDiv.innerHTML = '';
    window.resultadosExport = [];

    resumen.innerHTML = `
        <strong>Consulta realizada:</strong> 
        Catálogo: ${data.catalogo} |
        Cubo: ${data.cubo} |
        CLUES consultadas: ${data.total_clues_consultadas}
    `;

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
                    ${cluesData.resultados.map(item => {
                        window.resultadosExport.push({
                            CLUES: cluesData.clues,
                            Variable: item.variable,
                            "Total de Pacientes": item.total_pacientes
                        });
                        return `
                            <tr>
                                <td>${item.variable || 'N/A'}</td>
                                <td>${item.total_pacientes !== null ? item.total_pacientes : 'Sin datos'}</td>
                            </tr>`;
                    }).join('')}
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

function exportarExcel() {
    if (!window.resultadosExport || window.resultadosExport.length === 0) {
        alert("No hay datos para exportar.");
        return;
    }

    const worksheet = XLSX.utils.json_to_sheet(window.resultadosExport);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, "Resultados");
    XLSX.writeFile(workbook, "resultados_clues.xlsx");
}

function mostrarSpinner() {
    document.getElementById('spinnerCarga').classList.remove('d-none');
}

function ocultarSpinner() {
    document.getElementById('spinnerCarga').classList.add('d-none');
}
</script>
@endsection
