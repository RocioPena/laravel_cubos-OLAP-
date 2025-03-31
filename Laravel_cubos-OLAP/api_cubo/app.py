from fastapi import FastAPI
from fastapi.responses import JSONResponse
from fastapi import Body
import win32com.client
import pythoncom
import pandas as pd
import math
from typing import List
from fastapi import Query
from fastapi.middleware.cors import CORSMiddleware




app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # Puedes restringir esto a tu dominio
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


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

@app.get("/miembros_jerarquia2")
def miembros_jerarquia(
    catalogo: str,
    cubo: str,
    jerarquia: str
):
    try:
        # Si el cubo tiene espacio, lo envolvemos en comillas dobles
        cubo_mdx = f'"{cubo}"' if " " in cubo else f"[{cubo}]"

        # Si la jerarquía no incluye el nombre completo, lo armamos como [CLUES].[CLUES]
        if "." not in jerarquia:
            jerarquia_completa = f"[{jerarquia}].[{jerarquia}]"
        else:
            jerarquia_completa = f"[{jerarquia}]"

        mdx = f"""
        SELECT 
            {{ [Measures].DefaultMember }} ON COLUMNS,
            {{ {jerarquia_completa}.MEMBERS }} ON ROWS
        FROM {cubo_mdx}
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

        miembros = [{"nombre": row[0]} for _, row in df.iterrows()]
        return {"jerarquia": jerarquia_completa, "miembros": miembros}

    except Exception as e:
        return JSONResponse(status_code=500, content={"error": str(e)})

@app.get("/variables_por_clues")
def variables_por_clues(
    catalogo: str,
    cubo: str,
    clues: str
):
    try:
        mdx = f"""
        SELECT 
            [Variable].[Variable].MEMBERS ON ROWS,
            {{ [Measures].DefaultMember }} ON COLUMNS
        FROM [{cubo}]
        WHERE ([CLUES].[CLUES].&[{clues}])
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

        variables = [{"nombre": row[0]} for _, row in df.iterrows()]
        return {
            "clues": clues,
            "variables": variables
        }

    except Exception as e:
        return JSONResponse(status_code=500, content={"error": str(e)})



@app.post("/variables_por_clues_multiple")
def variables_por_clues_multiple(
    catalogo: str = Body(...),
    cubo: str = Body(...),
    clues: str = Body(...),
    variables: List[str] = Body(...)
):
    try:
        print(f"Recibido: catalogo={catalogo}, cubo={cubo}, clues={clues}, variables={variables}")
        
        # Define connection string
        cadena_conexion = (
            "Provider=MSOLAP.8;"
            "Data Source=pwidgis03.salud.gob.mx;"
            "User ID=SALUD\\DGIS15;"
            "Password=Temp123!;"
            f"Initial Catalog={catalogo};"
        )

        # Try a simplified query first to check if the CLUES exists
        mdx_check = f"""
        SELECT 
        {{[Measures].DefaultMember}} ON COLUMNS
        FROM [{cubo}]
        WHERE ([CLUES].[CLUES].&[{clues}])
        """
        
        try:
            # Test if CLUES exists
            check_df = query_olap(cadena_conexion, mdx_check)
            print("CLUES check result:", check_df.to_dict())
        except Exception as e:
            print(f"CLUES check error: {str(e)}")
            # If there's an error, the CLUES might be invalid
            return JSONResponse(
                status_code=400, 
                content={"error": f"La CLUES '{clues}' no existe o no es válida."}
            )

        # Modified MDX query to better handle variable references
        mdx = f"""
        SELECT 
        {{[Measures].DefaultMember}} ON COLUMNS,
        {{ {", ".join(f"[Variable].[Variable].[{v}]" for v in variables)} }} ON ROWS
        FROM [{cubo}]
        WHERE ([CLUES].[CLUES].[{clues}])
        """
        
        print("MDX generado:", mdx)
        
        df = query_olap(cadena_conexion, mdx)
        print("Datos crudos:", df.to_dict())
        
        # Check if we got any data
        if df.empty:
            print("DataFrame está vacío - no hay resultados")
            return {"clues": clues, "resultados": [], "message": "No se encontraron datos para esta consulta"}
        
        resultados = []
        for _, row in df.iterrows():
            # Try to extract variable name from full path
            nombre_variable = row[0]
            # Remove hierarchy prefix if present
            if '.[' in nombre_variable:
                nombre_variable = nombre_variable.split('.[')[-1].rstrip(']')
            
            resultados.append({
                "variable": nombre_variable,
                "valor": sanitize_result(row[1]) if len(row) > 1 else None
            })
        
        print("Resultados procesados:", resultados)
        return {"clues": clues, "resultados": resultados}

    except Exception as e:
        print("Error en API:", str(e))
        import traceback
        traceback.print_exc()
        return JSONResponse(status_code=500, content={"error": str(e)})