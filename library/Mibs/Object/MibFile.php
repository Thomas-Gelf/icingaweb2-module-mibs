<?php

namespace Icinga\Module\Mibs\Object;

use gipfl\Json\JsonString;
use Icinga\Module\Director\Data\Db\DbObject;

class MibFile extends DbObject
{
    const TABLE = 'snmp_mib_file';
    protected $table = self::TABLE;

    protected $keyName = 'mib_file_checksum';

    protected $defaultProperties = [
        'mib_file_checksum' => null,
        'mib_checksum'      => null,
        'content'           => null,
        'file_size'         => null,
        'parsed_mib'        => null,
        'last_processing_error' => null,
    ];

    protected $parsedMib;

    public static function fromFileString(string $content): MibFile
    {
        return MibFile::create([
            'mib_file_checksum' => sha1($content, true),
            'file_size'         => strlen($content),
            'content'           => $content
        ]);
    }

    public function setParsedMib($parsed)
    {
        self::sortStructure($parsed);
        $parsedJson = JsonString::encode($parsed);
        $this->set('parsed_mib', $parsedJson);
        $this->set('last_processing_error', null);
        $this->set('mib_checksum', sha1($parsedJson, true));
    }

    public function set($key, $value)
    {
        if ($key === 'parsed_mib') {
            $this->parsedMib = null;
        }

        return parent::set($key, $value); // TODO: Change the autogenerated stub
    }

    public function getParsedMib()
    {
        if ($this->parsedMib === null) {
            $mib = $this->get('parsed_mib');
            if ($mib !== null) {
                $this->parsedMib = JsonString::decode($mib);
            }
        }

        return $this->parsedMib;
    }

    public function hasProcessingErrors(): bool
    {
        return $this->get('last_processing_error') !== null;
    }

    public function getLastProcessingError(): ?string
    {
        return $this->get('last_processing_error');
    }

    protected static function sortStructure(&$any)
    {
        if (is_array($any)) {
            ksort($any);
            foreach ($any as &$value) {
                self::sortStructure($value);
            }
        } elseif (is_object($any)) {
            $array = (array)$any;
            ksort($array);
            foreach ($array as &$value) {
                self::sortStructure($value);
            }
            $any = (object) $array;
        }
    }
}