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
    <button class="btn btn-success mb-2 ms-2" onclick="abrirModalEdicion()" id="btnExportar" disabled>⬇️ Exportar a Excel</button>

    <div id="spinnerCarga" class="text-center my-4 d-none">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"></div>
        <p class="mt-2">Consultando...</p>
    </div>

    <div id="resultadosContainer" class="d-none">
        <div class="alert alert-info" id="resumenConsulta"></div>
        <div id="resultadosPorClues"></div>
    </div>
</div>

<!-- Modal de edición -->
<div class="modal fade" id="modalEdicion" tabindex="-1" aria-labelledby="modalEdicionLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title">📝 Editar y Exportar Resultados</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row mb-3">
          <div class="col">
            <label>🎨 Fondo encabezado</label>
            <input type="color" id="colorFondoEncabezado" class="form-control" value="#800080" onchange="actualizarVistaPrevia()">
          </div>
          <div class="col">
            <label>🔤 Texto encabezado</label>
            <input type="color" id="colorTextoEncabezado" class="form-control" value="#FFFFFF" onchange="actualizarVistaPrevia()">
          </div>
          <div class="col">
            <label>🎨 Fondo contenido</label>
            <input type="color" id="colorFondoContenido" class="form-control" value="#F5F5DC" onchange="actualizarVistaPrevia()">
          </div>
          <div class="col">
            <label>🔤 Texto contenido</label>
            <input type="color" id="colorTextoContenido" class="form-control" value="#000000" onchange="actualizarVistaPrevia()">
          </div>
        </div>
        <div class="table-responsive">
          <table id="tablaEdicion" class="table table-bordered table-striped">
            <thead>
              <tr>
                <th>CLUES</th>
                <th>Variable</th>
                <th>Total de Pacientes</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-success" onclick="exportarDesdeModal()">📥 Exportar Excel</button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

@endsection

@section('scripts')
<script>
const baseUrl = 'http://127.0.0.1:8070';
let cuboActivo = null;
let cluesDisponibles = [];
let todasLasVariables = new Set();

document.addEventListener('DOMContentLoaded', () => {
    $('#cluesSelect').select2({ placeholder: "Selecciona una o más CLUES", width: '100%', allowClear: true });
    $('#variablesSelect').select2({ placeholder: "Busca variables...", width: '100%', allowClear: true });

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
        resetearFormulario();
        if (!catalogo) return;
        $('#btnCargarClues').prop('disabled', false);
        fetch(`${baseUrl}/cubos_en_catalogo/${catalogo}`)
            .then(res => res.json())
            .then(data => {
                cuboActivo = data.cubos[0];
            });
    });

    $('#cluesSelect').on('change', function() {
        const cluesSeleccionadas = $(this).val();
        $('#btnCargarVariables').prop('disabled', cluesSeleccionadas.length === 0);
        if (cluesSeleccionadas.length === 0) resetearVariables();
    });
});

function resetearFormulario() {
    $('#cluesSelect').val(null).trigger('change').prop('disabled', true);
    $('#btnCargarClues').prop('disabled', true);
    $('#btnCargarVariables').prop('disabled', true);
    resetearVariables();
    document.getElementById('mensajeCluesCargadas').classList.add('d-none');
    document.getElementById('mensajeCargadas').classList.add('d-none');
}

function resetearVariables() {
    $('#variablesSelect').val(null).trigger('change').prop('disabled', true);
    $('#btnConsultar').prop('disabled', true);
    $('#btnExportar').prop('disabled', true);
    document.getElementById('mensajeCargadas').classList.add('d-none');
    todasLasVariables = new Set();
}

function cargarClues() {
    const catalogo = document.getElementById('catalogoSelect').value;
    if (!catalogo || !cuboActivo) return alert("Selecciona un catálogo primero.");
    mostrarSpinner(); resetearVariables();
    fetch(`${baseUrl}/miembros_jerarquia2?catalogo=${encodeURIComponent(catalogo)}&cubo=${encodeURIComponent(cuboActivo)}&jerarquia=CLUES`)
        .then(res => res.json())
        .then(data => {
            const select = $('#cluesSelect').empty();
            if (data.miembros.length > 0) {
                cluesDisponibles = data.miembros.map(m => m.nombre);
                cluesDisponibles.forEach(clues => select.append(new Option(clues, clues)));
                select.prop('disabled', false).trigger('change');
                document.getElementById('mensajeCluesCargadas').classList.remove('d-none');
            } else {
                alert("No se encontraron CLUES en este cubo.");
            }
        })
        .catch(() => alert("Ocurrió un error al cargar las CLUES."))
        .finally(() => ocultarSpinner());
}

async function cargarVariablesCombinadas() {
    const cluesSeleccionadas = $('#cluesSelect').val();
    if (!cluesSeleccionadas.length) return alert("Selecciona al menos una CLUES");
    mostrarSpinner(); document.getElementById('mensajeCargadas').classList.add('d-none');
    todasLasVariables = new Set();
    const catalogo = document.getElementById('catalogoSelect').value;
    const promesas = cluesSeleccionadas.map(clues =>
        fetch(`${baseUrl}/variables_pacientes_por_clues?catalogo=${encodeURIComponent(catalogo)}&cubo=${encodeURIComponent(cuboActivo)}&clues=${encodeURIComponent(clues)}`)
            .then(res => res.json())
            .then(data => data.variables?.forEach(v => todasLasVariables.add(v.variable)))
    );
    await Promise.all(promesas);
    const select = $('#variablesSelect').empty();
    if (todasLasVariables.size > 0) {
        Array.from(todasLasVariables).sort().forEach(variable =>
            select.append(new Option(variable, variable))
        );
        select.prop('disabled', false).trigger('change');
        document.getElementById('mensajeCargadas').classList.remove('d-none');
        $('#btnConsultar, #btnExportar').prop('disabled', false);
    } else {
        alert("No se encontraron variables.");
    }
    ocultarSpinner();
}

async function consultarVariables() {
    mostrarSpinner(); document.getElementById('resultadosContainer').classList.add('d-none');
    try {
        const catalogo = document.getElementById('catalogoSelect').value;
        const cluesSeleccionadas = $('#cluesSelect').val();
        const variables = $('#variablesSelect').val() || [];
        const payload = { catalogo, cubo: cuboActivo, clues_list: cluesSeleccionadas, variables };
        const res = await fetch(`${baseUrl}/total_pacientes_multiple`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || `Error HTTP ${res.status}`);
        mostrarResultados(data);
    } catch (err) {
        document.getElementById('resultadosContainer').classList.remove('d-none');
        document.getElementById('resumenConsulta').innerHTML = `<strong>Error:</strong> ${err.message}`;
    } finally {
        ocultarSpinner();
    }
}

function mostrarResultados(data) {
    const container = document.getElementById('resultadosContainer');
    const resumen = document.getElementById('resumenConsulta');
    const resultadosDiv = document.getElementById('resultadosPorClues');
    resultadosDiv.innerHTML = ''; window.resultadosExport = [];
    const variablesUnicas = new Set(data.resultados.flatMap(clues => clues.resultados.map(r => r.variable)));
    resumen.innerHTML = `Consulta realizada: Catálogo: ${data.catalogo} | Cubo: ${data.cubo} | CLUES: ${data.total_clues_consultadas} | Variables: ${variablesUnicas.size}`;
    data.resultados.forEach(cluesData => {
        const card = document.createElement('div');
        card.className = 'card mb-4';
        card.innerHTML = `
        <div class="card-header ${cluesData.estado === 'error' ? 'bg-danger' : 'bg-primary'} text-white">
            <h5 class="mb-0">CLUES: ${cluesData.clues}
                <span class="badge ${cluesData.estado === 'error' ? 'bg-warning' : 'bg-success'} float-end">
                    ${cluesData.estado === 'error' ? 'Error' : cluesData.total_variables + ' variables'}
                </span>
            </h5>
        </div>
        <div class="card-body">
            ${cluesData.estado === 'error'
            ? `<p class="text-danger">${cluesData.mensaje}</p>`
            : cluesData.resultados.length === 0
            ? `<p class="text-muted">Sin resultados</p>`
            : `
            <table class="table table-striped">
                <thead><tr><th>Variable</th><th>Total de Pacientes</th></tr></thead>
                <tbody>
                    ${cluesData.resultados.map(item => {
                        window.resultadosExport.push({
                            CLUES: cluesData.clues,
                            Variable: item.variable,
                            "Total de Pacientes": item.total_pacientes
                        });
                        return `<tr><td>${item.variable}</td><td>${item.total_pacientes}</td></tr>`;
                    }).join('')}
                </tbody>
            </table>`}
        </div>`;
        resultadosDiv.appendChild(card);
    });
    container.classList.remove('d-none');
}

function abrirModalEdicion() {
    const tbody = document.querySelector("#tablaEdicion tbody");
    tbody.innerHTML = "";
    if (!window.resultadosExport?.length) return alert("No hay datos para editar.");
    window.resultadosExport.forEach(row => {
        const tr = document.createElement("tr");
        tr.innerHTML = `
            <td contenteditable="true">${row.CLUES}</td>
            <td contenteditable="true">${row.Variable}</td>
            <td contenteditable="true">${row["Total de Pacientes"]}</td>
        `;
        tbody.appendChild(tr);
    });
    const modal = new bootstrap.Modal(document.getElementById('modalEdicion'));
    modal.show();
    actualizarVistaPrevia();
}

function exportarDesdeModal() {
    const filas = document.querySelectorAll("#tablaEdicion tbody tr");
    const datos = Array.from(filas).map(f => {
        const celdas = f.querySelectorAll("td");
        return {
            CLUES: celdas[0].innerText.trim(),
            Variable: celdas[1].innerText.trim(),
            "Total de Pacientes": parseFloat(celdas[2].innerText.trim()) || 0
        };
    });

    const hoja = XLSX.utils.json_to_sheet(datos);
    const libro = XLSX.utils.book_new();
    const keys = Object.keys(datos[0]);

    const estiloEncabezado = {
        fill: { fgColor: { rgb: document.getElementById("colorFondoEncabezado").value.slice(1).toUpperCase() } },
        font: { color: { rgb: document.getElementById("colorTextoEncabezado").value.slice(1).toUpperCase() }, bold: true },
        alignment: { horizontal: "center" },
        border: {
            top: { style: "thin", color: { rgb: "000000" } },
            bottom: { style: "thin", color: { rgb: "000000" } },
            left: { style: "thin", color: { rgb: "000000" } },
            right: { style: "thin", color: { rgb: "000000" } }
        }
    };

    const estiloContenido = {
        fill: { fgColor: { rgb: document.getElementById("colorFondoContenido").value.slice(1).toUpperCase() } },
        font: { color: { rgb: document.getElementById("colorTextoContenido").value.slice(1).toUpperCase() } },
        border: {
            top: { style: "thin", color: { rgb: "000000" } },
            bottom: { style: "thin", color: { rgb: "000000" } },
            left: { style: "thin", color: { rgb: "000000" } },
            right: { style: "thin", color: { rgb: "000000" } }
        }
    };

    for (let i = 0; i < keys.length; i++) {
        const cell = hoja[`${String.fromCharCode(65 + i)}1`];
        if (cell) cell.s = estiloEncabezado;
    }

    for (let r = 0; r < datos.length; r++) {
        for (let c = 0; c < keys.length; c++) {
            const cell = hoja[`${String.fromCharCode(65 + c)}${r + 2}`];
            if (cell) cell.s = estiloContenido;
        }
    }

    XLSX.utils.book_append_sheet(libro, hoja, "Personalizado");
    XLSX.writeFile(libro, "resultados_personalizados.xlsx");
}

function actualizarVistaPrevia() {
    const fondoHeader = document.getElementById("colorFondoEncabezado").value;
    const textoHeader = document.getElementById("colorTextoEncabezado").value;
    const fondoContenido = document.getElementById("colorFondoContenido").value;
    const textoContenido = document.getElementById("colorTextoContenido").value;
    document.querySelectorAll("#tablaEdicion thead th").forEach(th => {
        th.style.backgroundColor = fondoHeader;
        th.style.color = textoHeader;
    });
    document.querySelectorAll("#tablaEdicion tbody td").forEach(td => {
        td.style.backgroundColor = fondoContenido;
        td.style.color = textoContenido;
    });
}

function mostrarSpinner() {
    document.getElementById('spinnerCarga').classList.remove('d-none');
}
function ocultarSpinner() {
    document.getElementById('spinnerCarga').classList.add('d-none');
}
</script>
@endsection
