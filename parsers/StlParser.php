<?php
require_once __DIR__ . '/IModelParser.php';

class StlParser implements IModelParser {
    private $filePath;
    private $vertices = [];
    private $normals = [];
    
    public function __construct($filePath) {
        $this->filePath = $filePath;
    }

    public function parse() {
        $handle = fopen($this->filePath, 'rb');
        $header = fread($handle, 80);
        
        if ($this->isAscii($header)) {
            $this->parseAscii($handle);
        } else {
            $this->parseBinary($handle);
        }
        
        fclose($handle);
        
        return [
            'vertices' => $this->vertices,
            'normals' => $this->normals,
            'uvs' => [],
            'materials' => [
                ['name' => 'default']
            ],
            'textures' => []
        ];
    }

    private function parseBinary($handle) {
        $triangleCount = unpack('V', fread($handle, 4))[1];
        
        for ($i = 0; $i < $triangleCount; $i++) {
            $normal = unpack('f3', fread($handle, 12));
            $this->normals[] = [$normal[1], $normal[2], $normal[3]];
            
            for ($j = 0; $j < 3; $j++) {
                $vertex = unpack('f3', fread($handle, 12));
                $this->vertices[] = [$vertex[1], $vertex[2], $vertex[3]];
            }
            
            fread($handle, 2); // Pula o atributo
        }
    }

    // Implementação dos métodos getters
    public function getVertices() { return $this->vertices; }
    public function getNormals() { return $this->normals; }
    public function getUVs() { return []; }
    public function getMaterials() { return []; }
    public function getTextures() { return []; }
} 