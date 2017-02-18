<?php

namespace M2Max\CSSManager;

abstract class IKant
{
    protected static $instance;

    protected $data = [];

    public function __set($name, $value) {
        if(array_key_exists($name, $this->data)) {
            $this->data[$name] = $value;
        }
    }

    public function data() {
        return $this->data;
    }

    public abstract static function Kanter();
    public abstract static function generate($data);
    public abstract function css();
    public abstract function js();
}
