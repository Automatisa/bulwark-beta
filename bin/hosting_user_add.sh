#!/bin/sh
# hosting_user_add.sh — Crea usuario de sistema h_USERNAME para una cuenta de hosting.
#
# Lee el nombre de cuenta desde /var/bulwark/run/hosting_useradd_req (root:bulwark 660).
# Validación estricta: solo letras minúsculas, dígitos y guión bajo, 1-32 caracteres.
# Es idempotente: si el usuario ya existe solo corrige la propiedad del directorio.
# Llamado via privilege::run('hosting_user_add') desde contexto bulwark (doas).
#
# IMPORTANTE: crea también el ESQUELETO del home (home + web/ + backups/). El panel corre
# como 'bulwark' y hostdata es www:www 0755 -> bulwark NO puede escribir ahí, así que la
# creación del home es responsabilidad de este wrapper root (si no, la cuenta queda sin home
# y vhost_dir_add falla con exit 6). Mismo esqueleto que ExecuteCreateClient.

REQ_FILE="/var/bulwark/run/hosting_useradd_req"
HOSTED_DIR="/var/bulwark/hostdata"

[ -f "$REQ_FILE" ] || exit 1

USERNAME=$(cat "$REQ_FILE" | tr -d '\n\r ')
rm -f "$REQ_FILE"

# Validación estricta del nombre de usuario del panel
echo "$USERNAME" | grep -qE '^[a-z][a-z0-9_]{0,31}$' || exit 2

SYSUSER="h_${USERNAME}"
HOSTDIR="${HOSTED_DIR}/${USERNAME}"

# Crea (si falta) el esqueleto del home y reajusta ownership/permisos de aislamiento.
# home 2770, backups 2770, web 2750 (setgid; www lo atraviesa por grupo pero NO escribe:
# los dominios se crean por doas en vhost_dir_add). h_USERNAME:www en todo el árbol.
ensure_hostdir() {
    mkdir -p "$HOSTDIR" "${HOSTDIR}/web" "${HOSTDIR}/backups"
    chown "${SYSUSER}:www" "$HOSTDIR" "${HOSTDIR}/backups" "${HOSTDIR}/web"
    chmod 2770 "$HOSTDIR" "${HOSTDIR}/backups"
    chmod 2750 "${HOSTDIR}/web"
    # El directorio de correo es de vmail, no tocarlo
    [ -d "${HOSTDIR}/mail" ] && chown -R vmail:vmail "${HOSTDIR}/mail"
    return 0
}

# Idempotente: si el usuario ya existe, solo corrige grupo/ownership/permisos y sale
if pw usershow "$SYSUSER" >/dev/null 2>&1; then
    # Aislamiento entre inquilinos: el usuario NO debe estar en el grupo www (si no,
    # podría leer los ficheros group-www de otros clientes). Apache (www) sirve los
    # estáticos porque es el GRUPO de los ficheros, no porque el cliente esté en www.
    pw groupmod www -d "$SYSUSER" 2>/dev/null || true
    ensure_hostdir
    exit 0
fi

# Crear grupo propio del usuario
pw groupadd -n "$SYSUSER" 2>/dev/null || true

# Crear usuario: sin shell de login, sin home real, SOLO en su propio grupo (NO en www:
# el aislamiento depende de que el cliente no comparta grupo con los demás).
pw useradd -n "$SYSUSER" \
           -g "$SYSUSER" \
           -s /usr/sbin/nologin \
           -d /nonexistent \
           -c "Bulwark hosting ${USERNAME}" \
           2>/dev/null || exit 3

ensure_hostdir

exit 0
