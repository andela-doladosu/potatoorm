<?php

namespace Dara\Origins;

use PDO;
use Dotenv;

abstract class BaseModel
{

    /**
     * The name of the model 
     * 
     * @var string
     */
    protected $className;


    /**
     * An instance of the Connection class
     * 
     * @var PDO
     */
    protected $connection;


    /**
     * Array of table rows
     * 
     * @var array
     */
    protected $resultRows = [];


    public function __construct()
    {
        $this->loadEnv();
        $this->connection = new Connection($_ENV['P_DRIVER'], $_ENV['P_HOST'], $_ENV['P_DBNAME'], $_ENV['P_USER'], $_ENV['P_PASS']);
        $this->className = get_called_class();
    }


    /**
     * Load environment variables
     * 
     * @return null
     */
    public function loadEnv()
    {
        $dotenv = new Dotenv\Dotenv(__DIR__.'/../..');
        $dotenv->load();
    }


    /**
     * Create an instance of the called model
     * 
     * @return mixed
     */
    protected static function createModelInstance()
    {
        $model = get_called_class();
        return new $model();
    }


    /**
     * Return  all the rows from a table
     * 
     * @return array
     */
    public static function getAll()
    {
        return self::doSearch()->resultRows;
    }


    /**
     * Choose to either search database for one row or all rows depending on whether the $id argument is passed
     * 
     * @param int $id
     * @return mixed
     */
    protected static function doSearch($id = null)
    {
        $model = self::createModelInstance();
        $tableName = $model->getTableName($model->className);

        return $id ? self::selectOne($model, $tableName, $id) : self::selectAll($model, $tableName);
    }


    /**
     * Search database for all rows
     * 
     * @param $model
     * @param $tableName
     * @return mixed
     */
    protected static function selectAll($model, $tableName)
    {
        $getAll = $model->connection->prepare('select * from '.$tableName);
        $getAll->execute();

        while ($allRows = $getAll->fetch(PDO::FETCH_ASSOC)) {
            array_push($model->resultRows, $allRows);
        }

        return $model;
    }


    /**
     * Search database for one row
     * 
     * @param $model
     * @param $tableName
     * @param $id
     * @return mixed
     */
    protected static function selectOne($model, $tableName, $id)
    {
        $getAll = $model->connection->prepare('select * from '.$tableName.' where id='.$id);
        $getAll->execute();

        $row = $getAll->fetch(PDO::FETCH_ASSOC);
        array_push($model->resultRows, $row);
        
        return $model;
    }


    /**
     * Return the database rows 
     * 
     * @param $id
     * @return mixed
     */
    public static function find($id = null)
    {
        return self::doSearch($id);
    }


    /**
     * Edit an existing row or insert a new row depending on whether the row already exists
     * 
     * @return bool|string
     */
    public function save()
    {   
        return $this->checkForRows() ? $this->updateRow() : $this->insertRow();
    }


    /**
     * Get user assigned column values
     * 
     * @return array
     */
    protected function getAssignedValues()
    {
        $tableFields = $this->getTableFields();
        $newPropertiesArray = array_slice(get_object_vars($this), 3);

        $columns = $values = $tableData = [];

        foreach ($newPropertiesArray as $index => $value) {
            if (in_array($index, $tableFields)) {
                array_push($columns, $index);
                array_push( $values, $value);
            }
        }

        $tableData['columns'] = $columns;
        $tableData['values'] = $values; 

        return $tableData;
    }


    /**
     * Get table name for the called model
     * 
     * @param $model
     * @return string
     */
    protected function getTableName($model)
    {  
        return strtolower(explode('\\', $model)[2]).'s';
    }


    /**
     * Check if a row already exists
     * 
     * @return int
     */
    protected function checkForRows()
    {
        return count($this->resultRows);
    }


    /**
     * Insert a new row 
     * 
     * @return string
     */
    protected function insertRow()
    {   
        $assignedValues = $this->getAssignedValues();

        $columns = implode(', ', $assignedValues['columns']);
        $values = '\''.implode('\', \'', $assignedValues['values']).'\'';

        $tableName = $this->getTableName($this->className);
        $insert = $this->connection->prepare('insert into '.$tableName.'('.$columns.') values ('.$values.')');

        return $insert->execute() ? 'Row inserted successfully' : 'An error occured, unable to insert row';
    }


    /**
     * Get all the column names in a table
     * 
     * @return array
     */
    protected function getTableFields()
    {
        $q = $this->connection->prepare('describe '.$this->getTableName($this->className));
        $q->execute();
        
        return $q->fetchAll(PDO::FETCH_COLUMN);        
    }


    /**
     * Edit an existing row
     * 
     * @return bool
     */
    protected function updateRow()
    {   
        $tableName = $this->getTableName($this->className);
        $assignedValues = $this->getAssignedValues();
        $updateDetails = [];

        for ($i = 0; $i < count($assignedValues['columns']); $i++) { 
            array_push($updateDetails, $assignedValues['columns'][$i]  .' =\''. $assignedValues['values'][$i].'\'');
        }

        $allUpdates = implode(', ' , $updateDetails);
        $update = $this->connection->prepare('update '.$tableName.' set '. $allUpdates.' where id='.$this->resultRows[0]['id']);
       
        return $update->execute();
    }

}