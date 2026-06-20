# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Idioma

Todo el código de cara al usuario (strings i18n), los comentarios y los docblocks están en **español**. Mantén ese idioma al escribir o modificar código y al responder al usuario. Text domain: `galerias-domi`.

## Qué es

Plugin de WordPress para crear galerías de imágenes con filtros y paginación. Sin tooling de build/lint/test (no hay `composer.json`, `package.json` ni `phpcs.xml`): WordPress carga el plugin directamente. El entorno de desarrollo es **Local** (la raíz del repo vive dentro de `wp-content/plugins/`). PHP mínimo 7.4, WordPress mínimo 6.0.

El código sigue WordPress Coding Standards (hay anotaciones `// phpcs:ignore ...` aunque la config de PHPCS no está versionada).

## Autoloader y convención de nombres (crítico)

`galerias-domi.php` registra un autoloader PSR-4: prefijo `GaleriasDomi\` → `includes/`. La clase determina la ruta del archivo así:

- `GaleriasDomi\Admin\Admin_Menu` → `includes/Admin/class-admin-menu.php`
- Los `_` del nombre de clase se vuelven `-`, todo en minúsculas, con prefijo `class-`.

Al crear una clase nueva hay que respetar esta convención exacta o no se cargará.

## Modelo de datos

Una galería es un Custom Post Type **privado** `galerias-domi` (`Gallery_Post_Type::POST_TYPE`), usado solo como contenedor de datos (`public=false`, `show_ui=false`, soporta `title` + `author`). Nunca se edita por la UI nativa de WordPress; toda la edición pasa por las páginas propias del plugin.

Toda la configuración vive en post meta con prefijo `_gd_`:

- `_gd_columns`, `_gd_width` (porcentaje: 100/95/80/65/50), `_gd_hover_effect`, `_gd_pagination_enabled`, `_gd_pagination_rows`
- `_gd_filters_enabled`, `_gd_filter_style` (tipo: buttons/select), `_gd_filter_variant_buttons` (solid/outline/minimal), `_gd_filter_variant_select` (solid/minimal), `_gd_filter_shape` (rounded/square; solo botones, no aplica a minimal), `_gd_show_todos`, `_gd_todos_position`, `_gd_filters` (array `{name,id}`)
- Cada tipo recuerda su propia variante de estilo de forma independiente. `_gd_filter_variant` (unificado) es el meta legacy: solo se lee como fallback en `get_saved_variant()`. El snapshot guarda la variante **efectiva** del tipo activo en `filter_variant`.
- `_gd_images` (array `{id,filter}` — `id` es un attachment ID)
- Versionado/estado: `_gd_published`, `_gd_save_version`, `_gd_render_version`, `_gd_saved_at`, `_gd_rendered_at`, `_gd_rendered_data` (snapshot)

## Flujo de 3 estados versionados (el concepto central)

Editar una galería NO cambia el frontend inmediatamente. Hay tres acciones independientes, cada una vía `admin-post.php` con su propia action + nonce (definidas en `Admin_Edit_Gallery`):

1. **Guardar** (`handle_save`): escribe los metas `_gd_*` e incrementa `_gd_save_version`.
2. **Publicar** (`handle_publish`): pone `_gd_published = 1` (habilita el shortcode).
3. **Renderizar** (`handle_render`): congela los metas actuales en un snapshot (`build_config_snapshot()` → `_gd_rendered_data`) e iguala `_gd_render_version` a `_gd_save_version`.

El botón "Renderizar" solo se habilita si la galería está publicada **y** `save_version > render_version` (hay cambios sin renderizar). **Implicación clave:** cualquier cambio en la configuración solo se refleja en el sitio público tras pulsar *Renderizar*. Al renderizar se invalida el transient de la versión anterior.

## Frontend (shortcode)

`[galeria_domi id="X"]` (`Gallery_Shortcode`) renderiza **solo desde el snapshot** `_gd_rendered_data`, y únicamente si la galería está publicada y tiene imágenes. El HTML se cachea en el transient `gd_gallery_html_{id}_{render_version}` durante una semana; como la clave incluye `render_version`, la caché se invalida sola en el siguiente render. Los assets (`frontend.css`/`frontend.js`) se encolan solo cuando hay una galería en la página.

Filtros y paginación son **client-side** (`assets/js/frontend.js`): muestra/oculta `.gd-gallery__item` por `data-filter` y pagina con `data-per-page`. El `per_page` se calcula como `pagination_rows * columns` y queda fijado en el snapshot.

Cada imagen se envuelve en un `<button class="gd-gallery__open">` con `data-full` (URL a tamaño completo) y `data-caption`. Al hacer clic se abre un **lightbox** accesible (`Lightbox`, singleton creado de forma diferida en `frontend.js`): navegación prev/next sobre el set filtrado actual, teclado (Esc / flechas / Tab atrapado), bloqueo de scroll del body (`.gd-no-scroll`), restauración de foco y precarga de vecinos. Los textos del lightbox van hardcodeados en español (no hay i18n de JS). El lightbox siempre está activo; no tiene meta propio, así que galerías ya renderizadas necesitan **Renderizar** de nuevo para regenerar el HTML con el botón disparador.

## Admin (routing y assets)

- Un único menú top-level `galerias-domi` (`Admin_Menu`). `Admin_Page::render()` despacha según `?action`: `edit` → `Admin_Edit_Gallery`; por defecto → `Galleries_List_Table`.
- Submenú oculto `galerias-domi-new` (`Admin_New_Gallery`) para crear galerías.
- La pantalla de edición es de dos columnas: opciones en tabs (`get_tabs()` → general / filtros) y panel de imágenes (biblioteca de medios de WP, `wp_enqueue_media`).
- Los assets se encolan condicionalmente en `Admin_Menu::enqueue_assets()`, filtrando por hook de página y por `?action=edit`.

## Gotchas conocidos

- Los handlers de `Admin_Edit_Gallery` y `Admin_Page::render()` validan que el post exista pero **no** que su `post_type` sea `galerias-domi`; con un `id` arbitrario podrían leerse/escribirse metas sobre otro post (solo `manage_options`). Validar el `post_type` es una mejora pendiente.
- El registro del CPT y del shortcode se engancha a `init` desde dentro del propio callback de `init` (en `galerias_domi()->init()`), patrón frágil aunque funcional.
