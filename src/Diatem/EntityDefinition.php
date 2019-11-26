<?php

namespace Diatem\EntityManager;

use Diatem\EntityManager\SpecificException;

class EntityDefinition{
    private $definition;
    private $outputAppend;
    private $definitionReverse;
    private $addRequirements;
    private $updateRequirements;
    private $tableName;
    private $primaryKey;
    private $className;
    private $ignoreInOutput;

    public function __construct($className){
        $this->definition = $className::DEF_ATTRIBUTS;
        $this->className = $className;
        $this->outputAppend = array();
        foreach($className::DEF_ATTRIBUTS_APPEND AS $r){
            $this->outputAppend[$r] = null;
        }
        $this->definitionReverse = array_flip($this->definition);
        $this->tableName = $className::DEF_TABLE;
        $this->primaryKey = $className::DEF_PRIMARYKEY;
        $this->addRequirements = $className::DEF_ATTRIBUTS_ADDREQS;
        $this->updateRequirements = $className::DEF_ATTRIBUTS_UPDATEREQS;
        $this->ignoreInOutput = $className::DEF_ATTRIBUTS_IGNOREINOUTPUT;
    }

    public function getAttributeFromDdbFieldName($ddbFieldName, $nullIfUndefined = false){
        if(!isset($this->definition[$ddbFieldName])){
            if($nullIfUndefined){
                return null;
            }
            throw new SpecificException('G002', $ddbFieldName);
        }
        return $this->definition[$ddbFieldName];
    }

    public function getIgnoreInOutput(){
        return $this->ignoreInOutput;
    }

    public function getDdbFieldNameFromAttribute($ddbFieldName, $nullIfUndefined = false){
        if(!isset($this->definitionReverse[$ddbFieldName])){
            if($nullIfUndefined){
                return null;
            }
            throw new SpecificException('G002', $ddbFieldName);
        }
        return $this->definitionReverse[$ddbFieldName];
    }

    public function getTableName(){
        return $this->tableName;
    }

    public function getDefinition(){
        return $this->definition;
    }

    public function getReverseDefinition(){
        return $this->definitionReverse;
    }

    public function getOutputAppend(){
        return $this->outputAppend;
    }

    public function getAttributesList(){
        return array_values($this->definition);
    }

    public function getSqlFieldsList(){
        return array_values($this->definitionReverse);
    }

    public function getPrimaryAttributeKey(){
        return $this->primaryKey;
    }

    public function getPrimaryDbKey(){
        return $this->getDdbFieldNameFromAttribute($this->getPrimaryAttributeKey());
    }

    public function checkAddRequirements($datas){
        foreach($this->addRequirements AS $r){
            if(!isset($datas[$r]) || $datas[$r] === null){
                throw new SpecificException('G005', $this->className.'::'.$r);
            }
        }
    }

    public function checkUpdateRequirements($datas){
        foreach($this->updateRequirements AS $r){
            if(!isset($datas[$r]) || $datas[$r] == null || $datas[$r] == ''){
                throw new SpecificException('G005', $this->className.'::'.$r);
            }
        }
    }

    public function prepareDatas($datas){
        $out = array();
        foreach($this->definitionReverse AS $attribut => $dbName){
            if(isset($datas[$attribut])){
                $out[$attribut] = $datas[$attribut];
            }else{
                $out[$attribut] = null;
            }
        }

        return $out;
    }
}