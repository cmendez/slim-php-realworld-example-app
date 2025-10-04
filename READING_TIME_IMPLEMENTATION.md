# Implementación de Reading Time en Articles

## ✅ Cambios Realizados

### 1. **Migración de Base de Datos**
- ✅ Se añadió la columna `reading_time` (tipo INTEGER) a la tabla `articles`
- ✅ Campo con valor por defecto `1` y comentario descriptivo
- ✅ Migración ya ejecutada (20251003000000)

### 2. **Modelo Article.php**
**Archivo**: `src/Conduit/Models/Article.php`

Cambios realizados:
- ✅ Añadido `reading_time` al arreglo `$fillable`
- ✅ Añadido casting para `reading_time` como `integer`
- ✅ Actualizada la documentación PHPDoc con la propiedad `@property integer reading_time`

### 3. **Controlador ArticleController.php**
**Archivo**: `src/Conduit/Controllers/Article/ArticleController.php`

#### Método `store()` (Crear artículo):
```php
// Calcula el tiempo de lectura basado en el contenido
$wordCount = str_word_count(strip_tags($data['body']));
$readingTime = (int) ceil($wordCount / 200);

$article = new Article([
    'title' => $data['title'],
    'description' => $data['description'],
    'body' => $data['body'],
    'reading_time' => $readingTime,
]);
```

#### Método `update()` (Actualizar artículo):
```php
if (isset($params['body'])) {
    $article->body = $params['body'];
    // Recalcula el tiempo de lectura cuando cambia el body
    $wordCount = str_word_count(strip_tags($params['body']));
    $article->reading_time = (int) ceil($wordCount / 200);
}
```

### 4. **Transformer ArticleTransformer.php**
**Archivo**: `src/Conduit/Transformers/ArticleTransformer.php`

- ✅ Añadido `'readingTime' => $article->reading_time` al método `transform()`
- ✅ El campo se incluirá automáticamente en todas las respuestas de artículos

### 5. **Script de Actualización**
**Archivo**: `update_reading_time.php`

- ✅ Creado script para actualizar artículos existentes
- ✅ Ejecutado exitosamente: 1 artículo actualizado

## 📝 Fórmula de Cálculo

**Tiempo de lectura** = ceil(número_de_palabras / 200)

- Se usa `str_word_count()` para contar palabras
- Se usa `strip_tags()` para remover HTML del contenido
- Se divide entre 200 palabras por minuto (promedio de lectura)
- Se redondea hacia arriba con `ceil()`

## 🔄 Flujo de Trabajo

### Crear Artículo (POST /api/articles):
1. Usuario envía título, descripción y body
2. Backend calcula automáticamente `reading_time` del body
3. Se guarda el artículo con el tiempo de lectura
4. Respuesta incluye el campo `readingTime`

### Actualizar Artículo (PUT /api/articles/{slug}):
1. Usuario puede actualizar cualquier campo
2. Si se actualiza el `body`, se recalcula automáticamente el `reading_time`
3. Si no se actualiza el `body`, se mantiene el tiempo anterior
4. Respuesta incluye el campo `readingTime` actualizado

### Listar Artículos (GET /api/articles):
1. Todos los artículos incluyen el campo `readingTime`
2. No se requieren cambios en el frontend para mostrar este dato

## 🧪 Verificación

Para verificar que funciona correctamente:

### 1. Ver artículo existente:
```bash
curl -X GET http://localhost:8080/api/articles/{slug}
```

Debería incluir: `"readingTime": 1`

### 2. Crear nuevo artículo:
```bash
curl -X POST http://localhost:8080/api/articles \
  -H "Authorization: Token YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "article": {
      "title": "Test Reading Time",
      "description": "Testing the reading time feature",
      "body": "Este es un texto de prueba con varias palabras para calcular el tiempo de lectura. Lorem ipsum dolor sit amet..."
    }
  }'
```

El artículo creado incluirá automáticamente el `readingTime` calculado.

### 3. Actualizar el body:
```bash
curl -X PUT http://localhost:8080/api/articles/{slug} \
  -H "Authorization: Token YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "article": {
      "body": "Nuevo contenido más largo que recalculará el tiempo de lectura..."
    }
  }'
```

El `readingTime` se actualizará automáticamente.

## ✨ Características Implementadas

- ✅ Cálculo automático en creación
- ✅ Recálculo automático en actualización del body
- ✅ Campo incluido en todas las respuestas API
- ✅ Migración de base de datos ejecutada
- ✅ Artículos existentes actualizados
- ✅ Sin cambios necesarios en rutas
- ✅ Backend completamente funcional
- ✅ Compatible con Docker

## 📌 Notas Técnicas

- El campo `reading_time` es de tipo INTEGER (no nullable)
- Valor por defecto: 1 minuto
- Se calcula basado en 200 palabras/minuto
- Se usa `strip_tags()` para ignorar HTML
- Siempre se redondea hacia arriba con `ceil()`
