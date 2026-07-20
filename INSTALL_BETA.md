# Bulwark — Beta para prueba en VPS (instalación limpia)

Esta carpeta contiene **solo** lo necesario para una instalación limpia y funcional del
panel. **No** incluye nada de migración, build de PHP, suite de pruebas ni documentación
interna de desarrollo.

## Requisitos del VPS

- **FreeBSD 15.x** (RELEASE), acceso root, IP pública y, para el correo/DNS, un dominio.
- Recomendado: 2 vCPU / 2–4 GB RAM / 20 GB disco. Instalación desde cero (sin panel previo).

## Cómo obtiene el código el instalador

`install_bulwark.sh` **clona `GIT_REPO`** en `/usr/local/bulwark`. Por eso el flujo es:

### Opción A — publicar esta beta en un repositorio (recomendada)

1. En tu máquina, sube esta carpeta a un repo (privado o público):
   ```sh
   cd beta
   git init && git add . && git commit -m "Bulwark beta"
   git remote add origin https://github.com/TU_USUARIO/bulwark-beta.git
   git push -u origin main
   ```
2. En el VPS (root), lanza el instalador apuntando a tu repo:
   ```sh
   fetch -o install_bulwark.sh https://raw.githubusercontent.com/TU_USUARIO/bulwark-beta/main/install_bulwark.sh
   GIT_REPO=https://github.com/TU_USUARIO/bulwark-beta.git sh install_bulwark.sh
   ```

### Opción B — copiar la carpeta al VPS

1. `scp -r beta root@VPS:/root/bulwark-beta`
2. Publica esa copia como repo local y apunta el instalador ahí, **o** simplemente usa la
   Opción A. (El instalador espera un `git clone`, así que necesita un repo alcanzable.)

## Durante la instalación

El instalador es interactivo (FQDN, IP, email postmaster, zona horaria, dominio del
proveedor, nameservers, forwarders DNS, rol de nodo P/secundario, modo TLS del cluster
off/pin/ca, y contraseña de `zadmin`). Para desatender, puedes alimentarlo con un
`answers.txt` por stdin (una respuesta por línea en ese orden).

Al terminar: entra en `https://<tu-FQDN>/` con usuario **zadmin** y la contraseña que fijaste.
El resto de credenciales generadas quedan en `/var/bulwark/install-passwords.txt` del VPS.

## Qué NO está aquí (a propósito)

- `compilador_php/` (toolkit de build de PHP con poudriere) — no hace falta: el instalador usa PHP de `pkg`.
- `test_system/` (suite de pruebas automatizadas).
- Documentación interna: `SOLUCIONES.md`, `GUIA_DESARROLLADOR.md`, `informe_*.md`, etc.
- Historial `.git` y artefactos locales.

## Seguridad

`cnf/db.php` va como **plantilla** (`YOUR_ROOT_MYSQL_PASSWORD`); el instalador la rellena con
la contraseña generada. Esta carpeta se ha verificado sin credenciales reales. Aun así, si la
publicas en un repo **público**, revísalo antes de subir.
