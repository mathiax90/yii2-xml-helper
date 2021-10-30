<?php

namespace mathiax90\XmlHelper;

use mathiax90\XmlHelper\XmlValidator;
/**
 * 
 */
class XmlHelper {
    
    public string $filePath;
    public string $fileName;
    public string $fileNameNoExt;
    public string $fileDir;
    public string $regexPattern;
    public string $xsdPath;
    public string $content;
    public bool $contentReady = false;
    public $errors = [];

    function __construct(string $xmlFilePath) {
        $this->filePath = $xmlFilePath;
        //set names
        try {
            $path_parts = pathinfo($this->filePath);
            $this->fileName = $path_parts['basename'];
            $this->fileNameNoExt = $path_parts['filename'];
            $this->fileDir = $path_parts['dirname'];
        } catch (Exception $ex) {
            $this->errors[] = "Error while parsing path of file $this->xmlFilePath";
            return;
        }
    }

    /**
     * Is uppercased filename with extension matches regexPattern, last checked regexPattern stored in regexPattern field.
     * regexPattern example: any xml - /.{0,}\.XML/
     * @param string $regexPattern
     * @return bool
     */
    public function isValidByRegexPattern(string $regexPattern): bool {
        $this->regexPattern = $regexPattern;
        if (preg_match($this->regexPattern, strtoupper($this->fileName))) {
            return true;
        }
        return false;
    }

    /**
     * Reads xml to $this->content.
     * Tip. If you want to validate xml by xsd do it first, because content is stored in Ram. There is no point to store it, if you can have errors in it.
     * @return bool
     */
    public function readContent(): bool {
        //load document
        try {
            $domDocument = new \DOMDocument();
            if ($domDocument->load($this->filePath)) {
                $this->content = $domDocument->saveXML($domDocument->documentElement);
            } else {
                $this->errors[] = "Error while DOMDocument->load($this->filePath)";
                return false;
            }
        } catch (Exception $ex) {
            $this->errors[] = "Exception while DOMDocument->load($this->filePath)" . PHP_EOL . $ex;
            return false;
        }
        $this->contentReady = true;
        return true;
    }

    /**
     * Validates xml by XSD
     * @param string $xsdPath
     * @return boolean
     */
    public function isValidByXsd(string $xsdPath) {
        $this->xsdPath = $xsdPath;
        try {
//validate xml
            $validator = new XmlValidator($this->filePath, $xsdPath);
            if (!$validator->isValidByXsd()) {
                $this->errors = $validator->errors;
                return false;
            }
        } catch (Exception $ex) {
            $this->errors[] = 'Exception while validating document\r\n' . $ex;
            return false;
        }
        return true;
    }

}
