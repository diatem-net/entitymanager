<?php

namespace Diatem\EntityManager;

use \Diatem\RestServer\RestException;

class SpecificException extends RestException{
    public function __construct($internalCode, $infos = null){
        if(!isset($GLOBALS['erreurs'][$internalCode])){
            throw new RestException(500, 'Code interne d\'erreur inexistant : '.$internalCode, null);
        }
        if(!$infos){
            $infos = '';
        }else{
            $infos = ' : '.$infos;
        }
        parent::__construct($GLOBALS['erreurs'][$internalCode]['http'], $GLOBALS['erreurs'][$internalCode]['label'].$infos, $internalCode);
    }
}