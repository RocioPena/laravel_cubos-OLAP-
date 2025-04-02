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
    allow_origins=["*"], 
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


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
        cubos = ejecutar_query_lista(conn, "SELECT [catalog_name] FROM $system.DBSCHEMA_CATALOGS", "CATALOG_NAME")
        conn.Close()
        pythoncom.CoUninitialize()

   
        cubos_filtrados = [c for c in cubos if 'sis' in c.lower() and 'sectorial' not in c.lower()]
        return {"cubos_sis": cubos_filtrados}

    except Exception as e:
        return JSONResponse(status_code=500, content={"error": str(e)})

@app.get("/explorar_sis")
def explorar_sis():
    try:
        conn = crear_conexion()
        catalogos = ejecutar_query_lista(conn, "SELECT [catalog_name] FROM $system.DBSCHEMA_CATALOGS", "CATALOG_NAME")
        conn.Close()

       
        catalogos_sis = [c for c in catalogos if "sis" in c.lower() and "sectorial" not in c.lower()]
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
     
        cubo_mdx = f'"{cubo}"' if " " in cubo else f"[{cubo}]"

      
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
        
       
        cadena_conexion = (
            "Provider=MSOLAP.8;"
            "Data Source=pwidgis03.salud.gob.mx;"
            "User ID=SALUD\\DGIS15;"
            "Password=Temp123!;"
            f"Initial Catalog={catalogo};"
        )

        mdx_check = f"""
        SELECT 
        {{[Measures].DefaultMember}} ON COLUMNS
        FROM [{cubo}]
        WHERE ([CLUES].[CLUES].&[{clues}])
        """
        
        try:
          
            check_df = query_olap(cadena_conexion, mdx_check)
            print("CLUES check result:", check_df.to_dict())
        except Exception as e:
            print(f"CLUES check error: {str(e)}")
          
            return JSONResponse(
                status_code=400, 
                content={"error": f"La CLUES '{clues}' no existe o no es válida."}
            )

    
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
        
       
        if df.empty:
            print("DataFrame está vacío - no hay resultados")
            return {"clues": clues, "resultados": [], "message": "No se encontraron datos para esta consulta"}
        
        resultados = []
        for _, row in df.iterrows():
          
            nombre_variable = row[0]
    
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

@app.get("/variables_pacientes_por_clues")
def variables_pacientes_por_clues(catalogo: str, cubo: str, clues: str):
    try:
        cubo_mdx = f'"{cubo}"' if " " in cubo else f"[{cubo}]"

        cadena_conexion = (
            "Provider=MSOLAP.8;"
            "Data Source=pwidgis03.salud.gob.mx;"
            "User ID=SALUD\\DGIS15;"
            "Password=Temp123!;"
            f"Initial Catalog={catalogo};"
        )

        mdx_check = f"""
        SELECT 
        {{[Measures].DefaultMember}} ON COLUMNS
        FROM {cubo_mdx}
        WHERE ([CLUES].[CLUES].&[{clues}])
        """

        try:
            check_df = query_olap(cadena_conexion, mdx_check)
        except Exception as e:
            return JSONResponse(
                status_code=400,
                content={"error": f"La CLUES '{clues}' no existe o no es válida en el catálogo/cubo especificado."}
            )

        mdx = f"""
        SELECT 
        {{[Measures].[Total]}} ON COLUMNS,
        {{[Variable].[Variable].MEMBERS}} ON ROWS
        FROM {cubo_mdx}
        WHERE ([CLUES].[CLUES].&[{clues}])
        """

        df = query_olap(cadena_conexion, mdx)

        if df is None or not hasattr(df, 'empty') or df.empty:
            return {"clues": clues, "variables": [], "message": "No se encontraron datos o el cubo no respondió correctamente"}

        variables = []
        for _, row in df.iterrows():
            if row[0] is None:
                continue

            nombre_variable = row[0]

            if '.[' in nombre_variable:
                nombre_variable = nombre_variable.split('.[')[-1].rstrip(']')

            valor = sanitize_result(row[1]) if len(row) > 1 else None
            if valor is not None and isinstance(valor, (int, float)) and valor > 0:
                variables.append({
                    "variable": nombre_variable,
                    "total_pacientes": valor
                })

        return {
            "clues": clues,
            "catalogo": catalogo,
            "cubo": cubo,
            "total_variables": len(variables),
            "variables": variables
        }

    except Exception as e:
        import traceback
        traceback.print_exc()
        return JSONResponse(status_code=500, content={"error": str(e)})


@app.post("/total_pacientes_multiple")
def total_pacientes_multiple(
    catalogo: str = Body(...),
    cubo: str = Body(...),
    clues_list: List[str] = Body(...),
    variables: List[str] = Body(default=None, description="Lista de variables a consultar (opcional)")
):
    if variables is None:
        variables = []

    try:
        print(f"Recibido: catalogo={catalogo}, cubo={cubo}, clues_list={clues_list}, variables={variables}")

        cubo_mdx = f'"{cubo}"' if " " in cubo else f"[{cubo}]"

        cadena_conexion = (
            "Provider=MSOLAP.8;"
            "Data Source=pwidgis03.salud.gob.mx;"
            "User ID=SALUD\\DGIS15;"
            "Password=Temp123!;"
            f"Initial Catalog={catalogo};"
        )

        resultados_por_clues = []

        for clues in clues_list:
            mdx_check = f"""
            SELECT 
            {{[Measures].DefaultMember}} ON COLUMNS
            FROM {cubo_mdx}
            WHERE ([CLUES].[CLUES].&[{clues}])
            """

            try:
                check_df = query_olap(cadena_conexion, mdx_check)
                print(f"CLUES {clues} check result:", check_df.to_dict() if check_df is not None else "None")
            except Exception as e:
                print(f"CLUES {clues} check error: {str(e)}")
                resultados_por_clues.append({
                    "clues": clues,
                    "estado": "error",
                    "mensaje": f"La CLUES '{clues}' no existe o no es válida.",
                    "resultados": []
                })
                continue

            if not variables:
                mdx = f"""
                SELECT 
                {{[Measures].[Total]}} ON COLUMNS,
                {{[Variable].[Variable].MEMBERS}} ON ROWS
                FROM {cubo_mdx}
                WHERE ([CLUES].[CLUES].&[{clues}])
                """
            else:
                mdx = f"""
                SELECT 
                {{[Measures].[Total]}} ON COLUMNS,
                {{ {", ".join(f"[Variable].[Variable].[{v}]" for v in variables)} }} ON ROWS
                FROM {cubo_mdx}
                WHERE ([CLUES].[CLUES].&[{clues}])
                """

            print(f"MDX generado para CLUES {clues}:", mdx)

            try:
                df = query_olap(cadena_conexion, mdx)
                if df is None or not hasattr(df, 'empty') or df.empty:
                    print(f"DataFrame está vacío para CLUES {clues} - no hay resultados")
                    resultados_por_clues.append({
                        "clues": clues,
                        "estado": "sin_datos",
                        "mensaje": "No se encontraron datos para esta consulta",
                        "resultados": []
                    })
                    continue

                resultados = []
                for _, row in df.iterrows():
                    if row[0] is None:
                        continue
                    nombre_variable = row[0]
                    if '.[' in nombre_variable:
                        nombre_variable = nombre_variable.split('.[')[-1].rstrip(']')
                    valor = sanitize_result(row[1]) if len(row) > 1 else None
                    if valor is not None and isinstance(valor, (int, float)):
                        resultados.append({
                            "variable": nombre_variable,
                            "total_pacientes": valor
                        })

                print(f"Resultados procesados para CLUES {clues}:", resultados)
                resultados_por_clues.append({
                    "clues": clues,
                    "estado": "exito",
                    "total_variables": len(resultados),
                    "resultados": resultados
                })

            except Exception as e:
                print(f"Error en consulta para CLUES {clues}:", str(e))
                resultados_por_clues.append({
                    "clues": clues,
                    "estado": "error",
                    "mensaje": f"Error al consultar datos: {str(e)}",
                    "resultados": []
                })

        return {
            "catalogo": catalogo,
            "cubo": cubo,
            "total_clues_consultadas": len(clues_list),
            "resultados": resultados_por_clues
        }

    except Exception as e:
        print("Error general en API:", str(e))
        import traceback
        traceback.print_exc()
        return JSONResponse(status_code=500, content={"error": str(e)})
