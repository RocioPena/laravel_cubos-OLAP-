from fastapi import FastAPI
from fastapi.responses import JSONResponse
from fastapi import Body
import win32com.client
import pythoncom
import pandas as pd
import math
from typing import List
from fastapi import Query


app = FastAPI()


# === Función auxiliar para crear una conexión ===
def crear_conexion(catalogo: str = None):
    pythoncom.CoInitialize()
    conn = win32com.client.Dispatch("ADODB.Connection")

    cadena = (
        "Provider=MSOLAP.8;"
        "Data Source=pwidgis03.salud.gob.mx;"
        "User ID=SALUD\\DGIS15;"
        "Password=Temp123!;"
        "Persist Security Info=True;"
        "Connect Timeout=60;"
    )

    if catalogo:
        cadena += f"Initial Catalog={catalogo};"

    conn.Open(cadena)
    return conn

# === Función auxiliar para ejecutar query y devolver lista ===
def ejecutar_query_lista(conn, query, campo):
    rs = win32com.client.Dispatch("ADODB.Recordset")
    rs.Open(query, conn)
    resultados = []
    while not rs.EOF:
        resultados.append(rs.Fields(campo).Value)
        rs.MoveNext()
    rs.Close()
    return resultados

def query_olap(connection_string: str, query: str) -> pd.DataFrame:
    pythoncom.CoInitialize()
    conn = win32com.client.Dispatch("ADODB.Connection")
    rs = win32com.client.Dispatch("ADODB.Recordset")
    conn.Open(connection_string)
    rs.Open(query, conn)

    fields = [rs.Fields.Item(i).Name for i in range(rs.Fields.Count)]
    data = []
    while not rs.EOF:
        row = [rs.Fields.Item(i).Value for i in range(rs.Fields.Count)]
        data.append(row)
        rs.MoveNext()
    rs.Close()
    conn.Close()
    pythoncom.CoUninitialize()
    return pd.DataFrame(data, columns=fields)



def sanitize_result(data):
    if isinstance(data, float) and (math.isnan(data) or data == float("inf") or data == float("-inf")):
        return None
    elif isinstance(data, list):
        return [sanitize_result(x) for x in data]
    elif isinstance(data, dict):
        return {k: sanitize_result(v) for k, v in data.items()}
    return data


# === Endpoint: cubos disponibles ===
@app.get("/cubos_disponibles")
def cubos_disponibles():
    try:
        conn = crear_conexion()
        cubos = ejecutar_query_lista(conn, "SELECT [catalog_name] FROM $system.DBSCHEMA_CATALOGS", "CATALOG_NAME")
        conn.Close()
        pythoncom.CoUninitialize()
        return {"cubos": list(set(cubos))}
    except Exception as e:
        return JSONResponse(status_code=500, content={"error": str(e)})

# === Endpoint: cubos en catálogo específico ===
@app.get("/cubos_en_catalogo/{catalogo}")
def cubos_en_catalogo(catalogo: str):
    try:
        conn = crear_conexion(catalogo)
        cubos = ejecutar_query_lista(conn, "SELECT CUBE_NAME FROM $system.mdschema_cubes WHERE CUBE_SOURCE = 1", "CUBE_NAME")
        conn.Close()
        pythoncom.CoUninitialize()
        return {"catalogo": catalogo, "cubos": cubos}
    except Exception as e:
        return JSONResponse(status_code=500, content={"error": str(e)})

# === Endpoint: explorar catálogo con detalles ===
@app.get("/explorar_catalogo/{catalogo}")
def explorar_catalogo(catalogo: str):
    try:
        conn = crear_conexion(catalogo)
        resultado = {"catalogo": catalogo, "cubos": []}

        cubos = ejecutar_query_lista(conn, "SELECT CUBE_NAME FROM $system.mdschema_cubes WHERE CUBE_SOURCE = 1", "CUBE_NAME")

        for cubo in cubos:
            cubo_info = {
                "cubo": cubo,
                "jerarquias": ejecutar_query_lista(conn, f"SELECT HIERARCHY_NAME FROM $system.mdschema_hierarchies WHERE CUBE_NAME = '{cubo}'", "HIERARCHY_NAME"),
                "niveles": ejecutar_query_lista(conn, f"SELECT LEVEL_NAME FROM $system.mdschema_levels WHERE CUBE_NAME = '{cubo}'", "LEVEL_NAME"),
                "medidas": ejecutar_query_lista(conn, f"SELECT MEASURE_NAME FROM $system.mdschema_measures WHERE CUBE_NAME = '{cubo}'", "MEASURE_NAME")
            }
            resultado["cubos"].append(cubo_info)

        conn.Close()
        pythoncom.CoUninitialize()
        return resultado
    except Exception as e:
        return JSONResponse(status_code=500, content={"error": str(e)})
@app.get("/cubos_sis")
def cubos_sis():
    try:
        conn = crear_conexion()
        # Obtenemos todos los catálogos
        cubos = ejecutar_query_lista(conn, "SELECT [catalog_name] FROM $system.DBSCHEMA_CATALOGS", "CATALOG_NAME")
        conn.Close()
        pythoncom.CoUninitialize()

        # Filtramos los que contienen 'sis' (insensible a mayúsculas)
        cubos_filtrados = [cubo for cubo in cubos if 'sis' in cubo.lower()]
        return {"cubos_sis": cubos_filtrados}

    except Exception as e:
        return JSONResponse(status_code=500, content={"error": str(e)})
@app.get("/explorar_sis")
def explorar_sis():
    try:
        conn = crear_conexion()
        catalogos = ejecutar_query_lista(conn, "SELECT [catalog_name] FROM $system.DBSCHEMA_CATALOGS", "CATALOG_NAME")
        conn.Close()

        # Filtrar solo los que contienen 'sis'
        catalogos_sis = [c for c in catalogos if "sis" in c.lower()]
        resultado = []

        for catalogo in catalogos_sis:
            conn = crear_conexion(catalogo)
            cubos = ejecutar_query_lista(conn, "SELECT CUBE_NAME FROM $system.mdschema_cubes WHERE CUBE_SOURCE = 1", "CUBE_NAME")
            catalogo_info = {
                "catalogo": catalogo,
                "cubos": []
            }

            for cubo in cubos:
                cubo_info = {
                    "cubo": cubo,
                    "jerarquias": ejecutar_query_lista(conn, f"SELECT HIERARCHY_NAME FROM $system.mdschema_hierarchies WHERE CUBE_NAME = '{cubo}'", "HIERARCHY_NAME"),
                    "niveles": ejecutar_query_lista(conn, f"SELECT LEVEL_NAME FROM $system.mdschema_levels WHERE CUBE_NAME = '{cubo}'", "LEVEL_NAME"),
                    "medidas": ejecutar_query_lista(conn, f"SELECT MEASURE_NAME FROM $system.mdschema_measures WHERE CUBE_NAME = '{cubo}'", "MEASURE_NAME")
                }
                catalogo_info["cubos"].append(cubo_info)

            resultado.append(catalogo_info)
            conn.Close()

        pythoncom.CoUninitialize()
        return resultado

    except Exception as e:
        return JSONResponse(status_code=500, content={"error": str(e)})


@app.get("/inspeccionar_columnas_miembros/{catalogo}/{cubo}")
def inspeccionar_columnas_miembros(catalogo: str, cubo: str):
    try:
        conn = crear_conexion(catalogo)
        rs = win32com.client.Dispatch("ADODB.Recordset")
        query = f"SELECT * FROM $system.mdschema_members WHERE CUBE_NAME = '{cubo}'"
        rs.Open(query, conn)

        columnas = [rs.Fields.Item(i).Name for i in range(rs.Fields.Count)]

        rs.Close()
        conn.Close()
        pythoncom.CoUninitialize()
        return {"columnas_disponibles": columnas}
    except Exception as e:
        return JSONResponse(status_code=500, content={"error": str(e)})



@app.get("/miembros_jerarquia")
def miembros_jerarquia(
    catalogo: str,
    cubo: str,
    jerarquia: str
):
    try:
        mdx = f"""
        SELECT 
            {{ [Measures].DefaultMember }} ON COLUMNS,
            {{ [{jerarquia}].MEMBERS }} ON ROWS
        FROM [{cubo}]
        """

        cadena_conexion = (
            "Provider=MSOLAP.8;"
            "Data Source=pwidgis03.salud.gob.mx;"
            "User ID=SALUD\\DGIS15;"
            "Password=Temp123!;"
            f"Initial Catalog={catalogo};"
        )

        df = query_olap(cadena_conexion, mdx)
        df = df.rename(columns=lambda x: x.strip())

        miembros = []
        for _, row in df.iterrows():
            miembros.append({
                "nombre": row[0]  # En muchos casos el nombre del miembro
            })

        return {"jerarquia": jerarquia, "miembros": miembros}

    except Exception as e:
        return JSONResponse(status_code=500, content={"error": str(e)})




@app.get("/variables_por_clues")
def variables_por_clues(
    catalogo: str,
    cubo: str,
    clues: List[str] = Query(...)
):
    try:
        # Armamos el WHERE con múltiples CLUES
        clues_mdx = ", ".join(f"[DIM UNIDAD].[Clues].[{clue}]" for clue in clues)

        mdx = f"""
        SELECT 
            [DIM VARIABLES].[Variable].MEMBERS ON ROWS,
            {{}} ON COLUMNS
        FROM [{cubo}]
        WHERE ({{ {clues_mdx} }})
        """

        cadena_conexion = (
            "Provider=MSOLAP.8;"
            "Data Source=pwidgis03.salud.gob.mx;"
            "User ID=SALUD\\DGIS15;"
            "Password=Temp123!;"
            f"Initial Catalog={catalogo};"
        )

        df = query_olap(cadena_conexion, mdx)
        df = df.rename(columns=lambda x: x.strip())

        resultados = [{"variable": row[0]} for _, row in df.iterrows()]

        return {
            "catalogo": catalogo,
            "cubo": cubo,
            "clues": clues,
            "variables": resultados
        }

    except Exception as e:
        return JSONResponse(status_code=500, content={"error": str(e)})