# SISPAM - Sistema de Gestión Farmacéutica, Transcripción, Entrega y Turnero TV

Sistema completo desarrollado en **PHP 8+**, **Bootstrap 5.3**, **MySQL (PDO)** y **JavaScript ES6**, optimizado para el ingreso de pacientes, escaneo y categorización de documentos, transcripción de fórmulas con control de concurrencia, alistamiento semaforizado, entrega con firma digital en tableta táctil y pantallas de turnero TV con llamado por voz sintetizada en español.

---

## 🚀 Guía de Instalación Rápida en XAMPP (Local)

1. **Copiar Carpeta**:
   Copia la carpeta del proyecto `pharmacy_app` dentro del directorio `C:\xampp\htdocs\pharmacy_app`.

2. **Crear Base de Datos en phpMyAdmin**:
   - Abre `http://localhost/phpmyadmin/`.
   - Crea la base de datos `farmacia_db` con cotejamiento `utf8mb4_unicode_ci`.
   - Importa el archivo SQL ubicado en `database/schema.sql`.

3. **Ejecutar en el Navegador**:
   Abre `http://localhost/pharmacy_app/index.php`.

---

## ☁️ Guía de Despliegue en VPS Hostinger (Linux / Apache / Nginx)

1. **Subir Archivos**:
   Sube la carpeta del proyecto a `public_html` o a tu subdominio mediante FTP (FileZilla) o SSH.

2. **Importar MySQL en Hostinger**:
   Desde hPanel o la consola MySQL de Linux, ejecuta:
   ```bash
   mysql -u tu_usuario -p tu_base_datos < database/schema.sql
   ```

3. **Configurar Credenciales en `config/database.php`**:
   Edita las credenciales o define variables de entorno `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASS`.

4. **Permisos de Carpetas para Archivos Adjuntos**:
   Asegúrate de otorgar permisos de escritura a la carpeta de cargas:
   ```bash
   chmod -R 755 assets/uploads/
   ```

---

## 🔑 Credenciales de Prueba por Defecto

| Rol / Perfil | Usuario | Contraseña | Funciones |
| :--- | :--- | :--- | :--- |
| **Administrador** | `admin` | `admin123` | Control total, parámetros de empresa y usuarios. |
| **Orientador** | `orientador` | `admin123` | Admisión de pacientes, subida de documentos y tiquetes. |
| **Transcripción** | `transcriptor` | `admin123` | Verificación de stock, visor PDF dual y estado FEFO. |
| **Alistamiento** | `alistador` | `admin123` | Picking semaforizado y asignación a ventanillas. |
| **Entrega** | `entregador` | `admin123` | Factura, acta de entrega y captura de firma táctil. |

---

## 📺 Pantallas de Turnero TV

- **Turnero 1 (En Proceso)**: `http://localhost/pharmacy_app/index.php?page=turnero1`
- **Turnero 2 (Listo para Entrega con Voz)**: `http://localhost/pharmacy_app/index.php?page=turnero2`

# SISPAM — Documentación Técnica y Operativa

## 1. Descripción general

**SISPAM** es un sistema de gestión farmacéutica orientado al ingreso de pacientes, gestión de documentos, transcripción de fórmulas, control de concurrencia, alistamiento semaforizado, entrega de medicamentos con firma digital y operación de turneros en pantalla.

El repositorio está implementado principalmente con:

- PHP 8+
- MySQL/MariaDB mediante PDO
- Bootstrap 5.3
- JavaScript ES6
- Apache/Nginx según el entorno de despliegue

La estructura actual separa API, configuración, base de datos, modelos, servicios, vistas y recursos estáticos. Dentro de esta arquitectura existe una capa específica de integración con **QRYSTALOS**, actualmente preparada pero pendiente de completar con los datos y condiciones definitivas suministrados por QRYSTALOS.

> **Estado de la integración QRYSTALOS: PENDIENTE DE HABILITACIÓN/VALIDACIÓN PRODUCTIVA.**
>
> La integración no debe considerarse completamente operativa hasta recibir y validar las credenciales, URL definitiva, catálogos, reglas de negocio, permisos y demás parámetros reales de QRYSTALOS.

---

# 2. Arquitectura del proyecto

La raíz del proyecto contiene, entre otros, los siguientes componentes:

```text
sispam/
├── api/
│   ├── QrystalosClient.php
│   ├── buscar_paciente.php
│   ├── get_paciente.php
│   ├── lock_record.php
│   ├── notificaciones.php
│   └── turnero_data.php
├── assets/
├── config/
│   ├── app.php
│   ├── database.php
│   └── qrystalos.php
├── database/
├── models/
├── services/
│   └── QrystalosSyncService.php
├── views/
├── .env.example
├── composer.json
└── index.php
```

El repositorio público actual muestra específicamente la separación entre `api`, `config`, `database`, `models`, `services` y `views`. citeturn0view0

La integración QRYSTALOS está concentrada actualmente en:

- `config/qrystalos.php`
- `api/QrystalosClient.php`
- `services/QrystalosSyncService.php`

Estos archivos conforman la base de la futura interoperabilidad con QRYSTALOS. citeturn1view0turn1view1turn1view3

---

# 3. Requisitos de instalación

## 3.1 Entorno local

Para una instalación local se requiere:

- PHP 8+
- Apache o servidor web compatible
- MySQL/MariaDB
- Composer
- Extensión PHP PDO/MySQL
- cURL habilitado
- OpenSSL/TLS habilitado
- JavaScript habilitado en el navegador

El README del proyecto contempla una instalación local mediante XAMPP y una base de datos denominada `farmacia_db`. citeturn0view0

## 3.2 Base de datos

Ejemplo de configuración:

```env
DB_HOST=127.0.0.1
DB_NAME=farmacia_db
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4
DB_TIMEZONE=-05:00
```

La conexión se realiza mediante PDO y utiliza sentencias preparadas, excepciones y `utf8mb4`. También configura la zona horaria de la conexión cuando está definida. citeturn3view2

---

# 4. Variables de entorno

Se recomienda utilizar un archivo `.env` local y **no almacenar credenciales reales en Git**.

## 4.1 Base de datos

```env
DB_HOST=127.0.0.1
DB_NAME=farmacia_db
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4
DB_TIMEZONE=-05:00
```

## 4.2 QRYSTALOS

Configuración actualmente planteada:

```env
QRYSTALOS_BASE_URL=https://api-test.sispam.com

QRYSTALOS_AUTH_USER=usuario_temporal
QRYSTALOS_AUTH_PASS=clave_temporal

QRYSTALOS_USUARIO_AUDITORIA=USUARIO_INTEGRACION_TEMP

QRYSTALOS_ID_SEDE=29
QRYSTALOS_ID_ADMINISTRADORA=0100000010
QRYSTALOS_ID_PLAN=TARC26
QRYSTALOS_CIUDAD_DIVIPOLA=05001
QRYSTALOS_ID_BARRIO=05001001
```

> **Importante:** los valores anteriores son de configuración temporal/de desarrollo y **no deben interpretarse como credenciales ni catálogos definitivos de producción**.

El archivo `config/qrystalos.php` toma estos valores desde `$_ENV` y dispone además de valores predeterminados para varios campos obligatorios. citeturn2view2

---

# 5. Integración con QRYSTALOS

## 5.1 Objetivo

SISPAM incorpora una capa de interoperabilidad destinada a sincronizar información de pacientes con QRYSTALOS.

La integración está diseñada para que SISPAM construya un payload normalizado y lo envíe al servicio de QRYSTALOS mediante HTTP/JSON.

Actualmente el código contempla operaciones de:

- **INSERTAR** paciente.
- **EDITAR** paciente existente.

El cliente se encuentra implementado en `api/QrystalosClient.php`. citeturn2view0

---

# 6. Componentes de la integración

## 6.1 `config/qrystalos.php`

Este archivo centraliza la configuración de interoperabilidad.

Actualmente define:

```php
[
    'base_url' => ...,
    'auth_user' => ...,
    'auth_pass' => ...,
    'usuario_auditoria' => ...,

    'catalogs' => [
        'id_sede' => ...,
        'id_administradora' => ...,
        'id_plan' => ...,
        'ciudad' => ...,
        'id_barrio' => ...,
    ],

    'defaults' => [
        'estado_civil' => ...,
        'grupo_pob' => ...,
        'grupo_etnico' => ...,
        'tipo_discapacidad' => ...,
        'escolaridad' => ...,
        'zona' => ...,
        'nivel_socioec' => ...,
        'tipo_usuario' => ...,
        'estado' => ...,
        'procedencia' => ...
    ]
]
```

La configuración distingue entre **catálogos que deben venir de QRYSTALOS** y valores predeterminados utilizados para campos obligatorios que actualmente no están disponibles en el CSV de entrada. citeturn2view2

---

# 7. Cliente HTTP QRYSTALOS

El archivo:

```text
api/QrystalosClient.php
```

implementa la comunicación HTTP con QRYSTALOS.

La URL actualmente construida es:

```text
{QRYSTALOS_BASE_URL}/api/json/
```

La solicitud se realiza mediante:

```text
POST
Content-Type: application/json
Authorization: Basic <credenciales>
```

El cliente utiliza Basic Authentication y HTTPS/TLS con verificación del certificado (`CURLOPT_SSL_VERIFYPEER=true`). El tiempo máximo de espera configurado es de 30 segundos. citeturn2view0

---

# 8. Estructura del mensaje enviado

El cliente construye una envoltura similar a:

```json
{
  "MODELO": "SISPAM",
  "METODO": "INSERTAR",
  "USUARIO": "USUARIO_INTEGRACION",
  "PARAMETROS": {
    "...": "..."
  }
}
```

Para edición:

```json
{
  "MODELO": "SISPAM",
  "METODO": "EDITAR",
  "USUARIO": "USUARIO_INTEGRACION",
  "PARAMETROS": {
    "IDAFILIADO": "..."
  }
}
```

El modelo se encuentra actualmente fijado como `SISPAM` y el método cambia entre `INSERTAR` y `EDITAR`. citeturn2view0

---

# 9. Sincronización de pacientes

El archivo:

```text
services/QrystalosSyncService.php
```

funciona como capa de transformación entre los datos internos de SISPAM y el formato esperado por QRYSTALOS.

La función principal es:

```php
sincronizarPaciente(array $datosCsv, ?string $idAfiliadoExistente = null)
```

Esta función construye los parámetros necesarios para QRYSTALOS.

Entre los datos contemplados están:

```text
TIPO_DOC
DOCIDAFILIADO
FNACIMIENTO
PAPELLIDO
PNOMBRE
SEXO
ESTADO_CIVIL
GRUPOPOB
GRUPOETNICO
TIPODISCAPACIDAD
IDESCOLARIDAD
DIRECCION
CELULAR
EMAIL
CIUDAD
ZONA
IDBARRIO
IDADMINISTRADORA
IDPLAN
NIVELSOCIOEC
TIPOUSUARIO
IDSEDE
ESTADO
PROCEDENCIA
```

Si existe un `idAfiliadoExistente`, se agrega `IDAFILIADO` y la operación se envía como edición. citeturn2view1

---

# 10. ⚠️ Estado actual de la integración QRYSTALOS

Esta sección es **crítica para la puesta en marcha del sistema**.

La integración técnica ya tiene una base funcional, pero **depende de información externa que todavía debe ser suministrada, validada y aprobada por QRYSTALOS**.

Por esta razón, no se debe asumir que los valores actuales son definitivos.

## 10.1 Factores pendientes

Antes de habilitar la integración productiva deben quedar definidos, como mínimo:

### A. URL definitiva

Actualmente se utiliza:

```text
https://api-test.sispam.com
```

Debe confirmarse con QRYSTALOS:

- URL de pruebas.
- URL de producción.
- Ruta definitiva del servicio.
- Uso de HTTPS.
- Certificado válido.
- Restricciones de red, firewall o IP.

### B. Credenciales reales

Actualmente existen valores temporales:

```env
QRYSTALOS_AUTH_USER=usuario_temporal
QRYSTALOS_AUTH_PASS=clave_temporal
```

Deben reemplazarse por credenciales oficiales.

Además, se debe confirmar:

- Usuario de integración.
- Contraseña.
- Método de autenticación.
- Permisos asociados.
- Modelo autorizado.
- Ambiente al que pertenece cada credencial.
- Política de rotación de credenciales.

### C. Usuario de auditoría

Actualmente:

```env
QRYSTALOS_USUARIO_AUDITORIA=USUARIO_INTEGRACION_TEMP
```

Debe confirmarse el identificador oficial que QRYSTALOS espera recibir en:

```json
{
  "USUARIO": "..."
}
```

Este campo participa en la trazabilidad de la operación. citeturn2view0

---

# 11. Catálogos QRYSTALOS pendientes de confirmación

Los siguientes valores son especialmente importantes porque el servicio de sincronización los introduce directamente en el payload.

| Variable | Valor actual | Estado |
|---|---:|---|
| `QRYSTALOS_ID_SEDE` | `29` | ⚠️ Pendiente de validar |
| `QRYSTALOS_ID_ADMINISTRADORA` | `0100000010` | ⚠️ Pendiente de validar |
| `QRYSTALOS_ID_PLAN` | `TARC26` | ⚠️ Pendiente de validar |
| `QRYSTALOS_CIUDAD_DIVIPOLA` | `05001` | ⚠️ Pendiente de validar |
| `QRYSTALOS_ID_BARRIO` | `05001001` | ⚠️ Pendiente de validar |

El propio código identifica estos datos como catálogos que deberán actualizarse con los valores reales obtenidos durante el proceso de onboarding de QRYSTALOS. citeturn2view1turn2view2

---

# 12. Otros campos pendientes de definición funcional

Además de las credenciales y catálogos, debe validarse con QRYSTALOS el significado y los valores permitidos para campos como:

```text
ESTADO_CIVIL
GRUPOPOB
GRUPOETNICO
TIPODISCAPACIDAD
IDESCOLARIDAD
ZONA
NIVELSOCIOEC
TIPOUSUARIO
ESTADO
PROCEDENCIA
```

Actualmente algunos de estos campos utilizan valores predeterminados porque el CSV de entrada no contiene toda la información requerida. citeturn2view1turn2view2

**Esto debe considerarse una solución temporal de integración y no necesariamente una representación definitiva del dato clínico/administrativo.**

---

# 13. Datos temporales generados automáticamente

Existe una consideración importante con el correo electrónico.

Si el CSV no proporciona email, el servicio genera uno temporal con el patrón:

```text
paciente_<documento>@temp.sispam.local
```

Esto está implementado para cumplir con la obligatoriedad del campo `EMAIL` en el payload actual. citeturn2view1

Antes de producción debe confirmarse con QRYSTALOS:

1. Si el email realmente es obligatorio.
2. Si acepta correos temporales.
3. Si existe un valor oficial para "sin correo".
4. Si se debe solicitar el correo al paciente.
5. Si el correo debe ser único.
6. Si el formato actual cumple las validaciones del servicio.

---

# 14. Flujo previsto de integración

El flujo técnico actual puede representarse así:

```text
Paciente / CSV
      │
      ▼
SISPAM
      │
      ▼
QrystalosSyncService
      │
      │ Transformación y normalización
      ▼
Payload QRYSTALOS
      │
      ▼
QrystalosClient
      │
      │ HTTPS + Basic Auth
      ▼
QRYSTALOS
      │
      ▼
Respuesta JSON
      │
      ├── OK
      │    ├── CONSECUTIVO
      │    ├── ACCION
      │    └── MENSAJE
      │
      └── ERROR
           └── ERROR de negocio
```

El cliente interpreta tanto errores HTTP como errores de negocio devueltos dentro de la respuesta JSON. citeturn2view0

---

# 15. Manejo de respuestas QRYSTALOS

El cliente contempla, entre otros:

| HTTP / condición | Tratamiento |
|---|---|
| Error cURL | Error de red |
| `401` | Credenciales Basic Auth inválidas |
| `403` | Usuario sin permisos para el modelo |
| `5xx` | Error del servidor QRYSTALOS |
| JSON no reconocido | Error de estructura |
| `OK = OK` | Operación exitosa |
| `OK != OK` | Error de negocio |

Cuando la operación es exitosa se devuelve información como:

```text
success
consecutivo
accion
mensaje
```

Si existe un error de negocio, el cliente intenta obtenerlo desde el segundo recordset de la respuesta. citeturn2view0

---

# 16. Condiciones para declarar la integración como "lista"

La integración QRYSTALOS **NO debería pasar a producción** hasta completar este checklist:

## Conectividad

- [ ] URL de pruebas confirmada.
- [ ] URL de producción confirmada.
- [ ] HTTPS validado.
- [ ] Firewall/IP whitelist validado.
- [ ] Timeout acordado.
- [ ] Disponibilidad del servicio verificada.

## Autenticación

- [ ] Usuario oficial recibido.
- [ ] Contraseña oficial recibida.
- [ ] Basic Auth confirmado.
- [ ] Permisos del usuario confirmados.
- [ ] Modelo `SISPAM` autorizado.
- [ ] Usuario de auditoría confirmado.

## Catálogos

- [ ] ID de sede confirmado.
- [ ] ID de administradora confirmado.
- [ ] ID de plan confirmado.
- [ ] Ciudad DIVIPOLA confirmada.
- [ ] ID de barrio confirmado.

## Reglas de negocio

- [ ] Campos obligatorios confirmados.
- [ ] Valores permitidos de `TIPO_DOC` confirmados.
- [ ] Valores permitidos de `SEXO` confirmados.
- [ ] Estados civiles confirmados.
- [ ] Grupo poblacional confirmado.
- [ ] Grupo étnico confirmado.
- [ ] Discapacidad confirmada.
- [ ] Escolaridad confirmada.
- [ ] Zona confirmada.
- [ ] Nivel socioeconómico confirmado.
- [ ] Tipo de usuario confirmado.
- [ ] Estado del afiliado confirmado.
- [ ] Procedencia confirmada.
- [ ] Política de email confirmada.

## Pruebas

- [ ] INSERTAR paciente válido.
- [ ] INSERTAR paciente duplicado.
- [ ] EDITAR paciente.
- [ ] Paciente con datos incompletos.
- [ ] Credenciales inválidas.
- [ ] Usuario sin permisos.
- [ ] Catálogo inválido.
- [ ] Error 5xx.
- [ ] Timeout.
- [ ] Respuesta JSON inválida.
- [ ] Error de negocio.
- [ ] Confirmación del consecutivo retornado.

---

# 17. Recomendación para la puesta en marcha

La integración debe manejarse como un **proceso de onboarding de interoperabilidad**, no simplemente como el cambio de unas variables `.env`.

Se recomienda seguir esta secuencia:

```text
1. Recibir documentación oficial QRYSTALOS
             ↓
2. Confirmar ambiente TEST
             ↓
3. Recibir credenciales TEST
             ↓
4. Recibir catálogos oficiales
             ↓
5. Confirmar diccionario de datos
             ↓
6. Validar campos obligatorios
             ↓
7. Ejecutar pruebas INSERTAR
             ↓
8. Ejecutar pruebas EDITAR
             ↓
9. Validar respuestas y consecutivos
             ↓
10. Corregir reglas de transformación
             ↓
11. Recibir credenciales PRODUCCIÓN
             ↓
12. Configurar ambiente productivo
             ↓
13. Ejecutar prueba controlada
             ↓
14. Autorizar salida a producción
```

---

# 18. Seguridad

## No almacenar secretos en el repositorio

Nunca se deben versionar:

```env
QRYSTALOS_AUTH_USER=...
QRYSTALOS_AUTH_PASS=...
```

ni credenciales reales de base de datos.

Debe utilizarse `.env` o el mecanismo seguro de variables de entorno del servidor.

## Protección de información

La integración maneja información de identificación de pacientes, por lo que deben aplicarse controles apropiados de:

- acceso;
- auditoría;
- cifrado en tránsito;
- protección de credenciales;
- logs sin datos sensibles innecesarios;
- control de errores;
- separación de ambientes.

El cliente ya utiliza HTTPS/TLS y evita desactivar la verificación del certificado SSL. citeturn2view0

---

# 19. Instalación rápida

## XAMPP

1. Clonar el repositorio:

```bash
git clone https://github.com/comitedeestudiosmedicos/sispam.git
```

2. Colocar el proyecto en:

```text
C:\xampp\htdocs\sispam
```

3. Crear la base de datos:

```text
farmacia_db
```

4. Importar:

```text
database/schema.sql
```

5. Instalar dependencias:

```bash
composer install
```

6. Crear `.env` a partir de `.env.example`.

7. Configurar primero la base de datos.

8. Configurar QRYSTALOS solamente con los valores oficiales entregados para el ambiente correspondiente.

9. Abrir:

```text
http://localhost/sispam/
```

El README actual contempla XAMPP, `farmacia_db` y la importación de `database/schema.sql`. citeturn0view0

---

# 20. API interna de pacientes

SISPAM dispone además de endpoints internos para consultar pacientes.

Ejemplo:

```text
GET api/buscar_paciente.php?tipo_documento=CC&numero_documento=123456789
```

El endpoint valida tipo y número de documento y consulta el modelo `Paciente`. citeturn3view0

También existe:

```text
GET api/get_paciente.php?tipo_doc=CC&num_doc=123456789
```

que devuelve el paciente encontrado o un estado `not_found`. citeturn3view1

> Estos endpoints internos de SISPAM no deben confundirse con el endpoint externo de interoperabilidad QRYSTALOS.

---

# 21. Diferencia entre SISPAM y QRYSTALOS

Es importante separar las responsabilidades:

### SISPAM

Es el sistema local de gestión farmacéutica y operación del proceso de dispensación.

### QRYSTALOS

Es el sistema externo con el que SISPAM pretende interoperar para sincronizar información bajo el contrato técnico y las reglas que sean definidas por QRYSTALOS.

### Integración

La integración funciona como una frontera entre ambos sistemas:

```text
SISPAM
  │
  │ datos internos
  ▼
QrystalosSyncService
  │
  │ transformación
  ▼
QrystalosClient
  │
  │ HTTPS / JSON
  ▼
QRYSTALOS
```

Esto permite mantener separada la lógica del negocio local de la lógica específica de comunicación externa.

---

# 22. Riesgos actuales de la integración

Mientras no se complete el onboarding de QRYSTALOS, existen los siguientes riesgos:

1. **Catálogos incorrectos:** un ID de sede, administradora, plan o barrio incorrecto puede provocar rechazo o asociación incorrecta.
2. **Credenciales temporales:** no deben utilizarse para operación real.
3. **Valores por defecto:** algunos datos se completan automáticamente y deben ser revisados contra el contrato de interoperabilidad.
4. **Cambios de contrato:** QRYSTALOS puede modificar nombres, tipos o obligatoriedad de campos.
5. **Reglas de negocio:** un payload técnicamente válido puede ser rechazado por reglas funcionales.
6. **Ambiente equivocado:** nunca deben mezclarse credenciales de TEST y PRODUCCIÓN.
7. **Respuesta no esperada:** el cliente actualmente espera una estructura JSON específica.
8. **Datos de pacientes:** debe garantizarse la protección de la información durante todo el proceso.

---

# 23. Registro de pendientes QRYSTALOS

| Pendiente | Responsable | Prioridad | Estado |
|---|---|---:|---|
| URL TEST definitiva | QRYSTALOS | Alta | Pendiente |
| URL PRODUCCIÓN | QRYSTALOS | Alta | Pendiente |
| Usuario de integración | QRYSTALOS | Alta | Pendiente |
| Contraseña de integración | QRYSTALOS | Alta | Pendiente |
| Usuario de auditoría | QRYSTALOS | Alta | Pendiente |
| ID sede | QRYSTALOS | Alta | Pendiente |
| ID administradora | QRYSTALOS | Alta | Pendiente |
| ID plan | QRYSTALOS | Alta | Pendiente |
| Ciudad DIVIPOLA | QRYSTALOS | Media | Pendiente |
| ID barrio | QRYSTALOS | Media | Pendiente |
| Diccionario de campos | QRYSTALOS / SISPAM | Alta | Pendiente |
| Reglas de obligatoriedad | QRYSTALOS | Alta | Pendiente |
| Valores permitidos | QRYSTALOS | Alta | Pendiente |
| Casos de prueba | SISPAM / QRYSTALOS | Alta | Pendiente |
| Validación INSERTAR | SISPAM / QRYSTALOS | Alta | Pendiente |
| Validación EDITAR | SISPAM / QRYSTALOS | Alta | Pendiente |
| Autorización productiva | SISPAM / QRYSTALOS | Alta | Pendiente |

---

# 24. Conclusión

SISPAM ya dispone de una **estructura técnica específica para la integración con QRYSTALOS**, incluyendo configuración, cliente HTTP, transformación de pacientes y manejo básico de respuestas.

Sin embargo, la interoperabilidad debe considerarse **pendiente de cierre funcional y operativo** hasta que QRYSTALOS entregue y valide los factores externos necesarios.

En particular, **no basta con cambiar `QRYSTALOS_BASE_URL` y las credenciales**. Es necesario validar simultáneamente:

> **endpoint + autenticación + permisos + modelo + usuario de auditoría + catálogos + diccionario de datos + obligatoriedad de campos + reglas de negocio + casos de prueba + autorización de producción.**

Una vez completados estos puntos, el `QrystalosSyncService` y `QrystalosClient` constituyen la base para formalizar la integración.

---

## Referencias del repositorio

- Repositorio: https://github.com/comitedeestudiosmedicos/sispam
- Cliente QRYSTALOS: `api/QrystalosClient.php`
- Servicio de sincronización: `services/QrystalosSyncService.php`
- Configuración QRYSTALOS: `config/qrystalos.php`
- Configuración BD: `config/database.php`
- Ejemplo de variables de entorno: `.env.example`

