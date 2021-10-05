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
    public function insert($data = null, bool $returnID = true)
    {
        $data = $this->transformDataToArray($data, 'insert');

        return $this->insertWithoutParent($data, $returnID);
    }

    /**
     * Inserts data under a referent in a MPTT. If an object is provided,
     * it will attempt to convert it to an array.
     *
     * @param array|object|null $data
     * @param bool              $returnID Whether insert ID should be returned or not.
     *
     * @throws ReflectionException
     *
     * @return BaseResult|false|int|object|string
     */
    public function insertUnderReferent($data = null, int $referentId = NULL, bool $returnID = true)
    {
        $data = $this->transformDataToArray($data, 'insert');
        

        if ($referentId != NULL)
        {
            $parent = $this->select(''. $this->leftIdKey .','. $this->rightIdKey .'')
                        ->find($referentId);

            return $this->insertUnderParent($data, $parent->{$this->rightIdKey}, $returnID);
        } else
        {
            return $this->insertWithoutParent($data, $returnID);
        }
    }

    /**
     * Inserts data after a referent in a MPTT. If an object is provided,
     * it will attempt to convert it to an array.
     *
     * @param array|object|null $data
     * @param bool              $returnID Whether insert ID should be returned or not.
     *
     * @throws ReflectionException
     *
     * @return BaseResult|false|int|object|string
     */
    public function insertAfterReferent($data = null, int $referentId = NULL, bool $returnID = true)
    {
        $data = $this->transformDataToArray($data, 'insert');
        

        if ($referentId != NULL)
        {
            $parent = $this->select(''. $this->leftIdKey .','. $this->rightIdKey .'')
                        ->find($referentId);

            return $this->insertAfterParent($data, $parent->{$this->rightIdKey}, $returnID);
        } else
        {
            return $this->insertWithoutParent($data, $returnID);
        }
    }

    /**
     * Deletes a single record from the database where $id matches
     *
     * @param array|int|string|null $id    The rows primary key(s)
     * @param bool                  $purge Allows overriding the soft deletes setting.
     *
     * @throws DatabaseException
     *
     * @return BaseResult|bool
     */
    public function delete($id = NULL, bool $purge = true)
    {
        $this->transStart();
        $element = $this->select(''. $this->leftIdKey .','. $this->rightIdKey .'')
                            ->find($id);
        if($element == null){
            $this->transComplete();
            return false;
        }
        $taille = $element->{$this->rightIdKey} - $element->{$this->leftIdKey} + 1;

        parent::where($this->leftIdKey .' >= ', $element->{$this->leftIdKey})
             ->where($this->rightIdKey .' <= ', $element->{$this->rightIdKey})
             ->delete(); 
        /*$this->db->simpleQuery('DELETE FROM '. $this->table .'
                                WHERE '. $this->leftIdKey .' >= '. $element->{$this->leftIdKey} .' 
                                    AND '. $this->rightIdKey .' <= '. $element->{$this->rightIdKey} .';');*/
        $this->where($this->leftIdKey .' > ', $element->{$this->rightIdKey})
             ->set([$this->leftIdKey => $this->leftIdKey .' - '. $taille])
             ->orderBy($this->leftIdKey, 'ASC')
             ->update();              
        /*$this->db->simpleQuery('UPDATE '. $this->table .'
                                SET '. $this->leftIdKey .' = '. $this->leftIdKey .' - '. ($taille+1).'
                                WHERE '. $this->leftIdKey .' > '. $element->{$this->rightIdKey} .'
                                ORDER BY '. $this->leftIdKey .' ;');*/
        $this->where($this->rightIdKey .' > ', $element->{$this->rightIdKey})
             ->set([$this->rightIdKey => $this->rightIdKey .' - '. $taille])
             ->orderBy($this->rightIdKey, 'ASC')
             ->update(); 
        /*$this->db->simpleQuery('UPDATE '. $this->table .'
                                SET '. $this->rightIdKey .' = '. $this->rightIdKey .' - '. ($taille+1).'
                                WHERE '. $this->rightIdKey .' > '. $element->{$this->rightIdKey} .'
                                ORDER BY '. $this->rightIdKey .' ;');     
        if( ! parent::delete($id, $purge)){
            $this->db->transComplete();
            return false;
        }*/
        $this->transComplete();
        return $this->db->transStatus();
    }
    
    /**
     * move element and child in a MPTT. 
     *
     * @param int           $id of the element to move
     * @param string        $position compared to the reference           
     * @param int           $referentId  id of the reference
     * 
     * @throws ReflectionException
     *
     * @return BaseResult|false|int|object|string
     */
    public function deplacer($id, $position, $referentId)
    {
        $this->db->transStart();
        $element = $this->select(''. $this->leftIdKey .','. $this->rightIdKey .'')
                            ->find($id);
        if($element == null){
            $this->db->transComplete();
            return false;
        }
        $taille = $element->{$this->rightIdKey} - $element->{$this->leftIdKey} + 1;

        $reference = NULL;
        if ($referentId!=0)
        {
            $reference = $this->select(''. $this->leftIdKey .','. $this->rightIdKey .'')
                                ->find($referentId);
            $referenceLeft = $reference->{$this->leftIdKey};
            $referenceRight = $reference->{$this->rightIdKey};
        }else{
            $referenceLeft = 0;
        }
        switch ($position) {
            case 'after':
                $difference = $referenceRight - $element->{$this->leftIdKey} + 1;
                $newLocation = $referenceRight + 1;
                break;
            case 'before':
                $difference = $referenceLeft - $element->{$this->leftIdKey};
                $newLocation = $referenceLeft;
                break;
            case 'firstChild':
            default:
                $difference = $referenceLeft - $element->{$this->leftIdKey} + 1;
                $newLocation = $referenceLeft + 1;
                break;
        }

        //Create new location space
        $this->db->simpleQuery('UPDATE '. $this->table .'
                                SET '. $this->leftIdKey .' = '. $this->leftIdKey .' + '. $taille.'
                                WHERE '. $this->leftIdKey .' >= '. $newLocation .'
                                ORDER BY '. $this->leftIdKey .' DESC;');
        $this->db->simpleQuery('UPDATE '. $this->table .'
                                SET '. $this->rightIdKey .' = '. $this->rightIdKey .' + '. $taille.'
                                WHERE '. $this->rightIdKey .' >= '. $newLocation .'
                                ORDER BY '. $this->rightIdKey .' DESC;');

        // recalculate elements location
        if ($difference < 0)
        {
            $element->{$this->leftIdKey} = $element->{$this->leftIdKey} + $taille;
            $element->{$this->rightIdKey} = $element->{$this->rightIdKey} + $taille;
            $order = 'ASC';
            $difference = $difference - $taille;
        }else{
            $order = 'DESC';
        }
        //move elements into new location
        $this->db->simpleQuery('UPDATE '. $this->table .'
                                SET '. $this->leftIdKey .' = '. $this->leftIdKey .' + '. $difference .'
                                WHERE '. $this->leftIdKey .' >= '. $element->{$this->leftIdKey} .'
                                AND '. $this->leftIdKey .' < '. $element->{$this->rightIdKey} .'
                                ORDER BY '. $this->leftIdKey .' '.$order.';');
        
        $this->db->simpleQuery('UPDATE '. $this->table .'
                                SET '. $this->rightIdKey .' = '. $this->rightIdKey .' + '. $difference .'
                                WHERE '. $this->rightIdKey .' > '. $element->{$this->leftIdKey} .'
                                AND '. $this->rightIdKey .' <= '. $element->{$this->rightIdKey} .'
                                ORDER BY '. $this->rightIdKey .' '.$order.';');

        //remove old space
        $this->db->simpleQuery('UPDATE '. $this->table .'
                                SET '. $this->leftIdKey .' = '. $this->leftIdKey .' - '. $taille.'
                                WHERE '. $this->leftIdKey .' >= '. $element->{$this->leftIdKey} .'
                                ORDER BY '. $this->leftIdKey .' ASC;');
        
        $this->db->simpleQuery('UPDATE '. $this->table .'
                                SET '. $this->rightIdKey .' = '. $this->rightIdKey .' - '. $taille.'
                                WHERE '. $this->rightIdKey .' >= '. $element->{$this->rightIdKey} .'
                                ORDER BY '. $this->rightIdKey .' ASC;');
        

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
    private function insertUnderParent($data = null, int $parentRightKey, bool $returnID = true)
    {
        $this->db->transStart();
        
        $this->db->simpleQuery('UPDATE '. $this->table .'
                                SET '. $this->leftIdKey .' = '. $this->leftIdKey .' + 2
                                WHERE '. $this->leftIdKey .' > '. $parentRightKey .'
                                ORDER BY '. $this->leftIdKey .' desc;');        
        $this->db->simpleQuery('UPDATE '. $this->table .'
                                SET '. $this->rightIdKey .' = '. $this->rightIdKey .' + 2
                                WHERE '. $this->rightIdKey .' >= '. $parentRightKey .'
                                ORDER BY '. $this->rightIdKey .' desc;');        

        $data[$this->leftIdKey] = $parentRightKey;
        $data[$this->rightIdKey] = $parentRightKey+1;

        if( ! parent::insert($data,$returnID)){
            $this->db->transComplete();
            return false;
        }
        $data[$this->primaryKey] = $this->insertID;
        $result = $this->db->transComplete();
        return $returnID ? $this->insertID : $result;
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
    private function insertAfterParent($data = null, int $referentRightKey, bool $returnID = true)
    {
        $this->db->transStart();
        
        $this->db->simpleQuery('UPDATE '. $this->table .'
                                SET '. $this->leftIdKey .' = '. $this->leftIdKey .' + 2
                                WHERE '. $this->leftIdKey .' > '. $referentRightKey .'
                                ORDER BY '. $this->leftIdKey .' desc;');        
        $this->db->simpleQuery('UPDATE '. $this->table .'
                                SET '. $this->rightIdKey .' = '. $this->rightIdKey .' + 2
                                WHERE '. $this->rightIdKey .' > '. $referentRightKey .'
                                ORDER BY '. $this->rightIdKey .' desc;');        

        $data[$this->leftIdKey] = $referentRightKey+1;
        $data[$this->rightIdKey] = $referentRightKey+2;

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
     * @param array $data
     * @param bool  $returnID Whether insert ID should be returned or not.
     *
     * @throws ReflectionException
     *
     * @return BaseResult|false|int|object|string
     */
    private function insertWithoutParent($data = null, bool $returnID = true)
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