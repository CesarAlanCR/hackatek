<?php
// Configuración de OpenWeatherMap para uso interno
// AVISO: Este archivo contiene la API key y debe mantenerse fuera del control de versiones (añadir a .gitignore)
// Uso recomendado: mover esta clave a variables de entorno en producción y no incluirla en el repositorio.

$OWM_API_KEY = 'e3f0790da98e5d2fa495d11bb819e9f1';

// Fallback a variable de entorno si existe (prioriza variable de entorno si está definida)
if (getenv('OWM_API_KEY')) {
    $OWM_API_KEY = getenv('OWM_API_KEY');
}

return $OWM_API_KEY;
