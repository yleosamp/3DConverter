<?php
class GlbParser {
    private $data;
    private $offset = 0;
    
    public function __construct($filePath) {
        $this->data = file_get_contents($filePath);
    }
    
    public function parse() {
        $header = $this->parseHeader();
        $chunks = $this->parseChunks();
        
        return [
            'json' => json_decode($chunks['JSON'], true),
            'binary' => $chunks['BIN']
        ];
    }
    
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
} 