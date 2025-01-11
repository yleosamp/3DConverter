<?php
require_once 'IModelParser.php';

class FbxParser implements IModelParser {
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
        if (!$header['valid']) {
            throw new Exception('Arquivo FBX inválido');
        }
        
        $this->parseNodes();
        
        return [
            'vertices' => $this->vertices,
            'normals' => $this->normals,
            'uvs' => $this->uvs,
            'materials' => $this->materials,
            'textures' => $this->textures
        ];
    }
    
    private function parseHeader() {
        $magic = substr($this->data, 0, 21);
        $version = unpack('L', substr($this->data, 23, 4))[1];
        
        return [
            'valid' => $magic === "Kaydara FBX Binary\x20\x20\x00\x1a\x00",
            'version' => $version
        ];
    }
    
    private function parseNodes() {
        $this->offset = 27; // Após o cabeçalho
        
        while ($this->offset < strlen($this->data)) {
            $endOffset = unpack('L', substr($this->data, $this->offset, 4))[1];
            $numProperties = unpack('L', substr($this->data, $this->offset + 4, 4))[1];
            $propertyListLen = unpack('L', substr($this->data, $this->offset + 8, 4))[1];
            $nameLen = unpack('c', substr($this->data, $this->offset + 12, 1))[1];
            $name = substr($this->data, $this->offset + 13, $nameLen);
            
            $this->offset += 13 + $nameLen;
            
            if ($name === 'Vertices') {
                $this->vertices = $this->parseVertexData();
            } elseif ($name === 'Normals') {
                $this->normals = $this->parseVertexData();
            } elseif ($name === 'UV') {
                $this->uvs = $this->parseUVData();
            } elseif ($name === 'Material') {
                $this->parseMaterial();
            }
            
            $this->offset = $endOffset;
        }
    }
    
    private function parseVertexData() {
        $count = unpack('L', substr($this->data, $this->offset, 4))[1];
        $this->offset += 4;
        
        $vertices = [];
        for ($i = 0; $i < $count; $i += 3) {
            $vertices[] = [
                unpack('f', substr($this->data, $this->offset + $i * 4, 4))[1],
                unpack('f', substr($this->data, $this->offset + ($i + 1) * 4, 4))[1],
                unpack('f', substr($this->data, $this->offset + ($i + 2) * 4, 4))[1]
            ];
        }
        
        return $vertices;
    }
    
    private function parseUVData() {
        $count = unpack('L', substr($this->data, $this->offset, 4))[1];
        $this->offset += 4;
        
        $uvs = [];
        for ($i = 0; $i < $count; $i += 2) {
            $uvs[] = [
                unpack('f', substr($this->data, $this->offset + $i * 4, 4))[1],
                unpack('f', substr($this->data, $this->offset + ($i + 1) * 4, 4))[1]
            ];
        }
        
        return $uvs;
    }
    
    // Implementação dos métodos getters
    public function getVertices() { return $this->vertices; }
    public function getNormals() { return $this->normals; }
    public function getUVs() { return $this->uvs; }
    public function getMaterials() { return $this->materials; }
    public function getTextures() { return $this->textures; }
} 