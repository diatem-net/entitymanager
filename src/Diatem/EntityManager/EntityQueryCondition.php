<?php

namespace Diatem\EntityManager;

use Jin2\Db\Query\Query;
use Jin2\Utils\StringTools;

class EntityQueryCondition{

    private $iterates = array();

    public function __construct($attribut, $valeur = '', $test = '=' , $operand = 'AND'){
        $this->iterates[] = array('attribut' => $attribut, 'valeur' => $valeur, 'operand' => $operand, 'test' => $test);
    }

    public function add($attribut, $valeur = '', $test = '=' , $operand = 'AND'){
        $this->iterates[] = array('attribut' => $attribut, 'valeur' => $valeur, 'operand' => $operand, 'test' => $test);
    }

    public function apply($query, $definition){
        $query->addToRequest('AND (');

        $first = true;
        $i = 1;
        foreach($this->iterates AS $it){
            $pName = 'f'.uniqid();
            $attr = $definition->getDdbFieldNameFromAttribute($it['attribut'], true);
            if($attr === null){
                $attr = $it['attribut'];
            }
            if(!$first){
                $query->addToRequest($it['operand']);
            }
            if($it['valeur'] === null){
                $query->addToRequest($attr.' '.$it['test'].' NULL');
            }else if($it['test'] == 'IN'){
                $query->addToRequest($attr.' '.$it['test']. '('.$it['valeur'].')');
            }else if($it['test'] == 'LIKE'){
                $query->addToRequest('lower('.$attr.') '.$it['test']. ' \''.StringTools::toLowerCase($it['valeur']).'\'');
            }else{
                $query->addToRequest($attr.' '.$it['test'].' :'.$pName);

                if($it['valeur'] === true || $it['valeur'] === false){
                    $query->argument($it['valeur'], Query::SQL_BOOL, $pName);
                }else if(is_string($it['valeur'])){
                    $query->argument($it['valeur'], Query::SQL_STRING, $pName);
                }else if(is_numeric($it['valeur'])){
                    $query->argument($it['valeur'], Query::SQL_NUMERIC, $pName);
                }
            }
            
            $first = false;
            $i++;
        }

        $query->addToRequest(')');
        return $query;
    }
    
}