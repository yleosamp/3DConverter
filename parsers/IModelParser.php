<?php
interface IModelParser {
    public function parse();
    public function getVertices();
    public function getNormals();
    public function getUVs();
    public function getMaterials();
    public function getTextures();
} 