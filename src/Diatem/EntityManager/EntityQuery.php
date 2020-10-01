<?php

namespace Diatem\EntityManager;

use Jin2\Db\Query\Query;
use Jin2\Db\Database\DbConnexion;
use Jin2\Utils\StringTools;
use Jin2\Utils\ListTools;
use \Diatem\RestServer\RestLog;
use Diatem\EntityManager\SpecificException;

class EntityQuery{

    private $definition;
    const VAL_NULL = 1.123456789;

    public function __construct($entityName = null, $entityDefinition = null){
        if($entityName){
            $this->definition = new EntityDefinition($entityName);
        }else if($entityDefinition){
            $this->definition = $entityDefinition;
        }else{
            throw new SpecificException('G006');
        }
    }

    public function executeSelectX($orderBy = 'id', $ordertype = 'ASC', $limit = 20, $offset = 0, $from = null, $fromFieldName = null, $jointures = array(), $conditions = array()){
        if(!is_array($jointures)){
            $jointures = array();
        }

        if(!is_array($conditions)){
            $conditions = array();
        }

        $query = new Query();
        $query->setRequest('SELECT DISTINCT COUNT(DISTINCT ' . $this->definition->getTableName() . '.' . $this->definition->getPrimaryDbKey() . ') FROM '.$this->definition->getTableName());
        foreach($jointures AS $join){
            $query->addToRequest($join->getJoinType().' '.$join->getTableName());
            if($join->getAlias()){
                $query->addToRequest('AS '.$join->getAlias());
            }
            $query->addToRequest('ON '.$join->getFromDbField() . '='.$join->getToDbField());
            foreach($join->getConditions() AS $condition){
                $query = $condition->apply($query, $this->definition);
            }
        }
        $query->addToRequest('WHERE 1=1');


        foreach($conditions AS $condition){
            $query = $condition->apply($query, $this->definition);
        }

        if($from && $fromFieldName){
            $query->addToRequest('AND '.$this->definition->getDdbFieldNameFromAttribute($fromFieldName).'>'.$from);
        }

        $q = new Query();
        $q->setRequest('SELECT DISTINCT 
        ( '.$query->getSql().' ) AS total, 
        '.$this->determineSqlFields(true).'
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
        }

        if($orderBy){
            if($orderBy == '#RANDOM'){
                $q->addToRequest('ORDER BY RANDOM() ');
            }else{
                $q->addToRequest('ORDER BY '.$this->definition->getTableName().'.'.$this->definition->getDdbFieldNameFromAttribute($orderBy).' '.$ordertype.' ');
            }
        }

        if(is_numeric($limit)){
            $q->addToRequest('LIMIT '.$limit.' OFFSET '.$offset);
        }

        RestLog::addLog('Query : '.$q->getSql());

        $q->execute();

        $qr = $q->getQueryResults();

        if($qr->count() == 0){
            return array('data' => array(), 'total' => 0, 'count' => 0);
        }else{
            return array('data' => $qr, 'total' => $qr->getValueAt('total', 0), 'count' => $qr->count());
        }
    }

    public function executeSelect($primaryKeyValue, $primaryKeyName = null, $jointures = array(), $conditions = array(), $throwing = true, $error = 'G001'){
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
        $q->setRequest('SELECT '.$this->determineSqlFields(true).'
        FROM
        '.$this->definition->getTableName());
        foreach($jointures AS $join){
            $q->addToRequest(' 
            '.$join->getJoinType().' '.$join->getTableName());
            if($join->getAlias()){
                $q->addToRequest('AS '.$join->getAlias());
            }
            $q->addToRequest('ON '.$join->getFromDbField() . '='.$join->getToDbField());
            foreach($join->getConditions() AS $condition){
                $q = $condition->apply($q, $this->definition);
            }
        }
        if (!is_null($primaryKeyValue)) {
            $q->addToRequest('WHERE '.$primaryKeyName.'=:primaryKeyValue');
        } else {
            $q->addToRequest('WHERE 1=1');
        }
        foreach($conditions AS $condition){
            $q = $condition->apply($q, $this->definition);
        }

        if (!is_null($primaryKeyValue)) {
            if(is_numeric($primaryKeyValue)){
                $q->argument($primaryKeyValue, Query::SQL_NUMERIC, 'primaryKeyValue');
            }else {
                $q->argument($primaryKeyValue, Query::SQL_STRING, 'primaryKeyValue');
            }
        }
        
        

        RestLog::addLog('Query : '.$q->getSql());
        $q->execute();

        $qr = $q->getQueryResults();

        if($qr->count() != 1 && $throwing){
            throw new SpecificException($error, $this->definition->getTableName().'::'.$primaryKeyName.'('.$primaryKeyValue.')');
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
                        if(ListTools::len($sql, '.') == 2){
                            $sql = ListTools::last($sql, '.');
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

        return DbConnexion::getLastInsertId($this->definition->getTableName(),$primaryKey);
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
                if($datas[$attribut] == self::VAL_NULL){
                    if(!$first){
                        $q->addToRequest(',');
                    }

                    $q->addToRequest($sql.'= NULL');
                    $first = false;
                }else if($datas[$attribut] == -1 && StringTools::len($attribut) >= 8 && StringTools::left($attribut, 8) == 'id_image'){
                    if(!$first){
                        $q->addToRequest(',');
                    }

                    $q->addToRequest($sql.'= NULL');
                    $first = false;
                }else if($datas[$attribut] != null){ 
                    if(!$first){
                        $q->addToRequest(',');
                    }

                    if(ListTools::len($sql, '.') == 2){
                        $sql = ListTools::last($sql, '.');
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

    public function  determineSqlFields($sqlFormat = true){
        $fields =  $this->definition->getAttributesList();
        $intersect = array_intersect($this->definition->getDefinition(), $fields);
        if($sqlFormat){
            $out = '';
            $first = true;
            foreach($intersect AS $fieldName => $restName){
                if(!$first){
                    $out .= ", \n";
                }
                $out .= $this->definition->getTableName().'.'.$fieldName.' AS '.$restName;
                $first = false;
            }
            return $out;
        }else{
            return $intersect;
        }
    }

    
}