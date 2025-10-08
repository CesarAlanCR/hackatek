<?php
// vistas/ia/chat.php
// Scaffold básico para el módulo de Chat IA (Gemini)
// - Interfaz: simple área de mensajes + formulario
// - Endpoint: cuando POST 'message' se prepara la petición a Gemini (placeholder)

// Nota: REMPLAZA la función `call_gemini_api` con la llamada real. Se requieren
// una API KEY y parámetros de modelo. No incluyas la API key en el repo.

// Manejo de POST: devuelve JSON con la respuesta simulada o real
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $input = $_POST['message'] ?? '';
    if (trim($input) === '') {
        echo json_encode(['error' => 'Mensaje vacío']);
        exit;
    }

    // Llamada a Gemini (placeholder)
    try {
        $response = call_gemini_api($input);
        echo json_encode(['ok' => true, 'reply' => $response]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

function call_gemini_api(string $prompt): string {
    // Aquí debes implementar la llamada a la API de Gemini.
    // Ejemplo (pseudocódigo):
    // $key = getenv('GEMINI_API_KEY');
    // $model = 'gemini-1.0';
    // usar cURL o Guzzle para POST a la endpoint de Google/VertexAI o la API adecuada.
    // Por motivos de seguridad, no incluimos la clave en el código.

    // Por ahora retornamos una respuesta simulada para UI local:
    return "Simulación de Gemini: recibí tu mensaje -> " . substr($prompt, 0, 200);
}

?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Chat IA - AgroScout</title>
    <link rel="stylesheet" href="../../recursos/css/general.css">
    <style>
        .chat-container{max-width:900px;margin:24px auto}
        .messages{background:#fff;padding:12px;border-radius:8px;min-height:240px;border:1px solid rgba(0,0,0,0.04);overflow:auto}
        .msg{margin:8px 0}
        .msg.user{text-align:right}
        .msg .bubble{display:inline-block;padding:8px 12px;border-radius:12px}
        .msg.user .bubble{background:var(--green-4);color:#fff}
        .msg.bot .bubble{background:#f1f6f1;color:#122212}
        .chat-form{display:flex;gap:8px;margin-top:12px}
        .chat-form input[type=text]{flex:1;padding:10px;border-radius:8px;border:1px solid rgba(0,0,0,0.08)}
    </style>
</head>
<body>
    <main class="container chat-container">
        <h2>Chat IA</h2>
        <p class="lead">Habla con AgroScout para recibir recomendaciones y ayuda.</p>

        <div id="messages" class="messages" aria-live="polite"></div>

        <form id="chatForm" class="chat-form" method="post" action="chat.php">
            <input id="messageInput" type="text" name="message" placeholder="Escribe tu mensaje..." autocomplete="off">
            <button class="btn btn-primary" type="submit">Enviar</button>
        </form>

        <p class="muted">Nota: la integración con Gemini requiere una API key y configuración del modelo. Por ahora el servidor devuelve una respuesta simulada.</p>
    </main>

    <script>
    // Manejo AJAX simple para enviar mensajes sin recargar
    (function(){
        const form = document.getElementById('chatForm');
        const input = document.getElementById('messageInput');
        const messages = document.getElementById('messages');

        function appendMessage(text, who){
            const div = document.createElement('div');
            div.className = 'msg ' + (who === 'user' ? 'user' : 'bot');
            const span = document.createElement('span');
            span.className = 'bubble';
            span.textContent = text;
            div.appendChild(span);
            messages.appendChild(div);
            messages.scrollTop = messages.scrollHeight;
        }

        form.addEventListener('submit', function(e){
            e.preventDefault();
            const val = input.value.trim();
            if(!val) return;
            appendMessage(val, 'user');
            input.value = '';

            const fd = new FormData();
            fd.append('message', val);

            fetch('chat.php', {method:'POST', body:fd})
            .then(r => r.json())
            .then(json => {
                if(json.ok){
                    appendMessage(json.reply, 'bot');
                } else if(json.error){
                    appendMessage('Error: ' + json.error, 'bot');
                }
            })
            .catch(err => appendMessage('Error de red: ' + err.message, 'bot'));
        });

    })();
    </script>
</body>
</html>