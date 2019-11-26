<?php

namespace Diatem\EntityManager;

use Jin2\Db\Query\Query;
use Jin2\Db\Database\DbConnexion;
use Jin2\Utils\StringTools;
use \Diatem\RestServer\RestLog;

class EntityQuery{

    private $definition;

    public function __construct($entityName = null, $entityDefinition = null){
        if($entityName){
            $this->definition = new EntityDefinition($entityName);
        }else if($entityDefinition){
            $this->definition = $entityDefinition;
        }else{
            throw new Exception('Impossible d\'initialiser un objet EntityQuery: le nom de l\'entité ou un objet définition doit être transmis');
        }
    }

    public function executeSelectX($fields = '*', $orderBy = 'id', $ordertype = 'ASC', $limit = 20, $offset = 0, $from = null, $fromFieldName = null, $jointures = array(), $conditions = array()){
        if(!is_array($jointures)){
            $jointures = array();
        }

        if(!is_array($conditions)){
            $conditions = array();
        }                       

        $q = new Query();
        $q->setRequest('SELECT 
        COUNT(*) OVER() AS total, 
        '.$this->determineSqlFields($fields, true).'
        FROM
        '.$this->definition->getTableName());
        foreach($jointures AS $join){
            $q->addToRequest($join->getJoinType().' '.$join->getTableName());
            if($join->getAlias()){
                $q->addToRequest('AS '.$join->getAlias());
            }
            $q->addToRequest('ON '.$join->getFromDbField() . '='.$join->getToDbField());
            foreach($join->getConditions() AS $condition){
                $q = $condition->apply($q, $this->definition);
            }
        }
        $q->addToRequest('WHERE 1=1');


        foreach($conditions AS $condition){
            $q = $condition->apply($q, $this->definition);
        }
        if($from && $fromFieldName){
            $q->addToRequest('AND '.$this->definition->getDdbFieldNameFromAttribute($fromFieldName).'>'.$from);
        }else{
            

            $newValues = array();
            $orderValues = explode(',',$orderBy);
            foreach($orderValues as $value){
                $newValues[] = $this->definition->getDdbFieldNameFromAttribute($value);
            }  
            $orderBy = implode(',',$newValues);
            $q->addToRequest('ORDER BY '.$orderBy.' '.$ordertype.' LIMIT '.$limit.' OFFSET '.$offset);
            
        }
        RestLog::addLog('Query : '.$q->getSql());
        $q->execute();

        $qr = $q->getQueryResults();

        if($qr->count() == 0){
            return array('datas' => array(), 'total' => 0, 'count' => 0);
        }else{
            return array('datas' => $qr, 'total' => $qr->getValueAt('total', 0), 'count' => $qr->count());
        }
    }

    public function executeSelect($primaryKeyValue, $primaryKeyName = null, $fields = '*', $jointures = array(), $conditions = array()){
        if(!is_array($jointures)){
            $jointures = array();
        }

        if(!is_array($conditions)){
            $conditions = array();
        }

        if($primaryKeyName){
            $primaryKeyName = $this->definition->getDdbFieldNameFromAttribute($primaryKeyName);
        }else{
            $primaryKeyName = $this->definition->getPrimaryDbKey();
        }

        $q = new Query();
        $q->setRequest('SELECT '.$this->determineSqlFields($fields, true).'
        FROM
        '.$this->definition->getTableName());
        foreach($jointures AS $join){
            $q->addToRequest($join->getJoinType().' '.$join->getTableName());
            if($join->getAlias()){
                $q->addToRequest('AS '.$join->getAlias());
            }
            $q->addToRequest('ON '.$join->getFromDbField() . '='.$join->getToDbField());
        }
        $q->addToRequest('WHERE '.$primaryKeyName.'=:primaryKeyValue');
        foreach($conditions AS $condition){
            $q = $condition->apply($q, $this->definition);
        }

        if(is_string($primaryKeyValue)){
            $q->argument($primaryKeyValue, Query::SQL_STRING, 'primaryKeyValue');
        }else{
            $q->argument($primaryKeyValue, Query::SQL_NUMERIC, 'primaryKeyValue');
        }

        RestLog::addLog('Query : '.$q->getSql());
        $q->execute();

        $qr = $q->getQueryResults();

        if($qr->count() != 1){
            throw new Exception('Ressource inexistante'. $this->definition->getTableName().'::'.$primaryKeyName.'('.$primaryKeyValue.')');
        }

        return $qr;
    }

    public function executeInsert($datas, $primaryKeyName = null){
        if($primaryKeyName){
            $primaryKey = $this->definition->getDdbFieldNameFromAttribute($primaryKeyName);
        }else{
            $primaryKey = $this->definition->getPrimaryDbKey();
        }

        $q = new Query();
        $q->setRequest('INSERT INTO '.$this->definition->getTableName().' 
        ');

        $ok = false;
        $def = $this->definition->getDefinition();
        foreach($def AS $sql => $attribut){
            if(isset($datas[$attribut])){
                if($datas[$attribut] != null){ 
                    $ok = true;
                }
            }
        }

        if($ok){
            $first = true;
            $q->addToRequest('(');
            foreach($def AS $sql => $attribut){
                if(isset($datas[$attribut])){
                    if($datas[$attribut] != null){ 
                        if(!$first){
                            $q->addToRequest(',');
                        }
                        $q->addToRequest($sql);
                        $first = false;
                    }
                    
                    
                }
            }
            $first = true;
            $q->addToRequest(' ) VALUES (');
            foreach($def AS $sql => $attribut){
                if(isset($datas[$attribut])){
                    if($datas[$attribut] != null){
                        
                        if(!$first){
                            $q->addToRequest(',');
                        }
                        $q->addToRequest(':'.$attribut);

                        if(is_string($datas[$attribut])){
                            $q->argument($datas[$attribut], Query::SQL_STRING, $attribut);
                        }else if(is_numeric($datas[$attribut])){
                            $q->argument($datas[$attribut], Query::SQL_NUMERIC, $attribut);
                        }else if(is_bool($datas[$attribut])){
                            $q->argument($datas[$attribut], Query::SQL_BOOL, $attribut);
                        }
                        $first = false;
                    }
                }
            }
            $q->addToRequest(')');
        }else{
            $q->addToRequest(' DEFAULT VALUES');
        }
        RestLog::addLog('Query : '.$q->getSql());
        $q->execute();
        return DbConnexion::getLastInsertId($this->definition->getTableName(), $primaryKey);
    }


    public function executeUpdate($datas, $primaryKeyValue, $primaryKeyName = null){
        if($primaryKeyName){
            $primaryKeyName = $this->definition->getDdbFieldNameFromAttribute($primaryKeyName);
        }else{
            $primaryKeyName = $this->definition->getPrimaryDbKey();
        }
        
        $q = new Query();
        $q->setRequest('UPDATE '.$this->definition->getTableName(). ' SET ');
        $first = true;

        $def = $this->definition->getDefinition();
        foreach($def AS $sql => $attribut){
            if(isset($datas[$attribut])){
                if($datas[$attribut] != null){ 
                    if(!$first){
                        $q->addToRequest(',');
                    }
                    $q->addToRequest($sql.'=:'.$attribut);
                    if(is_string($datas[$attribut])){
                        $q->argument($datas[$attribut], Query::SQL_STRING, $attribut);
                    }else if(is_numeric($datas[$attribut])){
                        $q->argument($datas[$attribut], Query::SQL_NUMERIC, $attribut);
                    }else if(is_bool($datas[$attribut])){
                        $q->argument($datas[$attribut], Query::SQL_BOOL, $attribut);
                    }
                    $first = false;
                }
            }
        }
        $q->addToRequest('WHERE '.$primaryKeyName.'=:keyvalue');
        if(is_string($primaryKeyValue)){
            $q->argument($primaryKeyValue, Query::SQL_STRING, 'keyvalue');
        }else if(is_numeric($primaryKeyValue)){
            $q->argument($primaryKeyValue, Query::SQL_NUMERIC, 'keyvalue');
        }
        RestLog::addLog('Query : '.$q->getSql());
        $q->execute();
        
    }

    public function  determineSqlFields($fields = '*', $sqlFormat = true){
        if($fields == '*'){
            $fields =  $this->definition->getAttributesList();
        }else{
            $fields = StringTools::explode($fields,',');
        }

        $intersect = array_intersect($this->definition->getDefinition(), $fields);
        if($sqlFormat){
            $out = '';
            $first = true;
            foreach($intersect AS $fieldName => $restName){
                if(!$first){
                    $out .= ", \n";
                }
                $out .= $fieldName.' AS '.$restName;
                $first = false;
            }
            return $out;
        }else{
            return $intersect;
        }
    }

    
}