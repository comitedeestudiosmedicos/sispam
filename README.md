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
