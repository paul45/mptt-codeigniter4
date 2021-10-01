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
        $data = $this->transformDataToArray($data, 'insert');
        if (! empty($this->tempData['data'])) {
            if (empty($data)) {
                $data = $this->tempData['data'] ?? null;
            } else {
                $data = array_merge($this->tempData['data'], $data);
            }
        }

        $this->escape   = $this->tempData['escape'] ?? [];
        $this->tempData = [];


        if (isset($data[$this->parentIdKey]) && $data[$this->parentIdKey] != '')
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
        $element = $this->select(''. $this->leftIdKey .','. $this->rightIdKey .'')
                            ->find($id);
        if($element == null){
            $this->db->transComplete();
            return false;
        }
        $taille = $element->{$this->rightIdKey} - $element->{$this->leftIdKey};
        $this->db->simpleQuery('DELETE FROM '. $this->table .'
                                WHERE '. $this->leftIdKey .' >= '. $element->{$this->leftIdKey} .' 
                                    AND '. $this->rightIdKey .' <= '. $element->{$this->rightIdKey} .';');
        $this->db->simpleQuery('UPDATE '. $this->table .'
                                SET '. $this->leftIdKey .' = '. $this->leftIdKey .' - '. ($taille+1).'
                                WHERE '. $this->leftIdKey .' > '. $element->{$this->rightIdKey} .'
                                ORDER BY '. $this->leftIdKey .' ;');
        $this->db->simpleQuery('UPDATE '. $this->table .'
                                SET '. $this->rightIdKey .' = '. $this->rightIdKey .' - '. ($taille+1).'
                                WHERE '. $this->rightIdKey .' > '. $element->{$this->rightIdKey} .'
                                ORDER BY '. $this->rightIdKey .' ;');     
        if( ! parent::delete($id, $purge)){
            $this->db->transComplete();
            return false;
        }
        $this->db->transComplete();
        return $this->db->transStatus();
    }
    
    public function deplacer($id,$position,$index) //TODO
    {
        $this->db->transStart();
        $element = $this->select('arbre_gauche,arbre_droite')
                            ->find($id);
        if($element == null){
            $this->db->transComplete();
            return false;
        }
        $taille = $element->arbre_droite - $element->arbre_gauche + 1;

        $reference = NULL;
        if ($index!=0)
        {
            $reference = $this->select('arbre_gauche,arbre_droite')
                                ->find($index);
        }
        switch ($position) {
            case 'after':
                $difference = $reference->arbre_droite - $element->arbre_gauche + 1;
                $newLocation = $reference->arbre_droite + 1;
                break;
            case 'before':
                $difference = $reference->arbre_gauche - $element->arbre_gauche;
                $newLocation = $reference->arbre_gauche;
                break;
            case 'lastChild':
                $difference = $reference->arbre_droite - $element->arbre_gauche;
                $newLocation = $reference->arbre_droite;
                break;
            case 'firstChild':
            default:
                $difference = $reference->arbre_gauche - $element->arbre_gauche + 1;
                $newLocation = $reference->arbre_gauche + 1;
                break;
        }

        //Create new location space
        $this->db->simpleQuery('UPDATE '. $this->table .'
                                SET arbre_gauche = arbre_gauche + '. $taille.'
                                WHERE arbre_gauche >= '. $newLocation .'
                                ORDER BY arbre_gauche ;');
        
                                $this->db->simpleQuery('UPDATE '. $this->table .'
                                SET arbre_droite = arbre_droite + '. $taille.'
                                WHERE arbre_droite >= '. $newLocation .'
                                ORDER BY arbre_droite ;');
        // recalculate elements location
        if ($difference < 0)
        {
            $element->arbre_gauche = $element->arbre_gauche + $taille;
            $element->arbre_droite = $element->arbre_droite + $taille;
        }
        //move elements into new location
        $this->db->simpleQuery('UPDATE '. $this->table .'
                                SET arbre_gauche = arbre_gauche + '. $difference.'
                                WHERE arbre_gauche >= '. $element->arbre_gauche .'
                                ORDER BY arbre_gauche ;');
        
                                $this->db->simpleQuery('UPDATE '. $this->table .'
                                SET arbre_droite = arbre_droite + '. $difference.'
                                WHERE arbre_droite <= '. $element->arbre_droite .'
                                ORDER BY arbre_droite ;');

        //remove old space
        $this->db->simpleQuery('UPDATE '. $this->table .'
                                SET arbre_gauche = arbre_gauche - '. $taille.'
                                WHERE arbre_gauche >= '. $element->arbre_gauche .'
                                ORDER BY arbre_gauche ;');
        
                                $this->db->simpleQuery('UPDATE '. $this->table .'
                                SET arbre_droite = arbre_droite + '. $taille.'
                                WHERE arbre_droite >= '. $element->arbre_droite .'
                                ORDER BY arbre_droite ;');
        

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
                    ->find($data[$this->parentIdKey]);
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
        $data[$this->primaryKey] = $this->insertID;
        $result = $this->db->transComplete();
        return $returnID ? $this->insertID : $result;
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
        $lastElement = $this->select(''. $this->rightIdKey .'')
                ->orderby(''. $this->rightIdKey .'','desc')
                ->limit(1)
                ->find();
        if (isset($lastElement[0]))
        {
            $data[$this->leftIdKey] = $lastElement[0]->{$this->rightIdKey}+1;
            $data[$this->rightIdKey] = $lastElement[0]->{$this->rightIdKey}+2;
        }else{
            $data[$this->leftIdKey] = 1;
            $data[$this->rightIdKey] = 2;
        }
        if( ! parent::insert($data,$returnID)){
            $this->db->transComplete();
            return false;
        }
        $data[$this->primaryKey] = $this->insertID;
        $result = $this->db->transComplete();
        return $returnID ? $this->insertID : $result;
    }
}