require "fileinto";

# Cabecera X-Spam: rspamd (modo milter) solo la anade cuando el mensaje alcanza
# la accion "add header" o superior — spam, o VIRUS con la accion "add header".
if header :contains "X-Spam" "Yes" {
        fileinto "Spam";
        stop;
}

# X-Spam-Flag: formato SpamAssassin (cualquier valor distinto de NO es spam).
if exists "X-Spam-Flag" {
        if header :contains "X-Spam-Flag" "NO" {
        } else {
        fileinto "Spam";
        stop;
        }
}

# Asunto reescrito por rspamd (accion "rewrite subject"): spam o virus entregado
# marcado (***VIRUS*** es el aviso de virus del modo "add header" de clamav_admin).
if header :contains "subject" ["***SPAM***", "***VIRUS***"] {
  fileinto "Spam";
  stop;
}
