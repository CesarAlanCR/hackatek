
<?php
// Chat IA especializado en agricultura
// Archivo: vistas/ia/chat.php

// Configuración: leer API Key OpenAI desde variable de entorno
$OPENAI_API_KEY = getenv('OPENAI_API_KEY') ?: getenv('OPENAI_APIKEY') ?: null;

// Nota: para desarrollo local puedes crear un archivo .env y usar un cargador de variables
// (por ejemplo vlucas/phpdotenv) o configurar la variable en tu entorno del servidor.

// Endpoint para procesar mensajes (POST)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$message = $_POST['message'] ?? '';
	$response = '';
	$imageData = null;
	if (isset($_FILES['image']) && $_FILES['image']['tmp_name']) {
		$imageData = base64_encode(file_get_contents($_FILES['image']['tmp_name']));
	}

	// Construir payload para OpenAI (gpt-4o, soporta imágenes)
	$messages = [
		["role" => "system", "content" => "Eres un asistente experto en agricultura. Responde solo sobre temas agrícolas, cultivos, plagas, clima, suelos, fertilización, imágenes de hojas y enfermedades. Si recibes una imagen, analiza y describe el estado agrícola de la planta."]
	];
	if ($imageData) {
		$messages[] = [
			"role" => "user",
			"content" => [
				["type" => "text", "text" => $message],
				["type" => "image_url", "image_url" => ["url" => "data:image/jpeg;base64,$imageData"]]
			]
		];
	} else {
		$messages[] = ["role" => "user", "content" => $message];
	}
	$payload = [
		"model" => "gpt-4o",
		"messages" => $messages,
		"max_tokens" => 800
	];

	if (!$OPENAI_API_KEY) {
		header('Content-Type: application/json');
		echo json_encode(["reply" => "Error: OPENAI_API_KEY no configurada en el entorno."]); exit;
	}

	$ch = curl_init('https://api.openai.com/v1/chat/completions');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'Content-Type: application/json',
		'Authorization: Bearer ' . $OPENAI_API_KEY
	]);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
	$result = curl_exec($ch);
	$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	$data = json_decode($result, true);
	if (isset($data['choices'][0]['message']['content'])) {
		$response = $data['choices'][0]['message']['content'];
	} else if (isset($data['error']['message'])) {
		$response = 'Error OpenAI: ' . $data['error']['message'];
	} else {
		$response = 'Error: No se pudo obtener respuesta de la IA. Código HTTP: ' . $httpcode;
	}
	header('Content-Type: application/json');
	echo json_encode(["reply" => $response]);
	exit;
}
?>
<!doctype html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Chat IA Agrícola</title>
	<link rel="stylesheet" href="../../recursos/css/general.css">
	<style>
		.chat-container{max-width:680px;margin:0 auto;padding:24px 0}
		.chat-messages{background:var(--green-1);border-radius:10px;padding:18px;min-height:220px;margin-bottom:18px;box-shadow:var(--shadow)}
		.chat-message{margin-bottom:12px}
		.chat-message.user{color:var(--green-4);font-weight:600}
		.chat-message.ia{color:var(--muted);background:var(--green-2);border-radius:8px;padding:8px}
		.chat-form{display:flex;gap:8px;align-items:center}
		.chat-form input[type="text"]{flex:1;padding:8px;border-radius:8px;border:1px solid var(--green-3)}
		.chat-form input[type="file"]{border:0}
		.chat-form button{padding:8px 16px;border-radius:8px;background:var(--green-4);color:white;border:0;font-weight:700}
		.chat-preview-img{max-width:120px;max-height:120px;border-radius:8px;margin-left:12px}
	</style>
</head>
<body>
	<main class="chat-container">
		<div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
			<button onclick="history.back()" class="btn" style="background:transparent;color:var(--green-4);border:1px solid var(--green-3);">← Volver</button>
			<h2 style="margin:0">Chat IA Agrícola</h2>
		</div>
		<div class="chat-messages" id="chat-messages">
			<!-- Mensajes se renderizan aquí -->
		</div>
		<form class="chat-form" id="chat-form" enctype="multipart/form-data" autocomplete="off">
			<input type="text" name="message" id="message" placeholder="Escribe tu pregunta agrícola..." required>
			<input type="file" name="image" id="image" accept="image/*">
			<img id="preview-img" class="chat-preview-img" style="display:none" alt="Preview imagen">
			<button type="submit">Enviar</button>
		</form>
	</main>
	<script>
	// Chat frontend
	const chatForm = document.getElementById('chat-form');
	const chatMessages = document.getElementById('chat-messages');
	const previewImg = document.getElementById('preview-img');
	const imageInput = document.getElementById('image');

	imageInput.addEventListener('change', function(){
		const file = imageInput.files[0];
		if(file){
			const reader = new FileReader();
			reader.onload = function(e){
				previewImg.src = e.target.result;
				previewImg.style.display = 'inline-block';
			};
			reader.readAsDataURL(file);
		}else{
			previewImg.style.display = 'none';
		}
	});

	chatForm.addEventListener('submit', function(e){
		e.preventDefault();
		const formData = new FormData(chatForm);
		const userMsg = formData.get('message');
		addMessage('user', userMsg);
		fetch('', {
			method: 'POST',
			body: formData
		})
		.then(res => res.json())
		.then(data => {
			addMessage('ia', data.reply);
		})
		.catch(()=>{
			addMessage('ia', 'Error al conectar con la IA.');
		});
		chatForm.reset();
		previewImg.style.display = 'none';
	});

	function addMessage(role, text){
		const div = document.createElement('div');
		div.className = 'chat-message ' + role;
		div.textContent = (role==='user'? 'Tú: ':'IA: ') + text;
		chatMessages.appendChild(div);
		chatMessages.scrollTop = chatMessages.scrollHeight;
	}
	</script>
</body>
</html>
