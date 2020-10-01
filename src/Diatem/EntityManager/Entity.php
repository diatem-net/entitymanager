<?php

namespace Diatem\EntityManager;

use \Diatem\RestServer\RestLog;
use Jin2\Db\Query\Query;
use Diatem\EntityManager\SpecificException;

class Entity{
    
    public static function checkUnicite($keys, $datas){
        $c = get_called_class();
        $definition = new EntityDefinition($c);

        $q = new Query();
        $q->setRequest('SELECT FROM '.$definition->getTableName() . ' WHERE 1=1 ');
        foreach($keys AS $key){
            $q->addToRequest('AND '.$definition->getDdbFieldNameFromAttribute($key).' = '.$q->argument($datas[$key], Query::SQL_STRING));
        }
        RestLog::addLog($q->getSql());
        $q->execute();
        if($q->getResultsCount() > 0){
            throw new SpecificException('G007');
        }
    }


    public static function _post($datas){
        $c = get_called_class();
        $definition = new EntityDefinition($c);
        $definition->checkAddRequirements($datas);
        
        $query = new EntityQuery(null, $definition);

        $id = $query->executeInsert($datas);
        $obj = new $c($id, $key = 'id', $definition, null);

        $out = array(
            'data'    =>  $obj->output()
        );

        return $out;
    }

    public static function _getx($orderby = 'id', $ordertype = 'ASC', $limit = 20, $offset = 0, $from = 0, $fromFieldName = null, $jointures = array(), $conditions = array()){

        $c = get_called_class();
        $definition = new EntityDefinition($c);
        $query = new EntityQuery(null, $definition);

        $datas = $query->executeSelectX($orderby, $ordertype, $limit, $offset, $from, $fromFieldName, $jointures, $conditions);

        $out = array(
            'count'         =>  $datas['count'],
            'total'         =>  $datas['total'],
            'offset'        =>  $offset,
            'results'       =>  array()
        );

        foreach($datas['data'] AS $r){
            $obj = new $c( null, 'id', $definition, $r);
            $out['results'][] = $obj->output();
        }

        return $out;
    }

    

    //-------------------------------------------------------------------------------

    protected $datas;
    protected $definition;
    protected $key;

    public function __construct( $id = null, $key = 'id', $entityDefinition = null, $datas = null, $jointures = array(), $conditions = array(), $throwing = true, $error = 'G001'){
        $this->key = $key;

        if($entityDefinition){
            $this->definition = $entityDefinition;
        }else{
             $c = get_called_class();
             $this->definition = new EntityDefinition($c);
        }

        if(!$datas){
            $query = new EntityQuery(null, $this->definition);   
            $this->datas = $query->executeSelect($id, $key, $jointures, $conditions, $throwing, $error);

        }else{
            $this->datas = $datas;
        }
    }

   
   
    public function _put($datas){
        $query = new EntityQuery(null, $this->definition);   
        $query->executeUpdate($datas, $this->getId());

        $c = get_called_class();
        $obj = new $c( $this->getId(), 'id');

        $out = array(
            'data'    =>  $obj->output()
        );

        return $out;
    }

    public function _patch($datas){
        $query = new EntityQuery(null, $this->definition);   
        $query->executeUpdate($datas, $this->getId());

        $c = get_called_class();
        $obj = new $c( $this->getId(), 'id', new EntityDefinition($c));

        $out = array(
            'data'    =>  $obj->output()
        );

        return $out;
    }

    public function _delete(){
        $q = new Query();
        $q->setRequest('DELETE FROM '.$this->definition->getTableName().' WHERE '.$this->definition->getPrimaryDbKey().'=:id');
        $q->argument($this->getId(), Query::SQL_NUMERIC, 'id');
        RestLog::addLog($q->getSql());
        $q->execute();

        return array();
    }

    public function _up($field = 'ordre', $condition = null){
        if($this->getValue($field) == 1){
            throw new SpecificException('G003');
        }

        $fieldOrdre = $this->definition->getDdbFieldNameFromAttribute($field);

        $q = new Query();
        $q->setRequest('UPDATE 
        '.$this->definition->getTableName().'
        SET '.$fieldOrdre.'='.$fieldOrdre.'+1 
        WHERE '.$fieldOrdre.'=:ordre');
        if($condition){
            $q->addToRequest('AND '.$condition);
        }
        $q->argument($this->getValue($field)-1, Query::SQL_NUMERIC, 'ordre');
        RestLog::addLog('Query : '.$q->getSql());
        $q->execute();

        $q = new Query();
        $q->setRequest('UPDATE 
        '.$this->definition->getTableName().'
         SET '.$fieldOrdre.'='.$fieldOrdre.'-1 
        WHERE '.$this->definition->getPrimaryDbKey().'=:id');
        $q->argument($this->getValue($this->definition->getPrimaryAttributeKey()), Query::SQL_NUMERIC, 'id');
        RestLog::addLog('Query : '.$q->getSql());
        $q->execute();

    }

    public function _down($field = 'ordre', $condition = null){

        $fieldOrdre = $this->definition->getDdbFieldNameFromAttribute($field);

        $q = new Query();
        $q->setRequest('SELECT 
        MAX('.$fieldOrdre.') AS mx 
        FROM
         '.$this->definition->getTableName());
        if($condition){
            $q->addToRequest('WHERE '.$condition);
        }
        RestLog::addLog('Query : '.$q->getSql());
        $q->execute();
        $qr = $q->getQueryResults();

        if($qr->getValueAt('mx',0) == $this->getValue($field)){
            throw new SpecificException('G004');
        }


        $q = new Query();
        $q->setRequest('UPDATE 
        '.$this->definition->getTableName().'
        SET '.$fieldOrdre.'='.$fieldOrdre.'-1 
        WHERE '.$fieldOrdre.'=:ordre');
        if($condition){
            $q->addToRequest('AND '.$condition);
        }
        $q->argument($this->datas->getValueAt('ordre',0)+1, Query::SQL_NUMERIC, 'ordre');
        RestLog::addLog('Query : '.$q->getSql());
        $q->execute();

        $q = new Query();
        $q->setRequest('UPDATE 
        '.$this->definition->getTableName().'
        SET '.$fieldOrdre.'='.$fieldOrdre.'+1
        WHERE '.$this->definition->getPrimaryDbKey().'=:id');
        $q->argument($this->getValue($this->definition->getPrimaryAttributeKey()), Query::SQL_NUMERIC, 'id');
        RestLog::addLog('Query : '.$q->getSql());
        $q->execute();
    }

    public function getValue($key){
        if(is_array($this->datas)){
             return $this->datas[$key];
        }else{
             return $this->datas->getValueAt($key);
        }
    }

    public function getId(){
        return $this->getValue('id');
    }

    public function getUId(){
        return $this->getValue('uid');
    }

    public function output(){
        if(is_array($this->datas)){
            $obj = $this->datas;
        }else{
            $obj = $this->datas->getDatasInArray();
        }

        $obj = array_intersect_key($obj, $this->definition->getReverseDefinition());
        $obj = array_merge($obj, $this->definition->getOutputAppend());

        foreach($this->definition->getIgnoreInOutput() AS $attr){
            unset($obj[$attr]);
        }

        return $obj;
    }

    
    

    
}