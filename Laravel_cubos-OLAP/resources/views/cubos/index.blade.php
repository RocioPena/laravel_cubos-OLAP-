@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h3 class="mb-4">Explorador de Cubos SIS</h3>

    <div class="mb-3">
        <label for="cuboSelect" class="form-label">Selecciona un catálogo SIS:</label>
        <select id="cuboSelect" class="form-select">
            <option value="">-- Selecciona un catálogo --</option>
        </select>
    </div>

    <div class="mb-3 d-flex gap-2">
        <button class="btn btn-primary" onclick="cargarMiembros('CLUES')">Mostrar CLUES</button>
        <button class="btn btn-secondary" onclick="cargarMiembros('Variable')">Mostrar Variables</button>
    </div>

    <div id="tablaResultados" class="table-responsive d-none">
        <table class="table table-bordered">
            <thead>
                <tr><th>#</th><th>Nombre</th></tr>
            </thead>
            <tbody id="tablaCuerpo"></tbody>
        </table>

        <nav>
            <ul class="pagination justify-content-center" id="paginacion"></ul>
        </nav>
    </div>
</div>
@endsection

@section('scripts')
<script>
const baseUrl = 'http://127.0.0.1:8070'; // Cambia si tu API FastAPI está en otro puerto
let miembrosGlobal = [];
let paginaActual = 1;
const porPagina = 8;

document.addEventListener('DOMContentLoaded', () => {
    fetch(`${baseUrl}/cubos_sis`)
        .then(res => res.json())
        .then(data => {
            const select = document.getElementById('cuboSelect');
            data.cubos_sis.forEach(catalogo => {
                const option = document.createElement('option');
                option.value = catalogo;
                option.textContent = catalogo;
                select.appendChild(option);
            });
        });
});

function cargarMiembros(jerarquia) {
    const catalogo = document.getElementById('cuboSelect').value;
    if (!catalogo) return alert('Selecciona un catálogo SIS');

    fetch(`${baseUrl}/cubos_en_catalogo/${catalogo}`)
        .then(res => res.json())
        .then(data => {
            const cubo = data.cubos[0]; // tomamos el primer cubo del catálogo
            console.log("📦 Cubo seleccionado:", cubo);

            const url = `${baseUrl}/miembros_jerarquia2?catalogo=${encodeURIComponent(catalogo)}&cubo=${encodeURIComponent(cubo)}&jerarquia=${encodeURIComponent(jerarquia)}`;

            fetch(url)
                .then(res => {
                    if (!res.ok) throw new Error("Error en la API");
                    return res.json();
                })
                .then(respuesta => {
                    miembrosGlobal = respuesta.miembros || [];
                    paginaActual = 1;
                    renderizarTabla();
                    document.getElementById('tablaResultados').classList.remove('d-none');
                })
                .catch(err => {
                    console.error("❌ Error al cargar miembros:", err);
                    alert("Error al cargar datos. Ver consola.");
                });
        });
}

function renderizarTabla() {
    const cuerpo = document.getElementById('tablaCuerpo');
    const paginacion = document.getElementById('paginacion');
    cuerpo.innerHTML = '';
    paginacion.innerHTML = '';

    const inicio = (paginaActual - 1) * porPagina;
    const datosPagina = miembrosGlobal.slice(inicio, inicio + porPagina);

    if (datosPagina.length === 0) {
        cuerpo.innerHTML = `<tr><td colspan="2" class="text-center text-muted">No hay datos disponibles.</td></tr>`;
        return;
    }

    datosPagina.forEach((m, i) => {
        cuerpo.innerHTML += `<tr><td>${inicio + i + 1}</td><td>${m.nombre}</td></tr>`;
    });

    const totalPaginas = Math.ceil(miembrosGlobal.length / porPagina);
    const maxPaginasVisibles = 5;
    const desde = Math.max(1, paginaActual - Math.floor(maxPaginasVisibles / 2));
    const hasta = Math.min(totalPaginas, desde + maxPaginasVisibles - 1);

    // Botón « anterior
    if (paginaActual > 1) {
        paginacion.innerHTML += `
            <li class="page-item">
                <a class="page-link" href="#" onclick="cambiarPagina(${paginaActual - 1})">«</a>
            </li>`;
    }

    // Números de página visibles
    for (let i = desde; i <= hasta; i++) {
        paginacion.innerHTML += `
            <li class="page-item ${i === paginaActual ? 'active' : ''}">
                <a class="page-link" href="#" onclick="cambiarPagina(${i})">${i}</a>
            </li>`;
    }

    // Botón siguiente »
    if (paginaActual < totalPaginas) {
        paginacion.innerHTML += `
            <li class="page-item">
                <a class="page-link" href="#" onclick="cambiarPagina(${paginaActual + 1})">»</a>
            </li>`;
    }
}


function cambiarPagina(pagina) {
    paginaActual = pagina;
    renderizarTabla();
}
</script>
@endsection
