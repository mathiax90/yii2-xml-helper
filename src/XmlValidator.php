<?php

namespace mathiax90\XmlHelper;

class XmlValidator {

    public $errors;
    private $xmlFilePath;
    private $xsdFilePath;

    function __construct(string $xmlFilePath, string $xsdFilePath) {
        $this->xmlFilePath = $xmlFilePath;
        $this->xsdFilePath = $xsdFilePath;
    }

    public function isValidByXsd(): bool {

        if (!fileExists($this->xmlFilePath)) {
            $this->errors[] = "XML-file not found: " . $this->xmlFilePath;
            return false;
        }

        if (!fileExists($this->xsdFilePath)) {
            $this->errors[] = "XSD-file not found: " . $this->xmlFilePath;
            return false;
        }

        try {
            libxml_use_internal_errors(true);
            $xmlReader = new \XMLReader();
            $xmlReader->open($xml_path);
            $xmlReader->setSchema($this->xsdFilePath);
            while (@$xml->read()) {

            };
            $this->errors = libxml_get_errors();
        } catch (Exception $ex) {
            $this->errors[] = "Exception while validating by XSD\r\n" . $ex;
            return false;
        }

        if (count($this->errors) == 0) {
            return true;
        }
        else {
            return false;
        }
    }

}
