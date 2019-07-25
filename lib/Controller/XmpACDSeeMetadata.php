<?php
namespace OCA\Metadata\Controller;

class XmpACDSeeMetadata {
    const EL_ACDSEE_AUTHOR = 'acdsee:author';
    const EL_ACDSEE_CAPTION = 'acdsee:caption';
    const EL_ACDSEE_CATEGORIES = 'acdsee:categories';
    const EL_ACDSEE_DATETIME = 'acdsee:datetime';
    const EL_ACDSEE_NOTES = 'acdsee:notes';
    const EL_ACDSEE_RATING = 'acdsee:rating';
    const EL_ACDSEE_TAGGED = 'acdsee:tagged';
    const EL_ACDSEE_COLLECTIONS = 'acdsee:collections';

    private $parser;
    private $text;
    private $data = array();
    private $context = array();
    private $rsName = null;
    private $rsType = null;

    private function __construct() {
        $this->parser = xml_parser_create('UTF-8');
        xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($this->parser, XML_OPTION_SKIP_WHITE, 0);
        xml_set_element_handler($this->parser, array($this, 'startElement'), array($this, 'endElement'));
        xml_set_character_data_handler($this->parser, array($this, 'charData'));
    }

    public function __destruct() {
        if (is_resource($this->parser)) {
            xml_parser_free($this->parser);
        }
    }

    public static function fromData($xml) {
        $obj = new XmpACDSeeMetadata();
        xml_parse($obj->parser, $xml, true);

        return $obj;
    }

    public static function fromFile($file) {
        if ($hnd = fopen($file, 'rb')) {
            try {
                $obj = new XmpACDSeeMetadata();

                while (($data = fread($hnd, 8192))) {
                    xml_parse($obj->parser, $data);
                }

                xml_parse($obj->parser, '', true);

                return $obj;

            } finally {
                fclose($hnd);
            }
        }
    }

    public function getArray() {
        return $this->data;
    }

    public function startElement($parser, $name, array $attributes) {
        $this->text = null;

        switch ($name) {
            // Elements to remember
            case self::EL_ACDSEE_AUTHOR:
            case self::EL_ACDSEE_CAPTION:
            case self::EL_ACDSEE_CATEGORIES:
            case self::EL_ACDSEE_DATETIME:
            case self::EL_ACDSEE_NOTES:
            case self::EL_ACDSEE_RATING:
            case self::EL_ACDSEE_TAGGED:
            case self::EL_ACDSEE_COLLECTIONS:
                $this->contextPush($name);
                break;
        }
    }

    public function endElement($parser, $name) {
        if ($this->contextPeek() === $name) {
            $this->contextPop();
        }

        switch ($name) {
            case self::EL_ACDSEE_AUTHOR:
            case self::EL_ACDSEE_CAPTION:
            case self::EL_ACDSEE_DATETIME:
            case self::EL_ACDSEE_NOTES:
            case self::EL_ACDSEE_RATING:
            case self::EL_ACDSEE_TAGGED:
            case self::EL_ACDSEE_COLLECTIONS:
                $this->addVal($this->formatKey($name), $this->text);
                break;
            case self::EL_ACDSEE_CATEGORIES:
                $this->addEncVal($this->formatKey($name), $this->text);
                break;
        }
    }

    public function addEncVal($key, &$value){
        if(!empty($value)){
            $xmlparser = xml_parser_create();
            $categories = array();
            xml_parse_into_struct($xmlparser, $value, $categories);
            $this->data[$key][] = $categories;
            xml_parser_free($xmlparser);
        }
    }

    public function charData($parser, $data) {
            $this->text .= $data;
    }

    protected function addValIfExists($key, &$attributes) {
        if (array_key_exists($key, $attributes)) {
            $this->addVal($this->formatKey($key), $attributes[$key]);
        }
    }

    protected function addVal($key, &$value) {
        if (!empty($value)) {
            if (!array_key_exists($key, $this->data)) {
                $this->data[$key] = array($value);

            } else {
                $this->data[$key][] = $value;
            }
        }
    }

    protected function addHierVal($key, &$value) {
        if (!array_key_exists($key, $this->data)) {
            $this->data[$key] = array($value);

        } else {
            if ((($prevIdx = count($this->data[$key]) - 1) >= 0) && (($prevVal = $this->data[$key][$prevIdx]) === substr($value, 0, strlen($prevVal)))) {
                $this->data[$key][$prevIdx] = $value;   // replace parent

            } else {
                $this->data[$key][] = $value;
            }
        }
    }

    protected function formatKey($key) {
        $pos = strrpos($key, ':');
        if ($pos !== false) {
            $key = substr($key, $pos + 1);
        }

        return lcfirst($key);
    }

    protected function contextPush($var) {
        array_push($this->context, $var);
    }

    protected function contextPop() {
        return array_pop($this->context);
    }

    protected function contextPeek() {
        return empty($this->context) ? null : array_values(array_slice($this->context, -1))[0];
    }
}