<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar permissões
$uploadDir = __DIR__ . '/uploads';
if (!is_writable($uploadDir)) {
    die('Diretório de uploads sem permissão de escrita: ' . $uploadDir);
}

require_once 'converter.php';

$message = '';
$messageType = '';

$formatosSuportados = [
    'glb' => ['nome' => 'GL Transmission Binary', 'ext' => '.glb'],
    'fbx' => ['nome' => 'Autodesk FBX', 'ext' => '.fbx']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['model'])) {
        // Debug do upload
        error_log('Upload recebido:');
        error_log('Nome do arquivo: ' . $_FILES['model']['name']);
        error_log('Arquivo temporário: ' . $_FILES['model']['tmp_name']);
        error_log('Erro de upload: ' . $_FILES['model']['error']);
        
        // Verifica se houve erro no upload
        if ($_FILES['model']['error'] !== UPLOAD_ERR_OK) {
            $message = "Erro no upload do arquivo: ";
            switch ($_FILES['model']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                    $message .= "O arquivo excede o tamanho máximo permitido pelo PHP.";
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $message .= "O arquivo excede o tamanho máximo permitido pelo formulário.";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $message .= "O upload foi interrompido.";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $message .= "Nenhum arquivo foi enviado.";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $message .= "Diretório temporário não encontrado.";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $message .= "Falha ao escrever o arquivo.";
                    break;
                default:
                    $message .= "Erro desconhecido.";
            }
            error_log($message);
            die($message);
        }

        $file = $_FILES['model'];
        $converter = new ModelConverter();
        
        if ($converter->isValidFile($file)) {
            try {
                $result = $converter->convert($file);
                if ($result) {
                    header('Content-Type: application/zip');
                    header('Content-Disposition: attachment; filename="converted_model.zip"');
                    readfile($result);
                    unlink($result);
                    exit;
                } else {
                    $message = "Erro ao converter o arquivo.";
                    $messageType = "error";
                }
            } catch (Exception $e) {
                $message = "Erro: " . $e->getMessage();
                $messageType = "error";
            }
        } else {
            $message = "Arquivo inválido. Por favor, envie um arquivo GLB.";
            $messageType = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conversor GLB para OBJ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#6366f1',
                        dark: {
                            900: '#202020',
                            800: '#2d2d2d',
                            700: '#333333',
                            600: '#404040'
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <link rel="icon" type="image/png" href="./favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="./favicon.svg" />
    <link rel="shortcut icon" href="./favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="./apple-touch-icon.png" />
    <link rel="manifest" href="./site.webmanifest" />
</head>

<body class="bg-dark-900 min-h-screen transition-colors duration-200">
    <div class="container mx-auto px-4 py-8 max-w-3xl">
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-white mb-4">
                Conversor 3D
            </h1>
            <p class="text-gray-400">
                Converta seus arquivos GLB para OBJ com texturas, materiais e normal maps
            </p>
        </div>

        <!-- Mensagem de erro/sucesso -->
        <?php if ($message): ?>
            <div class="mb-8 p-4 rounded-lg <?php echo $messageType === 'error' 
                ? 'bg-red-900/30 text-red-400 border border-red-800' 
                : 'bg-green-900/30 text-green-400 border border-green-800' ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Upload Form -->
        <div class="bg-dark-800 rounded-xl shadow-lg p-8 border border-dark-700">
            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <!-- Seleção de Formato -->
                <div class="mb-6">
                    <select name="format" id="formatSelect" 
                            class="bg-dark-700 text-gray-300 rounded-lg px-4 py-2 w-full
                                   border border-dark-600 focus:border-primary focus:ring-1 focus:ring-primary
                                   disabled:opacity-50 disabled:cursor-not-allowed">
                        <option value="">Detectar formato automaticamente</option>
                        <?php foreach ($formatosSuportados as $ext => $info): ?>
                            <option value="<?php echo $ext; ?>">
                                <?php echo $info['nome'] . ' (' . $info['ext'] . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Drop Zone -->
                <div id="dropZone" class="border-2 border-dashed border-dark-600 rounded-lg p-8 text-center transition-all duration-200">
                    <input type="file" 
                           name="model" 
                           accept="<?php echo '.'.implode(',.', array_keys($formatosSuportados)); ?>"
                           required
                           class="hidden" 
                           id="fileInput">
                    
                    <label for="fileInput" class="cursor-pointer">
                        <div class="space-y-4">
                            <div id="uploadIcon" class="transition-all duration-200">
                                <svg xmlns="http://www.w3.org/2000/svg" 
                                     class="h-12 w-12 mx-auto text-gray-600"
                                     fill="none" 
                                     viewBox="0 0 24 24" 
                                     stroke="currentColor">
                                    <path stroke-linecap="round" 
                                          stroke-linejoin="round" 
                                          stroke-width="2" 
                                          d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                            </div>
                            <div id="fileName" class="text-gray-400 hidden">
                                Arquivo selecionado: <span class="font-medium text-primary"></span>
                            </div>
                            <div id="uploadText" class="text-gray-400">
                                <span class="font-medium">Clique para selecionar</span> ou arraste seu arquivo GLB
                            </div>
                            <p class="text-sm text-gray-600">
                                Apenas arquivos GLB são aceitos
                            </p>
                        </div>
                    </label>
                </div>

                <!-- Botão de Converter -->
                <button type="submit" 
                        class="w-full bg-primary hover:bg-primary/90 text-white font-medium py-3 px-6 rounded-lg
                               shadow-lg shadow-primary/20 transition-all duration-200
                               flex items-center justify-center space-x-2">
                    <svg xmlns="http://www.w3.org/2000/svg" 
                         class="h-5 w-5" 
                         fill="none" 
                         viewBox="0 0 24 24" 
                         stroke="currentColor">
                        <path stroke-linecap="round" 
                              stroke-linejoin="round" 
                              stroke-width="2" 
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    <span>Converter</span>
                </button>
            </form>
        </div>

        <!-- Features -->
        <div class="mt-12 grid md:grid-cols-3 gap-6">
            <div class="bg-white dark:bg-dark-800 p-6 rounded-lg shadow-md">
                <div class="text-primary mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Texturas</h3>
                <p class="text-gray-600 dark:text-gray-400">Preserva todas as texturas do modelo original</p>
            </div>

            <div class="bg-white dark:bg-dark-800 p-6 rounded-lg shadow-md">
                <div class="text-primary mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Materiais</h3>
                <p class="text-gray-600 dark:text-gray-400">Suporte a PBR, metallic/roughness</p>
            </div>

            <div class="bg-white dark:bg-dark-800 p-6 rounded-lg shadow-md">
                <div class="text-primary mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2 1m2-1l-2-1m2 1v2.5M14 4l-2-1-2 1M4 7l2-1M4 7l2 1M4 7v2.5M12 21l-2-1m2 1l2-1m-2 1v-2.5M6 18l-2-1v-2.5M18 18l2-1v-2.5" />
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Normal Maps</h3>
                <p class="text-gray-600 dark:text-gray-400">Suporte completo a normal maps</p>
            </div>
        </div>
    </div>

    <script>
        const fileInput = document.getElementById('fileInput');
        const dropZone = document.getElementById('dropZone');
        const fileName = document.getElementById('fileName');
        const uploadText = document.getElementById('uploadText');
        const uploadIcon = document.getElementById('uploadIcon');

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                const file = e.target.files[0];
                updateFileName(file);
            }
        });

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('border-primary', 'bg-primary/5');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('border-primary', 'bg-primary/5');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            const file = e.dataTransfer.files[0];
            fileInput.files = e.dataTransfer.files;
            updateFileName(file);
        });

        function updateFileName(file) {
            fileName.querySelector('span').textContent = file.name;
            fileName.classList.remove('hidden');
            uploadText.classList.add('hidden');
            uploadIcon.classList.add('text-primary');
            dropZone.classList.add('border-primary', 'bg-primary/5');
        }
    </script>
</body>
</html> 