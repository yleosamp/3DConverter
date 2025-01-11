<?php
require_once __DIR__ . '/parsers/IModelParser.php';
require_once __DIR__ . '/parsers/GlbParser.php';
require_once __DIR__ . '/parsers/FbxParser.php';
require_once __DIR__ . '/parsers/ColladaParser.php';
require_once __DIR__ . '/parsers/StlParser.php';
// ... outros parsers

class ModelConverter {
    private $tempDir;
    private $format;
    private $parser;
    
    public function __construct() {
        $this->tempDir = __DIR__ . '/temp/' . uniqid();
        if (!file_exists($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
        mkdir($this->tempDir . '/textures');
    }
    
    public function isValidFile($file) {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        return in_array($extension, ['glb', 'fbx']);
    }
    
    public function convert($file) {
        try {
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $this->format = $extension;
            $tempFile = $this->tempDir . '/model.' . $extension;
            
            if (!move_uploaded_file($file['tmp_name'], $tempFile)) {
                throw new Exception('Erro ao mover arquivo');
            }
            
            $this->parser = $this->getParser($extension, $tempFile);
            $model = $this->parser->parse();
            
            // Debug
            error_log('Modelo parseado: ' . print_r($model, true));
            
            $this->generateFiles($model);
            return $this->createZipArchive();
            
        } catch (Exception $e) {
            error_log('Erro na conversão: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }
    
    private function getParser($extension, $filePath) {
        switch ($extension) {
            case 'glb':
                return new GlbParser($filePath);
            case 'fbx':
                return new FbxParser($filePath);
            default:
                throw new Exception('Formato não suportado. Use GLB ou FBX.');
        }
    }
    
    private function generateFiles($model) {
        // Gera arquivo OBJ
        $objContent = $this->generateObjContent($model);
        file_put_contents($this->tempDir . '/model.obj', $objContent);

        // Gera arquivo MTL
        if (!empty($model['materials'])) {
            $mtlContent = $this->generateMtlContent($model);
            file_put_contents($this->tempDir . '/model.mtl', $mtlContent);
        }

        // Processa texturas
        foreach ($model['materials'] as $index => $material) {
            if (isset($material['pbrMetallicRoughness'])) {
                $pbr = $material['pbrMetallicRoughness'];
                
                // Textura base
                if (isset($pbr['baseColorTexture'])) {
                    $this->saveTexture($model, $pbr['baseColorTexture'], 'baseColor_' . $index);
                }
                
                // Textura metálica/rugosidade
                if (isset($pbr['metallicRoughnessTexture'])) {
                    $this->saveTexture($model, $pbr['metallicRoughnessTexture'], 'metallic_' . $index);
                }
            }
            
            // Normal map
            if (isset($material['normalTexture'])) {
                $this->saveTexture($model, $material['normalTexture'], 'normal_' . $index);
            }
        }
    }

    private function saveTexture($model, $textureInfo, $prefix) {
        $textureIndex = $textureInfo['index'];
        $texture = $model['textures'][$textureIndex];
        
        if (isset($texture['data'])) {
            $extension = $this->getTextureExtension($texture['mimeType']);
            file_put_contents(
                $this->tempDir . '/textures/' . $prefix . $extension,
                $texture['data']
            );
        }
    }

    private function generateObjContent($model) {
        $content = "# Convertido por 3D Converter\n";
        $content .= "mtllib model.mtl\n\n";

        // Vértices
        foreach ($model['vertices'] as $v) {
            $content .= sprintf("v %.6f %.6f %.6f\n", $v[0], $v[1], $v[2]);
        }

        // Normais
        foreach ($model['normals'] as $n) {
            $content .= sprintf("vn %.6f %.6f %.6f\n", $n[0], $n[1], $n[2]);
        }

        // UVs
        foreach ($model['uvs'] as $uv) {
            $content .= sprintf("vt %.6f %.6f\n", $uv[0], $uv[1]);
        }

        // Faces
        $content .= "\n# Faces\n";
        $vertexCount = count($model['vertices']);
        for ($i = 0; $i < $vertexCount; $i += 3) {
            $v1 = $i + 1;
            $v2 = $i + 2;
            $v3 = $i + 3;
            $content .= sprintf("f %d/%d/%d %d/%d/%d %d/%d/%d\n",
                $v1, $v1, $v1,
                $v2, $v2, $v2,
                $v3, $v3, $v3
            );
        }

        return $content;
    }

    private function generateMtlContent($model) {
        $content = "# Material file generated by 3D Converter\n\n";

        foreach ($model['materials'] as $index => $material) {
            $content .= "newmtl " . $material['name'] . "\n";
            
            if (isset($material['pbrMetallicRoughness'])) {
                $pbr = $material['pbrMetallicRoughness'];
                
                // Cor base
                if (isset($pbr['baseColorFactor'])) {
                    $content .= sprintf("Kd %.6f %.6f %.6f\n",
                        $pbr['baseColorFactor'][0],
                        $pbr['baseColorFactor'][1],
                        $pbr['baseColorFactor'][2]
                    );
                }

                // Textura base
                if (isset($pbr['baseColorTexture'])) {
                    $content .= "map_Kd textures/texture_" . $pbr['baseColorTexture']['index'] . ".png\n";
                }
            }

            // Normal map
            if (isset($material['normalTexture'])) {
                $content .= "map_Bump textures/texture_" . $material['normalTexture']['index'] . ".png\n";
            }

            $content .= "\n";
        }

        return $content;
    }

    private function getTextureExtension($mimeType) {
        switch ($mimeType) {
            case 'image/jpeg':
                return '.jpg';
            case 'image/png':
                return '.png';
            default:
                return '.png';
        }
    }

    private function createZipArchive() {
        $zipPath = $this->tempDir . '/converted_model.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            throw new Exception('Não foi possível criar arquivo ZIP');
        }

        $this->addDirToZip($zip, $this->tempDir, '');
        $zip->close();

        return $zipPath;
    }

    private function addDirToZip($zip, $path, $relativePath) {
        $files = scandir($path);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || pathinfo($file, PATHINFO_EXTENSION) === 'glb') continue;
            
            $filePath = $path . '/' . $file;
            $zipPath = $relativePath . ($relativePath ? '/' : '') . $file;

            if (is_dir($filePath)) {
                $zip->addEmptyDir($zipPath);
                $this->addDirToZip($zip, $filePath, $zipPath);
            } else {
                $zip->addFile($filePath, $zipPath);
            }
        }
    }
} 