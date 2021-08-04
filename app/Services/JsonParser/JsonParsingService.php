<?php

namespace App\Services\JsonParser;

use \JsonMachine\JsonMachine;

class JsonParsingService
{
    protected $file;

    public function __construct($path)
    {
        $this->setFile($path);
    }

    public function parse()
    {
        $file = $this->getFile();
        $items = JsonMachine::fromFile($file, '/sessions');
        foreach ($items as $name => $data) {
            dump($data);
        }
    }

    private function setFile($path)
    {
        if (file_exists($path)) {
            $this->file = $path;
        } else {
            die('File not found');
        }
    }

    private function getFile()
    {
        return $this->file;
    }
}