PHP PORTABLE Y DEPENDENCIAS EN WINDOWS — INSTRUCCIONES
======================================================

Esta carpeta (php\) es el directorio previsto para alojar una copia de PHP 8+ portable
para que la aplicación funcione de manera completamente autónoma sin requerir una
instalación previa de XAMPP, Laragon ni configuración del PATH del sistema.


1. DESCARGA DE PHP PORTABLE
---------------------------
1. Visita la página oficial de descargas para Windows:
   https://windows.php.net/download/

2. Descarga la compilación "Non Thread Safe (NTS)" de PHP 8.2 o superior para x64:
   Ejemplo de nombre: php-8.2.x-nts-Win32-vs16-x64.zip

3. Extrae la totalidad del contenido del archivo ZIP directamente dentro de esta carpeta php\.

Estructura de archivos esperada en php\:
  php\
    php.exe                ← Binario principal
    php8.dll               ← Biblioteca del motor PHP (o php8ts.dll según compilación)
    ext\                   ← Extensiones necesarias activadas en php.ini:
        php_curl.dll
        php_mbstring.dll
        php_openssl.dll
        php_pdo_sqlite.dll
        php_sqlite3.dll
        php_intl.dll
        php_gd.dll
        php_zip.dll
        php_fileinfo.dll
        php_pdo_mysql.dll  (opcional para migraciones MySQL)
        php_mysqli.dll     (opcional)
    libcurl.dll            ← DLLs auxiliares incluidas en el paquete de PHP.net
    icudt*.dll / icuuc*.dll
    (demás archivos y DLLs suministrados en el ZIP oficial)


2. REQUISITO CRÍTICO EN EQUIPOS LIMPIOS: RUNTIME DE VISUAL C++
--------------------------------------------------------------
Las compilaciones oficiales de PHP para Windows (VS16 / VS17) dependen de las librerías
de runtime de Microsoft Visual C++ 2015-2022 (x64), concretamente de:
  - VCRUNTIME140.dll
  - MSVCP140.dll

En equipos limpios de Windows (instalaciones nuevas o máquinas de oficina donde no se
hayan instalado juegos ni herramientas de desarrollo), PHP NO PODRÁ INICIAR y Windows
mostrará un error informando que falta VCRUNTIME140.dll si este runtime no está presente.

Enlace oficial de descarga de Microsoft:
  https://aka.ms/vs/17/release/vc_redist.x64.exe


3. CÓMO DEBE INCLUIRLO EL INSTALADOR O EMPAQUETADOR
---------------------------------------------------
Si vas a generar un instalador ejecutable (por ejemplo con Inno Setup, NSIS o WiX):

A) Inno Setup:
   Incluye vc_redist.x64.exe en la sección [Files] y ejecútalo silenciosamente en [Run]:
   ```ini
   [Files]
   Source: "vc_redist.x64.exe"; DestDir: "{tmp}"; Flags: deleteafterinstall

   [Run]
   Filename: "{tmp}\vc_redist.x64.exe"; Parameters: "/install /quiet /norestart"; StatusMsg: "Instalando componentes de Microsoft Visual C++..."
   ```

B) NSIS o Script Batch:
   ```cmd
   vc_redist.x64.exe /install /quiet /norestart
   ```

C) Detección automática en el lanzador (iniciar_servidor.ps1):
   Si colocas el archivo "vc_redist.x64.exe" dentro de esta carpeta php\ o en la raíz
   del proyecto, iniciar_servidor.ps1 detectará si el runtime falta en Windows y lo
   instalará automáticamente de manera silenciosa en el primer arranque.


4. NOTAS ADICIONALES
--------------------
- El sistema utiliza su propio archivo php.ini ubicado en la raíz del proyecto, el cual
  es cargado explícitamente mediante el argumento -c al invocar PHP. No es necesario
  crear ni editar ningún php.ini dentro de esta subcarpeta php\.
- El directorio de datos SQLite y respaldos se guarda de forma persistente fuera de
  esta carpeta, en %APPDATA%\SistemaEmpleados, protegiendo los datos del usuario aunque
  se reinstale o actualice la carpeta de la aplicación.
