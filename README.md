VideoConverter
==============

Interfaz batch de conversión de videos en PHP basado en ffmpeg [http://www.ffmpeg.org].

Ejemplos Invocaciones
  
  -i /Users/gustavo/Downloads/1Test/filepaths.txt -o /Users/gustavo/conver --parseFiles
  -i /Users/gustavo/Downloads/1Test/1/Funcionales/OficiosCursodeterminacionesT01C07_SD_PA-PP-10060pa-pp-10601.mp4 -o /Users/gustavo/convert
  -i /Users/gustavo/Downloads/1Test/1 -o /Users/gustavo/Downloads/1Test/to --regex /.*(mp4|flv|avi)$/
  -i /Users/gustavo/Downloads/1Test/1/Funcionales/OficiosCursodeterminacionesT01C07_SD_PA-PP-10060pa-pp-10601.mp4 -o /Users/gustavo/conver --regex /.*(avi|flv)$/
  -i /Users/gustavo/original -o /Users/gustavo/original_convertidos

 * Casos:

- Parseo desde directorio (con regex default)

php VideoConverter.php -i /ubicacion/videos/origen/ -o /ubicacion/videos/destino/

- Parseo desde directorio CON regex especificado.

php VideoConverter.php -i /media/usbdrive/ -o /ubicacion/videos/destino/  --regex /.*(mp4|flv|avi)$/

- Parseo desde UN UNICO ARCHIVO DE VIDEO (omite --regex o configuracion default)

php VideoConverter.php -i /media/usbdrive/archivo.avi -o /ubicacion/videos/destino/

- Parseo desde lista de archivos (omite regex default)

php VideoConverter.php -i /media/usbdrive/archivos_a_procesar.txt -o /ubicacion/videos/destino/l  --inputFileList

- Parseo desde lista de archivos APLICA regex por parametro

php VideoConverter.php -i /media/usbdrive/archivos_a_procesar.txt -o /ubicacion/videos/destino/  --inputFileList --regex /.*(mp4|flv|avi)$/

