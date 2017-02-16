<?php

namespace M2Max\CSSManager;

/**
 * Created by PhpStorm.
 * User: Maxime Maillet
 * Date: 17/02/2017
 * Time: 00:10
 */
class CSSManager
{
    public static function init($css_files) {

        $root_path = dirname($_SERVER['SCRIPT_FILENAME']);
        $root_url = dirname($_SERVER['SCRIPT_NAME']);
        $css = '';

        foreach ($css_files as $file) {
            $css .= file_get_contents($root_path.'/'.$file);
        }

        file_put_contents($root_path.'/main.all.css', $css);
        return $root_url.'/main.all.css';
    }
}