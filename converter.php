<?php
require_once 'GlbParser.php';

class ModelConverter {
    private $tempDir;
    private $vertices = [];
    private $normals = [];
    private $uvs = [];
    private $indices = [];
    private $materials = [];
    private $textures = [];
    
    public function __construct() {
        $this->tempDir = __DIR__ . '/temp/' . uniqid();
        if (!file_exists($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
        mkdir($this->tempDir . '/textures');
    }
    
    public function isValidFile($file) {
        return pathinfo($file['name'], PATHINFO_EXTENSION) === 'glb';
    }
    
    public function convert($file) {
        try {
            if (!move_uploaded_file($file['tmp_name'], $this->tempDir . '/model.glb')) {
                throw new Exception('Erro ao mover arquivo');
            }
            
            $parser = new GlbParser($this->tempDir . '/model.glb');
            $glb = $parser->parse();
            
            if (!isset($glb['json']['meshes'])) {
                throw new Exception('Arquivo GLB não contém meshes');
            }
            
            $this->processGLTF($glb);
            $this->extractTextures($glb);
            
            $objContent = $this->generateOBJ();
            $mtlContent = $this->generateMTL();
            
            file_put_contents($this->tempDir . '/model.obj', $objContent);
            file_put_contents($this->tempDir . '/model.mtl', $mtlContent);
            
            return $this->createZipArchive();
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }
    
    private function processGLTF($glb) {
        $json = $glb['json'];
        
        foreach ($json['meshes'] as $mesh) {
            foreach ($mesh['primitives'] as $primitive) {
                if (isset($primitive['attributes']['POSITION'])) {
                    $this->vertices = $this->extractAttribute($primitive['attributes']['POSITION'], $glb);
                }
                if (isset($primitive['attributes']['NORMAL'])) {
                    $this->normals = $this->extractAttribute($primitive['attributes']['NORMAL'], $glb);
                }
                if (isset($primitive['attributes']['TEXCOORD_0'])) {
                    $this->uvs = $this->extractAttribute($primitive['attributes']['TEXCOORD_0'], $glb);
                }
                if (isset($primitive['indices'])) {
                    $this->indices = $this->extractIndices($primitive['indices'], $glb);
                }
            }
        }
    }
    
    private function extractAttribute($accessorIndex, $glb) {
        $json = $glb['json'];
        $accessor = $json['accessors'][$accessorIndex];
        $bufferView = $json['bufferViews'][$accessor['bufferView']];
        $offset = isset($bufferView['byteOffset']) ? $bufferView['byteOffset'] : 0;
        
        $data = substr($glb['binary'], $offset, $bufferView['byteLength']);
        $componentType = $accessor['componentType'];
        $count = $accessor['count'];
        
        $format = 'V';  // default uint32
        if ($componentType === 5126) $format = 'f';  // float32
        else if ($componentType === 5123) $format = 'v';  // uint16
        else if ($componentType === 5121) $format = 'C';  // uint8
        
        $values = [];
        $size = $accessor['type'] === 'VEC3' ? 3 : 2;
        
        for ($i = 0; $i < $count; $i++) {
            $offset = $i * $size * 4;
            $components = unpack($format . $size, substr($data, $offset, $size * 4));
            $values[] = array_values($components);
        }
        
        return $values;
    }
    
    private function extractIndices($accessorIndex, $glb) {
        $json = $glb['json'];
        $accessor = $json['accessors'][$accessorIndex];
        $bufferView = $json['bufferViews'][$accessor['bufferView']];
        $offset = isset($bufferView['byteOffset']) ? $bufferView['byteOffset'] : 0;
        
        $data = substr($glb['binary'], $offset, $bufferView['byteLength']);
        $count = $accessor['count'];
        
        $format = $accessor['componentType'] === 5123 ? 'v' : 'V';
        $indices = [];
        
        for ($i = 0; $i < $count; $i++) {
            $offset = $i * ($format === 'v' ? 2 : 4);
            $index = unpack($format, substr($data, $offset, $format === 'v' ? 2 : 4))[1];
            $indices[] = $index;
        }
        
        return $indices;
    }
    
    private function extractTextures($glb) {
        $json = $glb['json'];
        
        if (!isset($json['images']) || !isset($json['materials'])) {
            return;
        }
        
        foreach ($json['materials'] as $index => $material) {
            $materialData = [
                'name' => "material_{$index}",
                'metallic' => 0.0,
                'roughness' => 1.0
            ];
            
            if (isset($material['pbrMetallicRoughness'])) {
                $pbr = $material['pbrMetallicRoughness'];
                
                // Base Color Texture
                if (isset($pbr['baseColorTexture'])) {
                    $texturePath = $this->extractTextureFromGLB(
                        $glb,
                        $pbr['baseColorTexture']['index'],
                        "diffuse_{$index}"
                    );
                    $materialData['diffuseMap'] = $texturePath;
                }
                
                // Metallic Roughness Texture
                if (isset($pbr['metallicRoughnessTexture'])) {
                    $texturePath = $this->extractTextureFromGLB(
                        $glb,
                        $pbr['metallicRoughnessTexture']['index'],
                        "metallic_{$index}"
                    );
                    $materialData['metallicMap'] = $texturePath;
                }
                
                // Valores fixos de metallic/roughness
                if (isset($pbr['metallicFactor'])) {
                    $materialData['metallic'] = $pbr['metallicFactor'];
                }
                if (isset($pbr['roughnessFactor'])) {
                    $materialData['roughness'] = $pbr['roughnessFactor'];
                }
            }
            
            // Normal Map
            if (isset($material['normalTexture'])) {
                $texturePath = $this->extractTextureFromGLB(
                    $glb,
                    $material['normalTexture']['index'],
                    "normal_{$index}"
                );
                $materialData['normalMap'] = $texturePath;
            }
            
            $this->materials[$index] = $materialData;
        }
    }
    
    private function extractTextureFromGLB($glb, $textureIndex, $prefix) {
        $json = $glb['json'];
        $imageIndex = $json['textures'][$textureIndex]['source'];
        $image = $json['images'][$imageIndex];
        
        if (isset($image['bufferView'])) {
            $bufferView = $json['bufferViews'][$image['bufferView']];
            $offset = isset($bufferView['byteOffset']) ? $bufferView['byteOffset'] : 0;
            $length = $bufferView['byteLength'];
            
            $imageData = substr($glb['binary'], $offset, $length);
            $mimeType = $image['mimeType'];
            $extension = $this->getMimeExtension($mimeType);
            
            $texturePath = "{$prefix}.{$extension}";
            file_put_contents($this->tempDir . '/textures/' . $texturePath, $imageData);
            
            return $texturePath;
        }
        return null;
    }
    
    private function getMimeExtension($mimeType) {
        $types = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];
        return $types[$mimeType] ?? 'png';
    }
    
    private function generateOBJ() {
        $obj = "# Converted from GLB\n";
        $obj .= "mtllib model.mtl\n\n";
        
        // Vertices
        foreach ($this->vertices as $v) {
            $obj .= sprintf("v %.6f %.6f %.6f\n", $v[0], $v[1], $v[2]);
        }
        
        // Texturas UV
        foreach ($this->uvs as $uv) {
            $obj .= sprintf("vt %.6f %.6f\n", $uv[0], 1 - $uv[1]); // Inverte Y para compatibilidade
        }
        
        // Normais
        foreach ($this->normals as $n) {
            $obj .= sprintf("vn %.6f %.6f %.6f\n", $n[0], $n[1], $n[2]);
        }
        
        // Faces com material
        if (!empty($this->materials)) {
            foreach ($this->materials as $matIndex => $material) {
                $obj .= "\nusemtl " . $material['name'] . "\n";
                
                // Gera faces para este material
                for ($i = 0; $i < count($this->indices); $i += 3) {
                    $v1 = $this->indices[$i] + 1;
                    $v2 = $this->indices[$i+1] + 1;
                    $v3 = $this->indices[$i+2] + 1;
                    
                    $obj .= sprintf("f %d/%d/%d %d/%d/%d %d/%d/%d\n",
                        $v1, $v1, $v1,
                        $v2, $v2, $v2,
                        $v3, $v3, $v3
                    );
                }
            }
        } else {
            // Se não houver materiais, gera faces sem material
            for ($i = 0; $i < count($this->indices); $i += 3) {
                $v1 = $this->indices[$i] + 1;
                $v2 = $this->indices[$i+1] + 1;
                $v3 = $this->indices[$i+2] + 1;
                
                $obj .= sprintf("f %d/%d/%d %d/%d/%d %d/%d/%d\n",
                    $v1, $v1, $v1,
                    $v2, $v2, $v2,
                    $v3, $v3, $v3
                );
            }
        }
        
        return $obj;
    }
    
    private function generateMTL() {
        $mtl = "# Material file for model.obj\n\n";
        
        foreach ($this->materials as $index => $material) {
            $mtl .= "newmtl " . $material['name'] . "\n";
            $mtl .= "Ka 1.000000 1.000000 1.000000\n";
            $mtl .= "Kd 1.000000 1.000000 1.000000\n";
            $mtl .= "Ks 0.000000 0.000000 0.000000\n";
            
            // Metallic e Roughness
            $mtl .= sprintf("Pm %.6f\n", $material['metallic']); // Metallic
            $mtl .= sprintf("Pr %.6f\n", $material['roughness']); // Roughness
            
            // Mapas de textura
            if (isset($material['diffuseMap'])) {
                $mtl .= "map_Kd textures/" . $material['diffuseMap'] . "\n";
            }
            if (isset($material['metallicMap'])) {
                $mtl .= "map_Pm textures/" . $material['metallicMap'] . "\n";
            }
            if (isset($material['normalMap'])) {
                $mtl .= "map_bump textures/" . $material['normalMap'] . "\n";
                $mtl .= "norm textures/" . $material['normalMap'] . "\n";
            }
            
            $mtl .= "\n";
        }
        
        return $mtl;
    }
    
    private function createZipArchive() {
        $zipPath = $this->tempDir . '/converted_model.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
            throw new Exception('Não foi possível criar o arquivo ZIP');
        }
        
        // Adiciona OBJ e MTL
        $zip->addFile($this->tempDir . '/model.obj', 'model.obj');
        $zip->addFile($this->tempDir . '/model.mtl', 'model.mtl');
        
        // Adiciona texturas
        $textures = glob($this->tempDir . '/textures/*');
        foreach ($textures as $texture) {
            $zip->addFile($texture, 'textures/' . basename($texture));
        }
        
        $zip->close();
        return $zipPath;
    }
    
    public function __destruct() {
        if (file_exists($this->tempDir)) {
            $files = glob($this->tempDir . '/{,*/}*', GLOB_BRACE);
            foreach ($files as $file) {
                if (is_file($file)) unlink($file);
            }
            rmdir($this->tempDir . '/textures');
            rmdir($this->tempDir);
        }
    }
} 