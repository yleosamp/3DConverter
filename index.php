<?php
require_once 'converter.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['model'])) {
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
                        surface: {
                            dark: '#1a1a1a',
                            light: '#ffffff'
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-50 dark:bg-gray-900 min-h-screen transition-colors duration-200">
    <div class="container mx-auto px-4 py-8 max-w-3xl">
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">
                Conversor 3D
            </h1>
            <p class="text-gray-600 dark:text-gray-400">
                Converta seus arquivos GLB para OBJ com texturas, materiais e normal maps
            </p>
        </div>

        <!-- Mensagem de erro/sucesso -->
        <?php if ($message): ?>
            <div class="mb-8 p-4 rounded-lg <?php echo $messageType === 'error' 
                ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400' 
                : 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400' ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Upload Form -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-8">
            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <!-- Drop Zone -->
                <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-8 text-center">
                    <input type="file" 
                           name="model" 
                           accept=".glb" 
                           required
                           class="hidden" 
                           id="fileInput">
                    
                    <label for="fileInput" class="cursor-pointer">
                        <div class="space-y-4">
                            <svg xmlns="http://www.w3.org/2000/svg" 
                                 class="h-12 w-12 mx-auto text-gray-400"
                                 fill="none" 
                                 viewBox="0 0 24 24" 
                                 stroke="currentColor">
                                <path stroke-linecap="round" 
                                      stroke-linejoin="round" 
                                      stroke-width="2" 
                                      d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            <div class="text-gray-600 dark:text-gray-400">
                                <span class="font-medium">Clique para selecionar</span> ou arraste seu arquivo GLB
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-500">
                                Apenas arquivos GLB são aceitos
                            </p>
                        </div>
                    </label>
                </div>

                <!-- Botão de Converter -->
                <button type="submit" 
                        class="w-full bg-primary hover:bg-primary/90 text-white font-medium py-3 px-6 rounded-lg
                               shadow-lg shadow-primary/30 transition-all duration-200
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
            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
                <div class="text-primary mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Texturas</h3>
                <p class="text-gray-600 dark:text-gray-400">Preserva todas as texturas do modelo original</p>
            </div>

            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
                <div class="text-primary mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Materiais</h3>
                <p class="text-gray-600 dark:text-gray-400">Suporte a PBR, metallic/roughness</p>
            </div>

            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
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
        // Drag and drop functionality
        const dropZone = document.querySelector('form');
        const fileInput = document.getElementById('fileInput');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults (e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            dropZone.classList.add('border-primary');
        }

        function unhighlight(e) {
            dropZone.classList.remove('border-primary');
        }

        dropZone.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
        }
    </script>
</body>
</html> 