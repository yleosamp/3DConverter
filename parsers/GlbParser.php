<?php
require_once __DIR__ . '/IModelParser.php';

class GlbParser implements IModelParser {
    private $data;
    private $offset = 0;
    private $vertices = [];
    private $normals = [];
    private $uvs = [];
    private $materials = [];
    private $textures = [];
    
    public function __construct($filePath) {
        if (!file_exists($filePath)) {
            throw new Exception('Arquivo não encontrado');
        }
        $this->data = file_get_contents($filePath);
        if (!$this->data) {
            throw new Exception('Erro ao ler arquivo');
        }
    }
    
    public function parse() {
        $header = $this->parseHeader();
        $chunks = $this->parseChunks();
        
        $glb = [
            'json' => json_decode($chunks['JSON'], true),
            'binary' => $chunks['BIN']
        ];

        $this->extractGeometry($glb);
        $this->extractMaterials($glb);
        
        return [
            'vertices' => $this->vertices,
            'normals' => $this->normals,
            'uvs' => $this->uvs,
            'materials' => $this->materials,
            'textures' => $this->textures
        ];
    }

    private function extractGeometry($glb) {
        if (!isset($glb['json']['meshes'])) {
            return;
        }

        foreach ($glb['json']['meshes'] as $mesh) {
            if (!isset($mesh['primitives'])) continue;

            foreach ($mesh['primitives'] as $primitive) {
                if (!isset($primitive['attributes'])) continue;

                $attributes = $primitive['attributes'];
                
                // Extrai vértices
                if (isset($attributes['POSITION'])) {
                    $this->vertices = $this->extractBufferData(
                        $glb,
                        $attributes['POSITION'],
                        3
                    );
                }

                // Extrai normais
                if (isset($attributes['NORMAL'])) {
                    $this->normals = $this->extractBufferData(
                        $glb,
                        $attributes['NORMAL'],
                        3
                    );
                }

                // Extrai UVs
                if (isset($attributes['TEXCOORD_0'])) {
                    $this->uvs = $this->extractBufferData(
                        $glb,
                        $attributes['TEXCOORD_0'],
                        2
                    );
                }
            }
        }
    }

    private function extractMaterials($glb) {
        if (!isset($glb['json']['materials'])) {
            return;
        }

        foreach ($glb['json']['materials'] as $index => $material) {
            $mat = [
                'name' => $material['name'] ?? "material_$index",
                'pbrMetallicRoughness' => $material['pbrMetallicRoughness'] ?? null,
                'normalTexture' => $material['normalTexture'] ?? null,
                'emissiveTexture' => $material['emissiveTexture'] ?? null,
                'occlusionTexture' => $material['occlusionTexture'] ?? null
            ];

            $this->materials[] = $mat;

            // Extrai texturas
            $this->extractTextures($material, $glb);
        }
    }

    private function extractTextures($material, $glb) {
        if (isset($material['pbrMetallicRoughness'])) {
            $pbr = $material['pbrMetallicRoughness'];
            
            if (isset($pbr['baseColorTexture'])) {
                $this->extractTextureData($glb, $pbr['baseColorTexture']);
            }
            
            if (isset($pbr['metallicRoughnessTexture'])) {
                $this->extractTextureData($glb, $pbr['metallicRoughnessTexture']);
            }
        }
        
        if (isset($material['normalTexture'])) {
            $this->extractTextureData($glb, $material['normalTexture']);
        }
    }

    private function extractTextureData($glb, $textureInfo) {
        $texture = $glb['json']['textures'][$textureInfo['index']];
        $image = $glb['json']['images'][$texture['source']];
        
        if (isset($image['bufferView'])) {
            $bufferView = $glb['json']['bufferViews'][$image['bufferView']];
            $data = substr($glb['binary'], $bufferView['byteOffset'], $bufferView['byteLength']);
            
            $this->textures[] = [
                'index' => $textureInfo['index'],
                'data' => $data,
                'mimeType' => $image['mimeType'] ?? 'image/png'
            ];
        }
    }

    private function extractBufferData($glb, $accessorIndex, $size) {
        $accessor = $glb['json']['accessors'][$accessorIndex];
        $bufferView = $glb['json']['bufferViews'][$accessor['bufferView']];
        $buffer = $glb['binary'];

        $count = $accessor['count'];
        $offset = $bufferView['byteOffset'] ?? 0;
        $stride = $bufferView['byteStride'] ?? 0;

        $data = [];
        for ($i = 0; $i < $count; $i++) {
            $values = [];
            for ($j = 0; $j < $size; $j++) {
                $byteOffset = $offset + ($i * ($stride ?: $size * 4)) + ($j * 4);
                $values[] = unpack('f', substr($buffer, $byteOffset, 4))[1];
            }
            $data[] = $values;
        }

        return $data;
    }

    // Métodos existentes
    private function parseHeader() {
        $magic = $this->readUint32();
        if ($magic !== 0x46546C67) {
            throw new Exception('Arquivo GLB inválido');
        }
        
        $version = $this->readUint32();
        $length = $this->readUint32();
        
        return [
            'version' => $version,
            'length' => $length
        ];
    }

    private function parseChunks() {
        $chunks = [];
        
        while ($this->offset < strlen($this->data)) {
            $chunkLength = $this->readUint32();
            $chunkType = $this->readUint32();
            
            if ($chunkLength <= 0) break;
            
            $chunkData = substr($this->data, $this->offset, $chunkLength);
            $this->offset += $chunkLength;
            
            if ($chunkType === 0x4E4F534A) { // JSON
                $chunks['JSON'] = $chunkData;
            } elseif ($chunkType === 0x004E4942) { // BIN
                $chunks['BIN'] = $chunkData;
            }
        }
        
        return $chunks;
    }

    private function readUint32() {
        if ($this->offset + 4 > strlen($this->data)) {
            return 0;
        }
        $value = unpack('V', substr($this->data, $this->offset, 4))[1];
        $this->offset += 4;
        return $value;
    }

    // Implementação dos métodos da interface
    public function getVertices() { return $this->vertices; }
    public function getNormals() { return $this->normals; }
    public function getUVs() { return $this->uvs; }
    public function getMaterials() { return $this->materials; }
    public function getTextures() { return $this->textures; }
} 