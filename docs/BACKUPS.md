# Backups y restauración

## Cómo funcionan

- **Automáticos**: diario a las 02:00 y semanal los domingos 03:00 (requiere la tarea programada de
  Windows, ver [INSTALACION.md](INSTALACION.md)). Se activan o desactivan en Configuración → Backups.
- **Manual**: Administración → Backups → «Generar backup ahora», o en el servidor `php artisan galpon:backup`.
- Se usa `mysqldump --single-transaction` (copia consistente **sin frenar** la operación del galpón),
  comprimido en `.sql.gz`, guardado en `storage/app/private/backups`.
- La contraseña de la base **nunca** se pasa por línea de comandos ni queda en logs: se usa un archivo
  temporal de opciones que se borra al terminar.
- Cada backup guarda su **checksum SHA-256** y se **verifica** al terminar (se descomprime completo y se
  controla el encabezado y la marca de fin del volcado). «Verificar» repite el control cuando quieras:
  si el archivo fue alterado o está dañado, lo informa.
- **Retención**: los archivos más viejos que los días configurados (30 por defecto) se borran solos;
  siempre se conserva el último backup correcto.
- Si un backup falla, el administrador recibe un aviso y la pantalla de Backups lo muestra en rojo.

## Copia fuera del servidor (muy recomendado)

Un backup guardado sólo en el servidor no protege de un disco roto, un robo o un virus. Opciones:

- **Descargar** periódicamente el último backup (botón «Descargar») a un pendrive o disco externo.
- Programar una copia automática de `storage\app\private\backups` a un disco externo o carpeta de red,
  por ejemplo con una tarea de Windows que ejecute
  `robocopy C:\xampp\htdocs\galpon\storage\app\private\backups E:\backups-galpon /MIR`.
- Guardar una copia **fuera del galpón** (nube o en otra ubicación) al menos una vez por semana.

## Restaurar

Sólo el **Super Administrador** (permiso «Restaurar backups»).

1. Administración → Backups → en la fila del backup, **Restaurar**.
2. Escribir el **motivo**, tu **contraseña** y la palabra **RESTAURAR**.
3. El sistema:
   1. verifica el archivo (si no pasa la verificación, no hace nada);
   2. genera **automáticamente un backup del estado actual** («Previo a restauración»), para poder
      volver atrás si hiciera falta;
   3. pone el sistema en mantenimiento (los demás usuarios ven «volvemos en unos minutos»);
   4. restaura la base y vuelve a habilitar el sistema;
   5. registra la restauración en auditoría con el motivo.
4. Es posible que tengas que volver a ingresar (las sesiones son las del momento del backup).

**Todo lo cargado después del backup elegido se pierde** (queda, eso sí, en el backup previo automático).

## Restaurar a mano (si el sistema no arranca)

```powershell
cd C:\xampp\mysql\bin
# descomprimir el .sql.gz (por ejemplo con 7-Zip) y luego:
mysql -u galpon -p galpon < C:\ruta\galpon-daily-AAAAMMDD-HHMMSS-xxxx.sql
```

## Recuperar el acceso

Si nadie recuerda la contraseña de un administrador: en el servidor, `php artisan galpon:create-admin`.
