=== Galerias DOMI ===
Contributors: kenpoc4
Tags: galeria, imagenes, filtros, paginacion, lightbox
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Crea galerías de imágenes profesionales con filtros, paginación y lightbox mediante un shortcode.

== Description ==

Galerias DOMI permite crear galerías de imágenes elegantes y personalizables desde el panel de administración, sin escribir código. Cada galería se inserta en cualquier página o entrada mediante un shortcode.

**Características principales**

* Cuadrícula configurable (número de columnas y ancho de la galería: 100, 95, 80, 65 o 50 %, siempre centrada).
* Efectos al pasar el cursor: sombra, borde, zoom-in, zoom-out y desenfoque con texto.
* Filtros por categoría con dos tipos (botones o selector), cada uno con sus propios estilos (sólido, contorno, minimal) y forma (redondo o cuadrado) para los botones.
* Paginación opcional del lado del cliente.
* Lightbox accesible al hacer clic en una imagen: navegación con teclado, bloqueo de scroll y restauración del foco.
* Flujo de publicación versionado: los cambios solo se reflejan en el sitio público al pulsar «Renderizar», y el HTML se sirve cacheado para mayor rendimiento.

El plugin no carga recursos externos (sin fuentes ni scripts de terceros) y limpia todos sus datos al desinstalarse.

== Installation ==

1. Sube la carpeta `galerias-domi` al directorio `/wp-content/plugins/`, o instala el plugin desde el buscador de plugins de WordPress.
2. Activa el plugin desde el menú «Plugins» de WordPress.
3. Ve al menú «Galerias DOMI», crea una galería, agrega imágenes y configúrala.
4. Pulsa «Publicar» y luego «Renderizar».
5. Copia el shortcode (por ejemplo `[galeria_domi id="123"]`) y pégalo en la página o entrada donde quieras mostrar la galería.

== Frequently Asked Questions ==

= ¿Por qué mis cambios no se ven en el sitio? =

Los cambios de configuración solo se aplican al frontend después de pulsar «Renderizar». Es intencional: te permite editar con calma sin afectar el sitio público hasta que estés listo.

= ¿Cómo inserto una galería? =

Usa el shortcode `[galeria_domi id="X"]`, donde `X` es el ID de la galería (lo ves en el listado de galerías una vez publicada).

= ¿El plugin carga fuentes o scripts externos? =

No. Toda la tipografía usa fuentes del sistema y los recursos se sirven desde el propio plugin.

= ¿Qué ocurre al desinstalar el plugin? =

Se eliminan las galerías creadas, sus ajustes y la caché que genera el plugin.

== Screenshots ==

1. Pantalla de edición de una galería con opciones y panel de imágenes.
2. Configuración de filtros (tipo, estilo y forma).
3. Galería en el frontend con filtros y paginación.
4. Lightbox al hacer clic en una imagen.

== Changelog ==

= 1.0.0 =
* Versión inicial.
* Galerías con columnas, ancho configurable y efectos hover.
* Filtros con tipos, estilos y forma; paginación del lado del cliente.
* Lightbox accesible.
* Flujo de publicación versionado con caché del frontend.

== Upgrade Notice ==

= 1.0.0 =
Versión inicial de Galerias DOMI.
