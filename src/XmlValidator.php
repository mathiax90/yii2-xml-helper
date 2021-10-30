<?php

namespace mathiax90\XmlHelper;

class XmlValidator {

    public $errors;
    private $xmlFilePath;
    public $xsdFilePath;

    function __construct(string $xmlFilePath, string $xsdFilePath) {
        $this->xmlFilePath = $xmlFilePath;
        $this->xsdFilePath = $xsdFilePath;
    }

    public function isValidByXsd(string $xsdFilePath = null): bool {
        if (!empty($xsdFilePath)) {
            $this->xsdFilePath = $xsdFilePath;
        }

        if (!file_exists($this->xmlFilePath)) {
            $this->errors[] = "XML-file not found: " . $this->xmlFilePath;
            return false;
        }

        if (!file_exists($this->xsdFilePath)) {
            $this->errors[] = "XSD-file not found: " . $this->xmlFilePath;
            return false;
        }

        libxml_use_internal_errors(true);
        $this->readAndFindErrors();
        libxml_use_internal_errors(false);
        if (count($this->errors) == 0) {
            return true;
        } else {
            return false;
        }
    }

    private function readAndFindErrors(): bool {
        try {

            $xmlReader = new \XMLReader();

            if (!$xmlReader->open($this->xmlFilePath)) {
                $this->errors[] = "XMLReader open returned false. Can't open $this->xmlFilePath";
                return false;
            }

            $xmlReader->setSchema($this->xsdFilePath);
            while (@$xmlReader->read()) {

            };
            $this->errors = libxml_get_errors();
        } catch (Exception $ex) {
            $this->errors[] = "Exception while validating by XSD\r\n" . $ex;
            return false;
        }
        return true;
    }

}
