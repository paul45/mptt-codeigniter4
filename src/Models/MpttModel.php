<?php namespace MpttCodeigniter4\Models;

use CodeIgniter\Model;
use CodeIgniter\BaseModel;
class MpttModel extends Model
{    
    /**
     * The table's left id key.
     *
     * @var string
     */
    protected $leftIdKey = 'left';   

    /**
     * The table's right id key.
     *
     * @var string
     */
    protected $rightIdKey = 'right';
    
    /**
    * The table's parent id key.
    *
    * @var string
    */
    protected $parentIdKey = 'parent';

    /**
     * Inserts data into the database. If an object is provided,
     * it will attempt to convert it to an array.
     *
     * @param array|object|null $data
     * @param bool              $returnID Whether insert ID should be returned or not.
     *
     * @throws ReflectionException
     *
     * @return BaseResult|false|int|object|string
     */
    public function insert($data = null, bool $returnID = true)
    {
        if (! empty($this->tempData['data'])) {
            if (empty($data)) {
                $data = $this->tempData['data'] ?? null;
            } else {
                $data = $this->transformDataToArray($data, 'insert');
                $data = array_merge($this->tempData['data'], $data);
            }
        }

        $this->escape   = $this->tempData['escape'] ?? [];
        $this->tempData = [];


        if (isset($data[$this->parent]) && $data[$this->parent] != '')
        {
            return $this->insertUnderParent($data,$returnID);
        } else
        {
            return $this->insertWithoutParent($data,$returnID);
        }
    }
    public function delete($id = NULL, bool $purge = false)
    {
        $this->db->transStart();
        $element = $this->select('arbre_gauche,arbre_droite')
                            ->find($id);
        if($element == null){
            $this->db->transComplete();
            return false;
        }
        $taille = $element->arbre_droite - $element->arbre_gauche;
        $this->db->simpleQuery('DELETE FROM '. $this->table .'
                                WHERE arbre_gauche >= '.$element->arbre_gauche.' 
                                    AND arbre_droite <= '.$element->arbre_droite.';');
        $this->db->simpleQuery('UPDATE '. $this->table .'
                                SET arbre_gauche = arbre_gauche - '. ($taille+1).'
                                WHERE arbre_gauche > '. $element->arbre_droite .'
                                ORDER BY arbre_gauche ;');
        $this->db->simpleQuery('UPDATE '. $this->table .'
                                SET arbre_droite = arbre_droite - '. ($taille+1).'
                                WHERE arbre_droite > '. $element->arbre_droite .'
                                ORDER BY arbre_droite ;');     
        if( ! parent::delete($id)){
            $this->db->transComplete();
            return false;
        }
        $this->db->transComplete();
        return $this->db->transStatus();
    }
    public function deplacer($id,$position,$index)
    {
        $this->db->transStart();
        $element = $this->select('arbre_gauche,arbre_droite')
                            ->find($id);
        if($element == null){
            $this->db->transComplete();
            return false;
        }
        $taille = $element->arbre_droite - $element->arbre_gauche;

        $this->db->simpleQuery('UPDATE '. $this->table .'
                                SET arbre_gauche = arbre_gauche + 100000
                                , arbre_droite = arbre_droite + 100000
                                WHERE arbre_gauche >= '. $element->arbre_gauche .' 
                                    AND arbre_droite <= '.$element->arbre_droite.';');
        $this->db->simpleQuery('UPDATE '. $this->table .'
                                SET arbre_gauche = arbre_gauche - '. ($taille+1).'
                                WHERE arbre_gauche >= '. $element->arbre_gauche .'
                                AND arbre_gauche <= 100000
                                ORDER BY arbre_gauche ;');
        
        $this->db->simpleQuery('UPDATE '. $this->table .'
                                SET arbre_droite = arbre_droite - '. ($taille+1).'
                                WHERE arbre_droite >= '. $element->arbre_gauche .'
                                AND arbre_gauche <= 100000
                                ORDER BY arbre_droite ;');

        if($position=='after')
        {
            $precedents = $this->select('arbre_gauche,arbre_droite')
                                ->find($index);
            $difference = $precedents->arbre_droite - $element->arbre_gauche;
            $nouvelle_position = $precedents->arbre_droite + 1;
        }else//first
        {
            if ($index!=0)
            {
                $parent = $this->select('arbre_gauche,arbre_droite')
                                ->find($index);
                $difference = $parent->arbre_gauche - $element->arbre_gauche;
                $nouvelle_position = $parent->arbre_gauche + 1;
            } else {
                $difference = -$element->arbre_gauche;
                $nouvelle_position = 1;
            }
        }
         
        $this->db->simpleQuery('UPDATE '. $this->table .'
                                SET arbre_gauche = arbre_gauche + '. ($taille+1).'
                                WHERE arbre_gauche >= '. $nouvelle_position .'
                                AND arbre_gauche <= 100000
                                ORDER BY arbre_gauche desc;');

        $this->db->simpleQuery('UPDATE '. $this->table .'
                                SET arbre_droite = arbre_droite + '. ($taille+1).'
                                WHERE arbre_droite >= '. $nouvelle_position .'
                                AND arbre_gauche <= 100000
                                ORDER BY arbre_droite desc;');

        $this->db->simpleQuery('UPDATE '. $this->table .'
                                SET arbre_gauche = arbre_gauche - 99999 + '. ($difference).'
                                , arbre_droite = arbre_droite - 99999 + '. ($difference).'
                                WHERE arbre_gauche >= 100000;');


        $this->db->transComplete();
        if ($this->db->transStatus() === FALSE)
        {
            return false;
        }
        return true;
    }
    /**
     * Inserts data under a parent into MPTT. If an object is provided,
     * it will attempt to convert it to an array.
     *
     * @param array|object|null $data
     * @param bool              $returnID Whether insert ID should be returned or not.
     *
     * @throws ReflectionException
     *
     * @return BaseResult|false|int|object|string
     */
    protected function insertUnderParent($data = null, bool $returnID = true)
    {
        $this->db->transStart();
        $parent = $this->select(''. $this->leftIdKey .','. $this->rightIdKey .'')
                    ->find($data[$this->parent]);
        $this->db->simpleQuery('UPDATE '. $this->table .'
                                SET '. $this->leftIdKey .' = '. $this->leftIdKey .' + 2
                                WHERE '. $this->leftIdKey .' > '. $parent->{$this->rightIdKey} .'
                                ORDER BY '. $this->leftIdKey .' desc;');        
        $this->db->simpleQuery('UPDATE '. $this->table .'
                                SET '. $this->rightIdKey .' = '. $this->rightIdKey .' + 2
                                WHERE '. $this->rightIdKey .' >= '. $parent->{$this->rightIdKey} .'
                                ORDER BY '. $this->rightIdKey .' desc;');        

        $data[$this->leftIdKey] = $parent->{$this->rightIdKey};
        $data[$this->rightIdKey] = $parent->{$this->rightIdKey}+1;

        if( ! parent::insert($data,$returnID)){
            $this->db->transComplete();
            return false;
        }
        $data->id = $this->insertID;
        return $this->db->transComplete();
    }
    /**
     * Inserts data at the end of a MPTT. If an object is provided,
     * it will attempt to convert it to an array.
     *
     * @param array|object|null $data
     * @param bool              $returnID Whether insert ID should be returned or not.
     *
     * @throws ReflectionException
     *
     * @return BaseResult|false|int|object|string
     */
    protected function insertWithoutParent($data = null, bool $returnID = true)
    {
        $this->db->transStart();
        $lastElement =$this->select(''. $this->rightIdKey .'')
                ->orderby(''. $this->rightIdKey .'','desc')
                ->limit(1)
                ->find();
        if (isset($lastElement[0]))
        {
            $data[$this->leftIdKey]=$lastElement[0]->{$this->rightIdKey}+1;
            $data[$this->rightIdKey]=$lastElement[0]->{$this->rightIdKey}+2;
        }else{
            $data[$this->leftIdKey]=1;
            $data[$this->rightIdKey]=2;
        }
        if( ! parent::insert($data,$returnID)){
            $this->db->transComplete();
            return false;
        }
        $data->id=$this->insertID;
        return $this->db->transComplete();
    }
}