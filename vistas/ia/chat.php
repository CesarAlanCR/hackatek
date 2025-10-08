
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
	<!-- Bootstrap CSS -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-..." crossorigin="anonymous">
	<link rel="stylesheet" href="../../recursos/css/general.css">
	<style>
		/* Small overrides to integrate with bootstrap */
		.chat-shell{max-height:calc(100vh - 140px)}
		.typing-indicator{font-size:0.9rem;color:var(--muted);margin-left:8px}
	</style>
</head>
<body class="bg-light">
	<div class="container py-3">
		<div class="row justify-content-center">
			<div class="col-12 col-md-10">
				<div class="card shadow-sm">
					<div class="card-header d-flex align-items-center">
						<button onclick="history.back()" class="btn btn-outline-success btn-sm me-3">← Volver</button>
						<div class="flex-fill text-center">
							<h5 class="mb-0">Asistente Agrícola</h5>
						</div>
						<div id="typing" class="typing-indicator ms-auto" style="display:none">IA está escribiendo...</div>
					</div>
					<div class="card-body p-0">
						<div class="chat-shell d-flex flex-column">
							<!-- area de mensajes con su propio scroll -->
							<div class="chat-messages flex-grow-1 overflow-auto p-3" id="chat-messages" aria-live="polite"></div>

							<form class="chat-form border-top" id="chat-form" enctype="multipart/form-data" autocomplete="off">
								<div class="d-flex align-items-center gap-2 p-3">
									<textarea name="message" id="message" class="chat-input form-control flex-grow-1 me-2" placeholder="Escribe tu pregunta agrícola..." required aria-label="Mensaje" rows="1" style="height:auto;min-height:44px;max-height:140px;overflow:auto;resize:none"></textarea>

																		<!-- Custom small file button -->
																		<label for="image" id="image-label" class="btn btn-outline-secondary btn-sm px-2 py-1 d-flex align-items-center justify-content-center" title="Adjuntar imagen">
																				<!-- nicer inline SVG icon for image -->
																				<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
																					<path d="M14 3a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h12z" fill-opacity="0" stroke="currentColor" stroke-width="0.5"/>
																					<path d="M14 3H2a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1zM4.502 9.02a1.5 1.5 0 1 1 2.996 0 1.5 1.5 0 0 1-2.996 0zM2 12l3.5-4.5L9 12h5"/>
																				</svg>
																		</label>
																		<button id="send-btn" type="submit" class="chat-send btn btn-success btn-sm px-3 py-1 d-flex align-items-center justify-content-center">Enviar</button>
									<input type="file" name="image" id="image" class="d-none" accept="image/*" aria-label="Adjuntar imagen">
								</div>
								<div class="px-3 pb-3 d-flex align-items-center gap-3">
									<img id="preview-img" class="chat-preview-img rounded border" style="display:none;max-width:160px;max-height:120px;object-fit:cover" alt="Preview imagen">
								</div>
							</form>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Bootstrap JS (optional) -->
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-..." crossorigin="anonymous"></script>
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

	// etiqueta personalizada abre el selector
	const imageLabel = document.getElementById('image-label');
	imageLabel.addEventListener('click', function(e){
		e.preventDefault();
		imageInput.click();
	});

	// Drag & Drop sobre toda el area del chat
	const chatShell = document.querySelector('.chat-shell');
	let dragCounter = 0;
	chatShell.addEventListener('dragenter', (e) => {
		e.preventDefault();
		dragCounter++;
		chatShell.classList.add('border', 'border-secondary', 'bg-white');
	});
	chatShell.addEventListener('dragover', (e) => {
		e.preventDefault();
	});
	chatShell.addEventListener('dragleave', (e) => {
		e.preventDefault();
		dragCounter--;
		if (dragCounter === 0) {
			chatShell.classList.remove('border', 'border-secondary', 'bg-white');
		}
	});
	chatShell.addEventListener('drop', (e) => {
		e.preventDefault();
		dragCounter = 0;
		chatShell.classList.remove('border', 'border-secondary', 'bg-white');
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
			// label muestra el nombre del archivo
			imageLabel.textContent = file.name.length > 20 ? file.name.slice(0,18) + '…' : file.name;
		};
		reader.readAsDataURL(file);
	}

	// Auto-resize textarea: crece hasta max-height y luego muestra scroll interno
	const inputEl = document.getElementById('message');
	const MAX_HEIGHT = 140; // px
	const imageLabelEl = document.getElementById('image-label');
	const sendBtn = document.getElementById('send-btn');
	function resizeTextarea(el){
		el.style.height = 'auto';
		const newHeight = Math.min(el.scrollHeight, MAX_HEIGHT);
		el.style.height = newHeight + 'px';
		// si excede max, mantener scroll interno
		if (el.scrollHeight > MAX_HEIGHT) {
			el.style.overflow = 'auto';
		} else {
			el.style.overflow = 'hidden';
		}
		// ajustar altura de botones para que coincida con el textarea
		try {
			if (imageLabelEl) {
				imageLabelEl.style.height = el.style.height;
			}
			if (sendBtn) {
				sendBtn.style.height = el.style.height;
			}
		} catch (err) {
			// ignore
		}
	}
	// inicial
	resizeTextarea(inputEl);
	inputEl.addEventListener('input', function(e){
		resizeTextarea(e.target);
	});

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
		// limpiar label y preview
		imageLabel.textContent = '📷 Adjuntar';
		previewImg.style.display = 'none';
	});

	function addMessage(role, text, imageSrc){
		const wrapper = document.createElement('div');
		wrapper.className = role === 'user' ? 'd-flex justify-content-end' : 'd-flex justify-content-start';
		const bubble = document.createElement('div');
		bubble.className = 'msg ' + role + ' p-2';
		const safeText = String(text).replace(/\n/g, '<br>');
		let inner = (role==='user'? '<strong>Tú</strong><br>' : '<strong>IA</strong><br>') + safeText;
		if (imageSrc) {
			inner += '<div class="mt-2"><img src="' + imageSrc + '" style="max-width:220px;max-height:160px;object-fit:cover;border-radius:6px;border:1px solid rgba(0,0,0,0.06)"></div>';
		}
		bubble.innerHTML = inner;
		wrapper.appendChild(bubble);
		chatMessages.appendChild(wrapper);
		chatMessages.scrollTo({ top: chatMessages.scrollHeight, behavior: 'smooth' });
	}
	</script>
</body>
</html>
