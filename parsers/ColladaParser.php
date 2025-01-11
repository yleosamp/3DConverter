<?php
require_once 'IModelParser.php';

class ColladaParser implements IModelParser {
    private $filePath;
    private $xml;
    private $vertices = [];
    private $normals = [];
    private $uvs = [];
    private $materials = [];
    private $textures = [];

    public function __construct($filePath) {
        $this->filePath = $filePath;
        $this->xml = simplexml_load_file($filePath);
        if (!$this->xml) {
            throw new Exception('Erro ao carregar arquivo COLLADA');
        }
    }

    public function parse() {
        $geometries = $this->xml->library_geometries->geometry;
        foreach ($geometries as $geometry) {
            $this->extractGeometry($geometry);
        }

        $materials = $this->xml->library_materials->material;
        foreach ($materials as $material) {
            $this->extractMaterial($material);
        }

        return [
            'vertices' => $this->vertices,
            'normals' => $this->normals,
            'uvs' => $this->uvs,
            'materials' => $this->materials,
            'textures' => $this->textures
        ];
    }

    // Implementação dos métodos getters
    public function getVertices() { return $this->vertices; }
    public function getNormals() { return $this->normals; }
    public function getUVs() { return $this->uvs; }
    public function getMaterials() { return $this->materials; }
    public function getTextures() { return $this->textures; }
} 