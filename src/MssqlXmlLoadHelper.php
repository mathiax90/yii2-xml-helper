<?php

namespace mathiax90\XmlHelper;

use mathiax90\XmlHelper\XmlValidator;
use mathiax90\XmlHelper\XmlHelper;
use PDO;

class MssqlXmlLoadHelper {
    public $errors = [];
    public XmlHelper $xmlHelper;

    /**
     *
     * @param string $xmlFilePath
     * @return type
     */
    function __construct(string $xmlFilePath) {
        $this->xmlHelper = new XmlHelper($xmlFilePath);
    }

    function prepareXml(): bool {
        if (count($this->xmlHelper->errors) > 0) {
            array_push($this->errors, $this->xmlHelper->errors);
            return false;
        } else {
            if ($this->xmlHelper->contentReady) {
                return true;
            } else {
                if ($this->xmlHelper->readContent()) {
                    return true;
                } else {
                    array_push($this->errors, $this->xmlHelper->errors);
                    return false;
                }
            }
        }
    }

    /**
     * Runs SP with Params
     * $spCallQuery example - exec sp_testsp :param1, :param2, paramX, :msg
     * Params is an array
     * Param example:
     * ['name' => ":xml", 'value' => "xml here", 'type' => PDO::PARAM_STR];
     * Param example with output value
     * ['name' => ":inout", 'value' => "", 'type' => PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 'len' => 300];
     * msg param is required (always):
     * ['name' => ":msg", 'value' => "", 'type' => PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 'len' => 300];
     * if msg is empty string transaction will be commited, else rollback.
     * msg will be added to the helper errors
     *
     * @param string $spCallQuery
     * @param yii\db\Connection $connection
     * @param ArrayOfPDOParams $params
     * @return boolean
     */
    public function simpleLoadWithParams($spCallQuery, $connection, $params): bool {
        if (!$this->prepareXml()) {
            return false;
        }

        $msg = "";
        $msgParamExists = false;
        $transaction = $connection->beginTransaction();
        try {
            $command = $connection->createCommand($spCallQuery);
            foreach ($params as $param) {
                if (isset($param['name'])) {
                    if (trim($param['name']) == ':msg') {
                        $command->bindParam($param['name'], $msg, $param['type'], $param['len']);
                        $msgParamExists = true;
                        continue;
                    }
                } else {
                    $this->errors[] = 'Param has no name\r\n' . print_r($param, true);
                    return false;
                }
                if (isset($param['len'])) {
                    $command->bindParam($param['name'], $param['value'], $param['type'], $param['len']);
                } else {
                    $command->bindParam($param['name'], $param['value'], $param['type']);
                }
            }
            if (!$msgParamExists) {
                $this->errors[] = 'msg param is required';
                return false;
            }
            $command->execute();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            $this->errors[] = "Exception while SP execution.\r\n" . $e;
            return false;
        }

        if (strlen(trim($msg)) > 0) {
            $transaction->rollBack();
            $this->errors[] = "Error while SP execution.\r\n" . $msg;
            return false;
        }

        $transaction->commit();
        return true;
    }

    /**
     * simpleLoad - runs SP with signature exec $spName :xml, :filename, :msg
     * msg - is output param varchar(300)
     * if msg is empty string transaction will be commited, else rollback.
     * msg will be added to helper errors
     * @param type $spName
     * @param type $connection
     * @return boolean
     */
    public function simpleLoad($spName, $connection): bool {
        if (!$this->prepareXml()) {
            return false;
        }
        $msg = "";
        $transaction = $connection->beginTransaction();
        try {
            $result = \Yii::$app->db->createCommand("exec " . $spName . " :xml,:filename, :msg")
                    ->bindValue(':xml', $this->xmlHelper->content)
                    ->bindValue(':filename', $this->xmlHelper->fileNameNoExt)
                    ->bindParam(":msg", $msg, PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 300)
                    ->execute();
        } catch (Exception $e) {
            $transaction->rollBack();
            $this->errors[] = "Exception while SP execution.\r\n" . $e;
            return false;
        }

        if (strlen($msg) > 0) {
            $transaction->rollBack();
            $this->errors[] = "Error while SP execution.\r\n" . $msg;
            return false;
        }

        $transaction->commit();
        return true;
    }
}
