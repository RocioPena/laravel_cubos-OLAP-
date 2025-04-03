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
        <label for="cluesSelect" class="form-label">Selecciona CLUES:</label>
        <select id="cluesSelect" class="form-select" multiple disabled>
            <option value="">-- Primero selecciona un catálogo --</option>
        </select>
    </div>

    <div class="mb-3">
        <button class="btn btn-secondary" onclick="cargarClues()" id="btnCargarClues" disabled>🔍 Cargar CLUES disponibles</button>
    </div>

    <div id="mensajeCluesCargadas" class="alert alert-info d-none">
        ✅ CLUES cargadas correctamente.
    </div>

    <div class="mb-3">
        <button class="btn btn-info" onclick="cargarVariablesCombinadas()" id="btnCargarVariables" disabled>🔍 Cargar Variables</button>
    </div>

    <div class="mb-3">
        <label for="variablesSelect" class="form-label">Selecciona Variables:</label>
        <select id="variablesSelect" class="form-select" multiple disabled></select>
    </div>

    <div id="mensajeCargadas" class="alert alert-success d-none">
        ✅ Variables cargadas correctamente. Ya puedes seleccionar variables y consultar.
    </div>

    <button class="btn btn-primary mb-2" onclick="consultarVariables()" id="btnConsultar" disabled>Consultar</button>
    <button class="btn btn-success mb-2 ms-2" onclick="exportarExcel()" id="btnExportar" disabled>⬇️ Exportar a Excel</button>
    <button class="btn btn-warning mb-2 ms-2" onclick="abrirModalEdicion()" id="btnEditar" disabled>✏️ Editar y Exportar</button>

    <div id="spinnerCarga" class="text-center my-4 d-none">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"></div>
        <p class="mt-2">Consultando...</p>
    </div>

    <div id="resultadosContainer" class="d-none">
        <div class="alert alert-info" id="resumenConsulta"></div>
        <div id="resultadosPorClues"></div>
    </div>
</div>

<!-- Modal de Edición -->
<div class="modal fade" id="modalEdicion" tabindex="-1" aria-labelledby="modalEdicionLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEdicionLabel">Editar y Personalizar Exportación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-3">
                        <label for="colorFondoEncabezado" class="form-label">Fondo Encabezado</label>
                        <input type="color" class="form-control form-control-color" id="colorFondoEncabezado" value="#800080" onchange="actualizarVistaPrevia()">
                    </div>
                    <div class="col-md-3">
                        <label for="colorTextoEncabezado" class="form-label">Texto Encabezado</label>
                        <input type="color" class="form-control form-control-color" id="colorTextoEncabezado" value="#FFFFFF" onchange="actualizarVistaPrevia()">
                    </div>
                    <div class="col-md-3">
                        <label for="colorFondoContenido" class="form-label">Fondo Contenido</label>
                        <input type="color" class="form-control form-control-color" id="colorFondoContenido" value="#F5F5DC" onchange="actualizarVistaPrevia()">
                    </div>
                    <div class="col-md-3">
                        <label for="colorTextoContenido" class="form-label">Texto Contenido</label>
                        <input type="color" class="form-control form-control-color" id="colorTextoContenido" value="#000000" onchange="actualizarVistaPrevia()">
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table" id="tablaEdicion">
                        <thead>
                            <tr>
                                <th>CLUES</th>
                                <th>Entidad</th>
                                <th>Jurisdicción</th>
                                <th>Municipio</th>
                                <th>Unidad Médica</th>
                                <th>Variable</th>
                                <th>Total de Pacientes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Los datos se insertarán dinámicamente aquí -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" onclick="exportarDesdeModal()">Exportar Personalizado</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.min.js"></script>

@endsection

@section('scripts')
<script>
const baseUrl = 'http://127.0.0.1:8070';
let cuboActivo = null;
let cluesDisponibles = [];
let todasLasVariables = new Set(); 
let resultadosConsulta = []; // Variable global para almacenar los resultados

document.addEventListener('DOMContentLoaded', () => {
   
    $('#cluesSelect').select2({
        placeholder: "Selecciona una o más CLUES",
        width: '100%',
        allowClear: true
    });

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
        if (!catalogo) {
            resetearFormulario();
            return;
        }

        $('#btnCargarClues').prop('disabled', false);
        
        fetch(`${baseUrl}/cubos_en_catalogo/${catalogo}`)
            .then(res => res.json())
            .then(data => {
                cuboActivo = data.cubos[0];
            });
    });

    $('#cluesSelect').on('change', function() {
        const cluesSeleccionadas = $(this).val();
        if (cluesSeleccionadas && cluesSeleccionadas.length > 0) {
            $('#btnCargarVariables').prop('disabled', false);
        } else {
            $('#btnCargarVariables').prop('disabled', true);
            resetearVariables();
        }
    });
});

function resetearFormulario() {
    $('#cluesSelect').val(null).trigger('change').prop('disabled', true);
    $('#btnCargarClues').prop('disabled', true);
    $('#btnCargarVariables').prop('disabled', true);
    resetearVariables();

    document.getElementById('mensajeCluesCargadas').classList.add('d-none');
    document.getElementById('mensajeCargadas').classList.add('d-none');
    $('#btnEditar').prop('disabled', true);
}

function resetearVariables() {
    $('#variablesSelect').val(null).trigger('change').prop('disabled', true);
    $('#btnConsultar').prop('disabled', true);
    $('#btnExportar').prop('disabled', true);
    $('#btnEditar').prop('disabled', true);
    document.getElementById('mensajeCargadas').classList.add('d-none');
    todasLasVariables = new Set();
}

function cargarClues() {
    const catalogo = document.getElementById('catalogoSelect').value;

    if (!catalogo || !cuboActivo) {
        alert("Selecciona un catálogo primero.");
        return;
    }

    mostrarSpinner();
    resetearVariables();
    document.getElementById('mensajeCluesCargadas').classList.add('d-none');
    $('#btnCargarVariables').prop('disabled', true);

    fetch(`${baseUrl}/miembros_jerarquia2?catalogo=${encodeURIComponent(catalogo)}&cubo=${encodeURIComponent(cuboActivo)}&jerarquia=CLUES`)
        .then(res => res.json())
        .then(data => {
            const select = $('#cluesSelect');
            select.empty();
            
            if (data.miembros && data.miembros.length > 0) {
                cluesDisponibles = data.miembros.map(m => m.nombre);
                
                cluesDisponibles.forEach(clues => {
                    select.append(new Option(clues, clues));
                });
                
                select.prop('disabled', false);
                select.trigger('change');
                
                document.getElementById('mensajeCluesCargadas').classList.remove('d-none');
            } else {
                alert("No se encontraron CLUES en este cubo.");
                select.prop('disabled', true);
            }
        })
        .catch(err => {
            console.error("Error al cargar CLUES:", err);
            alert("Ocurrió un error al cargar las CLUES.");
        })
        .finally(() => ocultarSpinner());
}

async function cargarVariablesCombinadas() {
    const cluesSeleccionadas = $('#cluesSelect').val();
    
    if (!cluesSeleccionadas || cluesSeleccionadas.length === 0) {
        alert("Por favor selecciona al menos una CLUES primero.");
        return;
    }

    mostrarSpinner();
    document.getElementById('mensajeCargadas').classList.add('d-none');
    todasLasVariables = new Set();

    const promesasCarga = cluesSeleccionadas.map(clues => {
        const catalogo = document.getElementById('catalogoSelect').value;
        return fetch(`${baseUrl}/variables_pacientes_por_clues?catalogo=${encodeURIComponent(catalogo)}&cubo=${encodeURIComponent(cuboActivo)}&clues=${encodeURIComponent(clues)}`)
            .then(res => res.json())
            .then(data => {
                if (data.variables && data.variables.length > 0) {
                    data.variables.forEach(v => {
                        todasLasVariables.add(v.variable);
                    });
                }
            });
    });

    try {
        await Promise.all(promesasCarga);

        const select = $('#variablesSelect');
        select.empty();

        if (todasLasVariables.size > 0) {
            const variablesOrdenadas = Array.from(todasLasVariables).sort();
            
            variablesOrdenadas.forEach(variable => {
                select.append(new Option(variable, variable));
            });
            
            select.prop('disabled', false);
            select.trigger('change');

            document.getElementById('mensajeCargadas').classList.remove('d-none');
            $('#btnConsultar').prop('disabled', false);
            $('#btnExportar').prop('disabled', false);
            $('#btnEditar').prop('disabled', false);
        } else {
            alert("No se encontraron variables con datos para las CLUES seleccionadas.");
            select.prop('disabled', true);
        }
    } catch (err) {
        console.error("Error al cargar variables por CLUES:", err);
        alert("Ocurrió un error al cargar las variables.");
    } finally {
        ocultarSpinner();
    }
}

async function consultarVariables() {
    mostrarSpinner();
    document.getElementById('resultadosContainer').classList.add('d-none');

    try {
        const catalogo = document.getElementById('catalogoSelect').value;
        const cluesSeleccionadas = $('#cluesSelect').val();
        const variables = $('#variablesSelect').val() || [];

        if (!catalogo || !cuboActivo || !cluesSeleccionadas || cluesSeleccionadas.length === 0) {
            throw new Error("Por favor completa el catálogo y selecciona al menos una CLUES");
        }

        const payload = {
            catalogo: catalogo,
            cubo: cuboActivo,
            clues_list: cluesSeleccionadas,
            variables: variables
        };

        const response = await fetch(`${baseUrl}/total_pacientes_multiple_detallado`, {
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

        // Guardamos los resultados en la variable global
        resultadosConsulta = data.resultados;
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

    const variablesUnicas = new Set();
    data.resultados.forEach(cluesData => {
        if (cluesData.resultados) {
            cluesData.resultados.forEach(item => {
                variablesUnicas.add(item.variable);
            });
        }
    });

    resumen.innerHTML = `
        <strong>Consulta realizada:</strong> 
        Catálogo: ${data.catalogo} |
        Cubo: ${data.cubo} |
        CLUES consultadas: ${data.total_clues_consultadas} |
        Variables consultadas: ${variablesUnicas.size}
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
                        <th>Entidad</th>
                        <th>Jurisdicción</th>
                        <th>Municipio</th>
                        <th>Unidad Médica</th>
                        <th>Variable</th>
                        <th>Total de Pacientes</th>
                    </tr>
                </thead>
                <tbody>
                    ${cluesData.resultados.map(item => {
                        window.resultadosExport.push({
                            CLUES: cluesData.clues,
                            Entidad: cluesData.unidad?.entidad || '',
                            Jurisdicción: cluesData.unidad?.jurisdiccion || '',
                            Municipio: cluesData.unidad?.municipio || '',
                            "Unidad Médica": cluesData.unidad?.unidad_medica || '',
                            Variable: item.variable,
                            "Total de Pacientes": item.total_pacientes
                        });
                        return `
                            <tr>
                                <td>${cluesData.unidad?.entidad || 'N/A'}</td>
                                <td>${cluesData.unidad?.jurisdiccion || 'N/A'}</td>
                                <td>${cluesData.unidad?.municipio || 'N/A'}</td>
                                <td>${cluesData.unidad?.unidad_medica || 'N/A'}</td>
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

function abrirModalEdicion() {
    const tbody = document.querySelector("#tablaEdicion tbody");
    tbody.innerHTML = "";

    if (!window.resultadosExport || window.resultadosExport.length === 0) {
        alert("No hay datos para editar.");
        return;
    }

    // Usamos los datos de resultadosConsulta en lugar de resultadosExport si es necesario
    resultadosConsulta.forEach(cluesData => {
        if (cluesData.resultados && cluesData.resultados.length > 0) {
            cluesData.resultados.forEach(item => {
                const tr = document.createElement("tr");
                    tr.innerHTML = `
                        <td contenteditable="true">${cluesData.clues}</td>
                        <td contenteditable="true">${cluesData.unidad?.entidad || ''}</td>
                        <td contenteditable="true">${cluesData.unidad?.jurisdiccion || ''}</td>
                        <td contenteditable="true">${cluesData.unidad?.municipio || ''}</td>
                        <td contenteditable="true">${cluesData.unidad?.unidad_medica || ''}</td>
                        <td contenteditable="true">${item.variable}</td>
                        <td contenteditable="true">${item.total_pacientes !== null ? item.total_pacientes : '0'}</td>
                    `;

                tbody.appendChild(tr);
            });
        }
    });

    // Mostrar modal
    const modal = new bootstrap.Modal(document.getElementById('modalEdicion'));
    modal.show();

    // Aplicar colores seleccionados
    actualizarVistaPrevia();
}

function exportarDesdeModal() {
    const tabla = document.getElementById("tablaEdicion");
    const filas = tabla.querySelectorAll("tbody tr");
    const datos = [];

    filas.forEach(fila => {
        const celdas = fila.querySelectorAll("td");
        datos.push({
            CLUES: celdas[0].innerText.trim(),
            Entidad: celdas[1].innerText.trim(),
            Jurisdicción: celdas[2].innerText.trim(),
            Municipio: celdas[3].innerText.trim(),
            "Unidad Médica": celdas[4].innerText.trim(),
            Variable: celdas[5].innerText.trim(),
            "Total de Pacientes": parseFloat(celdas[6].innerText.trim()) || 0
        });
    });

    const hoja = XLSX.utils.json_to_sheet(datos);

    // Obtener colores elegidos
    const colorFondoEncabezado = document.getElementById("colorFondoEncabezado").value.replace("#", "").toUpperCase();
    const colorTextoEncabezado = document.getElementById("colorTextoEncabezado").value.replace("#", "").toUpperCase();
    const colorFondoContenido = document.getElementById("colorFondoContenido").value.replace("#", "").toUpperCase();
    const colorTextoContenido = document.getElementById("colorTextoContenido").value.replace("#", "").toUpperCase();

    // Estilos dinámicos
    const estiloEncabezado = {
        fill: { fgColor: { rgb: colorFondoEncabezado } },
        font: { color: { rgb: colorTextoEncabezado }, bold: true },
        alignment: { horizontal: "center" },
        border: {
            top: { style: "thin", color: { rgb: "000000" } },
            bottom: { style: "thin", color: { rgb: "000000" } },
            left: { style: "thin", color: { rgb: "000000" } },
            right: { style: "thin", color: { rgb: "000000" } }
        }
    };

    const estiloContenido = {
        fill: { fgColor: { rgb: colorFondoContenido } },
        font: { color: { rgb: colorTextoContenido } },
        border: {
            top: { style: "thin", color: { rgb: "000000" } },
            bottom: { style: "thin", color: { rgb: "000000" } },
            left: { style: "thin", color: { rgb: "000000" } },
            right: { style: "thin", color: { rgb: "000000" } }
        }
    };

    const keys = Object.keys(datos[0]);
    for (let i = 0; i < keys.length; i++) {
        const col = String.fromCharCode(65 + i); // A, B, C...
        const cell = hoja[`${col}1`];
        if (cell) cell.s = estiloEncabezado;
    }

    for (let r = 0; r < datos.length; r++) {
        for (let c = 0; c < keys.length; c++) {
            const cell = hoja[`${String.fromCharCode(65 + c)}${r + 2}`];
            if (cell) cell.s = estiloContenido;
        }
    }

    const libro = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(libro, hoja, "Personalizado");
    XLSX.writeFile(libro, "resultados_personalizados.xlsx");
}

function actualizarVistaPrevia() {
    const fondoHeader = document.getElementById("colorFondoEncabezado").value;
    const textoHeader = document.getElementById("colorTextoEncabezado").value;
    const fondoContenido = document.getElementById("colorFondoContenido").value;
    const textoContenido = document.getElementById("colorTextoContenido").value;

    // Encabezados
    const ths = document.querySelectorAll("#tablaEdicion thead th");
    ths.forEach(th => {
        th.style.backgroundColor = fondoHeader;
        th.style.color = textoHeader;
    });

    // Contenido
    const tds = document.querySelectorAll("#tablaEdicion tbody td");
    tds.forEach(td => {
        td.style.backgroundColor = fondoContenido;
        td.style.color = textoContenido;
    });
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