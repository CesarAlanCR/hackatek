
<?php
// Chat IA especializado en agricultura
// Archivo: vistas/ia/chat.php

// Configuración: intentar cargar .env simple desde la raíz del proyecto (si existe)
$envPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
if (file_exists($envPath)) {
	$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	foreach ($lines as $line) {
		// eliminar BOM si existe
		$line = preg_replace("/^\xEF\xBB\xBF/", '', $line);
		$line = trim($line);
		if ($line === '' || $line[0] === '#') continue;
		if (strpos($line, '=') === false) continue;
		list($k, $v) = explode('=', $line, 2);
		$k = trim($k);
		$v = trim($v);
		// quitar comillas si las hay
		if (strlen($v) >= 2 && (($v[0] === '"' && substr($v, -1) === '"') || ($v[0] === "'" && substr($v, -1) === "'"))) {
			$v = substr($v, 1, -1);
		}
		if (getenv($k) === false) {
			putenv("$k=$v");
			$_ENV[$k] = $v;
		}
	}
}

// Leer API Key OpenAI desde variable de entorno
$OPENAI_API_KEY = getenv('OPENAI_API_KEY') ?: getenv('OPENAI_APIKEY') ?: null;

// Nota: para desarrollo local también puedes usar vlucas/phpdotenv si prefieres.

// Recibir parámetros de contexto de ubicación (desde GET)
$clima = $_GET['clima'] ?? '';
$lat = $_GET['lat'] ?? '';
$lon = $_GET['lon'] ?? '';
$suelo = $_GET['suelo'] ?? '';
$estado = $_GET['estado'] ?? '';
$temporada = $_GET['temporada'] ?? '';

// Endpoint para procesar mensajes (POST)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$message = $_POST['message'] ?? '';
	$response = '';
	$imageData = null;
	if (isset($_FILES['image']) && $_FILES['image']['tmp_name']) {
		$imageData = base64_encode(file_get_contents($_FILES['image']['tmp_name']));
	}

	// --- RAG: buscar contexto relevante en knowledge.sqlite ---
	$rag_context = '';
	$query_script = dirname(__DIR__, 2) . '/recursos/docs/query_rag.py';
	if (file_exists($query_script)) {
		$cmd = escapeshellcmd("python " . escapeshellarg($query_script) . " " . escapeshellarg($message));
		$rag_output = shell_exec($cmd);
		if ($rag_output) {
			// Extraer los fragmentos del output
			$chunks = [];
			foreach (explode("---", $rag_output) as $frag) {
				$frag = trim($frag);
				if ($frag && strlen($frag) > 30) $chunks[] = $frag;
			}
			if ($chunks) {
				$rag_context = "Contexto relevante:\n" . implode("\n\n", $chunks);
			}
		}
	}

	// Construir payload para OpenAI (gpt-4o, soporta imágenes)
	$system_prompt = "Eres un asistente experto en agricultura. Responde solo sobre temas agrícolas, cultivos, plagas, clima, suelos, fertilización, imágenes de hojas y enfermedades. Si recibes una imagen, analiza y describe el estado agrícola de la planta.";
	
	// Añadir contexto de ubicación del usuario (si está disponible)
	if ($estado || $clima || $suelo || $temporada) {
		$system_prompt .= "\n\nContexto del usuario:";
		if ($estado) $system_prompt .= "\n- Estado: $estado";
		if ($clima) $system_prompt .= "\n- Clima actual: $clima";
		if ($suelo) $system_prompt .= "\n- Tipo de suelo: $suelo";
		if ($temporada) $system_prompt .= "\n- Temporada: $temporada";
		if ($lat && $lon) $system_prompt .= "\n- Coordenadas: $lat, $lon";
		$system_prompt .= "\n\nUsa esta información para personalizar tus recomendaciones agrícolas según las condiciones locales del usuario.";
	}
	
	if ($rag_context) {
		$system_prompt .= "\n\n" . $rag_context;
	}
	$messages = [
		["role" => "system", "content" => $system_prompt]
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
			// Diagnóstico: ver si existe .env y si contiene la clave
			$diag = [];
			if (file_exists($envPath)) {
				$diag[] = ".env encontrado en: $envPath";
				// comprobar si la línea existe en el archivo
				$envContents = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
				$found = false; $empty = false;
				foreach ($envContents as $l) {
					$l = preg_replace("/^\xEF\xBB\xBF/", '', $l);
					$l = trim($l);
					if (stripos($l, 'OPENAI_API_KEY=') === 0) {
						$found = true;
						$val = substr($l, strlen('OPENAI_API_KEY='));
						if (trim($val) === '') $empty = true;
						break;
					}
				}
				if ($found) {
					$diag[] = 'OPENAI_API_KEY presente en .env' . ($empty ? ' (vacía)' : ' (no vacía)');
				} else {
					$diag[] = 'OPENAI_API_KEY no encontrada en .env';
				}
			} else {
				$diag[] = ".env no encontrado en: $envPath";
			}
			header('Content-Type: application/json');
			echo json_encode(["reply" => "Error: OPENAI_API_KEY no configurada en el entorno.", "diag" => $diag]); exit;
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
	echo json_encode([
		"reply" => $response,
		"context" => $rag_context ?: null
	]);
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
	<link rel="icon" type="image/png" href="../logo.png">
	<style>
		.chat-container{
			background:var(--bg-card);
			border-radius:var(--radius-lg);
			overflow:hidden;
			box-shadow:var(--shadow-lg);
			border:1px solid var(--border);
			animation:slideUp 0.6s cubic-bezier(0.4, 0, 0.2, 1);
			max-width:1000px;
			margin:0 auto;
			display:flex;
			flex-direction:column;
			height:calc(100vh - 120px);
			max-height:800px;
			position:relative; /* allow absolute footer form */
		}
		@keyframes slideUp{from{opacity:0;transform:translateY(40px)}to{opacity:1;transform:translateY(0)}}
		.chat-header{
			padding:20px 24px;
			background:rgba(30, 41, 54, 0.6);
			backdrop-filter:blur(10px);
			border-bottom:1px solid var(--border);
			display:flex;
			align-items:center;
			gap:16px;
		}
		.chat-header h5{
			margin:0;
			color:var(--accent);
			font-size:1.4rem;
			font-weight:700;
			flex:1;
			text-align:center;
			/* visually center title similar to before, but keep back button z-index above */
			transform:translateX(-70px);
			letter-spacing:-0.5px;
		}
		.btn-back{
			background:rgba(124, 179, 66, 0.15);
			border:1px solid var(--border-hover);
			color:var(--accent);
			padding:10px 20px;
			border-radius:var(--radius);
			font-weight:600;
			text-decoration:none;
			transition:var(--transition-fast);
			z-index:10; /* ensure clickable above the header title */
		}
		.btn-back:hover{
			background:var(--accent);
			color:white;
			transform:translateX(-4px);
		}
		.typing-indicator{
			font-size:0.85rem;
			color:var(--text-muted);
			padding:6px 12px;
			background:rgba(124, 179, 66, 0.1);
			border-radius:20px;
			animation:pulse 1.5s ease-in-out infinite;
		}
		@keyframes pulse{0%, 100%{opacity:1}50%{opacity:0.5}}
		.chat-messages{
			flex:1;
			overflow-y:auto;
			padding:24px 24px 120px 24px; /* generous bottom padding for form overlay; JS will adjust */
			background:var(--bg-secondary);
			scrollbar-width:thin;
			scrollbar-color:var(--border) var(--bg-secondary);
			min-height:300px;
		}
		.chat-messages::-webkit-scrollbar{width:8px}
		.chat-messages::-webkit-scrollbar-track{background:var(--bg-secondary)}
		.chat-messages::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}
		.chat-messages::-webkit-scrollbar-thumb:hover{background:var(--border-hover)}
		.msg-wrapper{
			display:flex;
			margin-bottom:16px;
			animation:messageSlide 0.4s cubic-bezier(0.4, 0, 0.2, 1);
		}
		@keyframes messageSlide{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
		.msg{
			padding:14px 18px;
			border-radius:var(--radius-lg);
			max-width:75%;
			word-wrap:break-word;
			box-shadow:0 2px 8px rgba(0,0,0,0.15);
			position:relative;
		}
		.msg.user{
			background:linear-gradient(135deg, var(--green-4), var(--accent));
			color:white;
			margin-left:auto;
			border-bottom-right-radius:4px;
		}
		.msg.assistant{
			background:var(--bg-card);
			color:var(--text-primary);
			border:1px solid var(--border);
			border-bottom-left-radius:4px;
		}
		.msg strong{
			display:block;
			margin-bottom:6px;
			font-size:0.85rem;
			opacity:0.8;
		}
		.chat-form{
			padding:20px;
			background:rgba(30, 41, 54, 0.9);
			border-top:1px solid var(--border);
			flex-shrink:0;
			position:absolute; /* anchor at bottom to avoid layout shifts */
			left:0;
			right:0;
			bottom:0;
			z-index:5;
		}
		.chat-input{
			background:var(--bg-secondary);
			border:1px solid var(--border);
			color:var(--text-primary);
			border-radius:var(--radius);
			padding:12px 16px;
			transition:var(--transition-fast);
			font-size:0.95rem;
			resize:none;
			overflow-y:hidden;
		}
		.chat-input:focus{
			outline:none;
			border-color:var(--accent);
			box-shadow:0 0 0 3px var(--green-glow);
		}
		.chat-input::placeholder{color:var(--text-muted)}
		.chat-send{
			background:linear-gradient(135deg, var(--green-4), var(--accent));
			color:white;
			border:none;
			padding:12px 24px;
			border-radius:var(--radius);
			font-weight:600;
			transition:var(--transition-bounce);
			cursor:pointer;
		}
		.chat-send:hover{
			transform:scale(1.05);
			box-shadow:var(--shadow-glow);
		}
		.btn-image{
			background:rgba(124, 179, 66, 0.15);
			border:1px solid var(--border);
			color:var(--accent);
			padding:10px;
			border-radius:var(--radius);
			transition:var(--transition-fast);
			cursor:pointer;
		}
		.btn-image:hover{
			background:var(--accent);
			color:white;
		}
		.chat-preview-img{
			border:2px solid var(--border);
			border-radius:var(--radius);
			box-shadow:var(--shadow);
		}
	</style>
</head>
<body>
	<main class="container" style="padding:40px 0">
		<div class="chat-container">
			<div class="chat-header">
				<a href="../index.php" class="btn-back">← Volver</a>
				<h5>Asistente Agrícola IA</h5>
				<div id="typing" class="typing-indicator" style="display:none">✍️ Escribiendo...</div>
			</div>
			
			<div class="chat-messages" id="chat-messages" aria-live="polite"></div>

			<form class="chat-form" id="chat-form" enctype="multipart/form-data" autocomplete="off">
				<div style="display:flex;align-items:end;gap:12px">
					<textarea name="message" id="message" class="chat-input" placeholder="Pregunta sobre cultivos, plagas, clima..." required aria-label="Mensaje" rows="1" style="flex:1;min-height:48px;max-height:140px"></textarea>
					
					<label for="image" class="btn-image" title="Adjuntar imagen" style="display:inline-flex;align-items:center;justify-content:center;width:48px;height:48px">
						<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
							<path d="M6.002 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/>
							<path d="M2.002 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2h-12zm12 1a1 1 0 0 1 1 1v6.5l-3.777-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12V3a1 1 0 0 1 1-1h12z"/>
						</svg>
					</label>
					<input type="file" name="image" id="image" style="display:none" accept="image/*" aria-label="Adjuntar imagen">
					
					<button id="send-btn" type="submit" class="chat-send">Enviar</button>
				</div>
				<div style="margin-top:12px">
					<img id="preview-img" class="chat-preview-img" style="display:none;max-width:160px;max-height:120px;object-fit:cover" alt="Preview imagen">
				</div>
			</form>
		</div>
	</main>
	<script>
	// Chat frontend (updated for bootstrap UI)
	const chatForm = document.getElementById('chat-form');
	const chatMessages = document.getElementById('chat-messages');
	const previewImg = document.getElementById('preview-img');
	const imageInput = document.getElementById('image');
	const typingEl = document.getElementById('typing');

	// cuando el input cambia (selección por dialogo)
	imageInput.addEventListener('change', function(){
		const file = imageInput.files[0];
		handleSelectedFile(file);
	});

	// Drag & Drop sobre toda el area del chat
	const chatContainer = document.querySelector('.chat-container');
	let dragCounter = 0;
	chatContainer.addEventListener('dragenter', (e) => {
		e.preventDefault();
		dragCounter++;
		chatContainer.style.borderColor = 'var(--accent)';
		chatContainer.style.borderWidth = '2px';
	});
	chatContainer.addEventListener('dragover', (e) => {
		e.preventDefault();
	});
	chatContainer.addEventListener('dragleave', (e) => {
		e.preventDefault();
		dragCounter--;
		if (dragCounter === 0) {
			chatContainer.style.borderColor = 'var(--border)';
			chatContainer.style.borderWidth = '1px';
		}
	});
	chatContainer.addEventListener('drop', (e) => {
		e.preventDefault();
		dragCounter = 0;
		chatContainer.style.borderColor = 'var(--border)';
		chatContainer.style.borderWidth = '1px';
		const dt = e.dataTransfer;
		if (!dt || !dt.files || dt.files.length === 0) return;
		const file = dt.files[0];
		// solo imágenes
		if (file.type && file.type.startsWith('image/')) {
			// adjuntar al input de archivo para que el form lo envíe
			const dataTransfer = new DataTransfer();
			dataTransfer.items.add(file);
			imageInput.files = dataTransfer.files;
			handleSelectedFile(file);
		} else {
			alert('Solo se permiten archivos de imagen.');
		}
	});

	function handleSelectedFile(file){
		if (!file) { previewImg.style.display = 'none'; return; }
		const reader = new FileReader();
		reader.onload = function(e){
			previewImg.src = e.target.result;
			previewImg.style.display = 'inline-block';
		};
		reader.readAsDataURL(file);
	}

	// Auto-resize textarea: crece hasta max-height (5 líneas aprox = 140px) y luego muestra scroll interno
	const inputEl = document.getElementById('message');
	const MAX_HEIGHT = 140; // px (aproximadamente 5 líneas)
	const sendBtn = document.getElementById('send-btn');
	
	function resizeTextarea(el){
		el.style.height = 'auto';
		const newHeight = Math.min(el.scrollHeight, MAX_HEIGHT);
		el.style.height = newHeight + 'px';
		// si excede max, mantener scroll interno
		if (el.scrollHeight > MAX_HEIGHT) {
			el.style.overflowY = 'auto';
		} else {
			el.style.overflowY = 'hidden';
		}

		// Ajustar padding-bottom del área de mensajes para que el textarea no empuje el contenido
		try {
			const formRect = chatForm.getBoundingClientRect();
			const containerRect = document.querySelector('.chat-container').getBoundingClientRect();
			// altura visible del form dentro del contenedor
			const visibleFormHeight = formRect.height;
			// añadir un pequeño margen
			chatMessages.style.paddingBottom = (visibleFormHeight + 24) + 'px';
		} catch (err) {
			// ignore en entornos sin layout completo
		}
	}
	
	// inicial
	resizeTextarea(inputEl);
	inputEl.addEventListener('input', function(e){
		resizeTextarea(e.target);
	});

	// ResizeObserver para actualizar padding cuando el formulario cambia de tamaño
	if (typeof ResizeObserver !== 'undefined') {
		const ro = new ResizeObserver(() => {
			try {
				const formRect = chatForm.getBoundingClientRect();
				chatMessages.style.paddingBottom = (formRect.height + 24) + 'px';
			} catch (err) {
				// ignore
			}
		});
		ro.observe(chatForm);
	}

	// Enviar con Enter (Shift+Enter para nueva línea)
	inputEl.addEventListener('keydown', function(e){
		if (e.key === 'Enter' && !e.shiftKey) {
			e.preventDefault();
			chatForm.requestSubmit();
		}
	});

	chatForm.addEventListener('submit', function(e){
		e.preventDefault();
		const formData = new FormData(chatForm);
		const userMsg = formData.get('message');
		// if there is a selected file, pass its preview as image
		const file = imageInput.files[0];
		const userPreview = previewImg && previewImg.style.display !== 'none' ? previewImg.src : null;
		addMessage('user', userMsg, userPreview);
		// show typing indicator
		typingEl.style.display = 'inline-block';
		fetch('', {
			method: 'POST',
			body: formData
		})
		.then(res => res.json())
		.then(data => {
			typingEl.style.display = 'none';
			addMessage('ia', data.reply);
		})
		.catch(()=>{
			typingEl.style.display = 'none';
			addMessage('ia', 'Error al conectar con la IA.');
		});
			chatForm.reset();
			previewImg.style.display = 'none';
			// reset textarea height and recalc padding so messages aren't hidden
			inputEl.style.height = '48px';
			resizeTextarea(inputEl);
			// return focus to input for quick follow-up messages
			inputEl.focus();
	});

	function addMessage(role, text, imageSrc){
		const wrapper = document.createElement('div');
		wrapper.className = 'msg-wrapper';
		const bubble = document.createElement('div');
		bubble.className = 'msg ' + role;
		const safeText = String(text).replace(/\n/g, '<br>');
		let inner = (role==='user'? '<strong>Tú</strong>' : '<strong>IA</strong>') + safeText;
		if (imageSrc) {
			inner += '<div style="margin-top:8px"><img src="' + imageSrc + '" style="max-width:220px;max-height:160px;object-fit:cover;border-radius:8px;border:1px solid var(--border)"></div>';
		}
		bubble.innerHTML = inner;
		wrapper.appendChild(bubble);
		chatMessages.appendChild(wrapper);
		chatMessages.scrollTo({ top: chatMessages.scrollHeight, behavior: 'smooth' });
	}
	</script>
	<script src="../../recursos/js/animations.js" defer></script>
</body>
</html>
