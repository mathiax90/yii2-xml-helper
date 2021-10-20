<?php

namespace mathiax90\XmlHelper;

use mathiax90\Yii2XmlHelper\XmlValidator;
use Yii;

class MssqlXmlLoadHelper {

    private string $xmlFilePath;
    private $inFileNameWithExt;
    private $endcoding;
    private $inFileBaseName;
    public $errors;
    public $validationErrors;
    //* @var $preparedXml string */
    private $preparedXml;
    private $isXmlPrepared;

    /**
     *
     * @param string $inFilePath
     * @return type
     */
    function __construct(string $xmlFilePath) {
        $this->xmlFilePath = $xmlFilePath;
        $this->inFileNameWithExt = $inFileNameWithExt;
        $this->endcoding;

        if (!$this->inFileExists()) {
            $this->errors[] = "Can't find file " . $inFilePath;
            return;
        }

        $this->inFileBaseName = basename($this->inFileNameWithExt);
        if ($this->inFileBaseName == "") {
            $this->errors[] = "BaseName is empty string";
            return;
        }

        $this->isXmlPrepared = false;
    }

    /**
     * returns preparedXml or false (boolean)
     *
     * @params
     * @return string if success, bool false if error
     */
    public function getPreparedXml() {
        if ($this->isXmlPrepared) {
            return $this->preparedXml;
        } else {
            return false;
        }
    }

    /**
     * prepare XML for loading. Decodes.
     * @return boolean
     */
    public function prepareXml() {
        $domDocument = new \DOMDocument();
        if ($domDocument->load($this->inFilePath)) {
            $this->preparedXml = $domDocument->saveXML($domDocument->documentElement);
//            Yii::info('prepared xml:');
//            Yii::info(print_r($this->preparedXml, true));
            $this->isXmlPrepared = true;
            return true;
        } else {
            $this->errors[] = "error while document load";
            $this->isXmlPrepared = false;
            return false;
        }
    }

    public function isNameMatchesRegex($regexPattern) {
        if (preg_match($regexPattern, $this->inFileNameWithExt)) {
            return true;
        }
        return false;
    }

    public function isValidByXsd($xsdPath) {
        if (!file_exists($xsdPath)) {
            $this->errors[] = "Can't find xsd-scheme: " . $xsdPath . "\r\n File is not exist.";
            return false;
        }
        try {
//validate xml
            $validator = new XmlValidator();
            if (!$validator->isValid($this->inFilePath, $xsdPath)) {
                $this->validationErrors = $validator->errors;
                return false;
            }
        } catch (Exception $e) {
            $this->errors[] = 'Исключение при валидации по XSD.\r\n' . $e;
            return false;
        }
        return true;
    }

    /**
     * Выполняет хранимую процедуру с заданныи параметрами.
     * Параметры задаются массивом
     * Элемент массива выглядит так:
     * ['name' => ":xml", 'value' => $XmlLoader->getPreparedXml(), 'type' => PDO::PARAM_STR];
     * Если параметр input-output то так:
     * ['name' => ":inout", 'value' => "", 'type' => PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 'len' => 300];
     * ВНИМАНИЕ ОБЯЗАТЕЛЬН НУЖНО ИСПОЛЬЗОВАТЬ ПАРАМЕТР msg
     * ['name' => ":msg", 'value' => "", 'type' => PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 'len' => 300];
     * т.к. на основе него будет выполняться решение об откате транзакции, если переменная будет заполнена в ХП
     * значение переменной будет передано в массив errors
     *
     *
     * @param string $sqlQuery
     * @param yii\db\Connection $connection
     * @param ArrayOfPDOParams $params
     * @return boolean
     */
    public function simpleLoadWithParams($sqlQuery, $connection, $params) {
        if (!$this->isXmlPrepared) {
            if (!$this->prepareXml()) {
                return false;
            }
        }
        $msg = "";
        $msgParamExists = false;
        $transaction = $connection->beginTransaction();
        try {
            $command = \Yii::$app->db->createCommand($sqlQuery);
            foreach ($params as $param) {
                if (isset($param['name'])) {
                    if (trim($param['name']) == ':msg') {
                        Yii::info("msg is binded");
                        $command->bindParam($param['name'], $msg, $param['type'], $param['len']);
                        $msgParamExists = true;
                        continue;
                    }
                } else {
                    $this->errors[] = 'Параметр не содержит поля name\r\n' . print_r($param, true);
                    return false;
                }
                if (isset($param['len'])) {
                    $command->bindParam($param['name'], $param['value'], $param['type'], $param['len']);
                } else {
                    $command->bindParam($param['name'], $param['value'], $param['type']);
                }
            }
            if (!$msgParamExists) {
                $this->errors[] = 'Не задан параметр msg. rtm pls.';
                return false;
            }
            $command->execute();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            $this->errors[] = "Exception while SP execution.\r\n" . $e;
            return false;
        }

        if (strlen(trim($msg)) > 0) {
            Yii::info('msg: ' . $msg . ' msglen: ' . strlen($msg));
            $transaction->rollBack();
            $this->errors[] = "Error while SP execution.\r\n" . $msg;
            return false;
        }

        $transaction->commit();
        return true;
    }

    /**
     * Load XML
     *
     * @params string storedProcedureName (name only)
     * @return bool, errors in XmlHelper->errorss
     */
    public function simpleLoad($storedProcedureName, $connection) {
        if (!$this->isXmlPrepared) {
            if (!$this->prepareXml()) {
                return false;
            }
        }

        $msg = "";
        $transaction = $connection->beginTransaction();
        try {
            $result = \Yii::$app->db->createCommand("exec " . $storedProcedureName . " :xml,:filename, :msg")
                    ->bindValue(':xml', $this->preparedXml)
                    ->bindValue(':filename', $filename)
                    ->bindParam(":msg", $msg, PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 300)
                    ->execute();
        } catch (\Throwable $e) {
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

    private function inFileExists() {
        if (!file_exists($this->inFilePath)) {
            return false;
        }
        return true;
    }

}
