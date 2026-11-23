<?php
class WatchysRequest
{
    public static function decode($raw)
    {
        if (!is_string($raw) || strlen($raw)>16384) {throw new InvalidArgumentException('Requête trop volumineuse.');}
        try {$input=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(JsonException $e){throw new InvalidArgumentException('Requête JSON invalide.');}
        if(!is_array($input) || array_is_list($input)) {throw new InvalidArgumentException('Objet de configuration attendu.');}
        return $input;
    }
}
